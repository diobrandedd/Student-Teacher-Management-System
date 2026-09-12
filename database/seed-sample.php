<?php
declare(strict_types=1);
/**
 * Demo sample data (safe to re-run).
 * Preserves administrator and registrar accounts.
 * Rebuilds sample teachers, subjects, blocks, students, and enrollment applications.
 *
 * Scale:
 * - 5 teachers / college year (20)
 * - 5 subjects / college year (20) — codes 1xx / 2xx / 3xx / 4xx
 * - 5 blocks / college year (20), 5 students each (100)
 * - 5 new (1st-year) enrollment applicants
 * - 5 moving-up applicants per target year 2–4 (15)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = db();
$tempHash = hash_temp_password(demo_seed_password());
$ay = '2026-2027';
$sem = '1';

$firstNames = [
    'Ana', 'Ben', 'Carla', 'Diego', 'Elena', 'Felix', 'Grace', 'Hugo', 'Irene', 'Jake',
    'Kara', 'Luis', 'Mira', 'Nico', 'Olivia', 'Paolo', 'Quinn', 'Rosa', 'Sam', 'Tina',
    'Ulysses', 'Vera', 'Wade', 'Xena', 'Yuki', 'Zara', 'Arvin', 'Bianca', 'Carlo', 'Dana',
    'Ethan', 'Faye', 'Gino', 'Helen', 'Ivan', 'Julia', 'Kevin', 'Lara', 'Marco', 'Nina',
    'Oscar', 'Pearl', 'Ramon', 'Sofia', 'Troy', 'Uma', 'Vince', 'Wendy', 'Xander', 'Yana',
];
$lastNames = [
    'Reyes', 'Garcia', 'Lopez', 'Ramos', 'Torres', 'Navarro', 'Mendoza', 'Villanueva', 'Santos', 'Cruz',
    'Bautista', 'Dela Cruz', 'Gonzales', 'Fernandez', 'Rivera', 'Flores', 'Castro', 'Domingo', 'Aquino', 'Perez',
    'Santiago', 'Morales', 'Gutierrez', 'Romero', 'Vargas', 'Castillo', 'Jimenez', 'Aguilar', 'Medina', 'Silva',
    'Ortega', 'Herrera', 'Pascual', 'Valdez', 'Salazar', 'Padilla', 'Mercado', 'Cortez', 'Alonzo', 'Lim',
    'Tan', 'Chua', 'Sy', 'Go', 'Uy', 'Ong', 'Dy', 'Yap', 'Co', 'Ng',
];

$subjectPlans = [
    1 => [
        ['IT 101', 'Introduction to Computing'],
        ['IT 111', 'Computer Programming 1'],
        ['IT 121', 'Discrete Mathematics'],
        ['IT 131', 'Web Fundamentals'],
        ['IT 141', 'Digital Literacy'],
    ],
    2 => [
        ['IT 201', 'Data Structures'],
        ['IT 211', 'Computer Programming 2'],
        ['IT 221', 'Database Fundamentals'],
        ['IT 231', 'Object-Oriented Programming'],
        ['IT 241', 'Computer Networks 1'],
    ],
    3 => [
        ['IT 301', 'Systems Analysis and Design'],
        ['IT 311', 'Advanced Databases'],
        ['IT 321', 'Software Engineering'],
        ['IT 331', 'Human-Computer Interaction'],
        ['IT 341', 'Information Management'],
    ],
    4 => [
        ['IT 401', 'Capstone Project 1'],
        ['IT 411', 'Information Assurance'],
        ['IT 421', 'Information Security'],
        ['IT 431', 'IT Project Management'],
        ['IT 441', 'Emerging Technologies'],
    ],
];

$teacherLast = ['Santos', 'Reyes', 'Cruz', 'Garcia', 'Lopez', 'Torres', 'Ramos', 'Mendoza', 'Flores', 'Navarro', 'Castillo', 'Rivera', 'Aquino', 'Perez', 'Lim', 'Tan', 'Go', 'Sy', 'Ong', 'Uy'];
$teacherFirst = ['Maria', 'Jose', 'Ana', 'Juan', 'Liza', 'Carlo', 'Rosa', 'Miguel', 'Elena', 'Paolo', 'Nina', 'Mark', 'Sofia', 'David', 'Grace', 'Leo', 'Iris', 'Noel', 'Kate', 'Ryan'];

$pdo->beginTransaction();
try {
    // --- Clear prior sample graph (keep admins + registrars) ---
    $allAppIds = $pdo->query('SELECT id FROM enrollment_applications')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($allAppIds as $appId) {
        $dir = dirname(__DIR__) . '/storage/enrollment/' . (int)$appId;
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }
    $pdo->exec('DELETE FROM enrollment_documents');
    $pdo->exec('DELETE FROM enrollment_applications');
    $pdo->exec('DELETE FROM student_score_entries');
    $pdo->exec('DELETE FROM student_term_grades');
    $pdo->exec('DELETE FROM assignment_grade_submissions');
    $pdo->exec('DELETE FROM subject_score_items');
    $pdo->exec('DELETE FROM block_subject_enrollments');
    $pdo->exec('DELETE FROM block_subject_assignments');
    $pdo->exec('DELETE FROM block_students');
    $pdo->exec('DELETE FROM blocks');
    $pdo->exec('DELETE FROM students');
    $pdo->exec('DELETE FROM subjects');

    $sampleStaffIds = $pdo->query(
        "SELECT id FROM users WHERE role='staff'"
    )->fetchAll(PDO::FETCH_COLUMN);
    if ($sampleStaffIds) {
        $in = implode(',', array_map('intval', $sampleStaffIds));
        $pdo->exec("DELETE FROM teachers WHERE user_id IN ($in)");
        $pdo->exec("DELETE FROM users WHERE id IN ($in)");
    }

    $pdo->exec('UPDATE teachers SET department_id=NULL');
    $pdo->exec('DELETE FROM departments');
    $pdo->exec('DELETE FROM courses');

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
    $departmentId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO courses (name) VALUES (?)')->execute(['Bachelor of Science in Information Technology']);
    $courseId = (int)$pdo->lastInsertId();
    $courseName = 'Bachelor of Science in Information Technology';
    $pdo->prepare('INSERT INTO courses (name) VALUES (?)')->execute(['Bachelor of Science in Computer Science']);
    $courseIdCs = (int)$pdo->lastInsertId();

    // --- Subjects (5 per year) ---
    $subjectIdsByYear = [];
    $insertSubject = $pdo->prepare('INSERT INTO subjects (code, title) VALUES (?,?)');
    foreach ($subjectPlans as $year => $rows) {
        foreach ($rows as [$code, $title]) {
            $insertSubject->execute([$code, $title]);
            $subjectIdsByYear[$year][] = [
                'id' => (int)$pdo->lastInsertId(),
                'code' => $code,
                'title' => $title,
            ];
        }
    }

    // --- Teachers (5 per year) ---
    $teacherIdsByYear = [];
    $insertUser = $pdo->prepare(
        'INSERT INTO users (full_name, username, email, password_hash, role, must_change_password) VALUES (?,?,?,?,\'staff\',1)'
    );
    $insertTeacher = $pdo->prepare(
        'INSERT INTO teachers (user_id, first_name, middle_name, last_name, phone, department_id) VALUES (?,?,?,?,?,?)'
    );
    $ti = 0;
    for ($year = 1; $year <= 4; $year++) {
        for ($n = 1; $n <= 5; $n++) {
            $first = $teacherFirst[$ti];
            $last = $teacherLast[$ti];
            $full = $first . ' ' . $last;
            $username = 't' . $year . 'n' . $n;
            $email = strtolower($username) . '@teacher.sample.edu';
            $insertUser->execute([$full, $username, $email, $tempHash]);
            $userId = (int)$pdo->lastInsertId();
            $insertTeacher->execute([$userId, $first, null, $last, sprintf('0917%07d', 1000000 + $ti), $departmentId]);
            $teacherIdsByYear[$year][] = (int)$pdo->lastInsertId();
            $ti++;
        }
    }

    // --- Students + blocks (5 blocks/year × 5 students) ---
    $insertStudent = $pdo->prepare(
        'INSERT INTO students (
            student_number, first_name, last_name, email, phone, address,
            course, course_id, year_level, academic_status, username, password_hash, must_change_password
        ) VALUES (?,?,?,?,?,?,?,?,?,\'Active\',?,?,1)'
    );
    $insertBlock = $pdo->prepare('INSERT INTO blocks (name, year_level) VALUES (?,?)');
    $linkStudent = $pdo->prepare('INSERT INTO block_students (block_id, student_id) VALUES (?,?)');
    $insertAssignment = $pdo->prepare(
        'INSERT INTO block_subject_assignments (block_id, teacher_id, subject_id, subject_code, subject_name) VALUES (?,?,?,?,?)'
    );
    $insertEnrollment = $pdo->prepare(
        'INSERT INTO block_subject_enrollments (
            assignment_id, student_id, block_id, teacher_id, subject_code, subject_name, synced_at
        ) VALUES (?,?,?,?,?,?,NOW())'
    );

    $studentSeq = 0;
    $studentsByYear = [1 => [], 2 => [], 3 => [], 4 => []];
    $blockIdsByYear = [1 => [], 2 => [], 3 => [], 4 => []];

    for ($year = 1; $year <= 4; $year++) {
        for ($b = 1; $b <= 5; $b++) {
            $insertBlock->execute(['Block ' . $b, $year]);
            $blockId = (int)$pdo->lastInsertId();
            $blockIdsByYear[$year][$b] = $blockId;
            $memberIds = [];
            for ($s = 1; $s <= 5; $s++) {
                $studentSeq++;
                $idx = ($studentSeq - 1) % count($firstNames);
                $first = $firstNames[$idx];
                $last = $lastNames[($studentSeq - 1) % count($lastNames)];
                // Disambiguate repeated names across the 100 students.
                if ($studentSeq > count($firstNames)) {
                    $first .= $s;
                }
                $number = sprintf('2026-%04d', $studentSeq);
                $email = strtolower(preg_replace('/[^a-z0-9]+/i', '', $first . '.' . $last)) . $studentSeq . '@student.sample.edu';
                $username = allocate_login_username($last, $first);
                $insertStudent->execute([
                    $number,
                    $first,
                    $last,
                    $email,
                    sprintf('0918%07d', 2000000 + $studentSeq),
                    'Sample address ' . $studentSeq . ', Metro Manila',
                    $courseName,
                    $courseId,
                    $year,
                    $username,
                    $tempHash,
                ]);
                $studentId = (int)$pdo->lastInsertId();
                $linkStudent->execute([$blockId, $studentId]);
                $memberIds[] = $studentId;
                $studentsByYear[$year][] = [
                    'id' => $studentId,
                    'number' => $number,
                    'first' => $first,
                    'last' => $last,
                    'email' => $email,
                    'mobile' => sprintf('0918%07d', 2000000 + $studentSeq),
                    'block_id' => $blockId,
                ];
            }

            // Each year-subject taught by the matching year-teacher on every block of that year.
            for ($i = 0; $i < 5; $i++) {
                $subject = $subjectIdsByYear[$year][$i];
                $teacherId = $teacherIdsByYear[$year][$i];
                $insertAssignment->execute([
                    $blockId,
                    $teacherId,
                    $subject['id'],
                    $subject['code'],
                    $subject['title'],
                ]);
                $assignmentId = (int)$pdo->lastInsertId();
                foreach ($memberIds as $studentId) {
                    $insertEnrollment->execute([
                        $assignmentId,
                        $studentId,
                        $blockId,
                        $teacherId,
                        $subject['code'],
                        $subject['title'],
                    ]);
                }
                // Sample score items on year-4 IT 421 in Block 1 only.
                if ($year === 4 && $subject['code'] === 'IT 421' && $b === 1) {
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
        }
    }

    // --- Enrollment applications ---
    $assetDir = __DIR__ . '/sample-assets/enrollment';
    $psaBytes = is_file($assetDir . '/sample-psa.pdf') ? file_get_contents($assetDir . '/sample-psa.pdf') : false;
    $idBytes = is_file($assetDir . '/sample-id.png') ? file_get_contents($assetDir . '/sample-id.png') : false;
    if ($psaBytes === false || $idBytes === false) {
        throw new RuntimeException('Missing database/sample-assets/enrollment files. Run php database/write-sample-enrollment-assets.php first.');
    }

    $addr = [
        'province_code' => '130000000', 'province_name' => 'Metro Manila (NCR)',
        'city_code' => '137404000', 'city_name' => 'Quezon City',
        'barangay_code' => '137404001', 'barangay_name' => 'Diliman',
    ];

    $enrollmentApps = [];
    $newApplicantNames = [
        ['Santos', 'Patricia', 'Cruz'],
        ['Villanueva', 'Marco', null],
        ['Bautista', 'Irene', 'Joy'],
        ['Castillo', 'Noah', null],
        ['Mercado', 'Alya', 'Mae'],
    ];
    foreach ($newApplicantNames as $i => [$last, $first, $middle]) {
        $enrollmentApps[] = [
            'status' => 'pending',
            'type' => 'new',
            'student_number' => null,
            'last' => $last,
            'first' => $first,
            'middle' => $middle,
            'email' => strtolower($first . '.' . $last) . ($i + 1) . '@enroll.sample.edu',
            'mobile' => sprintf('0919%07d', 1001000 + $i),
            'dob' => sprintf('2007-%02d-%02d', ($i % 12) + 1, ($i % 27) + 1),
            'pob' => 'Quezon City',
            'sex' => $i % 2 === 0 ? 'female' : 'male',
            'civil' => 'single',
            'address' => ($i + 1) . ' Sample Street, Quezon City',
            'mother' => 'Mother ' . $last,
            'father' => 'Father ' . $last,
            'guardian' => 'Mother ' . $last,
            'grel' => 'mother',
            'gnum' => sprintf('0919%07d', 1002000 + $i),
            'last_school' => 'Sample Senior High School',
            'year_grad' => '2025',
            'strand' => 'STEM',
            'course_id' => $courseId,
            'course2' => $i === 0 ? $courseIdCs : null,
            'year' => 1,
            'ay' => $ay,
            'sem' => $sem,
            'reject' => null,
            'reviewed_by' => null,
        ] + $addr;
    }

    // 5 moving-up apps into each of years 2, 3, 4 (from current year-1/2/3 students).
    foreach ([2 => 1, 3 => 2, 4 => 3] as $toYear => $fromYear) {
        $pool = array_slice($studentsByYear[$fromYear], 0, 5);
        foreach ($pool as $i => $student) {
            $enrollmentApps[] = [
                'status' => 'pending',
                'type' => 'moving_up',
                'student_number' => $student['number'],
                'last' => $student['last'],
                'first' => $student['first'],
                'middle' => null,
                'email' => 'move' . $toYear . '.' . ($i + 1) . '@enroll.sample.edu',
                'mobile' => $student['mobile'],
                'dob' => sprintf('200%d-%02d-%02d', 4 + $fromYear, ($i % 12) + 1, ($i % 27) + 1),
                'pob' => 'Metro Manila',
                'sex' => $i % 2 === 0 ? 'female' : 'male',
                'civil' => 'single',
                'address' => 'Campus residence ' . $student['number'],
                'mother' => 'Mother ' . $student['last'],
                'father' => 'Father ' . $student['last'],
                'guardian' => 'Mother ' . $student['last'],
                'grel' => 'mother',
                'gnum' => sprintf('0919%07d', 1003000 + $toYear * 10 + $i),
                'last_school' => 'This institution (moving up)',
                'year_grad' => null,
                'strand' => null,
                'course_id' => $courseId,
                'course2' => null,
                'year' => $toYear,
                'ay' => $ay,
                'sem' => $sem,
                'reject' => null,
                'reviewed_by' => null,
            ] + $addr;
        }
    }

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
        $appId = (int)$pdo->lastInsertId();
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

$counts = [
    'teachers' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='staff' AND email LIKE '%@teacher.sample.edu'")->fetchColumn(),
    'subjects' => (int)$pdo->query('SELECT COUNT(*) FROM subjects')->fetchColumn(),
    'blocks' => (int)$pdo->query('SELECT COUNT(*) FROM blocks')->fetchColumn(),
    'students' => (int)$pdo->query("SELECT COUNT(*) FROM students WHERE student_number LIKE '2026-%'")->fetchColumn(),
    'new_apps' => (int)$pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE application_type='new' AND email LIKE '%@enroll.sample.edu'")->fetchColumn(),
    'move_apps' => (int)$pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE application_type='moving_up' AND email LIKE '%@enroll.sample.edu'")->fetchColumn(),
];

echo "Sample data loaded (admins + registrars preserved).\n";
echo "Teachers: {$counts['teachers']} (5/year, usernames t1n1…t4n5, temp " . demo_seed_password() . ")\n";
echo "Subjects: {$counts['subjects']} (IT 1xx / 2xx / 3xx / 4xx)\n";
echo "Blocks: {$counts['blocks']} (Block 1–5 × years 1–4)\n";
echo "Students: {$counts['students']} (5 per block, IDs 2026-0001…0100, temp " . demo_seed_password() . ")\n";
echo "Enrollments: {$counts['new_apps']} new (1st year) + {$counts['move_apps']} moving up (5 into each of years 2–4)\n";
echo "Registrars/admins left unchanged.\n";
