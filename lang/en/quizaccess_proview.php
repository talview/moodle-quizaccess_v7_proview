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

// Plugin.
$string['pluginname'] = 'Talview Proview proctoring';

// Admin settings — headings.
$string['settings_heading_connection'] = 'Proview connection';
$string['settings_heading_connection_desc'] = 'Enter the Proview service URLs and credentials provided by Talview.';
$string['settings_heading_callbacks'] = 'Callback authentication';
$string['settings_heading_callbacks_desc'] = 'Credentials used to authenticate inbound callback requests from Talview.';

// Admin settings — fields.
$string['proview_cdn_url'] = 'CDN URL';
$string['proview_cdn_url_desc'] = 'URL of the Proview JavaScript script injected into quiz pages to launch the proctoring session.';

$string['proview_account_name'] = 'Account name';
$string['proview_account_name_desc'] = 'Talview account name associated with this Moodle site.';

$string['proview_admin_url'] = 'Admin URL';
$string['proview_admin_url_desc'] = 'Base URL of the Proview admin/API service (e.g. https://appv7.proview.io/embedded).';

$string['proview_admin_username'] = 'Admin username';
$string['proview_admin_username_desc'] = 'Username provided by Talview to authenticate callback requests.';

$string['proview_admin_password'] = 'Admin password';
$string['proview_admin_password_desc'] = 'Password provided by Talview to authenticate callback requests.';

$string['root_dir'] = 'Root directory';
$string['root_dir_desc'] = 'Moodle root directory path. Use <code>/</code> for the default site root.';

$string['proview_callback_url'] = 'Callback URL';
$string['proview_callback_url_desc'] = 'URL that Talview will POST proctoring event callbacks to. Leave blank to use the default plugin endpoint.';

// Privacy.
$string['privacy:metadata:talview_proview'] = 'Data sent to Talview Proview for proctoring purposes.';
$string['privacy:metadata:talview_proview:userid'] = 'The Moodle user ID of the candidate.';
$string['privacy:metadata:talview_proview:quizid'] = 'The ID of the quiz being proctored.';
$string['privacy:metadata:talview_proview:fullname'] = 'The candidate\'s full name.';
$string['privacy:metadata:talview_proview:email'] = 'The candidate\'s email address.';
