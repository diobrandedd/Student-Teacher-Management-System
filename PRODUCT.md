# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Primary users are school-office staff acting as registrar: administrators and Teacher/Staff (`staff`) who open SSIS to manage student records, teacher assignments, blocks, courses, departments, and reports.

Students are a secondary audience. They sign in with a username set on their student record (not via User accounts) to view their academic information and edit limited contact fields (email, phone, address).

## Product Purpose

SSIS (Secure Student Information and Access Management System) is a least-privilege student information system. Staff create and maintain academic records; each student signs in as that record and sees only their own data. Success is that the right role can complete records work, and no other role can reach data or actions outside its wall.

## Positioning

A generic records CRUD app could list students. SSIS cannot honestly be copied without the access contract: role-gated routes, generic authentication errors, five-attempt / 15-minute lockout, CSRF on every mutating form, non-destructive deactivation, and an audit log of security and data events. Students never manage other students.

## Operating Context

Staff use a browser against a local or institutional PHP/MySQL host (XAMPP-style `public/` document root). Timezone is Asia/Manila. First-time use is a one-shot administrator setup page that disappears after the first user exists. Issued accounts come from an administrator; there is no self-registration.

Daily staff work: search and edit students (course, year, academic status, and student sign-in username on the same form), maintain teachers and departments, group students into named blocks, assign teachers to subjects within those blocks, export an active-student CSV, and (administrators) manage staff/admin users, settings, and audit logs.

## Capabilities and Constraints

Confirmed functionality:

- Roles: `admin`, `staff` (Teacher/Staff); students sign in via credentials on their student record (not `users.role=student`)
- Student records with course catalog (`course_id`), year level 1–4, academic status Active / Dropped out / Graduated, and auto username `Lastname_F` (spaces stripped) on the student form (temporary password `123`); block membership (and teachers via block subjects) is managed under Blocks, not on the student form
- Teachers directory, one department per teacher, structured teacher profile (first/middle/last name, phone, address) editable by admin from Teachers and by the teacher via My profile; blocks with unique names and one-or-more students; multiple teacher+subject assignments per block (subject code + name); saving an assignment snapshots current members into enrollments (roster edits do not auto-sync); a student may belong to multiple blocks
- Courses and departments: administrators add/rename; staff can view
- Active-student report with CSV export
- Administrator and Teacher/Staff users only on the Users page; settings (`school_name`, `maintenance_notice`), and latest-200 audit log
- Security: password hashing, session rotation, lockout (staff and students), CSRF, prepared statements, output escaping, security headers, CSP `default-src 'self'`
- Issued accounts start with temporary password `123` and must set a strong password with confirmation on first successful sign-in; password fields include a show/hide control; admins cannot set custom passwords — only a reset button back to `123` (forced change on next sign-in); student/teacher usernames are auto-generated, not typed by admin

Constraints future work must preserve:

- Product name remains SSIS / Secure Student IS
- Institution display name is the admin-editable `school_name` setting (schema default: “Secure Student Information System”), not a hardcoded campus
- Security contract above stays intact; login errors stay account-neutral
- Philippine academic conventions already in code: Asia/Manila, year levels 1–4; grades/GPA are not entered on the student form (to be computed from teacher-submitted scores)
- Stack is the incumbent PHP 8.1+ / MySQL (MariaDB) server-rendered app with `public/style.css` and `public/app.js`

Undecided: no named campus, logo, or support contact is committed.

## Brand Commitments

- Product: **SSIS**, UI brand **Secure Student IS**
- Voice in the product is institutional and access-minded (authorized accounts, role badges, explicit empty states)
- School identity is a setting, not a rebrand. Do not replace SSIS with a specific campus name in code or chrome unless an administrator saved `school_name`

## Evidence on Hand

- Product copy and flows live in `public/index.php` and `app/*-view.php`
- Schema and default school name: `database/schema.sql`
- Operator documentation: `README.md`
- No logo, wordmark, or other brand image is in the repository
- No testimonials, press, real student rosters, or support phone/email exist; future work must not fabricate them

## Product Principles

1. Least privilege is the product: every screen and action is for one role’s job, not a shared dump of the database.
2. Staff speed matters more than showcase: search, assign, group, and export must stay completable in the office workflow.
3. Security behavior is user-facing truth: generic failures, lockouts, and auditability are not optional chrome.
4. Institutional identity comes from settings and real records, never invented school branding or proof.
5. Academic fields stay locally conventional (PH grading, year levels) unless the operator changes product rules.
