<?php
declare(strict_types=1);
/**
 * Light sample data for local demos.
 * Safe to re-run: clears prior sample rows (keeps real admins like nolifin).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = db();
$tempHash = hash_temp_password(demo_seed_password());

$pdo->beginTransaction();
try {
    // Remove prior sample graph (order respects FKs).
    $pdo->exec("DELETE ed FROM enrollment_documents ed
        INNER JOIN enrollment_applications ea ON ea.id=ed.application_id
        WHERE ea.email LIKE '%@enroll.sample.edu'");
    $sampleAppIds = $pdo->query("SELECT id FROM enrollment_applications WHERE email LIKE '%@enroll.sample.edu'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($sampleAppIds as $appId) {
        $dir = dirname(__DIR__) . '/storage/enrollment/' . (int)$appId;
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }
    $pdo->exec("DELETE FROM enrollment_applications WHERE email LIKE '%@enroll.sample.edu'");
    $pdo->exec("DELETE FROM student_score_entries");
    $pdo->exec("DELETE FROM student_term_grades");
    $pdo->exec("DELETE FROM assignment_grade_submissions");
    $pdo->exec("DELETE FROM subject_score_items");
    $pdo->exec("DELETE FROM block_subject_enrollments");
    $pdo->exec("DELETE FROM block_subject_assignments");
    $pdo->exec("DELETE FROM block_students");
    $pdo->exec("DELETE FROM blocks WHERE name IN ('BSIT 4-A','Block 1','Block 2')");
    $pdo->exec("DELETE FROM students WHERE student_number LIKE '2024-S%' OR student_number LIKE '2026-%' OR email LIKE '%@enroll.sample.edu'");
    $pdo->exec("DELETE FROM teachers WHERE user_id IN (SELECT id FROM users WHERE username IN ('msantos','jdelacruz','Delacruz_J','rgarcia') OR email IN ('maria.santos@example.edu','juan.delacruz@example.edu','rosa.garcia@example.edu'))");
    $pdo->exec("DELETE FROM users WHERE username IN ('msantos','jdelacruz','Delacruz_J','rgarcia') OR email IN ('maria.santos@example.edu','juan.delacruz@example.edu','rosa.garcia@example.edu')");
    $pdo->exec("UPDATE teachers SET department_id=NULL WHERE department_id IN (SELECT id FROM (SELECT id FROM departments WHERE name = 'Information Technology') AS d)");
    $pdo->exec("DELETE FROM departments WHERE name = 'Information Technology'");
    $pdo->exec("DELETE FROM courses WHERE name IN ('Bachelor of Science in Information Technology','Bachelor of Science in Computer Science')");

    foreach ([
        'grade_weight_quiz' => '20',
        'grade_weight_activities' => '20',
        'grade_weight_attendance' => '10',
        'grade_weight_projects' => '20',
        'grade_weight_exam' => '30',
        'grade_weight_midterm' => '40',
        'grade_weight_final' => '60',
    ] as $key => $value) {
        $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key, $value]);
    }

    $pdo->prepare('INSERT INTO departments (name) VALUES (?)')->execute(['Information Technology']);
    $departmentId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO courses (name) VALUES (?)')->execute(['Bachelor of Science in Information Technology']);
    $courseId = (int) $pdo->lastInsertId();
    $courseName = 'Bachelor of Science in Information Technology';
    $pdo->prepare('INSERT INTO courses (name) VALUES (?)')->execute(['Bachelor of Science in Computer Science']);
    $courseIdCs = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO users (full_name, username, email, password_hash, role, must_change_password) VALUES (?,?,?,?,\'registrar\',1)'
    )->execute(['Rosa Garcia', 'rgarcia', 'rosa.garcia@example.edu', $tempHash]);
    $registrarId = (int) $pdo->lastInsertId();

    $teachers = [
        [
            'full_name' => 'Maria Santos',
            'username' => 'msantos',
            'email' => 'maria.santos@example.edu',
            'first_name' => 'Maria',
            'middle_name' => null,
            'last_name' => 'Santos',
            'phone' => '09171234501',
        ],
        [
            'full_name' => 'Juan Dela Cruz',
            'username' => 'jdelacruz',
            'email' => 'juan.delacruz@example.edu',
            'first_name' => 'Juan',
            'middle_name' => null,
            'last_name' => 'Dela Cruz',
            'phone' => '09171234502',
        ],
    ];

    $teacherIds = [];
    $insertUser = $pdo->prepare(
        'INSERT INTO users (full_name, username, email, password_hash, role, must_change_password) VALUES (?,?,?,?,\'staff\',1)'
    );
    $insertTeacher = $pdo->prepare(
        'INSERT INTO teachers (user_id, first_name, middle_name, last_name, phone, department_id) VALUES (?,?,?,?,?,?)'
    );
    foreach ($teachers as $teacher) {
        $insertUser->execute([$teacher['full_name'], $teacher['username'], $teacher['email'], $tempHash]);
        $userId = (int) $pdo->lastInsertId();
        $insertTeacher->execute([
            $userId,
            $teacher['first_name'],
            $teacher['middle_name'],
            $teacher['last_name'],
            $teacher['phone'],
            $departmentId,
        ]);
        $teacherIds[$teacher['username']] = (int) $pdo->lastInsertId();
    }

    $students = [
        ['2026-0001', 'Ana', 'Reyes', 'ana.reyes@student.example.edu', '09181234001', 'Quezon City', 4],
        ['2026-0002', 'Ben', 'Garcia', 'ben.garcia@student.example.edu', '09181234002', 'Makati', 4],
        ['2026-0003', 'Carla', 'Lopez', 'carla.lopez@student.example.edu', '09181234003', 'Pasig', 4],
        ['2026-0004', 'Diego', 'Ramos', 'diego.ramos@student.example.edu', '09181234004', 'Marikina', 4],
        ['2026-0005', 'Elena', 'Torres', 'elena.torres@student.example.edu', '09181234005', 'Taguig', 4],
        ['2026-0006', 'Felix', 'Navarro', 'felix.navarro@student.example.edu', '09181234006', 'Caloocan', 4],
        ['2026-0007', 'Grace', 'Mendoza', 'grace.mendoza@student.example.edu', '09181234007', 'Manila', 4],
        ['2026-0008', 'Hugo', 'Villanueva', 'hugo.villanueva@student.example.edu', '09181234008', 'Parañaque', 4],
    ];

    $insertStudent = $pdo->prepare(
        'INSERT INTO students (
            student_number, first_name, last_name, email, phone, address,
            course, course_id, year_level, academic_status, username, password_hash, must_change_password
        ) VALUES (?,?,?,?,?,?,?,?,?,\'Active\',?,?,1)'
    );
    $studentIds = [];
    foreach ($students as $row) {
        [$number, $first, $last, $email, $phone, $address, $year] = $row;
        $username = allocate_login_username($last, $first);
        $insertStudent->execute([
            $number,
            $first,
            $last,
            $email,
            $phone,
            $address,
            $courseName,
            $courseId,
            $year,
            $username,
            $tempHash,
        ]);
        $studentIds[] = (int) $pdo->lastInsertId();
    }

    $blockPlans = [
        [
            'name' => 'Block 1',
            'students' => array_slice($studentIds, 0, 4),
            'subject' => ['IT 421', 'Information Security', $teacherIds['msantos']],
        ],
        [
            'name' => 'Block 2',
            'students' => array_slice($studentIds, 4, 4),
            'subject' => ['IT 411', 'Systems Analysis and Design', $teacherIds['jdelacruz']],
        ],
    ];

    $insertBlock = $pdo->prepare('INSERT INTO blocks (name) VALUES (?)');
    $linkStudent = $pdo->prepare('INSERT INTO block_students (block_id, student_id) VALUES (?,?)');
    $insertAssignment = $pdo->prepare(
        'INSERT INTO block_subject_assignments (block_id, teacher_id, subject_code, subject_name) VALUES (?,?,?,?)'
    );
    $insertEnrollment = $pdo->prepare(
        'INSERT INTO block_subject_enrollments (
            assignment_id, student_id, block_id, teacher_id, subject_code, subject_name, synced_at
        ) VALUES (?,?,?,?,?,?,NOW())'
    );

    foreach ($blockPlans as $plan) {
        $insertBlock->execute([$plan['name']]);
        $blockId = (int) $pdo->lastInsertId();
        foreach ($plan['students'] as $studentId) {
            $linkStudent->execute([$blockId, $studentId]);
        }
        [$code, $name, $teacherId] = $plan['subject'];
        $insertAssignment->execute([$blockId, $teacherId, $code, $name]);
        $assignmentId = (int) $pdo->lastInsertId();
        foreach ($plan['students'] as $studentId) {
            $insertEnrollment->execute([$assignmentId, $studentId, $blockId, $teacherId, $code, $name]);
        }
        if ($code === 'IT 421') {
            $insertItem = $pdo->prepare(
                'INSERT INTO subject_score_items (assignment_id, term, category, title, max_score, sort_order) VALUES (?,?,?,?,?,?)'
            );
            $insertItem->execute([$assignmentId, 'midterm', 'quiz', 'Quiz 1', 50, 1]);
            $insertItem->execute([$assignmentId, 'midterm', 'quiz', 'Quiz 2', 50, 2]);
            $insertItem->execute([$assignmentId, 'midterm', 'activities', 'Lab Activity 1', 100, 1]);
            $insertItem->execute([$assignmentId, 'midterm', 'attendance', 'Midterm attendance', 100, 1]);
            $insertItem->execute([$assignmentId, 'midterm', 'projects', 'Project draft', 100, 1]);
            $insertItem->execute([$assignmentId, 'midterm', 'exam', 'Midterm exam', 100, 1]);
        }
    }

    $assetDir = __DIR__ . '/sample-assets/enrollment';
    $psaBytes = is_file($assetDir . '/sample-psa.pdf') ? file_get_contents($assetDir . '/sample-psa.pdf') : false;
    $idBytes = is_file($assetDir . '/sample-id.png') ? file_get_contents($assetDir . '/sample-id.png') : false;
    if ($psaBytes === false || $idBytes === false) {
        throw new RuntimeException('Missing database/sample-assets/enrollment files. Run php database/write-sample-enrollment-assets.php first.');
    }

    $enrollmentApps = [
        [
            'status' => 'pending',
            'type' => 'new',
            'last' => 'Santos', 'first' => 'Patricia', 'middle' => 'Cruz',
            'email' => 'patricia.santos@enroll.sample.edu', 'mobile' => '09190001001',
            'dob' => '2007-03-14', 'pob' => 'Cavite City',
            'sex' => 'female', 'civil' => 'single',
            'province_code' => '042100000', 'province_name' => 'Cavite',
            'city_code' => '042108000', 'city_name' => 'City of Bacoor',
            'barangay_code' => '042108001', 'barangay_name' => 'Molino I',
            'address' => '123 Sample Street',
            'mother' => 'Lorna Cruz Santos', 'father' => 'Roberto Santos',
            'guardian' => 'Lorna Cruz Santos', 'grel' => 'mother', 'gnum' => '09190001011',
            'last_school' => 'Cavite National Science High School', 'year_grad' => '2025', 'strand' => 'STEM',
            'course_id' => $courseId, 'course2' => $courseIdCs, 'year' => 1,
            'ay' => '2026-2027', 'sem' => '1',
            'student_number' => null,
            'reject' => null, 'reviewed_by' => null,
        ],
        [
            'status' => 'pending',
            'type' => 'new',
            'last' => 'Villanueva', 'first' => 'Marco', 'middle' => null,
            'email' => 'marco.villanueva@enroll.sample.edu', 'mobile' => '09190001002',
            'dob' => '2006-11-02', 'pob' => 'Quezon City',
            'sex' => 'male', 'civil' => 'single',
            'province_code' => '130000000', 'province_name' => 'Metro Manila (NCR)',
            'city_code' => '137404000', 'city_name' => 'Quezon City',
            'barangay_code' => '137404001', 'barangay_name' => 'Commonwealth',
            'address' => '45 Commonwealth Ave',
            'mother' => 'Ana Villanueva', 'father' => 'Pedro Villanueva',
            'guardian' => 'Pedro Villanueva', 'grel' => 'father', 'gnum' => '09190001012',
            'last_school' => 'Quezon City Science High School', 'year_grad' => '2024', 'strand' => 'STEM',
            'course_id' => $courseId, 'course2' => null, 'year' => 1,
            'ay' => '2026-2027', 'sem' => '1',
            'student_number' => null,
            'reject' => null, 'reviewed_by' => null,
        ],
        [
            'status' => 'pending',
            'type' => 'moving_up',
            'student_number' => '2026-0003',
            'last' => 'Lopez', 'first' => 'Carla', 'middle' => null,
            'email' => 'carla.lopez@enroll.sample.edu', 'mobile' => '09181234003',
            'dob' => '2005-07-21', 'pob' => 'Pasig',
            'sex' => 'female', 'civil' => 'single',
            'province_code' => '130000000', 'province_name' => 'Metro Manila (NCR)',
            'city_code' => '137401000', 'city_name' => 'City of Pasig',
            'barangay_code' => '137401001', 'barangay_name' => 'Kapitolyo',
            'address' => 'Pasig sample address',
            'mother' => 'Maria Lopez', 'father' => 'Jose Lopez',
            'guardian' => 'Maria Lopez', 'grel' => 'mother', 'gnum' => '09190001013',
            'last_school' => 'This institution (moving up)', 'year_grad' => null, 'strand' => null,
            'course_id' => $courseId, 'course2' => null, 'year' => 2,
            'ay' => '2026-2027', 'sem' => '1',
            'reject' => null, 'reviewed_by' => null,
        ],
        [
            'status' => 'pending',
            'type' => 'moving_up',
            'student_number' => '2026-0005',
            'last' => 'Torres', 'first' => 'Elena', 'middle' => null,
            'email' => 'elena.torres@enroll.sample.edu', 'mobile' => '09181234005',
            'dob' => '2004-01-09', 'pob' => 'Taguig',
            'sex' => 'female', 'civil' => 'single',
            'province_code' => '130000000', 'province_name' => 'Metro Manila (NCR)',
            'city_code' => '137607000', 'city_name' => 'City of Taguig',
            'barangay_code' => '137607001', 'barangay_name' => 'Fort Bonifacio',
            'address' => 'Taguig sample address',
            'mother' => 'Ruth Torres', 'father' => 'Oscar Torres',
            'guardian' => 'Ruth Torres', 'grel' => 'mother', 'gnum' => '09190001014',
            'last_school' => 'This institution (moving up)', 'year_grad' => null, 'strand' => null,
            'course_id' => $courseId, 'course2' => null, 'year' => 3,
            'ay' => '2026-2027', 'sem' => '1',
            'reject' => null, 'reviewed_by' => null,
        ],
        [
            'status' => 'rejected',
            'type' => 'new',
            'student_number' => null,
            'last' => 'Bautista', 'first' => 'Irene', 'middle' => 'Joy',
            'email' => 'irene.bautista@enroll.sample.edu', 'mobile' => '09190001005',
            'dob' => '2007-09-30', 'pob' => 'Bulacan',
            'sex' => 'female', 'civil' => 'single',
            'province_code' => '031400000', 'province_name' => 'Bulacan',
            'city_code' => '031420000', 'city_name' => 'City of Malolos',
            'barangay_code' => '031420001', 'barangay_name' => 'Santo Rosario',
            'address' => '7 Capitol Road',
            'mother' => 'Joy Bautista', 'father' => 'Ramon Bautista',
            'guardian' => 'Joy Bautista', 'grel' => 'mother', 'gnum' => '09190001015',
            'last_school' => 'Bulacan State University Laboratory High School', 'year_grad' => '2025', 'strand' => 'ABM',
            'course_id' => $courseId, 'course2' => null, 'year' => 1,
            'ay' => '2026-2027', 'sem' => '1',
            'reject' => 'Incomplete or unclear ID photo. Please re-apply with a clearer photo.',
            'reviewed_by' => $registrarId,
        ],
    ];

    $insertApp = $pdo->prepare(
        'INSERT INTO enrollment_applications (
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
        ) VALUES (
            ?, ?, IF(? IS NULL, NULL, NOW()), ?,
            ?, ?, ?, ?,
            ?, ?, \'Filipino\', ?, ?,
            ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            1, \'127.0.0.1\'
        )'
    );
    $insertDoc = $pdo->prepare(
        'INSERT INTO enrollment_documents (application_id, doc_type, original_name, stored_name, mime_type, byte_size)
         VALUES (?,?,?,?,?,?)'
    );

    foreach ($enrollmentApps as $app) {
        $noMiddle = $app['middle'] === null ? 1 : 0;
        $insertApp->execute([
            $app['status'],
            $app['reviewed_by'],
            $app['reviewed_by'],
            $app['reject'],
            $app['last'], $app['first'], $app['middle'], $noMiddle,
            $app['sex'], $app['civil'], $app['dob'], $app['pob'],
            $app['email'], $app['mobile'],
            $app['province_code'], $app['province_name'], $app['city_code'], $app['city_name'],
            $app['barangay_code'], $app['barangay_name'],
            $app['address'], $app['mother'], $app['father'],
            $app['guardian'], $app['grel'], $app['gnum'],
            $app['type'], $app['student_number'] ?? null, $app['last_school'], $app['year_grad'], $app['strand'],
            $app['course_id'], $app['course2'], $app['year'], $app['ay'], $app['sem'],
        ]);
        $appId = (int) $pdo->lastInsertId();
        $storageDir = dirname(__DIR__) . '/storage/enrollment/' . $appId;
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }
        $psaName = 'psa_birth_sample.pdf';
        $idName = 'id_photo_sample.png';
        file_put_contents($storageDir . '/' . $psaName, $psaBytes);
        file_put_contents($storageDir . '/' . $idName, $idBytes);
        $insertDoc->execute([$appId, 'psa_birth', 'PSA-birth-certificate.pdf', $psaName, 'application/pdf', strlen($psaBytes)]);
        $insertDoc->execute([$appId, 'id_photo', 'ID-photo.png', $idName, 'image/png', strlen($idBytes)]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Sample data loaded.\n";
echo "Department: Information Technology\n";
echo "Courses: BSIT, BSCS\n";
echo "Teachers: msantos, jdelacruz (temp password DemoTemp1234)\n";
echo "Registrar: rgarcia (temp password DemoTemp1234)\n";
echo "Blocks: Block 1 (IT 421 Information Security), Block 2 (IT 411 Systems Analysis and Design)\n";
echo "Students: 2026-0001 … 2026-0008 (temp password DemoTemp1234)\n";
echo "Enrollments: 5 samples — 2 new pending (ID assigned on approve), 2 moving-up pending (2026-0003, 2026-0005), 1 rejected\n";
echo "Grading: default weights seeded; IT 421 has sample midterm score items\n";
