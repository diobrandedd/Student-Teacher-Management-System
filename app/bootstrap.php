<?php
declare(strict_types=1);

function load_env(string $path): void
{
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (getenv($key) === false) putenv($key . '=' . trim($value, "\"'"));
    }
}

load_env(dirname(__DIR__) . '/.env');

ini_set('display_errors', getenv('APP_ENV') === 'development' ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Manila');

if (PHP_SAPI !== 'cli') {
session_name('ssis_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('Cache-Control: no-store');
}

function env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306'), env('DB_NAME', 'student_secure'));
    $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $page = 'dashboard', array $query = []): never
{
    $params = array_merge(['page' => $page], $query);
    header('Location: index.php?' . http_build_query($params));
    exit;
}
function user(): ?array { return $_SESSION['user'] ?? null; }
function require_auth(): void
{
    if (!user()) redirect('login');
    $page = $_GET['page'] ?? 'dashboard';
    if (!empty($_SESSION['must_change_password']) && !in_array($page, ['change_password', 'logout'], true)) {
        redirect('change_password');
    }
}
function require_role(array $roles): void { require_auth(); if (!in_array(user()['role'], $roles, true)) { deny_request('You do not have permission to open this page.', 403); } }

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Administrator',
        'staff' => 'Teacher/Staff',
        'student' => 'Student',
        default => ucfirst($role),
    };
}

function system_settings(): array
{
    static $settings;
    if (is_array($settings)) return $settings;
    $settings = [];
    try {
        foreach (db()->query('SELECT setting_key, setting_value FROM system_settings') as $row) {
            $settings[$row['setting_key']] = (string) $row['setting_value'];
        }
    } catch (Throwable) {
        // Unavailable during early install or connection issues.
    }
    return $settings;
}

function valid_person_name(string $name): bool
{
    return mb_strlen($name) >= 1
        && mb_strlen($name) <= 100
        && preg_match("/^[\p{L}][\p{L}\p{M} .,'-]*$/u", $name) === 1;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void
{
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string) $_POST['csrf'])) {
        deny_request('Invalid or expired form token. Go back and try again.', 419);
    }
}

function flash(string $type, string $message): void { $_SESSION['flash'] = [$type, $message]; }
function take_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

function audit(string $action, string $entityType, ?int $entityId = null, string $details = ''): void
{
    $actor = user();
    $userId = null;
    if ($actor && ($actor['account_type'] ?? 'user') !== 'student') {
        $userId = $actor['id'] ?? null;
    } elseif ($actor && ($actor['account_type'] ?? '') === 'student') {
        $prefix = 'student:' . ($actor['username'] ?? (string)($actor['id'] ?? ''));
        $details = $details === '' ? $prefix : ($prefix . ' — ' . $details);
    }
    $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $action, $entityType, $entityId, mb_substr($details, 0, 500), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
}

function validate_student(array $data, bool $studentSelfEdit = false): array
{
    $errors = [];
    if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (($data['phone'] ?? '') !== '' && !preg_match('/^[0-9+() -]{7,20}$/', $data['phone'])) $errors[] = 'Enter a valid phone number.';
    if (!$studentSelfEdit) {
        foreach (['student_number','first_name','last_name','year_level'] as $field) if (trim((string)($data[$field] ?? '')) === '') $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        if (course_name($data['course_id']??null)==='') $errors[]='Select a valid course.';
        if (!preg_match('/^\d{3,30}$/', $data['student_number'] ?? '')) $errors[] = 'Student number must contain digits only (3-30 digits).';
        if (($data['first_name'] ?? '') !== '' && !valid_person_name($data['first_name'])) $errors[] = 'First name must use letters and common name punctuation only.';
        if (($data['last_name'] ?? '') !== '' && !valid_person_name($data['last_name'])) $errors[] = 'Last name must use letters and common name punctuation only.';
        if (!filter_var($data['year_level'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]])) $errors[] = 'Year level must be from 1 to 4.';
        if (!in_array($data['academic_status'] ?? '', academic_statuses(), true)) $errors[] = 'Select a valid academic status.';
    }
    return $errors;
}

function login_username_base(string $lastName, string $firstName): string
{
    $lastLetters = preg_replace('/[^\p{L}]+/u', '', $lastName) ?? '';
    $firstLetters = preg_replace('/[^\p{L}]+/u', '', $firstName) ?? '';
    if ($lastLetters === '' || $firstLetters === '') {
        return '';
    }
    $lastPart = mb_strtoupper(mb_substr($lastLetters, 0, 1)) . mb_strtolower(mb_substr($lastLetters, 1));
    $initial = mb_strtoupper(mb_substr($firstLetters, 0, 1));
    return $lastPart . '_' . $initial;
}

function allocate_login_username(string $lastName, string $firstName, ?int $ignoreStudentId = null, ?int $ignoreUserId = null): string
{
    $base = login_username_base($lastName, $firstName);
    if ($base === '') {
        return '';
    }
    $candidate = $base;
    for ($suffix = 2; username_taken($candidate, $ignoreStudentId, $ignoreUserId); $suffix++) {
        if ($suffix > 999) {
            return '';
        }
        $candidate = $base . $suffix;
    }
    return $candidate;
}

function username_taken(string $username, ?int $ignoreStudentId = null, ?int $ignoreUserId = null): bool
{
    $userSql = 'SELECT COUNT(*) FROM users WHERE username=?';
    $userParams = [$username];
    if ($ignoreUserId) {
        $userSql .= ' AND id<>?';
        $userParams[] = $ignoreUserId;
    }
    $stmt = db()->prepare($userSql);
    $stmt->execute($userParams);
    if ((int)$stmt->fetchColumn() > 0) return true;

    $studentSql = 'SELECT COUNT(*) FROM students WHERE username=?';
    $studentParams = [$username];
    if ($ignoreStudentId) {
        $studentSql .= ' AND id<>?';
        $studentParams[] = $ignoreStudentId;
    }
    $stmt = db()->prepare($studentSql);
    $stmt->execute($studentParams);
    return (int)$stmt->fetchColumn() > 0;
}

function session_user_from_staff(array $account): array
{
    return [
        'id' => (int)$account['id'],
        'full_name' => $account['full_name'],
        'username' => $account['username'],
        'email' => $account['email'],
        'role' => $account['role'],
        'account_type' => 'user',
    ];
}

function session_user_from_student(array $student): array
{
    return [
        'id' => (int)$student['id'],
        'full_name' => trim($student['first_name'] . ' ' . $student['last_name']),
        'username' => $student['username'],
        'email' => $student['email'],
        'role' => 'student',
        'account_type' => 'student',
    ];
}

function academic_statuses(): array
{
    return ['Active', 'Dropped out', 'Graduated'];
}

function valid_full_name(string $name): bool
{
    return mb_strlen($name) >= 3
        && mb_strlen($name) <= 150
        && preg_match("/^[\p{L}][\p{L}\p{M} .,'-]*$/u", $name) === 1;
}

function sync_teacher(int $userId): void
{
    db()->prepare("INSERT INTO teachers (user_id) SELECT id FROM users WHERE id=? AND role='staff' ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)")->execute([$userId]);
    $stmt = db()->prepare("SELECT t.id, t.first_name, t.last_name, u.full_name FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.user_id=? AND u.role='staff'");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) return;
    if (trim((string)($row['first_name'] ?? '')) === '' && trim((string)($row['last_name'] ?? '')) === '') {
        [$first, $middle, $last] = split_person_name((string)$row['full_name']);
        db()->prepare('UPDATE teachers SET first_name=?, middle_name=?, last_name=? WHERE id=?')->execute([$first, $middle !== '' ? $middle : null, $last, $row['id']]);
    }
}

function split_person_name(string $fullName): array
{
    $parts = preg_split('/\s+/u', trim($fullName)) ?: [];
    $parts = array_values(array_filter($parts, fn($part) => $part !== ''));
    if (!$parts) return ['', '', ''];
    if (count($parts) === 1) return [$parts[0], '', $parts[0]];
    $first = array_shift($parts);
    $last = array_pop($parts);
    return [$first, implode(' ', $parts), $last];
}

function compose_full_name(string $first, string $middle, string $last): string
{
    return trim(preg_replace('/\s+/u', ' ', $first . ' ' . $middle . ' ' . $last) ?? '');
}

function teacher_display_name(array $row): string
{
    $first = trim((string)($row['first_name'] ?? ''));
    $middle = trim((string)($row['middle_name'] ?? ''));
    $last = trim((string)($row['last_name'] ?? ''));
    if ($first === '' && $last === '') {
        return trim((string)($row['full_name'] ?? 'Teacher'));
    }
    $given = trim($first . ($middle !== '' ? ' ' . $middle : ''));
    return $last !== '' ? ($given !== '' ? $last . ', ' . $given : $last) : $given;
}

function validate_teacher_profile(array $data, bool $requireDepartment = false): array
{
    $errors = [];
    if (!valid_person_name(trim((string)($data['first_name'] ?? '')))) $errors[] = 'Enter a valid first name using letters and common name punctuation.';
    if (!valid_person_name(trim((string)($data['last_name'] ?? '')))) $errors[] = 'Enter a valid last name using letters and common name punctuation.';
    $middle = trim((string)($data['middle_name'] ?? ''));
    if ($middle !== '' && !valid_person_name($middle)) $errors[] = 'Middle name must use letters and common name punctuation only.';
    if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (($data['phone'] ?? '') !== '' && !preg_match('/^[0-9+() -]{7,20}$/', (string)$data['phone'])) $errors[] = 'Enter a valid contact number.';
    if (mb_strlen((string)($data['address'] ?? '')) > 255) $errors[] = 'Address must be 255 characters or fewer.';
    if ($requireDepartment) {
        $department = filter_var($data['department_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$department) $errors[] = 'Select a department.';
        else {
            $check = db()->prepare('SELECT COUNT(*) FROM departments WHERE id=?');
            $check->execute([$department]);
            if (!(int)$check->fetchColumn()) $errors[] = 'Select a valid department.';
        }
    }
    return $errors;
}

function course_name(mixed $id): string
{
    $id=filter_var($id,FILTER_VALIDATE_INT);
    if (!$id || $id<1) return '';
    $stmt=db()->prepare('SELECT name FROM courses WHERE id=?');
    $stmt->execute([$id]);
    return (string)($stmt->fetchColumn() ?: '');
}

function deny_request(string $message, int $status = 403): never
{
    http_response_code($status);
    if (PHP_SAPI === 'cli') {
        exit($message);
    }
    $title = $status === 419 ? 'Form expired' : ($status === 404 ? 'Not found' : 'Access denied');
    $home = 'index.php';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title) . ' - SSIS</title><link rel="stylesheet" href="style.css"></head><body><main class="wrap"><section class="card narrow"><h1>' . htmlspecialchars($title) . '</h1><p class="muted">' . htmlspecialchars($message) . '</p><p><a class="button" href="' . htmlspecialchars($home) . '">Return home</a></p></section></main></body></html>';
    exit;
}

function default_temp_password(): string
{
    return '123';
}

function password_is_strong(string $password): bool
{
    return $password !== default_temp_password()
        && strlen($password) >= 12
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/\d/', $password) === 1;
}

function hash_temp_password(): string
{
    return password_hash(default_temp_password(), PASSWORD_DEFAULT);
}

function password_field(string $id, string $name, array $attrs = []): string
{
    $parts = [];
    foreach ($attrs as $key => $value) {
        if ($value === false || $value === null) continue;
        if ($value === true) {
            $parts[] = e((string)$key);
            continue;
        }
        $parts[] = e((string)$key) . '="' . e((string)$value) . '"';
    }
    $attrString = $parts ? ' ' . implode(' ', $parts) : '';
    return '<div class="password-field">'
        . '<input type="password" id="' . e($id) . '" name="' . e($name) . '"' . $attrString . '>'
        . '<button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false" title="Show password">'
        . '<svg class="icon-eye" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 5c-5 0-9.27 3.11-11 7 1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-2.5A2.5 2.5 0 1 0 12 9a2.5 2.5 0 0 0 0 5z"/></svg>'
        . '<svg class="icon-eye-off" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false" hidden><path fill="currentColor" d="M2.1 3.51 3.5 2.1l18.4 18.4-1.41 1.41-3.23-3.23A12.7 12.7 0 0 1 12 19c-5 0-9.27-3.11-11-7a13.4 13.4 0 0 1 4.2-5.13L2.1 3.51zM12 7a5 5 0 0 1 4.9 6.1l-1.6-1.6A2.5 2.5 0 0 0 12.5 9.2L11 7.7c.33-.12.66-.2 1-.2zm-5.34.93A11.2 11.2 0 0 0 1 12c1.73 3.89 6 7 11 7 1.4 0 2.74-.25 3.97-.7l-2.1-2.1A5 5 0 0 1 7.7 9.13L6.66 7.93z"/></svg>'
        . '</button></div>';
}
