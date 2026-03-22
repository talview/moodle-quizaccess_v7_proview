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
 * Proview quiz access rule.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;

/**
 * Proview proctoring access rule for Moodle quizzes.
 *
 * Handles per-quiz proctoring configuration form fields, persists config to
 * mdl_quizaccess_proview, syncs settings to the LMS Connector API, and
 * injects the Proview CDN script into quiz attempt pages.
 */
class quizaccess_proview extends access_rule_base {
    /** @var stdClass Proctoring configuration record from mdl_quizaccess_proview. */
    private stdClass $proviewconfig;

    /**
     * Map internal proctoringtype values to Proview SDK session_type strings.
     *
     * @param string $proctoringtype Internal type: 'ai', 'record_review', 'live'.
     * @return string SDK session_type string.
     */
    private static function map_session_type(string $proctoringtype): string {
        $map = [
            'ai'            => 'ai_proctor',
            'record_review' => 'record_and_review',
            'live'          => 'live_proctor',
        ];
        return $map[$proctoringtype] ?? 'ai_proctor';
    }

    /**
     * Parse a markdown reference links string into the array format the Proview SDK expects.
     *
     * Each line: [Caption](https://example.com)
     * Returns:   [['caption' => 'Caption', 'url' => 'https://example.com'], ...]
     *
     * @param string $raw Raw markdown reference links value.
     * @return array Array of {caption, url} objects.
     */
    private static function parse_reference_links(string $raw): array {
        $links = [];
        preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $raw, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $links[] = ['caption' => $match[1], 'url' => $match[2]];
        }
        return $links;
    }

    /**
     * Build the config array passed to the proview_launch AMD module.
     *
     * @param string $sessionid   Proview session ID.
     * @param bool   $preflight   True on preflight page, false on attempt page.
     * @return array Config for js_call_amd.
     */
    private function build_amd_config(string $sessionid, bool $preflight): array {
        global $USER;

        $config = $this->proviewconfig;
        return [
            'cdnUrl'                 => (string) get_config('quizaccess_proview', 'proview_cdn_url'),
            'token'                  => (string) ($config->proview_token ?? ''),
            'profileId'              => (string) $USER->id,
            'sessionId'              => $sessionid,
            'sessionType'            => self::map_session_type($config->proctoringtype),
            'candidateInstructions'  => (string) ($config->candidateinstructions ?? ''),
            'referenceLinks'         => self::parse_reference_links((string) ($config->referencelinks ?? '')),
            'skipHardwareTest'       => !$preflight,
            'preflight'              => $preflight,
        ];
    }

    /**
     * Validate the reference links field value.
     *
     * Each non-empty line must be a markdown-style link: [Label](https://example.com)
     *
     * @param string $value Field value to validate.
     * @return bool True if valid (or empty), false otherwise.
     */
    public static function validate_reference_links($value): bool {
        if (empty($value)) {
            return true;
        }
        $pattern = '/^\[([^\]]+)\]\(([^)]+)\)$/i';
        $lines   = preg_split('/\r\n|\r|\n/', $value);
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            if (!preg_match($pattern, $line)) {
                return false;
            }
            preg_match($pattern, $line, $matches);
            if (!filter_var($matches[2], FILTER_VALIDATE_URL)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Return an appropriately configured instance of this rule, if it is applicable
     * to the given quiz, otherwise return null.
     *
     * @param quiz_settings $quizobj information about the quiz in question.
     * @param int $timenow the time that should be considered as 'now'.
     * @param bool $canignoretimelimits whether the current user is exempt from time limits.
     * @return self|null the rule, if applicable, else null.
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        global $DB;

        $record = $DB->get_record('quizaccess_proview', ['quizid' => $quizobj->get_quizid()]);

        if (!$record || ($record->proctoringtype === 'none' && empty($record->tsbenabled))) {
            return null;
        }

        $instance = new self($quizobj, $timenow);
        $instance->proviewconfig = $record;
        return $instance;
    }

    /**
     * Add per-quiz proctoring settings fields to the quiz editing form.
     *
     * @param mod_quiz_mod_form $quizform the quiz settings form.
     * @param MoodleQuickForm   $mform    the underlying QuickForm object.
     */
    public static function add_settings_form_fields(
        mod_quiz_mod_form $quizform,
        MoodleQuickForm $mform
    ): void {
        global $DB;

        $mform->addElement(
            'header',
            'proview_proctoring_header',
            get_string('proview_proctoring_header', 'quizaccess_proview')
        );

        $orgs     = [];
        $orgsfail = false;
        try {
            $orgs = \quizaccess_proview\api::get_organizations();
        } catch (\moodle_exception $e) {
            $orgsfail = true;
        }

        try {
            $tokenmgr      = new \quizaccess_proview\token_manager();
            $bearer        = $tokenmgr->get_token();
            $proviewtokens = \quizaccess_proview\api::get_proview_tokens($bearer);
            $tokenoptions  = ['' => get_string('choosedots')];
            foreach ($proviewtokens as $pt) {
                $ptuuid = $pt['token'] ?? '';
                $ptname = $pt['name'] ?? $ptuuid;
                if ($ptuuid !== '') {
                    $tokenoptions[$ptuuid] = $ptname;
                }
            }
            $mform->addElement(
                'select',
                'proview_token',
                get_string('proview_token', 'quizaccess_proview'),
                $tokenoptions
            );
        } catch (\moodle_exception $e) {
            $mform->addElement(
                'text',
                'proview_token',
                get_string('proview_token', 'quizaccess_proview')
            );
            $mform->setType('proview_token', PARAM_TEXT);
        }
        $mform->addHelpButton('proview_token', 'proview_token', 'quizaccess_proview');

        $proctoringtypes = [
            'none'          => get_string('noproctor', 'quizaccess_proview'),
            'ai'            => get_string('ai_proctor', 'quizaccess_proview'),
            'record_review' => get_string('record_review', 'quizaccess_proview'),
            'live'          => get_string('live_proctor', 'quizaccess_proview'),
        ];
        $mform->addElement(
            'select',
            'proctoringtype',
            get_string('proctoringtype', 'quizaccess_proview'),
            $proctoringtypes
        );
        $mform->addHelpButton('proctoringtype', 'proctoringtype', 'quizaccess_proview');
        $mform->setDefault('proctoringtype', 'none');

        $schedulingoptions = ['' => get_string('choosedots')];
        if (!$orgsfail) {
            foreach ($orgs as $org) {
                $types = $org['event_schedule_type'] ?? [];
                foreach ($types as $type) {
                    if ($type !== '' && !array_key_exists($type, $schedulingoptions)) {
                        $schedulingoptions[$type] = $type;
                    }
                }
            }
        }
        if (count($schedulingoptions) > 1) {
            $mform->addElement(
                'select',
                'eventschedulingtype',
                get_string('eventschedulingtype', 'quizaccess_proview'),
                $schedulingoptions
            );
        } else {
            $mform->addElement(
                'text',
                'eventschedulingtype',
                get_string('eventschedulingtype', 'quizaccess_proview')
            );
            $mform->setType('eventschedulingtype', PARAM_TEXT);
        }
        $mform->addHelpButton('eventschedulingtype', 'eventschedulingtype', 'quizaccess_proview');
        $mform->setDefault('eventschedulingtype', 'bulk');
        $mform->hideIf('eventschedulingtype', 'proctoringtype', 'neq', 'live');

        $mform->addElement(
            'editor',
            'proctorinstructions',
            get_string('proctorinstructions', 'quizaccess_proview')
        );
        $mform->setType('proctorinstructions', PARAM_RAW);
        $mform->addHelpButton('proctorinstructions', 'proctorinstructions', 'quizaccess_proview');

        $mform->addElement(
            'editor',
            'candidateinstructions',
            get_string('candidateinstructions', 'quizaccess_proview')
        );
        $mform->setType('candidateinstructions', PARAM_RAW);
        $mform->addHelpButton('candidateinstructions', 'candidateinstructions', 'quizaccess_proview');

        $mform->addElement(
            'textarea',
            'referencelinks',
            get_string('referencelinks', 'quizaccess_proview'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('referencelinks', PARAM_TEXT);
        $mform->addHelpButton('referencelinks', 'referencelinks', 'quizaccess_proview');
        $mform->addRule(
            'referencelinks',
            get_string('invalid_reference_links', 'quizaccess_proview'),
            'callback',
            'quizaccess_proview::validate_reference_links'
        );

        $mform->addElement(
            'advcheckbox',
            'tsbenabled',
            get_string('tsbenabled', 'quizaccess_proview')
        );
        $mform->addHelpButton('tsbenabled', 'tsbenabled', 'quizaccess_proview');
        $mform->setDefault('tsbenabled', 0);

        $mform->addElement(
            'textarea',
            'blacklistedwindowssoftwares',
            get_string('blacklistedwindowssoftwares', 'quizaccess_proview'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('blacklistedwindowssoftwares', PARAM_TEXT);
        $mform->hideIf('blacklistedwindowssoftwares', 'tsbenabled', 'eq', 0);

        $mform->addElement(
            'textarea',
            'blacklistedmacsoftwares',
            get_string('blacklistedmacsoftwares', 'quizaccess_proview'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('blacklistedmacsoftwares', PARAM_TEXT);
        $mform->hideIf('blacklistedmacsoftwares', 'tsbenabled', 'eq', 0);

        $mform->addElement(
            'textarea',
            'whitelistedwindowssoftwares',
            get_string('whitelistedwindowssoftwares', 'quizaccess_proview'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('whitelistedwindowssoftwares', PARAM_TEXT);
        $mform->hideIf('whitelistedwindowssoftwares', 'tsbenabled', 'eq', 0);

        $mform->addElement(
            'textarea',
            'whitelistedmacsoftwares',
            get_string('whitelistedmacsoftwares', 'quizaccess_proview'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('whitelistedmacsoftwares', PARAM_TEXT);
        $mform->hideIf('whitelistedmacsoftwares', 'tsbenabled', 'eq', 0);

        $mform->addElement(
            'advcheckbox',
            'minimizepermitted',
            get_string('minimizepermitted', 'quizaccess_proview')
        );
        $mform->addHelpButton('minimizepermitted', 'minimizepermitted', 'quizaccess_proview');
        $mform->setDefault('minimizepermitted', 0);
        $mform->hideIf('minimizepermitted', 'tsbenabled', 'eq', 0);

        $mform->addElement(
            'advcheckbox',
            'screenprotection',
            get_string('screenprotection', 'quizaccess_proview')
        );
        $mform->addHelpButton('screenprotection', 'screenprotection', 'quizaccess_proview');
        $mform->setDefault('screenprotection', 0);
        $mform->hideIf('screenprotection', 'tsbenabled', 'eq', 0);

        $quizid = $quizform->get_instance();
        if ($quizid) {
            $record = $DB->get_record('quizaccess_proview', ['quizid' => $quizid]);
            if ($record) {
                $mform->setDefault('proctoringtype', $record->proctoringtype);
                $mform->setDefault('proview_token', $record->proview_token ?? '');
                $mform->setDefault('eventschedulingtype', $record->eventschedulingtype ?? '');
                $mform->setDefault('proctorinstructions', [
                    'text'   => $record->proctorinstructions ?? '',
                    'format' => FORMAT_HTML,
                ]);
                $mform->setDefault('candidateinstructions', [
                    'text'   => $record->candidateinstructions ?? '',
                    'format' => FORMAT_HTML,
                ]);
                $mform->setDefault('referencelinks', $record->referencelinks ?? '');
                $mform->setDefault('tsbenabled', $record->tsbenabled);
                $mform->setDefault('blacklistedwindowssoftwares', $record->blacklistedwindowssoftwares ?? '');
                $mform->setDefault('blacklistedmacsoftwares', $record->blacklistedmacsoftwares ?? '');
                $mform->setDefault('whitelistedwindowssoftwares', $record->whitelistedwindowssoftwares ?? '');
                $mform->setDefault('whitelistedmacsoftwares', $record->whitelistedmacsoftwares ?? '');
                $mform->setDefault('minimizepermitted', $record->minimizepermitted);
                $mform->setDefault('screenprotection', $record->screenprotection);
            }
        }
    }

    /**
     * Validate the proctoring settings submitted via the quiz editing form.
     *
     * @param array            $errors   Existing validation errors (pass-through).
     * @param array            $data     Submitted form data.
     * @param array            $files    Uploaded files (unused).
     * @param mod_quiz_mod_form $quizform The quiz settings form.
     * @return array Validation errors (key = field name, value = error string).
     */
    public static function validate_settings_form_fields(
        array $errors,
        array $data,
        $files,
        mod_quiz_mod_form $quizform
    ): array {
        return $errors;
    }

    /**
     * Save the proctoring configuration for a quiz.
     *
     * Upserts a row in mdl_quizaccess_proview, then (best-effort) syncs the
     * config to the LMS Connector API.  API failures are logged via
     * debugging() and do NOT prevent the local save from succeeding.
     *
     * @param stdClass $quiz Quiz record including all submitted form fields.
     */
    public static function save_settings($quiz): void {
        global $DB;

        $now = time();

        $proctorinstructions = '';
        if (isset($quiz->proctorinstructions)) {
            $proctorinstructions = is_array($quiz->proctorinstructions)
                ? ($quiz->proctorinstructions['text'] ?? '')
                : (string) $quiz->proctorinstructions;
        }

        $candidateinstructions = '';
        if (isset($quiz->candidateinstructions)) {
            $candidateinstructions = is_array($quiz->candidateinstructions)
                ? ($quiz->candidateinstructions['text'] ?? '')
                : (string) $quiz->candidateinstructions;
        }

        $record = $DB->get_record('quizaccess_proview', ['quizid' => $quiz->id]);

        if ($record) {
            $record->proctoringenabled           = 1;
            $record->proctoringtype              = $quiz->proctoringtype ?? 'none';
            $record->proview_token               = $quiz->proview_token ?? null;
            $record->eventschedulingtype         = $quiz->eventschedulingtype ?? null;
            $record->proctorinstructions         = $proctorinstructions;
            $record->candidateinstructions       = $candidateinstructions;
            $record->referencelinks              = $quiz->referencelinks ?? null;
            $record->tsbenabled                  = (int) !empty($quiz->tsbenabled);
            $record->blacklistedwindowssoftwares = $quiz->blacklistedwindowssoftwares ?? null;
            $record->blacklistedmacsoftwares     = $quiz->blacklistedmacsoftwares ?? null;
            $record->whitelistedwindowssoftwares = $quiz->whitelistedwindowssoftwares ?? null;
            $record->whitelistedmacsoftwares     = $quiz->whitelistedmacsoftwares ?? null;
            $record->minimizepermitted           = (int) !empty($quiz->minimizepermitted);
            $record->screenprotection            = (int) !empty($quiz->screenprotection);
            $record->timemodified                = $now;
            $DB->update_record('quizaccess_proview', $record);
        } else {
            $record                              = new \stdClass();
            $record->quizid                      = $quiz->id;
            $record->proctoringenabled           = 1;
            $record->proctoringtype              = $quiz->proctoringtype ?? 'none';
            $record->proview_token               = $quiz->proview_token ?? null;
            $record->eventschedulingtype         = $quiz->eventschedulingtype ?? null;
            $record->proctorinstructions         = $proctorinstructions;
            $record->candidateinstructions       = $candidateinstructions;
            $record->referencelinks              = $quiz->referencelinks ?? null;
            $record->tsbenabled                  = (int) !empty($quiz->tsbenabled);
            $record->blacklistedwindowssoftwares = $quiz->blacklistedwindowssoftwares ?? null;
            $record->blacklistedmacsoftwares     = $quiz->blacklistedmacsoftwares ?? null;
            $record->whitelistedwindowssoftwares = $quiz->whitelistedwindowssoftwares ?? null;
            $record->whitelistedmacsoftwares     = $quiz->whitelistedmacsoftwares ?? null;
            $record->minimizepermitted           = (int) !empty($quiz->minimizepermitted);
            $record->screenprotection            = (int) !empty($quiz->screenprotection);
            $record->timecreated                 = $now;
            $record->timemodified                = $now;
            $DB->insert_record('quizaccess_proview', $record);
        }

        try {
            $typemap = [
                'ai'            => 'ai_proctor',
                'record_review' => 'record_and_review',
                'live'          => 'live_proctor',
            ];
            $isnone          = ($record->proctoringtype === 'none');
            $proctoringenabled = !$isnone;
            $apitype           = $typemap[$record->proctoringtype] ?? null;

            $tokenmgr = new \quizaccess_proview\token_manager();
            $token    = $tokenmgr->get_token();
            $payload  = [
                'action'                        => empty($quiz->instance) ? 0 : 1,
                'quiz_id'                       => (int) $quiz->id,
                'quiz_title'                    => (string) ($quiz->name ?? ''),
                'course_id'                     => (int) $quiz->course,
                'course_module_id'              => (string) ($quiz->coursemodule ?? ''),
                'attempts'                      => (int) ($quiz->attempts ?? 0),
                'timeopen'                      => (int) ($quiz->timeopen ?? 0),
                'timeclose'                     => (int) ($quiz->timeclose ?? 0),
                'timelimit'                     => (int) ($quiz->timelimit ?? 0),
                'overduehandling'               => (string) ($quiz->overduehandling ?? 'autosubmit'),
                'graceperiod'                   => (int) ($quiz->graceperiod ?? 0),
                'proctoring_enabled'            => $proctoringenabled,
                'tsb_enabled'                   => (bool) $record->tsbenabled,
                'proview_token'                 => (string) ($record->proview_token ?? ''),
                'scheduling_type'               => (string) ($record->eventschedulingtype ?? ''),
                'proctor_instructions'          => $proctorinstructions,
                'candidate_instructions'        => $candidateinstructions,
                'reference_links'               => (string) ($record->referencelinks ?? ''),
                'blacklisted_windows_softwares' => (string) ($record->blacklistedwindowssoftwares ?? ''),
                'blacklisted_mac_softwares'     => (string) ($record->blacklistedmacsoftwares ?? ''),
                'whitelisted_windows_softwares' => (string) ($record->whitelistedwindowssoftwares ?? ''),
                'whitelisted_mac_softwares'     => (string) ($record->whitelistedmacsoftwares ?? ''),
                'minimize_permitted'            => (bool) $record->minimizepermitted,
                'screen_protection'             => (bool) $record->screenprotection,
                'timemodified'                  => (int) $now,
            ];
            if ($apitype !== null) {
                $payload['proctoring_type'] = $apitype;
            }
            \quizaccess_proview\api::save_quiz($token, $payload);
        } catch (\moodle_exception $e) {
            debugging('[quizaccess_proview] API sync failed in save_settings(): ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Whether a preflight check is required before this attempt can proceed.
     *
     * Returns true for proctored quizzes so startattempt.php shows the preflight
     * page, which immediately redirects to frame.php (no attempt created yet).
     * This ensures Proview's hardware check runs before the quiz timer starts.
     *
     * @param int|null $attemptid The existing attempt ID, or null if no attempt yet.
     * @return bool
     */
    public function is_preflight_check_required($attemptid) {
        global $CFG;

        $context = $this->quizobj->get_context();
        if (has_capability('quizaccess/proview:manage', $context)) {
            return false;
        }

        $tsb       = !empty($this->proviewconfig->tsbenabled);
        $intbs     = strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Proview-SB') !== false;
        $proctored = $this->proviewconfig->proctoringtype !== 'none';

        if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'startattempt.php') {
            if ($proctored && !($tsb && !$intbs)) {
                $cm = $this->quizobj->get_cm();
                redirect(new \moodle_url(
                    $CFG->wwwroot . '/mod/quiz/accessrule/proview/frame.php',
                    ['quizid' => (int) $this->proviewconfig->quizid, 'cmid' => (int) $cm->id, 'sesskey' => sesskey()]
                ));
            }
            return ($tsb && !$intbs);
        }

        return false;
    }

    /**
     * Inject Proview launch logic into the preflight form page.
     *
     * This runs before the quiz attempt is created (and before the timer starts).
     * Three modes:
     *  - TSB + not in TSB: JS redirect to TSB wrapper URL.
     *  - TSB + in TSB + Proview: load CDN and start Proview preflight inside TSB.
     *  - Proview only: load CDN and start Proview preflight in regular browser.
     *
     * @param mod_quiz_preflight_check_form $quizform The preflight form object.
     * @param MoodleQuickForm               $mform    The underlying QuickForm object.
     * @param int|null                      $attemptid Existing attempt ID, or null.
     */
    public function add_preflight_check_form_fields($quizform, $mform, $attemptid) {
        global $PAGE, $USER, $DB, $CFG;

        $config    = $this->proviewconfig;
        $tsb       = !empty($config->tsbenabled);
        $intbs     = strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Proview-SB') !== false;
        $proctored = $config->proctoringtype !== 'none';

        if ($tsb && !$intbs) {
            $islive    = $config->proctoringtype === 'live';
            $attemptno = (int) $DB->count_records_select(
                'quiz_attempts',
                'quiz = :quiz AND userid = :userid AND state <> :abandoned',
                ['quiz' => $config->quizid, 'userid' => $USER->id, 'abandoned' => 'abandoned']
            );
            $attemptno = max(1, $attemptno);
            $sessionid = $config->quizid . '-' . $USER->id . ($islive ? '' : '-' . $attemptno);

            try {
                $tokenmgr = new \quizaccess_proview\token_manager();
                $token    = $tokenmgr->get_token();
            } catch (\moodle_exception $e) {
                debugging('[quizaccess_proview] Token fetch failed (TSB preflight): ' . $e->getMessage(), DEBUG_DEVELOPER);
                return;
            }

            $redirecturl = $this->quizobj->view_url()->out(false);
            $closetime   = (int) ($this->quizobj->get_quiz()->timeclose ?? 0);
            $expiry      = $closetime > 0 ? $closetime : time() + (3 * DAYSECS);

            try {
                $wrapperurl = \quizaccess_proview\api::create_tsb_wrapper(
                    $token,
                    $sessionid,
                    (string) $USER->id,
                    $redirecturl,
                    $expiry
                );
            } catch (\moodle_exception $e) {
                debugging('[quizaccess_proview] TSB wrapper creation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                return;
            }

            $PAGE->requires->js_call_amd('quizaccess_proview/proview_launch', 'redirectToTsb', [$wrapperurl]);
            return;
        }

        if (!$proctored) {
            return;
        }
    }

    /**
     * Validate the preflight check submission.
     *
     * Proview manages its own preflight UI and completion state; no additional
     * Moodle-side validation is required here.
     *
     * @param array    $data      Submitted form data.
     * @param array    $files     Uploaded files (unused).
     * @param array    $errors    Existing validation errors (pass-through).
     * @param int|null $attemptid Existing attempt ID, or null.
     * @return array Validation errors.
     */
    public function validate_preflight_check($data, $files, $errors, $attemptid) {
        return $errors;
    }

    /**
     * Redirect the quiz attempt page into the Proview iframe wrapper (frame.php),
     * or — when already running inside that iframe — intercept the "Finish attempt"
     * navigation and postMessage the parent to stop the Proview session first.
     *
     * @param moodle_page $page The current quiz attempt page.
     */
    public function setup_attempt_page($page) {
        global $DB, $USER, $CFG;

        $path      = $page->url->get_path();
        $isattempt = strpos($path, '/mod/quiz/attempt.php') !== false;
        $issummary = strpos($path, '/mod/quiz/summary.php') !== false;
        $isreview  = strpos($path, '/mod/quiz/review.php') !== false;

        if (!$isattempt && !$issummary && !$isreview) {
            return;
        }

        $config    = $this->proviewconfig;
        $proctored = $config->proctoringtype !== 'none';

        if (!$proctored) {
            return;
        }

        if ($isreview) {
            $page->set_pagelayout('secure');
            $page->requires->js_amd_inline('
                (function() {
                    if (window.self === window.top) { return; }
                    document.addEventListener("click", function(e) {
                        var a = e.target.closest("a[href]");
                        if (a && a.href.indexOf("/mod/quiz/view.php") !== -1) {
                            e.preventDefault();
                            window.parent.postMessage({ type: "stopProview", url: a.href }, "*");
                        }
                    });
                })();
            ');
            return;
        }

        $frameurl = (new \moodle_url(
            $CFG->wwwroot . '/mod/quiz/accessrule/proview/frame.php',
            [
                'quizid'  => (int) $config->quizid,
                'sesskey' => sesskey(),
            ]
        ))->out(false);

        if ($isattempt) {
            $inframe = optional_param('proview_iframe', 0, PARAM_INT);
            if ($inframe) {
                $attemptno = (int) $DB->count_records_select(
                    'quiz_attempts',
                    'quiz = :quiz AND userid = :userid AND state <> :abandoned',
                    ['quiz' => $config->quizid, 'userid' => $USER->id, 'abandoned' => 'abandoned']
                );
                $attemptno = max(1, $attemptno);
                $exists = $DB->record_exists('quizaccess_proview_attempts', [
                    'quizid' => $config->quizid, 'userid' => $USER->id, 'attemptno' => $attemptno,
                ]);
                if (!$exists) {
                    $row              = new \stdClass();
                    $row->quizid      = (int) $config->quizid;
                    $row->userid      = (int) $USER->id;
                    $row->attemptno   = $attemptno;
                    $row->proctortype = $config->proctoringtype;
                    $row->timecreated = time();
                    $DB->insert_record('quizaccess_proview_attempts', $row);
                }
                $page->set_pagelayout('secure');
                return;
            }

            redirect(new \moodle_url($frameurl));
        }

        $page->set_pagelayout('secure');

        $jsfameurl = json_encode($frameurl);
        $page->requires->js_amd_inline('
            (function() {
                if (window.self === window.top) {
                    window.location.replace(' . $jsfameurl . ');
                }
            })();
        ');
    }

    /**
     * Delete the proctoring configuration for a quiz (called when quiz is deleted).
     *
     * @param stdClass $quiz The quiz being deleted.
     */
    public static function delete_settings($quiz): void {
        global $DB;
        $DB->delete_records('quizaccess_proview', ['quizid' => $quiz->id]);
    }
}
