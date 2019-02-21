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
 * ELIS core privacy API.
 *
 * @package    local_eliscore
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 Remote Learner.net Inc http://www.remote-learner.net
 */

namespace local_eliscore\privacy;

defined('MOODLE_INTERNAL') || die();

// Need this constant, and there seems to be no way to ensure it's available. This is a potential problem area.
if (!defined('CONTEXT_ELIS_USER')) {
    define('CONTEXT_ELIS_USER', 15);
}

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
        $collection->add_database_table('local_eliscore_wkflow_inst', [
            'userid' => 'privacy:metadata:local_eliscore_wkflow_inst:userid',
            'type' => 'privacy:metadata:local_eliscore_wkflow_inst:type',
            'subtype' => 'privacy:metadata:local_eliscore_wkflow_inst:subtype',
            'data' => 'privacy:metadata:local_eliscore_wkflow_inst:data',
            'timemodified' => 'privacy:metadata:local_eliscore_wkflow_inst:timemodified',
        ], 'privacy:metadata:local_eliscore_wkflow_inst');

        $collection->add_database_table('local_eliscore_fld_data_text', [
            'contextid' => 'privacy:metadata:local_eliscore_fld_data_text:contextid',
            'fieldid' => 'privacy:metadata:local_eliscore_fld_data_text:fieldid',
            'data' => 'privacy:metadata:local_eliscore_fld_data_text:data',
        ], 'privacy:metadata:local_eliscore_fld_data_text');

        $collection->add_database_table('local_eliscore_fld_data_int', [
            'contextid' => 'privacy:metadata:local_eliscore_fld_data_int:contextid',
            'fieldid' => 'privacy:metadata:local_eliscore_fld_data_int:fieldid',
            'data' => 'privacy:metadata:local_eliscore_fld_data_int:data',
        ], 'privacy:metadata:local_eliscore_fld_data_int');

        $collection->add_database_table('local_eliscore_fld_data_num', [
            'contextid' => 'privacy:metadata:local_eliscore_fld_data_num:contextid',
            'fieldid' => 'privacy:metadata:local_eliscore_fld_data_num:fieldid',
            'data' => 'privacy:metadata:local_eliscore_fld_data_num:data',
        ], 'privacy:metadata:local_eliscore_fld_data_num');

        $collection->add_database_table('local_eliscore_fld_data_char', [
            'contextid' => 'privacy:metadata:local_eliscore_fld_data_char:contextid',
            'fieldid' => 'privacy:metadata:local_eliscore_fld_data_char:fieldid',
            'data' => 'privacy:metadata:local_eliscore_fld_data_char:data',
        ], 'privacy:metadata:local_eliscore_fld_data_char');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int $userid The user to search.
     * @return  contextlist   $contextlist  The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();

        // If the user exists in any of the ELIS core tables, add the user context and return it.
        if (self::user_has_eliscore_data($userid)) {
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
        if (self::user_has_eliscore_data($context->instanceid)) {
            $userlist->add_user($context->instanceid);
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts, using the supplied exporter instance.
     *
     * @param   approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contextlist) {
        if (empty($contextlist->count())) {
            return;
        }

        // Export ELIS core data.
        $data = new \stdClass();
        $data->workflows = [];
        $data->elisfields = [];
        $user = $contextlist->get_user();
        $context = \context_user::instance($user->id);

        $workflowdata = self::user_workflow_data($user->id);
        foreach ($workflowdata as $workflow) {
            $data->workflows[] = [
                'type' => $workflow->type,
                'subtype' => $workflow->subtype,
                'data' => $workflow->data,
                'timemodified' => \core_privacy\local\request\transform::datetime($workflow->timemodified),
            ];
        }

        $elisfieldsdata = self::user_field_data($user->id);
        foreach ($elisfieldsdata as $elisfield) {
            $data->elisfields[] = [
                'name' => $elisfield->name,
                'data' => $elisfield->data,
            ];
        }

        \core_privacy\local\request\writer::with_context($context)->export_data([
            get_string('privacy:metadata:local_eliscore', 'local_eliscore')
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
    private static function user_has_eliscore_data(int $userid) {
        global $DB;

        $hasdata = false;
        if (!empty(self::user_workflow_data($userid))) {
            $hasdata = true;
        } else if (!empty(self::user_field_data($userid))) {
            $hasdata = true;
        }

        return $hasdata;
    }

    /**
     * Return the workflow record for the specified user.
     *
     * @param int $userid The user to check for.
     * @return array
     */
    private static function user_workflow_data(int $userid) {
        global $DB;

        return $DB->get_records('local_eliscore_wkflow_inst', ['userid' => $userid]);
    }

    /**
     * Return the ELIS core field records for the specified user.
     *
     * @param int $userid The user to check for.
     * @return array The data or empty record.
     */
    private static function user_field_data(int $userid) {
        global $DB;

        $data = [];
        $tables = ['local_eliscore_fld_data_text', 'local_eliscore_fld_data_int',
            'local_eliscore_fld_data_num', 'local_eliscore_fld_data_char'];
        $select = 'SELECT ecfd.id, ecf.id as fieldid, ecf.name, ecfd.data, ';
        $conditions = 'INNER JOIN {local_eliscore_field} ecf ON ecfd.fieldid = ecf.id ' .
            'INNER JOIN {local_eliscore_fld_cat_ctx} ecfc ON ecf.categoryid = ecfc.categoryid AND ' .
            'ecfc.contextlevel = :usercontext1 ' .
            'INNER JOIN {user} u ON u.id = :userid ' .
            'INNER JOIN {local_elisprogram_usr} epu ON u.idnumber = epu.idnumber ' .
            'INNER JOIN {context} c ON epu.id = c.instanceid AND c.contextlevel = :usercontext2 ' .
            'WHERE c.id = ecfd.contextid';
        $params = ['userid' => $userid, 'usercontext1' => CONTEXT_ELIS_USER, 'usercontext2' => CONTEXT_ELIS_USER];

        foreach ($tables as $table) {
            $from = 'FROM {' . $table . '} ecfd ';
            $sql = $select . '\'' . $table . '\' as tablename '. $from . $conditions;
            if (!empty($records = $DB->get_records_sql($sql, $params))) {
                $data = array_merge($data, $records);
            }
        }

        return $data;
    }

    /**
     * Delete all plugin data for the specified user id.
     *
     * @param int $userid The Moodle user id to delete data for.
     */
    private static function delete_user_data($userid) {
        global $DB;

        $DB->delete_records('local_eliscore_wkflow_inst', ['userid' => $userid]);

        $recordsinfo = self::user_field_data($userid);
        foreach ($recordsinfo as $recordinfo) {
            $DB->delete_records($recordinfo->tablename, ['id' => $recordinfo->id]);
        }
    }
}