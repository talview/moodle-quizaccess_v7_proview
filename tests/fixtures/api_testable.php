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
 * Test fixture: controllable subclass of api for unit tests.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_proview\tests;

/**
 * Test double for {@see \quizaccess_proview\api}.
 *
 * Overrides make_request() so unit tests never touch the network.
 * Configure the static properties before each assertion, then call reset()
 * in tearDown / setUp.
 *
 * Usage:
 *   api_testable::$mockresponse = ['access_token' => 'tok'];
 *   $token = api_testable::authenticate();
 *
 * To simulate a transport failure:
 *   api_testable::$mockexception = new \moodle_exception('proview_api_error', 'quizaccess_proview');
 *   api_testable::get_organizations(); // throws.
 */
class api_testable extends \quizaccess_proview\api {
    /**
     * @var array|null Preset decoded-JSON response returned by make_request().
     *                 Null means return an empty array.
     */
    public static ?array $mockresponse = null;

    /**
     * @var \Throwable|null When non-null, make_request() throws this instead of
     *                      returning a response.
     */
    public static ?\Throwable $mockexception = null;

    /**
     * @var array[] Chronological log of every make_request() invocation.
     *              Each entry: ['method'=>string, 'url'=>string,
     *                           'headers'=>string[], 'body'=>array|null].
     */
    public static array $calls = [];

    /**
     * Reset all static state between tests.
     */
    public static function reset(): void {
        static::$mockresponse  = null;
        static::$mockexception = null;
        static::$calls         = [];
    }

    /**
     * Intercept HTTP calls: record the invocation, then return the preset
     * response or throw the preset exception.
     *
     * {@inheritdoc}
     */
    protected static function make_request(
        string $method,
        string $url,
        array $headers,
        ?array $body
    ): array {
        static::$calls[] = [
            'method'  => $method,
            'url'     => $url,
            'headers' => $headers,
            'body'    => $body,
        ];

        if (static::$mockexception !== null) {
            throw static::$mockexception;
        }

        return static::$mockresponse ?? [];
    }
}
