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

INSERT INTO system_settings (setting_key, setting_value) VALUES
('grade_weight_quiz', '20'),
('grade_weight_activities', '20'),
('grade_weight_attendance', '10'),
('grade_weight_projects', '20'),
('grade_weight_exam', '30'),
('grade_weight_midterm', '40'),
('grade_weight_final', '60')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
