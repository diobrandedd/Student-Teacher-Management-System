<?php
declare(strict_types=1);
require_role(['admin', 'staff']);
require_once __DIR__ . '/blocks.php';

$blockErrors = [];
$assignmentErrors = [];
$blockInput = ['name' => '', 'student_ids' => []];
$assignmentInput = ['teacher_id' => '', 'subject_code' => '', 'subject_name' => ''];
$editAssignmentId = null;
$blockId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT) ?: null;
$blockMissing = false;
$assignments = [];
$memberCount = 0;
$assignmentsNeedResync = false;

if ($blockId) {
    $statement = db()->prepare('SELECT * FROM blocks WHERE id=?');
    $statement->execute([$blockId]);
    $existingBlock = $statement->fetch();
    if (!$existingBlock) {
        http_response_code(404);
        $blockMissing = true;
        $blockErrors[] = 'Block not found. Return to the block list to select an existing block.';
    } else {
        $statement = db()->prepare('SELECT student_id FROM block_students WHERE block_id=?');
        $statement->execute([$blockId]);
        $blockInput = [...$existingBlock, 'student_ids' => array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN))];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blockMissing) {
    verify_csrf();
    $form = (string)($_POST['form'] ?? 'block');
    if ($form === 'assignment' && $blockId) {
        $action = (string)($_POST['assignment_action'] ?? 'save');
        $assignmentId = filter_var($_POST['assignment_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $assignmentInput = [
            'teacher_id' => is_scalar($_POST['teacher_id'] ?? null) ? (string)$_POST['teacher_id'] : '',
            'subject_code' => is_string($_POST['subject_code'] ?? null) ? (string)$_POST['subject_code'] : '',
            'subject_name' => is_string($_POST['subject_name'] ?? null) ? (string)$_POST['subject_name'] : '',
        ];
        try {
            if ($action === 'delete' && $assignmentId) {
                delete_block_assignment(db(), $blockId, $assignmentId);
                flash('success', 'Subject assignment removed.');
                redirect('blocks', ['edit' => $blockId]);
            }
            if ($action === 'resync' && $assignmentId) {
                $count = resync_block_assignment(db(), $blockId, $assignmentId);
                flash('success', 'Enrollments now match the current roster (' . $count . ' student' . ($count === 1 ? '' : 's') . ').');
                redirect('blocks', ['edit' => $blockId]);
            }
            if ($action === 'edit' && $assignmentId) {
                $editAssignmentId = $assignmentId;
            } else {
                save_block_assignment(db(), $blockId, $_POST, $assignmentId);
                flash('success', $assignmentId ? 'Subject assignment updated and current members synced.' : 'Subject assigned. Current block members were synced.');
                redirect('blocks', ['edit' => $blockId]);
            }
        } catch (InvalidArgumentException $exception) {
            $assignmentErrors[] = $exception->getMessage();
            if ($assignmentId) $editAssignmentId = $assignmentId;
        } catch (PDOException $exception) {
            error_log('Block assignment save failed: ' . $exception->getMessage());
            $assignmentErrors[] = 'The subject assignment could not be saved. Please try again.';
            if ($assignmentId) $editAssignmentId = $assignmentId;
        }
    } else {
        $blockInput = [
            'name' => is_string($_POST['name'] ?? null) ? $_POST['name'] : '',
            'student_ids' => is_array($_POST['student_ids'] ?? null) ? array_values(array_filter($_POST['student_ids'], 'is_scalar')) : [],
        ];
        try {
            $savedId = save_block(db(), $_POST, $blockId);
            if ($blockId) {
                flash('success', 'Block updated. Subject enrollments were not changed; re-sync an assignment if needed.');
                redirect('blocks', ['edit' => $savedId]);
            }
            flash('success', 'Block created. Assign teachers and subjects next.');
            redirect('blocks', ['edit' => $savedId]);
        } catch (InvalidArgumentException $exception) {
            $blockErrors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('Block save failed: ' . $exception->getMessage());
            $blockErrors[] = 'The block could not be saved. Your selection is still here; please try again.';
        }
    }
}

if ($blockId && !$blockMissing) {
    $statement = db()->prepare(
        "SELECT a.*, u.full_name AS teacher_name, u.is_active AS teacher_active, u.role AS teacher_role,
            (SELECT COUNT(*) FROM block_subject_enrollments e WHERE e.assignment_id=a.id) AS enrollment_count
         FROM block_subject_assignments a
         JOIN teachers t ON t.id=a.teacher_id
         JOIN users u ON u.id=t.user_id
         WHERE a.block_id=?
         ORDER BY a.subject_code, a.id"
    );
    $statement->execute([$blockId]);
    $assignments = $statement->fetchAll();
    $memberCount = count($blockInput['student_ids']);
    $memberIds = array_map('intval', $blockInput['student_ids']);
    sort($memberIds);
    foreach ($assignments as &$assignmentRow) {
        $enrolledStmt = db()->prepare('SELECT student_id FROM block_subject_enrollments WHERE assignment_id=? ORDER BY student_id');
        $enrolledStmt->execute([(int)$assignmentRow['id']]);
        $enrolledIds = array_map('intval', $enrolledStmt->fetchAll(PDO::FETCH_COLUMN));
        $assignmentRow['needs_resync'] = $enrolledIds !== $memberIds;
        $enrolled = (int)$assignmentRow['enrollment_count'];
        $assignmentRow['match_confirm'] = 'Match enrollments for ' . $assignmentRow['subject_code']
            . ' to the current roster of ' . $memberCount . ' student' . ($memberCount === 1 ? '' : 's')
            . '? Currently enrolled: ' . $enrolled . '.';
        $assignmentRow['remove_confirm'] = 'Remove ' . $assignmentRow['subject_code'] . ' and its enrollments from this block?';
        $classes = [];
        if ($editAssignmentId === (int)$assignmentRow['id']) $classes[] = 'is-editing';
        if ($assignmentRow['needs_resync']) $classes[] = 'needs-resync';
        $assignmentRow['row_class'] = implode(' ', $classes);
    }
    unset($assignmentRow);
    $assignmentsNeedResync = (bool)array_filter($assignments, fn($row) => !empty($row['needs_resync']));
    if ($editAssignmentId) {
        foreach ($assignments as $row) {
            if ((int)$row['id'] === $editAssignmentId) {
                $assignmentInput = [
                    'teacher_id' => (string)$row['teacher_id'],
                    'subject_code' => (string)$row['subject_code'],
                    'subject_name' => (string)$row['subject_name'],
                ];
                break;
            }
        }
    }
} else {
    $memberCount = 0;
    $assignmentsNeedResync = false;
}

$teachers = db()->query("SELECT t.id, u.full_name FROM teachers t JOIN users u ON u.id=t.user_id WHERE u.role='staff' AND u.is_active=1 ORDER BY u.full_name, t.id")->fetchAll();
$statement = db()->prepare('SELECT id, student_number, first_name, last_name, course, year_level, is_active FROM students WHERE is_active=1 OR id IN (SELECT student_id FROM block_students WHERE block_id=?) ORDER BY last_name, first_name, id');
$statement->execute([$blockId ?? 0]);
$blockStudents = $statement->fetchAll();
$blockSearch = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$teacherFilter = max(0, (int)(filter_var($_GET['teacher_id'] ?? null, FILTER_VALIDATE_INT) ?: 0));
$like = '%' . strtr($blockSearch, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
$statement = db()->prepare(
    "SELECT COUNT(DISTINCT b.id) FROM blocks b
     LEFT JOIN block_subject_assignments a ON a.block_id=b.id
     LEFT JOIN teachers t ON t.id=a.teacher_id
     LEFT JOIN users u ON u.id=t.user_id
     WHERE (?=0 OR a.teacher_id=?)
       AND (b.name LIKE ? ESCAPE '!' OR COALESCE(u.full_name,'') LIKE ? ESCAPE '!' OR COALESCE(a.subject_code,'') LIKE ? ESCAPE '!' OR COALESCE(a.subject_name,'') LIKE ? ESCAPE '!')"
);
$statement->execute([$teacherFilter, $teacherFilter, $like, $like, $like, $like]);
$blockTotal = (int)$statement->fetchColumn();
$blockPages = max(1, (int)ceil($blockTotal / 20));
$blockPage = max(1, min($blockPages, (int)($_GET['p'] ?? 1)));
$offset = ($blockPage - 1) * 20;
$statement = db()->prepare(
    "SELECT b.*,
        (SELECT COUNT(*) FROM block_students bs WHERE bs.block_id=b.id) AS student_count,
        (SELECT COUNT(*) FROM block_subject_assignments a2 WHERE a2.block_id=b.id) AS assignment_count,
        (SELECT GROUP_CONCAT(CONCAT(a3.subject_code, ' · ', u3.full_name) ORDER BY a3.subject_code SEPARATOR ' | ')
           FROM block_subject_assignments a3
           JOIN teachers t3 ON t3.id=a3.teacher_id
           JOIN users u3 ON u3.id=t3.user_id
           WHERE a3.block_id=b.id) AS subject_summary
     FROM blocks b
     WHERE (?=0 OR EXISTS (SELECT 1 FROM block_subject_assignments ax WHERE ax.block_id=b.id AND ax.teacher_id=?))
       AND (
         b.name LIKE ? ESCAPE '!'
         OR EXISTS (
           SELECT 1 FROM block_subject_assignments a
           JOIN teachers t ON t.id=a.teacher_id
           JOIN users u ON u.id=t.user_id
           WHERE a.block_id=b.id
             AND (u.full_name LIKE ? ESCAPE '!' OR a.subject_code LIKE ? ESCAPE '!' OR a.subject_name LIKE ? ESCAPE '!')
         )
       )
     ORDER BY b.name, b.id
     LIMIT 20 OFFSET $offset"
);
$statement->execute([$teacherFilter, $teacherFilter, $like, $like, $like, $like]);
$blocks = $statement->fetchAll();
$selectedStudents = array_map('intval', $blockInput['student_ids']);
$showBlockForm = isset($_GET['add']) || $blockId || $_SERVER['REQUEST_METHOD'] === 'POST';
