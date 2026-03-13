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
 * Privacy provider stub for quizaccess_proview.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_proview\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;

/**
 * Privacy provider for quizaccess_proview.
 *
 * This plugin stores per-quiz proctoring configuration (not personal data).
 * Personal data (proctoring session recordings) is held by Talview's Proview
 * service under the institution's data processing agreement.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'talview_proview',
            [
                'userid'   => 'privacy:metadata:talview_proview:userid',
                'quizid'   => 'privacy:metadata:talview_proview:quizid',
                'fullname' => 'privacy:metadata:talview_proview:fullname',
                'email'    => 'privacy:metadata:talview_proview:email',
            ],
            'privacy:metadata:talview_proview'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the given user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.

     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * Export all user data for the specified user in the contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export for.

     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        // No personal data stored locally — proctoring data held by Talview Proview.
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param \context $context The context to delete in.

     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        // No personal data stored locally.
    }

    /**
     * Delete all user data for the specified user in the contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete.

     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        // No personal data stored locally.
    }
}
