<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/grading.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

[$ok, $errors] = grading_validate_weights([
    'quiz' => '20',
    'activities' => '20',
    'attendance' => '10',
    'projects' => '20',
    'exam' => '30',
    'midterm' => '40',
    'final' => '60',
]);
check($errors === [], 'Valid weights must pass.');
check(abs($ok['quiz'] - 20.0) < 0.001, 'Quiz weight parsed.');

[, $bad] = grading_validate_weights([
    'quiz' => '25',
    'activities' => '25',
    'attendance' => '10',
    'projects' => '20',
    'exam' => '30',
    'midterm' => '50',
    'final' => '40',
]);
check($bad !== [], 'Category sum over 100 must fail.');

[, $badTerm] = grading_validate_weights([
    'quiz' => '20',
    'activities' => '20',
    'attendance' => '10',
    'projects' => '20',
    'exam' => '30',
    'midterm' => '70',
    'final' => '40',
]);
check($badTerm !== [], 'Term blend over 100 must fail.');

$weights = [
    'quiz' => 20.0,
    'activities' => 20.0,
    'attendance' => 10.0,
    'projects' => 20.0,
    'exam' => 30.0,
];
$items = [
    ['id' => 1, 'category' => 'quiz', 'title' => 'Q1', 'max_score' => 50],
    ['id' => 2, 'category' => 'quiz', 'title' => 'Q2', 'max_score' => 50],
    ['id' => 3, 'category' => 'activities', 'title' => 'A1', 'max_score' => 100],
    ['id' => 4, 'category' => 'attendance', 'title' => 'Att', 'max_score' => 100],
    ['id' => 5, 'category' => 'projects', 'title' => 'P1', 'max_score' => 100],
    ['id' => 6, 'category' => 'exam', 'title' => 'Exam', 'max_score' => 100],
];
$scores = [
    1 => 40,
    2 => 50,
    3 => 80,
    4 => 100,
    5 => 90,
    6 => 70,
];
// quiz pct = 90/100 = 0.9 → 18; act 0.8→16; att 1→10; proj 0.9→18; exam 0.7→21 = 83
$grade = compute_term_grade($items, $scores, $weights);
check(abs($grade - 83.0) < 0.01, 'Term grade compute expected 83, got ' . $grade);

$overall = overall_grade(80.0, 90.0, ['midterm' => 40.0, 'final' => 60.0]);
check(abs($overall - 86.0) < 0.01, 'Overall blend expected 86, got ' . $overall);

check(assignment_phase_label('midterm') === 'Midterm', 'Phase label midterm');
check(assignment_phase(['midterm_submitted_at' => '2026-01-01', 'final_submitted_at' => null]) === 'final', 'Phase after midterm');
check(assignment_phase(['midterm_submitted_at' => '2026-01-01', 'final_submitted_at' => '2026-02-01']) === 'complete', 'Phase complete');

echo "grading tests passed\n";
