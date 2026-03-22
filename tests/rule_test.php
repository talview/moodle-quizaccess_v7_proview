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
 * PHPUnit tests for quizaccess_proview rule class.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_proview
 * @group      quizaccess_proview
 */

namespace quizaccess_proview;

/**
 * Tests for {@see \quizaccess_proview}.
 *
 * @covers \quizaccess_proview
 */
final class rule_test extends \advanced_testcase {
    // Helpers section.

    /**
     * Build a minimal quiz stdClass suitable for save_settings() / delete_settings().
     *
     * @param array $overrides Fields to override on the base object.
     * @return stdClass
     */
    private function make_quiz(array $overrides = []): stdClass {
        return (object) array_merge([
            'id'                          => 0,
            'course'                      => 1,
            'name'                        => 'Test quiz',
            'coursemodule'                => 0,
            'instance'                    => 1,
            'proctoringtype'              => 'none',
            'proview_token'               => null,
            'eventschedulingtype'         => null,
            'proctorinstructions'         => '',
            'candidateinstructions'       => '',
            'referencelinks'              => null,
            'tsbenabled'                  => 0,
            'blacklistedwindowssoftwares' => null,
            'blacklistedmacsoftwares'     => null,
            'whitelistedwindowssoftwares' => null,
            'whitelistedmacsoftwares'     => null,
            'minimizepermitted'           => 0,
            'screenprotection'            => 0,
            'attempts'                    => 0,
            'timeopen'                    => 0,
            'timeclose'                   => 0,
            'timelimit'                   => 0,
            'overduehandling'             => 'autosubmit',
            'graceperiod'                 => 0,
        ], $overrides);
    }

    // Tests for validate_reference_links.

    /**
     * An empty string must be valid.
     */
    public function test_validate_reference_links_empty_string(): void {
        $this->assertTrue(\quizaccess_proview::validate_reference_links(''));
    }

    /**
     * A single well-formed markdown link must be valid.
     */
    public function test_validate_reference_links_single_valid_link(): void {
        $this->assertTrue(
            \quizaccess_proview::validate_reference_links('[Docs](https://example.com)')
        );
    }

    /**
     * Multiple well-formed links (one per line) must be valid.
     */
    public function test_validate_reference_links_multiple_valid_links(): void {
        $value = "[Link A](https://a.example.com)\n[Link B](https://b.example.com)";
        $this->assertTrue(\quizaccess_proview::validate_reference_links($value));
    }

    /**
     * Blank lines between valid links must be ignored.
     */
    public function test_validate_reference_links_blank_lines_are_ignored(): void {
        $value = "[Link A](https://a.example.com)\n\n[Link B](https://b.example.com)";
        $this->assertTrue(\quizaccess_proview::validate_reference_links($value));
    }

    /**
     * A line with plain text (no markdown format) must fail validation.
     */
    public function test_validate_reference_links_plain_text_is_invalid(): void {
        $this->assertFalse(\quizaccess_proview::validate_reference_links('just plain text'));
    }

    /**
     * A line missing the URL portion must fail validation.
     */
    public function test_validate_reference_links_missing_url_is_invalid(): void {
        $this->assertFalse(\quizaccess_proview::validate_reference_links('[Label]()'));
    }

    /**
     * A line with a non-URL value in the URL portion must fail validation.
     */
    public function test_validate_reference_links_invalid_url_is_invalid(): void {
        $this->assertFalse(\quizaccess_proview::validate_reference_links('[Label](not-a-url)'));
    }

    /**
     * A mix of valid and invalid lines must return false.
     */
    public function test_validate_reference_links_mixed_valid_and_invalid(): void {
        $value = "[Good](https://example.com)\nbad line";
        $this->assertFalse(\quizaccess_proview::validate_reference_links($value));
    }

    /**
     * Windows-style CRLF line endings must be handled correctly.
     */
    public function test_validate_reference_links_crlf_line_endings(): void {
        $value = "[A](https://a.example.com)\r\n[B](https://b.example.com)";
        $this->assertTrue(\quizaccess_proview::validate_reference_links($value));
    }

    // Tests for save_settings: DB upsert.

    /**
     * save_settings() must insert a new row when none exists for the quiz.
     */
    public function test_save_settings_inserts_new_record(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = $this->make_quiz(['id' => 42, 'course' => 1]);

        \quizaccess_proview::save_settings($quiz);

        $record = $DB->get_record('quizaccess_proview', ['quizid' => 42]);
        $this->assertNotFalse($record, 'Expected a DB record to be inserted for quizid=42.');
    }

    /**
     * save_settings() must always set proctoringenabled = 1.
     */
    public function test_save_settings_sets_proctoring_enabled(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = $this->make_quiz(['id' => 43]);
        \quizaccess_proview::save_settings($quiz);

        $record = $DB->get_record('quizaccess_proview', ['quizid' => 43]);
        $this->assertSame('1', (string) $record->proctoringenabled);
    }

    /**
     * save_settings() must persist the proctoringtype value.
     */
    public function test_save_settings_persists_proctoringtype(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = $this->make_quiz(['id' => 44, 'proctoringtype' => 'ai']);
        \quizaccess_proview::save_settings($quiz);

        $record = $DB->get_record('quizaccess_proview', ['quizid' => 44]);
        $this->assertSame('ai', $record->proctoringtype);
    }

    /**
     * save_settings() must update an existing row rather than insert a second one.
     */
    public function test_save_settings_updates_existing_record(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = $this->make_quiz(['id' => 45, 'proctoringtype' => 'none']);
        \quizaccess_proview::save_settings($quiz);

        $quiz->proctoringtype = 'live';
        \quizaccess_proview::save_settings($quiz);

        $records = $DB->get_records('quizaccess_proview', ['quizid' => 45]);
        $this->assertCount(1, $records, 'Expected exactly one DB row after two saves.');

        $record = reset($records);
        $this->assertSame('live', $record->proctoringtype);
    }

    /**
     * save_settings() must persist tsbenabled correctly when set to 1.
     */
    public function test_save_settings_persists_tsbenabled_true(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = $this->make_quiz(['id' => 46, 'tsbenabled' => 1]);
        \quizaccess_proview::save_settings($quiz);

        $record = $DB->get_record('quizaccess_proview', ['quizid' => 46]);
        $this->assertSame('1', (string) $record->tsbenabled);
    }

    /**
     * save_settings() must persist tsbenabled = 0 when not set.
     */
    public function test_save_settings_persists_tsbenabled_false(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = $this->make_quiz(['id' => 47, 'tsbenabled' => 0]);
        \quizaccess_proview::save_settings($quiz);

        $record = $DB->get_record('quizaccess_proview', ['quizid' => 47]);
        $this->assertSame('0', (string) $record->tsbenabled);
    }

    /**
     * save_settings() must extract HTML text from an editor array for candidateinstructions.
     */
    public function test_save_settings_extracts_editor_array_for_candidate_instructions(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = $this->make_quiz([
            'id'                    => 48,
            'candidateinstructions' => ['text' => '<p>Hello candidate</p>', 'format' => 1],
        ]);
        \quizaccess_proview::save_settings($quiz);

        $record = $DB->get_record('quizaccess_proview', ['quizid' => 48]);
        $this->assertSame('<p>Hello candidate</p>', $record->candidateinstructions);
    }

    /**
     * save_settings() must accept a plain string for candidateinstructions.
     */
    public function test_save_settings_accepts_string_candidate_instructions(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = $this->make_quiz([
            'id'                    => 49,
            'candidateinstructions' => 'Plain instructions',
        ]);
        \quizaccess_proview::save_settings($quiz);

        $record = $DB->get_record('quizaccess_proview', ['quizid' => 49]);
        $this->assertSame('Plain instructions', $record->candidateinstructions);
    }

    /**
     * save_settings() must persist minimizepermitted and screenprotection.
     */
    public function test_save_settings_persists_tsb_flags(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = $this->make_quiz([
            'id'                => 50,
            'minimizepermitted' => 1,
            'screenprotection'  => 1,
        ]);
        \quizaccess_proview::save_settings($quiz);

        $record = $DB->get_record('quizaccess_proview', ['quizid' => 50]);
        $this->assertSame('1', (string) $record->minimizepermitted);
        $this->assertSame('1', (string) $record->screenprotection);
    }

    /**
     * save_settings() must set timecreated and timemodified on a new record.
     */
    public function test_save_settings_sets_timestamps_on_insert(): void {
        global $DB;
        $this->resetAfterTest();

        $before = time();
        $quiz = $this->make_quiz(['id' => 51]);
        \quizaccess_proview::save_settings($quiz);
        $after = time();

        $record = $DB->get_record('quizaccess_proview', ['quizid' => 51]);
        $this->assertGreaterThanOrEqual($before, (int) $record->timecreated);
        $this->assertLessThanOrEqual($after, (int) $record->timecreated);
        $this->assertGreaterThanOrEqual($before, (int) $record->timemodified);
    }

    /**
     * save_settings() must not throw even when the API sync fails.
     */
    public function test_save_settings_does_not_throw_on_api_failure(): void {
        global $DB;
        $this->resetAfterTest();

        // No API config set, so token call will fail, but save_settings must still complete.
        $quiz = $this->make_quiz(['id' => 52]);

        \quizaccess_proview::save_settings($quiz);

        $record = $DB->get_record('quizaccess_proview', ['quizid' => 52]);
        $this->assertNotFalse($record, 'DB record must be saved even when API sync fails.');
    }

    // Tests for delete_settings.

    /**
     * delete_settings() must remove the DB record for the given quiz.
     */
    public function test_delete_settings_removes_record(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = $this->make_quiz(['id' => 60]);
        \quizaccess_proview::save_settings($quiz);

        $this->assertNotFalse($DB->get_record('quizaccess_proview', ['quizid' => 60]));

        \quizaccess_proview::delete_settings($quiz);

        $this->assertFalse(
            $DB->get_record('quizaccess_proview', ['quizid' => 60]),
            'DB record must be deleted after delete_settings().'
        );
    }

    /**
     * delete_settings() on a quiz with no record must not throw.
     */
    public function test_delete_settings_is_safe_when_no_record_exists(): void {
        $this->resetAfterTest();
        $quiz = $this->make_quiz(['id' => 61]);

        \quizaccess_proview::delete_settings($quiz);
        $this->assertTrue(true);
    }

    // Tests for make.

    /**
     * make() must return null when no proctoring record exists for the quiz.
     */
    public function test_make_returns_null_when_no_record(): void {
        $this->resetAfterTest();

        $quizobj = $this->createMock(\mod_quiz\quiz_settings::class);
        $quizobj->method('get_quizid')->willReturn(9999);

        $result = \quizaccess_proview::make($quizobj, time(), false);

        $this->assertNull($result);
    }

    /**
     * make() must return a quizaccess_proview instance when a record exists.
     */
    public function test_make_returns_instance_when_record_exists(): void {
        $this->resetAfterTest();

        $quizid = 70;
        $quiz = $this->make_quiz(['id' => $quizid, 'proctoringtype' => 'ai']);
        \quizaccess_proview::save_settings($quiz);

        $quizobj = $this->createMock(\mod_quiz\quiz_settings::class);
        $quizobj->method('get_quizid')->willReturn($quizid);

        $result = \quizaccess_proview::make($quizobj, time(), false);

        $this->assertInstanceOf(\quizaccess_proview::class, $result);
    }

    /**
     * make() must return null when proctoringtype is 'none' and TSB is disabled.
     */
    public function test_make_returns_null_when_proctoring_none_and_tsb_disabled(): void {
        $this->resetAfterTest();

        $quizid = 71;
        $quiz = $this->make_quiz(['id' => $quizid, 'proctoringtype' => 'none', 'tsbenabled' => 0]);
        \quizaccess_proview::save_settings($quiz);

        $quizobj = $this->createMock(\mod_quiz\quiz_settings::class);
        $quizobj->method('get_quizid')->willReturn($quizid);

        $result = \quizaccess_proview::make($quizobj, time(), false);

        $this->assertNull($result);
    }

    /**
     * make() must return an instance when proctoringtype is 'none' but TSB is enabled (Mode 1).
     */
    public function test_make_returns_instance_when_tsb_only(): void {
        $this->resetAfterTest();

        $quizid = 72;
        $quiz = $this->make_quiz(['id' => $quizid, 'proctoringtype' => 'none', 'tsbenabled' => 1]);
        \quizaccess_proview::save_settings($quiz);

        $quizobj = $this->createMock(\mod_quiz\quiz_settings::class);
        $quizobj->method('get_quizid')->willReturn($quizid);

        $result = \quizaccess_proview::make($quizobj, time(), false);

        $this->assertInstanceOf(\quizaccess_proview::class, $result);
    }

    // Tests for save_settings: additional field coverage.

    /**
     * save_settings() must extract HTML text from an editor array for proctorinstructions.
     */
    public function test_save_settings_extracts_editor_array_for_proctor_instructions(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = $this->make_quiz([
            'id'                  => 53,
            'proctorinstructions' => ['text' => '<p>Watch carefully</p>', 'format' => 1],
        ]);
        \quizaccess_proview::save_settings($quiz);

        $record = $DB->get_record('quizaccess_proview', ['quizid' => 53]);
        $this->assertSame('<p>Watch carefully</p>', $record->proctorinstructions);
    }

    /**
     * save_settings() must persist referencelinks.
     */
    public function test_save_settings_persists_referencelinks(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = $this->make_quiz([
            'id'             => 54,
            'referencelinks' => "[Docs](https://example.com)\n[API](https://api.example.com)",
        ]);
        \quizaccess_proview::save_settings($quiz);

        $record = $DB->get_record('quizaccess_proview', ['quizid' => 54]);
        $this->assertSame("[Docs](https://example.com)\n[API](https://api.example.com)", $record->referencelinks);
    }

    /**
     * save_settings() must persist blacklisted and whitelisted software lists.
     */
    public function test_save_settings_persists_software_lists(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = $this->make_quiz([
            'id'                          => 55,
            'blacklistedwindowssoftwares' => 'notepad.exe,chrome.exe',
            'blacklistedmacsoftwares'     => 'TextEdit,Safari',
            'whitelistedwindowssoftwares' => 'explorer.exe',
            'whitelistedmacsoftwares'     => 'Finder',
        ]);
        \quizaccess_proview::save_settings($quiz);

        $record = $DB->get_record('quizaccess_proview', ['quizid' => 55]);
        $this->assertSame('notepad.exe,chrome.exe', $record->blacklistedwindowssoftwares);
        $this->assertSame('TextEdit,Safari', $record->blacklistedmacsoftwares);
        $this->assertSame('explorer.exe', $record->whitelistedwindowssoftwares);
        $this->assertSame('Finder', $record->whitelistedmacsoftwares);
    }

    /**
     * save_settings() must persist eventschedulingtype.
     */
    public function test_save_settings_persists_eventschedulingtype(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = $this->make_quiz(['id' => 56, 'proctoringtype' => 'live', 'eventschedulingtype' => 'bulk']);
        \quizaccess_proview::save_settings($quiz);

        $record = $DB->get_record('quizaccess_proview', ['quizid' => 56]);
        $this->assertSame('bulk', $record->eventschedulingtype);
    }

    // Tests for is_preflight_check_required.

    /**
     * is_preflight_check_required() must return false on non-startattempt.php pages
     * for a proctored quiz — the redirect to frame.php is handled inside startattempt.php
     * and the preflight form is never shown.
     */
    public function test_preflight_required_returns_false_outside_startattempt(): void {
        $this->resetAfterTest();

        $quizid = 80;
        $quiz = $this->make_quiz(['id' => $quizid, 'proctoringtype' => 'ai']);
        \quizaccess_proview::save_settings($quiz);

        $quizobj = $this->createMock(\mod_quiz\quiz_settings::class);
        $quizobj->method('get_quizid')->willReturn($quizid);
        $rule = \quizaccess_proview::make($quizobj, time(), false);

        // PHPUnit's SCRIPT_FILENAME is never startattempt.php, so this always returns false.
        $this->assertFalse($rule->is_preflight_check_required(null));
        $this->assertFalse($rule->is_preflight_check_required(123));
    }

    /**
     * is_preflight_check_required() must return true on startattempt.php when TSB is
     * enabled and the candidate is NOT using the TSB browser (redirect-to-TSB mode).
     */
    public function test_preflight_required_returns_true_on_startattempt_when_tsb_not_in_browser(): void {
        $this->resetAfterTest();

        $quizid = 81;
        $quiz = $this->make_quiz(['id' => $quizid, 'proctoringtype' => 'none', 'tsbenabled' => 1]);
        \quizaccess_proview::save_settings($quiz);

        $quizobj = $this->createMock(\mod_quiz\quiz_settings::class);
        $quizobj->method('get_quizid')->willReturn($quizid);
        $rule = \quizaccess_proview::make($quizobj, time(), false);

        // Simulate being called from startattempt.php with a standard browser UA.
        $origscript = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $origua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SERVER['SCRIPT_FILENAME'] = '/var/www/html/moodle/mod/quiz/startattempt.php';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (standard browser, not TSB)';

        $result = $rule->is_preflight_check_required(null);

        $_SERVER['SCRIPT_FILENAME'] = $origscript;
        $_SERVER['HTTP_USER_AGENT'] = $origua;

        $this->assertTrue($result);
    }

    /**
     * is_preflight_check_required() must return false on non-startattempt.php for TSB quiz.
     */
    public function test_preflight_not_required_for_existing_attempt_no_tsb(): void {
        $this->resetAfterTest();

        $quizid = 82;
        $quiz = $this->make_quiz(['id' => $quizid, 'proctoringtype' => 'ai', 'tsbenabled' => 0]);
        \quizaccess_proview::save_settings($quiz);

        $quizobj = $this->createMock(\mod_quiz\quiz_settings::class);
        $quizobj->method('get_quizid')->willReturn($quizid);
        $rule = \quizaccess_proview::make($quizobj, time(), false);

        $this->assertFalse($rule->is_preflight_check_required(123));
    }

    /**
     * validate_preflight_check() must pass errors through unchanged.
     */
    public function test_validate_preflight_check_passes_errors_through(): void {
        $this->resetAfterTest();

        $quizid = 90;
        $quiz = $this->make_quiz(['id' => $quizid, 'proctoringtype' => 'ai']);
        \quizaccess_proview::save_settings($quiz);

        $quizobj = $this->createMock(\mod_quiz\quiz_settings::class);
        $quizobj->method('get_quizid')->willReturn($quizid);
        $rule = \quizaccess_proview::make($quizobj, time(), false);

        $errors = ['somefield' => 'Some error'];
        $result = $rule->validate_preflight_check([], [], $errors, null);

        $this->assertSame($errors, $result);
    }

    /**
     * validate_preflight_check() must return an empty array when no errors are passed.
     */
    public function test_validate_preflight_check_returns_empty_on_no_errors(): void {
        $this->resetAfterTest();

        $quizid = 91;
        $quiz = $this->make_quiz(['id' => $quizid, 'proctoringtype' => 'ai']);
        \quizaccess_proview::save_settings($quiz);

        $quizobj = $this->createMock(\mod_quiz\quiz_settings::class);
        $quizobj->method('get_quizid')->willReturn($quizid);
        $rule = \quizaccess_proview::make($quizobj, time(), false);

        $this->assertSame([], $rule->validate_preflight_check([], [], [], null));
    }
}
