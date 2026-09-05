USE student_secure;

ALTER TABLE users ADD COLUMN full_name VARCHAR(150) NULL AFTER id;
UPDATE users SET full_name = username WHERE full_name IS NULL OR full_name = '';
ALTER TABLE users MODIFY full_name VARCHAR(150) NOT NULL;

ALTER TABLE students
  ADD COLUMN assigned_staff_id BIGINT UNSIGNED NULL AFTER user_id,
  ADD INDEX idx_students_staff (assigned_staff_id),
  ADD CONSTRAINT fk_students_staff
    FOREIGN KEY (assigned_staff_id) REFERENCES users(id) ON DELETE SET NULL;

