<?php
declare(strict_types=1);

function grading_category_keys(): array
{
    return ['quiz', 'activities', 'attendance', 'projects', 'exam'];
}

function grading_term_keys(): array
{
    return ['midterm', 'final'];
}

function grading_category_labels(): array
{
    return [
        'quiz' => 'Quiz',
        'activities' => 'Activities',
        'attendance' => 'Attendance',
        'projects' => 'Projects',
        'exam' => 'Exam',
    ];
}

function grading_default_weights(): array
{
    return [
        'quiz' => 20.0,
        'activities' => 20.0,
        'attendance' => 10.0,
        'projects' => 20.0,
        'exam' => 30.0,
        'midterm' => 40.0,
        'final' => 60.0,
    ];
}

function grading_weights(): array
{
    $defaults = grading_default_weights();
    $settings = system_settings();
    $out = [];
    foreach ($defaults as $key => $default) {
        $raw = $settings['grade_weight_' . $key] ?? null;
        $out[$key] = $raw === null || $raw === '' ? $default : (float)$raw;
    }
    return $out;
}

function grading_validate_weights(array $input): array
{
    $errors = [];
    $weights = [];
    foreach (array_merge(grading_category_keys(), grading_term_keys()) as $key) {
        $raw = is_scalar($input[$key] ?? null) ? trim((string)$input[$key]) : '';
        if ($raw === '' || !is_numeric($raw)) {
            $errors[] = 'Enter a numeric weight for ' . $key . '.';
            continue;
        }
        $value = (float)$raw;
        if ($value < 0 || $value > 100) {
            $errors[] = ucfirst($key) . ' weight must be between 0 and 100.';
            continue;
        }
        $weights[$key] = round($value, 2);
    }
    if ($errors) {
        return [$weights, $errors];
    }
    $categorySum = 0.0;
    foreach (grading_category_keys() as $key) {
        $categorySum += $weights[$key];
    }
    $termSum = $weights['midterm'] + $weights['final'];
    if (abs($categorySum - 100.0) > 0.01) {
        $errors[] = 'Category weights (Quiz, Activities, Attendance, Projects, Exam) must total 100. Current total: ' . rtrim(rtrim(number_format($categorySum, 2), '0'), '.') . '.';
    }
    if (abs($termSum - 100.0) > 0.01) {
        $errors[] = 'Term blend (Midterm %, Final %) must total 100. Current total: ' . rtrim(rtrim(number_format($termSum, 2), '0'), '.') . '.';
    }
    return [$weights, $errors];
}

function grading_save_weights(PDO $db, array $weights, int $userId): void
{
    $stmt = $db->prepare('INSERT INTO system_settings(setting_key, setting_value, updated_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=VALUES(updated_by)');
    foreach (array_merge(grading_category_keys(), grading_term_keys()) as $key) {
        $stmt->execute(['grade_weight_' . $key, (string)$weights[$key], $userId]);
    }
}

function teacher_row_id_for_user(?int $userId = null): ?int
{
    $userId = $userId ?? (int)(user()['id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }
    $stmt = db()->prepare('SELECT id FROM teachers WHERE user_id=?');
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

function assignment_submission(PDO $db, int $assignmentId): array
{
    $stmt = $db->prepare('SELECT * FROM assignment_grade_submissions WHERE assignment_id=?');
    $stmt->execute([$assignmentId]);
    $row = $stmt->fetch() ?: [];
    return [
        'assignment_id' => $assignmentId,
        'midterm_submitted_at' => $row['midterm_submitted_at'] ?? null,
        'final_submitted_at' => $row['final_submitted_at'] ?? null,
        'midterm_submitted_by' => $row['midterm_submitted_by'] ?? null,
        'final_submitted_by' => $row['final_submitted_by'] ?? null,
    ];
}

function assignment_phase(array $submission): string
{
    if (!empty($submission['final_submitted_at'])) {
        return 'complete';
    }
    if (!empty($submission['midterm_submitted_at'])) {
        return 'final';
    }
    return 'midterm';
}

function assignment_phase_label(string $phase): string
{
    return match ($phase) {
        'complete' => 'Complete',
        'final' => 'Finals',
        default => 'Midterm',
    };
}

/** Student-facing publish status (not teacher workflow jargon). */
function student_publish_status_label(string $phase): string
{
    return match ($phase) {
        'complete' => 'All published',
        'final' => 'Midterm published',
        default => 'Awaiting midterm',
    };
}

function fetch_teacher_assignment(PDO $db, int $assignmentId, int $teacherId): ?array
{
    $stmt = $db->prepare(
        'SELECT a.*, b.name AS block_name
         FROM block_subject_assignments a
         JOIN blocks b ON b.id=a.block_id
         WHERE a.id=? AND a.teacher_id=?'
    );
    $stmt->execute([$assignmentId, $teacherId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function list_teacher_assignments(PDO $db, int $teacherId): array
{
    $stmt = $db->prepare(
        'SELECT a.id, a.block_id, a.subject_code, a.subject_name, b.name AS block_name,
                (SELECT COUNT(*) FROM block_subject_enrollments e WHERE e.assignment_id=a.id) AS roster_count,
                s.midterm_submitted_at, s.final_submitted_at
         FROM block_subject_assignments a
         JOIN blocks b ON b.id=a.block_id
         LEFT JOIN assignment_grade_submissions s ON s.assignment_id=a.id
         WHERE a.teacher_id=?
         ORDER BY b.name, a.subject_code'
    );
    $stmt->execute([$teacherId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['phase'] = assignment_phase([
            'midterm_submitted_at' => $row['midterm_submitted_at'] ?? null,
            'final_submitted_at' => $row['final_submitted_at'] ?? null,
        ]);
        $row['phase_label'] = assignment_phase_label($row['phase']);
    }
    unset($row);
    return $rows;
}

function list_score_items(PDO $db, int $assignmentId, ?string $term = null): array
{
    if ($term) {
        $stmt = $db->prepare('SELECT * FROM subject_score_items WHERE assignment_id=? AND term=? ORDER BY category, sort_order, id');
        $stmt->execute([$assignmentId, $term]);
    } else {
        $stmt = $db->prepare('SELECT * FROM subject_score_items WHERE assignment_id=? ORDER BY term, category, sort_order, id');
        $stmt->execute([$assignmentId]);
    }
    return $stmt->fetchAll();
}

function save_score_item(PDO $db, int $assignmentId, array $input, ?int $itemId = null): int
{
    $term = (string)($input['term'] ?? '');
    $category = (string)($input['category'] ?? '');
    $title = trim((string)($input['title'] ?? ''));
    $maxScore = is_scalar($input['max_score'] ?? null) ? trim((string)$input['max_score']) : '';
    if (!in_array($term, grading_term_keys(), true)) {
        throw new InvalidArgumentException('Select midterm or final for this item.');
    }
    if (!in_array($category, grading_category_keys(), true)) {
        throw new InvalidArgumentException('Select a valid category.');
    }
    if ($title === '' || mb_strlen($title) > 150) {
        throw new InvalidArgumentException('Enter a title up to 150 characters.');
    }
    if ($maxScore === '' || !is_numeric($maxScore) || (float)$maxScore <= 0) {
        throw new InvalidArgumentException('Max score must be a number greater than 0.');
    }
    $submission = assignment_submission($db, $assignmentId);
    $phase = assignment_phase($submission);
    if ($term === 'midterm' && !empty($submission['midterm_submitted_at'])) {
        throw new InvalidArgumentException('Midterm is submitted. Midterm score items cannot be changed.');
    }
    if ($term === 'final' && !empty($submission['final_submitted_at'])) {
        throw new InvalidArgumentException('Finals are submitted. Final score items cannot be changed.');
    }
    if ($category === 'exam') {
        $check = $db->prepare('SELECT id FROM subject_score_items WHERE assignment_id=? AND term=? AND category=\'exam\' AND (? IS NULL OR id<>?) LIMIT 1');
        $check->execute([$assignmentId, $term, $itemId, $itemId]);
        if ($check->fetchColumn()) {
            throw new InvalidArgumentException($term === 'midterm'
                ? 'Only one midterm exam item is allowed for this subject.'
                : 'Only one final exam item is allowed for this subject.');
        }
        if ($term === 'midterm' && $phase !== 'midterm') {
            throw new InvalidArgumentException('Midterm exam can only be set before midterm submission.');
        }
    }
    $max = round((float)$maxScore, 2);
    if ($itemId) {
        $own = $db->prepare('SELECT id FROM subject_score_items WHERE id=? AND assignment_id=?');
        $own->execute([$itemId, $assignmentId]);
        if (!$own->fetchColumn()) {
            throw new InvalidArgumentException('Score item not found.');
        }
        $db->prepare('UPDATE subject_score_items SET term=?, category=?, title=?, max_score=? WHERE id=? AND assignment_id=?')
            ->execute([$term, $category, $title, $max, $itemId, $assignmentId]);
        return $itemId;
    }
    $maxSort = $db->prepare('SELECT COALESCE(MAX(sort_order),0) FROM subject_score_items WHERE assignment_id=? AND term=? AND category=?');
    $maxSort->execute([$assignmentId, $term, $category]);
    $sort = (int)$maxSort->fetchColumn() + 1;
    $db->prepare('INSERT INTO subject_score_items (assignment_id, term, category, title, max_score, sort_order) VALUES (?,?,?,?,?,?)')
        ->execute([$assignmentId, $term, $category, $title, $max, $sort]);
    return (int)$db->lastInsertId();
}

function delete_score_item(PDO $db, int $assignmentId, int $itemId): void
{
    $stmt = $db->prepare('SELECT * FROM subject_score_items WHERE id=? AND assignment_id=?');
    $stmt->execute([$itemId, $assignmentId]);
    $item = $stmt->fetch();
    if (!$item) {
        throw new InvalidArgumentException('Score item not found.');
    }
    $submission = assignment_submission($db, $assignmentId);
    if ($item['term'] === 'midterm' && !empty($submission['midterm_submitted_at'])) {
        throw new InvalidArgumentException('Midterm is submitted. Midterm score items cannot be removed.');
    }
    if ($item['term'] === 'final' && !empty($submission['final_submitted_at'])) {
        throw new InvalidArgumentException('Finals are submitted. Final score items cannot be removed.');
    }
    $db->prepare('DELETE FROM subject_score_items WHERE id=? AND assignment_id=?')->execute([$itemId, $assignmentId]);
}

function enrollment_roster(PDO $db, int $assignmentId): array
{
    $stmt = $db->prepare(
        'SELECT e.student_id, s.student_number, s.first_name, s.last_name, s.academic_status
         FROM block_subject_enrollments e
         JOIN students s ON s.id=e.student_id
         WHERE e.assignment_id=?
         ORDER BY s.last_name, s.first_name'
    );
    $stmt->execute([$assignmentId]);
    return $stmt->fetchAll();
}

function student_score_map(PDO $db, int $assignmentId, int $studentId): array
{
    $stmt = $db->prepare('SELECT item_id, score FROM student_score_entries WHERE assignment_id=? AND student_id=?');
    $stmt->execute([$assignmentId, $studentId]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int)$row['item_id']] = $row['score'];
    }
    return $map;
}

function save_student_score_drafts(PDO $db, int $assignmentId, int $studentId, array $scoresByItem, string $term): void
{
    $submission = assignment_submission($db, $assignmentId);
    if ($term === 'midterm' && !empty($submission['midterm_submitted_at'])) {
        throw new InvalidArgumentException('Midterm grades are locked for this subject.');
    }
    if ($term === 'final' && !empty($submission['final_submitted_at'])) {
        throw new InvalidArgumentException('Final grades are locked for this subject.');
    }
    $enrolled = $db->prepare('SELECT 1 FROM block_subject_enrollments WHERE assignment_id=? AND student_id=?');
    $enrolled->execute([$assignmentId, $studentId]);
    if (!$enrolled->fetchColumn()) {
        throw new InvalidArgumentException('That student is not on this subject roster.');
    }
    $items = list_score_items($db, $assignmentId, $term);
    if (!$items) {
        throw new InvalidArgumentException('Add score items for this term in My subjects before entering scores.');
    }
    $allowed = [];
    foreach ($items as $item) {
        $allowed[(int)$item['id']] = $item;
    }
    $upsert = $db->prepare(
        'INSERT INTO student_score_entries (assignment_id, student_id, item_id, score) VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE score=VALUES(score)'
    );
    foreach ($scoresByItem as $itemId => $rawScore) {
        $itemId = (int)$itemId;
        if (!isset($allowed[$itemId])) {
            continue;
        }
        $raw = is_scalar($rawScore) ? trim((string)$rawScore) : '';
        if ($raw === '') {
            $upsert->execute([$assignmentId, $studentId, $itemId, null]);
            continue;
        }
        if (!is_numeric($raw)) {
            throw new InvalidArgumentException('Scores must be numbers.');
        }
        $score = round((float)$raw, 2);
        $max = (float)$allowed[$itemId]['max_score'];
        if ($score < 0 || $score > $max) {
            throw new InvalidArgumentException('Score for "' . $allowed[$itemId]['title'] . '" must be between 0 and ' . rtrim(rtrim(number_format($max, 2), '0'), '.') . '.');
        }
        $upsert->execute([$assignmentId, $studentId, $itemId, $score]);
    }
}

function student_has_complete_drafts(PDO $db, int $assignmentId, int $studentId, string $term): bool
{
    $items = list_score_items($db, $assignmentId, $term);
    if (!$items) {
        return false;
    }
    $map = student_score_map($db, $assignmentId, $studentId);
    foreach ($items as $item) {
        $score = $map[(int)$item['id']] ?? null;
        if ($score === null || $score === '') {
            return false;
        }
    }
    return true;
}

/**
 * Category percent = sum(score)/sum(max) for items in category+term.
 * Term grade = sum(category_pct * weight). Weights are percent points (sum 100).
 */
function compute_term_grade(array $items, array $scoreMap, array $categoryWeights): float
{
    $byCategory = [];
    foreach ($items as $item) {
        $cat = $item['category'];
        if (!isset($byCategory[$cat])) {
            $byCategory[$cat] = ['sum_score' => 0.0, 'sum_max' => 0.0];
        }
        $itemId = (int)$item['id'];
        $score = $scoreMap[$itemId] ?? null;
        if ($score === null || $score === '') {
            throw new InvalidArgumentException('Missing score for "' . $item['title'] . '".');
        }
        $byCategory[$cat]['sum_score'] += (float)$score;
        $byCategory[$cat]['sum_max'] += (float)$item['max_score'];
    }
    $grade = 0.0;
    foreach (grading_category_keys() as $cat) {
        $weight = (float)($categoryWeights[$cat] ?? 0);
        if ($weight <= 0) {
            continue;
        }
        if (!isset($byCategory[$cat]) || $byCategory[$cat]['sum_max'] <= 0) {
            throw new InvalidArgumentException('Category "' . (grading_category_labels()[$cat] ?? $cat) . '" has weight but no score items for this term.');
        }
        $pct = $byCategory[$cat]['sum_score'] / $byCategory[$cat]['sum_max'];
        $grade += $pct * $weight;
    }
    return round($grade, 2);
}

function validate_term_ready_for_submit(PDO $db, int $assignmentId, string $term, array $weights): void
{
    $items = list_score_items($db, $assignmentId, $term);
    $present = [];
    foreach ($items as $item) {
        $present[$item['category']] = true;
    }
    foreach (grading_category_keys() as $cat) {
        $weight = (float)($weights[$cat] ?? 0);
        if ($weight > 0 && empty($present[$cat])) {
            throw new InvalidArgumentException('Add at least one ' . (grading_category_labels()[$cat] ?? $cat) . ' item for ' . $term . ' before submitting (weight is ' . rtrim(rtrim(number_format($weight, 2), '0'), '.') . ').');
        }
    }
    $roster = enrollment_roster($db, $assignmentId);
    if (!$roster) {
        throw new InvalidArgumentException('No students are enrolled on this subject roster. Match roster from Blocks first.');
    }
    foreach ($roster as $student) {
        if (!student_has_complete_drafts($db, $assignmentId, (int)$student['student_id'], $term)) {
            throw new InvalidArgumentException('Every enrolled student needs a score for each ' . $term . ' item before you can submit.');
        }
    }
}

function submit_assignment_term(PDO $db, int $assignmentId, string $term, int $userId): int
{
    if (!in_array($term, grading_term_keys(), true)) {
        throw new InvalidArgumentException('Invalid term.');
    }
    $submission = assignment_submission($db, $assignmentId);
    if ($term === 'midterm') {
        if (!empty($submission['midterm_submitted_at'])) {
            throw new InvalidArgumentException('Midterm is already submitted for this subject.');
        }
    } else {
        if (empty($submission['midterm_submitted_at'])) {
            throw new InvalidArgumentException('Submit midterm before finals.');
        }
        if (!empty($submission['final_submitted_at'])) {
            throw new InvalidArgumentException('Finals are already submitted for this subject.');
        }
    }
    $weights = grading_weights();
    validate_term_ready_for_submit($db, $assignmentId, $term, $weights);
    $items = list_score_items($db, $assignmentId, $term);
    $roster = enrollment_roster($db, $assignmentId);
    $upsertGrade = $db->prepare(
        'INSERT INTO student_term_grades (assignment_id, student_id, term, grade, submitted_at) VALUES (?,?,?,?,NOW())
         ON DUPLICATE KEY UPDATE grade=VALUES(grade), submitted_at=VALUES(submitted_at)'
    );
    $count = 0;
    $db->beginTransaction();
    try {
        foreach ($roster as $student) {
            $studentId = (int)$student['student_id'];
            $map = student_score_map($db, $assignmentId, $studentId);
            $grade = compute_term_grade($items, $map, $weights);
            $upsertGrade->execute([$assignmentId, $studentId, $term, $grade]);
            $count++;
        }
        $db->prepare(
            'INSERT INTO assignment_grade_submissions (assignment_id, midterm_submitted_at, midterm_submitted_by, final_submitted_at, final_submitted_by)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               midterm_submitted_at=COALESCE(VALUES(midterm_submitted_at), midterm_submitted_at),
               midterm_submitted_by=COALESCE(VALUES(midterm_submitted_by), midterm_submitted_by),
               final_submitted_at=COALESCE(VALUES(final_submitted_at), final_submitted_at),
               final_submitted_by=COALESCE(VALUES(final_submitted_by), final_submitted_by)'
        )->execute([
            $assignmentId,
            $term === 'midterm' ? date('Y-m-d H:i:s') : null,
            $term === 'midterm' ? $userId : null,
            $term === 'final' ? date('Y-m-d H:i:s') : null,
            $term === 'final' ? $userId : null,
        ]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return $count;
}

function overall_grade(float $midterm, float $final, array $weights): float
{
    return round(($midterm * ((float)$weights['midterm'] / 100.0)) + ($final * ((float)$weights['final'] / 100.0)), 2);
}

function student_term_grades_map(PDO $db, int $assignmentId): array
{
    $stmt = $db->prepare('SELECT student_id, term, grade FROM student_term_grades WHERE assignment_id=?');
    $stmt->execute([$assignmentId]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int)$row['student_id']][$row['term']] = (float)$row['grade'];
    }
    return $map;
}

function student_subject_grades(PDO $db, int $studentId): array
{
    $weights = grading_weights();
    $stmt = $db->prepare(
        'SELECT e.assignment_id, e.subject_code, e.subject_name, e.block_id, b.name AS block_name,
                t.first_name AS teacher_first, t.middle_name AS teacher_middle, t.last_name AS teacher_last,
                u.full_name AS teacher_full_name,
                s.midterm_submitted_at, s.final_submitted_at,
                g_mid.grade AS midterm_grade, g_fin.grade AS final_grade
         FROM block_subject_enrollments e
         JOIN blocks b ON b.id=e.block_id
         JOIN block_subject_assignments a ON a.id=e.assignment_id
         JOIN teachers t ON t.id=a.teacher_id
         JOIN users u ON u.id=t.user_id
         LEFT JOIN assignment_grade_submissions s ON s.assignment_id=e.assignment_id
         LEFT JOIN student_term_grades g_mid ON g_mid.assignment_id=e.assignment_id AND g_mid.student_id=e.student_id AND g_mid.term=\'midterm\'
         LEFT JOIN student_term_grades g_fin ON g_fin.assignment_id=e.assignment_id AND g_fin.student_id=e.student_id AND g_fin.term=\'final\'
         WHERE e.student_id=?
         ORDER BY b.name, e.subject_code'
    );
    $stmt->execute([$studentId]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $mid = $row['midterm_grade'] !== null ? (float)$row['midterm_grade'] : null;
        $fin = $row['final_grade'] !== null ? (float)$row['final_grade'] : null;
        $overall = ($mid !== null && $fin !== null && !empty($row['final_submitted_at']))
            ? overall_grade($mid, $fin, $weights)
            : null;
        $rows[] = [
            'assignment_id' => (int)$row['assignment_id'],
            'block_name' => $row['block_name'],
            'subject_code' => $row['subject_code'],
            'subject_name' => $row['subject_name'],
            'teacher_name' => teacher_display_name([
                'first_name' => $row['teacher_first'],
                'middle_name' => $row['teacher_middle'],
                'last_name' => $row['teacher_last'],
                'full_name' => $row['teacher_full_name'],
            ]),
            'midterm_grade' => !empty($row['midterm_submitted_at']) ? $mid : null,
            'final_grade' => !empty($row['final_submitted_at']) ? $fin : null,
            'overall_grade' => $overall,
            'phase' => assignment_phase([
                'midterm_submitted_at' => $row['midterm_submitted_at'] ?? null,
                'final_submitted_at' => $row['final_submitted_at'] ?? null,
            ]),
        ];
    }
    return $rows;
}

/** One enrollment for this student only — never another student's grades. */
function student_own_subject_grade(PDO $db, int $studentId, int $assignmentId): ?array
{
    foreach (student_subject_grades($db, $studentId) as $row) {
        if ((int)$row['assignment_id'] === $assignmentId) {
            return $row;
        }
    }
    return null;
}

function format_grade(?float $grade): string
{
    if ($grade === null) {
        return '—';
    }
    return rtrim(rtrim(number_format($grade, 2), '0'), '.');
}
