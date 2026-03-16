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
 * @group      quizaccess_proview
 */

namespace quizaccess_proview;

/**
 * Tests for {@see \quizaccess_proview\token_manager}.
 *
 * The authfn constructor parameter is used to inject a closure instead of
 * making real HTTP calls, so every test runs fully in-process.
 *
 * Cache behaviour (hit / miss / invalidate) is exercised against Moodle's
 * real application cache — the database is reset after each test via
 * resetAfterTest().
 *
 * @covers \quizaccess_proview\token_manager
 */
final class token_manager_test extends \advanced_testcase {
    /**
     * Set all three admin config values needed by build_cache_key().
     *
     * @param string $url      Admin URL config value.
     * @param string $username Admin username config value.
     * @param string $password Admin password config value.
     */
    private function set_admin_config(
        string $url = 'https://lms.example.test',
        string $username = 'testuser',
        string $password = 'testpass'
    ): void {
        set_config('proview_admin_url', $url, 'quizaccess_proview');
        set_config('proview_admin_username', $username, 'quizaccess_proview');
        set_config('proview_admin_password', $password, 'quizaccess_proview');
    }

    /**
     * Return a counting authfn closure.
     *
     * @param int    $count Incremented on each call (passed by reference).
     * @param string $token Token to return.
     * @return callable
     */
    private function make_authfn(int &$count, string $token = 'dummy-token'): callable {
        return function () use (&$count, $token): string {
            $count++;
            return $token;
        };
    }

    /**
     * On a cold cache get_token() must call the authfn exactly once.
     */
    public function test_get_token_calls_authfn_on_cache_miss(): void {
        $this->resetAfterTest();
        $this->set_admin_config();

        $count = 0;
        $manager = new token_manager($this->make_authfn($count, 'fresh-token'));

        $token = $manager->get_token();

        $this->assertSame('fresh-token', $token);
        $this->assertSame(1, $count);
    }

    /**
     * A second get_token() call must return the cached value without re-calling authfn.
     */
    public function test_get_token_returns_cached_value_on_subsequent_calls(): void {
        $this->resetAfterTest();
        $this->set_admin_config();

        $count = 0;
        $manager = new token_manager($this->make_authfn($count, 'cached-token'));

        $first = $manager->get_token();
        $second = $manager->get_token();
        $third = $manager->get_token();

        $this->assertSame('cached-token', $first);
        $this->assertSame('cached-token', $second);
        $this->assertSame('cached-token', $third);
        $this->assertSame(1, $count, 'Authfn must be called only once across multiple get_token() calls.');
    }

    /**
     * After invalidate(), the next get_token() must call authfn again.
     */
    public function test_invalidate_causes_reauthentication(): void {
        $this->resetAfterTest();
        $this->set_admin_config();

        $count = 0;
        $authfn = function () use (&$count): string {
            $count++;
            return 'token-' . $count;
        };
        $manager = new token_manager($authfn);

        $first = $manager->get_token();
        $manager->invalidate();
        $second = $manager->get_token();

        $this->assertSame('token-1', $first);
        $this->assertSame('token-2', $second);
        $this->assertSame(2, $count);
    }

    /**
     * A get_token() call immediately after invalidate() must return a fresh token.
     */
    public function test_invalidate_then_get_returns_new_token(): void {
        $this->resetAfterTest();
        $this->set_admin_config();

        $seq = 0;
        $manager = new token_manager(function () use (&$seq): string {
            return 'v' . (++$seq);
        });

        $manager->get_token();
        $manager->invalidate();
        $token = $manager->get_token();

        $this->assertSame('v2', $token);
    }

    /**
     * Multiple invalidate() calls must be idempotent and not throw.
     */
    public function test_multiple_invalidate_calls_are_idempotent(): void {
        $this->resetAfterTest();
        $this->set_admin_config();

        $count = 0;
        $manager = new token_manager($this->make_authfn($count));

        $manager->get_token();
        $manager->invalidate();
        $manager->invalidate();
        $manager->get_token();

        $this->assertSame(2, $count);
    }

    /**
     * Changing admin credentials must result in a cache miss (different key),
     * so the new token_manager does not return the stale token.
     */
    public function test_cache_key_changes_when_credentials_change(): void {
        $this->resetAfterTest();

        $this->set_admin_config('https://lms.example.test', 'usera', 'passa');

        $callsa = 0;
        $managera = new token_manager($this->make_authfn($callsa, 'token-a'));
        $managera->get_token();

        $this->set_admin_config('https://lms.example.test', 'userb', 'passb');

        $callsb = 0;
        $managerb = new token_manager($this->make_authfn($callsb, 'token-b'));
        $tokenb = $managerb->get_token();

        $this->assertSame('token-b', $tokenb);
        $this->assertSame(1, $callsa, 'First manager authfn should have been called once.');
        $this->assertSame(1, $callsb, 'Second manager must not reuse the first cache key.');
    }

    /**
     * Changing only the admin URL must also produce a different cache key.
     */
    public function test_cache_key_changes_when_url_changes(): void {
        $this->resetAfterTest();

        $this->set_admin_config('https://old.example.test');

        $callsone = 0;
        $managerone = new token_manager($this->make_authfn($callsone, 'old-token'));
        $managerone->get_token();

        $this->set_admin_config('https://new.example.test');

        $callstwo = 0;
        $managertwo = new token_manager($this->make_authfn($callstwo, 'new-token'));
        $tokentwo = $managertwo->get_token();

        $this->assertSame('new-token', $tokentwo);
        $this->assertSame(1, $callstwo);
    }

    /**
     * When no authfn is passed the constructor should not throw — it wires
     * api::authenticate() as the default. We do not invoke get_token() here
     * as that would make a real HTTP call; we only verify object construction.
     */
    public function test_constructor_without_authfn_does_not_throw(): void {
        $this->resetAfterTest();
        $this->set_admin_config();

        $manager = new token_manager();
        $this->assertInstanceOf(token_manager::class, $manager);
    }

    /**
     * If authfn throws, get_token() must propagate the exception.
     */
    public function test_get_token_propagates_authfn_exception(): void {
        $this->resetAfterTest();
        $this->set_admin_config();

        $authfn = function (): string {
            throw new \moodle_exception('proview_auth_failed', 'quizaccess_proview');
        };
        $manager = new token_manager($authfn);

        $this->expectException(\moodle_exception::class);
        $manager->get_token();
    }

    /**
     * After a failed get_token() call, a subsequent call with a working authfn
     * must succeed as nothing stale should be cached.
     */
    public function test_failed_get_token_does_not_cache_bad_state(): void {
        $this->resetAfterTest();
        $this->set_admin_config();

        $shouldfail = true;
        $calls = 0;
        $authfn = function () use (&$shouldfail, &$calls): string {
            $calls++;
            if ($shouldfail) {
                throw new \moodle_exception('proview_auth_failed', 'quizaccess_proview');
            }
            return 'recovered-token';
        };

        $manager = new token_manager($authfn);

        try {
            $manager->get_token();
        } catch (\moodle_exception $e) {
            $this->assertInstanceOf(\moodle_exception::class, $e);
        }

        $shouldfail = false;
        $token = $manager->get_token();

        $this->assertSame('recovered-token', $token);
        $this->assertSame(2, $calls);
    }
}
