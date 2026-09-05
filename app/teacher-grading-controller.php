<?php
declare(strict_types=1);
require_once __DIR__ . '/grading.php';

global $page;

$teacherId = teacher_row_id_for_user();
if (!$teacherId && user() && (user()['role'] ?? '') === 'staff') {
    sync_teacher((int)user()['id']);
    $teacherId = teacher_row_id_for_user();
}
if (!$teacherId) {
    deny_request('Your account is not linked to a teacher profile. Contact an administrator.', 403);
}

$assignmentId = filter_input(INPUT_GET, 'assignment_id', FILTER_VALIDATE_INT) ?: null;
$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT) ?: null;
$itemErrors = [];
$scoreErrors = [];
$submitErrors = [];
$itemInput = ['term' => 'midterm', 'category' => 'quiz', 'title' => '', 'max_score' => '100'];
$editItemId = null;
$weights = grading_weights();

if ($page === 'my_subjects') {
    require_role(['staff']);
    $assignments = list_teacher_assignments(db(), $teacherId);
    $assignment = null;
    $items = [];
    $submission = null;
    $phase = 'midterm';
    $lockedTerms = ['midterm' => false, 'final' => false];

    if ($assignmentId) {
        $assignment = fetch_teacher_assignment(db(), $assignmentId, $teacherId);
        if (!$assignment) {
            http_response_code(404);
            $itemErrors[] = 'Subject assignment not found.';
        } else {
            $submission = assignment_submission(db(), $assignmentId);
            $phase = assignment_phase($submission);
            $lockedTerms = [
                'midterm' => !empty($submission['midterm_submitted_at']),
                'final' => !empty($submission['final_submitted_at']),
            ];
            $items = list_score_items(db(), $assignmentId);
            if (!empty($lockedTerms['midterm']) && empty($lockedTerms['final'])) {
                $itemInput['term'] = 'final';
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $assignment && empty($itemErrors)) {
        verify_csrf();
        $form = (string)($_POST['form'] ?? 'item');
        try {
            if ($form === 'item') {
                $action = (string)($_POST['item_action'] ?? 'save');
                $editItemId = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                $itemInput = [
                    'term' => (string)($_POST['term'] ?? 'midterm'),
                    'category' => (string)($_POST['category'] ?? 'quiz'),
                    'title' => (string)($_POST['title'] ?? ''),
                    'max_score' => (string)($_POST['max_score'] ?? ''),
                ];
                if ($action === 'delete' && $editItemId) {
                    delete_score_item(db(), $assignmentId, $editItemId);
                    flash('success', 'Score item removed.');
                    redirect('my_subjects', ['assignment_id' => $assignmentId]);
                }
                if ($action === 'edit' && $editItemId) {
                    $found = null;
                    foreach ($items as $row) {
                        if ((int)$row['id'] === $editItemId) {
                            $found = $row;
                            break;
                        }
                    }
                    if ($found) {
                        $itemInput = [
                            'term' => $found['term'],
                            'category' => $found['category'],
                            'title' => $found['title'],
                            'max_score' => (string)$found['max_score'],
                        ];
                    }
                } else {
                    save_score_item(db(), $assignmentId, $itemInput, $editItemId);
                    flash('success', $editItemId ? 'Score item updated.' : 'Score item added.');
                    redirect('my_subjects', ['assignment_id' => $assignmentId]);
                }
            }
        } catch (InvalidArgumentException $e) {
            $itemErrors[] = $e->getMessage();
        } catch (PDOException $e) {
            error_log('Score item save failed: ' . $e->getMessage());
            $itemErrors[] = 'The score item could not be saved. Please try again.';
        }
        if ($assignmentId) {
            $items = list_score_items(db(), $assignmentId);
        }
    }
}

if ($page === 'assigned_blocks') {
    require_role(['staff']);
    $assignments = list_teacher_assignments(db(), $teacherId);
    $assignment = null;
    $roster = [];
    $gradesMap = [];
    $submission = null;
    $phase = 'midterm';
    $currentTerm = 'midterm';
    $gradedCount = 0;
    $rosterCount = 0;
    $canSubmit = false;
    $submitBlockReason = '';

    if ($assignmentId) {
        $assignment = fetch_teacher_assignment(db(), $assignmentId, $teacherId);
        if (!$assignment) {
            http_response_code(404);
            $submitErrors[] = 'Subject assignment not found.';
        } else {
            $submission = assignment_submission(db(), $assignmentId);
            $phase = assignment_phase($submission);
            $currentTerm = $phase === 'complete' ? 'final' : $phase;
            $roster = enrollment_roster(db(), $assignmentId);
            $gradesMap = student_term_grades_map(db(), $assignmentId);
            $rosterCount = count($roster);
            foreach ($roster as &$row) {
                $sid = (int)$row['student_id'];
                $row['graded'] = $phase === 'complete'
                    ? true
                    : student_has_complete_drafts(db(), $assignmentId, $sid, $currentTerm);
                if (!empty($row['graded']) && $phase !== 'complete') {
                    $gradedCount++;
                }
                $row['midterm_grade'] = $gradesMap[$sid]['midterm'] ?? null;
                $row['final_grade'] = $gradesMap[$sid]['final'] ?? null;
                $row['overall_grade'] = ($row['midterm_grade'] !== null && $row['final_grade'] !== null && $phase === 'complete')
                    ? overall_grade((float)$row['midterm_grade'], (float)$row['final_grade'], $weights)
                    : null;
            }
            unset($row);
            if ($phase === 'midterm' || $phase === 'final') {
                try {
                    validate_term_ready_for_submit(db(), $assignmentId, $phase === 'midterm' ? 'midterm' : 'final', $weights);
                    $canSubmit = true;
                } catch (InvalidArgumentException $e) {
                    $canSubmit = false;
                    $submitBlockReason = $e->getMessage();
                }
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $assignment) {
        verify_csrf();
        $form = (string)($_POST['form'] ?? '');
        try {
            if ($form === 'submit_term') {
                $term = (string)($_POST['term'] ?? '');
                $count = submit_assignment_term(db(), $assignmentId, $term, (int)user()['id']);
                audit('SUBMIT', 'assignment_grades', $assignmentId, ucfirst($term) . ' grades submitted for ' . $count . ' students');
                flash('success', ucfirst($term === 'final' ? 'Finals' : 'Midterm') . ' grades submitted for ' . $count . ' student' . ($count === 1 ? '' : 's') . '.');
                redirect('assigned_blocks', ['assignment_id' => $assignmentId]);
            }
        } catch (InvalidArgumentException $e) {
            $submitErrors[] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Grade submit failed: ' . $e->getMessage());
            $submitErrors[] = 'Grades could not be submitted. Please try again.';
        }
    }
}

if ($page === 'submit_scores') {
    require_role(['staff']);
    $assignment = null;
    $student = null;
    $items = [];
    $itemsByCategory = [];
    $scoreMap = [];
    $phase = 'midterm';
    $currentTerm = 'midterm';
    $locked = false;
    $prevStudentId = null;
    $nextStudentId = null;
    $rosterPosition = null;
    $rosterTotal = 0;

    if (!$assignmentId || !$studentId) {
        flash('error', 'Choose a student from an assigned block.');
        redirect('assigned_blocks');
    }
    $assignment = fetch_teacher_assignment(db(), $assignmentId, $teacherId);
    if (!$assignment) {
        deny_request('Subject assignment not found.', 404);
    }
    $submission = assignment_submission(db(), $assignmentId);
    $phase = assignment_phase($submission);
    $currentTerm = $phase === 'complete' ? 'final' : ($phase === 'final' ? 'final' : 'midterm');
    $locked = $phase === 'complete' || ($currentTerm === 'midterm' && !empty($submission['midterm_submitted_at'])) || ($currentTerm === 'final' && !empty($submission['final_submitted_at']));

    $fullRoster = enrollment_roster(db(), $assignmentId);
    $rosterIds = array_map(static fn($r) => (int)$r['student_id'], $fullRoster);
    $rosterTotal = count($rosterIds);
    $pos = array_search($studentId, $rosterIds, true);
    if ($pos === false) {
        deny_request('That student is not on this subject roster.', 404);
    }
    $rosterPosition = $pos + 1;
    $prevStudentId = $pos > 0 ? $rosterIds[$pos - 1] : null;
    $nextStudentId = $pos < $rosterTotal - 1 ? $rosterIds[$pos + 1] : null;

    $enrolled = db()->prepare(
        'SELECT s.id AS student_id, s.student_number, s.first_name, s.last_name
         FROM block_subject_enrollments e
         JOIN students s ON s.id=e.student_id
         WHERE e.assignment_id=? AND e.student_id=?'
    );
    $enrolled->execute([$assignmentId, $studentId]);
    $student = $enrolled->fetch();
    if (!$student) {
        deny_request('That student is not on this subject roster.', 404);
    }

    $items = list_score_items(db(), $assignmentId, $currentTerm);
    foreach ($items as $item) {
        $itemsByCategory[$item['category']][] = $item;
    }
    $scoreMap = student_score_map(db(), $assignmentId, $studentId);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if ($locked) {
            $scoreErrors[] = 'Scores for this term are locked.';
        } else {
            try {
                $rawScores = is_array($_POST['scores'] ?? null) ? $_POST['scores'] : [];
                save_student_score_drafts(db(), $assignmentId, $studentId, $rawScores, $currentTerm);
                $goNext = isset($_POST['save_next']) && $nextStudentId;
                flash('success', 'Draft scores saved.');
                if ($goNext) {
                    redirect('submit_scores', ['assignment_id' => $assignmentId, 'student_id' => $nextStudentId]);
                }
                redirect('assigned_blocks', ['assignment_id' => $assignmentId]);
            } catch (InvalidArgumentException $e) {
                $scoreErrors[] = $e->getMessage();
                $scoreMap = [];
                foreach ($rawScores as $iid => $val) {
                    $scoreMap[(int)$iid] = $val;
                }
            } catch (PDOException $e) {
                error_log('Draft score save failed: ' . $e->getMessage());
                $scoreErrors[] = 'Draft scores could not be saved. Please try again.';
            }
        }
    }
}
