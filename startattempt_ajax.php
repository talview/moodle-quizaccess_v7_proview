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
 * AJAX endpoint called from frame.php initCallback to create a quiz attempt.
 *
 * Called after Proview's hardware check completes so the quiz timer starts
 * only once the candidate is verified and ready.
 *
 * Returns JSON: { url: string, attemptno: int } on success,
 *               { error: string } on failure.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

require_login();
require_sesskey();

header('Content-Type: application/json');

$quizid = required_param('quizid', PARAM_INT);
$cmid   = required_param('cmid', PARAM_INT);

$cm     = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, false, $cm);

$quizobj = \mod_quiz\quiz_settings::create($cm->instance, $USER->id);

$attempts    = quiz_get_user_attempts($quizobj->get_quizid(), $USER->id, 'all', true);
$lastattempt = end($attempts) ?: false;

foreach ($attempts as $att) {
    if (in_array($att->state, ['inprogress', 'overdue'])) {
        $attempturl = new moodle_url('/mod/quiz/attempt.php', [
            'attempt' => $att->id,
            'cmid'    => $cmid,
        ]);
        echo json_encode([
            'url'       => $attempturl->out(false),
            'attemptno' => (int) $att->attempt,
        ]);
        exit;
    }
}

// Access checks before creating a new attempt.
$accessmanager = $quizobj->get_access_manager(time());

$messages = $accessmanager->prevent_access();
if ($messages) {
    http_response_code(403);
    echo json_encode(['error' => strip_tags(reset($messages))]);
    exit;
}

$numattempts = count($attempts);
$preventnew  = $accessmanager->prevent_new_attempt($numattempts, $lastattempt);
if ($preventnew) {
    http_response_code(403);
    echo json_encode(['error' => strip_tags($preventnew)]);
    exit;
}

$attempt = quiz_prepare_and_start_new_attempt($quizobj, $numattempts + 1, $lastattempt);

$attempturl = new moodle_url('/mod/quiz/attempt.php', [
    'attempt' => $attempt->id,
    'cmid'    => $cmid,
]);

echo json_encode([
    'url'       => $attempturl->out(false),
    'attemptno' => (int) $attempt->attempt,
]);
