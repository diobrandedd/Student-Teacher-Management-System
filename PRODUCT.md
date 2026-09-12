# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Primary users are college or university office administrators (registrar and records staff) who manage undergraduate student records, faculty/teachers, blocks, courses, departments, reports, grading weights, users, and settings. Teacher/Staff (`staff`) users have a narrower wall: Assigned Blocks (home), My subjects (score items), and My profile. They enter draft scores and submit midterm then finals for their assigned block subjects only.

Students are a secondary audience — undergraduate enrollees. They sign in with a username set on their student record (not via User accounts). Home is **My Subjects** (published midterm/final/overall only). Nav is My Subjects, My profile, Sign out. Profile: legal first/middle/last names are read-only; students may edit display name, email, phone, and address. Public applicants use **Enroll Now** for college admission; a **Registrar** (or admin) reviews applications before a student account exists.

## Product Purpose

SSIS (Secure Student Information and Access Management System) is a least-privilege student information system for **colleges and universities** (higher education), not DepEd basic education or K–12. Staff create and maintain undergraduate academic records; each student signs in as that record and sees only their own data. Success is that the right role can complete records work, and no other role can reach data or actions outside its wall.

## Positioning

A generic records CRUD app could list students. SSIS cannot honestly be copied without the access contract: role-gated routes, generic authentication errors, five-attempt / 15-minute lockout, CSRF on every mutating form, non-destructive deactivation, and an audit log of security and data events. Students never manage other students.

## Operating Context

Staff use a browser against a local or institutional PHP/MySQL host (XAMPP-style `public/` document root). Timezone is Asia/Manila. First-time use is a one-shot administrator setup page that disappears after the first user exists. Issued accounts come from an administrator or from registrar-approved enrollment; there is no instant self-registration without review.

Daily administrator work: search and edit students (course, year, academic status, and student sign-in username on the same form), maintain teachers and departments, group students into named blocks scoped by college year, assign teachers to subjects within those blocks, set grading category and term weights, export an active-student CSV, and manage staff/admin users, settings, and audit logs. Teachers open Assigned Blocks to grade their subjects and My subjects to define titled score items.

## Capabilities and Constraints

Confirmed functionality:

- Roles: `admin`, `staff` (Teacher/Staff), `registrar`; students sign in via credentials on their student record (not `users.role=student`)
- Public **Enroll Now** application (identity, family, address with full PSA PSGC province → city/municipality → barangay cascades in `public/data/ph-locations.json`, program course/year, PSA + ID photo uploads). Application types: **New student** (Student ID auto-assigned on approval as `YYYY-0001`; college year is **1st year only**) and **Moving up** (applicant enters their existing Student ID; college year **2nd–4th only**). Registrar wall is permission-segmented by admin on **Registrars**: each registrar may be granted **Enrollments**, **Students** (view-only), **Blocking**, and/or **Teachers** (assign blocks + catalog subjects). New registrar accounts start with **no duties** checked. Nav and page access follow those flags. Home is the first granted duty (Enrollments → Blocking → Teachers → Students), else a no-duties notice. Admin shares **Blocking** and **Assign teachers**, keeps editable **Students** / **Blocks**, and manages **Subjects** under More. Approve creates or updates the student + username and a one-time temporary password (shown to the reviewer) and records **Approved enrollee by** on the student; reject requires a reason. Teachers cannot open enrollments, Blocking, or Blocks. The Enrollments queue is built for high annual volume (thousands of applications): default **Pending**, status counts, filters for program / college year / application type / academic year / semester, search by name·email·student ID·mobile, and paginated results (25 per page)
- Student records with college course catalog (`course_id`), undergraduate year 1–4 (1st–4th year), academic status Active / Dropped out / Graduated, optional middle name and display name, and auto username `Lastname_F` (spaces stripped) on the student form (one-time temporary password on create); block membership (and teachers via block subjects) is managed under Blocks, not on the student form
- Public enrollment does **not** collect DepEd LRN or other basic-education identifiers; optional government ID and prior school/institution background are college-admissions oriented
- Teachers directory, one department per teacher, structured teacher profile (first/middle/last name, phone, address) editable by admin from Teachers and by the teacher via My profile; **Registrars** directory for registrar staff with per-account duty flags (Enrollments / Students / Blocking / Teachers; create defaults to none); blocks unique by **name + college year** (e.g. Block 1 · 1st year and Block 1 · 2nd year) with one-or-more matching-year students; multiple teacher+subject assignments per block (subject code + name from catalog); students joining or leaving a block automatically update subject enrollments for that block’s teachers; a student may belong to multiple blocks
- Teacher wall: home is Assigned Blocks; nav is Assigned Blocks, My subjects, My profile, Sign out. Teachers cannot open Students, Teachers, Registrars, Blocks, Reports, Courses, Departments, Administrators, or other admin modules
- Registrar wall: nav shows only granted duties, signed-in name/badge, Sign out. Registrars cannot create/edit students, create Teacher/Staff accounts, create blocks, or edit Subjects
- Admin wall: home is **Students** (no Dashboard). Primary nav groups **People** (Students, Teachers, Registrars), **Academics** (Blocks, Blocking, Assign teachers, Enrollments), and **Reports**; catalog and system pages stay under **More** (including **Subjects**: code + title)
- Subjects catalog (admin): subject code + title; block teacher assignments pick from this catalog (snapshots code/title onto the assignment)
- Blocking queue (admin + registrar with Blocking duty): active students with no block; enrollment-style filters; assign-to-block dialog lists only blocks for that student’s college year
- Assign teachers (admin + registrar with Teachers duty): pick a teacher, then assign year-labeled block + catalog subject in a modal; students who join that block later enroll automatically; edit / remove only
- Student wall: home is My Subjects; nav is My Subjects, My profile, Sign out. Click a subject row for teacher name plus published midterm, finals, and overall (draft item scores never shown). Students cannot open admin or teacher modules
- Grading: admin sets category weights (Quiz, Activities, Attendance, Projects, Exam) and midterm/final blend (each group sums to 100). Teachers define multiple titled score items per category/term on My subjects, enter draft scores per student on Assigned Blocks, then Submit midterm and later Submit finals for the whole assignment. After finals lock, teachers and admins see midterm, final, and overall (= midterm%×midterm + final%×final). Admin student form shows a subjects/grades table; students see the same published grades on My Subjects
- Courses and departments: administrators manage catalog
- Active-student report with CSV export (admin)
- Administrator accounts on **Administrators** (Users); teachers on **Teachers**; registrars on **Registrars** (Active / Deactivated / All status tabs, search, pagination, duty checkboxes — same queue chrome as Enrollments); settings (`school_name`, `maintenance_notice`, grading weight keys), and latest-200 audit log
- Security: password hashing, session rotation, lockout (staff and students), CSRF, prepared statements, output escaping, security headers, CSP `default-src 'self'`
- Issued teacher, student, and registrar accounts start with `DEFAULT_TEMP_PASSWORD` from `.env` (shown once to the issuing admin/registrar; reset temporary password restores that same configured value and forces change on next sign-in). Administrator accounts still get a random one-time temporary password. Users must set a strong password with confirmation on first successful sign-in; password fields include a show/hide control; admins cannot set custom passwords — only a reset that issues a temporary password (forced change on next sign-in); student/teacher usernames are auto-generated, not typed by admin

Constraints future work must preserve:

- Product name remains SSIS / Secure Student IS
- Institution display name is the admin-editable `school_name` setting (college/university name; schema default: “Secure Student Information System”), not a hardcoded campus
- Security contract above stays intact; login errors stay account-neutral
- Philippine **higher-education** conventions already in code: Asia/Manila, undergraduate years 1–4, midterm/finals grading; numeric term grades come from teacher-submitted scores (not typed on the student form)
- Stack is the incumbent PHP 8.1+ / MySQL (MariaDB) server-rendered app with `public/style.css` and `public/app.js`
- Do not add DepEd K–12 concepts (LRN, grade levels 1–12, Form 138 as required core identity) unless the product scope explicitly expands beyond college/university

Undecided: no named campus, logo, or support contact is committed.

## Brand Commitments

- Product: **SSIS**, UI brand **Secure Student IS**
- Audience: **college / university** (higher education) records and enrollment — not basic education
- Voice in the product is institutional and access-minded (authorized accounts, role badges, explicit empty states)
- Institution identity is a setting (`school_name` = college or university display name), not a rebrand. Do not replace SSIS with a specific campus name in code or chrome unless an administrator saved `school_name`

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
5. Academic fields stay college-conventional (PH higher-ed years 1–4, midterm/finals) unless the operator changes product rules.
