-- Student credentials live on the student record (not users.role=student).
ALTER TABLE students
  ADD COLUMN username VARCHAR(50) NULL,
  ADD COLUMN password_hash VARCHAR(255) NULL,
  ADD COLUMN must_change_password BOOLEAN NOT NULL DEFAULT TRUE,
  ADD COLUMN failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN locked_until DATETIME NULL,
  ADD COLUMN last_login_at DATETIME NULL;

-- Copy credentials from any previously linked student login accounts.
UPDATE students s
JOIN users u ON u.id = s.user_id AND u.role = 'student'
SET
  s.username = u.username,
  s.password_hash = u.password_hash,
  s.must_change_password = u.must_change_password,
  s.failed_attempts = u.failed_attempts,
  s.locked_until = u.locked_until,
  s.last_login_at = u.last_login_at
WHERE s.username IS NULL;

-- Free usernames so staff/admin logins and new student usernames do not collide.
UPDATE users
SET
  is_active = 0,
  username = CONCAT('legacy_student_', id),
  email = CONCAT('legacy_student_', id, '@invalid.local')
WHERE role = 'student';

ALTER TABLE students ADD UNIQUE KEY uq_students_username (username);

-- Drop obsolete link to users.
ALTER TABLE students DROP FOREIGN KEY fk_students_user;
ALTER TABLE students DROP COLUMN user_id;

DELETE FROM users WHERE role = 'student';
ALTER TABLE users MODIFY role ENUM('admin','staff') NOT NULL;
