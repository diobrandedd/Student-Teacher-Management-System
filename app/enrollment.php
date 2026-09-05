<?php
declare(strict_types=1);

function enrollment_storage_root(): string
{
    $root = dirname(__DIR__) . '/storage/enrollment';
    if (!is_dir($root)) {
        mkdir($root, 0750, true);
    }
    return $root;
}

function enrollment_doc_types(): array
{
    return [
        'psa_birth' => 'PSA birth certificate',
        'id_photo' => 'ID photo',
    ];
}

function enrollment_sex_options(): array
{
    return ['male' => 'Male', 'female' => 'Female'];
}

function enrollment_civil_status_options(): array
{
    return [
        'single' => 'Single',
        'married' => 'Married',
        'widowed' => 'Widowed',
        'separated' => 'Separated',
        'other' => 'Other',
    ];
}

function enrollment_guardian_relationships(): array
{
    return [
        'mother' => 'Mother',
        'father' => 'Father',
        'relative' => 'Relative',
        'other' => 'Other',
    ];
}

function enrollment_application_types(): array
{
    return [
        'new' => 'New student',
        'moving_up' => 'Moving up',
    ];
}

function enrollment_is_moving_up(array $data): bool
{
    return ($data['application_type'] ?? '') === 'moving_up';
}

function enrollment_semester_options(): array
{
    return [
        '1' => '1st semester',
        '2' => '2nd semester',
        'summer' => 'Summer',
    ];
}

function enrollment_blood_types(): array
{
    return ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
}

function enrollment_age_from_dob(string $dob): ?int
{
    try {
        $born = new DateTimeImmutable($dob);
        $now = new DateTimeImmutable('today');
        return (int)$born->diff($now)->y;
    } catch (Throwable) {
        return null;
    }
}

function enrollment_compose_address(array $row): string
{
    $parts = array_filter([
        trim((string)($row['address_line1'] ?? '')),
        trim((string)($row['address_line2'] ?? '')),
        trim((string)($row['barangay_name'] ?? '')),
        trim((string)($row['city_name'] ?? '')),
        trim((string)($row['province_name'] ?? '')),
    ], fn($v) => $v !== '');
    return mb_substr(implode(', ', $parts), 0, 255);
}

function enrollment_empty_input(): array
{
    return [
        'last_name' => '', 'first_name' => '', 'middle_name' => '', 'no_middle_name' => false,
        'suffix' => '', 'preferred_name' => '',
        'sex' => '', 'civil_status' => '', 'citizenship' => 'Filipino', 'religion' => '',
        'blood_type' => '', 'date_of_birth' => '', 'place_of_birth' => '',
        'gov_id_type' => '', 'gov_id_number' => '',
        'email' => '', 'mobile' => '',
        'province_code' => '', 'province_name' => '', 'city_code' => '', 'city_name' => '',
        'barangay_code' => '', 'barangay_name' => '',
        'address_line1' => '', 'address_line2' => '',
        'mother_maiden_name' => '', 'father_name' => '',
        'guardian_name' => '', 'guardian_relationship' => '', 'guardian_number' => '', 'guardian_address' => '',
        'emergency_name' => '', 'emergency_relationship' => '', 'emergency_number' => '',
        'application_type' => 'new', 'student_number' => '', 'last_school' => '', 'last_school_address' => '',
        'year_graduated' => '', 'strand_or_previous_course' => '',
        'course_id' => '', 'course_id_second' => '', 'year_level' => '1',
        'academic_year' => '', 'semester' => '1',
        'privacy_consent' => false,
    ];
}

function enrollment_collect_post(): array
{
    $data = enrollment_empty_input();
    foreach ($data as $key => $default) {
        if ($key === 'no_middle_name' || $key === 'privacy_consent') {
            $data[$key] = isset($_POST[$key]);
            continue;
        }
        $data[$key] = trim((string)($_POST[$key] ?? ''));
    }
    if ($data['no_middle_name']) {
        $data['middle_name'] = '';
    }
    if (enrollment_is_moving_up($data)) {
        $data['last_school'] = 'This institution (moving up)';
        $data['last_school_address'] = '';
        $data['year_graduated'] = '';
        $data['course_id_second'] = '';
    } else {
        $data['student_number'] = '';
    }
    return $data;
}

function enrollment_validate(array $data, bool $requireFiles = true): array
{
    $errors = [];
    if (!valid_person_name($data['last_name'] ?? '')) $errors[] = 'Enter a valid last name.';
    if (!valid_person_name($data['first_name'] ?? '')) $errors[] = 'Enter a valid first name.';
    if (empty($data['no_middle_name'])) {
        if (!valid_person_name($data['middle_name'] ?? '')) $errors[] = 'Enter a valid middle name, or check No middle name.';
    }
    if (($data['suffix'] ?? '') !== '' && mb_strlen($data['suffix']) > 20) $errors[] = 'Suffix is too long.';
    if (($data['preferred_name'] ?? '') !== '' && !valid_person_name($data['preferred_name']) && !valid_full_name($data['preferred_name'])) {
        $errors[] = 'Preferred name must use letters and common name punctuation only.';
    }
    if (!isset(enrollment_sex_options()[$data['sex'] ?? ''])) $errors[] = 'Select sex.';
    if (!isset(enrollment_civil_status_options()[$data['civil_status'] ?? ''])) $errors[] = 'Select civil status.';
    if (trim((string)($data['citizenship'] ?? '')) === '') $errors[] = 'Citizenship is required.';
    if (($data['blood_type'] ?? '') !== '' && !in_array($data['blood_type'], enrollment_blood_types(), true)) {
        $errors[] = 'Select a valid blood type.';
    }
    $dob = (string)($data['date_of_birth'] ?? '');
    $age = enrollment_age_from_dob($dob);
    if ($age === null || $age < 10 || $age > 100) $errors[] = 'Enter a valid date of birth.';
    if (trim((string)($data['place_of_birth'] ?? '')) === '') $errors[] = 'Place of birth is required.';
    if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!preg_match('/^[0-9+() -]{7,20}$/', (string)($data['mobile'] ?? ''))) $errors[] = 'Enter a valid mobile number.';
    foreach (['province_code','province_name','city_code','city_name','barangay_code','barangay_name','address_line1'] as $field) {
        if (trim((string)($data[$field] ?? '')) === '') {
            $errors[] = 'Complete the current address (province, city, barangay, and address line 1).';
            break;
        }
    }
    if (!valid_full_name((string)($data['mother_maiden_name'] ?? '')) && !valid_person_name((string)($data['mother_maiden_name'] ?? ''))) {
        $errors[] = 'Enter the mother’s maiden name.';
    }
    if (!valid_full_name((string)($data['father_name'] ?? '')) && !valid_person_name((string)($data['father_name'] ?? ''))) {
        $errors[] = 'Enter the father’s full name.';
    }
    if (!valid_full_name((string)($data['guardian_name'] ?? '')) && !valid_person_name((string)($data['guardian_name'] ?? ''))) {
        $errors[] = 'Enter the guardian’s name.';
    }
    if (!isset(enrollment_guardian_relationships()[$data['guardian_relationship'] ?? ''])) $errors[] = 'Select guardian relationship.';
    if (!preg_match('/^[0-9+() -]{7,20}$/', (string)($data['guardian_number'] ?? ''))) $errors[] = 'Enter a valid guardian number.';
    if (($data['emergency_number'] ?? '') !== '' && !preg_match('/^[0-9+() -]{7,20}$/', $data['emergency_number'])) {
        $errors[] = 'Enter a valid emergency contact number.';
    }
    if (!isset(enrollment_application_types()[$data['application_type'] ?? ''])) $errors[] = 'Select application type.';
    if (enrollment_is_moving_up($data)) {
        $sid = trim((string)($data['student_number'] ?? ''));
        if ($sid === '') {
            $errors[] = 'Enter your student ID.';
        } elseif (!valid_student_number_format($sid)) {
            $errors[] = 'Student ID must be 3–30 characters using letters, numbers, and hyphens.';
        } else {
            $exists = db()->prepare('SELECT id FROM students WHERE student_number=? LIMIT 1');
            $exists->execute([$sid]);
            if (!$exists->fetch()) {
                $errors[] = 'That student ID was not found. Check your ID or ask the registrar.';
            } else {
                $dup = db()->prepare("SELECT id FROM enrollment_applications WHERE student_number=? AND status='pending' LIMIT 1");
                $dup->execute([$sid]);
                if ($dup->fetch()) {
                    $errors[] = 'A pending moving-up application already exists for that student ID.';
                }
            }
        }
    } elseif (trim((string)($data['last_school'] ?? '')) === '') {
        $errors[] = 'Last school or institution attended is required for new students.';
    }
    $courseId = filter_var($data['course_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$courseId || course_name($courseId) === '') {
        $errors[] = enrollment_is_moving_up($data)
            ? 'Select your college program.'
            : 'Select your preferred college program.';
    }
    $course2 = trim((string)($data['course_id_second'] ?? ''));
    if (!enrollment_is_moving_up($data) && $course2 !== '') {
        $courseId2 = filter_var($course2, FILTER_VALIDATE_INT);
        if (!$courseId2 || course_name($courseId2) === '') $errors[] = 'Second course choice is invalid.';
        if ($courseId2 && (int)$courseId2 === (int)$courseId) $errors[] = 'Second course choice must differ from the preferred course.';
    }
    if (!filter_var($data['year_level'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]])) {
        $errors[] = 'Select college year (1st–4th year).';
    }
    if (!preg_match('/^\d{4}\s*[–-]\s*\d{4}$/', (string)($data['academic_year'] ?? '')) && !preg_match('/^\d{4}-\d{4}$/', (string)($data['academic_year'] ?? ''))) {
        $errors[] = 'Academic year must look like 2026-2027.';
    }
    if (!isset(enrollment_semester_options()[$data['semester'] ?? ''])) $errors[] = 'Select semester.';
    if (empty($data['privacy_consent'])) $errors[] = 'You must agree to the data privacy consent.';

    if ($requireFiles) {
        foreach (enrollment_doc_types() as $type => $label) {
            $file = $_FILES[$type] ?? null;
            $fileError = enrollment_validate_upload($type, $file);
            if ($fileError !== null) $errors[] = $fileError;
        }
    }

    $dup = enrollment_duplicate_check((string)$data['email']);
    if ($dup) $errors[] = $dup;

    return $errors;
}

function enrollment_rate_limited(?string $ip = null): ?string
{
    $ip = $ip ?? (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $windowSeconds = 900;
    $maxPerWindow = 5;
    $now = time();
    $bucket = &$_SESSION['enroll_rate'];
    if (!is_array($bucket) || ($bucket['ip'] ?? '') !== $ip || ($bucket['started'] ?? 0) < ($now - $windowSeconds)) {
        $bucket = ['ip' => $ip, 'started' => $now, 'count' => 0];
    }
    if ((int)$bucket['count'] >= $maxPerWindow) {
        return 'Too many enrollment submissions from this browser. Wait about 15 minutes and try again.';
    }

    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM enrollment_applications WHERE ip_address=? AND created_at >= (NOW() - INTERVAL 15 MINUTE)'
        );
        $stmt->execute([$ip]);
        if ((int)$stmt->fetchColumn() >= $maxPerWindow) {
            return 'Too many enrollment submissions from this network. Wait about 15 minutes and try again.';
        }
    } catch (Throwable) {
        // Table may be mid-migration; session throttle still applies.
    }
    return null;
}

function enrollment_rate_limit_hit(?string $ip = null): void
{
    $ip = $ip ?? (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $now = time();
    $bucket = &$_SESSION['enroll_rate'];
    if (!is_array($bucket) || ($bucket['ip'] ?? '') !== $ip || ($bucket['started'] ?? 0) < ($now - 900)) {
        $bucket = ['ip' => $ip, 'started' => $now, 'count' => 0];
    }
    $bucket['count'] = (int)$bucket['count'] + 1;
}

function enrollment_duplicate_check(string $email, ?int $ignoreId = null): ?string
{
    $sql = "SELECT id FROM enrollment_applications WHERE email=? AND status IN ('pending','approved')";
    $params = [$email];
    if ($ignoreId) {
        $sql .= ' AND id<>?';
        $params[] = $ignoreId;
    }
    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    if ($stmt->fetch()) return 'An application with this email is already pending or approved.';
    return null;
}

function enrollment_validate_upload(string $docType, ?array $file): ?string
{
    $label = enrollment_doc_types()[$docType] ?? $docType;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $label . ' is required.';
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return $label . ' failed to upload. Try again.';
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return $label . ' must be 5 MB or smaller.';
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return $label . ' upload is invalid.';
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $allowed = $docType === 'id_photo'
        ? ['image/jpeg', 'image/png']
        : ['application/pdf', 'image/jpeg', 'image/png'];
    if (!in_array($mime, $allowed, true)) {
        return $label . ($docType === 'id_photo' ? ' must be a JPEG or PNG image.' : ' must be PDF, JPEG, or PNG.');
    }
    return null;
}

function enrollment_store_uploads(int $applicationId): void
{
    $dir = enrollment_storage_root() . '/' . $applicationId;
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $insert = db()->prepare(
        'INSERT INTO enrollment_documents (application_id, doc_type, original_name, stored_name, mime_type, byte_size)
         VALUES (?,?,?,?,?,?)'
    );
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach (enrollment_doc_types() as $type => $_label) {
        $file = $_FILES[$type];
        $mime = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';
        $ext = match ($mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            default => 'jpg',
        };
        $stored = $type . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $dir . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('Could not store uploaded file.');
        }
        $insert->execute([
            $applicationId,
            $type,
            mb_substr((string)$file['name'], 0, 255),
            $stored,
            $mime,
            (int)$file['size'],
        ]);
    }
}

function enrollment_submit(array $data): int
{
    $courseId = (int)$data['course_id'];
    $course2 = trim((string)($data['course_id_second'] ?? ''));
    $courseId2 = $course2 !== '' ? (int)$course2 : null;
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

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
            $data['last_name'], $data['first_name'],
            $data['no_middle_name'] ? null : ($data['middle_name'] !== '' ? $data['middle_name'] : null),
            $data['no_middle_name'] ? 1 : 0,
            $data['suffix'] !== '' ? $data['suffix'] : null,
            $data['preferred_name'] !== '' ? $data['preferred_name'] : null,
            $data['sex'], $data['civil_status'], $data['citizenship'],
            $data['religion'] !== '' ? $data['religion'] : null,
            $data['blood_type'] !== '' ? $data['blood_type'] : null,
            $data['date_of_birth'], $data['place_of_birth'],
            $data['gov_id_type'] !== '' ? $data['gov_id_type'] : null,
            $data['gov_id_number'] !== '' ? $data['gov_id_number'] : null,
            $data['email'], $data['mobile'],
            $data['province_code'], $data['province_name'], $data['city_code'], $data['city_name'],
            $data['barangay_code'], $data['barangay_name'],
            $data['address_line1'], $data['address_line2'] !== '' ? $data['address_line2'] : null,
            $data['mother_maiden_name'], $data['father_name'],
            $data['guardian_name'], $data['guardian_relationship'], $data['guardian_number'],
            $data['guardian_address'] !== '' ? $data['guardian_address'] : null,
            $data['emergency_name'] !== '' ? $data['emergency_name'] : null,
            $data['emergency_relationship'] !== '' ? $data['emergency_relationship'] : null,
            $data['emergency_number'] !== '' ? $data['emergency_number'] : null,
            $data['application_type'],
            enrollment_is_moving_up($data) ? $data['student_number'] : null,
            $data['last_school'],
            $data['last_school_address'] !== '' ? $data['last_school_address'] : null,
            $data['year_graduated'] !== '' ? $data['year_graduated'] : null,
            $data['strand_or_previous_course'] !== '' ? $data['strand_or_previous_course'] : null,
            $courseId, $courseId2, (int)$data['year_level'], $data['academic_year'], $data['semester'],
            1, null, $ip,
        ]);
        $id = (int)db()->lastInsertId();
        enrollment_store_uploads($id);
        audit('CREATE', 'enrollment_application', $id, 'Public enrollment application submitted');
        db()->commit();
        return $id;
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

function enrollment_list(?string $status = null, string $q = ''): array
{
    $sql = 'SELECT a.*, c.name AS course_name, u.full_name AS reviewer_name
            FROM enrollment_applications a
            JOIN courses c ON c.id=a.course_id
            LEFT JOIN users u ON u.id=a.reviewed_by
            WHERE 1=1';
    $params = [];
    if ($status && in_array($status, ['pending','approved','rejected'], true)) {
        $sql .= ' AND a.status=?';
        $params[] = $status;
    }
    $q = trim($q);
    if ($q !== '') {
        $like = '%' . $q . '%';
        $sql .= ' AND (a.last_name LIKE ? OR a.first_name LIKE ? OR a.email LIKE ? OR CONCAT(a.last_name, \', \', a.first_name) LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    $sql .= ' ORDER BY FIELD(a.status,\'pending\',\'approved\',\'rejected\'), a.created_at DESC LIMIT 200';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function enrollment_fetch(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT a.*, c.name AS course_name, c2.name AS course_name_second, u.full_name AS reviewer_name
         FROM enrollment_applications a
         JOIN courses c ON c.id=a.course_id
         LEFT JOIN courses c2 ON c2.id=a.course_id_second
         LEFT JOIN users u ON u.id=a.reviewed_by
         WHERE a.id=?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function enrollment_documents(int $applicationId): array
{
    $stmt = db()->prepare('SELECT * FROM enrollment_documents WHERE application_id=? ORDER BY doc_type');
    $stmt->execute([$applicationId]);
    return $stmt->fetchAll();
}

function enrollment_document(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM enrollment_documents WHERE id=?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function enrollment_provisional_student_number(): string
{
    return allocate_student_number();
}

function enrollment_approve(int $applicationId, int $reviewerId): int
{
    $app = enrollment_fetch($applicationId);
    if (!$app || $app['status'] !== 'pending') {
        throw new InvalidArgumentException('That application is not pending.');
    }
    $docs = enrollment_documents($applicationId);
    if (count($docs) < 2) {
        throw new InvalidArgumentException('Both required documents must be on file before approval.');
    }
    $courseName = course_name((int)$app['course_id']);
    if ($courseName === '') {
        throw new InvalidArgumentException('The selected course no longer exists.');
    }
    $isMovingUp = ($app['application_type'] ?? '') === 'moving_up';
    $address = enrollment_compose_address($app);

    db()->beginTransaction();
    try {
        if ($isMovingUp) {
            $studentNumber = trim((string)($app['student_number'] ?? ''));
            if ($studentNumber === '') {
                throw new InvalidArgumentException('Moving-up applications require a student ID.');
            }
            $find = db()->prepare('SELECT id, username FROM students WHERE student_number=? LIMIT 1 FOR UPDATE');
            $find->execute([$studentNumber]);
            $existing = $find->fetch();
            if (!$existing) {
                throw new InvalidArgumentException('Student ID ' . $studentNumber . ' was not found.');
            }
            $studentId = (int)$existing['id'];
            db()->prepare(
                'UPDATE students SET
                    first_name=?, middle_name=?, last_name=?, display_name=COALESCE(?, display_name),
                    email=?, phone=?, address=?,
                    course=?, course_id=?, year_level=?, academic_status=\'Active\', is_active=1,
                    enrollment_approved_by=?, enrollment_approved_at=NOW()
                 WHERE id=?'
            )->execute([
                $app['first_name'],
                $app['middle_name'],
                $app['last_name'],
                $app['preferred_name'] ?: null,
                $app['email'],
                $app['mobile'],
                $address,
                $courseName,
                (int)$app['course_id'],
                (int)$app['year_level'],
                $reviewerId,
                $studentId,
            ]);
            db()->prepare(
                'UPDATE enrollment_applications SET status=\'approved\', reviewed_by=?, reviewed_at=NOW(), student_id=?, reject_reason=NULL WHERE id=?'
            )->execute([$reviewerId, $studentId, $applicationId]);
            audit('APPROVE', 'enrollment_application', $applicationId, 'Approved moving-up for student ' . $studentNumber);
            db()->commit();
            return $studentId;
        }

        $username = allocate_login_username($app['last_name'], $app['first_name']);
        if ($username === '') {
            throw new InvalidArgumentException('Could not build a username from the applicant name.');
        }
        $studentNumber = allocate_student_number();
        $issuedTemp = issue_temporary_password();
        db()->prepare(
            'INSERT INTO students (
                student_number, first_name, middle_name, last_name, display_name, email, phone, address,
                course, course_id, year_level, academic_status, username, password_hash, must_change_password,
                enrollment_approved_by, enrollment_approved_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,NOW())'
        )->execute([
            $studentNumber,
            $app['first_name'],
            $app['middle_name'],
            $app['last_name'],
            $app['preferred_name'] ?: null,
            $app['email'],
            $app['mobile'],
            $address,
            $courseName,
            (int)$app['course_id'],
            (int)$app['year_level'],
            'Active',
            $username,
            $issuedTemp['hash'],
            $reviewerId,
        ]);
        $studentId = (int)db()->lastInsertId();
        db()->prepare(
            'UPDATE enrollment_applications SET status=\'approved\', reviewed_by=?, reviewed_at=NOW(), student_id=?, student_number=?, reject_reason=NULL WHERE id=?'
        )->execute([$reviewerId, $studentId, $studentNumber, $applicationId]);
        audit('APPROVE', 'enrollment_application', $applicationId, 'Approved enrollment; student ' . $studentNumber . ' username ' . $username);
        db()->commit();
        $_SESSION['last_enrollment_temp_password'] = $issuedTemp['plain'];
        return $studentId;
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

function enrollment_reject(int $applicationId, int $reviewerId, string $reason): void
{
    $reason = trim($reason);
    if ($reason === '' || mb_strlen($reason) > 500) {
        throw new InvalidArgumentException('Enter a reject reason (up to 500 characters).');
    }
    $app = enrollment_fetch($applicationId);
    if (!$app || $app['status'] !== 'pending') {
        throw new InvalidArgumentException('That application is not pending.');
    }
    db()->prepare(
        'UPDATE enrollment_applications SET status=\'rejected\', reviewed_by=?, reviewed_at=NOW(), reject_reason=? WHERE id=?'
    )->execute([$reviewerId, $reason, $applicationId]);
    audit('REJECT', 'enrollment_application', $applicationId, 'Rejected enrollment: ' . mb_substr($reason, 0, 200));
}

function enrollment_serve_document(array $doc): never
{
    $stored = basename((string)($doc['stored_name'] ?? ''));
    if ($stored === '' || $stored !== (string)$doc['stored_name'] || str_contains($stored, '\\')) {
        http_response_code(404);
        exit('Document file missing.');
    }
    $root = realpath(enrollment_storage_root());
    if ($root === false) {
        http_response_code(404);
        exit('Document file missing.');
    }
    $path = $root . DIRECTORY_SEPARATOR . (int)$doc['application_id'] . DIRECTORY_SEPARATOR . $stored;
    $real = realpath($path);
    $appRoot = $root . DIRECTORY_SEPARATOR . (int)$doc['application_id'];
    if ($real === false || !is_file($real) || !str_starts_with($real, $appRoot)) {
        http_response_code(404);
        exit('Document file missing.');
    }
    $mime = (string)($doc['mime_type'] ?? 'application/octet-stream');
    $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($mime, $allowedMimes, true)) {
        $mime = 'application/octet-stream';
    }
    $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename((string)($doc['original_name'] ?? $stored))) ?: $stored;
    $disposition = str_starts_with($mime, 'image/') ? 'inline' : 'attachment';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($real));
    header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($real);
    exit;
}
