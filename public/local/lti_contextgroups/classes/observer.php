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
 * Responds to LTI enrolments by assigning the user to the LTI context group.
 *
 * @package    local_lti_contextgroups
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Add an LTI enrollee to a group named after the context label in the launch.
     *
     * @param user_enrolment_created $event
     */
    public static function user_enrolment_created(user_enrolment_created $event): void {
        global $CFG;

        if ($event->other['enrol'] !== 'lti') {
            return;
        }

        $groupname = self::get_context_label($event->relateduserid, $event->courseid);
        if ($groupname === null) {
            return;
        }

        require_once($CFG->dirroot . '/group/lib.php');
        $groupid = groups_get_group_by_name($event->courseid, $groupname);
        if (!$groupid) {
            $groupid = groups_create_group((object) [
                'courseid' => $event->courseid,
                'name' => $groupname,
                'description' => '',
                'descriptionformat' => FORMAT_MOODLE,
            ]);
        }

        if (!groups_add_member($groupid, $event->relateduserid)) {
            throw new \moodle_exception('groupmembershipfailed', 'local_lti_contextgroups');
        }
    }

    /**
     * Find the context label for the launch that created this enrolment.
     *
     * The enrol_lti plugin caches launch claims in the user's session, but does not persist context labels.
     *
     * @param int $userid Moodle user id.
     * @param int $courseid course id.
     * @return string|null trimmed label, or null if no matching LTI Advantage launch is available.
     */
    private static function get_context_label(int $userid, int $courseid): ?string {
        global $DB, $SESSION;

        if (empty($SESSION->enrol_lti_launch)) {
            return null;
        }

        $launchcache = new launch_cache_session();
        // A session can contain several launches; check the newest cached launches first.
        $launchids = array_reverse(array_keys($SESSION->enrol_lti_launch));
        foreach ($launchids as $launchid) {
            $launchdata = $launchcache->getLaunchData($launchid);
            if (!is_array($launchdata)) {
                continue;
            }

            $context = $launchdata['https://purl.imsglobal.org/spec/lti/claim/context'] ?? null;
            $subject = $launchdata['sub'] ?? null;
            $custom = $launchdata['https://purl.imsglobal.org/spec/lti/claim/custom'] ?? null;
            $label = is_array($context) ? ($context['label'] ?? null) : null;
            $resourceuuid = is_array($custom) ? ($custom['id'] ?? null) : null;
            if (!is_string($label) || !is_string($subject) || !is_string($resourceuuid)) {
                continue;
            }
            $label = trim($label);
            if ($label === '' || $subject === '' || $resourceuuid === '') {
                continue;
            }

            $sql = "SELECT lu.id
                      FROM {enrol_lti_users} lu
                      JOIN {enrol_lti_tools} t ON t.id = lu.toolid
                      JOIN {enrol} e ON e.id = t.enrolid
                     WHERE lu.userid = :userid
                       AND lu.sourceid = :sourceid
                       AND t.uuid = :resourceuuid
                       AND e.courseid = :courseid
                       AND e.enrol = :enrol";
            $params = [
                'userid' => $userid,
                'sourceid' => $subject,
                'resourceuuid' => $resourceuuid,
                'courseid' => $courseid,
                'enrol' => 'lti',
            ];
            if ($DB->record_exists_sql($sql, $params)) {
                return \core_text::substr($label, 0, 100);
            }
        }

        return null;
    }
}
