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
 * LMS Connector API client for quizaccess_proview.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_proview;

/**
 * Static HTTP client for the Talview LMS Connector service.
 *
 * All configuration is read from Moodle admin settings via {@see get_config()}.
 * All HTTP calls use Moodle's built-in {@see \curl} wrapper.
 */
class api {
    /**
     * Return the LMS Connector base URL from admin settings.
     *
     * @return string Configured callback URL (no trailing slash).
     */
    protected static function get_base_url(): string {
        return (string) get_config('quizaccess_proview', 'proview_callback_url');
    }

    /**
     * Fetch the list of organisations for this Moodle site.
     *
     * GET /organization/application
     * Header: app-id: md5($CFG->wwwroot)
     *
     * @return array[] Array of organisation objects.
     * @throws \moodle_exception On HTTP error or non-200 response.
     */
    public static function get_organizations(): array {
        global $CFG;

        $url     = static::get_base_url() . '/organization/application';
        $parsed  = parse_url($CFG->wwwroot);
        $appid   = md5($parsed['scheme'] . '://' . $parsed['host']);
        $headers = ['app-id: ' . $appid];

        $response = static::make_request('GET', $url, $headers, null);
        return $response;
    }

    /**
     * Authenticate with the LMS Connector and return a bearer token.
     *
     * POST /auth
     * Body (JSON): { username, password }
     *
     * @return string Bearer access token.
     * @throws \moodle_exception On HTTP error or missing token in response.
     */
    public static function authenticate(): string {
        $username = (string) get_config('quizaccess_proview', 'proview_admin_username');
        $password = (string) get_config('quizaccess_proview', 'proview_admin_password');

        $url  = static::get_base_url() . '/auth';
        $body = ['username' => $username, 'password' => $password];

        $response = static::make_request('POST', $url, [], $body);

        if (empty($response['access_token'])) {
            throw new \moodle_exception(
                'proview_auth_failed',
                'quizaccess_proview'
            );
        }

        return (string) $response['access_token'];
    }

    /**
     * Fetch Proview token configurations for this site.
     *
     * GET /proview/token
     * Header: Authorization: Bearer {bearertoken}
     *
     * @param string $bearertoken Valid LMS Connector bearer token.
     * @return array[] Array of Proview token config objects.
     * @throws \moodle_exception On HTTP error.
     */
    public static function get_proview_tokens(string $bearertoken): array {
        $url     = static::get_base_url() . '/proview/token';
        $headers = ['Authorization: Bearer ' . $bearertoken];

        return static::make_request('GET', $url, $headers, null);
    }

    /**
     * Fetch the Proview configuration for a specific quiz.
     *
     * GET /quiz/{quizid}
     * Header: Authorization: Bearer {bearertoken}
     *
     * @param string $bearertoken Valid LMS Connector bearer token.
     * @param int    $quizid      Moodle quiz ID.
     * @return array Quiz config object.
     * @throws \moodle_exception On HTTP error.
     */
    public static function get_quiz(string $bearertoken, int $quizid): array {
        $url     = static::get_base_url() . '/quiz/' . $quizid;
        $headers = ['Authorization: Bearer ' . $bearertoken];

        return static::make_request('GET', $url, $headers, null);
    }

    /**
     * Create or update a quiz's Proview configuration on the LMS Connector.
     *
     * POST /quiz
     * Header: Authorization: Bearer {bearertoken}
     *
     * @param string $bearertoken Valid LMS Connector bearer token.
     * @param array  $quizdata    Quiz payload (see schema below).
     *
     * Expected keys in $quizdata:
     *   quizid, cmid, proctortype, tsbenabled, candidateinstructions,
     *   reference_link, blacklistedwindowssoftwares, blacklistedmacsoftwares,
     *   whitelistedwindowssoftwares, whitelistedmacsoftwares,
     *   minimizepermitted, screenprotection
     *
     * @return array API response.
     * @throws \moodle_exception On HTTP error.
     */
    public static function save_quiz(string $bearertoken, array $quizdata): array {
        $url     = static::get_base_url() . '/quiz';
        $headers = ['Authorization: Bearer ' . $bearertoken];

        return static::make_request('POST', $url, $headers, $quizdata);
    }

    /**
     * Create a Talview Secure Browser wrapper URL for a quiz attempt.
     *
     * POST {proview_callback_url}/proview/wrapper/create
     * Header: Authorization: Bearer {bearertoken}
     *
     * The wrapper URL, when opened, launches the Talview Secure Browser and
     * redirects the candidate back to the quiz attempt URL inside the TSB.
     *
     * @param string      $bearertoken  Valid LMS Connector bearer token.
     * @param string      $sessionid    Session identifier — "{quizid}-{userid}-{attemptno}".
     * @param string      $attendeeid   Moodle user ID as a string.
     * @param string      $redirecturl  Full quiz attempt URL the TSB should load after launch.
     * @param int         $expiry       Unix timestamp at which the wrapper URL expires.
     * @param array       $extraparams  Optional extra fields merged into the request body
     *                                  (e.g. proview_token, is_secure_browser, secure_browser config).
     * @return string TSB wrapper URL (signed_url from response).
     * @throws \moodle_exception On HTTP error or missing URL in response.
     */
    public static function create_tsb_wrapper(
        string $bearertoken,
        string $sessionid,
        string $attendeeid,
        string $redirecturl,
        int $expiry,
        array $extraparams = []
    ): string {
        $callbackurl = (string) get_config('quizaccess_proview', 'proview_callback_url');
        $url         = rtrim($callbackurl, '/') . '/proview/wrapper/create';
        $headers  = ['Authorization: Bearer ' . $bearertoken];
        $body     = array_merge([
            'session_external_id'  => $sessionid,
            'attendee_external_id' => $attendeeid,
            'redirect_url'         => $redirecturl,
            'expiry'               => gmdate('Y-m-d\TH:i:s\Z', $expiry),
        ], $extraparams);

        $response = static::make_request('POST', $url, $headers, $body);

        if (empty($response['signed_url'])) {
            throw new \moodle_exception(
                'proview_api_error',
                'quizaccess_proview',
                '',
                'TSB wrapper URL missing from response'
            );
        }

        return (string) $response['signed_url'];
    }

    /**
     * Fetch all Proview recording sessions for a quiz.
     *
     * GET /proview/playback?quiz_id={quizid}&course_id={courseid}&limit={limit}&offset={offset}
     * Headers: app-id (md5 of site URL), Authorization: {proctortoken}
     *
     * @param string $bearertoken  LMS Connector bearer token (from token_manager).
     * @param int    $quizid       Moodle quiz ID.
     * @param int    $courseid     Moodle course ID.
     * @param int    $limit        Max records to return (default 100).
     * @param int    $offset       Pagination offset (default 0).
     * @return array[] Array of session objects from the API.
     * @throws \moodle_exception On HTTP error.
     */
    public static function get_playback_sessions(
        string $bearertoken,
        int $quizid,
        int $courseid,
        int $limit = 100,
        int $offset = 0
    ): array {
        global $CFG;

        $url     = static::get_base_url() . '/proview/playback'
                 . '?quiz_id=' . $quizid
                 . '&course_id=' . $courseid
                 . '&limit=' . $limit
                 . '&offset=' . $offset;
        $headers = [
            'Authorization: Bearer ' . $bearertoken,
        ];

        return static::make_request('GET', $url, $headers, null);
    }

    /**
     * Fetch a short-lived playback token for a specific Proview recording session.
     *
     * POST /token/playback
     * Headers: app-id (md5 of site URL), org-id (configured organisation ID)
     * Body: { session_uuid, proctor_token, validity }
     *
     * The returned token is appended as ?token={playback_token} to the
     * proviewurl before opening the recording link.
     *
     * @param string $sessionuuid  Proview session UUID (tail segment of the proviewurl).
     * @param string $proctortoken Proview token UUID configured for the quiz.
     * @param int    $validity     Token validity in minutes (default 60).
     * @return string Playback token string.
     * @throws \moodle_exception On HTTP error or missing token in response.
     */
    public static function get_playback_token(string $sessionuuid, string $proctortoken, int $validity = 60): string {
        global $CFG;

        $url    = static::get_base_url() . '/token/playback';
        $parsed = parse_url($CFG->wwwroot);
        $appid  = md5($parsed['scheme'] . '://' . $parsed['host']);

        $headers = [
            'app-id: ' . $appid,
        ];

        $body = [
            'session_uuid'  => $sessionuuid,
            'proctor_token' => $proctortoken,
            'validity'      => $validity,
        ];

        $response = static::make_request('POST', $url, $headers, $body);

        if (empty($response['token'])) {
            throw new \moodle_exception(
                'proview_api_error',
                'quizaccess_proview',
                '',
                'Playback token missing from response'
            );
        }

        return (string) $response['token'];
    }

    /**
     * Execute an HTTP request via Moodle's \curl wrapper.
     *
     * @param string     $method  HTTP method ('GET' or 'POST').
     * @param string     $url     Full request URL.
     * @param string[]   $headers Additional headers (each as "Name: value").
     * @param array|null $body    Associative array to JSON-encode as request body, or null.
     * @return array Decoded JSON response.
     * @throws \moodle_exception On curl error, non-2xx HTTP status, or JSON decode failure.
     */
    protected static function make_request(string $method, string $url, array $headers, ?array $body): array {
        $span = sentry::start_span('http.client', $method . ' ' . $url);

        try {
            $curl = new \curl(['ignoresecurity' => false]);

            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Accept: application/json';

            $curl->setHeader($headers);

            $options = [
                'CURLOPT_RETURNTRANSFER' => true,
                'CURLOPT_TIMEOUT'        => 30,
            ];

            if ($method === 'POST') {
                $rawbody  = $body !== null ? json_encode($body) : '{}';
                $options['CURLOPT_POSTFIELDS'] = $rawbody;
                $raw = $curl->post($url, $rawbody, $options);
            } else {
                $raw = $curl->get($url, [], $options);
            }

            if ($curl->errno !== 0) {
                $e = new \moodle_exception(
                    'proview_api_error',
                    'quizaccess_proview',
                    '',
                    'cURL error ' . $curl->errno . ': ' . $curl->error
                );
                sentry::capture_exception($e);
                if ($span !== null) {
                    $span->setStatus(\Sentry\Tracing\SpanStatus::internalError());
                    $span->finish();
                }
                throw $e;
            }

            $info       = $curl->get_info();
            $httpstatus = (int) ($info['http_code'] ?? 0);

            global $PAGE;
            if (!empty($PAGE) && !CLI_SCRIPT) {
                $label   = json_encode('[quizaccess_proview] ' . $method . ' ' . $url . ' HTTP ' . $httpstatus);
                $payload = json_encode(json_decode($raw, true), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $PAGE->requires->js_init_code('console.log(' . $label . ', ' . $payload . ');');
            }

            if ($httpstatus < 200 || $httpstatus >= 300) {
                $e = new \moodle_exception(
                    'proview_api_error',
                    'quizaccess_proview',
                    '',
                    'HTTP ' . $httpstatus . ' from ' . $url . ' — ' . $raw
                );
                sentry::capture_exception($e);
                if ($span !== null) {
                    $span->setStatus(\Sentry\Tracing\SpanStatus::createFromHttpStatusCode($httpstatus));
                    $span->finish();
                }
                throw $e;
            }

            $decoded = json_decode($raw, true);

            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                $e = new \moodle_exception(
                    'proview_api_error',
                    'quizaccess_proview',
                    '',
                    'Invalid JSON response: ' . json_last_error_msg()
                );
                sentry::capture_exception($e);
                if ($span !== null) {
                    $span->setStatus(\Sentry\Tracing\SpanStatus::internalError());
                    $span->finish();
                }
                throw $e;
            }

            if ($span !== null) {
                $span->setStatus(\Sentry\Tracing\SpanStatus::ok());
                $span->finish();
            }

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            if ($span !== null) {
                $span->setStatus(\Sentry\Tracing\SpanStatus::internalError());
                $span->finish();
            }
            throw $e;
        }
    }
}
