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
 * AJAX endpoint: fetch a Proview playback token for a specific recording session.
 *
 * Called client-side when an admin/teacher clicks a "View Recording" link.
 * Returns JSON { token: "..." } on success, or { error: "..." } on failure.
 *
 * Required GET params:
 *   - quizid        (int)    Moodle quiz ID — used for capability check only.
 *   - session_uuid  (string) Proview session UUID from the playback sessions API.
 *   - proctor_token (string) Proview token from the playback sessions API response.
 *   - sesskey       (string) Moodle session key for CSRF protection.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

require_sesskey();
require_login();

$quizid       = required_param('quizid', PARAM_INT);
$sessionuuid  = required_param('session_uuid', PARAM_TEXT);
$proctortoken = required_param('proctor_token', PARAM_TEXT);

$cm      = get_coursemodule_from_instance('quiz', $quizid, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);

require_capability('quizaccess/proview:manage', $context);

$PAGE->set_context($context);

try {
    $token = \quizaccess_proview\api::get_playback_token($sessionuuid, $proctortoken);
    echo json_encode(['token' => $token]);
} catch (\moodle_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
