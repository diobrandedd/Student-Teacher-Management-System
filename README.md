# Secure Student Information and Access Management System

A deliberately minimal PHP 8.1+ and MySQL/MariaDB system for the midterm requirement. It implements Administrator, Teacher/Staff (`staff`), and Student roles.

## Features

- Secure login (staff/admin via `users`, students via credentials on the student record), generic authentication errors, five-attempt/15-minute lockout, password hashing, and session ID rotation
- Role-based authorization on every protected route
- Administrator and Teacher/Staff accounts in **Users**; student usernames live on the student form
- Staff student search, permitted record updates, and CSV reports
- Student access to their own academic information with limited profile editing
- PDO prepared statements, server-side allow-list validation, contextual output escaping, CSRF tokens, security headers, audit logging, and non-destructive account/record deactivation
- InnoDB constraints, unique keys, indexes, and transactions supported by MySQL/MariaDB

## Setup (XAMPP/WAMP/LAMP)

1. Copy `student-system` into the web server directory (for XAMPP, usually `htdocs`). Keep `app` and `database` outside the public web root when configuring a virtual host; otherwise the included entry point is still under `public`.
2. Copy `.env.example` to `.env` and set the database credentials. Do not commit `.env`.
3. Import `database/schema.sql` using phpMyAdmin or the MySQL client.
4. Point the web server/document root to `student-system/public`, or open `http://localhost/student-system/public/`.
5. Visit `?page=setup` once and create the initial administrator. Setup automatically becomes unavailable after the first user exists.
6. Sign in as administrator, then create accounts as follows:
   - **Teachers:** use **Teachers → Add teacher** (profile + login together). Temporary password is `123`; they must change it on first sign-in.
   - **Students:** use **Students → Add student** and set a username on that form. Temporary password is `123` (no password to type on create). Edit can leave password blank, set a strong password, or reset to `123`.
   - **Administrators / other staff logins:** use **Users** (Teacher/Staff or Administrator). Temporary password is `123`.

## Recommended production settings

- Serve only over HTTPS and set `APP_ENV=production`.
- Use a dedicated database user limited to this database; do not use `root`.
- Place `.env`, `app`, and `database` outside the served directory.
- Back up the database regularly and test restores. Rotate database credentials and apply PHP/MySQL security updates.
- Set restrictive file permissions and configure PHP error logging outside the public directory.

## Courses and Departments

- Administrators add or rename courses and departments through their navigation modules. Staff can view these lists.
- Students select a course from **Courses** when added or edited; free-text course entry is replaced by a `course_id` relationship. Existing course names are migrated automatically. The old course text column is kept synchronized for compatibility.
- Year level is 1–4. GPA is not entered on student create/edit; grades will come from teacher-submitted scores.
- Academic status options are Active, Dropped out, and Graduated.
- In **Teachers**, use **Add teacher** (or edit) for profile + department + login. Each teacher has one department; existing teachers remain unassigned until selected.
- Run `database/migrate.php` for existing installations. It adds the tables and foreign keys without deleting existing records.
- `tests/academics.php` accepts `student`, `invalid-course`, `department`, `invalid-department`, or `rename-course`; each scenario runs against connection-local temporary tables.

## Blocks

The **Teachers** module lists Teacher/Staff accounts with name, email, status, and assigned block count. Search by name or email, open a teacher’s blocks, or (as administrator) **Add teacher** to create both the profile and login (temporary password `123`). Administrators and staff can view the directory; students cannot access it.

- Administrator and Teacher/Staff users can open **Blocks**, select **Add block**, name the block, assign an active teacher, and select one or more students.
- Search the block list by teacher name or block name. Results are paginated. **View / Edit** opens the membership selection and allows changes.
- A student may belong to multiple blocks; duplicate membership within the same block is prevented. Existing individual staff assignments are kept separately.
- Teacher records link to Teacher/Staff accounts in Users. Existing staff accounts are backfilled by the migration; new or updated staff accounts are registered automatically.
- Existing installations: run `C:\xampp\php\php.exe database\migrate.php` from the project directory. The migration is repeatable and preserves existing records. Fresh installations include the tables in `database/schema.sql`.
- Run `C:\xampp\php\php.exe tests\blocks.php` to verify assignment rules using temporary tables, without changing existing data.

## CIA principles

- **Confidentiality:** authentication, HTTPS-ready secure cookies, role checks, least-privilege views, password hashes, and protection from framing/content sniffing.
- **Integrity:** CSRF tokens, prepared statements, validation, foreign keys, unique constraints, controlled role values, and audit logs.
- **Availability:** indexed searches, bounded query results, account lockouts that expire automatically, non-destructive deactivation, and documented backups/restores.

## Quick verification checklist

- An anonymous user cannot open dashboard, student, user, log, settings, or report pages.
- Staff cannot open users, logs, or settings; students cannot open student management or reports.
- Invalid CSRF tokens are rejected; apostrophes and HTML in data cannot cause SQL injection or stored XSS.
- Five failed sign-ins lock an account for 15 minutes without revealing whether it exists.
- Deactivated users cannot sign in, and changes appear in the audit log.
