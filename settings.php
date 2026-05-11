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
 * Admin settings for quizaccess_proview.
 *
 * Moodle injects this file automatically when the plugin declares settings.
 * The $settings variable is provided by the admin framework.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Admin config text setting with Proview CDN URL allowlist validation.
 */
class admin_setting_configtext_proview_cdn_url extends admin_setting_configtext {
    /** @var string[] Trusted Proview CDN script hosts. */
    private const TRUSTED_CDN_HOSTS = [
        'appv7.proview.io',
        'cdn.proview.io',
        'static.proview.io',
        'pages.talview.com',
    ];

    /**
     * Return the trusted CDN host allowlist.
     *
     * @return string[]
     */
    public static function get_trusted_hosts(): array {
        return self::TRUSTED_CDN_HOSTS;
    }

    /**
     * Validate a Proview CDN URL.
     *
     * @param string $url URL value to validate.
     * @return bool True when URL is trusted, false otherwise.
     */
    public static function is_valid_cdn_url(string $url): bool {
        $url = trim($url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = $parts['port'] ?? null;
        $hasuserinfo = isset($parts['user']) || isset($parts['pass']);

        if ($scheme !== 'https' || $host === '' || !in_array($host, self::TRUSTED_CDN_HOSTS, true)) {
            return false;
        }

        if ($port !== null && (int) $port !== 443) {
            return false;
        }

        if ($hasuserinfo) {
            return false;
        }

        return true;
    }

    /**
     * Validate a config value before saving.
     *
     * @param string $data Incoming value from admin form.
     * @return string|true
     */
    public function validate($data) {
        $result = parent::validate($data);
        if ($result !== true) {
            return $result;
        }

        $url = trim((string) $data);
        $trustedhosts = self::get_trusted_hosts();

        if (!self::is_valid_cdn_url($url)) {
            return get_string(
                'proview_cdn_url_validation_error',
                'quizaccess_proview',
                implode(', ', $trustedhosts)
            );
        }

        return true;
    }
}

if ($hassiteconfig) {
    // Connection settings heading.
    $settings->add(new admin_setting_heading(
        'quizaccess_proview/settings_heading_connection',
        get_string('settings_heading_connection', 'quizaccess_proview'),
        get_string('settings_heading_connection_desc', 'quizaccess_proview')
    ));

    // CDN URL.
    $settings->add(new admin_setting_configtext_proview_cdn_url(
        'quizaccess_proview/proview_cdn_url',
        get_string('proview_cdn_url', 'quizaccess_proview'),
        get_string('proview_cdn_url_desc', 'quizaccess_proview'),
        '',
        PARAM_URL
    ));


    // Account name.
    $settings->add(new admin_setting_configtext(
        'quizaccess_proview/proview_account_name',
        get_string('proview_account_name', 'quizaccess_proview'),
        get_string('proview_account_name_desc', 'quizaccess_proview'),
        '',
        PARAM_TEXT
    ));

    // Admin URL.
    $settings->add(new admin_setting_configtext(
        'quizaccess_proview/proview_admin_url',
        get_string('proview_admin_url', 'quizaccess_proview'),
        get_string('proview_admin_url_desc', 'quizaccess_proview'),
        '',
        PARAM_URL
    ));

    // Callback authentication heading.
    $settings->add(new admin_setting_heading(
        'quizaccess_proview/settings_heading_callbacks',
        get_string('settings_heading_callbacks', 'quizaccess_proview'),
        get_string('settings_heading_callbacks_desc', 'quizaccess_proview')
    ));

    // Admin username.
    $settings->add(new admin_setting_configtext(
        'quizaccess_proview/proview_admin_username',
        get_string('proview_admin_username', 'quizaccess_proview'),
        get_string('proview_admin_username_desc', 'quizaccess_proview'),
        '',
        PARAM_TEXT
    ));

    // Admin password — stored encrypted.
    $settings->add(new admin_setting_configpasswordunmask(
        'quizaccess_proview/proview_admin_password',
        get_string('proview_admin_password', 'quizaccess_proview'),
        get_string('proview_admin_password_desc', 'quizaccess_proview'),
        ''
    ));

    // Root directory.
    $settings->add(new admin_setting_configtext(
        'quizaccess_proview/root_dir',
        get_string('root_dir', 'quizaccess_proview'),
        get_string('root_dir_desc', 'quizaccess_proview'),
        '/',
        PARAM_TEXT
    ));

    // Callback URL.
    $settings->add(new admin_setting_configtext(
        'quizaccess_proview/proview_callback_url',
        get_string('proview_callback_url', 'quizaccess_proview'),
        get_string('proview_callback_url_desc', 'quizaccess_proview'),
        '',
        PARAM_URL
    ));
}
