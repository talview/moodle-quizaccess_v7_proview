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
 * LMS Connector auth-token cache wrapper.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_proview;

/**
 * Thin cache wrapper for the LMS Connector bearer token.
 *
 * Does NOT know HTTP — delegates authentication to {@see api}.
 */
class token_manager {
    /** @var \cache Application cache instance. */
    private \cache $cache;

    /** @var string Cache key derived from current admin credentials. */
    private string $cachekey;

    /** @var callable Callable that returns a fresh bearer token string. */
    private $authfn;

    /**
     * Constructor.
     *
     * @param callable|null $authfn Optional override for the authenticate callable.
     *                              Defaults to {@see api::authenticate()}.
     *                              Inject a test double in unit tests.
     */
    public function __construct(?callable $authfn = null) {
        $this->cache    = \cache::make('quizaccess_proview', 'proview_auth_token');
        $this->cachekey = $this->build_cache_key();
        $this->authfn   = $authfn ?? [\quizaccess_proview\api::class, 'authenticate'];
    }

    /**
     * Return a valid bearer token, fetching and caching one if necessary.
     *
     * @return string Bearer token.
     * @throws \moodle_exception On authentication failure.
     */
    public function get_token(): string {
        $cached = $this->cache->get($this->cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $token = ($this->authfn)();
        $this->cache->set($this->cachekey, $token);
        return $token;
    }

    /**
     * Invalidate the cached token for the current admin credentials.
     *
     * Call this when an API request returns 401 so the next call will re-authenticate.
     */
    public function invalidate(): void {
        $this->cache->delete($this->cachekey);
    }

    /**
     * Build a stable, credential-scoped cache key.
     *
     * The key changes automatically when admin credentials or the callback URL
     * are updated in settings.
     *
     * @return string MD5 cache key.
     */
    private function build_cache_key(): string {
        $callbackurl   = (string) get_config('quizaccess_proview', 'proview_callback_url');
        $adminusername = (string) get_config('quizaccess_proview', 'proview_admin_username');
        $adminpassword = (string) get_config('quizaccess_proview', 'proview_admin_password');
        return md5($callbackurl . $adminusername . $adminpassword);
    }
}
