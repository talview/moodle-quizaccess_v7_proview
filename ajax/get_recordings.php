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
 * AJAX endpoint: fetch Proview recording sessions for a quiz.
 *
 * Called lazily when the "Proview Recordings" accordion section is first
 * expanded on the quiz settings page.
 *
 * Required GET params:
 *   - quizid   (int)    Moodle quiz ID.
 *   - sesskey  (string) Moodle session key for CSRF protection.
 *
 * Returns JSON:
 *   { sessions: [ { index, lmsuserid, fullname, email, attemptno,
 *                   finalrating, sessionuuid, proctortoken, proviewurl,
 *                   hasurl } ] }
 *   or { error: "..." } on failure.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../../config.php');

require_sesskey();
require_login();

$quizid = required_param('quizid', PARAM_INT);

$cm      = get_coursemodule_from_instance('quiz', $quizid, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);

require_capability('quizaccess/proview:manage', $context);

$PAGE->set_context($context);

$quiz            = $DB->get_record('quiz', ['id' => $quizid], 'course', MUST_EXIST);
$record          = $DB->get_record('quizaccess_proview', ['quizid' => $quizid]);
$proctortoken    = (string) ($record->proview_token ?? '');
$playbackbaseurl = rtrim((string) get_config('quizaccess_proview', 'proview_admin_url'), '/');

$sessions = [];
try {
    $tokenmgr = new \quizaccess_proview\token_manager();
    $bearer   = $tokenmgr->get_token();
    $sessions = \quizaccess_proview\api::get_playback_sessions(
        $bearer,
        $quizid,
        (int) $quiz->course
    );
} catch (\moodle_exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Bulk-load Moodle users for name/email fallback.
$lmsuserids = array_unique(array_filter(array_map(
    function ($s) {
        return (int) ($s['attendee']['lms_user_id'] ?? 0);
    },
    $sessions
)));
$moodleusers = [];
if (!empty($lmsuserids)) {
    [$insql, $inparams] = $DB->get_in_or_equal($lmsuserids, SQL_PARAMS_NAMED);
    $namefields = \core_user\fields::for_name()->get_sql('u');
    $userrows   = $DB->get_records_sql(
        "SELECT u.id{$namefields->selects}, u.email FROM {user} u WHERE u.id $insql",
        $inparams
    );
    foreach ($userrows as $u) {
        $moodleusers[(int) $u->id] = $u;
    }
}

$attempts = [];
foreach ($sessions as $i => $session) {
    $attendee    = $session['attendee'] ?? [];
    $sessionuuid = (string) ($session['session_uuid'] ?? '');
    $lmsuid      = (int) ($attendee['lms_user_id'] ?? 0);
    $dbuser      = $moodleusers[$lmsuid] ?? null;

    $apiname  = trim(($attendee['first_name'] ?? '') . ' ' . ($attendee['last_name'] ?? ''));
    $dispname = $apiname !== '' ? $apiname : ($dbuser ? fullname($dbuser) : '');
    $email    = (string) ($attendee['email'] ?? ($dbuser ? $dbuser->email : ''));

    $attempts[] = [
        'index'        => $i + 1,
        'lmsuserid'    => $lmsuid > 0 ? (string) $lmsuid : '',
        'fullname'     => $dispname,
        'email'        => $email,
        'attemptno'    => (int) ($session['attempt_no'] ?? 0),
        'finalrating'  => (string) ($session['final_rating'] ?? ''),
        'sessionuuid'  => $sessionuuid,
        'proctortoken' => (string) ($session['proctor_token'] ?? $proctortoken),
        'proviewurl'   => $sessionuuid !== '' ? $playbackbaseurl . '/' . $sessionuuid : '',
        'hasurl'       => $sessionuuid !== '',
    ];
}

echo json_encode(['sessions' => $attempts]);
