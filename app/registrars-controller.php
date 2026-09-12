<?php
declare(strict_types=1);
require_role(['admin']);

$registrarErrors = [];
$editRegistrar = null;
$showRegistrarCreate = false;
$defaultPermissions = [
    'can_enrollments' => 0,
    'can_blocking' => 0,
    'can_assign_teachers' => 0,
    'can_view_students' => 0,
];
$createInput = array_merge([
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'email' => '',
], $defaultPermissions);

if (isset($_GET['add'])) {
    $showRegistrarCreate = true;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $perms = registrar_permissions_from_post($_POST);
        $createInput = [
            'first_name' => trim((string)($_POST['first_name'] ?? '')),
            'middle_name' => trim((string)($_POST['middle_name'] ?? '')),
            'last_name' => trim((string)($_POST['last_name'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            ...$perms,
        ];
        if ($createInput['first_name'] === '' || !valid_person_name($createInput['first_name'])) {
            $registrarErrors[] = 'Enter a valid first name.';
        }
        if ($createInput['last_name'] === '' || !valid_person_name($createInput['last_name'])) {
            $registrarErrors[] = 'Enter a valid last name.';
        }
        if ($createInput['middle_name'] !== '' && !valid_person_name($createInput['middle_name'])) {
            $registrarErrors[] = 'Middle name must use letters and common name punctuation only.';
        }
        if (!filter_var($createInput['email'], FILTER_VALIDATE_EMAIL)) {
            $registrarErrors[] = 'Enter a valid email address.';
        }
        $username = '';
        if (!$registrarErrors) {
            $username = allocate_login_username($createInput['last_name'], $createInput['first_name']);
            if ($username === '') {
                $registrarErrors[] = 'Could not build a username from the first and last name.';
            } elseif (username_taken($username)) {
                $registrarErrors[] = 'That username is already in use.';
            }
        }
        if (!$registrarErrors) {
            $fullName = compose_full_name($createInput['first_name'], $createInput['middle_name'], $createInput['last_name']);
            $issuedTemp = issue_default_temporary_password();
            try {
                db()->prepare(
                    'INSERT INTO users(full_name,username,email,password_hash,role,must_change_password,can_enrollments,can_blocking,can_assign_teachers,can_view_students) VALUES(?,?,?,?,?,1,?,?,?,?)'
                )->execute([
                    $fullName,
                    $username,
                    $createInput['email'],
                    $issuedTemp['hash'],
                    'registrar',
                    $perms['can_enrollments'],
                    $perms['can_blocking'],
                    $perms['can_assign_teachers'],
                    $perms['can_view_students'],
                ]);
                $newId = (int)db()->lastInsertId();
                audit('CREATE', 'registrar', $newId, 'Created registrar account ' . $username);
                flash('success', 'Registrar created. Username is ' . $username . '; temporary password is ' . $issuedTemp['plain'] . '. Share it privately — they must change it on first sign-in.');
                redirect('registrars', array_filter([
                    'status' => in_array((string)($_GET['status'] ?? ''), ['active', 'inactive', 'all'], true) ? (string)$_GET['status'] : null,
                    'q' => trim((string)($_GET['q'] ?? '')) !== '' ? trim((string)$_GET['q']) : null,
                ], static fn($v) => $v !== null && $v !== ''));
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
                $registrarErrors[] = 'That username or email is already in use, or the registrar could not be created.';
            }
        }
        $createInput['username'] = $username;
    }
}

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT) ?: null;
if ($editId) {
    $statement = db()->prepare(
        "SELECT id, full_name, username, email, is_active, last_login_at, can_enrollments, can_blocking, can_assign_teachers, can_view_students
         FROM users WHERE id=? AND role='registrar'"
    );
    $statement->execute([$editId]);
    $editRegistrar = $statement->fetch() ?: null;
    if (!$editRegistrar) {
        deny_request('Registrar not found. Return to Registrars and try again.', 404);
    }
    $editRegistrar = array_merge($editRegistrar, registrar_permissions_from_account($editRegistrar));
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $resetTemp = isset($_POST['reset_temp_password']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $perms = registrar_permissions_from_post($_POST);
        if (!valid_full_name($fullName)) {
            $registrarErrors[] = 'Enter a valid full name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $registrarErrors[] = 'Enter a valid email address.';
        }
        if (!$registrarErrors) {
            $issuedTemp = null;
            try {
                $params = [
                    $fullName,
                    $email,
                    $isActive,
                    $perms['can_enrollments'],
                    $perms['can_blocking'],
                    $perms['can_assign_teachers'],
                    $perms['can_view_students'],
                ];
                $sql = 'UPDATE users SET full_name=?, email=?, is_active=?, can_enrollments=?, can_blocking=?, can_assign_teachers=?, can_view_students=?';
                if ($resetTemp) {
                    $issuedTemp = issue_default_temporary_password();
                    $sql .= ', password_hash=?, must_change_password=1, failed_attempts=0, locked_until=NULL';
                    $params[] = $issuedTemp['hash'];
                }
                $sql .= ' WHERE id=? AND role=\'registrar\'';
                $params[] = $editId;
                db()->prepare($sql)->execute($params);
                audit('UPDATE', 'registrar', $editId, $resetTemp ? 'Updated registrar and reset temporary password' : 'Updated registrar account');
                flash('success', $resetTemp && $issuedTemp
                    ? 'Registrar saved. Username is ' . $editRegistrar['username'] . '. Temporary password is ' . $issuedTemp['plain'] . '; share it privately — they must change it on next sign-in.'
                    : 'Registrar record saved.');
                redirect('registrars', array_filter([
                    'status' => in_array((string)($_GET['status'] ?? ''), ['active', 'inactive', 'all'], true) ? (string)$_GET['status'] : null,
                    'q' => trim((string)($_GET['q'] ?? '')) !== '' ? trim((string)$_GET['q']) : null,
                ], static fn($v) => $v !== null && $v !== ''));
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
                $registrarErrors[] = 'That email is already in use, or the record could not be saved.';
            }
        }
        $editRegistrar = array_merge($editRegistrar, [
            'full_name' => $fullName,
            'email' => $email,
            'is_active' => $isActive,
        ], $perms);
    }
}

$registrarSearch = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$registrarStatus = trim((string)($_GET['status'] ?? 'active'));
if (!in_array($registrarStatus, ['active', 'inactive', 'all'], true)) {
    $registrarStatus = 'active';
}
$registrarLike = '%' . strtr($registrarSearch, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';

$registrarWhere = "role='registrar' AND (full_name LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!' OR username LIKE ? ESCAPE '!')";
$registrarParams = [$registrarLike, $registrarLike, $registrarLike];
if ($registrarStatus === 'active') {
    $registrarWhere .= ' AND is_active=1';
} elseif ($registrarStatus === 'inactive') {
    $registrarWhere .= ' AND is_active=0';
}

$tallyStmt = db()->prepare(
    "SELECT
        SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) AS active_cnt,
        SUM(CASE WHEN is_active=0 THEN 1 ELSE 0 END) AS inactive_cnt,
        COUNT(*) AS all_cnt
     FROM users
     WHERE role='registrar' AND (full_name LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!' OR username LIKE ? ESCAPE '!')"
);
$tallyStmt->execute([$registrarLike, $registrarLike, $registrarLike]);
$tally = $tallyStmt->fetch() ?: [];
$registrarStatusCounts = [
    'active' => (int)($tally['active_cnt'] ?? 0),
    'inactive' => (int)($tally['inactive_cnt'] ?? 0),
    'all' => (int)($tally['all_cnt'] ?? 0),
];

$statement = db()->prepare("SELECT COUNT(*) FROM users WHERE $registrarWhere");
$statement->execute($registrarParams);
$registrarTotal = (int)$statement->fetchColumn();
$registrarPages = max(1, (int)ceil($registrarTotal / 20));
$registrarPage = max(1, min($registrarPages, (int)($_GET['p'] ?? 1)));
$registrarOffset = ($registrarPage - 1) * 20;
$statement = db()->prepare(
    "SELECT id, full_name, username, email, is_active, last_login_at, can_enrollments, can_blocking, can_assign_teachers, can_view_students
     FROM users
     WHERE $registrarWhere
     ORDER BY full_name, id
     LIMIT 20 OFFSET $registrarOffset"
);
$statement->execute($registrarParams);
$registrarRows = $statement->fetchAll();
foreach ($registrarRows as &$row) {
    $duties = [];
    if (!empty($row['can_enrollments'])) {
        $duties[] = 'Enrollments';
    }
    if (!empty($row['can_view_students'])) {
        $duties[] = 'Students';
    }
    if (!empty($row['can_blocking'])) {
        $duties[] = 'Blocking';
    }
    if (!empty($row['can_assign_teachers'])) {
        $duties[] = 'Teachers';
    }
    $row['duty_labels'] = $duties;
}
unset($row);

$registrarListQuery = static function (array $overrides = []) use ($registrarSearch, $registrarStatus, $registrarPage): array {
    $status = $overrides['status'] ?? $registrarStatus;
    $q = array_key_exists('q', $overrides) ? (string)$overrides['q'] : $registrarSearch;
    $p = (int)($overrides['p'] ?? $registrarPage);
    $params = ['page' => 'registrars', 'status' => $status];
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($p > 1) {
        $params['p'] = $p;
    }
    if (!empty($overrides['edit'])) {
        $params['edit'] = (int)$overrides['edit'];
    }
    if (!empty($overrides['add'])) {
        $params['add'] = 1;
    }
    return $params;
};
