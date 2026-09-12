<?php
declare(strict_types=1);
/**
 * Align sample students + moving-up enrollments:
 * - Students spread across years 1–4 (so they can advance)
 * - Pending Moving up apps: 1st→2nd, 2nd→3rd, and 3rd→4th with real Student IDs
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/enrollment.php';

$courseId = (int)db()->query(
    "SELECT id FROM courses WHERE name='Bachelor of Science in Information Technology' LIMIT 1"
)->fetchColumn();
if ($courseId < 1) {
    fwrite(STDERR, "BSIT course missing\n");
    exit(1);
}

// Year spread for demo students
$years = [
    '2026-0001' => 1, // Reyes — move up to 2nd
    '2026-0002' => 1, // Garcia — move up to 2nd
    '2026-0003' => 2, // Lopez — move up to 3rd
    '2026-0004' => 2, // Ramos — move up to 3rd
    '2026-0005' => 3, // Torres — move up to 4th
    '2026-0006' => 3, // Navarro — move up to 4th
    '2026-0007' => 4,
    '2026-0008' => 4,
];
$updYear = db()->prepare('UPDATE students SET year_level=? WHERE student_number=?');
foreach ($years as $num => $year) {
    $updYear->execute([$year, $num]);
    echo "student $num => year $year\n";
}

// Remove bogus rejected batch moving-up rows (no student ID)
db()->exec(
    "DELETE FROM enrollment_applications
     WHERE email LIKE '%@enroll.batch.local'
        OR (application_type='moving_up' AND status='rejected' AND (student_number IS NULL OR TRIM(student_number)=''))"
);
echo "cleaned bogus moving-up / batch apps\n";

$samples = [
    [
        'from_year' => 1,
        'to_year' => 2,
        'student_number' => '2026-0001',
        'email' => 'ana.reyes@enroll.sample.edu',
    ],
    [
        'from_year' => 1,
        'to_year' => 2,
        'student_number' => '2026-0002',
        'email' => 'ben.garcia@enroll.sample.edu',
    ],
    [
        'from_year' => 2,
        'to_year' => 3,
        'student_number' => '2026-0003',
        'email' => 'carla.lopez@enroll.sample.edu',
    ],
    [
        'from_year' => 2,
        'to_year' => 3,
        'student_number' => '2026-0004',
        'email' => 'diego.ramos@enroll.sample.edu',
    ],
    [
        'from_year' => 3,
        'to_year' => 4,
        'student_number' => '2026-0005',
        'email' => 'elena.torres@enroll.sample.edu',
    ],
    [
        'from_year' => 3,
        'to_year' => 4,
        'student_number' => '2026-0006',
        'email' => 'felix.navarro@enroll.sample.edu',
    ],
];

foreach ($samples as $sample) {
    $stu = db()->prepare('SELECT * FROM students WHERE student_number=? LIMIT 1');
    $stu->execute([$sample['student_number']]);
    $s = $stu->fetch();
    if (!$s) {
        echo "skip missing student {$sample['student_number']}\n";
        continue;
    }

    // One pending moving-up per student
    db()->prepare(
        "DELETE FROM enrollment_applications WHERE student_number=? AND application_type='moving_up'"
    )->execute([$sample['student_number']]);
    db()->prepare(
        "DELETE FROM enrollment_applications WHERE email=? AND application_type='moving_up'"
    )->execute([$sample['email']]);

    db()->prepare(
        'INSERT INTO enrollment_applications (
            status, last_name, first_name, middle_name, no_middle_name,
            sex, civil_status, citizenship, religion, date_of_birth, place_of_birth,
            email, mobile,
            province_code, province_name, city_code, city_name, barangay_code, barangay_name,
            address_line1, mother_maiden_name, father_name,
            guardian_name, guardian_relationship, guardian_number,
            emergency_name, emergency_relationship, emergency_number,
            application_type, student_number, last_school,
            course_id, year_level, academic_year, semester,
            privacy_consent, ip_address
        ) VALUES (
            \'pending\', ?, ?, NULL, 1,
            ?, \'single\', \'Filipino\', \'Roman Catholic\', ?, \'Metro Manila\',
            ?, ?,
            \'130000000\', \'Metro Manila (NCR)\', \'137404000\', \'Quezon City\', \'137404001\', \'Diliman\',
            ?, \'Sample Mother\', \'Sample Father\',
            \'Sample Mother\', \'mother\', \'09190009999\',
            \'Sample Mother\', \'Mother\', \'09190009999\',
            \'moving_up\', ?, \'This institution (moving up)\',
            ?, ?, \'2026-2027\', \'1\',
            1, \'127.0.0.1\'
        )'
    )->execute([
        $s['last_name'],
        $s['first_name'],
        str_contains(strtolower((string)$s['first_name']), 'a') ? 'female' : 'male',
        '2005-06-15',
        $sample['email'],
        $s['phone'] ?: '09181234999',
        $s['address'] ?: 'Sample campus address',
        $sample['student_number'],
        $courseId,
        $sample['to_year'],
    ]);
    $appId = (int)db()->lastInsertId();

    // Attach sample docs if available
    $assetDir = __DIR__ . '/sample-assets/enrollment';
    $psa = $assetDir . '/sample-psa.pdf';
    $idPhoto = $assetDir . '/sample-id.png';
    if (is_file($psa) && is_file($idPhoto)) {
        $dir = enrollment_storage_root() . '/' . $appId;
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $insertDoc = db()->prepare(
            'INSERT INTO enrollment_documents (application_id, doc_type, original_name, stored_name, mime_type, byte_size)
             VALUES (?,?,?,?,?,?)'
        );
        foreach (
            [
                'psa_birth' => [$psa, 'psa-birth.pdf', 'application/pdf', 'pdf'],
                'id_photo' => [$idPhoto, 'id-photo.png', 'image/png', 'png'],
            ] as $type => [$src, $orig, $mime, $ext]
        ) {
            $stored = $type . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            copy($src, $dir . '/' . $stored);
            $insertDoc->execute([$appId, $type, $orig, $stored, $mime, (int)filesize($dir . '/' . $stored)]);
        }
    }

    audit('CREATE', 'enrollment_application', $appId, 'Sample moving-up y' . $sample['from_year'] . '→y' . $sample['to_year']);
    echo "moving_up #{$appId}: {$s['last_name']}, {$s['first_name']} ({$sample['student_number']}) y{$sample['from_year']}→y{$sample['to_year']}\n";
}

echo "done\n";
