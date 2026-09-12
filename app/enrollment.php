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

function enrollment_normalize_name_part(?string $value): string
{
    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string)$value) ?? ''));
}

function enrollment_names_match(array $left, array $right): bool
{
    foreach (['first_name', 'middle_name', 'last_name'] as $field) {
        if (enrollment_normalize_name_part($left[$field] ?? '') !== enrollment_normalize_name_part($right[$field] ?? '')) {
            return false;
        }
    }
    return true;
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

function enrollment_mobile_digits(string $mobile): string
{
    $raw = trim($mobile);
    if (preg_match('/^63\+(\d{10})$/', $raw, $m)) {
        return $m[1];
    }
    if (preg_match('/^\d{10}$/', $raw)) {
        return $raw;
    }
    $compact = preg_replace('/[\s-]+/', '', $raw) ?? '';
    if (preg_match('/^63\+(\d{10})$/', $compact, $m)) {
        return $m[1];
    }
    if (preg_match('/^\+?63(\d{10})$/', $compact, $m)) {
        return $m[1];
    }
    if (preg_match('/^0(\d{10})$/', $compact, $m)) {
        return $m[1];
    }
    return '';
}

function enrollment_format_mobile(string $localOrFull): string
{
    $digits = enrollment_mobile_digits($localOrFull);
    if (strlen($digits) === 10) {
        return '63+' . $digits;
    }
    return '';
}

function enrollment_valid_mobile(string $mobile): bool
{
    $formatted = enrollment_format_mobile($mobile);
    return (bool)preg_match('/^63\+\d{10}$/', $formatted);
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
    $data['blood_type'] = '';
    $data['gov_id_type'] = '';
    $data['gov_id_number'] = '';
    $data['preferred_name'] = '';
    $data['last_school_address'] = '';
    $data['mobile'] = enrollment_format_mobile((string)$data['mobile']);
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

/**
 * @return array{messages: list<string>, fields: array<string, string>}
 */
function enrollment_validate(array $data, bool $requireFiles = true): array
{
    $messages = [];
    $fields = [];
    $add = static function (string $message, ?string $field = null) use (&$messages, &$fields): void {
        $messages[] = $message;
        if ($field !== null && $field !== '' && !isset($fields[$field])) {
            $fields[$field] = $message;
        }
    };

    if (!valid_person_name($data['last_name'] ?? '')) $add('Enter a valid last name using letters only (no numbers).', 'last_name');
    if (!valid_person_name($data['first_name'] ?? '')) $add('Enter a valid first name using letters only (no numbers).', 'first_name');
    if (empty($data['no_middle_name'])) {
        if (!valid_person_name($data['middle_name'] ?? '')) $add('Enter a valid middle name using letters only (no numbers), or check No middle name.', 'middle_name');
    }
    if (($data['suffix'] ?? '') !== '' && mb_strlen($data['suffix']) > 20) $add('Suffix is too long.', 'suffix');
    if (!isset(enrollment_sex_options()[$data['sex'] ?? ''])) $add('Select sex.', 'sex');
    if (!isset(enrollment_civil_status_options()[$data['civil_status'] ?? ''])) $add('Select civil status.', 'civil_status');
    if (trim((string)($data['citizenship'] ?? '')) === '') $add('Citizenship is required.', 'citizenship');
    if (trim((string)($data['religion'] ?? '')) === '') $add('Religion is required.', 'religion');
    $dob = (string)($data['date_of_birth'] ?? '');
    $age = enrollment_age_from_dob($dob);
    if ($age === null || $age < 10 || $age > 100) $add('Enter a valid date of birth.', 'date_of_birth');
    if (trim((string)($data['place_of_birth'] ?? '')) === '') $add('Place of birth is required.', 'place_of_birth');
    if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) $add('Enter a valid email address.', 'email');
    if (!enrollment_valid_mobile((string)($data['mobile'] ?? ''))) {
        $add('Mobile number must be 63+ followed by exactly 10 digits (numbers only).', 'mobile');
    }
    foreach (['province_code','province_name','city_code','city_name','barangay_code','barangay_name','address_line1'] as $field) {
        if (trim((string)($data[$field] ?? '')) === '') {
            $add('Complete the current address (province, city, barangay, and address line 1).', $field === 'address_line1' ? 'address_line1' : 'province_code');
            break;
        }
    }
    if (!valid_full_name((string)($data['mother_maiden_name'] ?? '')) && !valid_person_name((string)($data['mother_maiden_name'] ?? ''))) {
        $add('Enter the mother’s maiden name.', 'mother_maiden_name');
    }
    if (!valid_full_name((string)($data['father_name'] ?? '')) && !valid_person_name((string)($data['father_name'] ?? ''))) {
        $add('Enter the father’s full name.', 'father_name');
    }
    if (!valid_full_name((string)($data['guardian_name'] ?? '')) && !valid_person_name((string)($data['guardian_name'] ?? ''))) {
        $add('Enter the guardian’s name.', 'guardian_name');
    }
    if (!isset(enrollment_guardian_relationships()[$data['guardian_relationship'] ?? ''])) $add('Select guardian relationship.', 'guardian_relationship');
    if (!preg_match('/^[0-9+() -]{7,20}$/', (string)($data['guardian_number'] ?? ''))) $add('Enter a valid guardian number.', 'guardian_number');
    if (!valid_full_name((string)($data['emergency_name'] ?? '')) && !valid_person_name((string)($data['emergency_name'] ?? ''))) {
        $add('Enter the emergency contact name.', 'emergency_name');
    }
    if (trim((string)($data['emergency_relationship'] ?? '')) === '') {
        $add('Enter the emergency contact relationship.', 'emergency_relationship');
    }
    if (!preg_match('/^[0-9+() -]{7,20}$/', (string)($data['emergency_number'] ?? ''))) {
        $add('Enter a valid emergency contact number.', 'emergency_number');
    }
    if (!isset(enrollment_application_types()[$data['application_type'] ?? ''])) $add('Select application type.', 'application_type');
    if (enrollment_is_moving_up($data)) {
        $sid = trim((string)($data['student_number'] ?? ''));
        if ($sid === '') {
            $add('Enter your student ID.', 'student_number');
        } elseif (!valid_student_number_format($sid)) {
            $add('Student ID must use numbers only (3–30 digits).', 'student_number');
        } else {
            $lookup = student_lookup_by_number_input($sid);
            $student = $lookup['student'];
            if ($lookup['status'] === 'missing') {
                $add('That student ID does not exist.', 'student_number');
            } elseif ($lookup['status'] === 'ambiguous') {
                $add('This student ID matches more than one student record. Ask the registrar to fix the duplicate IDs first.', 'student_number');
            } elseif (!enrollment_names_match($data, $student)) {
                $add('That student ID belongs to a different student name. Use the legal name already in the student list, or ask the registrar to update the record first.', 'student_number');
            } else {
                $dup = db()->prepare("SELECT id FROM enrollment_applications WHERE student_number=? AND status='pending' LIMIT 1");
                $dup->execute([(string)$student['student_number']]);
                if ($dup->fetch()) {
                    $add('A pending moving-up application already exists for that student ID.', 'student_number');
                }
            }
        }
    } else {
        if (trim((string)($data['last_school'] ?? '')) === '') {
            $add('Last school or institution attended is required for new students.', 'last_school');
        }
        if (trim((string)($data['year_graduated'] ?? '')) === '') {
            $add('Year graduated / last attended is required for new students.', 'year_graduated');
        }
        $dupName = enrollment_duplicate_name_check($data);
        if ($dupName) $add($dupName, 'first_name');
    }
    $courseId = filter_var($data['course_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$courseId || course_name($courseId) === '') {
        $add(
            enrollment_is_moving_up($data) ? 'Select your college program.' : 'Select your preferred college program.',
            enrollment_is_moving_up($data) ? 'course_id' : 'course_id'
        );
    }
    $course2 = trim((string)($data['course_id_second'] ?? ''));
    if (!enrollment_is_moving_up($data) && $course2 !== '') {
        $courseId2 = filter_var($course2, FILTER_VALIDATE_INT);
        if (!$courseId2 || course_name($courseId2) === '') $add('Second course choice is invalid.', 'course_id_second');
        if ($courseId2 && (int)$courseId2 === (int)$courseId) $add('Second course choice must differ from the preferred course.', 'course_id_second');
    }
    if (!filter_var($data['year_level'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]])) {
        $add('Select college year (1st–4th year).', 'year_level');
    } elseif (!enrollment_is_moving_up($data) && (int)$data['year_level'] !== 1) {
        $add('New students enroll as 1st year. Use Moving up if you already study here and are advancing.', 'year_level');
    } elseif (enrollment_is_moving_up($data) && !in_array((int)$data['year_level'], [2, 3, 4], true)) {
        $add('Moving up is for advancing into 2nd, 3rd, or 4th year.', 'year_level');
    }
    if (!preg_match('/^\d{4}\s*[–-]\s*\d{4}$/', (string)($data['academic_year'] ?? '')) && !preg_match('/^\d{4}-\d{4}$/', (string)($data['academic_year'] ?? ''))) {
        $add('Academic year must look like 2026-2027.', 'academic_year');
    }
    if (!isset(enrollment_semester_options()[$data['semester'] ?? ''])) $add('Select semester.', 'semester');
    if (empty($data['privacy_consent'])) $add('You must agree to the data privacy consent.', 'privacy_consent');

    if ($requireFiles) {
        foreach (enrollment_doc_types() as $type => $label) {
            $file = $_FILES[$type] ?? null;
            $fileError = enrollment_validate_upload($type, $file);
            if ($fileError !== null) $add($fileError, $type);
        }
    }

    $dup = enrollment_duplicate_check((string)$data['email']);
    if ($dup) $add($dup, 'email');

    return ['messages' => $messages, 'fields' => $fields];
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

function enrollment_duplicate_name_check(array $data, ?int $ignoreApplicationId = null): ?string
{
    $first = enrollment_normalize_name_part($data['first_name'] ?? '');
    $last = enrollment_normalize_name_part($data['last_name'] ?? '');
    if ($first === '' || $last === '') {
        return null;
    }

    $student = db()->prepare(
        'SELECT student_number FROM students
         WHERE LOWER(TRIM(first_name))=?
           AND LOWER(TRIM(last_name))=?
         LIMIT 1'
    );
    $student->execute([$first, $last]);
    $studentNumber = $student->fetchColumn();
    if ($studentNumber) {
        return 'A student with this first and last name is already in the student list (Student ID ' . $studentNumber . '). If this is the same person moving up, choose Moving up and enter that student ID.';
    }

    $sql = "SELECT id FROM enrollment_applications
            WHERE status IN ('pending','approved')
              AND LOWER(TRIM(first_name))=?
              AND LOWER(TRIM(last_name))=?";
    $params = [$first, $last];
    if ($ignoreApplicationId) {
        $sql .= ' AND id<>?';
        $params[] = $ignoreApplicationId;
    }
    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    if ($stmt->fetch()) {
        return 'An application with this first and last name is already pending or approved. You may apply again only if that application was rejected.';
    }
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
    $studentNumber = null;
    if (enrollment_is_moving_up($data)) {
        $lookup = student_lookup_by_number_input((string)($data['student_number'] ?? ''));
        if ($lookup['status'] !== 'found' || !$lookup['student']) {
            throw new InvalidArgumentException('This student ID does not exist in the student list.');
        }
        $studentNumber = (string)$lookup['student']['student_number'];
    }

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
            null,
            $data['sex'], $data['civil_status'], $data['citizenship'],
            $data['religion'],
            null,
            $data['date_of_birth'], $data['place_of_birth'],
            null,
            null,
            $data['email'], $data['mobile'],
            $data['province_code'], $data['province_name'], $data['city_code'], $data['city_name'],
            $data['barangay_code'], $data['barangay_name'],
            $data['address_line1'], $data['address_line2'] !== '' ? $data['address_line2'] : null,
            $data['mother_maiden_name'], $data['father_name'],
            $data['guardian_name'], $data['guardian_relationship'], $data['guardian_number'],
            $data['guardian_address'] !== '' ? $data['guardian_address'] : null,
            $data['emergency_name'],
            $data['emergency_relationship'],
            $data['emergency_number'],
            $data['application_type'],
            $studentNumber,
            $data['last_school'],
            null,
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

/**
 * Parse enrollments queue filters from request (GET).
 * Default status is pending — the registrar’s daily work queue.
 *
 * @return array{
 *   status: string,
 *   q: string,
 *   course_id: int|null,
 *   year_level: int|null,
 *   application_type: string|null,
 *   academic_year: string,
 *   semester: string|null,
 *   page: int,
 *   per_page: int
 * }
 */
function enrollment_list_filters_from_request(array $get = null): array
{
    $get ??= $_GET;
    $status = trim((string)($get['status'] ?? 'pending'));
    if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
        $status = 'pending';
    }
    $courseId = filter_var($get['course_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $yearLevel = filter_var($get['year_level'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]]);
    $appType = trim((string)($get['application_type'] ?? ''));
    if (!in_array($appType, ['new', 'moving_up'], true)) {
        $appType = '';
    }
    $semester = trim((string)($get['semester'] ?? ''));
    if (!array_key_exists($semester, enrollment_semester_options())) {
        $semester = '';
    }
    $academicYear = mb_substr(trim((string)($get['academic_year'] ?? '')), 0, 20);
    $page = max(1, (int)($get['p'] ?? 1));
    return [
        'status' => $status,
        'q' => mb_substr(trim((string)($get['q'] ?? '')), 0, 100),
        'course_id' => $courseId ?: null,
        'year_level' => $yearLevel ?: null,
        'application_type' => $appType !== '' ? $appType : null,
        'academic_year' => $academicYear,
        'semester' => $semester !== '' ? $semester : null,
        'page' => $page,
        'per_page' => 25,
    ];
}

/** Query string params for enrollments list/links (omit empty; keep status). */
function enrollment_list_query_params(array $filters, array $overrides = []): array
{
    $f = array_merge($filters, $overrides);
    $params = ['page' => 'enrollments', 'status' => $f['status'] ?? 'pending'];
    $q = trim((string)($f['q'] ?? ''));
    if ($q !== '') {
        $params['q'] = $q;
    }
    if (!empty($f['course_id'])) {
        $params['course_id'] = (int)$f['course_id'];
    }
    if (!empty($f['year_level'])) {
        $params['year_level'] = (int)$f['year_level'];
    }
    if (!empty($f['application_type'])) {
        $params['application_type'] = (string)$f['application_type'];
    }
    if (trim((string)($f['academic_year'] ?? '')) !== '') {
        $params['academic_year'] = trim((string)$f['academic_year']);
    }
    if (!empty($f['semester'])) {
        $params['semester'] = (string)$f['semester'];
    }
    $pageNum = (int)($f['page'] ?? 1);
    if ($pageNum > 1) {
        $params['p'] = $pageNum;
    }
    if (!empty($f['id'])) {
        $params['id'] = (int)$f['id'];
    }
    return $params;
}

function enrollment_list_query_string(array $filters, array $overrides = []): string
{
    return http_build_query(enrollment_list_query_params($filters, $overrides));
}

/** @return list<string> Distinct academic years on file (newest first). */
function enrollment_academic_years(): array
{
    try {
        $rows = db()->query(
            "SELECT DISTINCT academic_year FROM enrollment_applications WHERE academic_year <> '' ORDER BY academic_year DESC LIMIT 40"
        )->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_filter(array_map('strval', $rows)));
    } catch (Throwable) {
        return [];
    }
}

/**
 * Build WHERE + params for queue filters (shared by list, count, status tallies).
 *
 * @return array{0: string, 1: list<mixed>}
 */
function enrollment_list_where(array $filters, bool $includeStatus = true): array
{
    $sql = ' WHERE 1=1';
    $params = [];
    if ($includeStatus) {
        $status = $filters['status'] ?? 'pending';
        if ($status !== 'all' && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $sql .= ' AND a.status=?';
            $params[] = $status;
        }
    }
    if (!empty($filters['course_id'])) {
        $sql .= ' AND a.course_id=?';
        $params[] = (int)$filters['course_id'];
    }
    if (!empty($filters['year_level'])) {
        $sql .= ' AND a.year_level=?';
        $params[] = (int)$filters['year_level'];
    }
    if (!empty($filters['application_type'])) {
        $sql .= ' AND a.application_type=?';
        $params[] = (string)$filters['application_type'];
    }
    $ay = trim((string)($filters['academic_year'] ?? ''));
    if ($ay !== '') {
        $sql .= ' AND a.academic_year=?';
        $params[] = $ay;
    }
    if (!empty($filters['semester'])) {
        $sql .= ' AND a.semester=?';
        $params[] = (string)$filters['semester'];
    }
    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . $q . '%';
        $digits = preg_replace('/\D+/', '', $q) ?? '';
        $sql .= ' AND (
            a.last_name LIKE ? OR a.first_name LIKE ? OR a.email LIKE ?
            OR CONCAT(a.last_name, \', \', a.first_name) LIKE ?
            OR COALESCE(a.student_number, \'\') LIKE ?
            OR REPLACE(COALESCE(a.student_number, \'\'), \'-\', \'\') LIKE ?
            OR a.mobile LIKE ?
            OR REPLACE(a.mobile, \'+\', \'\') LIKE ?
        )';
        $digitLike = $digits !== '' ? '%' . $digits . '%' : $like;
        array_push($params, $like, $like, $like, $like, $like, $digitLike, $like, $digitLike);
    }
    return [$sql, $params];
}

/**
 * Paginated enrollments queue for registrar scale (~thousands/year).
 *
 * @return array{rows: list<array>, total: int, page: int, pages: int, per_page: int, status_counts: array<string,int>}
 */
function enrollment_list_page(array $filters): array
{
    $perPage = max(1, min(100, (int)($filters['per_page'] ?? 25)));
    [$where, $params] = enrollment_list_where($filters, true);

    $countStmt = db()->prepare('SELECT COUNT(*) FROM enrollment_applications a' . $where);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($pages, (int)($filters['page'] ?? 1)));
    $offset = ($page - 1) * $perPage;

    $sql = 'SELECT a.*, c.name AS course_name, u.full_name AS reviewer_name,
                   s.year_level AS current_year_level
            FROM enrollment_applications a
            JOIN courses c ON c.id=a.course_id
            LEFT JOIN users u ON u.id=a.reviewed_by
            LEFT JOIN students s ON a.student_number IS NOT NULL
              AND a.student_number <> \'\'
              AND s.student_number = a.student_number'
        . $where
        . ' ORDER BY FIELD(a.status,\'pending\',\'approved\',\'rejected\'), a.created_at DESC'
        . " LIMIT $perPage OFFSET $offset";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    [$baseWhere, $baseParams] = enrollment_list_where($filters, false);
    $tallySql = 'SELECT a.status, COUNT(*) AS cnt FROM enrollment_applications a' . $baseWhere . ' GROUP BY a.status';
    $tallyStmt = db()->prepare($tallySql);
    $tallyStmt->execute($baseParams);
    $statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'all' => 0];
    foreach ($tallyStmt->fetchAll() as $row) {
        $key = (string)$row['status'];
        $n = (int)$row['cnt'];
        if (isset($statusCounts[$key])) {
            $statusCounts[$key] = $n;
        }
        $statusCounts['all'] += $n;
    }

    return [
        'rows' => $rows,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
        'status_counts' => $statusCounts,
    ];
}

/** @deprecated Prefer enrollment_list_page(); kept for simple callers/tests. */
function enrollment_list(?string $status = null, string $q = ''): array
{
    $result = enrollment_list_page([
        'status' => $status ?? 'all',
        'q' => $q,
        'course_id' => null,
        'year_level' => null,
        'application_type' => null,
        'academic_year' => '',
        'semester' => null,
        'page' => 1,
        'per_page' => 200,
    ]);
    return $result['rows'];
}

function enrollment_fetch(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT a.*, c.name AS course_name, c2.name AS course_name_second, u.full_name AS reviewer_name,
                s.year_level AS current_year_level
         FROM enrollment_applications a
         JOIN courses c ON c.id=a.course_id
         LEFT JOIN courses c2 ON c2.id=a.course_id_second
         LEFT JOIN users u ON u.id=a.reviewed_by
         LEFT JOIN students s ON a.student_number IS NOT NULL
           AND a.student_number <> \'\'
           AND s.student_number = a.student_number
         WHERE a.id=?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Human label for moving-up from→to years (falls back when current year unknown). */
function enrollment_move_year_label(?int $fromYear, int $toYear): string
{
    $to = college_year_label($toYear);
    if ($fromYear === null || $fromYear < 1 || $fromYear > 4) {
        return 'Moving to ' . $to;
    }
    return college_year_label($fromYear) . ' → ' . $to;
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
            $lookup = student_lookup_by_number_input($studentNumber, true);
            $existing = $lookup['student'];
            if ($lookup['status'] === 'missing') {
                throw new InvalidArgumentException('Student ID ' . $studentNumber . ' was not found.');
            }
            if ($lookup['status'] === 'ambiguous') {
                throw new InvalidArgumentException('Student ID ' . $studentNumber . ' matches more than one student record. Fix the duplicate IDs before approval.');
            }
            if (!enrollment_names_match($app, $existing)) {
                throw new InvalidArgumentException('Student ID ' . $studentNumber . ' belongs to a different student name. Reject this application or ask the registrar to update the existing student record first.');
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

        $dupName = enrollment_duplicate_name_check($app, $applicationId);
        if ($dupName) {
            throw new InvalidArgumentException($dupName);
        }
        $username = allocate_login_username($app['last_name'], $app['first_name']);
        if ($username === '') {
            throw new InvalidArgumentException('Could not build a username from the applicant name.');
        }
        $studentNumber = allocate_student_number();
        $issuedTemp = issue_default_temporary_password();
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
