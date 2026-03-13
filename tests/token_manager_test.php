<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * PHPUnit tests for quizaccess_proview\token_manager.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_proview\token_manager
 */

namespace quizaccess_proview;

/**
 * Unit tests for {@see \quizaccess_proview\token_manager}.
 *
 * @covers \quizaccess_proview\token_manager
 */
final class token_manager_test extends \advanced_testcase {
    /** @var string Base URL of the Proview admin service used in test config. */
    private const ADMIN_URL = 'https://appv7.proview.io/embedded';

    /** @var string Username used in test admin config. */
    private const ADMIN_USER = 'testuser';

    /** @var string Password used in test admin config. */
    private const ADMIN_PASS = 'testpass';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        set_config('proview_admin_url', self::ADMIN_URL, 'quizaccess_proview');
        set_config('proview_admin_username', self::ADMIN_USER, 'quizaccess_proview');
        set_config('proview_admin_password', self::ADMIN_PASS, 'quizaccess_proview');
    }

    /**
     * Return a token_manager with the given callable injected.
     *
     * @param callable $authfn
     * @return token_manager
     */
    private function make_manager(callable $authfn): token_manager {
        return new token_manager($authfn);
    }

    /**
     * Auth callable that always returns the given token without any side effects.
     *
     * @param string $token Token to return.
     * @return callable
     */
    private function auth_returning(string $token): callable {
        return static function () use ($token): string {
            return $token;
        };
    }

    /**
     * Auth callable that tracks how many times it has been invoked.
     *
     * @param string $token  Token to return.
     * @param int    &$count Reference incremented on each invocation.
     * @return callable
     */
    private function auth_counting(string $token, int &$count): callable {
        return static function () use ($token, &$count): string {
            $count++;
            return $token;
        };
    }

    /**
     * On the first call with an empty cache the auth callable is invoked once
     * and the returned token is passed back to the caller.
     */
    public function test_get_token_fetches_when_cache_miss(): void {
        $calls = 0;
        $manager = $this->make_manager($this->auth_counting('tok-fresh', $calls));

        $token = $manager->get_token();

        $this->assertEquals('tok-fresh', $token);
        $this->assertEquals(1, $calls, 'auth callable must be called exactly once on cache miss');
    }

    /**
     * A second call reuses the cached token — auth callable is NOT invoked again.
     */
    public function test_get_token_returns_cached_token(): void {
        $calls = 0;
        $manager = $this->make_manager($this->auth_counting('tok-cached', $calls));

        $manager->get_token();
        $token = $manager->get_token();

        $this->assertEquals('tok-cached', $token);
        $this->assertEquals(1, $calls, 'auth callable must not be called on cache hit');
    }

    /**
     * After invalidate() the next get_token() call re-authenticates.
     */
    public function test_invalidate_causes_refetch(): void {
        $calls = 0;
        $manager = $this->make_manager($this->auth_counting('tok-refreshed', $calls));

        $manager->get_token();
        $manager->invalidate();
        $token = $manager->get_token();

        $this->assertEquals('tok-refreshed', $token);
        $this->assertEquals(2, $calls, 'auth callable must be called again after invalidate');
    }

    /**
     * A second token_manager with the same credentials shares the same cache
     * entry — the token fetched by the first is returned by the second without
     * a fresh auth call.
     */
    public function test_same_credentials_share_cache(): void {
        $calls1 = 0;
        $calls2 = 0;

        $manager1 = $this->make_manager($this->auth_counting('shared-tok', $calls1));
        $manager2 = $this->make_manager($this->auth_counting('should-not-be-used', $calls2));

        $manager1->get_token();
        $token = $manager2->get_token();

        $this->assertEquals('shared-tok', $token);
        $this->assertEquals(0, $calls2, 'second manager must not call auth if cache is warm');
    }

    /**
     * Changing admin credentials produces a different cache key, forcing a new
     * auth call even if the old token is still cached.
     */
    public function test_different_credentials_produce_different_cache_key(): void {
        $callsfirst = 0;
        $callssecond = 0;

        $managerfirst = $this->make_manager($this->auth_counting('tok-a', $callsfirst));
        $managerfirst->get_token();

        set_config('proview_admin_username', 'otheruser', 'quizaccess_proview');
        set_config('proview_admin_password', 'otherpass', 'quizaccess_proview');

        $managersecond = $this->make_manager($this->auth_counting('tok-b', $callssecond));
        $token = $managersecond->get_token();

        $this->assertEquals('tok-b', $token);
        $this->assertEquals(1, $callssecond, 'new credentials must trigger a fresh auth call');
    }

    /**
     * The token is never written into plugin config.
     */
    public function test_token_not_written_to_config(): void {
        $manager = $this->make_manager($this->auth_returning('secret-tok'));
        $manager->get_token();

        $this->assertFalse(
            get_config('quizaccess_proview', 'token'),
            'token must never be persisted in plugin config'
        );
        $this->assertFalse(
            get_config('quizaccess_proview', 'proview_token'),
            'token must never be persisted in plugin config'
        );
    }

    /**
     * If the auth callable throws, get_token() propagates the exception without
     * caching anything.
     */
    public function test_auth_exception_propagates_and_is_not_cached(): void {
        $calls = 0;
        $authfn = static function () use (&$calls): string {
            $calls++;
            throw new \moodle_exception('proview_auth_failed', 'quizaccess_proview');
        };

        $manager = $this->make_manager($authfn);

        $this->expectException(\moodle_exception::class);
        $manager->get_token();
    }

    /**
     * After a failed auth (exception), a subsequent get_token() call retries auth.
     */
    public function test_get_token_retries_after_auth_exception(): void {
        $attempt = 0;
        $authfn = static function () use (&$attempt): string {
            $attempt++;
            if ($attempt === 1) {
                throw new \moodle_exception('proview_auth_failed', 'quizaccess_proview');
            }
            return 'tok-retry';
        };

        $manager = $this->make_manager($authfn);

        $caught = false;
        try {
            $manager->get_token();
        } catch (\moodle_exception $e) {
            $caught = true;
        }
        $this->assertTrue($caught, 'First call must throw when auth fails.');

        $token = $manager->get_token();

        $this->assertEquals('tok-retry', $token);
        $this->assertEquals(2, $attempt);
    }
}
