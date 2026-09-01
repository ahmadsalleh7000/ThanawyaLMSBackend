# Instructor scope decision

Decision date: 2026-08-11

Instructors remain admin-managed for the current LMS scope. The platform models instructors as verified public profiles attached to courses, not as authenticated operational users. Course authoring, assessment management, media publishing, analytics, and instructor-profile changes already belong to permissioned Laravel roles (`content_editor` and `instructor_manager`) with audit logging.

An instructor-specific portal would duplicate these controls, add a new authorization boundary, and create an unsupported self-service workflow without a current business requirement. It should only be introduced when instructors need direct accounts and independently own workflows such as drafting assigned courses, grading submissions, answering learner discussions, or viewing only their own analytics. At that point, add an explicit instructor user role, link it to `instructor_profiles`, define tenant-safe ownership policies, and build a separately tested portal.
