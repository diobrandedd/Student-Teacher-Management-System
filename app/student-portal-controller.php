<?php
declare(strict_types=1);
require_role(['student']);
require_once __DIR__ . '/grading.php';

$studentId = (int)user()['id'];
$assignmentId = filter_input(INPUT_GET, 'assignment_id', FILTER_VALIDATE_INT) ?: null;
$subjects = student_subject_grades(db(), $studentId);
$selectedSubject = null;
$subjectLookupError = false;

if ($assignmentId) {
    $selectedSubject = student_own_subject_grade(db(), $studentId, $assignmentId);
    if (!$selectedSubject) {
        http_response_code(404);
        $subjectLookupError = true;
        $assignmentId = null;
    }
}
