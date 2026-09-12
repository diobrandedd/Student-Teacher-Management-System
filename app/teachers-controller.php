<?php
declare(strict_types=1);
require_role(['admin']);

$teacherErrors = [];
$editTeacher = null;
$showTeacherCreate = false;
$createInput = [
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'department_id' => '',
];
$departmentOptions = db()->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
$canManage = user()['role'] === 'admin';

if (isset($_GET['add'])) {
    require_role(['admin']);
    $showTeacherCreate = true;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $createInput = [
            'first_name' => trim((string)($_POST['first_name'] ?? '')),
            'middle_name' => trim((string)($_POST['middle_name'] ?? '')),
            'last_name' => trim((string)($_POST['last_name'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            'phone' => trim((string)($_POST['phone'] ?? '')),
            'address' => trim((string)($_POST['address'] ?? '')),
            'department_id' => $_POST['department_id'] ?? '',
        ];
        $teacherErrors = validate_teacher_profile($createInput, false);
        $username = '';
        if (!$teacherErrors) {
            $username = allocate_login_username($createInput['last_name'], $createInput['first_name']);
            if ($username === '') $teacherErrors[] = 'Could not build a username from the first and last name.';
        }
        $departmentId = filter_var($createInput['department_id'], FILTER_VALIDATE_INT) ?: null;
        if (($createInput['department_id'] ?? '') !== '' && !$departmentId) {
            $teacherErrors[] = 'Select a valid department.';
        } elseif ($departmentId) {
            $check = db()->prepare('SELECT COUNT(*) FROM departments WHERE id=?');
            $check->execute([$departmentId]);
            if (!(int)$check->fetchColumn()) $teacherErrors[] = 'Select a valid department.';
        }
        if (!$teacherErrors) {
            $fullName = compose_full_name($createInput['first_name'], $createInput['middle_name'], $createInput['last_name']);
            $issuedTemp = issue_default_temporary_password();
            db()->beginTransaction();
            try {
                db()->prepare('INSERT INTO users(full_name,username,email,password_hash,role,must_change_password) VALUES(?,?,?,?,?,1)')->execute([
                    $fullName,
                    $username,
                    $createInput['email'],
                    $issuedTemp['hash'],
                    'staff',
                ]);
                $userId = (int)db()->lastInsertId();
                sync_teacher($userId);
                $find = db()->prepare('SELECT id FROM teachers WHERE user_id=?');
                $find->execute([$userId]);
                $teacherId = (int)$find->fetchColumn();
                db()->prepare('UPDATE teachers SET first_name=?, middle_name=?, last_name=?, phone=?, address=?, department_id=? WHERE id=?')->execute([
                    $createInput['first_name'],
                    $createInput['middle_name'] !== '' ? $createInput['middle_name'] : null,
                    $createInput['last_name'],
                    $createInput['phone'] !== '' ? $createInput['phone'] : null,
                    $createInput['address'] !== '' ? $createInput['address'] : null,
                    $departmentId,
                    $teacherId,
                ]);
                audit('CREATE', 'teacher', $teacherId, 'Created teacher account and profile '.$username);
                db()->commit();
                flash('success', 'Teacher created. Username is '.$username.'; temporary password is '.$issuedTemp['plain'].'. Share it privately — they must change it on first sign-in.');
                redirect('teachers');
            } catch (PDOException $exception) {
                db()->rollBack();
                error_log($exception->getMessage());
                $teacherErrors[] = 'That username or email is already in use, or the teacher could not be created.';
            }
        }
        $createInput['department_id'] = $departmentId ?: '';
        $createInput['username'] = $username;
    }
}

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT) ?: null;
if ($editId) {
    $statement = db()->prepare("SELECT t.*, u.full_name, u.username, u.email, u.is_active, u.id AS user_id, d.name AS department_name, (SELECT COUNT(DISTINCT a.block_id) FROM block_subject_assignments a WHERE a.teacher_id=t.id) AS block_count FROM teachers t JOIN users u ON u.id=t.user_id LEFT JOIN departments d ON d.id=t.department_id WHERE t.id=? AND u.role='staff'");
    $statement->execute([$editId]);
    $editTeacher = $statement->fetch() ?: null;
    if (!$editTeacher) {
        deny_request('Teacher not found. Return to Teachers and try again.', 404);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$canManage) {
            deny_request('Only an administrator can edit teacher records from this directory.', 403);
        }
        verify_csrf();
        $input = [
            'first_name' => trim((string)($_POST['first_name'] ?? '')),
            'middle_name' => trim((string)($_POST['middle_name'] ?? '')),
            'last_name' => trim((string)($_POST['last_name'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            'phone' => trim((string)($_POST['phone'] ?? '')),
            'address' => trim((string)($_POST['address'] ?? '')),
            'department_id' => $_POST['department_id'] ?? '',
        ];
        $resetTemp = isset($_POST['reset_temp_password']);
        $teacherErrors = validate_teacher_profile($input, false);
        $username = '';
        if (!$teacherErrors) {
            $username = allocate_login_username($input['last_name'], $input['first_name'], null, (int)$editTeacher['user_id']);
            if ($username === '') $teacherErrors[] = 'Could not build a username from the first and last name.';
        }
        $departmentId = filter_var($input['department_id'], FILTER_VALIDATE_INT) ?: null;
        if (($input['department_id'] ?? '') !== '' && !$departmentId) {
            $teacherErrors[] = 'Select a valid department.';
        } elseif ($departmentId) {
            $check = db()->prepare('SELECT COUNT(*) FROM departments WHERE id=?');
            $check->execute([$departmentId]);
            if (!(int)$check->fetchColumn()) $teacherErrors[] = 'Select a valid department.';
        }
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if (!$teacherErrors) {
            $fullName = compose_full_name($input['first_name'], $input['middle_name'], $input['last_name']);
            $issuedTemp = null;
            db()->beginTransaction();
            try {
                $params = [$fullName, $username, $input['email'], $isActive];
                $sql = 'UPDATE users SET full_name=?, username=?, email=?, is_active=?';
                if ($resetTemp) {
                    $issuedTemp = issue_default_temporary_password();
                    $sql .= ', password_hash=?, must_change_password=1, failed_attempts=0, locked_until=NULL';
                    $params[] = $issuedTemp['hash'];
                }
                $sql .= ' WHERE id=?';
                $params[] = $editTeacher['user_id'];
                db()->prepare($sql)->execute($params);
                db()->prepare('UPDATE teachers SET first_name=?, middle_name=?, last_name=?, phone=?, address=?, department_id=? WHERE id=?')->execute([
                    $input['first_name'],
                    $input['middle_name'] !== '' ? $input['middle_name'] : null,
                    $input['last_name'],
                    $input['phone'] !== '' ? $input['phone'] : null,
                    $input['address'] !== '' ? $input['address'] : null,
                    $departmentId,
                    $editId,
                ]);
                audit('UPDATE', 'teacher', (int)$editId, $resetTemp ? 'Updated teacher and reset temporary password' : 'Updated teacher profile and account');
                db()->commit();
                flash('success', $resetTemp && $issuedTemp
                    ? 'Teacher saved. Username is '.$username.'. Temporary password is '.$issuedTemp['plain'].'; share it privately — they must change it on next sign-in.'
                    : 'Teacher record saved. Username is '.$username.'.');
                redirect('teachers');
            } catch (PDOException $exception) {
                db()->rollBack();
                error_log($exception->getMessage());
                $teacherErrors[] = 'That username or email is already in use, or the record could not be saved.';
            }
        }
        $editTeacher = array_merge($editTeacher, $input, [
            'department_id' => $departmentId,
            'is_active' => $isActive,
            'username' => $username !== '' ? $username : ($editTeacher['username'] ?? ''),
        ]);
    }
}

$teacherSearch = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$teacherLike = '%' . strtr($teacherSearch, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
$statement = db()->prepare("SELECT COUNT(*) FROM teachers t JOIN users u ON u.id=t.user_id WHERE u.role='staff' AND (u.full_name LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!' OR COALESCE(t.last_name,'') LIKE ? ESCAPE '!' OR COALESCE(t.first_name,'') LIKE ? ESCAPE '!')");
$statement->execute([$teacherLike, $teacherLike, $teacherLike, $teacherLike]);
$teacherTotal = (int)$statement->fetchColumn();
$teacherPages = max(1, (int)ceil($teacherTotal / 20));
$teacherPage = max(1, min($teacherPages, (int)($_GET['p'] ?? 1)));
$teacherOffset = ($teacherPage - 1) * 20;
$statement = db()->prepare("SELECT t.id, t.first_name, t.middle_name, t.last_name, t.phone, u.id AS user_id, u.full_name, u.email, u.is_active, d.name AS department_name, (SELECT COUNT(DISTINCT a.block_id) FROM block_subject_assignments a WHERE a.teacher_id=t.id) AS block_count FROM teachers t JOIN users u ON u.id=t.user_id LEFT JOIN departments d ON d.id=t.department_id WHERE u.role='staff' AND (u.full_name LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!' OR COALESCE(t.last_name,'') LIKE ? ESCAPE '!' OR COALESCE(t.first_name,'') LIKE ? ESCAPE '!') ORDER BY COALESCE(t.last_name, u.full_name), COALESCE(t.first_name, u.full_name), t.id LIMIT 20 OFFSET $teacherOffset");
$statement->execute([$teacherLike, $teacherLike, $teacherLike, $teacherLike]);
$teacherRows = $statement->fetchAll();
