CREATE DATABASE IF NOT EXISTS student_secure
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE student_secure;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff','registrar') NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  must_change_password BOOLEAN NOT NULL DEFAULT FALSE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role_active (role, is_active)
) ENGINE=InnoDB;

CREATE TABLE students (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assigned_staff_id BIGINT UNSIGNED NULL,
  student_number VARCHAR(30) NOT NULL UNIQUE,
  first_name VARCHAR(80) NOT NULL,
  middle_name VARCHAR(80) NULL,
  last_name VARCHAR(80) NOT NULL,
  display_name VARCHAR(150) NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(20) NULL,
  address VARCHAR(255) NULL,
  course VARCHAR(100) NOT NULL,
  year_level TINYINT UNSIGNED NOT NULL,
  academic_status ENUM('Active','Dropped out','Graduated') NOT NULL DEFAULT 'Active',
  gpa DECIMAL(3,2) NULL,
  username VARCHAR(50) NULL UNIQUE,
  password_hash VARCHAR(255) NULL,
  must_change_password BOOLEAN NOT NULL DEFAULT TRUE,
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  enrollment_approved_by BIGINT UNSIGNED NULL,
  enrollment_approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_students_staff FOREIGN KEY (assigned_staff_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_students_enrollment_approver FOREIGN KEY (enrollment_approved_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_students_name (last_name, first_name),
  INDEX idx_students_staff (assigned_staff_id),
  INDEX idx_students_course_status (course, academic_status)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  details VARCHAR(500) NULL,
  ip_address VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_created (created_at),
  INDEX idx_audit_user_action (user_id, action)
) ENGINE=InnoDB;

CREATE TABLE system_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO system_settings (setting_key, setting_value) VALUES
('school_name', 'Secure Student Information System'),
('maintenance_notice', ''),
('grade_weight_quiz', '20'),
('grade_weight_activities', '20'),
('grade_weight_attendance', '10'),
('grade_weight_projects', '20'),
('grade_weight_exam', '30'),
('grade_weight_midterm', '40'),
('grade_weight_final', '60')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

CREATE TABLE IF NOT EXISTS teachers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    first_name VARCHAR(80) NULL,
    middle_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NULL,
    phone VARCHAR(20) NULL,
    address VARCHAR(255) NULL,
    department_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_teachers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO teachers (user_id)
SELECT id FROM users WHERE role = 'staff'
ON DUPLICATE KEY UPDATE user_id = VALUES(user_id);

CREATE TABLE IF NOT EXISTS blocks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS block_students (
    block_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (block_id, student_id),
    INDEX idx_block_students_student (student_id),
    CONSTRAINT fk_block_students_block FOREIGN KEY (block_id) REFERENCES blocks(id) ON DELETE CASCADE,
    CONSTRAINT fk_block_students_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS block_subject_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    block_id BIGINT UNSIGNED NOT NULL,
    teacher_id BIGINT UNSIGNED NOT NULL,
    subject_code VARCHAR(30) NOT NULL,
    subject_name VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_block_subject_code (block_id, subject_code),
    INDEX idx_bsa_teacher (teacher_id),
    CONSTRAINT fk_bsa_block FOREIGN KEY (block_id) REFERENCES blocks(id) ON DELETE CASCADE,
    CONSTRAINT fk_bsa_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS block_subject_enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    block_id BIGINT UNSIGNED NOT NULL,
    teacher_id BIGINT UNSIGNED NOT NULL,
    subject_code VARCHAR(30) NOT NULL,
    subject_name VARCHAR(150) NOT NULL,
    synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_assignment_student (assignment_id, student_id),
    INDEX idx_bse_student (student_id),
    INDEX idx_bse_block (block_id),
    CONSTRAINT fk_bse_assignment FOREIGN KEY (assignment_id) REFERENCES block_subject_assignments(id) ON DELETE CASCADE,
    CONSTRAINT fk_bse_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subject_score_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id BIGINT UNSIGNED NOT NULL,
    term ENUM('midterm','final') NOT NULL,
    category ENUM('quiz','activities','attendance','projects','exam') NOT NULL,
    title VARCHAR(150) NOT NULL,
    max_score DECIMAL(8,2) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ssi_assignment_term (assignment_id, term),
    CONSTRAINT fk_ssi_assignment FOREIGN KEY (assignment_id) REFERENCES block_subject_assignments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_score_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    score DECIMAL(8,2) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sse_entry (assignment_id, student_id, item_id),
    INDEX idx_sse_student (student_id),
    CONSTRAINT fk_sse_assignment FOREIGN KEY (assignment_id) REFERENCES block_subject_assignments(id) ON DELETE CASCADE,
    CONSTRAINT fk_sse_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_sse_item FOREIGN KEY (item_id) REFERENCES subject_score_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assignment_grade_submissions (
    assignment_id BIGINT UNSIGNED PRIMARY KEY,
    midterm_submitted_at DATETIME NULL,
    midterm_submitted_by BIGINT UNSIGNED NULL,
    final_submitted_at DATETIME NULL,
    final_submitted_by BIGINT UNSIGNED NULL,
    CONSTRAINT fk_ags_assignment FOREIGN KEY (assignment_id) REFERENCES block_subject_assignments(id) ON DELETE CASCADE,
    CONSTRAINT fk_ags_midterm_by FOREIGN KEY (midterm_submitted_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_ags_final_by FOREIGN KEY (final_submitted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_term_grades (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    term ENUM('midterm','final') NOT NULL,
    grade DECIMAL(5,2) NOT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stg_term (assignment_id, student_id, term),
    INDEX idx_stg_student (student_id),
    CONSTRAINT fk_stg_assignment FOREIGN KEY (assignment_id) REFERENCES block_subject_assignments(id) ON DELETE CASCADE,
    CONSTRAINT fk_stg_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS courses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;
ALTER TABLE students ADD COLUMN course_id BIGINT UNSIGNED NULL;
INSERT INTO courses (name) SELECT DISTINCT TRIM(course) FROM students WHERE TRIM(course) <> ''
ON DUPLICATE KEY UPDATE name=VALUES(name);
UPDATE students s JOIN courses c ON c.name=TRIM(s.course) SET s.course_id=c.id WHERE s.course_id IS NULL;

ALTER TABLE students ADD CONSTRAINT fk_students_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT;
ALTER TABLE teachers ADD CONSTRAINT fk_teachers_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS enrollment_applications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  reject_reason VARCHAR(500) NULL,
  student_id BIGINT UNSIGNED NULL,
  last_name VARCHAR(80) NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  middle_name VARCHAR(80) NULL,
  no_middle_name BOOLEAN NOT NULL DEFAULT FALSE,
  suffix VARCHAR(20) NULL,
  preferred_name VARCHAR(80) NULL,
  sex ENUM('male','female') NOT NULL,
  civil_status ENUM('single','married','widowed','separated','other') NOT NULL,
  citizenship VARCHAR(80) NOT NULL DEFAULT 'Filipino',
  religion VARCHAR(80) NULL,
  blood_type VARCHAR(8) NULL,
  date_of_birth DATE NOT NULL,
  place_of_birth VARCHAR(190) NOT NULL,
  gov_id_type VARCHAR(50) NULL,
  gov_id_number VARCHAR(80) NULL,
  email VARCHAR(190) NOT NULL,
  mobile VARCHAR(20) NOT NULL,
  province_code VARCHAR(20) NOT NULL,
  province_name VARCHAR(120) NOT NULL,
  city_code VARCHAR(20) NOT NULL,
  city_name VARCHAR(120) NOT NULL,
  barangay_code VARCHAR(20) NOT NULL,
  barangay_name VARCHAR(120) NOT NULL,
  address_line1 VARCHAR(190) NOT NULL,
  address_line2 VARCHAR(190) NULL,
  mother_maiden_name VARCHAR(150) NOT NULL,
  father_name VARCHAR(150) NOT NULL,
  guardian_name VARCHAR(150) NOT NULL,
  guardian_relationship ENUM('mother','father','relative','other') NOT NULL,
  guardian_number VARCHAR(20) NOT NULL,
  guardian_address VARCHAR(255) NULL,
  emergency_name VARCHAR(150) NULL,
  emergency_relationship VARCHAR(80) NULL,
  emergency_number VARCHAR(20) NULL,
  application_type ENUM('new','moving_up') NOT NULL,
  student_number VARCHAR(30) NULL,
  last_school VARCHAR(190) NOT NULL,
  last_school_address VARCHAR(255) NULL,
  year_graduated VARCHAR(20) NULL,
  strand_or_previous_course VARCHAR(150) NULL,
  course_id BIGINT UNSIGNED NOT NULL,
  course_id_second BIGINT UNSIGNED NULL,
  year_level TINYINT UNSIGNED NOT NULL,
  academic_year VARCHAR(20) NOT NULL,
  semester ENUM('1','2','summer') NOT NULL,
  privacy_consent BOOLEAN NOT NULL DEFAULT FALSE,
  how_heard VARCHAR(120) NULL,
  ip_address VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_enroll_status (status, created_at),
  INDEX idx_enroll_email (email),
  CONSTRAINT fk_enroll_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_enroll_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
  CONSTRAINT fk_enroll_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
  CONSTRAINT fk_enroll_course2 FOREIGN KEY (course_id_second) REFERENCES courses(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS enrollment_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  doc_type ENUM('psa_birth','id_photo') NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  byte_size INT UNSIGNED NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_enroll_doc (application_id, doc_type),
  CONSTRAINT fk_enroll_doc_app FOREIGN KEY (application_id) REFERENCES enrollment_applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;
