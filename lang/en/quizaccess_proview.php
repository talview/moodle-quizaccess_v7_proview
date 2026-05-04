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
 * Language strings for quizaccess_proview.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['ai_proctor'] = 'Recorded';
$string['blacklistedmacsoftwares'] = 'Blacklisted macOS applications';
$string['blacklistedwindowssoftwares'] = 'Blacklisted Windows applications';
$string['candidateinstructions'] = 'Candidate instructions';
$string['candidateinstructions_help'] = 'Instructions shown to the candidate before the Proview session starts.';
$string['invalid_reference_links'] = 'Each reference link must be on its own line in the format: [Label](https://example.com)';
$string['live_proctor'] = 'Live proctoring';
$string['live_requires_timeclose'] = 'A closing time must be set when live proctoring is enabled.';
$string['live_requires_timeopen'] = 'An opening time must be set when live proctoring is enabled.';
$string['minimizepermitted'] = 'Allow minimise';
$string['minimizepermitted_help'] = 'When ticked, the candidate may minimise the browser window during the quiz.';
$string['next'] = 'Next';
$string['noproctor'] = 'No proctoring';
$string['org_unavailable'] = 'Organisation name unavailable (check LMS Connector settings)';
$string['organizationid'] = 'Organisation';
$string['pluginname'] = 'Talview Proview proctoring';
$string['previous'] = 'Previous';
$string['privacy:metadata:talview_proview'] = 'Data sent to Talview Proview for proctoring purposes.';
$string['privacy:metadata:talview_proview:email'] = 'The candidate\'s email address.';
$string['privacy:metadata:talview_proview:fullname'] = 'The candidate\'s full name.';
$string['privacy:metadata:talview_proview:quizid'] = 'The ID of the quiz being proctored.';
$string['privacy:metadata:talview_proview:userid'] = 'The Moodle user ID of the candidate.';
$string['proctoringenabled'] = 'Enable Proview proctoring';
$string['proctoringenabled_help'] = 'Tick this box to activate Proview proctoring for this quiz.';
$string['proctoringtype'] = 'Proctoring type';
$string['proctoringtype_help'] = 'Choose how this quiz will be proctored: no proctoring, AI-based review, recorded session review, or live invigilator.';
$string['proctorinstructions'] = 'Proctor instructions';
$string['proctorinstructions_help'] = 'Instructions shown to the invigilator before the proctoring session begins.';
$string['proview_account_name'] = 'Account name';
$string['proview_account_name_desc'] = 'Talview account name associated with this Moodle site.';
$string['proview_admin_password'] = 'Admin password';
$string['proview_admin_password_desc'] = 'Password provided by Talview to authenticate callback requests.';
$string['proview_admin_url'] = 'Admin URL';
$string['proview_admin_url_desc'] = 'Base URL of the Proview admin/API service (e.g. https://appv7.proview.io/embedded).';
$string['proview_admin_username'] = 'Admin username';
$string['proview_admin_username_desc'] = 'Username provided by Talview to authenticate callback requests.';
$string['proview_api_error'] = 'Proview API error: {$a}';
$string['proview_auth_failed'] = 'Failed to authenticate with Proview LMS Connector.';
$string['proview_callback_url'] = 'Callback URL';
$string['proview_callback_url_desc'] = 'URL that Talview will POST proctoring event callbacks to. Leave blank to use the default plugin endpoint.';
$string['proview_cdn_url'] = 'CDN URL';
$string['proview_cdn_url_desc'] = 'URL of the Proview JavaScript script injected into quiz pages to launch the proctoring session.';
$string['proview_proctoring_header'] = 'Proview Proctoring';
$string['proview_recordings_header'] = 'Proview Recordings';
$string['proview_token'] = 'Proview token';
$string['proview_token_help'] = 'Select the Proview token configuration to use for this quiz. The list is fetched from the LMS Connector.';
$string['proview_token_required'] = 'A Proview token must be selected when proctoring or Secure Browser is enabled.';
$string['record_review'] = 'Record and review';
$string['recordings_col_attempt'] = 'Attempt';
$string['recordings_col_email'] = 'Email';
$string['recordings_col_name'] = 'Name';
$string['recordings_col_rating'] = 'Rating';
$string['recordings_col_recording'] = 'Recording';
$string['recordings_no_attempts'] = 'No proctored sessions found for this quiz.';
$string['recordings_search_placeholder'] = 'Search by name or email…';
$string['recordings_view_link'] = 'View recording';
$string['referencelinks'] = 'Reference links';
$string['referencelinks_help'] = 'Enter one reference link per line using markdown format: [Label](https://example.com)';
$string['root_dir'] = 'Root directory';
$string['root_dir_desc'] = 'Moodle root directory path. Use <code>/</code> for the default site root.';
$string['screenprotection'] = 'Screen capture protection';
$string['screenprotection_help'] = 'When enabled, screen-capture and recording tools are blocked during the quiz.';
$string['securebrowser_header'] = 'Secure Browser';
$string['settings_heading_callbacks'] = 'Callback authentication';
$string['settings_heading_callbacks_desc'] = 'Credentials used to authenticate inbound callback requests from Talview.';
$string['settings_heading_connection'] = 'Proview connection';
$string['settings_heading_connection_desc'] = 'Enter the Proview service URLs and credentials provided by Talview.';
$string['tsbenabled'] = 'Enable Talview Secure Browser';
$string['tsbenabled_help'] = 'When enabled, quiz launches through the Talview Secure Browser.';
$string['whitelistedmacsoftwares'] = 'Whitelisted macOS applications';
$string['whitelistedwindowssoftwares'] = 'Whitelisted Windows applications';
