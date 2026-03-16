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
 * @group      quizaccess_proview
 */

namespace quizaccess_proview;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/api_testable.php');

use quizaccess_proview\tests\api_testable;

/**
 * Tests for {@see \quizaccess_proview\api}.
 *
 * All HTTP calls are intercepted by api_testable which overrides make_request().
 * No real network traffic is made during these tests.
 */
final class api_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        api_testable::reset();
    }

    protected function tearDown(): void {
        api_testable::reset();
        parent::tearDown();
    }

    /**
     * get_organizations() must send a GET to /organizations.
     */
    public function test_get_organizations_uses_get_method(): void {
        $this->resetAfterTest();
        api_testable::$mockresponse = [];

        api_testable::get_organizations();

        $this->assertSame('GET', api_testable::$calls[0]['method']);
    }

    /**
     * get_organizations() must call the /organizations endpoint.
     */
    public function test_get_organizations_calls_correct_url(): void {
        $this->resetAfterTest();
        api_testable::$mockresponse = [];

        api_testable::get_organizations();

        $this->assertStringEndsWith('/organizations', api_testable::$calls[0]['url']);
    }

    /**
     * get_organizations() must include the app-id header derived from wwwroot.
     */
    public function test_get_organizations_sends_app_id_header(): void {
        global $CFG;
        $this->resetAfterTest();

        $expectedappid = md5($CFG->wwwroot);
        api_testable::$mockresponse = [];

        api_testable::get_organizations();

        $this->assertContains(
            'app-id: ' . $expectedappid,
            api_testable::$calls[0]['headers']
        );
    }

    /**
     * app_id must be the MD5 hash of $CFG->wwwroot, not any other value.
     */
    public function test_app_id_is_md5_of_wwwroot(): void {
        global $CFG;
        $this->resetAfterTest();

        $original = $CFG->wwwroot;
        $CFG->wwwroot = 'https://moodle.example.test';
        api_testable::$mockresponse = [];

        api_testable::get_organizations();

        $CFG->wwwroot = $original;

        $this->assertContains(
            'app-id: ' . md5('https://moodle.example.test'),
            api_testable::$calls[0]['headers']
        );
    }

    /**
     * get_organizations() must return the decoded array from the API.
     */
    public function test_get_organizations_returns_response_array(): void {
        $this->resetAfterTest();
        $orgs = [['id' => 1, 'name' => 'Talview'], ['id' => 2, 'name' => 'Acme']];
        api_testable::$mockresponse = $orgs;

        $result = api_testable::get_organizations();

        $this->assertSame($orgs, $result);
    }

    /**
     * get_organizations() must propagate exceptions from make_request().
     */
    public function test_get_organizations_propagates_exception(): void {
        $this->resetAfterTest();
        api_testable::$mockexception = new \moodle_exception(
            'proview_api_error',
            'quizaccess_proview'
        );

        $this->expectException(\moodle_exception::class);
        api_testable::get_organizations();
    }

    /**
     * authenticate() must POST to /auth.
     */
    public function test_authenticate_uses_post_method(): void {
        $this->resetAfterTest();
        set_config('proview_admin_username', 'admin', 'quizaccess_proview');
        set_config('proview_admin_password', 'secret', 'quizaccess_proview');
        api_testable::$mockresponse = ['access_token' => 'tok'];

        api_testable::authenticate();

        $this->assertSame('POST', api_testable::$calls[0]['method']);
    }

    /**
     * authenticate() must call the /auth endpoint.
     */
    public function test_authenticate_calls_auth_url(): void {
        $this->resetAfterTest();
        set_config('proview_admin_username', 'admin', 'quizaccess_proview');
        set_config('proview_admin_password', 'secret', 'quizaccess_proview');
        api_testable::$mockresponse = ['access_token' => 'tok'];

        api_testable::authenticate();

        $this->assertStringEndsWith('/auth', api_testable::$calls[0]['url']);
    }

    /**
     * authenticate() must include admin credentials in the request body.
     */
    public function test_authenticate_sends_credentials_in_body(): void {
        $this->resetAfterTest();
        set_config('proview_admin_username', 'myuser', 'quizaccess_proview');
        set_config('proview_admin_password', 'mypass', 'quizaccess_proview');
        api_testable::$mockresponse = ['access_token' => 'tok'];

        api_testable::authenticate();

        $body = api_testable::$calls[0]['body'];
        $this->assertSame('myuser', $body['username']);
        $this->assertSame('mypass', $body['password']);
    }

    /**
     * authenticate() must return the access_token string from the response.
     */
    public function test_authenticate_returns_access_token(): void {
        $this->resetAfterTest();
        set_config('proview_admin_username', 'admin', 'quizaccess_proview');
        set_config('proview_admin_password', 'secret', 'quizaccess_proview');
        api_testable::$mockresponse = ['access_token' => 'eyJhbGciOiJSUzI1Ni.example'];

        $token = api_testable::authenticate();

        $this->assertSame('eyJhbGciOiJSUzI1Ni.example', $token);
    }

    /**
     * authenticate() must throw moodle_exception when access_token is absent.
     */
    public function test_authenticate_throws_when_access_token_missing(): void {
        $this->resetAfterTest();
        set_config('proview_admin_username', 'admin', 'quizaccess_proview');
        set_config('proview_admin_password', 'secret', 'quizaccess_proview');
        api_testable::$mockresponse = ['error' => 'invalid_credentials'];

        $this->expectException(\moodle_exception::class);
        api_testable::authenticate();
    }

    /**
     * authenticate() must throw when access_token is an empty string.
     */
    public function test_authenticate_throws_when_access_token_empty(): void {
        $this->resetAfterTest();
        set_config('proview_admin_username', 'admin', 'quizaccess_proview');
        set_config('proview_admin_password', 'secret', 'quizaccess_proview');
        api_testable::$mockresponse = ['access_token' => ''];

        $this->expectException(\moodle_exception::class);
        api_testable::authenticate();
    }

    /**
     * authenticate() must propagate transport exceptions from make_request().
     */
    public function test_authenticate_propagates_transport_exception(): void {
        $this->resetAfterTest();
        set_config('proview_admin_username', 'admin', 'quizaccess_proview');
        set_config('proview_admin_password', 'secret', 'quizaccess_proview');
        api_testable::$mockexception = new \moodle_exception(
            'proview_api_error',
            'quizaccess_proview',
            '',
            'HTTP 503 from https://lms-connector.proview.io/auth'
        );

        $this->expectException(\moodle_exception::class);
        api_testable::authenticate();
    }

    /**
     * get_proview_tokens() must use GET.
     */
    public function test_get_proview_tokens_uses_get_method(): void {
        $this->resetAfterTest();
        api_testable::$mockresponse = [];

        api_testable::get_proview_tokens('bearer-abc');

        $this->assertSame('GET', api_testable::$calls[0]['method']);
    }

    /**
     * get_proview_tokens() must call the /proview endpoint.
     */
    public function test_get_proview_tokens_calls_proview_url(): void {
        $this->resetAfterTest();
        api_testable::$mockresponse = [];

        api_testable::get_proview_tokens('bearer-abc');

        $this->assertStringEndsWith('/proview', api_testable::$calls[0]['url']);
    }

    /**
     * get_proview_tokens() must pass the bearer token as an Authorization header.
     */
    public function test_get_proview_tokens_sends_authorization_header(): void {
        $this->resetAfterTest();
        api_testable::$mockresponse = [];

        api_testable::get_proview_tokens('my-bearer-token');

        $this->assertContains(
            'Authorization: Bearer my-bearer-token',
            api_testable::$calls[0]['headers']
        );
    }

    /**
     * get_proview_tokens() must return the response array.
     */
    public function test_get_proview_tokens_returns_response(): void {
        $this->resetAfterTest();
        $tokens = [['proview_token' => 'ptok1'], ['proview_token' => 'ptok2']];
        api_testable::$mockresponse = $tokens;

        $result = api_testable::get_proview_tokens('any-bearer');

        $this->assertSame($tokens, $result);
    }

    /**
     * get_quiz() must use GET.
     */
    public function test_get_quiz_uses_get_method(): void {
        $this->resetAfterTest();
        api_testable::$mockresponse = [];

        api_testable::get_quiz('tok', 42);

        $this->assertSame('GET', api_testable::$calls[0]['method']);
    }

    /**
     * get_quiz() must embed the quizid in the URL.
     */
    public function test_get_quiz_builds_url_with_quizid(): void {
        $this->resetAfterTest();
        api_testable::$mockresponse = [];

        api_testable::get_quiz('tok', 99);

        $this->assertStringEndsWith('/quiz/99', api_testable::$calls[0]['url']);
    }

    /**
     * get_quiz() must send the Authorization header.
     */
    public function test_get_quiz_sends_authorization_header(): void {
        $this->resetAfterTest();
        api_testable::$mockresponse = [];

        api_testable::get_quiz('quiz-bearer', 1);

        $this->assertContains(
            'Authorization: Bearer quiz-bearer',
            api_testable::$calls[0]['headers']
        );
    }

    /**
     * get_quiz() must return the response array.
     */
    public function test_get_quiz_returns_response(): void {
        $this->resetAfterTest();
        $quizconfig = ['quizid' => 7, 'proctortype' => 'ai'];
        api_testable::$mockresponse = $quizconfig;

        $result = api_testable::get_quiz('tok', 7);

        $this->assertSame($quizconfig, $result);
    }

    /**
     * save_quiz() must use POST.
     */
    public function test_save_quiz_uses_post_method(): void {
        $this->resetAfterTest();
        api_testable::$mockresponse = ['success' => true];

        api_testable::save_quiz('tok', ['quizid' => 1]);

        $this->assertSame('POST', api_testable::$calls[0]['method']);
    }

    /**
     * save_quiz() must call the /quiz endpoint.
     */
    public function test_save_quiz_calls_quiz_url(): void {
        $this->resetAfterTest();
        api_testable::$mockresponse = ['success' => true];

        api_testable::save_quiz('tok', ['quizid' => 1]);

        $this->assertStringEndsWith('/quiz', api_testable::$calls[0]['url']);
    }

    /**
     * save_quiz() must send the Authorization header.
     */
    public function test_save_quiz_sends_authorization_header(): void {
        $this->resetAfterTest();
        api_testable::$mockresponse = ['success' => true];

        api_testable::save_quiz('save-bearer', ['quizid' => 1]);

        $this->assertContains(
            'Authorization: Bearer save-bearer',
            api_testable::$calls[0]['headers']
        );
    }

    /**
     * save_quiz() must forward the quiz data as the request body.
     */
    public function test_save_quiz_sends_quiz_data_as_body(): void {
        $this->resetAfterTest();
        api_testable::$mockresponse = ['success' => true];

        $quizdata = [
            'quizid' => 10,
            'cmid' => 5,
            'proctortype' => 'live',
            'tsbenabled' => 1,
            'candidateinstructions' => 'Please enable your webcam.',
            'minimizepermitted' => 0,
            'screenprotection' => 1,
        ];

        api_testable::save_quiz('tok', $quizdata);

        $this->assertSame($quizdata, api_testable::$calls[0]['body']);
    }

    /**
     * save_quiz() must return the response array from the API.
     */
    public function test_save_quiz_returns_response(): void {
        $this->resetAfterTest();
        $apiresponse = ['id' => 42, 'status' => 'created'];
        api_testable::$mockresponse = $apiresponse;

        $result = api_testable::save_quiz('tok', ['quizid' => 1]);

        $this->assertSame($apiresponse, $result);
    }

    /**
     * save_quiz() must propagate transport exceptions.
     */
    public function test_save_quiz_propagates_exception(): void {
        $this->resetAfterTest();
        api_testable::$mockexception = new \moodle_exception(
            'proview_api_error',
            'quizaccess_proview',
            '',
            'HTTP 500 from https://lms-connector.proview.io/quiz'
        );

        $this->expectException(\moodle_exception::class);
        api_testable::save_quiz('tok', ['quizid' => 1]);
    }

    /**
     * The base URL constant must point to the production LMS Connector host.
     */
    public function test_lms_connector_base_constant(): void {
        $this->assertSame(
            'https://lms-connector.proview.io',
            \quizaccess_proview\api::LMS_CONNECTOR_BASE
        );
    }
}
