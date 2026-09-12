<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

$page = $_GET['page'] ?? (user() ? home_page_for_user() : 'login');
$allowed = ['login','logout','setup','change_password','dashboard','students','student_form','profile','teacher_profile','users','user_form','logs','settings','report','blocks','teachers','registrars','courses','departments','subjects','grading','my_subjects','assigned_blocks','submit_scores','student_subjects','enroll','enrollments','enrollment_document','blocking','assign_teachers','registrar_home'];
if (!in_array($page, $allowed, true)) { http_response_code(404); $page = 'not_found'; }

if ($page === 'logout') {
    $token = (string)($_POST['csrf'] ?? $_GET['csrf'] ?? '');
    if ($token === '' || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        deny_request('Invalid or expired sign-out link. Use Sign out from the navigation bar.', 419);
    }
    if (user()) audit('LOGOUT', 'session');
    $_SESSION = []; if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']); }
    session_destroy(); redirect('login');
}

$loginError = '';
$loginUsername = '';
$setupAvailable = false;
if ($page === 'login') {
    $setupAvailable = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
}
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $loginUsername = trim((string)($_POST['username'] ?? ''));
    $loginPassword = (string)($_POST['password'] ?? '');
    $genericFail = 'Invalid credentials or account unavailable. Check your username and password, then try again. After five failed attempts, the account locks for 15 minutes.';

    $stmt = db()->prepare("SELECT id, full_name, username, email, password_hash, role, is_active, can_enrollments, can_blocking, can_assign_teachers, can_view_students, failed_attempts, locked_until, must_change_password FROM users WHERE username = ? AND role IN ('admin','staff','registrar') LIMIT 1");
    $stmt->execute([$loginUsername]);
    $account = $stmt->fetch();

    if ($account) {
        $locked = $account['locked_until'] && strtotime($account['locked_until']) > time();
        if (!$account['is_active'] || $locked || !password_verify($loginPassword, $account['password_hash'])) {
            if ($account['is_active'] && !$locked) {
                $attempts = (int)$account['failed_attempts'] + 1;
                $until = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
                db()->prepare('UPDATE users SET failed_attempts=?, locked_until=? WHERE id=?')->execute([$attempts >= 5 ? 0 : $attempts, $until, $account['id']]);
            }
            $loginError = $genericFail;
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = session_user_from_staff($account);
            $forceChange = !empty($account['must_change_password']);
            $_SESSION['must_change_password'] = $forceChange;
            db()->prepare('UPDATE users SET failed_attempts=0, locked_until=NULL, last_login_at=NOW() WHERE id=?')->execute([$account['id']]);
            if (($account['role'] ?? '') === 'staff') {
                sync_teacher((int)$account['id']);
            }
            audit('LOGIN', 'session');
            redirect($forceChange ? 'change_password' : home_page_for_user());
        }
    } else {
        $stmt = db()->prepare('SELECT id, first_name, last_name, username, email, password_hash, is_active, failed_attempts, locked_until, must_change_password FROM students WHERE username = ? LIMIT 1');
        $stmt->execute([$loginUsername]);
        $student = $stmt->fetch();
        $locked = $student && $student['locked_until'] && strtotime($student['locked_until']) > time();
        $hash = $student['password_hash'] ?? '';
        if (!$student || !$student['is_active'] || $hash === '' || $locked || !password_verify($loginPassword, $hash)) {
            if ($student && $student['is_active'] && !$locked) {
                $attempts = (int)$student['failed_attempts'] + 1;
                $until = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
                db()->prepare('UPDATE students SET failed_attempts=?, locked_until=? WHERE id=?')->execute([$attempts >= 5 ? 0 : $attempts, $until, $student['id']]);
            }
            $loginError = $genericFail;
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = session_user_from_student($student);
            $forceChange = !empty($student['must_change_password']);
            $_SESSION['must_change_password'] = $forceChange;
            db()->prepare('UPDATE students SET failed_attempts=0, locked_until=NULL, last_login_at=NOW() WHERE id=?')->execute([$student['id']]);
            audit('LOGIN', 'session');
            redirect($forceChange ? 'change_password' : home_page_for_user());
        }
    }
}

$changePasswordErrors = [];
if ($page === 'change_password') {
    require_auth();
    if (empty($_SESSION['must_change_password'])) {
        redirect(home_page_for_user());
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');
        if (!password_is_strong($password)) {
            $changePasswordErrors[] = 'Password must be at least 12 characters with uppercase, lowercase, and a number.';
        }
        if ($password !== $confirm) {
            $changePasswordErrors[] = 'Password confirmation does not match.';
        }
        if (!$changePasswordErrors) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ((user()['account_type'] ?? 'user') === 'student') {
                db()->prepare('UPDATE students SET password_hash=?, must_change_password=0 WHERE id=?')->execute([$hash, user()['id']]);
                audit('CHANGE_PASSWORD', 'student', (int)user()['id'], 'Replaced temporary password');
            } else {
                db()->prepare('UPDATE users SET password_hash=?, must_change_password=0 WHERE id=?')->execute([$hash, user()['id']]);
                audit('CHANGE_PASSWORD', 'user', (int)user()['id'], 'Replaced temporary password');
            }
            unset($_SESSION['must_change_password']);
            flash('success', 'Password updated. You can continue.');
            redirect(home_page_for_user());
        }
    }
}

if ($page === 'setup') {
    $count = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count > 0) { http_response_code(404); exit('Setup is no longer available.'); }
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf(); $fullName = trim((string)($_POST['full_name'] ?? '')); $username = trim((string)($_POST['username'] ?? '')); $email = trim((string)($_POST['email'] ?? '')); $password = (string)($_POST['password'] ?? '');
        if (!valid_full_name($fullName)) $errors[]='Enter a valid full name using letters and common name punctuation only.';
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) $errors[]='Username must be 3-50 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[]='Valid email is required.';
        if (strlen($password)<12 || !preg_match('/[A-Z]/',$password) || !preg_match('/[a-z]/',$password) || !preg_match('/\d/',$password)) $errors[]='Password must be 12+ characters with uppercase, lowercase, and a number.';
        if (!$errors) { $s=db()->prepare("INSERT INTO users(full_name,username,email,password_hash,role) VALUES(?,?,?,?, 'admin')"); $s->execute([$fullName,$username,$email,password_hash($password,PASSWORD_DEFAULT)]); flash('success','Administrator created. You may now sign in.'); redirect('login'); }
    }
}

if (!in_array($page, ['login','setup','enroll','not_found'], true)) require_auth();

if (in_array($page, ['courses','departments'], true)) require dirname(__DIR__) . '/app/catalog-controller.php';
if (in_array($page, ['students','student_form'], true)) $courseOptions=db()->query('SELECT id,name FROM courses ORDER BY name')->fetchAll();
if ($page === 'teachers') require dirname(__DIR__) . '/app/teachers-controller.php';
if ($page === 'registrars') require dirname(__DIR__) . '/app/registrars-controller.php';
if ($page === 'blocks') require dirname(__DIR__) . '/app/blocks-controller.php';
if ($page === 'subjects') require dirname(__DIR__) . '/app/subjects-controller.php';
if ($page === 'blocking') require dirname(__DIR__) . '/app/blocking-controller.php';
if ($page === 'assign_teachers') require dirname(__DIR__) . '/app/assign-teachers-controller.php';
if ($page === 'registrar_home') {
    require_role(['registrar']);
    if (registrar_has_any_capability()) {
        redirect(home_page_for_user());
    }
}
if ($page === 'grading') require dirname(__DIR__) . '/app/grading-controller.php';
if (in_array($page, ['my_subjects','assigned_blocks','submit_scores'], true)) require dirname(__DIR__) . '/app/teacher-grading-controller.php';
if ($page === 'student_subjects') require dirname(__DIR__) . '/app/student-portal-controller.php';
if (in_array($page, ['enroll','enrollments','enrollment_document'], true)) require dirname(__DIR__) . '/app/enrollment-controller.php';

if ($page === 'students') {
    require_registrar_capability('view_students');
    require_once dirname(__DIR__) . '/app/students-list.php';
    $studentsReadOnly = (user()['role'] ?? '') === 'registrar';
    $studentCreateErrors = [];
    $studentInput = [];
    $studentFilters = students_list_filters_from_request();
    $viewStudentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    $viewStudent = null;
    $viewStudentBlocks = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($studentsReadOnly) {
            deny_request('Registrars can view student records but cannot edit them.', 403);
        }
        verify_csrf();
        $studentInput = array_map(fn($value) => trim((string)$value), $_POST);
        $studentCreateErrors = validate_student($studentInput, false, true);
        $studentInput['course']=course_name($studentInput['course_id']??null);
        if (trim((string)($studentInput['student_number'] ?? '')) !== '') $studentCreateErrors[] = 'Leave Student ID blank. New students receive an automatic numeric ID when created.';
        if (!in_array($studentInput['academic_status'] ?? '', academic_statuses(), true)) $studentCreateErrors[] = 'Select a valid academic status.';
        $username = '';
        if (!$studentCreateErrors) {
            $username = allocate_login_username($studentInput['last_name'], $studentInput['first_name']);
            if ($username === '') $studentCreateErrors[] = 'Could not build a username from the first and last name.';
            $studentInput['student_number'] = allocate_student_number();
        }
        if (!$studentCreateErrors) {
            $middle = trim((string)($studentInput['middle_name'] ?? ''));
            if ($middle !== '' && !valid_person_name($middle)) $studentCreateErrors[] = 'Middle name must use letters and common name punctuation only.';
            $display = trim((string)($studentInput['display_name'] ?? ''));
            if ($display !== '' && !valid_person_name($display) && !valid_full_name($display)) $studentCreateErrors[] = 'Display name must use letters and common name punctuation only.';
            $duplicateName = student_duplicate_name_message($studentInput);
            if ($duplicateName) $studentCreateErrors[] = $duplicateName;
        }
        if (!$studentCreateErrors) {
            try {
                $sql='INSERT INTO students(student_number,first_name,middle_name,last_name,display_name,email,phone,address,course,course_id,year_level,academic_status,username,password_hash,must_change_password) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)';
                db()->prepare($sql)->execute([
                    $studentInput['student_number'],
                    $studentInput['first_name'],
                    trim((string)($studentInput['middle_name'] ?? '')) ?: null,
                    $studentInput['last_name'],
                    trim((string)($studentInput['display_name'] ?? '')) ?: null,
                    $studentInput['email'],
                    $studentInput['phone']?:null,
                    $studentInput['address']?:null,
                    $studentInput['course'],
                    $studentInput['course_id'],
                    $studentInput['year_level'],
                    $studentInput['academic_status'],
                    $username,
                    ($temp = issue_default_temporary_password())['hash'],
                ]);
                $newStudentId=(int)db()->lastInsertId(); audit('CREATE','student',$newStudentId,'Created student record with login '.$username);
                flash('success','Student created. Username is '.$username.'; temporary password is '.$temp['plain'].'. Share it privately — they must change it on first sign-in.'); redirect('students');
            } catch (PDOException $exception) {
                $studentCreateErrors[] = 'That student number or username is already in use.';
            }
        }
    }

    $list = students_list_page($studentFilters);
    $students = $list['rows'];
    $studentTotal = $list['total'];
    $studentPages = $list['pages'];
    $studentPage = $list['page'];
    $q = $studentFilters['q'];
    $studentBlocks = [];
    $filterQs = static function (array $overrides = []) use ($studentFilters): string {
        return http_build_query(students_list_query_params($studentFilters, $overrides));
    };
    $hasExtraFilters = $studentFilters['q'] !== ''
        || $studentFilters['course_id']
        || $studentFilters['year_level']
        || $studentFilters['academic_status']
        || ($studentFilters['block'] ?? 'all') !== 'all'
        || ($studentFilters['active'] ?? 'all') !== 'all';

    if ($viewStudentId) {
        $vs = db()->prepare('SELECT s.*, u.full_name AS approver_name FROM students s LEFT JOIN users u ON u.id=s.enrollment_approved_by WHERE s.id=?');
        $vs->execute([$viewStudentId]);
        $viewStudent = $vs->fetch() ?: null;
        if ($viewStudent) {
            $vb = db()->prepare('SELECT b.name FROM block_students bs JOIN blocks b ON b.id=bs.block_id WHERE bs.student_id=? ORDER BY b.name');
            $vb->execute([$viewStudentId]);
            $viewStudentBlocks = $vb->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $viewStudentId = null;
        }
    }
}
if ($page === 'student_form') {
    require_role(['admin']); $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT) ?: null; $student=null;
    if ($id) { $s=db()->prepare('SELECT * FROM students WHERE id=?'); $s->execute([$id]); $student=$s->fetch(); if(!$student){http_response_code(404);exit('Student not found');} }
    $studentBlocks = [];
    $studentSubjectGrades = [];
    $enrollmentApproverName = null;
    $enrollmentApprovedAt = null;
    if ($id) {
        $blockStmt = db()->prepare('SELECT b.id, b.name FROM block_students bs JOIN blocks b ON b.id=bs.block_id WHERE bs.student_id=? ORDER BY b.name');
        $blockStmt->execute([$id]);
        $studentBlocks = $blockStmt->fetchAll();
        require_once dirname(__DIR__) . '/app/grading.php';
        $studentSubjectGrades = student_subject_grades(db(), (int)$id);
        if (!empty($student['enrollment_approved_by'])) {
            $ap = db()->prepare('SELECT full_name FROM users WHERE id=?');
            $ap->execute([(int)$student['enrollment_approved_by']]);
            $enrollmentApproverName = $ap->fetchColumn() ?: null;
            $enrollmentApprovedAt = $student['enrollment_approved_at'] ?? null;
        }
    }
    $errors=[];
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        verify_csrf(); $data=array_map(fn($v)=>trim((string)$v),$_POST); $errors=validate_student($data, false, !$id); $data['course']=course_name($data['course_id']??null);
        if (!$id && trim((string)($data['student_number'] ?? '')) !== '') $errors[] = 'Leave Student ID blank. New students receive an automatic numeric ID when created.';
        $resetTemp = isset($_POST['reset_temp_password']);
        $username = '';
        if (!$errors) {
            $username = allocate_login_username($data['last_name'], $data['first_name'], $id ?: null);
            if ($username === '') $errors[] = 'Could not build a username from the first and last name.';
            if ($id) {
                $data['student_number'] = canonical_student_number_for_update($data['student_number'] ?? '', $id, $errors);
            } else {
                $data['student_number'] = allocate_student_number();
            }
        }
        if (!$errors) {
            $middle = trim((string)($data['middle_name'] ?? ''));
            if ($middle !== '' && !valid_person_name($middle)) $errors[] = 'Middle name must use letters and common name punctuation only.';
            $display = trim((string)($data['display_name'] ?? ''));
            if ($display !== '' && !valid_person_name($display) && !valid_full_name($display)) $errors[] = 'Display name must use letters and common name punctuation only.';
            $duplicateName = student_duplicate_name_message($data, $id ?: null);
            if ($duplicateName) $errors[] = $duplicateName;
        }
        if (!$errors) {
            try {
                $issuedTemp = null;
                if ($id) {
                    $params = [$data['student_number'],$data['first_name'],trim((string)($data['middle_name'] ?? '')) ?: null,$data['last_name'],trim((string)($data['display_name'] ?? '')) ?: null,$data['email'],$data['phone']?:null,$data['address']?:null,$data['course'],$data['course_id'],$data['year_level'],$data['academic_status'],isset($data['is_active'])?1:0,$username];
                    $sql='UPDATE students SET student_number=?,first_name=?,middle_name=?,last_name=?,display_name=?,email=?,phone=?,address=?,course=?,course_id=?,year_level=?,academic_status=?,is_active=?,username=?';
                    if ($resetTemp || empty($student['password_hash'])) {
                        $issuedTemp = issue_default_temporary_password();
                        $sql .= $resetTemp
                            ? ',password_hash=?,must_change_password=1,failed_attempts=0,locked_until=NULL'
                            : ',password_hash=?,must_change_password=1';
                        $params[] = $issuedTemp['hash'];
                    }
                    $sql .= ' WHERE id=?';
                    $params[] = $id;
                    db()->prepare($sql)->execute($params);
                    audit('UPDATE','student',(int)$id,$resetTemp ? 'Updated student and reset temporary password' : 'Updated student record');
                    if ($resetTemp && $issuedTemp) flash('success', 'Student saved. Username is '.$username.'. Temporary password is '.$issuedTemp['plain'].'; share it privately — they must change it on next sign-in.');
                    elseif ($issuedTemp) flash('success', 'Student saved. Username is '.$username.'. Temporary password is '.$issuedTemp['plain'].'; share it privately.');
                    else flash('success', 'Student record saved. Username is '.$username.'.');
                } else {
                    $issuedTemp = issue_default_temporary_password();
                    $sql='INSERT INTO students(student_number,first_name,middle_name,last_name,display_name,email,phone,address,course,course_id,year_level,academic_status,username,password_hash,must_change_password) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)';
                    db()->prepare($sql)->execute([$data['student_number'],$data['first_name'],trim((string)($data['middle_name'] ?? '')) ?: null,$data['last_name'],trim((string)($data['display_name'] ?? '')) ?: null,$data['email'],$data['phone']?:null,$data['address']?:null,$data['course'],$data['course_id'],$data['year_level'],$data['academic_status'],$username,$issuedTemp['hash']]);
                    $id=(int)db()->lastInsertId(); audit('CREATE','student',$id,'Created student record with login '.$username);
                    flash('success', 'Student created. Username is '.$username.'; temporary password is '.$issuedTemp['plain'].'. Share it privately — they must change it on first sign-in.');
                }
                redirect('students');
            } catch (PDOException $exception) {
                $errors[] = 'That student number or username is already in use.';
            }
        }
        $student=array_merge($data, ['username' => $username]);
    }
}
if ($page === 'profile') {
    require_role(['student']); $s=db()->prepare('SELECT * FROM students WHERE id=?');$s->execute([user()['id']]);$student=$s->fetch();
    if (!$student) { deny_request('No student record is linked to this account.', 404); }
    $errors=[];
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        verify_csrf();
        $data = [
            'display_name' => trim((string)($_POST['display_name'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            'phone' => trim((string)($_POST['phone'] ?? '')),
            'address' => trim((string)($_POST['address'] ?? '')),
        ];
        $errors = validate_student($data, true);
        if ($data['display_name'] !== '' && !valid_person_name($data['display_name']) && !valid_full_name($data['display_name'])) {
            $errors[] = 'Display name must use letters and common name punctuation only.';
        }
        if (!$errors) {
            db()->prepare('UPDATE students SET display_name=?,email=?,phone=?,address=? WHERE id=?')->execute([
                $data['display_name'] !== '' ? $data['display_name'] : null,
                $data['email'],
                $data['phone'] !== '' ? $data['phone'] : null,
                $data['address'] !== '' ? $data['address'] : null,
                $student['id'],
            ]);
            $student = array_merge($student, $data);
            $_SESSION['user'] = session_user_from_student($student);
            audit('UPDATE_PROFILE', 'student', (int)$student['id'], 'Student updated own profile');
            flash('success', 'Profile updated.');
            redirect('profile');
        }
        $student = array_merge($student, $data);
    }
}
if ($page === 'teacher_profile') {
    require_role(['staff']);
    sync_teacher((int)user()['id']);
    $stmt = db()->prepare("SELECT t.*, u.full_name, u.email, u.username FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.user_id=? AND u.role='staff'");
    $stmt->execute([user()['id']]);
    $teacherProfile = $stmt->fetch();
    if (!$teacherProfile) { deny_request('No teacher record is linked to this account.', 404); }
    $profileErrors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $input = [
            'first_name' => trim((string)($_POST['first_name'] ?? '')),
            'middle_name' => trim((string)($_POST['middle_name'] ?? '')),
            'last_name' => trim((string)($_POST['last_name'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            'phone' => trim((string)($_POST['phone'] ?? '')),
            'address' => trim((string)($_POST['address'] ?? '')),
        ];
        $profileErrors = validate_teacher_profile($input, false);
        if (!$profileErrors) {
            $fullName = compose_full_name($input['first_name'], $input['middle_name'], $input['last_name']);
            db()->beginTransaction();
            try {
                db()->prepare('UPDATE users SET full_name=?, email=? WHERE id=?')->execute([$fullName, $input['email'], user()['id']]);
                db()->prepare('UPDATE teachers SET first_name=?, middle_name=?, last_name=?, phone=?, address=? WHERE id=?')->execute([
                    $input['first_name'],
                    $input['middle_name'] !== '' ? $input['middle_name'] : null,
                    $input['last_name'],
                    $input['phone'] !== '' ? $input['phone'] : null,
                    $input['address'] !== '' ? $input['address'] : null,
                    $teacherProfile['id'],
                ]);
                audit('UPDATE_PROFILE', 'teacher', (int)$teacherProfile['id'], 'Teacher updated own profile');
                db()->commit();
                $_SESSION['user']['full_name'] = $fullName;
                $_SESSION['user']['email'] = $input['email'];
                flash('success', 'Profile updated.');
                redirect('teacher_profile');
            } catch (PDOException $exception) {
                db()->rollBack();
                error_log($exception->getMessage());
                $profileErrors[] = 'That email is already in use, or the profile could not be saved.';
            }
        }
        $teacherProfile = array_merge($teacherProfile, $input);
    }
}
if ($page === 'users') {
    require_role(['admin']);
    $createErrors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role = 'admin';
        if (!valid_full_name($fullName)) $createErrors[] = 'Enter a valid full name using letters and common name punctuation only.';
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) $createErrors[] = 'Username must be 3-50 characters and use only letters, numbers, dots, underscores, or hyphens.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $createErrors[] = 'Enter a valid email address.';
        if ($username !== '' && username_taken($username)) $createErrors[] = 'That username is already in use.';
        if (!$createErrors) {
            try {
                $issuedTemp = issue_temporary_password();
                db()->prepare('INSERT INTO users(full_name,username,email,password_hash,role,must_change_password) VALUES(?,?,?,?,?,1)')->execute([$fullName,$username,$email,$issuedTemp['hash'],$role]);
                $newId = (int)db()->lastInsertId();
                audit('CREATE','user',$newId,'Created admin account with temporary password');
                flash('success', 'Administrator created. Username '.$username.'; temporary password is '.$issuedTemp['plain'].'. Share it privately — they must replace it on first sign-in.');
                redirect('users');
            } catch (PDOException $exception) {
                $createErrors[] = 'That username or email address is already in use.';
            }
        }
    }
    $users=db()->query("SELECT id,full_name,username,email,role,is_active,last_login_at,created_at FROM users WHERE role='admin' ORDER BY full_name")->fetchAll();
}
if ($page === 'user_form') {
    require_role(['admin']);
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
    $account = null;
    if ($id) {
        $s = db()->prepare("SELECT id,full_name,username,email,role,is_active FROM users WHERE id=? AND role='admin'");
        $s->execute([$id]);
        $account = $s->fetch();
        if (!$account) {
            $roleCheck = db()->prepare('SELECT role FROM users WHERE id=?');
            $roleCheck->execute([$id]);
            $foundRole = $roleCheck->fetchColumn();
            if ($foundRole === 'staff') {
                flash('error', 'Teacher accounts are managed under Teachers.');
                redirect('teachers');
            }
            if ($foundRole === 'registrar') {
                flash('error', 'Registrar accounts are managed under Registrars.');
                redirect('registrars');
            }
            http_response_code(404);
            exit('User not found');
        }
    }
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role = 'admin';
        $resetTemp = isset($_POST['reset_temp_password']);
        if (!valid_full_name($fullName)) $errors[] = 'Enter a valid full name.';
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) $errors[] = 'Invalid username.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
        if ($id === (int)user()['id'] && !isset($_POST['is_active'])) $errors[] = 'You cannot deactivate your own administrator access.';
        if ($username !== '' && username_taken($username, null, $id ?: null)) $errors[] = 'That username is already in use.';
        if (!$errors) {
            try {
                $issuedTemp = null;
                if ($id) {
                    $params = [$fullName, $username, $email, $role, isset($_POST['is_active']) ? 1 : 0];
                    $sql = 'UPDATE users SET full_name=?,username=?,email=?,role=?,is_active=?';
                    if ($resetTemp) {
                        $issuedTemp = issue_temporary_password();
                        $sql .= ',password_hash=?,must_change_password=1,failed_attempts=0,locked_until=NULL';
                        $params[] = $issuedTemp['hash'];
                    }
                    $sql .= ' WHERE id=? AND role=\'admin\'';
                    $params[] = $id;
                    db()->prepare($sql)->execute($params);
                    audit('UPDATE', 'user', (int)$id, $resetTemp ? 'Updated admin and reset temporary password' : 'Updated admin account');
                    if ($resetTemp && $issuedTemp) flash('success', 'Administrator saved. Temporary password is '.$issuedTemp['plain'].'; share it privately — they must change it on next sign-in.');
                    else flash('success', 'Administrator account saved.');
                } else {
                    $issuedTemp = issue_temporary_password();
                    db()->prepare('INSERT INTO users(full_name,username,email,password_hash,role,must_change_password) VALUES(?,?,?,?,?,1)')->execute([$fullName, $username, $email, $issuedTemp['hash'], $role]);
                    $id = (int)db()->lastInsertId();
                    audit('CREATE', 'user', $id, 'Created admin account with temporary password');
                    flash('success', 'Administrator created. Username '.$username.'; temporary password is '.$issuedTemp['plain'].'. Share it privately — they must replace it on first sign-in.');
                }
                redirect('users');
            } catch (PDOException $e) {
                $errors[] = 'Username or email already exists.';
            }
        }
        $account = ['full_name' => $fullName, 'username' => $username, 'email' => $email, 'role' => $role, 'is_active' => isset($_POST['is_active'])];
    }
}
if ($page === 'logs') { require_role(['admin']);$logs=db()->query('SELECT a.*,u.username FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 200')->fetchAll(); }
if ($page === 'settings') { require_role(['admin']); if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();foreach(['school_name','maintenance_notice'] as $key){$value=mb_substr(trim((string)($_POST[$key]??'')),0,255);db()->prepare('INSERT INTO system_settings(setting_key,setting_value,updated_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)')->execute([$key,$value,user()['id']]);}audit('UPDATE','settings',null,'Updated system settings');flash('success','Settings saved.');redirect('settings');}$settings=[];foreach(db()->query('SELECT setting_key,setting_value FROM system_settings') as $r)$settings[$r['setting_key']]=$r['setting_value']; }
if ($page === 'report') { require_role(['admin']);$rows=db()->query('SELECT student_number,last_name,first_name,course,year_level,academic_status FROM students WHERE is_active=1 ORDER BY course,last_name')->fetchAll();if(($_GET['format']??'')==='csv'){audit('EXPORT','student_report',null,'Exported CSV report');header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="student-report.csv"');$out=fopen('php://output','wb');fputcsv($out,array_keys($rows[0]??['student_number'=>'','last_name'=>'','first_name'=>'','course'=>'','year_level'=>'','academic_status'=>'']));foreach($rows as $r)fputcsv($out,$r);fclose($out);exit;} }
if ($page === 'dashboard') {
    require_auth();
    redirect(home_page_for_user());
}

function render_header(string $title): void {
    global $page;
    $f = take_flash();
    $activePage = [
        "student_form"=>"students",
        "user_form"=>"users",
        "teacher_profile"=>"teacher_profile",
        "my_subjects"=>"my_subjects",
        "assigned_blocks"=>"assigned_blocks",
        "submit_scores"=>"assigned_blocks",
        "student_subjects"=>"student_subjects",
        "enrollments"=>"enrollments",
        "grading"=>"grading",
    ][$page] ?? $page;
    $settings = system_settings();
    $schoolName = trim((string)($settings['school_name'] ?? ''));
    $maintenance = trim((string)($settings['maintenance_notice'] ?? ''));
    $role = user()['role'] ?? null;
    $brandHref = !empty($_SESSION['must_change_password'])
        ? '?page=change_password'
        : (user() ? '?page=' . home_page_for_user() : '?page=login');
    ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?><?=$schoolName!==''?' · '.e($schoolName):''?> - SSIS</title><link rel="stylesheet" href="style.css?v=<?=filemtime(__DIR__.'/style.css')?>"><script src="app.js?v=<?=filemtime(__DIR__.'/app.js')?>" defer></script></head><body class="page-<?=e($page)?>"><a class="skip-link" href="#main-content">Skip to main content</a><?php render_completion_announcement($f); ?><header><div class="wrap bar"><div class="brand-stack"><?php if(!empty($_SESSION['must_change_password'])):?><a class="brand" href="?page=change_password" aria-current="page">Secure Student IS</a><?php else:?><a class="brand" href="<?=e($brandHref)?>">Secure Student IS</a><?php endif;?><?php if($schoolName!==''):?><span class="brand-school"><?=e($schoolName)?></span><?php endif;?></div><?php if(user()):?><?php if(!empty($_SESSION['must_change_password'])):?><nav aria-label="Main navigation"><a href="?page=logout&amp;csrf=<?=e(csrf_token())?>">Sign out</a></nav><?php else:?><nav aria-label="Main navigation"><?php if($role==='admin'):?><?php
                $adminMorePages = ['courses','subjects','departments','grading','users','logs','settings'];
                $adminMoreActive = in_array($activePage, $adminMorePages, true);
                ?><span class="nav-group" role="group" aria-label="People"><a href="?page=students" <?=$activePage==="students"?'aria-current="page"':''?>>Students</a><a href="?page=teachers" <?=$activePage==="teachers"?'aria-current="page"':''?>>Teachers</a><a href="?page=registrars" <?=$activePage==="registrars"?'aria-current="page"':''?>>Registrars</a></span><span class="nav-group" role="group" aria-label="Academics"><a href="?page=blocks" <?=$activePage==="blocks"?'aria-current="page"':''?>>Blocks</a><a href="?page=blocking" <?=$activePage==="blocking"?'aria-current="page"':''?>>Blocking</a><a href="?page=assign_teachers" <?=$activePage==="assign_teachers"?'aria-current="page"':''?>>Assign teachers</a><a href="?page=enrollments" <?=$activePage==="enrollments"?'aria-current="page"':''?>>Enrollments</a></span><span class="nav-group" role="group" aria-label="Reports"><a href="?page=report" <?=$activePage==="report"?'aria-current="page"':''?>>Reports</a></span><details class="nav-more"><summary<?=$adminMoreActive?' aria-current="page"':''?>>More</summary><div class="nav-more-panel" role="group" aria-label="Catalog and administration"><a href="?page=courses" <?=$activePage==="courses"?'aria-current="page"':''?>>Courses</a><a href="?page=subjects" <?=$activePage==="subjects"?'aria-current="page"':''?>>Subjects</a><a href="?page=departments" <?=$activePage==="departments"?'aria-current="page"':''?>>Departments</a><a href="?page=grading" <?=$activePage==="grading"?'aria-current="page"':''?>>Grading system</a><a href="?page=users" <?=$activePage==="users"?'aria-current="page"':''?>>Administrators</a><a href="?page=logs" <?=$activePage==="logs"?'aria-current="page"':''?>>Audit logs</a><a href="?page=settings" <?=$activePage==="settings"?'aria-current="page"':''?>>Settings</a></div></details><?php elseif($role==='registrar'):?><?php if (user_can('enrollments')): ?><a href="?page=enrollments" <?=$activePage==="enrollments"?'aria-current="page"':''?>>Enrollments</a><?php endif; ?><?php if (user_can('view_students')): ?><a href="?page=students" <?=$activePage==="students"?'aria-current="page"':''?>>Students</a><?php endif; ?><?php if (user_can('blocking')): ?><a href="?page=blocking" <?=$activePage==="blocking"?'aria-current="page"':''?>>Blocking</a><?php endif; ?><?php if (user_can('assign_teachers')): ?><a href="?page=assign_teachers" <?=$activePage==="assign_teachers"?'aria-current="page"':''?>>Teachers</a><?php endif; ?><span class="session-who" title="<?=e((string)(user()['username'] ?? ''))?>"><strong><?=e((string)(user()['full_name'] ?? user()['username'] ?? ''))?></strong> <span class="badge"><?=e(role_label('registrar'))?></span></span><?php elseif($role==='staff'):?><a href="?page=assigned_blocks" <?=$activePage==="assigned_blocks"?'aria-current="page"':''?>>Assigned Blocks</a><a href="?page=my_subjects" <?=$activePage==="my_subjects"?'aria-current="page"':''?>>My Subjects</a><a href="?page=teacher_profile" <?=$activePage==="teacher_profile"?'aria-current="page"':''?>>My profile</a><?php elseif($role==='student'):?><a href="?page=student_subjects" <?=$activePage==="student_subjects"?'aria-current="page"':''?>>My Subjects</a><a href="?page=profile" <?=$activePage==="profile"?'aria-current="page"':''?>>My profile</a><span class="session-who" title="<?=e((string)(user()['username'] ?? ''))?>"><strong><?=e((string)(user()['full_name'] ?? user()['username'] ?? ''))?></strong> <span class="badge"><?=e(role_label('student'))?></span></span><?php endif;?><a href="?page=logout&amp;csrf=<?=e(csrf_token())?>">Sign out</a></nav><?php endif;?><?php elseif(in_array($page,['login','enroll','setup'],true)):?><nav aria-label="Main navigation"><a href="?page=enroll" <?=$page==='enroll'?'aria-current="page"':''?>>Enroll Now</a><a href="?page=login" <?=$page==='login'?'aria-current="page"':''?>>Sign in</a></nav><?php endif;?></div></header><?php if($maintenance!=='' && user()):?><div class="standing-announcement" role="status"><div class="wrap"><p><?=e($maintenance)?></p></div></div><?php endif;?><main id="main-content" class="wrap" tabindex="-1"><div id="modal-status" class="sr-only" role="status" aria-live="polite" aria-busy="false"></div>
<?php }
function render_footer(): void { ?></main></body></html><?php }
function errors(array $items): void { if($items):?><div class="notice error" role="alert"><ul><?php foreach($items as $x):?><li><?=e($x)?></li><?php endforeach;?></ul></div><?php endif; }

function render_completion_announcement(?array $flash): void
{
    if (!$flash) return;
    $isError = ($flash[0] ?? '') === 'error';
    $title = $isError ? 'Could not complete' : 'Completed';
    $role = $isError ? 'alert' : 'status';
    ?>
<aside class="announcement-layer" aria-label="<?=e($title)?>">
  <div class="announcement<?=$isError?' announcement-error':''?>" role="<?=e($role)?>" aria-labelledby="announcement-title" aria-describedby="announcement-body" data-announcement<?=$isError?'':' data-announcement-timeout="8000"'?>>
    <div class="announcement-copy">
      <p id="announcement-title" class="announcement-title"><?=e($title)?></p>
      <p id="announcement-body" class="announcement-body"><?=e((string)$flash[1])?></p>
    </div>
    <button type="button" class="icon-button secondary" data-dismiss-announcement aria-label="Dismiss announcement">×</button>
  </div>
</aside>
    <?php
}

function render_student_fields(array $student, array $courseOptions, array $studentBlocks, bool $isEdit): void {
    $year = (string)($student['year_level'] ?? '1');
    $studentNumberValue = student_number_input_value($student['student_number'] ?? '');
    ?>
    <fieldset class="form-section"><legend>Identity</legend>
    <div class="grid">
      <div><label for="field-student_number">Student ID</label><?php if($isEdit):?><input id="field-student_number" name="student_number" value="<?=e($studentNumberValue)?>" required pattern="\d{3,30}" inputmode="numeric" maxlength="30" title="Use numbers only. Old IDs with hyphens are shown without the hyphen while editing." spellcheck="false"><?php else:?><input id="field-student_number" value="Assigned automatically" disabled aria-describedby="student-id-help"><p class="muted" id="student-id-help">New students receive the next numeric ID on creation (<?=e((string)date('Y'))?>0001 style).</p><?php endif;?></div>
      <div><label for="field-first_name">First name</label><input id="field-first_name" name="first_name" value="<?=e((string)($student['first_name']??''))?>" required maxlength="100" autocomplete="given-name"></div>
      <div><label for="field-middle_name">Middle name <span class="muted">(optional)</span></label><input id="field-middle_name" name="middle_name" value="<?=e((string)($student['middle_name']??''))?>" maxlength="80" autocomplete="additional-name"></div>
      <div><label for="field-last_name">Last name</label><input id="field-last_name" name="last_name" value="<?=e((string)($student['last_name']??''))?>" required maxlength="100" autocomplete="family-name"></div>
      <div><label for="field-display_name">Display name <span class="muted">(optional)</span></label><input id="field-display_name" name="display_name" value="<?=e((string)($student['display_name']??''))?>" maxlength="150" autocomplete="nickname"><p class="muted">Shown next to Sign out in the student portal when set. Legal names stay on the record.</p></div>
    </div>
    <p class="muted">Names may include spaces and common punctuation (for example Mary Ann, De la Cruz).</p>
    </fieldset>
    <fieldset class="form-section"><legend>Program</legend>
    <div class="grid">
      <div><label for="student-course">Course</label><select id="student-course" name="course_id" required><option value="">Select a course</option><?php foreach($courseOptions as $option):?><option value="<?=$option['id']?>" <?=((string)($student['course_id']??'')===(string)$option['id'])?'selected':''?>><?=e($option['name'])?></option><?php endforeach;?></select><?php if(!$courseOptions):?><p class="muted">An administrator must add a course in Courses first.</p><?php endif;?></div>
      <div><label for="field-year_level">College year</label><select id="field-year_level" name="year_level" required><?php foreach(college_year_labels() as $y=>$label):?><option value="<?=$y?>" <?=$year===(string)$y?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></div>
      <div><label for="field-academic_status">Academic status</label><select id="field-academic_status" name="academic_status" required><?php foreach(academic_statuses() as $v):?><option value="<?=e($v)?>" <?=(($student['academic_status']??'Active')===$v)?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
    </div>
    </fieldset>
    <fieldset class="form-section"><legend>Contact</legend>
    <div class="grid">
      <div><label for="field-email">Email</label><input type="email" id="field-email" name="email" value="<?=e((string)($student['email']??''))?>" required maxlength="190"></div>
      <div><label for="field-phone">Phone <span class="muted">(optional)</span></label><input id="field-phone" name="phone" value="<?=e((string)($student['phone']??''))?>" maxlength="20"></div>
      <div><label for="field-address">Address <span class="muted">(optional)</span></label><input id="field-address" name="address" value="<?=e((string)($student['address']??''))?>" maxlength="255"></div>
    </div>
    </fieldset>
    <fieldset class="form-section"><legend>Assignment</legend>
    <p class="muted">Teachers come from block subject assignments. Manage who is in each block under Blocks.</p>
    <div class="grid">
      <div>
        <span class="label-text">Blocks</span>
        <?php if ($isEdit && $studentBlocks): ?>
        <ul class="subject-chips block-chips">
          <?php foreach ($studentBlocks as $block): ?>
          <li><a href="?page=blocks&amp;edit=<?=(int)$block['id']?>"><?=e($block['name'])?></a></li>
          <?php endforeach; ?>
        </ul>
        <?php elseif ($isEdit): ?>
        <p class="muted">Not in any block yet. <a href="?page=blocks">Open Blocks</a> to assign this student.</p>
        <?php else: ?>
        <p class="muted">After creating the student, add them to a block under Blocks.</p>
        <?php endif; ?>
      </div>
      <?php if($isEdit):?><div><label><input type="checkbox" name="is_active" value="1" class="checkbox" <?=($student['is_active']??false)?'checked':''?>> Record is active</label><p class="muted">Uncheck to deactivate without deleting. The record stays in the system.</p></div><?php endif;?>
    </div>
    </fieldset>
    <fieldset class="form-section"><legend>Student login</legend>
    <p class="muted">Username is built automatically from last name + first initial (for example Delos Santos, Brent → <strong>Delossantos_B</strong>). A one-time temporary password is shown after create.</p>
    <div class="grid">
      <div>
        <span class="label-text">Username</span>
        <p class="username-preview"><strong data-username-preview><?php
          $preview = trim((string)($student['username'] ?? ''));
          if ($preview === '') $preview = login_username_base((string)($student['last_name'] ?? ''), (string)($student['first_name'] ?? ''));
          echo e($preview !== '' ? $preview : '—');
        ?></strong></p>
      </div>
    </div>
    </fieldset>
    <?php
}

$pageTitles = [
    'login' => 'Sign in to Portal',
    'setup' => 'Create initial administrator',
    'change_password' => 'Choose a new password',
    'students' => 'Student records',
    'student_form' => 'Student form',
    'profile' => 'My profile',
    'teacher_profile' => 'My profile',
    'users' => 'Administrators',
    'user_form' => 'Administrator account',
    'logs' => 'Audit logs',
    'settings' => 'System settings',
    'report' => 'Active-student report',
    'blocks' => 'Blocks',
    'blocking' => 'Blocking',
    'assign_teachers' => 'Assign teachers',
    'registrar_home' => 'Registrar home',
    'teachers' => 'Teachers',
    'registrars' => 'Registrars',
    'courses' => 'Courses',
    'subjects' => 'Subjects',
    'departments' => 'Departments',
    'grading' => 'Grading system',
    'my_subjects' => 'My Subjects',
    'assigned_blocks' => 'Assigned Blocks',
    'submit_scores' => 'Enter scores',
    'student_subjects' => 'My Subjects',
    'enroll' => 'Enroll Now',
    'enrollments' => 'Enrollments',
    'not_found' => 'Page not found',
];
render_header($pageTitles[$page] ?? ucfirst(str_replace('_', ' ', $page)));
if ($page==='login'): ?>
<section class="card narrow"><h1>Sign in to Portal</h1><p class="muted">College and university portal for authorized accounts. New students apply with Enroll Now; sign-in opens after the registrar approves the application.</p><?php if($loginError):?><div class="notice error" role="alert"><?=e($loginError)?></div><?php endif;?><form method="post"><?=csrf_field()?><label for="field-username">Username</label><input id="field-username" name="username" autocomplete="username" required maxlength="50" spellcheck="false" autocapitalize="none" value="<?=e($loginUsername)?>"><label for="field-password">Password</label><?=password_field('field-password', 'password', ['autocomplete' => 'current-password', 'required' => true])?><div class="actions"><button>Sign in</button><a class="button secondary" href="?page=enroll">Enroll Now</a><?php if($setupAvailable):?><a href="?page=setup">First-time setup</a><?php endif;?></div></form></section>
<?php elseif($page==='setup'): ?><section class="card narrow"><h1>Create initial administrator</h1><?php errors($errors);?><form method="post"><?=csrf_field()?><label for="field-full_name">Full name</label><input id="field-full_name" name="full_name" required maxlength="150"><label for="field-username">Username</label><input id="field-username" name="username" required maxlength="50"><label for="field-email">Email</label><input type="email" id="field-email" name="email" required><label for="field-password">Password</label><?=password_field('field-password', 'password', ['required' => true, 'minlength' => '12', 'autocomplete' => 'new-password'])?><p class="muted">12+ characters, including uppercase, lowercase, and a number.</p><button>Create administrator</button></form></section>
<?php elseif($page==='change_password'): ?>
<section class="card narrow" aria-labelledby="change-password-title">
  <h1 id="change-password-title">Choose a new password</h1>
  <p>Signed in as <strong><?=e(user()['full_name'] ?? user()['username'])?></strong> <span class="badge"><?=e(role_label(user()['role']))?></span></p>
  <p class="muted">Access stays locked until you replace the temporary password. Enter a new password, confirm it, then continue.</p>
  <?php errors($changePasswordErrors); ?>
  <form method="post" data-change-password-form>
    <?=csrf_field()?>
    <label for="field-password">New password</label>
    <?=password_field('field-password', 'password', ['required' => true, 'minlength' => '12', 'autocomplete' => 'new-password', 'aria-describedby' => 'password-rules'])?>
    <ul id="password-rules" class="password-rules" data-password-rules>
      <li data-rule="length">At least 12 characters</li>
      <li data-rule="upper">One uppercase letter</li>
      <li data-rule="lower">One lowercase letter</li>
      <li data-rule="digit">One number</li>
    </ul>
    <label for="field-password-confirm">Confirm password</label>
    <?=password_field('field-password-confirm', 'password_confirm', ['required' => true, 'minlength' => '12', 'autocomplete' => 'new-password', 'aria-describedby' => 'password-match-status'])?>
    <p id="password-match-status" class="muted password-match" data-password-match role="status" aria-live="polite">Confirm must match the new password.</p>
    <div class="actions"><button>Save password and continue</button></div>
  </form>
</section>
<?php elseif(in_array($page,['courses','departments'],true)): require dirname(__DIR__) . '/app/catalog-view.php'; ?>
<?php elseif($page==='subjects'): require dirname(__DIR__) . '/app/subjects-view.php'; ?>
<?php elseif($page==='teachers'): require dirname(__DIR__) . '/app/teachers-view.php'; ?>
<?php elseif($page==='registrars'): require dirname(__DIR__) . '/app/registrars-view.php'; ?>
<?php elseif($page==='blocks'): require dirname(__DIR__) . '/app/blocks-view.php'; ?>
<?php elseif($page==='blocking'): require dirname(__DIR__) . '/app/blocking-view.php'; ?>
<?php elseif($page==='assign_teachers'): require dirname(__DIR__) . '/app/assign-teachers-view.php'; ?>
<?php elseif($page==='registrar_home'): ?>
<section class="card narrow">
  <h1>No duties assigned</h1>
  <p class="muted">Your registrar account is active, but an administrator has not granted Enrollments, Students, Blocking, or Teachers yet. Ask them to set your duties under People → Registrars.</p>
</section>
<?php elseif($page==='students'): ?>
<div class="bar">
  <div>
    <h1><?=$studentsReadOnly ? 'Students' : 'Student records'?></h1>
    <p class="muted"><?=$studentsReadOnly
      ? 'View and search student records. Editing and password resets stay with administrators.'
      : 'Add the academic record here. Username is auto-built from last name + first initial; a one-time temporary password is shown after create. Place unblocked students under Blocking; assign teachers under Assign teachers.'?></p>
  </div>
  <?php if (!$studentsReadOnly): ?><button type="button" data-open-dialog="add-student-dialog">Add student</button><?php endif; ?>
</div>

<section class="queue-tools" aria-label="Student filters">
  <form method="get" class="filter-panel filter-panel--queue" role="search">
    <input type="hidden" name="page" value="students">
    <div class="filter-search">
      <label for="student-search">Search</label>
      <input id="student-search" type="search" name="q" value="<?=e($studentFilters['q'])?>" placeholder="Name, email, student ID, username, or mobile">
    </div>
    <div class="filter-actions">
      <button type="submit">Apply</button>
      <?php if ($hasExtraFilters): ?><a class="button secondary" href="?<?=e($filterQs(['q'=>'','course_id'=>null,'year_level'=>null,'academic_status'=>null,'block'=>'all','active'=>'all','page'=>1,'id'=>null]))?>">Clear</a><?php endif; ?>
    </div>
    <div class="filter-dims" role="group" aria-label="Student filters">
      <div>
        <label for="student-filter-course">Program</label>
        <select id="student-filter-course" name="course_id" data-filter-autosubmit>
          <option value="">All programs</option>
          <?php foreach ($courseOptions as $c): ?>
          <option value="<?=(int)$c['id']?>" <?=((int)($studentFilters['course_id'] ?? 0)===(int)$c['id'])?'selected':''?>><?=e($c['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="student-filter-year">Year</label>
        <select id="student-filter-year" name="year_level" data-filter-autosubmit>
          <option value="">All years</option>
          <?php foreach (college_year_labels() as $y => $label): ?>
          <option value="<?=(int)$y?>" <?=((int)($studentFilters['year_level'] ?? 0)===(int)$y)?'selected':''?>><?=e($label)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="student-filter-status">Academic status</label>
        <select id="student-filter-status" name="academic_status" data-filter-autosubmit>
          <option value="">All statuses</option>
          <?php foreach (academic_statuses() as $st): ?>
          <option value="<?=e($st)?>" <?=($studentFilters['academic_status'] ?? '')===$st?'selected':''?>><?=e($st)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="student-filter-block">Block</label>
        <select id="student-filter-block" name="block" data-filter-autosubmit>
          <option value="all" <?=($studentFilters['block'] ?? 'all')==='all'?'selected':''?>>All</option>
          <option value="in" <?=($studentFilters['block'] ?? '')==='in'?'selected':''?>>In a block</option>
          <option value="out" <?=($studentFilters['block'] ?? '')==='out'?'selected':''?>>Not in a block</option>
        </select>
      </div>
      <div>
        <label for="student-filter-active">Account</label>
        <select id="student-filter-active" name="active" data-filter-autosubmit>
          <option value="all" <?=($studentFilters['active'] ?? 'all')==='all'?'selected':''?>>All</option>
          <option value="1" <?=($studentFilters['active'] ?? '')==='1'?'selected':''?>>Active</option>
          <option value="0" <?=($studentFilters['active'] ?? '')==='0'?'selected':''?>>Deactivated</option>
        </select>
      </div>
    </div>
  </form>
  <?php
  $metaBits = [$studentTotal . ' ' . ($studentTotal === 1 ? 'student' : 'students')];
  if ($hasExtraFilters) {
      $metaBits[] = 'filtered';
  }
  $metaBits[] = 'Page ' . $studentPage . ' of ' . $studentPages;
  ?>
  <p class="queue-meta"><?=e(implode(' · ', $metaBits))?> · Click a row to <?=$studentsReadOnly?'view':'edit'?></p>
</section>

<div class="table-wrap queue-table">
  <table>
    <thead><tr><th scope="col">Student</th><th scope="col">Program</th><th scope="col">Blocks</th><th scope="col">Status</th></tr></thead>
    <tbody>
    <?php if (!$students): ?>
      <tr><td colspan="4" class="empty-state"><strong><?=$hasExtraFilters?'No matching students':'No student records yet'?></strong><p><?=$hasExtraFilters?'Try clearing or widening filters.':($studentsReadOnly?'Approved enrollments and student records will appear here.':'Add a student to start managing academic records.')?></p><?php if($hasExtraFilters):?><a href="?<?=e($filterQs(['q'=>'','course_id'=>null,'year_level'=>null,'academic_status'=>null,'block'=>'all','active'=>'all','page'=>1,'id'=>null]))?>">Clear filters</a><?php endif;?></td></tr>
    <?php else: foreach ($students as $s):
      $rowHref = $studentsReadOnly
        ? '?' . $filterQs(['id' => (int)$s['id']])
        : '?page=student_form&id=' . (int)$s['id'];
    ?>
      <tr class="row-link" data-href="<?=e($rowHref)?>" tabindex="0" aria-label="<?=$studentsReadOnly?'View':'Edit'?> <?=e($s['first_name'].' '.$s['last_name'])?>">
        <td><strong><?=e($s['student_number'])?></strong><br><?=e($s['last_name'].', '.$s['first_name'])?></td>
        <td><?=e($s['course'])?> · <?=e(college_year_label($s['year_level']))?></td>
        <td><?=($s['block_names']??'')!==''?e((string)$s['block_names']):'<span class="muted">None</span>'?></td>
        <td><?=e($s['academic_status'])?><?php if(!$s['is_active']):?> <span class="badge badge-muted">Deactivated</span><?php endif;?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php if ($studentPages > 1): ?>
<nav class="pager" aria-label="Student pages">
  <span>Page <?=$studentPage?> of <?=$studentPages?></span>
  <?php if ($studentPage > 1): ?><a href="?<?=e($filterQs(['page'=>$studentPage-1,'id'=>null]))?>">Previous</a><?php endif; ?>
  <?php if ($studentPage < $studentPages): ?><a href="?<?=e($filterQs(['page'=>$studentPage+1,'id'=>null]))?>">Next</a><?php endif; ?>
</nav>
<?php endif; ?>

<?php if (!$studentsReadOnly): ?>
<dialog aria-labelledby="add-student-title" id="add-student-dialog" class="wide-dialog" <?=$studentCreateErrors?'open':''?>><form method="post" data-student-form><?=csrf_field()?><div class="bar"><h2 id="add-student-title">Add student</h2><button type="button" class="icon-button secondary" data-close-dialog aria-label="Close">×</button></div><?php errors($studentCreateErrors); render_student_fields($studentInput ?: [], $courseOptions, [], false); ?><div class="actions"><button>Create student</button><button type="button" class="secondary" data-close-dialog>Cancel</button></div></form></dialog>
<?php endif; ?>

<?php if ($studentsReadOnly && $viewStudent): ?>
<dialog open class="wide-dialog" aria-labelledby="student-view-title" data-return-url="?<?=e($filterQs(['id'=>null]))?>">
  <div class="bar">
    <div>
      <h2 id="student-view-title" tabindex="-1"><?=e($viewStudent['last_name'].', '.$viewStudent['first_name'])?></h2>
      <p class="muted"><?=e($viewStudent['student_number'])?> · <?=e($viewStudent['academic_status'])?><?php if(!$viewStudent['is_active']):?> · Deactivated<?php endif; ?></p>
    </div>
    <button type="button" class="icon-button secondary" data-close-dialog aria-label="Close">×</button>
  </div>
  <section class="form-section">
    <h3>Program</h3>
    <p><strong><?=e($viewStudent['course'])?></strong> · <?=e(college_year_label($viewStudent['year_level']))?></p>
    <p>Blocks: <?php if ($viewStudentBlocks): ?><strong><?=e(implode(', ', $viewStudentBlocks))?></strong><?php else: ?><span class="muted">None — place under <a href="?page=blocking">Blocking</a></span><?php endif; ?></p>
  </section>
  <section class="form-section">
    <h3>Contact</h3>
    <p><?=e((string)$viewStudent['email'])?><?php if (!empty($viewStudent['phone'])): ?> · <?=e((string)$viewStudent['phone'])?><?php endif; ?></p>
    <?php if (!empty($viewStudent['address'])): ?><p class="muted"><?=e((string)$viewStudent['address'])?></p><?php endif; ?>
    <p class="muted">Username: <?=e((string)($viewStudent['username'] ?? '—'))?></p>
  </section>
  <?php if (!empty($viewStudent['enrollment_approved_at'])): ?>
  <section class="form-section">
    <h3>Enrollment</h3>
    <p>Approved enrollee<?php if (!empty($viewStudent['approver_name'])): ?> by <strong><?=e((string)$viewStudent['approver_name'])?></strong><?php endif; ?> · <?=e((string)$viewStudent['enrollment_approved_at'])?></p>
  </section>
  <?php endif; ?>
  <div class="actions"><button type="button" class="secondary" data-close-dialog>Close</button></div>
</dialog>
<?php endif; ?>
<?php elseif($page==='student_form'): ?><dialog open class="wide-dialog" aria-labelledby="student-editor-title" data-return-url="?page=students"><div class="bar"><h2 id="student-editor-title"><?=$id?'Edit':'Add'?> student</h2><button type="button" class="icon-button secondary" data-close-dialog aria-label="Close student form">×</button></div><?php errors($errors);?><?php if($id):?><p class="muted">Approved enrollee by: <?php if($enrollmentApproverName):?><strong><?=e((string)$enrollmentApproverName)?></strong><?php if($enrollmentApprovedAt):?> · <?=e((string)$enrollmentApprovedAt)?><?php endif;?><?php else:?>—<?php endif;?></p><?php endif;?><form method="post" data-student-form><?=csrf_field()?><?php render_student_fields($student ?: [], $courseOptions, $studentBlocks, (bool)$id); ?><div class="actions"><button>Save student</button><?php if($id):?><button type="submit" class="secondary" name="reset_temp_password" value="1">Reset temporary password</button><?php endif;?><button type="button" class="secondary" data-close-dialog>Cancel</button></div></form><?php if($id):?><section class="form-section student-grades-panel" aria-labelledby="student-grades-title"><h2 id="student-grades-title">Subjects and grades</h2><p class="muted">Grades appear after teachers submit midterm and finals for each subject. Overall uses the admin term blend.</p><?php if(!$studentSubjectGrades):?><p class="muted">No subject enrollments yet.</p><?php else:?><div class="table-wrap"><table><thead><tr><th>Block</th><th>Subject</th><th>Midterm</th><th>Final</th><th>Overall</th></tr></thead><tbody><?php foreach($studentSubjectGrades as $g):?><tr><td><?=e($g['block_name'])?></td><td><strong><?=e($g['subject_code'])?></strong> · <?=e($g['subject_name'])?></td><td><?=e(format_grade($g['midterm_grade']))?></td><td><?=e(format_grade($g['final_grade']))?></td><td><strong><?=e(format_grade($g['overall_grade']))?></strong></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section><?php endif;?></dialog>
<?php elseif(in_array($page,['grading'],true)): require dirname(__DIR__) . '/app/grading-view.php'; ?>
<?php elseif(in_array($page,['my_subjects','assigned_blocks','submit_scores'],true)): require dirname(__DIR__) . '/app/teacher-grading-view.php'; ?>
<?php elseif(in_array($page,['enroll','enrollments'],true)): require dirname(__DIR__) . '/app/enrollment-view.php'; ?>
<?php elseif($page==='student_subjects'): require dirname(__DIR__) . '/app/student-portal-view.php'; ?>
<?php elseif($page==='profile'): ?>
<h1>My profile</h1>
<p class="muted">Legal names are set by the registrar or records office. You can set a display name and update contact details.</p>
<section class="card" aria-labelledby="profile-summary-title">
  <h2 id="profile-summary-title" class="sr-only">Academic summary</h2>
  <p><strong><?=e($student['student_number'])?> · <?=e(trim(($student['first_name'] ?? '').' '.(($student['middle_name'] ?? '') !== '' ? $student['middle_name'].' ' : '').($student['last_name'] ?? '')))?></strong></p>
  <p><?=e($student['course'])?>, <?=e(college_year_label($student['year_level']))?> · <?=e($student['academic_status'])?></p>
</section>
<?php errors($errors); ?>
<form class="card" method="post" style="max-width:760px">
<?=csrf_field()?>
<fieldset class="form-section">
  <legend>Identity</legend>
  <div class="grid">
    <div><label for="profile-first_name">First name</label><input id="profile-first_name" value="<?=e((string)$student['first_name'])?>" readonly></div>
    <div><label for="profile-middle_name">Middle name</label><input id="profile-middle_name" value="<?=e((string)($student['middle_name'] ?? ''))?>" readonly></div>
    <div><label for="profile-last_name">Last name</label><input id="profile-last_name" value="<?=e((string)$student['last_name'])?>" readonly></div>
    <div><label for="profile-display_name">Display name <span class="muted">(optional)</span></label><input id="profile-display_name" name="display_name" maxlength="150" value="<?=e((string)($student['display_name'] ?? ''))?>" autocomplete="nickname"><p class="muted">Shown next to Sign out when set. Leave blank to use your legal first and last name.</p></div>
  </div>
</fieldset>
<fieldset class="form-section">
  <legend>Contact</legend>
  <div class="grid">
    <div><label for="field-email">Email</label><input type="email" id="field-email" name="email" value="<?=e((string)$student['email'])?>" required maxlength="190"></div>
    <div><label for="field-phone">Phone <span class="muted">(optional)</span></label><input id="field-phone" name="phone" value="<?=e((string)($student['phone'] ?? ''))?>" maxlength="20"></div>
    <div><label for="field-address">Address <span class="muted">(optional)</span></label><input id="field-address" name="address" value="<?=e((string)($student['address'] ?? ''))?>" maxlength="255"></div>
  </div>
</fieldset>
<p class="muted">Username: <strong><?=e((string)($student['username'] ?? ''))?></strong> (read-only). Ask the registrar or records office if you need a password reset.</p>
<div class="actions"><button>Save profile</button><a class="button secondary" href="?page=student_subjects">Back to My Subjects</a></div>
</form>
<?php elseif($page==='teacher_profile'): ?>
<h1>My profile</h1>
<p class="muted">Update the contact details on your teacher record. Department and sign-in credentials are managed by an administrator.</p>
<?php errors($profileErrors); ?>
<form class="card" method="post" style="max-width:760px">
<?=csrf_field()?>
<fieldset class="form-section">
  <legend>Identity</legend>
  <div class="grid">
    <div><label for="profile-last_name">Last name</label><input id="profile-last_name" name="last_name" required maxlength="80" value="<?=e((string)($teacherProfile['last_name'] ?? ''))?>"></div>
    <div><label for="profile-first_name">First name</label><input id="profile-first_name" name="first_name" required maxlength="80" value="<?=e((string)($teacherProfile['first_name'] ?? ''))?>"></div>
    <div><label for="profile-middle_name">Middle name <span class="muted">(optional)</span></label><input id="profile-middle_name" name="middle_name" maxlength="80" value="<?=e((string)($teacherProfile['middle_name'] ?? ''))?>"></div>
  </div>
</fieldset>
<fieldset class="form-section">
  <legend>Contact</legend>
  <div class="grid">
    <div><label for="profile-email">Email</label><input type="email" id="profile-email" name="email" required maxlength="190" value="<?=e((string)$teacherProfile['email'])?>"></div>
    <div><label for="profile-phone">Contact number <span class="muted">(optional)</span></label><input id="profile-phone" name="phone" maxlength="20" value="<?=e((string)($teacherProfile['phone'] ?? ''))?>"></div>
    <div><label for="profile-address">Address <span class="muted">(optional)</span></label><input id="profile-address" name="address" maxlength="255" value="<?=e((string)($teacherProfile['address'] ?? ''))?>"></div>
  </div>
</fieldset>
<p class="muted">Username: <strong><?=e((string)$teacherProfile['username'])?></strong> (ask an administrator to change sign-in credentials.)</p>
<button>Save profile</button>
</form>
<?php elseif($page==='users'): ?><div class="bar"><div><h1>Administrators</h1><p class="muted">Administrator logins only. Teachers are under <a href="?page=teachers">Teachers</a>; registrars under <a href="?page=registrars">Registrars</a>. Student usernames are created on the Students form or on enrollment approval. New admins get a one-time temporary password shown after create.</p></div><button type="button" data-open-dialog="add-user-dialog">Add administrator</button></div><div class="table-wrap"><table><thead><tr><th scope="col">Full name</th><th scope="col">Username / Email</th><th scope="col">Status</th><th scope="col">Last login</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead><tbody><?php foreach($users as $u):?><tr><td><strong><?=e($u['full_name'])?></strong></td><td><?=e($u['username'])?><br><?=e($u['email'])?></td><td><?=$u['is_active']?'Active':'Deactivated'?></td><td><?=e($u['last_login_at']??'Never')?></td><td><a href="?page=user_form&amp;id=<?=$u['id']?>" aria-label="Edit <?=e($u['full_name'])?>">Edit</a></td></tr><?php endforeach;?><?php if(!$users):?><tr><td colspan="5" class="empty-state"><strong>No administrators yet</strong><p>Create an administrator account to manage the system.</p></td></tr><?php endif;?></tbody></table></div><dialog aria-labelledby="add-user-title" id="add-user-dialog" <?=!empty($createErrors)?'open':''?>><form method="post" data-user-form><?=csrf_field()?><div class="bar"><h2 id="add-user-title">Add administrator</h2><button type="button" class="icon-button secondary" data-close-dialog aria-label="Close">×</button></div><?php errors($createErrors);?><label for="field-full_name">Full name</label><input id="field-full_name" name="full_name" value="<?=e($fullName??'')?>" required minlength="3" maxlength="150" autocomplete="name"><label for="field-username">Username</label><input id="field-username" name="username" value="<?=e($username??'')?>" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]+" title="Use letters, numbers, dots, underscores, or hyphens only."><label for="field-email">Email</label><input type="email" id="field-email" name="email" value="<?=e($email??'')?>" required maxlength="190"><p class="muted">Role is Administrator. A one-time temporary password is shown after save — share it privately.</p><div class="actions"><button>Create administrator</button><button type="button" class="secondary" data-close-dialog>Cancel</button></div></form></dialog>
<?php elseif($page==='user_form'): ?><dialog open aria-labelledby="user-editor-title" data-return-url="?page=users"><div class="bar"><h2 id="user-editor-title"><?=$id?'Edit':'Add'?> administrator</h2><button type="button" class="icon-button secondary" data-close-dialog aria-label="Close account form">×</button></div><?php errors($errors);?><form method="post"><?=csrf_field()?><label for="field-full_name">Full name</label><input id="field-full_name" name="full_name" value="<?=e($account['full_name']??'')?>" required maxlength="150"><label for="field-username">Username</label><input id="field-username" name="username" value="<?=e($account['username']??'')?>" required><label for="field-email">Email</label><input type="email" id="field-email" name="email" value="<?=e($account['email']??'')?>" required><p class="muted">Role is Administrator. <?php if($id):?>Use <strong>Reset temporary password</strong> below when needed.<?php else:?>A one-time temporary password is shown after save.<?php endif;?></p><?php if($id):?><label><input class="checkbox" type="checkbox" name="is_active" value="1" <?=($account['is_active']??false)?'checked':''?>> Account is active</label><p class="muted">Uncheck to deactivate without deleting. The account cannot sign in while inactive.</p><?php endif;?><div class="actions"><button>Save administrator</button><?php if($id):?><button type="submit" class="secondary" name="reset_temp_password" value="1">Reset temporary password</button><?php endif;?><button type="button" class="secondary" data-close-dialog>Cancel</button></div></form></dialog>
<?php elseif($page==='logs'): ?><h1>Audit logs</h1><p class="muted">Latest 200 security and data events.</p><div class="table-wrap"><table><tr><th scope="col">Time</th><th scope="col">User</th><th scope="col">Action</th><th scope="col">Target</th><th scope="col">Details</th><th scope="col">IP</th></tr><?php if(!$logs):?><tr><td colspan="6" class="empty-state"><strong>No activity recorded yet</strong><p>Security and data events will appear here.</p></td></tr><?php endif;?><?php foreach($logs as $l):?><tr><td><?=e($l['created_at'])?></td><td><?=e($l['username']??'System')?></td><td><?=e($l['action'])?></td><td><?=e($l['entity_type'].($l['entity_id']?' #'.$l['entity_id']:''))?></td><td><?=e($l['details'])?></td><td><?=e($l['ip_address'])?></td></tr><?php endforeach;?></table></div>
<?php elseif($page==='settings'): ?><h1>System settings</h1><p class="muted">College or university name appears under the Secure Student IS brand. A non-empty maintenance notice shows as a banner on every signed-in page.</p><form class="card" method="post"><?=csrf_field()?><label for="field-school_name">College / university name</label><input id="field-school_name" name="school_name" value="<?=e($settings['school_name']??'')?>" maxlength="255" placeholder="Official institution name"><label for="field-maintenance_notice">Maintenance notice</label><textarea id="field-maintenance_notice" name="maintenance_notice" maxlength="255"><?=e($settings['maintenance_notice']??'')?></textarea><button>Save settings</button></form>
<?php elseif($page==='report'): ?><div class="bar"><h1>Active-student report</h1><a class="button" href="?page=report&amp;format=csv">Download CSV</a></div><div class="table-wrap"><table><tr><th scope="col">Number</th><th scope="col">Name</th><th scope="col">Course</th><th scope="col">Year</th><th scope="col">Status</th></tr><?php if(!$rows):?><tr><td colspan="5" class="empty-state"><strong>No active students to report</strong><p>Active student records will appear here.</p><a href="?page=students">Go to student records</a></td></tr><?php endif;?><?php foreach($rows as $r):?><tr><td><?=e($r['student_number'])?></td><td><?=e($r['last_name'].', '.$r['first_name'])?></td><td><?=e($r['course'])?></td><td><?=e(college_year_label($r['year_level']))?></td><td><?=e($r['academic_status'])?></td></tr><?php endforeach;?></table></div>
<?php else: ?><section class="card"><h1>Page not found</h1><p class="muted">The page you requested is unavailable.</p><a class="button" href="index.php">Return home</a></section><?php endif; render_footer();

