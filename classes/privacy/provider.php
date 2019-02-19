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
        global $DB;

        // Need this constant, and there seems to be no way to ensure its available. This is a potential problem area.
        if (!defined('CONTEXT_ELIS_USER')) {
            define('CONTEXT_ELIS_USER', 15);
        }

        $contextlist = new \core_privacy\local\request\contextlist();

        // If the user exists in any of the ELIS core tables, add the user context and return it.
        if ($DB->record_exists('local_eliscore_wkflow_inst', ['userid' => $userid])) {
            $contextlist->add_user_context($userid);
        } else {
            $tables = ['local_eliscore_fld_data_text', 'local_eliscore_fld_data_int',
                'local_eliscore_fld_data_num', 'local_eliscore_fld_data_char'];
            $select = 'SELECT ecfd.id ';
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
                $sql = $select . $from . $conditions;
                if (!empty($DB->get_field_sql($sql, $params))) {
                    $contextlist->add_user_context($userid);
                    break;
                }
            }
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
        if (!$context instanceof \context_module) {
            return;
        }

        $params = ['modulename' => 'kronossandvm', 'instanceid' => $context->instanceid];

        // Kronossandvm user requests.
        $sql = "SELECT kr.userid
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
              JOIN {kronossandvm} k ON k.id = cm.instance
              JOIN {kronossandvm_requests} kr ON kr.vmid = k.id
             WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);
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

        $user = $contextlist->get_user();

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        // This will only work for get_recordset. If get_records is used, a different first field will be needed.
        $sql = "SELECT cm.id AS cmid,
                       kr.*
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {kronossandvm} k ON k.id = cm.instance
            INNER JOIN {kronossandvm_requests} kr ON kr.vmid = k.id
                 WHERE c.id {$contextsql}
                       AND kr.userid = :userid
              ORDER BY cm.id";

        $params = ['modname' => 'kronossandvm', 'contextlevel' => CONTEXT_MODULE, 'userid' => $user->id] + $contextparams;

        // Reference to the activity seen in the last iteration of the loop. By comparing this with the current record, and
        // because we know the results are ordered, we know when we've moved to the requests for a new activity and therefore
        // when we can export the complete data for the last activity.
        $lastcmid = null;
        $requestdata = [];

        $requests = $DB->get_recordset_sql($sql, $params);
        foreach ($requests as $request) {
            // If we've moved to a new activity, then write the last activity data and reinit the activity data array.
            if ($lastcmid != $request->cmid) {
                if (!empty($requestdata)) {
                    $context = \context_module::instance($lastcmid);
                    self::export_request_data_for_user($requestdata, $context, $user);
                }
                $requestdata = [];
                $lastcmid = $request->cmid;
            }
            $requestdata['requests'][] = [
                'requesttime' => \core_privacy\local\request\transform::datetime($request->requesttime),
                'starttime' => \core_privacy\local\request\transform::datetime($request->starttime),
                'endtime' => \core_privacy\local\request\transform::datetime($request->endtime),
                'instanceid' => $request->instanceid,
                'instanceip' => $request->instanceip,
                'isscript' => $request->isscript,
                'username' => $request->username,
                'password' => $request->password,
                'isactive' => $request->isactive,
            ];
        }
        $requests->close();

        // The data for the last activity won't have been written yet, so make sure to write it now!
        if (!empty($requestdata)) {
            $context = \context_module::instance($lastcmid);
            self::export_request_data_for_user($requestdata, $context, $user);
        }
    }

    /**
     * Export the supplied personal data for a single request.
     *
     * @param array $requestdata the personal data to export for the request.
     * @param \context_module $context the context of the request.
     * @param \stdClass $user the user record
     */
    protected static function export_request_data_for_user(array $requestdata, \context_module $context, \stdClass $user) {
        // Fetch the generic module data for the activity.
        $contextdata = \core_privacy\local\request\helper::get_context_data($context, $user);

        // Merge with activity data and write it.
        $contextdata = (object)array_merge((array)$contextdata, $requestdata);
        \core_privacy\local\request\writer::with_context($context)->export_data([], $contextdata);
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param context $context Context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel == CONTEXT_MODULE) {
            // Delete all user request data for the activity context.
            if ($cm = get_coursemodule_from_id('kronossandvm', $context->instanceid)) {
                $DB->delete_records('kronossandvm_requests', ['vmid' => $cm->instance]);
            }
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $cm = get_coursemodule_from_id('kronossandvm', $context->instanceid);
                $params = ['userid' => $userid, 'vmid' => $cm->instance];
                $DB->delete_records('kronossandvm_requests', $params);
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
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        if ($cm = get_coursemodule_from_id('kronossandvm', $context->instanceid)) {
            $userids = $userlist->get_userids();
            list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $select = "vmid = :vmid AND userid $usersql";
            $params = ['vmid' => $cm->instance] + $userparams;
            $DB->delete_records_select('kronossandvm_requests', $select, $params);
        }
    }
}