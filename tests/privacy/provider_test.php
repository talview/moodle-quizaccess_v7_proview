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
 * PHPUnit tests for quizaccess_proview\privacy\provider.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_proview\privacy\provider
 * @group      quizaccess_proview
 */

namespace quizaccess_proview\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\external_location;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\tests\provider_testcase;

/**
 * Tests for {@see \quizaccess_proview\privacy\provider}.
 *
 * This plugin stores no personal data locally — proctoring session data is
 * held by Talview's Proview service. Tests verify that the provider correctly
 * declares the external data location and that all GDPR action methods are
 * safe no-ops.
 *
 * @covers \quizaccess_proview\privacy\provider
 */
final class provider_test extends provider_testcase {
    /**
     * get_metadata() must return a collection instance.
     */
    public function test_get_metadata_returns_collection(): void {
        $collection = new collection('quizaccess_proview');
        $result = provider::get_metadata($collection);

        $this->assertInstanceOf(collection::class, $result);
    }

    /**
     * get_metadata() must register an external location for 'talview_proview'.
     */
    public function test_get_metadata_registers_talview_proview_external_location(): void {
        $collection = new collection('quizaccess_proview');
        $result = provider::get_metadata($collection);

        $found = false;
        foreach ($result->get_collection() as $item) {
            if ($item instanceof external_location && $item->get_name() === 'talview_proview') {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            'Expected an external_location named "talview_proview" in the metadata collection.'
        );
    }

    /**
     * The talview_proview external location must declare the four expected privacy fields.
     */
    public function test_get_metadata_external_location_declares_required_fields(): void {
        $collection = new collection('quizaccess_proview');
        $result = provider::get_metadata($collection);

        $privacyfields = null;
        foreach ($result->get_collection() as $item) {
            if ($item instanceof external_location && $item->get_name() === 'talview_proview') {
                $privacyfields = $item->get_privacy_fields();
                break;
            }
        }

        $this->assertNotNull($privacyfields, 'external_location "talview_proview" not found.');
        $this->assertArrayHasKey('userid', $privacyfields);
        $this->assertArrayHasKey('quizid', $privacyfields);
        $this->assertArrayHasKey('fullname', $privacyfields);
        $this->assertArrayHasKey('email', $privacyfields);
    }

    /**
     * The external location must include a summary string key.
     */
    public function test_get_metadata_external_location_has_summary(): void {
        $collection = new collection('quizaccess_proview');
        $result = provider::get_metadata($collection);

        foreach ($result->get_collection() as $item) {
            if ($item instanceof external_location && $item->get_name() === 'talview_proview') {
                $this->assertNotEmpty(
                    $item->get_summary(),
                    'external_location must have a non-empty summary string key.'
                );
                return;
            }
        }

        $this->fail('external_location "talview_proview" not found.');
    }

    /**
     * get_contexts_for_userid() must return a contextlist instance.
     */
    public function test_get_contexts_for_userid_returns_contextlist(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $result = provider::get_contexts_for_userid($user->id);

        $this->assertInstanceOf(contextlist::class, $result);
    }

    /**
     * get_contexts_for_userid() must return an empty contextlist — no local
     * personal data is stored by this plugin.
     */
    public function test_get_contexts_for_userid_returns_empty_contextlist(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $result = provider::get_contexts_for_userid($user->id);

        $this->assertCount(
            0,
            $result,
            'No contexts expected: plugin stores no personal data locally.'
        );
    }

    /**
     * get_contexts_for_userid() must always return empty, even for admin users.
     */
    public function test_get_contexts_for_userid_empty_for_admin(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $USER;

        $result = provider::get_contexts_for_userid($USER->id);

        $this->assertCount(0, $result);
    }

    /**
     * export_user_data() must complete without throwing.
     */
    public function test_export_user_data_does_not_throw(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $contextlist = new approved_contextlist($user, 'quizaccess_proview', []);

        provider::export_user_data($contextlist);
        $this->assertTrue(true);
    }

    /**
     * export_user_data() with an empty context list must complete without throwing.
     */
    public function test_export_user_data_with_empty_contextlist(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $contextlist = new approved_contextlist($user, 'quizaccess_proview', []);

        provider::export_user_data($contextlist);
        $this->assertTrue(true);
    }

    /**
     * delete_data_for_all_users_in_context() must complete without throwing
     * for a course context.
     */
    public function test_delete_data_for_all_users_in_context_does_not_throw(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        provider::delete_data_for_all_users_in_context($context);
        $this->assertTrue(true);
    }

    /**
     * delete_data_for_all_users_in_context() must complete without throwing
     * for the system context.
     */
    public function test_delete_data_for_all_users_in_system_context(): void {
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertTrue(true);
    }

    /**
     * delete_data_for_user() must complete without throwing.
     */
    public function test_delete_data_for_user_does_not_throw(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $contextlist = new approved_contextlist($user, 'quizaccess_proview', []);

        provider::delete_data_for_user($contextlist);
        $this->assertTrue(true);
    }

    /**
     * delete_data_for_user() with multiple contexts must complete without throwing.
     */
    public function test_delete_data_for_user_with_multiple_contexts(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        $contextids = [
            \context_course::instance($course1->id)->id,
            \context_course::instance($course2->id)->id,
        ];
        $contextlist = new approved_contextlist($user, 'quizaccess_proview', $contextids);

        provider::delete_data_for_user($contextlist);
        $this->assertTrue(true);
    }

    /**
     * provider must implement core_privacy metadata provider.
     */
    public function test_provider_implements_metadata_provider(): void {
        $this->assertInstanceOf(
            \core_privacy\local\metadata\provider::class,
            new provider()
        );
    }

    /**
     * provider must implement core_privacy request plugin provider.
     */
    public function test_provider_implements_request_provider(): void {
        $this->assertInstanceOf(
            \core_privacy\local\request\plugin\provider::class,
            new provider()
        );
    }
}
