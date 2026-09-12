-- LEGACY small sample dump — prefer: php database/seed-sample.php
-- The PHP seeder rebuilds the full demo (20 teachers, 20 subjects, 20 blocks, 100 students, enrollment apps)
-- while preserving administrator and registrar accounts.
--
-- This SQL file is kept only for older docs/workflows and no longer matches the full seed scale.
--
-- After a PHP seed, demo teacher usernames are t1n1…t4n5 (temp password DemoTemp1234).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Password hash for temporary password "DemoTemp1234"
SET @pwd := '$2y$10$iD5FdiQVNfh/PvxLMpPBUeLIorUi7WcsIDMO7dbXpyA9i9Yi/PlTy';

-- Wipe prior sample enrollment docs/apps
DELETE ed FROM enrollment_documents ed
INNER JOIN enrollment_applications ea ON ea.id = ed.application_id
WHERE ea.email LIKE '%@enroll.sample.edu';
DELETE FROM enrollment_applications WHERE email LIKE '%@enroll.sample.edu';

-- Wipe prior sample academic graph (same tags as seed-sample.php)
DELETE FROM student_score_entries;
DELETE FROM student_term_grades;
DELETE FROM assignment_grade_submissions;
DELETE FROM subject_score_items;
DELETE FROM block_subject_enrollments;
DELETE FROM block_subject_assignments;
DELETE FROM block_students;
DELETE FROM blocks WHERE name IN ('Block 1', 'Block 2', 'BSIT 4-A');
DELETE FROM students WHERE student_number LIKE '2024-S%' OR student_number LIKE '2026-%' OR email LIKE '%@enroll.sample.edu';
DELETE FROM teachers WHERE user_id IN (
  SELECT id FROM users WHERE username IN ('msantos','jdelacruz','rgarcia')
    OR email IN ('maria.santos@example.edu','juan.delacruz@example.edu','rosa.garcia@example.edu')
);
DELETE FROM users WHERE username IN ('msantos','jdelacruz','rgarcia')
  OR email IN ('maria.santos@example.edu','juan.delacruz@example.edu','rosa.garcia@example.edu');
UPDATE teachers SET department_id = NULL WHERE department_id IN (
  SELECT id FROM (SELECT id FROM departments WHERE name = 'Information Technology') AS d
);
DELETE FROM departments WHERE name = 'Information Technology';
DELETE FROM courses WHERE name IN (
  'Bachelor of Science in Information Technology',
  'Bachelor of Science in Computer Science'
);

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO system_settings (setting_key, setting_value) VALUES
  ('grade_weight_quiz', '20'),
  ('grade_weight_activities', '20'),
  ('grade_weight_attendance', '10'),
  ('grade_weight_projects', '20'),
  ('grade_weight_exam', '30'),
  ('grade_weight_midterm', '40'),
  ('grade_weight_final', '60')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO departments (name) VALUES ('Information Technology');
SET @dept_id := LAST_INSERT_ID();

INSERT INTO courses (name) VALUES ('Bachelor of Science in Information Technology');
SET @course_bsit := LAST_INSERT_ID();
INSERT INTO courses (name) VALUES ('Bachelor of Science in Computer Science');
SET @course_bscs := LAST_INSERT_ID();

INSERT INTO users (full_name, username, email, password_hash, role, must_change_password) VALUES
  ('Maria Santos', 'msantos', 'maria.santos@example.edu', @pwd, 'staff', 1);
SET @user_msantos := LAST_INSERT_ID();
INSERT INTO teachers (user_id, first_name, last_name, phone, department_id) VALUES
  (@user_msantos, 'Maria', 'Santos', '09171234501', @dept_id);
SET @teacher_msantos := LAST_INSERT_ID();

INSERT INTO users (full_name, username, email, password_hash, role, must_change_password) VALUES
  ('Juan Dela Cruz', 'jdelacruz', 'juan.delacruz@example.edu', @pwd, 'staff', 1);
SET @user_jdela := LAST_INSERT_ID();
INSERT INTO teachers (user_id, first_name, last_name, phone, department_id) VALUES
  (@user_jdela, 'Juan', 'Dela Cruz', '09171234502', @dept_id);
SET @teacher_jdela := LAST_INSERT_ID();

INSERT INTO users (full_name, username, email, password_hash, role, must_change_password) VALUES
  ('Rosa Garcia', 'rgarcia', 'rosa.garcia@example.edu', @pwd, 'registrar', 1);
SET @user_registrar := LAST_INSERT_ID();

INSERT INTO students (
  student_number, first_name, last_name, email, phone, address,
  course, course_id, year_level, academic_status, username, password_hash, must_change_password
) VALUES
  ('2026-0001', 'Ana', 'Reyes', 'ana.reyes@student.example.edu', '09181234001', 'Quezon City', 'Bachelor of Science in Information Technology', @course_bsit, 1, 'Active', 'Reyes_A', @pwd, 1),
  ('2026-0002', 'Ben', 'Garcia', 'ben.garcia@student.example.edu', '09181234002', 'Makati', 'Bachelor of Science in Information Technology', @course_bsit, 1, 'Active', 'Garcia_B', @pwd, 1),
  ('2026-0003', 'Carla', 'Lopez', 'carla.lopez@student.example.edu', '09181234003', 'Pasig', 'Bachelor of Science in Information Technology', @course_bsit, 2, 'Active', 'Lopez_C', @pwd, 1),
  ('2026-0004', 'Diego', 'Ramos', 'diego.ramos@student.example.edu', '09181234004', 'Marikina', 'Bachelor of Science in Information Technology', @course_bsit, 2, 'Active', 'Ramos_D', @pwd, 1),
  ('2026-0005', 'Elena', 'Torres', 'elena.torres@student.example.edu', '09181234005', 'Taguig', 'Bachelor of Science in Information Technology', @course_bsit, 3, 'Active', 'Torres_E', @pwd, 1),
  ('2026-0006', 'Felix', 'Navarro', 'felix.navarro@student.example.edu', '09181234006', 'Caloocan', 'Bachelor of Science in Information Technology', @course_bsit, 3, 'Active', 'Navarro_F', @pwd, 1),
  ('2026-0007', 'Grace', 'Mendoza', 'grace.mendoza@student.example.edu', '09181234007', 'Manila', 'Bachelor of Science in Information Technology', @course_bsit, 4, 'Active', 'Mendoza_G', @pwd, 1),
  ('2026-0008', 'Hugo', 'Villanueva', 'hugo.villanueva@student.example.edu', '09181234008', 'Parañaque', 'Bachelor of Science in Information Technology', @course_bsit, 4, 'Active', 'Villanueva_H', @pwd, 1);

SET @s1 := (SELECT id FROM students WHERE student_number='2026-0001');
SET @s2 := (SELECT id FROM students WHERE student_number='2026-0002');
SET @s3 := (SELECT id FROM students WHERE student_number='2026-0003');
SET @s4 := (SELECT id FROM students WHERE student_number='2026-0004');
SET @s5 := (SELECT id FROM students WHERE student_number='2026-0005');
SET @s6 := (SELECT id FROM students WHERE student_number='2026-0006');
SET @s7 := (SELECT id FROM students WHERE student_number='2026-0007');
SET @s8 := (SELECT id FROM students WHERE student_number='2026-0008');

INSERT INTO blocks (name, year_level) VALUES ('Block 1', 1);
SET @block1 := LAST_INSERT_ID();
INSERT INTO blocks (name, year_level) VALUES ('Block 1', 2);
SET @block1y2 := LAST_INSERT_ID();
INSERT INTO blocks (name, year_level) VALUES ('Block 2', 3);
SET @block2 := LAST_INSERT_ID();
INSERT INTO blocks (name, year_level) VALUES ('Block 2', 4);
SET @block2y4 := LAST_INSERT_ID();

INSERT INTO block_students (block_id, student_id) VALUES
  (@block1, @s1), (@block1, @s2),
  (@block1y2, @s3), (@block1y2, @s4),
  (@block2, @s5), (@block2, @s6),
  (@block2y4, @s7), (@block2y4, @s8);

INSERT INTO subjects (code, title) VALUES
  ('IT 421', 'Information Security'),
  ('IT 411', 'Systems Analysis and Design'),
  ('IT 101', 'Introduction to Computing'),
  ('IT 201', 'Data Structures')
ON DUPLICATE KEY UPDATE title=VALUES(title);
SET @subj_it421 := (SELECT id FROM subjects WHERE code='IT 421' LIMIT 1);
SET @subj_it411 := (SELECT id FROM subjects WHERE code='IT 411' LIMIT 1);
SET @subj_it101 := (SELECT id FROM subjects WHERE code='IT 101' LIMIT 1);
SET @subj_it201 := (SELECT id FROM subjects WHERE code='IT 201' LIMIT 1);

INSERT INTO block_subject_assignments (block_id, teacher_id, subject_id, subject_code, subject_name) VALUES
  (@block1, @teacher_msantos, @subj_it421, 'IT 421', 'Information Security');
SET @asg1 := LAST_INSERT_ID();
INSERT INTO block_subject_assignments (block_id, teacher_id, subject_id, subject_code, subject_name) VALUES
  (@block1y2, @teacher_msantos, @subj_it201, 'IT 201', 'Data Structures');
SET @asg1b := LAST_INSERT_ID();
INSERT INTO block_subject_assignments (block_id, teacher_id, subject_id, subject_code, subject_name) VALUES
  (@block2, @teacher_jdela, @subj_it411, 'IT 411', 'Systems Analysis and Design');
SET @asg2 := LAST_INSERT_ID();
INSERT INTO block_subject_assignments (block_id, teacher_id, subject_id, subject_code, subject_name) VALUES
  (@block2y4, @teacher_jdela, @subj_it101, 'IT 101', 'Introduction to Computing');
SET @asg2b := LAST_INSERT_ID();

INSERT INTO block_subject_enrollments (assignment_id, student_id, block_id, teacher_id, subject_code, subject_name, synced_at) VALUES
  (@asg1, @s1, @block1, @teacher_msantos, 'IT 421', 'Information Security', NOW()),
  (@asg1, @s2, @block1, @teacher_msantos, 'IT 421', 'Information Security', NOW()),
  (@asg1b, @s3, @block1y2, @teacher_msantos, 'IT 201', 'Data Structures', NOW()),
  (@asg1b, @s4, @block1y2, @teacher_msantos, 'IT 201', 'Data Structures', NOW()),
  (@asg2, @s5, @block2, @teacher_jdela, 'IT 411', 'Systems Analysis and Design', NOW()),
  (@asg2, @s6, @block2, @teacher_jdela, 'IT 411', 'Systems Analysis and Design', NOW()),
  (@asg2b, @s7, @block2y4, @teacher_jdela, 'IT 101', 'Introduction to Computing', NOW()),
  (@asg2b, @s8, @block2y4, @teacher_jdela, 'IT 101', 'Introduction to Computing', NOW());

INSERT INTO subject_score_items (assignment_id, term, category, title, max_score, sort_order) VALUES
  (@asg1, 'midterm', 'quiz', 'Quiz 1', 50, 1),
  (@asg1, 'midterm', 'quiz', 'Quiz 2', 50, 2),
  (@asg1, 'midterm', 'activities', 'Lab Activity 1', 100, 1),
  (@asg1, 'midterm', 'attendance', 'Midterm attendance', 100, 1),
  (@asg1, 'midterm', 'projects', 'Project draft', 100, 1),
  (@asg1, 'midterm', 'exam', 'Midterm exam', 100, 1);

-- Enrollment applications: new + moving_up samples
INSERT INTO enrollment_applications (
  status, reviewed_by, reviewed_at, reject_reason,
  last_name, first_name, middle_name, no_middle_name,
  sex, civil_status, citizenship, date_of_birth, place_of_birth,
  email, mobile,
  province_code, province_name, city_code, city_name, barangay_code, barangay_name,
  address_line1, mother_maiden_name, father_name,
  guardian_name, guardian_relationship, guardian_number,
  application_type, student_number, last_school, year_graduated, strand_or_previous_course,
  course_id, course_id_second, year_level, academic_year, semester,
  privacy_consent, ip_address
) VALUES
(
  'pending', NULL, NULL, NULL,
  'Santos', 'Patricia', 'Cruz', 0,
  'female', 'single', 'Filipino', '2007-03-14', 'Cavite City',
  'patricia.santos@enroll.sample.edu', '09190001001',
  '042100000', 'Cavite', '042108000', 'City of Bacoor', '042108001', 'Molino I',
  '123 Sample Street', 'Lorna Cruz Santos', 'Roberto Santos',
  'Lorna Cruz Santos', 'mother', '09190001011',
  'new', NULL, 'Cavite National Science High School', '2025', 'STEM',
  @course_bsit, @course_bscs, 1, '2026-2027', '1',
  1, '127.0.0.1'
),
(
  'pending', NULL, NULL, NULL,
  'Villanueva', 'Marco', NULL, 1,
  'male', 'single', 'Filipino', '2006-11-02', 'Quezon City',
  'marco.villanueva@enroll.sample.edu', '09190001002',
  '130000000', 'Metro Manila (NCR)', '137404000', 'Quezon City', '137404001', 'Commonwealth',
  '45 Commonwealth Ave', 'Ana Villanueva', 'Pedro Villanueva',
  'Pedro Villanueva', 'father', '09190001012',
  'new', NULL, 'Quezon City Science High School', '2024', 'STEM',
  @course_bsit, NULL, 1, '2026-2027', '1',
  1, '127.0.0.1'
),
(
  'pending', NULL, NULL, NULL,
  'Reyes', 'Ana', NULL, 1,
  'female', 'single', 'Filipino', '2006-05-10', 'Quezon City',
  'ana.reyes@enroll.sample.edu', '09181234001',
  '130000000', 'Metro Manila (NCR)', '137404000', 'Quezon City', '137404001', 'Diliman',
  'Quezon City sample address', 'Sample Mother', 'Sample Father',
  'Sample Mother', 'mother', '09190001018',
  'moving_up', '2026-0001', 'This institution (moving up)', NULL, NULL,
  @course_bsit, NULL, 2, '2026-2027', '1',
  1, '127.0.0.1'
),
(
  'pending', NULL, NULL, NULL,
  'Garcia', 'Ben', NULL, 1,
  'male', 'single', 'Filipino', '2006-02-18', 'Makati',
  'ben.garcia@enroll.sample.edu', '09181234002',
  '130000000', 'Metro Manila (NCR)', '137602000', 'City of Makati', '137602001', 'Poblacion',
  'Makati sample address', 'Sample Mother', 'Sample Father',
  'Sample Mother', 'mother', '09190001019',
  'moving_up', '2026-0002', 'This institution (moving up)', NULL, NULL,
  @course_bsit, NULL, 2, '2026-2027', '1',
  1, '127.0.0.1'
),
(
  'pending', NULL, NULL, NULL,
  'Lopez', 'Carla', NULL, 1,
  'female', 'single', 'Filipino', '2005-07-21', 'Pasig',
  'carla.lopez@enroll.sample.edu', '09181234003',
  '130000000', 'Metro Manila (NCR)', '137401000', 'City of Pasig', '137401001', 'Kapitolyo',
  'Pasig sample address', 'Maria Lopez', 'Jose Lopez',
  'Maria Lopez', 'mother', '09190001013',
  'moving_up', '2026-0003', 'This institution (moving up)', NULL, NULL,
  @course_bsit, NULL, 3, '2026-2027', '1',
  1, '127.0.0.1'
),
(
  'pending', NULL, NULL, NULL,
  'Ramos', 'Diego', NULL, 1,
  'male', 'single', 'Filipino', '2005-04-12', 'Marikina',
  'diego.ramos@enroll.sample.edu', '09181234004',
  '130000000', 'Metro Manila (NCR)', '137402000', 'City of Marikina', '137402001', 'San Roque',
  'Marikina sample address', 'Lina Ramos', 'Carlo Ramos',
  'Lina Ramos', 'mother', '09190001016',
  'moving_up', '2026-0004', 'This institution (moving up)', NULL, NULL,
  @course_bsit, NULL, 3, '2026-2027', '1',
  1, '127.0.0.1'
),
(
  'pending', NULL, NULL, NULL,
  'Torres', 'Elena', NULL, 1,
  'female', 'single', 'Filipino', '2004-01-09', 'Taguig',
  'elena.torres@enroll.sample.edu', '09181234005',
  '130000000', 'Metro Manila (NCR)', '137607000', 'City of Taguig', '137607001', 'Fort Bonifacio',
  'Taguig sample address', 'Ruth Torres', 'Oscar Torres',
  'Ruth Torres', 'mother', '09190001014',
  'moving_up', '2026-0005', 'This institution (moving up)', NULL, NULL,
  @course_bsit, NULL, 4, '2026-2027', '1',
  1, '127.0.0.1'
),
(
  'pending', NULL, NULL, NULL,
  'Navarro', 'Felix', NULL, 1,
  'male', 'single', 'Filipino', '2004-08-18', 'Caloocan',
  'felix.navarro@enroll.sample.edu', '09181234006',
  '130000000', 'Metro Manila (NCR)', '137501000', 'City of Caloocan', '137501001', 'Barangay 1',
  'Caloocan sample address', 'Nora Navarro', 'Luis Navarro',
  'Nora Navarro', 'mother', '09190001017',
  'moving_up', '2026-0006', 'This institution (moving up)', NULL, NULL,
  @course_bsit, NULL, 4, '2026-2027', '1',
  1, '127.0.0.1'
),
(
  'rejected', @user_registrar, NOW(), 'Incomplete or unclear ID photo. Please re-apply with a clearer photo.',
  'Bautista', 'Irene', 'Joy', 0,
  'female', 'single', 'Filipino', '2007-09-30', 'Bulacan',
  'irene.bautista@enroll.sample.edu', '09190001005',
  '031400000', 'Bulacan', '031420000', 'City of Malolos', '031420001', 'Santo Rosario',
  '7 Capitol Road', 'Joy Bautista', 'Ramon Bautista',
  'Joy Bautista', 'mother', '09190001015',
  'new', NULL, 'Bulacan State University Laboratory High School', '2025', 'ABM',
  @course_bsit, NULL, 1, '2026-2027', '1',
  1, '127.0.0.1'
);

-- Document metadata (binary files attached by link-sample-enrollment-files.php)
INSERT INTO enrollment_documents (application_id, doc_type, original_name, stored_name, mime_type, byte_size)
SELECT ea.id, 'psa_birth', 'PSA-birth-certificate.pdf', 'psa_birth_sample.pdf', 'application/pdf', 345
FROM enrollment_applications ea
WHERE ea.email LIKE '%@enroll.sample.edu'
  AND NOT EXISTS (
    SELECT 1 FROM enrollment_documents d WHERE d.application_id = ea.id AND d.doc_type = 'psa_birth'
  );

INSERT INTO enrollment_documents (application_id, doc_type, original_name, stored_name, mime_type, byte_size)
SELECT ea.id, 'id_photo', 'ID-photo.png', 'id_photo_sample.png', 'image/png', 70
FROM enrollment_applications ea
WHERE ea.email LIKE '%@enroll.sample.edu'
  AND NOT EXISTS (
    SELECT 1 FROM enrollment_documents d WHERE d.application_id = ea.id AND d.doc_type = 'id_photo'
  );

SELECT 'Sample data SQL finished. Run: php database/link-sample-enrollment-files.php' AS next_step;
