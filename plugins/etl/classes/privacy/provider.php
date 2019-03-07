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

    // This plugin has elicore subplugin user data.
    \local_eliscore\privacy\eliscore_provider {

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
     * Indicates whether or not the subplugin has any user data for the provided user.
     *
     * @param int $userid The user ID to get context IDs for.
     * @return boolean Indicating data presence.
     * @param \core_privacy\local\request\contextlist $contextlist Use add_from_sql with this object to add your context IDs.
     */
    public static function user_has_data(int $userid){
        global $DB;

        return $DB->record_exists('eliscore_etl_modactivity', ['userid' => $userid]) ||
            $DB->record_exists('eliscore_etl_useractivity', ['userid' => $userid]);
    }

    /**
     * Return all user data for the specified user.
     *
     * @param int $userid The id of the user the data is for.
     * @return array An array of arrays of each data collection for this user and subplugin.
     */
    public static function add_user_data(int $userid) {
        global $DB;

        $data = [];
        $sql = 'SELECT eu.*, c.fullname ' .
            'FROM {eliscore_etl_useractivity} eu ' .
            'INNER JOIN {course} c ON eu.courseid = c.id ' .
            'WHERE eu.userid = :userid';
        $params = ['userid' => $userid];
        $userrecords = $DB->get_records_sql($sql, $params);

        foreach ($userrecords as $userrecord) {
            $data['useractivity'][] = [
                'course' => $userrecord->fullname,
                'hour' => $userrecord->hour,
                'duration' => $userrecord->duration,
            ];
        }

        $sql = 'SELECT em.*, c.fullname, cm.instance, m.name as module ' .
            'FROM {eliscore_etl_modactivity} em ' .
            'INNER JOIN {course} c ON em.courseid = c.id ' .
            'INNER JOIN {course_modules} cm ON em.cmid = cm.id ' .
            'INNER JOIN {modules} m ON cm.module = m.id ' .
            'WHERE em.userid = :userid';
        $params = ['userid' => $userid];
        $modrecords = $DB->get_records_sql($sql, $params);

        foreach ($modrecords as $modrecord) {
            $modname = $DB->get_field($modrecord->module, 'name', ['id' => $modrecord->instance]);
            $data['modactivity'][] = [
                'course' => $modrecord->fullname,
                'module' => $modrecord->module . ': ' . $modname,
                'hour' => $modrecord->hour,
                'duration' => $modrecord->duration,
            ];
        }

        return $data;
    }

    /**
     * Delete all subplugin data for the specified user id.
     *
     * @param int $userid The Moodle user id to delete data for.
     */
    public static function delete_user_data($userid) {
        global $DB;

        $DB->delete_records('eliscore_etl_modactivity', ['userid' => $userid]);
        $DB->delete_records('eliscore_etl_useractivity', ['userid' => $userid]);
    }
}