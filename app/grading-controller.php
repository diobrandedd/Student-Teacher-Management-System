<?php
declare(strict_types=1);
require_role(['admin']);
require_once __DIR__ . '/grading.php';

$gradingErrors = [];
$weights = grading_weights();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    [$parsed, $gradingErrors] = grading_validate_weights($_POST);
    if (!$gradingErrors) {
        grading_save_weights(db(), $parsed, (int)user()['id']);
        // Clear settings cache by re-reading after unset — system_settings uses static
        audit('UPDATE', 'grading_weights', null, 'Updated grading weights');
        flash('success', 'Grading weights saved.');
        redirect('grading');
    }
    $weights = array_merge($weights, $parsed);
}
