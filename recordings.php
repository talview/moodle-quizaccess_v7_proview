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
 * Proview recordings page — lists recording sessions for a quiz.
 *
 * Accessible to users with quizaccess/proview:manage capability.
 *
 * URL params:
 *   - cmid  (int) Course-module ID of the quiz.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

$cmid = required_param('cmid', PARAM_INT);
$limit  = optional_param('limit', 2, PARAM_INT);
$offset = optional_param('offset', 0, PARAM_INT);

$cm      = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$quiz    = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('quizaccess/proview:manage', $context);

$PAGE->set_url('/mod/quiz/accessrule/proview/recordings.php', [
    'cmid'   => $cmid,
    'limit'  => $limit,
    'offset' => $offset,
]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('proview_recordings_header', 'quizaccess_proview') . ': ' . format_string($quiz->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

$PAGE->navbar->add(
    format_string($quiz->name),
    new moodle_url('/mod/quiz/view.php', ['id' => $cmid])
);
$PAGE->navbar->add(get_string('proview_recordings_header', 'quizaccess_proview'));


$record          = $DB->get_record('quizaccess_proview', ['quizid' => $quiz->id]);
$proctortoken    = (string) ($record->proview_token ?? '');
$playbackbaseurl = rtrim((string) get_config('quizaccess_proview', 'proview_admin_url'), '/');

$sessions    = [];
$fetcherror  = null;

try {
    $tokenmgr = new \quizaccess_proview\token_manager();
    $bearer   = $tokenmgr->get_token();
    $sessions = \quizaccess_proview\api::get_playback_sessions(
        $bearer,
        (int) $quiz->id,
        (int) $quiz->course,
        $limit,
        $offset
    );
} catch (\moodle_exception $e) {
    $fetcherror = $e->getMessage();
}

$moodleusers = [];
if (!empty($sessions)) {
    $lmsuserids = array_unique(array_filter(array_map(
        function ($s) {
            return (int) ($s['attendee']['lms_user_id'] ?? 0);
        },
        $sessions
    )));
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
}

$flat = [];
foreach ($sessions as $session) {
    $attendee    = $session['attendee'] ?? [];
    $sessionuuid = (string) ($session['session_uuid'] ?? '');
    $lmsuid      = (int) ($attendee['lms_user_id'] ?? 0);
    $dbuser      = $moodleusers[$lmsuid] ?? null;

    $apiname  = trim(($attendee['first_name'] ?? '') . ' ' . ($attendee['last_name'] ?? ''));
    $dispname = $apiname !== '' ? $apiname : ($dbuser ? fullname($dbuser) : '');
    $email    = (string) ($attendee['email'] ?? ($dbuser ? $dbuser->email : ''));

    $rating      = strtolower((string) ($session['final_rating'] ?? ''));
    $ratingclass = ['high' => 'success', 'medium' => 'warning', 'low' => 'danger'][$rating] ?? 'secondary';

    $groupkey         = $lmsuid > 0 ? $lmsuid : 'anon_' . $sessionuuid;
    $flat[$groupkey][] = [
        'lmsuserid'    => $lmsuid > 0 ? (string) $lmsuid : '',
        'fullname'     => $dispname,
        'email'        => $email,
        'attemptno'    => (int) ($session['attempt_no'] ?? 0),
        'finalrating'  => $rating,
        'ratingclass'  => $ratingclass,
        'sessionuuid'  => $sessionuuid,
        'proctortoken' => (string) ($session['proctor_token'] ?? $proctortoken),
        'proviewurl'   => $sessionuuid !== '' ? $playbackbaseurl . '/' . $sessionuuid : '',
        'hasurl'       => $sessionuuid !== '',
    ];
}

$attempts = [];
$rowindex = $offset + 1;
foreach ($flat as $rows) {
    usort($rows, function ($a, $b) {
        return $a['attemptno'] - $b['attemptno'];
    });
    $count = count($rows);
    foreach ($rows as $k => $row) {
        $row['index']    = $rowindex++;
        $row['firstrow'] = ($k === 0);
        $row['lastrow']  = ($k === $count - 1);
        $attempts[]      = $row;
    }
}

$hasnext = count($sessions) >= $limit;
$hasprev = $offset > 0;

$nexturl = '';
if ($hasnext) {
    $nexturl = new moodle_url('/mod/quiz/accessrule/proview/recordings.php', [
        'cmid'   => $cmid,
        'limit'  => $limit,
        'offset' => $offset + $limit,
    ]);
}

$prevurl = '';
if ($hasprev) {
    $prevurl = new moodle_url('/mod/quiz/accessrule/proview/recordings.php', [
        'cmid'   => $cmid,
        'limit'  => $limit,
        'offset' => max(0, $offset - $limit),
    ]);
}

$PAGE->requires->js_call_amd(
    'quizaccess_proview/attempt_recordings',
    'init',
    [(int) $quiz->id, sesskey()]
);


echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('proview_recordings_header', 'quizaccess_proview'));

if ($fetcherror !== null) {
    echo $OUTPUT->notification(
        get_string('proview_api_error', 'quizaccess_proview', $fetcherror),
        'error'
    );
} else {
    echo $OUTPUT->render_from_template('quizaccess_proview/attempt_recordings', [
        'attempts'    => $attempts,
        'hasattempts' => !empty($attempts),
        'hasnext'     => $hasnext,
        'hasprev'     => $hasprev,
        'nexturl'     => $nexturl ? $nexturl->out(false) : '',
        'prevurl'     => $prevurl ? $prevurl->out(false) : '',
    ]);
}

echo $OUTPUT->footer();
