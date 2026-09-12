<?php
declare(strict_types=1);
require_role(['admin']);
require_once __DIR__ . '/subjects.php';

$subjectErrors = [];
$subjectCode = '';
$subjectTitle = '';
$subjectId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT) ?: null;
$subjectShow = isset($_GET['add']) || $subjectId;

if ($subjectId) {
    $stmt = db()->prepare('SELECT code, title FROM subjects WHERE id=?');
    $stmt->execute([$subjectId]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        exit('Subject not found.');
    }
    $subjectCode = (string)$row['code'];
    $subjectTitle = (string)$row['title'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $subjectCode = is_string($_POST['code'] ?? null) ? trim((string)$_POST['code']) : '';
    $subjectTitle = is_string($_POST['title'] ?? null) ? trim((string)$_POST['title']) : '';
    if ($subjectCode === '' || mb_strlen($subjectCode) > 30) {
        $subjectErrors[] = 'Enter a subject code of 1–30 characters.';
    } elseif (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]{0,29}$/', $subjectCode)) {
        $subjectErrors[] = 'Subject code may use letters, numbers, spaces, dots, underscores, or hyphens.';
    }
    if ($subjectTitle === '' || mb_strlen($subjectTitle) > 150) {
        $subjectErrors[] = 'Enter a subject title of 1–150 characters.';
    }
    if (!$subjectErrors) {
        db()->beginTransaction();
        try {
            if ($subjectId) {
                db()->prepare('UPDATE subjects SET code=?, title=? WHERE id=?')->execute([$subjectCode, $subjectTitle, $subjectId]);
            } else {
                db()->prepare('INSERT INTO subjects (code, title) VALUES (?,?)')->execute([$subjectCode, $subjectTitle]);
                $subjectId = (int)db()->lastInsertId();
            }
            db()->prepare('UPDATE block_subject_assignments SET subject_code=?, subject_name=? WHERE subject_id=?')
                ->execute([$subjectCode, $subjectTitle, $subjectId]);
            db()->prepare('UPDATE block_subject_enrollments e
                JOIN block_subject_assignments a ON a.id=e.assignment_id
                SET e.subject_code=?, e.subject_name=?
                WHERE a.subject_id=?')
                ->execute([$subjectCode, $subjectTitle, $subjectId]);
            audit('SAVE', 'subject', $subjectId, 'Saved subject ' . $subjectCode);
            db()->commit();
            flash('success', 'Subject saved.');
            redirect('subjects');
        } catch (PDOException $exception) {
            db()->rollBack();
            if (($exception->errorInfo[1] ?? null) === 1062) {
                $subjectErrors[] = 'That subject code already exists.';
            } else {
                error_log($exception->getMessage());
                $subjectErrors[] = 'Could not save. Please try again.';
            }
        }
    }
    $subjectShow = true;
}

$subjectSearch = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$like = '%' . strtr($subjectSearch, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
$stmt = db()->prepare(
    "SELECT s.*,
        (SELECT COUNT(*) FROM block_subject_assignments a WHERE a.subject_id=s.id) AS assigned_count
     FROM subjects s
     WHERE (?='' OR s.code LIKE ? ESCAPE '!' OR s.title LIKE ? ESCAPE '!')
     ORDER BY s.code, s.title"
);
$stmt->execute([$subjectSearch, $like, $like]);
$subjectRows = $stmt->fetchAll();
