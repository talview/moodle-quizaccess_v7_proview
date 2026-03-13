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
 * Test double for quizaccess_proview\api.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_proview;

/**
 * Subclass of api that overrides make_request() so no real HTTP calls are made.
 *
 * Usage:
 *   api_testable::prime(['key' => 'value']);    // next call returns this array
 *   api_testable::prime_error('HTTP 500 ...');  // next call throws moodle_exception
 *   $call = api_testable::last_call();          // inspect method/url/headers/body
 */
class api_testable extends api {
    /** @var array|null Preset return value for the next make_request call. */
    private static ?array $nextresponse = null;

    /** @var string|null If set, make_request throws with this detail string. */
    private static ?string $nexterror = null;

    /** @var array|null Arguments captured from the last make_request call. */
    private static ?array $lastcall = null;

    /**
     * Prime a successful response for the next call.
     *
     * @param array $response Response to return.
     */
    public static function prime(array $response): void {
        self::$nextresponse = $response;
        self::$nexterror    = null;
    }

    /**
     * Prime an error for the next call.
     *
     * @param string $detail Error detail string included in the moodle_exception.
     */
    public static function prime_error(string $detail): void {
        self::$nexterror    = $detail;
        self::$nextresponse = null;
    }

    /**
     * Return the arguments captured by the last make_request invocation.
     *
     * @return array|null Keys: method, url, headers, body.
     */
    public static function last_call(): ?array {
        return self::$lastcall;
    }

    /**
     * Reset all static state between tests.
     */
    public static function reset_state(): void {
        self::$nextresponse = null;
        self::$nexterror    = null;
        self::$lastcall     = null;
    }

    /**
     * Override of the HTTP transport — returns preset data without making real HTTP calls.
     *
     * @param string     $method  HTTP method.
     * @param string     $url     Request URL.
     * @param string[]   $headers Request headers.
     * @param array|null $body    Request body.
     * @return array Preset response array.
     * @throws \moodle_exception When prime_error() was called.
     */
    protected static function make_request(string $method, string $url, array $headers, ?array $body): array {
        self::$lastcall = compact('method', 'url', 'headers', 'body');

        if (self::$nexterror !== null) {
            throw new \moodle_exception('proview_api_error', 'quizaccess_proview', '', self::$nexterror);
        }

        return self::$nextresponse ?? [];
    }
}
