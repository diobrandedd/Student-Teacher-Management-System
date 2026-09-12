# Secure Student Information and Access Management System

A deliberately minimal PHP 8.1+ and MySQL/MariaDB system for the midterm requirement. It is built for **college / university** (higher education) student records — not DepEd basic education. It implements Administrator, Teacher/Staff (`staff`), Registrar, and Student roles.

## Features

- Secure login (staff/admin via `users`, students via credentials on the student record), generic authentication errors, five-attempt/15-minute lockout, password hashing, and session ID rotation
- Role-based authorization on every protected route
- Administrator accounts in **Administrators**; teachers in **Teachers**; registrars in **Registrars**; student usernames live on the student form
- Administrator student search, record updates, CSV reports, blocks, and catalog
- Teachers use **Assigned Blocks** (home), **My subjects**, and **My profile** only — draft scores then submit midterm/finals
- Administrator **Grading system** for category and term weights
- Students use **My Subjects** (home) and **My profile** — published grades only; legal names read-only; editable display name and contact fields
- Public **Enroll Now** + **Registrar** role to approve/reject applications (PSA birth certificate + ID photo required)
- Address cascades use the full PSA PSGC list (all provinces + Metro Manila, cities/municipalities, barangays) in `public/data/ph-locations.json` — regenerate with `php database/build-ph-locations.php` when PSA updates
- PDO prepared statements, server-side allow-list validation, contextual output escaping, CSRF tokens, security headers, audit logging, and non-destructive account/record deactivation
- InnoDB constraints, unique keys, indexes, and transactions supported by MySQL/MariaDB

## Setup (XAMPP/WAMP/LAMP)

1. Copy `student-system` into the web server directory (for XAMPP, usually `htdocs`). Keep `app` and `database` outside the public web root when configuring a virtual host; otherwise the included entry point is still under `public`.
2. Copy `.env.example` to `.env` and set the database credentials. Do not commit `.env`.
3. Import `database/schema.sql` using phpMyAdmin or the MySQL client.
4. Point the web server/document root to `student-system/public`, or open `http://localhost/student-system/public/`.
5. Visit `?page=setup` once and create the initial administrator. Setup automatically becomes unavailable after the first user exists.
6. Sign in as administrator, then create accounts as follows:
   - **Teachers:** use **Teachers → Add teacher** (profile + login together). A one-time temporary password is shown after create; they must change it on first sign-in.
   - **Registrars:** use **Registrars → Add registrar** (duties start unchecked — grant Enrollments / Students / Blocking / Teachers as needed). A one-time temporary password is shown after create.
   - **Students:** use **Students → Add student**. A one-time temporary password is shown after create (no password to type on create). Edit can reset a new temporary password.
   - **Administrators:** use **More → Administrators**. A one-time temporary password is shown after create or reset.

## Sample data (local demos)

Rebuild a full demo set with:

```bash
php database/migrate.php
php database/seed-sample.php
```

The seeder **keeps administrator and registrar accounts**, replaces sample staff/teachers, and loads:

| Item | Count |
|------|-------|
| Teachers | 20 (5 per college year; usernames `t1n1`…`t4n5`) |
| Subjects | 20 (`IT 1xx` / `2xx` / `3xx` / `4xx`) |
| Blocks | 20 (`Block 1`–`5` × years 1–4) |
| Students | 100 (5 per block; IDs `2026-0001`…`0100`) |
| New enrollments | 5 pending (1st year) |
| Moving up | 15 pending (5 into each of years 2–4) |

Demo temporary password for seeded teachers/students comes from `DEFAULT_TEMP_PASSWORD` in `.env` (default `DemoTemp1234`; must change on first sign-in). Existing registrars (e.g. `rgarcia`, `Klint_R`) and admins are left as-is.

Open **Enrollments** as a registrar/admin: filter **New student** for 1st-year applicants, **Moving up** + year for advancement apps.

`database/sample-data.sql` is a smaller legacy dump; prefer `seed-sample.php` for the current scale.
## Recommended production settings

- Serve only over HTTPS and set `APP_ENV=production`.
- Use a dedicated database user limited to this database; do not use `root`.
- Place `.env`, `app`, and `database` outside the served directory.
- Back up the database regularly and test restores. Rotate database credentials and apply PHP/MySQL security updates.
- Set restrictive file permissions and configure PHP error logging outside the public directory.

## Courses and Departments

- Administrators add or rename courses and departments through their navigation modules.
- Students select a course from **Courses** when added or edited; free-text course entry is replaced by a `course_id` relationship. Existing course names are migrated automatically. The old course text column is kept synchronized for compatibility.
- Year level is undergraduate **1st–4th year**. Numeric grades come from teacher-submitted scores (visible on the student form after submit).
- Academic status options are Active, Dropped out, and Graduated.
- In **Teachers**, use **Add teacher** (or edit) for profile + department + login. Each teacher has one department; existing teachers remain unassigned until selected.
- Run `database/migrate.php` for existing installations. It adds the tables and foreign keys without deleting existing records.
- `tests/academics.php` accepts `student`, `invalid-course`, `department`, `invalid-department`, or `rename-course`; each scenario runs against connection-local temporary tables.

## Grading

- Administrators open **Grading system** to set category weights (Quiz, Activities, Attendance, Projects, Exam) and the midterm/final blend. Each group must total 100.
- Teachers open **My subjects** to add titled score items per category and term (multiple quizzes allowed; one midterm exam and one final exam per assignment).
- On **Assigned Blocks**, teachers enter draft scores per student, then **Submit midterm** for the whole roster, later **Submit finals**. Incomplete drafts block submit.
- After finals lock, the roster and the admin student form show midterm, final, and overall (blend formula). Students see the same published grades on **My Subjects** (click a row).
- Run `C:\xampp\php\php.exe tests\grading.php` for weight validation and compute smoke tests.

## Student portal

- Students sign in with the username on their record (`Lastname_F` pattern). New teacher/student/registrar accounts use `DEFAULT_TEMP_PASSWORD` from `.env` (shown to staff on create/approve/reset); they must change it on first sign-in. Sample seed accounts use the same value. Administrators still get a random one-time temporary password.
- Home is **My Subjects**. Click a subject to open teacher + midterm / finals / overall (only after the teacher submits that term).
- **My profile**: first, middle, and last name are read-only; display name, email, phone, and address are editable. Username is read-only.

## Blocks

The **Teachers** module lists Teacher/Staff accounts with name, email, status, and assigned block count. Search by name or email, open a teacher’s blocks, or (as administrator) **Add teacher** to create both the profile and login (one-time temporary password shown after create). Administrators manage the directory; teachers do not open this module.

- Administrators open **Blocks** to create/rename year-scoped blocks and manage rosters. **Blocking** (admin + registrars with Blocking duty) places active students who are not yet in any block into a matching college-year block. **Assign teachers** (admin) / **Teachers** (registrars with Teachers duty) assigns catalog subjects + teachers from a teacher-first list.
- Admin **Subjects** (under More) defines subject code + title used in those assignments.
- Search the block list by teacher name or block name. Results are paginated. **Edit** opens the block. Under **Students**, use **Assign student to this block** to search and assign one student at a time, or **Remove** on a row. The whole school is never listed as a checklist.
- A student may belong to multiple blocks; duplicate assignment within the same block is prevented.
- Teacher records link to Teacher/Staff accounts managed under **Teachers**. Existing staff accounts are backfilled by the migration; new or updated staff accounts are registered automatically.
- Existing installations: run `C:\xampp\php\php.exe database\migrate.php` from the project directory (includes subjects catalog). Fresh installations include the tables in `database/schema.sql`.
- Run `C:\xampp\php\php.exe tests\blocks.php` to verify assignment rules using temporary tables, without changing existing data.

## CIA principles

- **Confidentiality:** authentication, HTTPS-ready secure cookies, role checks, least-privilege views, password hashes, and protection from framing/content sniffing.
- **Integrity:** CSRF tokens, prepared statements, validation, foreign keys, unique constraints, controlled role values, and audit logs.
- **Availability:** indexed searches, bounded query results, account lockouts that expire automatically, non-destructive deactivation, and documented backups/restores.

## Quick verification checklist

- An anonymous user cannot open student, user, log, settings, or report pages.
- Staff cannot open users, logs, or settings; students cannot open student management or reports.
- Invalid CSRF tokens are rejected; apostrophes and HTML in data cannot cause SQL injection or stored XSS.
- Five failed sign-ins lock an account for 15 minutes without revealing whether it exists.
- Deactivated users cannot sign in, and changes appear in the audit log.
