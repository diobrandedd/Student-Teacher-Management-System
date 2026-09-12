<?php
declare(strict_types=1);
/**
 * Create subjects catalog, backfill from block_subject_assignments, add subject_id FK.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

db()->exec(
    'CREATE TABLE IF NOT EXISTS subjects (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      code VARCHAR(30) NOT NULL,
      title VARCHAR(150) NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uq_subjects_code (code)
    ) ENGINE=InnoDB'
);
echo "subjects table ready\n";

$col = db()->prepare(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='block_subject_assignments' AND COLUMN_NAME='subject_id'"
);
$col->execute();
$hasSubjectId = (int)$col->fetchColumn() > 0;

if (!$hasSubjectId) {
    db()->exec('ALTER TABLE block_subject_assignments ADD COLUMN subject_id BIGINT UNSIGNED NULL AFTER teacher_id');
    echo "added subject_id column\n";
}

// Backfill subjects from existing assignment free-text
$rows = db()->query(
    'SELECT DISTINCT subject_code, subject_name FROM block_subject_assignments ORDER BY subject_code, subject_name'
)->fetchAll();
$insert = db()->prepare('INSERT IGNORE INTO subjects (code, title) VALUES (?, ?)');
foreach ($rows as $row) {
    $code = trim((string)$row['subject_code']);
    $title = trim((string)$row['subject_name']);
    if ($code === '' || $title === '') {
        continue;
    }
    $insert->execute([$code, $title]);
}

// Seed defaults if still empty
$count = (int)db()->query('SELECT COUNT(*) FROM subjects')->fetchColumn();
if ($count < 1) {
    $defaults = [
        ['IT 411', 'Systems Analysis and Design'],
        ['IT 421', 'Information Security'],
        ['IT 101', 'Introduction to Computing'],
        ['IT 201', 'Data Structures'],
    ];
    foreach ($defaults as [$code, $title]) {
        $insert->execute([$code, $title]);
    }
    echo "seeded default subjects\n";
}

// Link assignments by code (prefer matching title when multiple)
$upd = db()->prepare(
    'UPDATE block_subject_assignments a
     JOIN subjects s ON s.code = a.subject_code
     SET a.subject_id = s.id
     WHERE a.subject_id IS NULL'
);
$upd->execute();
echo "backfilled subject_id by code\n";

// Orphan codes: create subject rows then link
$orphans = db()->query(
    'SELECT DISTINCT subject_code, subject_name FROM block_subject_assignments WHERE subject_id IS NULL'
)->fetchAll();
foreach ($orphans as $row) {
    $code = trim((string)$row['subject_code']);
    $title = trim((string)$row['subject_name']);
    if ($code === '') {
        continue;
    }
    $insert->execute([$code, $title !== '' ? $title : $code]);
}
$upd->execute();

$fk = db()->prepare(
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='block_subject_assignments'
       AND CONSTRAINT_NAME='fk_bsa_subject'"
);
$fk->execute();
if (!(int)$fk->fetchColumn()) {
    // Null any still-unlinked then set NOT NULL if all linked
    $nullLeft = (int)db()->query('SELECT COUNT(*) FROM block_subject_assignments WHERE subject_id IS NULL')->fetchColumn();
    if ($nullLeft === 0) {
        db()->exec('ALTER TABLE block_subject_assignments MODIFY subject_id BIGINT UNSIGNED NOT NULL');
    }
    db()->exec(
        'ALTER TABLE block_subject_assignments
         ADD CONSTRAINT fk_bsa_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT'
    );
    echo "fk_bsa_subject added\n";
}

echo "done\n";
