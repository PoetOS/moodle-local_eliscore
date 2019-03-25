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
 * Privacy test for the ELIS core local plugin.
 *
 * @package    local_eliscore
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2019 Remote Learner.net Inc http://www.remote-learner.net
 */

defined('MOODLE_INTERNAL') || die();

use \local_eliscore\privacy\provider;

/**
 * Privacy test for the ELIS core local plugin.
 *
 * @package    local_eliscore
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2019 Remote Learner.net Inc http://www.remote-learner.net
 * @group local_eliscore
 */
class local_eliscore_privacy_testcase extends \core_privacy\tests\provider_testcase {
    /**
     * Tests set up.
     */
    public function setUp() {
        // Need this constant, and there seems to be no way to ensure its available. This is a potential problem area.
        if (!defined('CONTEXT_ELIS_USER')) {
            define('CONTEXT_ELIS_USER', 15);
        }

        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Check that a user context is returned if there is any user data for this user.
     */
    public function test_get_contexts_for_userid() {
        $this->resetAfterTest();

        $user1 = self::create_elis_user($this->getDataGenerator());
        $user2 = self::create_elis_user($this->getDataGenerator());
        $user3 = self::create_elis_user($this->getDataGenerator());

        $this->assertEmpty(provider::get_contexts_for_userid($user1->id));
        $this->assertEmpty(provider::get_contexts_for_userid($user2->id));
        $this->assertEmpty(provider::get_contexts_for_userid($user3->id));

        // Create a workflow instance.
        self::create_workflow_instance($user1->id);

        $contextlist = provider::get_contexts_for_userid($user1->id);
        // Check that we only get back one context.
        $this->assertCount(1, $contextlist);

        // Check that a context is returned and is the expected context.
        $usercontext = \context_user::instance($user1->id);
        $this->assertEquals($usercontext->id, $contextlist->get_contextids()[0]);

        // Create an ELIS user field associated to user1.
        self::create_elis_user_field($user1->idnumber);

        // Vaerify that user2 still have no context data.
        $this->assertEmpty(provider::get_contexts_for_userid($user2->id));

        // Create some ELIS user field associated to user2.
        self::create_elis_user_field($user2->idnumber);
        self::create_elis_user_field($user2->idnumber);
        self::create_elis_user_field($user2->idnumber);

        $contextlist = provider::get_contexts_for_userid($user2->id);
        // Check that we only get back one context.
        $this->assertCount(1, $contextlist);

        // Check that a context is returned and is the expected context.
        $usercontext = \context_user::instance($user2->id);
        $this->assertEquals($usercontext->id, $contextlist->get_contextids()[0]);

        // Create some ETL data for user3.
        self::create_etl_data($user3->id);
        $contextlist = provider::get_contexts_for_userid($user3->id);
        // Check that we only get back one context.
        $this->assertCount(1, $contextlist);

        // Check that a context is returned and is the expected context.
        $usercontext = \context_user::instance($user3->id);
        $this->assertEquals($usercontext->id, $contextlist->get_contextids()[0]);
    }

    /**
     * Test that only users with a user context are fetched.
     */
    public function test_get_users_in_context() {
        $this->resetAfterTest();

        $component = 'local_eliscore';
        // Create some users.
        $user1 = self::create_elis_user($this->getDataGenerator());
        $user2 = self::create_elis_user($this->getDataGenerator());
        $usercontext = context_user::instance($user1->id);

        // The list of users should not return anything yet (related data still haven't been created).
        $userlist = new \core_privacy\local\request\userlist($usercontext, $component);
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);

        // Create a workflow record.
        self::create_workflow_instance($user1->id);
        // Create a user field instance.
        self::create_elis_user_field($user1->idnumber);
        self::create_elis_user_field($user2->idnumber);

        // The list of users for user context should return the user.
        provider::get_users_in_context($userlist);
        $this->assertCount(1, $userlist);
        $expected = [$user1->id];
        $actual = $userlist->get_userids();
        $this->assertEquals($expected, $actual);

        // The list of users for system context should not return any users.
        $userlist = new \core_privacy\local\request\userlist(context_system::instance(), $component);
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);
    }

    /**
     * Test that user data is exported correctly.
     */
    public function test_export_user_data() {
        global $DB;

        $this->resetAfterTest();

        // Create a user record.
        $user1 = self::create_elis_user($this->getDataGenerator());
        $user2 = self::create_elis_user($this->getDataGenerator());
        // Create workflow records.
        $wid = self::create_workflow_instance($user1->id);
        self::create_workflow_instance($user2->id);
        $workflow = $DB->get_record('local_eliscore_wkflow_inst', ['id' => $wid]);
        // Create user field instances.
        $efinfo = self::create_elis_user_field($user1->idnumber);
        self::create_elis_user_field($user1->idnumber);
        self::create_elis_user_field($user2->idnumber);
        $elisfielddata = $DB->get_record($efinfo->table, ['id' => $efinfo->id]);
        $elisfield = $DB->get_record('local_eliscore_field', ['id' => $elisfielddata->fieldid]);
        // Create some ETL data for user1.
        self::create_etl_data($user1->id);

        $usercontext = \context_user::instance($user1->id);

        $writer = \core_privacy\local\request\writer::with_context($usercontext);
        $this->assertFalse($writer->has_any_data());
        $approvedlist = new core_privacy\local\request\approved_contextlist($user1, 'local_eliscore', [$usercontext->id]);
        provider::export_user_data($approvedlist);
        $data = $writer->get_data([get_string('privacy:metadata:local_eliscore', 'local_eliscore')]);
        $this->assertEquals($workflow->type, $data->workflows[0]['type']);
        $this->assertEquals($workflow->data, $data->workflows[0]['data']);
        $this->assertCount(2, $data->elisdatafields);
        $this->assertEquals($elisfield->name, $data->elisdatafields[0]['name']);
        $this->assertEquals($elisfielddata->data, $data->elisdatafields[0]['data']);
        $this->assertCount(2, $data->eliscore['etl']);
        $this->assertCount(1, $data->eliscore['etl']['useractivity']);
        $this->assertCount(1, $data->eliscore['etl']['modactivity']);
        $this->assertNotEmpty($data->eliscore['etl']['useractivity'][0]['course']);
        $this->assertNotEmpty($data->eliscore['etl']['useractivity'][0]['hour']);
        $this->assertContains('assign', $data->eliscore['etl']['modactivity'][0]['module']);
        $this->assertEquals(17, $data->eliscore['etl']['modactivity'][0]['hour']);
    }

    /**
     * Test deleting all user data for a specific context.
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        $this->resetAfterTest();

        // Create a user record.
        $user1 = self::create_elis_user($this->getDataGenerator());
        $user1context = \context_user::instance($user1->id);
        $user2 = self::create_elis_user($this->getDataGenerator());
        // Create workflow records.
        self::create_workflow_instance($user1->id);
        self::create_workflow_instance($user2->id);
        // Create user field instances.
        $efinfo1 = self::create_elis_user_field($user1->idnumber);
        $efinfo2 = self::create_elis_user_field($user1->idnumber);
        $efinfo3 = self::create_elis_user_field($user2->idnumber);
        // Create some ETL data for user1.
        self::create_etl_data($user1->id);

        // Get the first fieldid for later test.
        $field1id = $DB->get_field($efinfo1->table, 'fieldid', ['id' => $efinfo1->id]);

        // Get all accounts. There should be two.
        $this->assertCount(2, $DB->get_records('local_eliscore_wkflow_inst', []));

        // Delete everything for the first user context.
        provider::delete_data_for_all_users_in_context($user1context);

        // Only the user1 record should be gone.
        $this->assertCount(0, $DB->get_records('local_eliscore_wkflow_inst', ['userid' => $user1->id]));
        $this->assertCount(1, $DB->get_records('local_eliscore_wkflow_inst', []));
        $this->assertCount(0, $DB->get_records($efinfo1->table, ['id' => $efinfo1->id]));
        $this->assertCount(0, $DB->get_records($efinfo2->table, ['id' => $efinfo2->id]));
        $this->assertCount(1, $DB->get_records($efinfo3->table, ['id' => $efinfo3->id]));
        $this->assertCount(0, $DB->get_records('eliscore_etl_modactivity', ['userid' => $user1->id]));
        $this->assertCount(0, $DB->get_records('eliscore_etl_useractivity', ['userid' => $user1->id]));

        // The field itself should still exist.
        $this->assertCount(1, $DB->get_records('local_eliscore_field', ['id' => $field1id]));
    }

    /**
     * This should work identical to the above test.
     */
    public function test_delete_data_for_user() {
        global $DB;

        $this->resetAfterTest();

        // Create a user record.
        $user1 = self::create_elis_user($this->getDataGenerator());
        $user1context = \context_user::instance($user1->id);
        self::create_workflow_instance($user1->id);
        $efinfo1 = self::create_elis_user_field($user1->idnumber);
        $efinfo2 = self::create_elis_user_field($user1->idnumber);
        // Get the first fieldid for later test.
        $field1id = $DB->get_field($efinfo1->table, 'fieldid', ['id' => $efinfo1->id]);

        $user2 = self::create_elis_user($this->getDataGenerator());
        self::create_workflow_instance($user2->id);
        $efinfo3 = self::create_elis_user_field($user2->idnumber);

        // Get all accounts. There should be two.
        $this->assertCount(2, $DB->get_records('local_eliscore_wkflow_inst', []));

        // Delete everything for the first user.
        $approvedlist = new \core_privacy\local\request\approved_contextlist($user1, 'local_eliscore', [$user1context->id]);
        provider::delete_data_for_user($approvedlist);

        // Only the user1 record should be gone.
        $this->assertCount(0, $DB->get_records('local_eliscore_wkflow_inst', ['userid' => $user1->id]));
        $this->assertCount(1, $DB->get_records('local_eliscore_wkflow_inst', []));
        $this->assertCount(0, $DB->get_records($efinfo1->table, ['id' => $efinfo1->id]));
        $this->assertCount(0, $DB->get_records($efinfo2->table, ['id' => $efinfo2->id]));
        $this->assertCount(1, $DB->get_records($efinfo3->table, ['id' => $efinfo3->id]));

        // The field itself should still exist.
        $this->assertCount(1, $DB->get_records('local_eliscore_field', ['id' => $field1id]));
    }

    /**
     * Test that data for users in approved userlist is deleted.
     */
    public function test_delete_data_for_users() {
        global $DB;

        $this->resetAfterTest();

        $component = 'local_eliscore';

        // Create a user record.
        $user1 = self::create_elis_user($this->getDataGenerator());
        $user1context = \context_user::instance($user1->id);
        self::create_workflow_instance($user1->id);
        $efinfo1 = self::create_elis_user_field($user1->idnumber);
        $efinfo2 = self::create_elis_user_field($user1->idnumber);
        // Get the first fieldid for later test.
        $field1id = $DB->get_field($efinfo1->table, 'fieldid', ['id' => $efinfo1->id]);

        $user2 = self::create_elis_user($this->getDataGenerator());
        $user2context = \context_user::instance($user2->id);
        self::create_workflow_instance($user2->id);
        $efinfo3 = self::create_elis_user_field($user2->idnumber);

        // The list of users for usercontext1 should return user1.
        $userlist1 = new \core_privacy\local\request\userlist($user1context, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(1, $userlist1);
        $expected = [$user1->id];
        $actual = $userlist1->get_userids();
        $this->assertEquals($expected, $actual);

        // The list of users for usercontext2 should return user2.
        $userlist2 = new \core_privacy\local\request\userlist($user2context, $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(1, $userlist2);
        $expected = [$user2->id];
        $actual = $userlist2->get_userids();
        $this->assertEquals($expected, $actual);

        // Add userlist1 to the approved user list.
        $approvedlist = new \core_privacy\local\request\approved_userlist($user1context, $component, $userlist1->get_userids());

        // Delete user data using delete_data_for_user for usercontext1.
        provider::delete_data_for_users($approvedlist);

        // Re-fetch users in usercontext1 - The user list should now be empty.
        $userlist1 = new \core_privacy\local\request\userlist($user1context, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(0, $userlist1);
        // Re-fetch users in usercontext2 - The user list should not be empty (user2).
        $userlist2 = new \core_privacy\local\request\userlist($user2context, $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(1, $userlist2);

        // User data should be only removed in the user context.
        $systemcontext = context_system::instance();
        // Add userlist2 to the approved user list in the system context.
        $approvedlist = new \core_privacy\local\request\approved_userlist($systemcontext, $component, $userlist2->get_userids());
        // Delete user1 data using delete_data_for_user.
        provider::delete_data_for_users($approvedlist);
        // Re-fetch users in usercontext2 - The user list should not be empty (user2).
        $userlist2 = new \core_privacy\local\request\userlist($user2context, $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(1, $userlist2);
    }

    /**
     * Generate a Moodle and ELIS user and return it.
     *
     * @param testing_data_generator $generator
     * @return stdClass
     */
    private static function create_elis_user(testing_data_generator $generator ) {
        global $DB;

        $user = $generator->create_user();
        // Trigger the user created event so ELIS will create its user records.
        \core\event\user_created::create_from_userid($user->id)->trigger();
        // The events will have updated the user records. Reload them.
        return $DB->get_record('user', ['id' => $user->id]);
    }

    /**
     * Create a user workflow instance for testing.
     *
     * @param int $userid Data id of the user record.
     * @return int Data id of the created record.
     */
    private static function create_workflow_instance($userid) {
        global $DB;

        // Create a workflow instance.
        $record = (object)['type' => 'Type text', 'subtype' => 'Subtype text.', 'userid' => $userid,
            'data' => 'This is the data.', 'timemodified' => time()];
        return $DB->insert_record('local_eliscore_wkflow_inst', $record);
    }

    /**
     * Create an ELIS user field for the specified user idnumber in one of four ELIS tables.
     *
     * @param string $useridnumber The data idnumber field of the user record.
     * @return stdClass The table name and the data id of the created field instance.
     */
    private static function create_elis_user_field($useridnumber) {
        global $DB;
        static $num = 0;
        $tables = ['local_eliscore_fld_data_text', 'local_eliscore_fld_data_int',
            'local_eliscore_fld_data_num', 'local_eliscore_fld_data_char'];
        $data = ['Test user data', 999, 888, 'Test data'];
        $table = $tables[$num];
        $datum = $data[$num];
        $datatype = substr($table, strrpos($table, '_') + 1);
        $num = ($num + 1) % 4;

        $return = new stdClass();
        $return->table = $table;

        // Get the data id of the ELIS user field associated with the specified idnumber.
        $elisuserid = $DB->get_field('local_elisprogram_usr', 'id', ['idnumber' => $useridnumber]);
        // Get the context data id for the ELIS user data id.
        $contextid = $DB->get_field('context', 'id', ['contextlevel' => CONTEXT_ELIS_USER, 'instanceid' => $elisuserid]);

        // Create a test category for the field.
        $record = (object)['name' => 'Test category'];
        $categoryid = $DB->insert_record('local_eliscore_field_cats', $record);

        // Link the category to the ELIS user context.
        $record = (object)['categoryid' => $categoryid, 'contextlevel' => CONTEXT_ELIS_USER];
        $DB->insert_record('local_eliscore_fld_cat_ctx', $record);

        // Create a field item in the test category.
        $record = (object)['shortname' => 'testfield', 'name' => 'Test Field', 'datatype' => $datatype,
            'categoryid' => $categoryid];
        $fieldid = $DB->insert_record('local_eliscore_field', $record);

        // Create field data for the created field with the specified user context.
        $record = (object)['contextid' => $contextid, 'fieldid' => $fieldid, 'data' => $datum];
        $return->id = $DB->insert_record($table, $record);

        return $return;
    }

    /**
     * Create data for the eliscore / etl subplugin.
     * @param int $userid The Moodle user id to create data for.
     */
    private static function create_etl_data($userid) {
        global $DB;

        $course = self::getDataGenerator()->create_course();
        $assign = self::getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $assigncm = get_coursemodule_from_id('assign', $assign->cmid);

        $DB->insert_record('eliscore_etl_useractivity',
            ['userid' => $userid,
             'courseid' => $course->id,
             'hour' => 4,
             'duration' => 120,
            ]);
        $DB->insert_record('eliscore_etl_modactivity',
            ['userid' => $userid,
             'courseid' => $course->id,
             'cmid' => $assigncm->id,
             'hour' => 17,
             'duration' => 30,
            ]);
    }
}