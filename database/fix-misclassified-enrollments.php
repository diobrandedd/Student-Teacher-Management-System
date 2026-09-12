<?php
declare(strict_types=1);
/**
 * Fix misclassified Enroll Now rows:
 * - new + year 2 or 3 → moving_up (with institution last_school; student_number filled when a unique name match exists)
 * - new + year 4 → delete (4th-year applicants are not moving up)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/enrollment.php';

$bad = db()->query(
    "SELECT id, application_type, year_level, student_number, last_name, first_name, middle_name, status, email, last_school
     FROM enrollment_applications
     WHERE application_type='new' AND year_level IN (2,3,4)
     ORDER BY year_level, id"
)->fetchAll();

echo 'Found ' . count($bad) . " misclassified new-student application(s).\n";

$converted = 0;
$deleted = 0;
$matchedIds = 0;

foreach ($bad as $row) {
    $id = (int)$row['id'];
    $year = (int)$row['year_level'];
    $label = '#' . $id . ' ' . $row['last_name'] . ', ' . $row['first_name'] . ' (y' . $year . ', ' . $row['status'] . ')';

    if ($year === 4) {
        $root = realpath(enrollment_storage_root());
        $appDir = $root !== false ? $root . DIRECTORY_SEPARATOR . $id : null;
        db()->prepare('DELETE FROM enrollment_applications WHERE id=?')->execute([$id]);
        if ($appDir !== null && is_dir($appDir)) {
            foreach (glob($appDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($appDir);
        }
        audit('DELETE', 'enrollment_application', $id, 'Removed invalid new-student 4th-year application (cannot move up)');
        echo "deleted: $label\n";
        $deleted++;
        continue;
    }

    $studentNumber = trim((string)($row['student_number'] ?? ''));
    if ($studentNumber === '') {
        $lookup = db()->prepare(
            'SELECT student_number FROM students
             WHERE LOWER(TRIM(first_name))=LOWER(TRIM(?)) AND LOWER(TRIM(last_name))=LOWER(TRIM(?))
             ORDER BY id ASC'
        );
        $lookup->execute([(string)$row['first_name'], (string)$row['last_name']]);
        $matches = $lookup->fetchAll(PDO::FETCH_COLUMN);
        if (count($matches) === 1) {
            $studentNumber = (string)$matches[0];
            $matchedIds++;
        }
    }

    db()->prepare(
        "UPDATE enrollment_applications SET
            application_type='moving_up',
            last_school='This institution (moving up)',
            last_school_address=NULL,
            year_graduated=NULL,
            course_id_second=NULL,
            student_number=COALESCE(NULLIF(?, ''), student_number)
         WHERE id=? AND application_type='new'"
    )->execute([$studentNumber, $id]);
    audit(
        'UPDATE',
        'enrollment_application',
        $id,
        'Reclassified new y' . $year . ' application as moving_up'
            . ($studentNumber !== '' ? '; student_number ' . $studentNumber : '; student_number still blank — registrar must supply ID before approval')
    );
    echo 'converted: ' . $label . ($studentNumber !== '' ? " → ID $studentNumber" : ' → needs Student ID') . "\n";
    $converted++;
}

echo "done: converted=$converted deleted=$deleted name_matched_ids=$matchedIds\n";
