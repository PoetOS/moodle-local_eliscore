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
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Check that a user context is returned if there is any user data for this user.
     */
    public function test_get_contexts_for_userid() {
        global $DB;

        // Need this constant, and there seems to be no way to ensure its available. This is a potential problem area.
        if (!defined('CONTEXT_ELIS_USER')) {
            define('CONTEXT_ELIS_USER', 15);
        }

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        // Trigger the user created event so ELIS will create its user records.
        \core\event\user_created::create_from_userid($user1->id)->trigger();
        \core\event\user_created::create_from_userid($user2->id)->trigger();
        // The events will have updated the user records. Reload them.
        $user1 = $DB->get_record('user', ['id' => $user1->id]);
        $user2 = $DB->get_record('user', ['id' => $user2->id]);

        $this->assertEmpty(provider::get_contexts_for_userid($user1->id));
        $this->assertEmpty(provider::get_contexts_for_userid($user2->id));

        // Create a workflow instance.
        $record = (object)['type' => 'Type text', 'subtype' => 'Subtype text.', 'userid' => $user1->id,
            'data' => 'This is the data.', 'timemodified' => time()];
        $DB->insert_record('local_eliscore_wkflow_inst', $record);

        $contextlist = provider::get_contexts_for_userid($user1->id);
        // Check that we only get back one context.
        $this->assertCount(1, $contextlist);

        // Check that a context is returned and is the expected context.
        $usercontext = \context_user::instance($user1->id);
        $this->assertEquals($usercontext->id, $contextlist->get_contextids()[0]);

        // Create an ELIS user field associated to user2.
        $elisuserid = $DB->get_field('local_elisprogram_usr', 'id', ['idnumber' => $user2->idnumber]);
        $contextid = $DB->get_field('context', 'id', ['contextlevel' => CONTEXT_ELIS_USER, 'instanceid' => $elisuserid]);
        $record = (object)['name' => 'Test category'];
        $categoryid = $DB->insert_record('local_eliscore_field_cats', $record);
        $record = (object)['categoryid' => $categoryid, 'contextlevel' => CONTEXT_ELIS_USER];
        $DB->insert_record('local_eliscore_fld_cat_ctx', $record);
        $record = (object)['shortname' => 'testfield', 'name' => 'Test Field', 'datatype' => 'text', 'categoryid' => $categoryid];
        $fieldid = $DB->insert_record('local_eliscore_field', $record);
        $record = (object)['contextid' => $contextid, 'fieldid' => $fieldid, 'data' => 'Test user data.'];
        $DB->insert_record('local_eliscore_fld_data_text', $record);

        $contextlist = provider::get_contexts_for_userid($user2->id);
        // Check that we only get back one context.
        $this->assertCount(1, $contextlist);

        // Check that a context is returned and is the expected context.
        $usercontext = \context_user::instance($user2->id);
        $this->assertEquals($usercontext->id, $contextlist->get_contextids()[0]);

    }

    /**
     * Test that only users with a user context are fetched.
     */
    public function test_get_users_in_context() {
        $this->resetAfterTest();
return;

        $component = 'auth_kronosportal';
        // Create a user.
        $user = $this->getDataGenerator()->create_user();
        $usercontext = context_user::instance($user->id);

        // The list of users should not return anything yet (related data still haven't been created).
        $userlist = new \core_privacy\local\request\userlist($usercontext, $component);
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);

        // Create a token record.
        self::create_token($user->id);

        // The list of users for user context should return the user.
        provider::get_users_in_context($userlist);
        $this->assertCount(1, $userlist);
        $expected = [$user->id];
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
return;
        // Create a user record.
        $user = $this->getDataGenerator()->create_user();
        $tokenrecord = self::create_token($user->id);

        $usercontext = \context_user::instance($user->id);

        $writer = \core_privacy\local\request\writer::with_context($usercontext);
        $this->assertFalse($writer->has_any_data());
        $approvedlist = new core_privacy\local\request\approved_contextlist($user, 'auth_kronosportal', [$usercontext->id]);
        provider::export_user_data($approvedlist);
        $data = $writer->get_data([]);
        $this->assertEquals($tokenrecord->userid, $data->userid);
        $this->assertEquals($tokenrecord->token, $data->token);
    }

    /**
     * Test deleting all user data for a specific context.
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;
return;

        // Create a user record.
        $user1 = $this->getDataGenerator()->create_user();
        self::create_token($user1->id);
        $user1context = \context_user::instance($user1->id);

        $user2 = $this->getDataGenerator()->create_user();
        self::create_token($user2->id);

        // Get all accounts. There should be two.
        $this->assertCount(2, $DB->get_records('auth_kronosportal_tokens', []));

        // Delete everything for the first user context.
        provider::delete_data_for_all_users_in_context($user1context);

        $this->assertCount(0, $DB->get_records('auth_kronosportal_tokens', ['userid' => $user1->id]));

        // Get all accounts. There should be one.
        $this->assertCount(1, $DB->get_records('auth_kronosportal_tokens', []));
    }

    /**
     * This should work identical to the above test.
     */
    public function test_delete_data_for_user() {
        global $DB;
return;

        // Create a user record.
        $user1 = $this->getDataGenerator()->create_user();
        self::create_token($user1->id);
        $user1context = \context_user::instance($user1->id);

        $user2 = $this->getDataGenerator()->create_user();
        self::create_token($user2->id);

        // Get all accounts. There should be two.
        $this->assertCount(2, $DB->get_records('auth_kronosportal_tokens', []));

        // Delete everything for the first user.
        $approvedlist = new \core_privacy\local\request\approved_contextlist($user1, 'auth_kronosportal', [$user1context->id]);
        provider::delete_data_for_user($approvedlist);

        $this->assertCount(0, $DB->get_records('auth_kronosportal_tokens', ['userid' => $user1->id]));

        // Get all accounts. There should be one.
        $this->assertCount(1, $DB->get_records('auth_kronosportal_tokens', []));
    }

    /**
     * Test that data for users in approved userlist is deleted.
     */
    public function test_delete_data_for_users() {
        $this->resetAfterTest();
return;

        $component = 'auth_kronosportal';
        // Create user1.
        $user1 = $this->getDataGenerator()->create_user();
        $usercontext1 = context_user::instance($user1->id);
        self::create_token($user1->id);

        // Create user2.
        $user2 = $this->getDataGenerator()->create_user();
        $usercontext2 = context_user::instance($user2->id);
        self::create_token($user2->id);

        // The list of users for usercontext1 should return user1.
        $userlist1 = new \core_privacy\local\request\userlist($usercontext1, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(1, $userlist1);
        $expected = [$user1->id];
        $actual = $userlist1->get_userids();
        $this->assertEquals($expected, $actual);

        // The list of users for usercontext2 should return user2.
        $userlist2 = new \core_privacy\local\request\userlist($usercontext2, $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(1, $userlist2);
        $expected = [$user2->id];
        $actual = $userlist2->get_userids();
        $this->assertEquals($expected, $actual);

        // Add userlist1 to the approved user list.
        $approvedlist = new \core_privacy\local\request\approved_userlist($usercontext1, $component, $userlist1->get_userids());

        // Delete user data using delete_data_for_user for usercontext1.
        provider::delete_data_for_users($approvedlist);

        // Re-fetch users in usercontext1 - The user list should now be empty.
        $userlist1 = new \core_privacy\local\request\userlist($usercontext1, $component);
        provider::get_users_in_context($userlist1);
        $this->assertCount(0, $userlist1);
        // Re-fetch users in usercontext2 - The user list should not be empty (user2).
        $userlist2 = new \core_privacy\local\request\userlist($usercontext2, $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(1, $userlist2);

        // User data should be only removed in the user context.
        $systemcontext = context_system::instance();
        // Add userlist2 to the approved user list in the system context.
        $approvedlist = new \core_privacy\local\request\approved_userlist($systemcontext, $component, $userlist2->get_userids());
        // Delete user1 data using delete_data_for_user.
        provider::delete_data_for_users($approvedlist);
        // Re-fetch users in usercontext2 - The user list should not be empty (user2).
        $userlist2 = new \core_privacy\local\request\userlist($usercontext2, $component);
        provider::get_users_in_context($userlist2);
        $this->assertCount(1, $userlist2);
    }
}