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

namespace local_lti_contextgroups;

use core\event\user_enrolment_created;
use enrol_lti\local\ltiadvantage\lib\launch_cache_session;

/**
 * Tests for LTI context group assignment.
 *
 * @package    local_lti_contextgroups
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer_test extends \advanced_testcase {

    /**
     * A new LTI enrolment creates the context group and adds the user to it.
     */
    public function test_user_enrolment_created_adds_user_to_context_group(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $enrolid = $DB->insert_record('enrol', (object) [
            'enrol' => 'lti',
            'status' => ENROL_INSTANCE_ENABLED,
            'courseid' => $course->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $resourceuuid = '14377000-0000-0000-0000-000000000001';
        $toolid = $DB->insert_record('enrol_lti_tools', (object) [
            'enrolid' => $enrolid,
            'contextid' => \context_course::instance($course->id)->id,
            'institution' => '',
            'city' => '',
            'country' => 'US',
            'roleinstructor' => 0,
            'rolelearner' => 0,
            'uuid' => $resourceuuid,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('enrol_lti_users', (object) [
            'userid' => $user->id,
            'toolid' => $toolid,
            'sourceid' => 'platform-user-1',
        ]);

        $launchcache = new launch_cache_session();
        $launchcache->cacheLaunchData('test-launch', [
            'sub' => 'platform-user-1',
            'https://purl.imsglobal.org/spec/lti/claim/custom' => ['id' => $resourceuuid],
            'https://purl.imsglobal.org/spec/lti/claim/context' => ['label' => 'MESH_B01_202120'],
        ]);

        $event = user_enrolment_created::create([
            'objectid' => 1,
            'courseid' => $course->id,
            'context' => \context_course::instance($course->id),
            'relateduserid' => $user->id,
            'other' => ['enrol' => 'lti'],
        ]);

        observer::user_enrolment_created($event);
        observer::user_enrolment_created($event);

        $groupid = groups_get_group_by_name($course->id, 'MESH_B01_202120');
        $this->assertNotEmpty($groupid);
        $this->assertTrue(groups_is_member($groupid, $user->id));
        $this->assertEquals(1, $DB->count_records('groups', [
            'courseid' => $course->id,
            'name' => 'MESH_B01_202120',
        ]));
    }

    /**
     * A launch without a context label does not create a group.
     */
    public function test_user_enrolment_created_without_context_label_does_nothing(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $launchcache = new launch_cache_session();
        $launchcache->cacheLaunchData('test-launch', [
            'sub' => 'platform-user-1',
            'https://purl.imsglobal.org/spec/lti/claim/custom' => ['id' => 'resource-uuid'],
        ]);
        $event = user_enrolment_created::create([
            'objectid' => 1,
            'courseid' => $course->id,
            'context' => \context_course::instance($course->id),
            'relateduserid' => $user->id,
            'other' => ['enrol' => 'lti'],
        ]);

        observer::user_enrolment_created($event);

        $this->assertEquals(0, $DB->count_records('groups', ['courseid' => $course->id]));
    }
}
