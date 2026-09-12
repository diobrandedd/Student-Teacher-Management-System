<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

$reason = 'Reclassified from New student to Moving up, but no matching Student ID was on file. Please re-apply with Moving up and your existing Student ID.';
$stmt = db()->query(
    "SELECT id, last_name, first_name, year_level FROM enrollment_applications
     WHERE application_type='moving_up' AND year_level IN (2,3)
       AND (student_number IS NULL OR TRIM(student_number)='')
       AND status='pending'"
);
$rows = $stmt->fetchAll();
$update = db()->prepare(
    "UPDATE enrollment_applications
     SET status='rejected', reviewed_at=NOW(), reject_reason=?, reviewed_by=NULL
     WHERE id=? AND status='pending'"
);
foreach ($rows as $row) {
    $update->execute([$reason, (int)$row['id']]);
    audit('REJECT', 'enrollment_application', (int)$row['id'], 'Auto-rejected after reclassify: missing student ID');
    echo 'rejected #' . $row['id'] . ' ' . $row['last_name'] . ', ' . $row['first_name'] . " (y{$row['year_level']})\n";
}
echo 'done: ' . count($rows) . "\n";
