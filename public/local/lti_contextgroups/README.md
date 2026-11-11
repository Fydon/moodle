# LTI context groups

This Moodle 5.2 local plugin assigns newly LTI-enrolled users to a course group
named after the LTI Advantage context label. For example, a launch with the
context label `MESH_B01_202120` creates or reuses a group of that name.

Install the plugin in `local/lti_contextgroups`. It observes course enrolment
events and reads the matching context label from the enrol_lti launch session
cache. Moodle's enrol_lti plugin does not persist the context label. Labels
longer than Moodle's 100-character group-name limit are truncated.
