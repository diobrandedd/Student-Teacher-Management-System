<?php
declare(strict_types=1);
require_registrar_capability('assign_teachers');
require_once __DIR__ . '/blocks.php';
require_once __DIR__ . '/subjects.php';

$assignmentErrors = [];
$assignmentInput = ['block_id' => '', 'subject_id' => ''];
$editAssignmentId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT) ?: null;
$editingAssignment = null;
$teacherId = filter_var($_GET['teacher'] ?? null, FILTER_VALIDATE_INT) ?: null;
$selectedTeacher = null;
$assignments = [];

if (!$teacherId && filter_var($_GET['block'] ?? null, FILTER_VALIDATE_INT)) {
    redirect('assign_teachers');
}

if ($teacherId) {
    $statement = db()->prepare(
        "SELECT t.id, u.full_name, u.email, u.is_active, u.role
         FROM teachers t
         JOIN users u ON u.id=t.user_id
         WHERE t.id=? AND u.role='staff' LIMIT 1"
    );
    $statement->execute([$teacherId]);
    $selectedTeacher = $statement->fetch() ?: null;
    if (!$selectedTeacher || !(int)$selectedTeacher['is_active']) {
        http_response_code(404);
        $teacherId = null;
        $selectedTeacher = null;
        $editAssignmentId = null;
        $assignmentErrors[] = 'Teacher not found. Choose a teacher from the list.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $teacherId && $selectedTeacher) {
    verify_csrf();
    $action = (string)($_POST['assignment_action'] ?? 'save');
    $assignmentId = filter_var($_POST['assignment_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    $blockId = filter_var($_POST['block_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $assignmentInput = [
        'block_id' => $blockId > 0 ? (string)$blockId : '',
        'subject_id' => is_scalar($_POST['subject_id'] ?? null) ? (string)$_POST['subject_id'] : '',
    ];
    try {
        if ($action === 'delete' && $assignmentId) {
            $own = db()->prepare('SELECT block_id FROM block_subject_assignments WHERE id=? AND teacher_id=?');
            $own->execute([$assignmentId, $teacherId]);
            $ownedBlock = (int)$own->fetchColumn();
            if ($ownedBlock < 1) {
                throw new InvalidArgumentException('That assignment no longer exists for this teacher.');
            }
            delete_block_assignment(db(), $ownedBlock, $assignmentId);
            flash('success', 'Block assignment removed.');
            redirect('assign_teachers', ['teacher' => $teacherId]);
        }
        if ($blockId < 1) {
            throw new InvalidArgumentException('Select a block.');
        }
        if ($assignmentId) {
            $own = db()->prepare('SELECT block_id FROM block_subject_assignments WHERE id=? AND teacher_id=?');
            $own->execute([$assignmentId, $teacherId]);
            $ownedBlock = (int)$own->fetchColumn();
            if ($ownedBlock < 1) {
                throw new InvalidArgumentException('That assignment no longer exists for this teacher.');
            }
            $dup = db()->prepare(
                'SELECT COUNT(*) FROM block_subject_assignments WHERE block_id=? AND teacher_id=? AND id<>?'
            );
            $dup->execute([$blockId, $teacherId, $assignmentId]);
            if ((int)$dup->fetchColumn() > 0) {
                throw new InvalidArgumentException('This teacher is already assigned to that block.');
            }
            if ($blockId !== $ownedBlock) {
                delete_block_assignment(db(), $ownedBlock, $assignmentId);
                save_block_assignment(db(), $blockId, [
                    'teacher_id' => $teacherId,
                    'subject_id' => $_POST['subject_id'] ?? '',
                ], null);
                flash('success', 'Assignment updated.');
            } else {
                save_block_assignment(db(), $blockId, [
                    'teacher_id' => $teacherId,
                    'subject_id' => $_POST['subject_id'] ?? '',
                ], $assignmentId);
                flash('success', 'Assignment updated.');
            }
            redirect('assign_teachers', ['teacher' => $teacherId]);
        }
        $dup = db()->prepare('SELECT COUNT(*) FROM block_subject_assignments WHERE block_id=? AND teacher_id=?');
        $dup->execute([$blockId, $teacherId]);
        if ((int)$dup->fetchColumn() > 0) {
            throw new InvalidArgumentException('This teacher is already assigned to that block. Edit that row instead.');
        }
        save_block_assignment(db(), $blockId, [
            'teacher_id' => $teacherId,
            'subject_id' => $_POST['subject_id'] ?? '',
        ], null);
        flash('success', 'Teacher assigned to the block.');
        redirect('assign_teachers', ['teacher' => $teacherId]);
    } catch (InvalidArgumentException $exception) {
        $assignmentErrors[] = $exception->getMessage();
        if ($assignmentId) {
            $editAssignmentId = $assignmentId;
        }
    } catch (PDOException $exception) {
        error_log('Assign teachers save failed: ' . $exception->getMessage());
        $assignmentErrors[] = 'The assignment could not be saved. Please try again.';
        if ($assignmentId) {
            $editAssignmentId = $assignmentId;
        }
    }
}

if ($teacherId && $selectedTeacher) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $blockIds = db()->prepare('SELECT DISTINCT block_id FROM block_subject_assignments WHERE teacher_id=?');
        $blockIds->execute([$teacherId]);
        foreach ($blockIds->fetchAll(PDO::FETCH_COLUMN) as $bid) {
            sync_block_enrollments(db(), (int)$bid);
        }
    }
    $statement = db()->prepare(
        "SELECT a.*, b.name AS block_name, b.year_level AS block_year_level,
            (SELECT COUNT(*) FROM block_students bs WHERE bs.block_id=a.block_id) AS block_student_count,
            (SELECT COUNT(*) FROM block_subject_enrollments e WHERE e.assignment_id=a.id) AS enrollment_count
         FROM block_subject_assignments a
         JOIN blocks b ON b.id=a.block_id
         WHERE a.teacher_id=?
         ORDER BY b.year_level, b.name, a.subject_code, a.id"
    );
    $statement->execute([$teacherId]);
    $assignments = $statement->fetchAll();
    foreach ($assignments as &$row) {
        $row['block_label'] = block_label($row['block_name'], $row['block_year_level'] ?? null);
        $row['remove_confirm'] = 'Remove ' . $row['subject_code'] . ' from ' . $row['block_label'] . '?';
        $row['row_class'] = $editAssignmentId === (int)$row['id'] ? 'is-editing' : '';
    }
    unset($row);

    if ($editAssignmentId) {
        foreach ($assignments as $row) {
            if ((int)$row['id'] === $editAssignmentId) {
                $editingAssignment = $row;
                if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $assignmentInput['block_id'] === '') {
                    $assignmentInput = [
                        'block_id' => (string)$row['block_id'],
                        'subject_id' => (string)($row['subject_id'] ?? ''),
                    ];
                }
                break;
            }
        }
        if (!$editingAssignment) {
            $editAssignmentId = null;
            $assignmentErrors[] = 'That assignment no longer exists. Choose another row to edit.';
        }
    }
}

$subjectOptions = subjects_all();
$assignedBlockIds = [];
foreach ($assignments as $row) {
    $assignedBlockIds[(int)$row['block_id']] = (int)$row['id'];
}
$allBlocks = db()->query('SELECT id, name, year_level FROM blocks ORDER BY year_level, name, id')->fetchAll();
$availableBlocks = [];
$editBlocks = [];
foreach ($allBlocks as $block) {
    $bid = (int)$block['id'];
    $assignedAssignmentId = $assignedBlockIds[$bid] ?? null;
    if ($assignedAssignmentId === null) {
        $availableBlocks[] = $block;
        $editBlocks[] = $block;
        continue;
    }
    if ($editAssignmentId && $assignedAssignmentId === $editAssignmentId) {
        $editBlocks[] = $block;
    }
}

$teacherSearch = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$like = '%' . strtr($teacherSearch, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
$countStmt = db()->prepare(
    "SELECT COUNT(*) FROM teachers t
     JOIN users u ON u.id=t.user_id
     WHERE u.role='staff' AND u.is_active=1
       AND (?='' OR u.full_name LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!')"
);
$countStmt->execute([$teacherSearch, $like, $like]);
$teacherTotal = (int)$countStmt->fetchColumn();
$teacherPages = max(1, (int)ceil($teacherTotal / 25));
$teacherPage = max(1, min($teacherPages, (int)($_GET['p'] ?? 1)));
$offset = ($teacherPage - 1) * 25;
$listStmt = db()->prepare(
    "SELECT t.id, u.full_name, u.email,
        (SELECT COUNT(*) FROM block_subject_assignments a WHERE a.teacher_id=t.id) AS assignment_count,
        (SELECT GROUP_CONCAT(CONCAT(b.name, ' · ',
            CASE b.year_level WHEN 1 THEN '1st year' WHEN 2 THEN '2nd year' WHEN 3 THEN '3rd year' WHEN 4 THEN '4th year' ELSE CONCAT('Year ', b.year_level) END,
            ' · ', a.subject_code) ORDER BY b.year_level, b.name, a.subject_code SEPARATOR ' | ')
           FROM block_subject_assignments a
           JOIN blocks b ON b.id=a.block_id
           WHERE a.teacher_id=t.id) AS block_summary
     FROM teachers t
     JOIN users u ON u.id=t.user_id
     WHERE u.role='staff' AND u.is_active=1
       AND (?='' OR u.full_name LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!')
     ORDER BY u.full_name, t.id
     LIMIT 25 OFFSET $offset"
);
$listStmt->execute([$teacherSearch, $like, $like]);
$teacherRows = $listStmt->fetchAll();

$isAdmin = (user()['role'] ?? '') === 'admin';
$returnQs = http_build_query(array_filter([
    'page' => 'assign_teachers',
    'q' => $teacherSearch !== '' ? $teacherSearch : null,
    'p' => $teacherPage > 1 ? $teacherPage : null,
]));
$teacherReturnQs = http_build_query(array_filter([
    'page' => 'assign_teachers',
    'teacher' => $teacherId,
    'q' => $teacherSearch !== '' ? $teacherSearch : null,
    'p' => $teacherPage > 1 ? $teacherPage : null,
]));
