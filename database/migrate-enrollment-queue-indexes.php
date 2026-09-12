<?php
declare(strict_types=1);
/**
 * Adds enrollment queue indexes for existing databases created before
 * idx_enroll_queue / ay_sem / names / student_number existed.
 * Safe to re-run: skips indexes that are already present.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

$indexes = [
    'idx_enroll_queue' => '(status, academic_year, course_id, created_at)',
    'idx_enroll_ay_sem' => '(academic_year, semester)',
    'idx_enroll_names' => '(last_name, first_name)',
    'idx_enroll_student_number' => '(student_number)',
];

$dbName = (string)db()->query('SELECT DATABASE()')->fetchColumn();
foreach ($indexes as $name => $cols) {
    $check = db()->prepare(
        'SELECT 1 FROM information_schema.statistics
         WHERE table_schema=? AND table_name=? AND index_name=? LIMIT 1'
    );
    $check->execute([$dbName, 'enrollment_applications', $name]);
    if ($check->fetchColumn()) {
        echo "skip: $name already exists\n";
        continue;
    }
    db()->exec("ALTER TABLE enrollment_applications ADD INDEX $name $cols");
    echo "added: $name\n";
}
echo "done\n";
