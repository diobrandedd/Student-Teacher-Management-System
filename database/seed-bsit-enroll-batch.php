<?php
declare(strict_types=1);
/**
 * One-off: seed pending BSIT enrollment apps.
 * Year 1 = New student; years 2–4 = Moving up (requires matching Student IDs when approving).
 * CLI only.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/enrollment.php';

$assetDir = __DIR__ . '/sample-assets/enrollment';
if (!is_file($assetDir . '/sample-psa.pdf') || !is_file($assetDir . '/sample-id.png')) {
    passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/write-sample-enrollment-assets.php'), $code);
    if ($code !== 0) {
        fwrite(STDERR, "Could not create sample assets\n");
        exit(1);
    }
}

$psaSrc = $assetDir . '/sample-psa.pdf';
$idSrc = $assetDir . '/sample-id.png';

$course = db()->query(
    "SELECT id FROM courses WHERE name = 'Bachelor of Science in Information Technology' LIMIT 1"
)->fetchColumn();
if (!$course) {
    fwrite(STDERR, "BSIT course not found. Seed courses first.\n");
    exit(1);
}
$courseId = (int)$course;

$firstNames = [
    1 => ['Andrea', 'Brian', 'Camille', 'Darren', 'Elaine'],
    2 => ['Francis', 'Gina', 'Harold', 'Isabel', 'Jonas'],
    3 => ['Karen', 'Leo', 'Mira', 'Nathan', 'Olivia'],
    4 => ['Paolo', 'Queenie', 'Rafael', 'Sofia', 'Theo'],
];
$lastNames = [
    1 => ['Alonzo', 'Bautista', 'Cruz', 'Domingo', 'Espino'],
    2 => ['Flores', 'Gomez', 'Hernandez', 'Ibanez', 'Jimenez'],
    3 => ['Kato', 'Lagman', 'Morales', 'Natividad', 'Ocampo'],
    4 => ['Pascual', 'Quinto', 'Rivera', 'Santos', 'Tan'],
];
$middle = 'Santos';
$birthYears = [1 => 2008, 2 => 2007, 3 => 2006, 4 => 2005];

function store_docs_cli(int $applicationId, string $psaSrc, string $idSrc): void
{
    $dir = enrollment_storage_root() . '/' . $applicationId;
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $insert = db()->prepare(
        'INSERT INTO enrollment_documents (application_id, doc_type, original_name, stored_name, mime_type, byte_size)
         VALUES (?,?,?,?,?,?)'
    );
    $files = [
        'psa_birth' => [$psaSrc, 'psa-birth.pdf', 'application/pdf'],
        'id_photo' => [$idSrc, 'id-photo.png', 'image/png'],
    ];
    foreach ($files as $type => [$src, $orig, $mime]) {
        $ext = $type === 'psa_birth' ? 'pdf' : 'png';
        $stored = $type . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $dir . '/' . $stored;
        if (!copy($src, $dest)) {
            throw new RuntimeException("Could not copy $type for application $applicationId");
        }
        $insert->execute([
            $applicationId,
            $type,
            $orig,
            $stored,
            $mime,
            (int)filesize($dest),
        ]);
    }
}

function submit_cli(array $data, int $courseId, string $psaSrc, string $idSrc): int
{
    $ip = '127.0.0.' . random_int(10, 250);
    $isMovingUp = ($data['application_type'] ?? 'new') === 'moving_up';
    db()->beginTransaction();
    try {
        db()->prepare(
            'INSERT INTO enrollment_applications (
                last_name, first_name, middle_name, no_middle_name, suffix, preferred_name,
                sex, civil_status, citizenship, religion, blood_type, date_of_birth, place_of_birth,
                gov_id_type, gov_id_number, email, mobile,
                province_code, province_name, city_code, city_name, barangay_code, barangay_name,
                address_line1, address_line2, mother_maiden_name, father_name,
                guardian_name, guardian_relationship, guardian_number, guardian_address,
                emergency_name, emergency_relationship, emergency_number,
                application_type, student_number, last_school, last_school_address, year_graduated, strand_or_previous_course,
                course_id, course_id_second, year_level, academic_year, semester,
                privacy_consent, how_heard, ip_address
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $data['last_name'], $data['first_name'], $data['middle_name'], 0, null, null,
            $data['sex'], 'single', 'Filipino', 'Roman Catholic', null,
            $data['date_of_birth'], 'Quezon City',
            null, null,
            $data['email'], $data['mobile'],
            '137400000', 'Metro Manila (NCR)', '137404000', 'Quezon City', '137404018', 'Diliman',
            $data['address_line1'], null,
            $data['mother'], $data['father'],
            $data['guardian_name'], 'mother', $data['guardian_number'], null,
            $data['guardian_name'], 'Mother', $data['guardian_number'],
            $isMovingUp ? 'moving_up' : 'new',
            $isMovingUp ? ($data['student_number'] ?? null) : null,
            $isMovingUp ? 'This institution (moving up)' : $data['last_school'],
            null,
            $isMovingUp ? null : $data['year_graduated'],
            $isMovingUp ? null : 'STEM',
            $courseId, null, (int)$data['year_level'], '2026-2027', '1',
            1, null, $ip,
        ]);
        $id = (int)db()->lastInsertId();
        store_docs_cli($id, $psaSrc, $idSrc);
        if (function_exists('audit')) {
            audit('CREATE', 'enrollment_application', $id, 'CLI batch enrollment (BSIT year-level seed)');
        }
        db()->commit();
        return $id;
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

$created = [];
$stamp = date('YmdHis');
$mobileBase = 9001000000;
$existingStudents = db()->query(
    "SELECT student_number, first_name, last_name, year_level FROM students WHERE is_active=1 ORDER BY year_level, id"
)->fetchAll();

foreach ([1, 2, 3, 4] as $year) {
    for ($i = 0; $i < 5; $i++) {
        $fn = $firstNames[$year][$i];
        $ln = $lastNames[$year][$i];
        $idx = ($year - 1) * 5 + $i + 1;
        $email = sprintf('bsit.y%d.%s.%s.%s@enroll.batch.local', $year, strtolower($fn), strtolower($ln), $stamp);
        $mobileLocal = (string)($mobileBase + $idx);
        $isMovingUp = $year >= 2;
        $studentNumber = null;
        if ($isMovingUp) {
            // Pair with an existing student one year below when available
            $prior = array_values(array_filter(
                $existingStudents,
                static fn(array $s): bool => (int)$s['year_level'] === $year - 1
            ));
            if (isset($prior[$i])) {
                $studentNumber = (string)$prior[$i]['student_number'];
                $fn = (string)$prior[$i]['first_name'];
                $ln = (string)$prior[$i]['last_name'];
            }
        }
        $data = [
            'first_name' => $fn,
            'last_name' => $ln,
            'middle_name' => $middle,
            'sex' => ($i % 2 === 0) ? 'female' : 'male',
            'date_of_birth' => sprintf('%d-%02d-%02d', $birthYears[$year], 3 + $i, 10 + $i),
            'email' => $email,
            'mobile' => enrollment_format_mobile($mobileLocal),
            'address_line1' => (100 + $idx) . ' Commonwealth Ave',
            'mother' => 'Maria ' . $ln,
            'father' => 'Jose ' . $ln,
            'guardian_name' => 'Maria ' . $ln,
            'guardian_number' => '09' . $mobileLocal,
            'last_school' => 'Sample Senior High School',
            'year_graduated' => (string)(2024 - ($year - 1)),
            'year_level' => (string)$year,
            'application_type' => $isMovingUp ? 'moving_up' : 'new',
            'student_number' => $studentNumber,
        ];
        $id = submit_cli($data, $courseId, $psaSrc, $idSrc);
        $created[] = [
            'id' => $id,
            'year' => $year,
            'name' => "$ln, $fn",
            'email' => $email,
        ];
        $typeLabel = $isMovingUp ? 'moving_up' : 'new';
        echo "OK year {$year} ({$typeLabel}): {$ln}, {$fn} => application #{$id}"
            . ($studentNumber ? " ID {$studentNumber}" : '') . "\n";
    }
}

echo "\nCreated " . count($created) . " pending BSIT enrollment applications.\n";
$counts = db()->query(
    "SELECT year_level, COUNT(*) AS c FROM enrollment_applications
     WHERE email LIKE '%@enroll.batch.local' AND status='pending'
     GROUP BY year_level ORDER BY year_level"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($counts as $row) {
    echo "Year {$row['year_level']}: {$row['c']} pending (batch emails)\n";
}
