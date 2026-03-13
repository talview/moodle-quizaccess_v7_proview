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
 * PHPUnit tests for quizaccess_proview\api.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_proview\api
 */

namespace quizaccess_proview;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/api_testable.php');

/**
 * Unit tests for {@see \quizaccess_proview\api}.
 *
 * @covers \quizaccess_proview\api
 */
final class api_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        api_testable::reset_state();
    }

    /**
     * A successful response is parsed and returned as an array.
     */
    public function test_get_organizations_returns_list(): void {
        $orgs = [
            ['id' => 1, 'name' => 'Acme Corp', 'proview_organization_id' => 'acme'],
            ['id' => 2, 'name' => 'Beta Ltd', 'proview_organization_id' => 'beta'],
        ];
        api_testable::prime($orgs);

        $result = api_testable::get_organizations();

        $this->assertCount(2, $result);
        $this->assertEquals('Acme Corp', $result[0]['name']);
        $this->assertEquals('Beta Ltd', $result[1]['name']);
    }

    /**
     * An HTTP error propagates as moodle_exception with proview_api_error key.
     */
    public function test_get_organizations_http_error_throws(): void {
        api_testable::prime_error('HTTP 500 from /organizations');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/proview_api_error/');

        api_testable::get_organizations();
    }

    /**
     * The app-id header is set to md5($CFG->wwwroot).
     */
    public function test_app_id_is_md5_of_wwwroot(): void {
        global $CFG;

        $CFG->wwwroot = 'https://moodle.example.com';
        api_testable::prime([]);

        api_testable::get_organizations();

        $call = api_testable::last_call();
        $headers = $call['headers'];
        $this->assertContains('app-id: ' . md5('https://moodle.example.com'), $headers);
    }

    /**
     * get_organizations issues a GET request to /organizations.
     */
    public function test_get_organizations_uses_correct_url_and_method(): void {
        api_testable::prime([]);

        api_testable::get_organizations();

        $call = api_testable::last_call();
        $this->assertEquals('GET', $call['method']);
        $this->assertStringEndsWith('/organizations', $call['url']);
        $this->assertNull($call['body']);
    }

    /**
     * A valid response returns the access_token string.
     */
    public function test_authenticate_returns_token_string(): void {
        set_config('proview_admin_username', 'admin@example.com', 'quizaccess_proview');
        set_config('proview_admin_password', 's3cr3t', 'quizaccess_proview');

        api_testable::prime(['access_token' => 'tok-abc123']);

        $token = api_testable::authenticate();

        $this->assertEquals('tok-abc123', $token);
    }

    /**
     * An HTTP error propagates as moodle_exception.
     */
    public function test_authenticate_http_error_throws(): void {
        api_testable::prime_error('HTTP 503 from /auth');

        $this->expectException(\moodle_exception::class);

        api_testable::authenticate();
    }

    /**
     * A response without access_token throws proview_auth_failed.
     */
    public function test_authenticate_missing_token_throws_auth_failed(): void {
        api_testable::prime(['error' => 'invalid_credentials']);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/proview_auth_failed/');

        api_testable::authenticate();
    }

    /**
     * Credentials from admin settings are sent in the POST body.
     */
    public function test_authenticate_sends_credentials_in_body(): void {
        set_config('proview_admin_username', 'user@test.com', 'quizaccess_proview');
        set_config('proview_admin_password', 'pass123', 'quizaccess_proview');

        api_testable::prime(['access_token' => 'tok-xyz']);

        api_testable::authenticate();

        $call = api_testable::last_call();
        $this->assertEquals('POST', $call['method']);
        $this->assertStringEndsWith('/auth', $call['url']);
        $this->assertEquals('user@test.com', $call['body']['username']);
        $this->assertEquals('pass123', $call['body']['password']);
    }

    /**
     * Bearer token is sent as Authorization header.
     */
    public function test_get_proview_tokens_sends_bearer_header(): void {
        api_testable::prime([['id' => 1, 'token' => 'pv-token']]);

        api_testable::get_proview_tokens('my-bearer-token');

        $call = api_testable::last_call();
        $headers = $call['headers'];
        $this->assertContains('Authorization: Bearer my-bearer-token', $headers);
    }

    /**
     * Response is returned as-is.
     */
    public function test_get_proview_tokens_returns_list(): void {
        $data = [['id' => 1, 'type' => 'ai', 'name' => 'AI proctoring']];
        api_testable::prime($data);

        $result = api_testable::get_proview_tokens('tok');

        $this->assertEquals($data, $result);
    }

    /**
     * Quiz ID is appended to the URL.
     */
    public function test_get_quiz_uses_correct_url(): void {
        api_testable::prime(['quizid' => 42]);

        api_testable::get_quiz('tok', 42);

        $call = api_testable::last_call();
        $this->assertEquals('GET', $call['method']);
        $this->assertStringEndsWith('/quiz/42', $call['url']);
    }

    /**
     * save_quiz POSTs the quiz data with bearer auth.
     */
    public function test_save_quiz_posts_data_with_bearer_header(): void {
        $quizdata = [
            'quizid'      => 7,
            'cmid'        => 10,
            'proctortype' => 'ai',
            'tsbenabled'  => 1,
        ];
        api_testable::prime(['status' => 'ok']);

        api_testable::save_quiz('bearer-tok', $quizdata);

        $call = api_testable::last_call();
        $headers = $call['headers'];
        $this->assertEquals('POST', $call['method']);
        $this->assertStringEndsWith('/quiz', $call['url']);
        $this->assertContains('Authorization: Bearer bearer-tok', $headers);
        $this->assertEquals($quizdata, $call['body']);
    }
}
