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
 * AJAX endpoint to save the Proview playback URL to the DB after initCallback.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../../../config.php');

require_login();

header('Content-Type: application/json');

$rawbody = file_get_contents('php://input');
$data    = json_decode($rawbody, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body.']);
    exit;
}

$postedsesskey = isset($data['sesskey']) ? (string) $data['sesskey'] : '';
if (!confirm_sesskey($postedsesskey)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid session key.']);
    exit;
}

$posteduserid = isset($data['userid']) ? (int) $data['userid'] : 0;
if ($posteduserid !== (int) $USER->id) {
    http_response_code(403);
    echo json_encode(['error' => 'User mismatch.']);
    exit;
}

$quizid     = isset($data['quizid']) ? (int) $data['quizid'] : 0;
$attemptno  = isset($data['attemptno']) ? (int) $data['attemptno'] : 0;
$proviewurl = isset($data['proviewurl']) ? (string) $data['proviewurl'] : '';

if ($quizid <= 0 || $attemptno <= 0 || $proviewurl === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid parameters.']);
    exit;
}

$record = $DB->get_record('quizaccess_proview_attempts', [
    'quizid'    => $quizid,
    'userid'    => $USER->id,
    'attemptno' => $attemptno,
]);

if (!$record) {
    http_response_code(404);
    echo json_encode(['error' => 'Attempt record not found.']);
    exit;
}

$record->proviewurl   = $proviewurl;
$record->timemodified = time();
$DB->update_record('quizaccess_proview_attempts', $record);

echo json_encode(['success' => true]);
