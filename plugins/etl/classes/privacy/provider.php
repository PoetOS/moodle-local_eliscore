<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * ELIS core / eliscore subplugin privacy API.
 *
 * @package    eliscore_etl
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 Remote Learner.net Inc http://www.remote-learner.net
 */

namespace eliscore_etl\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements
    // This plugin has data.
    \core_privacy\local\metadata\provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider,

    // This plugin currently implements the original plugin_provider interface.
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns meta data about this system.
     *
     * @param   collection $items The collection to add metadata to.
     * @return  collection  The array of metadata
     */
    public static function get_metadata(\core_privacy\local\metadata\collection $collection):
    \core_privacy\local\metadata\collection {

        // Add all of the relevant tables and fields to the collection.
        $collection->add_database_table('eliscore_etl_useractivity', [
            'userid' => 'privacy:metadata:eliscore_etl:userid',
            'courseid' => 'privacy:metadata:eliscore_etl:courseid',
            'hour' => 'privacy:metadata:eliscore_etl:hour',
            'duration' => 'privacy:metadata:eliscore_etl:duration',
        ], 'privacy:metadata:eliscore_etl_useractivity');

        $collection->add_database_table('eliscore_etl_modactivity', [
            'userid' => 'privacy:metadata:eliscore_etl:userid',
            'courseid' => 'privacy:metadata:eliscore_etl:courseid',
            'cmid' => 'privacy:metadata:eliscore_etl:cmid',
            'hour' => 'privacy:metadata:eliscore_etl:hour',
            'duration' => 'privacy:metadata:eliscore_etl:duration',
        ], 'privacy:metadata:eliscore_etl_modactivity');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int $userid The user to search.
     * @return  contextlist   $contextlist  The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        global $DB;

        $contextlist = new \core_privacy\local\request\contextlist();

        // If the user exists in any of the ELIS core tables, add the user context and return it.
        if (self::user_has_etl_data($userid)) {
            $contextlist->add_user_context($userid);
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param \core_privacy\local\request\userlist $userlist The userlist containing the list of users who have data in this
     * context/plugin combination.
     */
    public static function get_users_in_context(\core_privacy\local\request\userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_user) {
            return;
        }

        // If the user exists in any of the ELIS core tables, add the user context and return it.
        if (self::user_has_etl_data($context->instanceid)) {
            $userlist->add_user($context->instanceid);
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts, using the supplied exporter instance.
     *
     * @param   approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        // Export ELIS core data.
        $data = new \stdClass();
        $data->useractivity = [];
        $data->modactivity = [];
        $user = $contextlist->get_user();
        $context = \context_user::instance($user->id);

        $sql = 'SELECT eu.*, c.fullname ' .
            'FROM {eliscore_etl_useractivity} eu ' .
            'INNER JOIN {course} c ON eu.courseid = c.id ' .
            'WHERE eu.userid = :userid';
        $params = ['userid' => $user->id];
        $userrecords = $DB->get_record_sql($sql, $params);

        foreach ($userrecords as $userrecord) {
            $data->useractivity[] = [
                'course' => $userrecord->fullname,
                'hour' => $userrecord->hour,
                'duration' => $userrecord->duration,
            ];
        }

        $sql = 'SELECT em.*, c.fullname, cm.instance, m.name as module ' .
            'FROM {eliscore_etl_modactivity} em ' .
            'INNER JOIN {course} c ON eu.courseid = c.id ' .
            'INNER JOIN {course_modules} cm ON eu.cmid = cm.id ' .
            'INNER JOIN {modules} m ON cm.module = m.id ' .
            'WHERE eu.userid = :userid';
        $params = ['userid' => $user->id];
        $modrecords = $DB->get_record_sql($sql, $params);

        foreach ($modrecords as $modrecord) {
            $modname = $DB->get_field($modrecord->module, 'name', ['id' => $modrecord->instance]);
            $data->modactivity[] = [
                'course' => $modrecord->fullname,
                'module' => $modrecord->module . ': ' . $modname,
                'hour' => $modrecord->hour,
                'duration' => $modrecord->duration,
            ];
        }

        \core_privacy\local\request\writer::with_context($context)->export_data([
            get_string('privacy:metadata:eliscore_etl', 'eliscore_etl')
        ], $data);
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param context $context Context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context->contextlevel == CONTEXT_USER) {
            // Because we only use user contexts the instance ID is the user ID.
            self::delete_user_data($context->instanceid);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $contextlist) {
        if (empty($contextlist->count())) {
            return;
        }

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER) {
                // Because we only use user contexts the instance ID is the user ID.
                self::delete_user_data($context->instanceid);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param \core_privacy\local\request\approved_userlist $userlist The approved context and user information to delete
     * information for.
     */
    public static function delete_data_for_users(\core_privacy\local\request\approved_userlist $userlist) {
        $context = $userlist->get_context();
        // Because we only use user contexts the instance ID is the user ID.
        if ($context instanceof \context_user) {
            self::delete_user_data($context->instanceid);
        }
    }

    /**
     * Return true if the specified userid has data in the ELIS core tables.
     *
     * @param int $userid The user to check for.
     * @return boolean
     */
    private static function user_has_etl_data(int $userid) {
        global $DB;

        $hasdata = false;
        // If the user exists in any of the ELIS etl tables, return true.
        if ($DB->record_exists('eliscore_etl_useractivity', ['userid' => $userid]) ||
            $DB->record_exists('eliscore_etl_modactivity', ['userid' => $userid])) {
            $hasdata = true;
        }

        return $hasdata;
    }

    /**
     * Delete all plugin data for the specified user id.
     *
     * @param int $userid The Moodle user id to delete data for.
     */
    private static function delete_user_data($userid) {
        global $DB;

        $DB->delete_records('eliscore_etl_modactivity', ['userid' => $userid]);
        $DB->delete_records('eliscore_etl_useractivity', ['userid' => $userid]);
    }
}