-- Enrollment system: registrar role, applications, documents, approval attribution

ALTER TABLE users MODIFY role ENUM('admin','staff','registrar') NOT NULL;

ALTER TABLE students
  ADD COLUMN enrollment_approved_by BIGINT UNSIGNED NULL AFTER is_active,
  ADD COLUMN enrollment_approved_at DATETIME NULL AFTER enrollment_approved_by;

ALTER TABLE students
  ADD CONSTRAINT fk_students_enrollment_approver
  FOREIGN KEY (enrollment_approved_by) REFERENCES users(id) ON DELETE SET NULL;

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
