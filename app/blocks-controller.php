<?php
declare(strict_types=1);
require_role(['admin']);
require_once __DIR__ . '/blocks.php';
require_once __DIR__ . '/subjects.php';

$blockErrors = [];
$assignmentErrors = [];
$blockInput = ['name' => '', 'year_level' => '1', 'student_ids' => []];
$assignmentInput = ['teacher_id' => '', 'subject_id' => ''];
$editAssignmentId = null;
$blockId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT) ?: null;
$blockMissing = false;
$assignments = [];
$memberCount = 0;

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
            'subject_id' => is_scalar($_POST['subject_id'] ?? null) ? (string)$_POST['subject_id'] : '',
        ];
        try {
            if ($action === 'delete' && $assignmentId) {
                delete_block_assignment(db(), $blockId, $assignmentId);
                flash('success', 'Teacher assignment removed.');
                redirect('blocks', ['edit' => $blockId]);
            }
            if ($action === 'edit' && $assignmentId) {
                $editAssignmentId = $assignmentId;
            } else {
                save_block_assignment(db(), $blockId, $_POST, $assignmentId);
                flash('success', $assignmentId ? 'Teacher assignment updated.' : 'Teacher assigned to this block.');
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
    } elseif ($form === 'assign_student' && $blockId) {
        $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        try {
            assign_block_student(db(), $blockId, $studentId);
            flash('success', 'Student assigned to this block.');
            redirect('blocks', ['edit' => $blockId, 'assign' => 1]);
        } catch (InvalidArgumentException $exception) {
            $blockErrors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('Block student assign failed: ' . $exception->getMessage());
            $blockErrors[] = 'The student could not be assigned. Please try again.';
        }
    } elseif ($form === 'remove_student' && $blockId) {
        $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        try {
            remove_block_student(db(), $blockId, $studentId);
            flash('success', 'Student removed from this block.');
            redirect('blocks', ['edit' => $blockId]);
        } catch (InvalidArgumentException $exception) {
            $blockErrors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('Block student remove failed: ' . $exception->getMessage());
            $blockErrors[] = 'The student could not be removed. Please try again.';
        }
    } else {
        $blockInput = [
            'name' => is_string($_POST['name'] ?? null) ? $_POST['name'] : '',
            'year_level' => is_scalar($_POST['year_level'] ?? null) ? (string)$_POST['year_level'] : '',
            'student_ids' => $blockInput['student_ids'] ?? [],
        ];
        try {
            $savedId = save_block(db(), [
                'name' => $blockInput['name'],
                'year_level' => $blockInput['year_level'],
            ], $blockId);
            if ($blockId) {
                flash('success', 'Block saved.');
                redirect('blocks', ['edit' => $savedId]);
            }
            flash('success', 'Block created. Assign students next.');
            redirect('blocks', ['edit' => $savedId, 'assign' => 1]);
        } catch (InvalidArgumentException $exception) {
            $blockErrors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('Block save failed: ' . $exception->getMessage());
            $blockErrors[] = 'The block could not be saved. Please try again.';
        }
    }
}

if ($blockId && !$blockMissing) {
    // Keep teacher enrollments aligned when students were placed after teachers (or older data drifted).
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sync_block_enrollments(db(), $blockId);
    }
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
    foreach ($assignments as &$assignmentRow) {
        $assignmentRow['remove_confirm'] = 'Remove ' . $assignmentRow['subject_code'] . ' (' . $assignmentRow['teacher_name'] . ') from this block?';
        $assignmentRow['row_class'] = $editAssignmentId === (int)$assignmentRow['id'] ? 'is-editing' : '';
    }
    unset($assignmentRow);
    if ($editAssignmentId) {
        foreach ($assignments as $row) {
            if ((int)$row['id'] === $editAssignmentId) {
                $assignmentInput = [
                    'teacher_id' => (string)$row['teacher_id'],
                    'subject_id' => (string)($row['subject_id'] ?? ''),
                ];
                break;
            }
        }
    }
} else {
    $memberCount = 0;
}

$teachers = db()->query("SELECT t.id, u.full_name FROM teachers t JOIN users u ON u.id=t.user_id WHERE u.role='staff' AND u.is_active=1 ORDER BY u.full_name, t.id")->fetchAll();
$subjectOptions = subjects_all();
$currentTeacherId = (int)($assignmentInput['teacher_id'] ?? 0);
$assignedTeacherIds = [];
foreach ($assignments as $row) {
    $tid = (int)($row['teacher_id'] ?? 0);
    if ($tid > 0) {
        $assignedTeacherIds[$tid] = true;
    }
}
$availableTeachers = array_values(array_filter(
    $teachers,
    static fn(array $t): bool => (int)$t['id'] === $currentTeacherId || empty($assignedTeacherIds[(int)$t['id']])
));

$selectedStudents = array_values(array_unique(array_map('intval', $blockInput['student_ids'])));
$rosterStudents = [];
if ($selectedStudents) {
    $placeholders = implode(',', array_fill(0, count($selectedStudents), '?'));
    $statement = db()->prepare(
        "SELECT id, student_number, first_name, last_name, course, year_level, is_active
         FROM students
         WHERE id IN ($placeholders)
         ORDER BY last_name, first_name, id"
    );
    $statement->execute($selectedStudents);
    $rosterStudents = $statement->fetchAll();
}

$blockYearLevel = (int)($blockInput['year_level'] ?? 0);
$studentSearch = is_string($_GET['student_q'] ?? null) ? trim($_GET['student_q']) : '';
$studentSearchResults = [];
$studentSearchTooShort = $studentSearch !== '' && mb_strlen($studentSearch) < 2;
if (!$studentSearchTooShort && $studentSearch !== '' && $blockYearLevel >= 1 && $blockYearLevel <= 4) {
    $like = '%' . strtr($studentSearch, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $excludeSql = '';
    $params = [$blockYearLevel, $like, $like, $like];
    if ($selectedStudents) {
        $placeholders = implode(',', array_fill(0, count($selectedStudents), '?'));
        $excludeSql = " AND id NOT IN ($placeholders)";
        $params = array_merge($params, $selectedStudents);
    }
    $statement = db()->prepare(
        "SELECT id, student_number, first_name, last_name, course, year_level, is_active
         FROM students
         WHERE is_active=1
           AND year_level=?
           AND (student_number LIKE ? ESCAPE '!' OR first_name LIKE ? ESCAPE '!' OR last_name LIKE ? ESCAPE '!')
           $excludeSql
         ORDER BY last_name, first_name, id
         LIMIT 25"
    );
    $statement->execute($params);
    $studentSearchResults = $statement->fetchAll();
}

$studentCatalogCount = (int) db()->query('SELECT COUNT(*) FROM students WHERE is_active=1')->fetchColumn();
$assigningStudents = $blockId && (isset($_GET['assign']) || $studentSearch !== '' || $blockErrors);

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
     ORDER BY b.year_level, b.name, b.id
     LIMIT 20 OFFSET $offset"
);
$statement->execute([$teacherFilter, $teacherFilter, $like, $like, $like, $like]);
$blocks = $statement->fetchAll();
$showBlockForm = isset($_GET['add']) || $blockId || $_SERVER['REQUEST_METHOD'] === 'POST';
