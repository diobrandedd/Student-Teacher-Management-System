<?php
declare(strict_types=1);
/**
 * Seed pending New + Moving up enrollments for Blocking / Enrollments testing.
 * - Pending NEW apps (approve → new students land in Blocking)
 * - Pending Moving up for students removed from blocks (so they also need Blocking)
 * - A couple of already-approved, unblocked students for instant Blocking checks
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
    fwrite(STDERR, "BSIT course missing — run sample seed first\n");
    exit(1);
}

$adminId = (int)db()->query("SELECT id FROM users WHERE role='admin' AND is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
$registrarId = (int)db()->query("SELECT id FROM users WHERE role='registrar' AND is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
$approverId = $registrarId > 0 ? $registrarId : $adminId;

// Free sample students from blocks so Blocking has work (and moving-up targets stay placeable)
$unblockNums = ['2026-0001', '2026-0002', '2026-0003', '2026-0004'];
$delBs = db()->prepare(
    'DELETE bs FROM block_students bs
     JOIN students s ON s.id=bs.student_id
     WHERE s.student_number=?'
);
foreach ($unblockNums as $num) {
    $delBs->execute([$num]);
    echo "unblocked $num from any block\n";
}

// Keep year levels consistent with moving-up targets
$years = [
    '2026-0001' => 1,
    '2026-0002' => 1,
    '2026-0003' => 2,
    '2026-0004' => 2,
];
$updYear = db()->prepare('UPDATE students SET year_level=? WHERE student_number=?');
foreach ($years as $num => $year) {
    $updYear->execute([$year, $num]);
}

$assetDir = __DIR__ . '/sample-assets/enrollment';
$psa = $assetDir . '/sample-psa.pdf';
$idPhoto = $assetDir . '/sample-id.png';
$attachDocs = static function (int $appId) use ($psa, $idPhoto): void {
    if (!is_file($psa) || !is_file($idPhoto)) {
        return;
    }
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
};

$insertApp = db()->prepare(
    'INSERT INTO enrollment_applications (
        status, last_name, first_name, middle_name, no_middle_name,
        sex, civil_status, citizenship, religion, date_of_birth, place_of_birth,
        email, mobile,
        province_code, province_name, city_code, city_name, barangay_code, barangay_name,
        address_line1, mother_maiden_name, father_name,
        guardian_name, guardian_relationship, guardian_number,
        emergency_name, emergency_relationship, emergency_number,
        application_type, student_number, last_school, year_graduated, strand_or_previous_course,
        course_id, year_level, academic_year, semester,
        privacy_consent, ip_address
    ) VALUES (
        \'pending\', ?, ?, ?, ?,
        ?, \'single\', \'Filipino\', \'Roman Catholic\', ?, ?,
        ?, ?,
        \'130000000\', \'Metro Manila (NCR)\', \'137404000\', \'Quezon City\', \'137404001\', \'Diliman\',
        ?, ?, ?,
        ?, \'mother\', \'09190008888\',
        ?, \'Mother\', \'09190008888\',
        ?, ?, ?, ?, ?,
        ?, ?, \'2026-2027\', \'1\',
        1, \'127.0.0.1\'
    )'
);

// Clear prior blocking-test emails so re-run is clean
db()->exec(
    "DELETE FROM enrollment_applications
     WHERE email LIKE '%@enroll.blocking.test'
        OR email IN (
          'carla.lopez@enroll.sample.edu','diego.ramos@enroll.sample.edu',
          'ana.reyes@enroll.sample.edu','ben.garcia@enroll.sample.edu',
          'elena.torres@enroll.sample.edu','felix.navarro@enroll.sample.edu',
          'patricia.santos@enroll.sample.edu','marco.villanueva@enroll.sample.edu'
        )"
);

$newApps = [
    ['Cruz', 'Sofia', null, 'female', '2007-04-12', 'sofia.cruz@enroll.blocking.test', '09190002001', 'Quezon City Science HS', '2025', 'STEM'],
    ['Dela Cruz', 'Miguel', 'Santos', 'male', '2006-08-03', 'miguel.delacruz@enroll.blocking.test', '09190002002', 'Makati Science HS', '2025', 'STEM'],
    ['Ocampo', 'Isabel', null, 'female', '2007-01-22', 'isabel.ocampo@enroll.blocking.test', '09190002003', 'Pasig City Science HS', '2024', 'HUMSS'],
    ['Tan', 'Kevin', 'Lee', 'male', '2006-11-19', 'kevin.tan@enroll.blocking.test', '09190002004', 'Manila Science HS', '2025', 'ABM'],
];

foreach ($newApps as [$last, $first, $middle, $sex, $dob, $email, $mobile, $school, $grad, $strand]) {
    $noMiddle = $middle === null ? 1 : 0;
    $insertApp->execute([
        $last, $first, $middle, $noMiddle,
        $sex, $dob, 'Metro Manila',
        $email, $mobile,
        'Sample campus address', 'Sample Mother', 'Sample Father',
        'Sample Mother', 'Sample Mother',
        'new', null, $school, $grad, $strand,
        $courseId, 1,
    ]);
    $appId = (int)db()->lastInsertId();
    $attachDocs($appId);
    audit('CREATE', 'enrollment_application', $appId, 'Blocking-test new enrollee');
    echo "pending NEW #$appId: $last, $first\n";
}

$movingUp = [
    ['Reyes', 'Ana', '2026-0001', 'ana.reyes@enroll.sample.edu', 1, 2, 'female', '09181234001'],
    ['Garcia', 'Ben', '2026-0002', 'ben.garcia@enroll.sample.edu', 1, 2, 'male', '09181234002'],
    ['Lopez', 'Carla', '2026-0003', 'carla.lopez@enroll.sample.edu', 2, 3, 'female', '09181234003'],
    ['Ramos', 'Diego', '2026-0004', 'diego.ramos@enroll.sample.edu', 2, 3, 'male', '09181234004'],
];

foreach ($movingUp as [$last, $first, $num, $email, $from, $to, $sex, $mobile]) {
    db()->prepare("DELETE FROM enrollment_applications WHERE student_number=? AND application_type='moving_up'")->execute([$num]);
    $insertApp->execute([
        $last, $first, null, 1,
        $sex, '2005-06-15', 'Metro Manila',
        $email, $mobile,
        'Sample campus address', 'Sample Mother', 'Sample Father',
        'Sample Mother', 'Sample Mother',
        'moving_up', $num, 'This institution (moving up)', null, null,
        $courseId, $to,
    ]);
    $appId = (int)db()->lastInsertId();
    $attachDocs($appId);
    audit('CREATE', 'enrollment_application', $appId, "Blocking-test moving_up y{$from}→y{$to}");
    echo "pending MOVING UP #$appId: $last, $first ($num) y{$from}→y{$to}\n";
}

// Instant Blocking fodder: approved enrollees not in any block
$ready = [
    ['2026-0091', 'Nina', 'Valdez', 'nina.valdez@student.blocking.test', '09190003001'],
    ['2026-0092', 'Omar', 'Silva', 'omar.silva@student.blocking.test', '09190003002'],
];
$pwd = password_hash(default_temp_password(), PASSWORD_DEFAULT);
foreach ($ready as [$num, $first, $last, $email, $phone]) {
    db()->prepare('DELETE FROM block_students WHERE student_id IN (SELECT id FROM students WHERE student_number=?)')->execute([$num]);
    db()->prepare('DELETE FROM students WHERE student_number=? OR email=?')->execute([$num, $email]);
    $username = allocate_login_username($last, $first);
    db()->prepare(
        'INSERT INTO students (
            student_number, first_name, last_name, email, phone, address,
            course, course_id, year_level, academic_status, username, password_hash, must_change_password,
            enrollment_approved_by, enrollment_approved_at, is_active
        ) VALUES (?,?,?,?,?,?,?,?,1,\'Active\',?,?,1,?,NOW(),1)'
    )->execute([
        $num, $first, $last, $email, $phone, 'Quezon City',
        'Bachelor of Science in Information Technology', $courseId,
        $username, $pwd,
        $approverId > 0 ? $approverId : null,
    ]);
    $sid = (int)db()->lastInsertId();
    echo "ready unblocked student #$sid: $last, $first ($num) — already in Blocking\n";
}

$pendingNew = (int)db()->query("SELECT COUNT(*) FROM enrollment_applications WHERE status='pending' AND application_type='new'")->fetchColumn();
$pendingMove = (int)db()->query("SELECT COUNT(*) FROM enrollment_applications WHERE status='pending' AND application_type='moving_up'")->fetchColumn();
$unblocked = (int)db()->query(
    "SELECT COUNT(*) FROM students s
     WHERE s.is_active=1 AND s.academic_status='Active'
       AND NOT EXISTS (SELECT 1 FROM block_students bs WHERE bs.student_id=s.id)"
)->fetchColumn();

echo "\nSummary: pending new=$pendingNew, pending moving_up=$pendingMove, unblocked active=$unblocked\n";
echo "Approve NEW apps under Enrollments → they appear in Blocking.\n";
echo "Valdez / Silva are already waiting in Blocking.\n";
echo "done\n";
