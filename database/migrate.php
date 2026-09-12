<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
db()->exec(file_get_contents(__DIR__ . '/migrations/2026_09_05_add_blocks.sql'));
foreach (explode(';', file_get_contents(__DIR__ . '/migrations/2026_09_05_add_academic_catalogs.sql')) as $sql) {
    $sql=trim($sql);
    if ($sql==='') continue;
    if (preg_match('/^ALTER TABLE (students|teachers) ADD COLUMN IF NOT EXISTS (course_id|department_id)/', $sql, $match)) {
        $check=db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $check->execute([$match[1],$match[2]]);
        if ((int)$check->fetchColumn()) continue;
        $sql=str_replace('ADD COLUMN IF NOT EXISTS','ADD COLUMN',$sql);
    }
    db()->exec($sql);
}
foreach (['students'=>['fk_students_course','course_id','courses'], 'teachers'=>['fk_teachers_department','department_id','departments']] as $table=>$relation) {
    [$constraint,$column,$target] = $relation;
    $check = db()->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?');
    $check->execute([$table,$constraint]);
    if (!(int)$check->fetchColumn()) db()->exec("ALTER TABLE $table ADD CONSTRAINT $constraint FOREIGN KEY ($column) REFERENCES $target(id) ON DELETE RESTRICT");
}
foreach (explode(';', file_get_contents(__DIR__ . '/migrations/2026_09_05_teacher_profile.sql')) as $sql) {
    $sql = trim(preg_replace('/^--.*$/m', '', $sql) ?? '');
    if ($sql === '') continue;
    if (preg_match('/^ALTER TABLE teachers ADD COLUMN (\w+)/', $sql, $match)) {
        $check = db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $check->execute(['teachers', $match[1]]);
        if ((int)$check->fetchColumn()) continue;
    }
    db()->exec($sql);
}
$backfill = db()->query("SELECT t.id, u.full_name FROM teachers t JOIN users u ON u.id=t.user_id WHERE (t.first_name IS NULL OR t.first_name='') AND (t.last_name IS NULL OR t.last_name='')");
foreach ($backfill as $row) {
    [$first, $middle, $last] = split_person_name((string)$row['full_name']);
    db()->prepare('UPDATE teachers SET first_name=?, middle_name=?, last_name=? WHERE id=?')->execute([$first, $middle !== '' ? $middle : null, $last, $row['id']]);
}
$mcp = db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
$mcp->execute(['users', 'must_change_password']);
if (!(int)$mcp->fetchColumn()) {
    db()->exec('ALTER TABLE users ADD COLUMN must_change_password BOOLEAN NOT NULL DEFAULT FALSE');
}

$tables = db()->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('block_subject_assignments','block_subject_enrollments')")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('block_subject_assignments', $tables, true)) {
    foreach (explode(';', file_get_contents(__DIR__ . '/migrations/2026_09_05_block_subjects.sql')) as $sql) {
        $sql = trim($sql);
        if ($sql !== '') db()->exec($sql);
    }
}

$col = db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
$col->execute(['blocks', 'teacher_id']);
if ((int)$col->fetchColumn()) {
    $legacy = db()->query('SELECT id, teacher_id FROM blocks WHERE teacher_id IS NOT NULL');
    $insertAssign = db()->prepare('INSERT INTO block_subject_assignments (block_id, teacher_id, subject_code, subject_name) VALUES (?,?,?,?)');
    $hasAssign = db()->prepare('SELECT COUNT(*) FROM block_subject_assignments WHERE block_id=?');
    foreach ($legacy as $block) {
        $hasAssign->execute([(int)$block['id']]);
        if ((int)$hasAssign->fetchColumn()) continue;
        $insertAssign->execute([(int)$block['id'], (int)$block['teacher_id'], 'MIGRATE', 'Subject not set']);
        $assignmentId = (int)db()->lastInsertId();
        $members = db()->prepare('SELECT student_id FROM block_students WHERE block_id=?');
        $members->execute([(int)$block['id']]);
        $insertEnroll = db()->prepare('INSERT INTO block_subject_enrollments (assignment_id, student_id, block_id, teacher_id, subject_code, subject_name, synced_at) VALUES (?,?,?,?,?,?,NOW())');
        foreach ($members as $member) {
            $insertEnroll->execute([$assignmentId, (int)$member['student_id'], (int)$block['id'], (int)$block['teacher_id'], 'MIGRATE', 'Subject not set']);
        }
    }
    $fk = db()->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='blocks' AND CONSTRAINT_TYPE='FOREIGN KEY' AND CONSTRAINT_NAME='fk_blocks_teacher'")->fetchColumn();
    if ($fk) db()->exec('ALTER TABLE blocks DROP FOREIGN KEY fk_blocks_teacher');
    $idx = db()->query("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='blocks' AND INDEX_NAME='idx_blocks_teacher'")->fetchColumn();
    if ($idx) db()->exec('ALTER TABLE blocks DROP INDEX idx_blocks_teacher');
    db()->exec('ALTER TABLE blocks DROP COLUMN teacher_id');
}

$statusCol = db()->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='academic_status'")->fetchColumn();
if (is_string($statusCol) && !str_contains($statusCol, "Dropped out")) {
    foreach (explode(';', file_get_contents(__DIR__ . '/migrations/2026_09_05_academic_status.sql')) as $sql) {
        $sql = trim(preg_replace('/^--.*$/m', '', $sql) ?? '');
        if ($sql !== '') db()->exec($sql);
    }
}

$col->execute(['students', 'username']);
if (!(int)$col->fetchColumn()) {
    foreach (explode(';', file_get_contents(__DIR__ . '/migrations/2026_09_05_student_auth.sql')) as $sql) {
        $sql = trim(preg_replace('/^--.*$/m', '', $sql) ?? '');
        if ($sql === '') continue;
        if (preg_match('/^ALTER TABLE students DROP FOREIGN KEY (\w+)/i', $sql, $match)) {
            $fk = db()->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='students' AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE='FOREIGN KEY'");
            $fk->execute([$match[1]]);
            if (!(int)$fk->fetchColumn()) continue;
        }
        if (preg_match('/^ALTER TABLE students DROP COLUMN (\w+)/i', $sql, $match)) {
            $col->execute(['students', $match[1]]);
            if (!(int)$col->fetchColumn()) continue;
        }
        if (preg_match('/^ALTER TABLE students ADD UNIQUE KEY uq_students_username/i', $sql)) {
            $idx = db()->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND INDEX_NAME='uq_students_username'")->fetchColumn();
            if ((int)$idx) continue;
        }
        if (preg_match('/^DELETE FROM users WHERE role = \'student\'/i', $sql)) {
            $left = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
            if ($left === 0) continue;
        }
        if (preg_match('/^ALTER TABLE users MODIFY role ENUM/i', $sql)) {
            $roleType = db()->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role'")->fetchColumn();
            if (is_string($roleType) && !str_contains($roleType, 'student')) continue;
            $left = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
            if ($left > 0) continue;
        }
        db()->exec($sql);
    }
}

$roleType = db()->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role'")->fetchColumn();
if (is_string($roleType) && str_contains($roleType, 'student')) {
    $left = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    if ($left === 0) {
        db()->exec("ALTER TABLE users MODIFY role ENUM('admin','staff') NOT NULL");
    }
}

$gradeTables = db()->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('subject_score_items','student_score_entries','assignment_grade_submissions','student_term_grades')")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('subject_score_items', $gradeTables, true)) {
    foreach (explode(';', file_get_contents(__DIR__ . '/migrations/2026_09_05_grading.sql')) as $sql) {
        $sql = trim(preg_replace('/^--.*$/m', '', $sql) ?? '');
        if ($sql !== '') db()->exec($sql);
    }
} else {
    foreach ([
        'grade_weight_quiz' => '20',
        'grade_weight_activities' => '20',
        'grade_weight_attendance' => '10',
        'grade_weight_projects' => '20',
        'grade_weight_exam' => '30',
        'grade_weight_midterm' => '40',
        'grade_weight_final' => '60',
    ] as $key => $value) {
        db()->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key)')->execute([$key, $value]);
    }
}

foreach (['middle_name', 'display_name'] as $studentCol) {
    $col->execute(['students', $studentCol]);
    if (!(int)$col->fetchColumn()) {
        if ($studentCol === 'middle_name') {
            db()->exec('ALTER TABLE students ADD COLUMN middle_name VARCHAR(80) NULL AFTER first_name');
        } else {
            db()->exec('ALTER TABLE students ADD COLUMN display_name VARCHAR(150) NULL AFTER last_name');
        }
    }
}

$roleTypeEnroll = db()->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role'")->fetchColumn();
if (is_string($roleTypeEnroll) && !str_contains($roleTypeEnroll, 'registrar')) {
    db()->exec("ALTER TABLE users MODIFY role ENUM('admin','staff','registrar') NOT NULL");
}

$col->execute(['students', 'enrollment_approved_by']);
if (!(int)$col->fetchColumn()) {
    db()->exec('ALTER TABLE students ADD COLUMN enrollment_approved_by BIGINT UNSIGNED NULL AFTER is_active');
    db()->exec('ALTER TABLE students ADD COLUMN enrollment_approved_at DATETIME NULL AFTER enrollment_approved_by');
    $fkCheck = db()->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='students' AND CONSTRAINT_NAME='fk_students_enrollment_approver'");
    $fkCheck->execute();
    if (!(int)$fkCheck->fetchColumn()) {
        db()->exec('ALTER TABLE students ADD CONSTRAINT fk_students_enrollment_approver FOREIGN KEY (enrollment_approved_by) REFERENCES users(id) ON DELETE SET NULL');
    }
}

$enrollTable = db()->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enrollment_applications'")->fetchColumn();
if (!(int)$enrollTable) {
    foreach (explode(';', file_get_contents(__DIR__ . '/migrations/2026_09_06_enrollment.sql')) as $sql) {
        $sql = trim(preg_replace('/^--.*$/m', '', $sql) ?? '');
        if ($sql === '') continue;
        if (preg_match('/^ALTER TABLE users MODIFY role/i', $sql)) continue;
        if (preg_match('/^ALTER TABLE students/i', $sql)) continue;
        db()->exec($sql);
    }
}

$col->execute(['enrollment_applications', 'lrn']);
if ((int)$col->fetchColumn()) {
    db()->exec('ALTER TABLE enrollment_applications DROP COLUMN lrn');
}

$appType = db()->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='enrollment_applications' AND COLUMN_NAME='application_type'")->fetchColumn();
if (is_string($appType) && !str_contains($appType, 'moving_up')) {
    db()->exec("UPDATE enrollment_applications SET application_type='new' WHERE application_type IN ('transferee','returnee')");
    db()->exec("ALTER TABLE enrollment_applications MODIFY application_type ENUM('new','moving_up') NOT NULL");
}

$col->execute(['enrollment_applications', 'student_number']);
if (!(int)$col->fetchColumn()) {
    db()->exec('ALTER TABLE enrollment_applications ADD COLUMN student_number VARCHAR(30) NULL AFTER application_type');
}

$subjectsTable = db()->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subjects'")->fetchColumn();
$col->execute(['block_subject_assignments', 'subject_id']);
$needsSubjects = !(int)$subjectsTable || !(int)$col->fetchColumn();
if ($needsSubjects) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . 'migrate-subjects.php'));
}

foreach (
    [
        'can_enrollments' => 'ALTER TABLE users ADD COLUMN can_enrollments BOOLEAN NOT NULL DEFAULT FALSE AFTER is_active',
        'can_blocking' => 'ALTER TABLE users ADD COLUMN can_blocking BOOLEAN NOT NULL DEFAULT FALSE AFTER can_enrollments',
        'can_assign_teachers' => 'ALTER TABLE users ADD COLUMN can_assign_teachers BOOLEAN NOT NULL DEFAULT FALSE AFTER can_blocking',
        'can_view_students' => 'ALTER TABLE users ADD COLUMN can_view_students BOOLEAN NOT NULL DEFAULT FALSE AFTER can_assign_teachers',
    ] as $permCol => $alterSql
) {
    $col->execute(['users', $permCol]);
    if (!(int)$col->fetchColumn()) {
        db()->exec($alterSql);
        // Existing registrar accounts keep prior open access when a flag is first introduced.
        if ($permCol === 'can_view_students') {
            db()->exec("UPDATE users SET can_view_students=1 WHERE role='registrar'");
        } elseif (in_array($permCol, ['can_enrollments', 'can_blocking', 'can_assign_teachers'], true)) {
            db()->exec("UPDATE users SET `$permCol`=1 WHERE role='registrar'");
        }
    }
}

$col->execute(['blocks', 'year_level']);
if (!(int)$col->fetchColumn()) {
    db()->exec('ALTER TABLE blocks ADD COLUMN year_level TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER name');
    $blockIds = db()->query('SELECT id FROM blocks')->fetchAll(PDO::FETCH_COLUMN);
    $majority = db()->prepare(
        'SELECT s.year_level
         FROM block_students bs
         JOIN students s ON s.id=bs.student_id
         WHERE bs.block_id=?
         GROUP BY s.year_level
         ORDER BY COUNT(*) DESC, s.year_level ASC
         LIMIT 1'
    );
    $setYear = db()->prepare('UPDATE blocks SET year_level=? WHERE id=?');
    foreach ($blockIds as $bid) {
        $majority->execute([(int)$bid]);
        $year = (int)$majority->fetchColumn();
        if ($year >= 1 && $year <= 4) {
            $setYear->execute([$year, (int)$bid]);
        }
    }
    $idxRows = db()->query(
        "SELECT DISTINCT INDEX_NAME, NON_UNIQUE FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='blocks' AND COLUMN_NAME='name'"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($idxRows as $indexRow) {
        $indexName = (string)$indexRow['INDEX_NAME'];
        if ($indexName === 'PRIMARY' || $indexName === 'uq_blocks_name_year') {
            continue;
        }
        if ((int)$indexRow['NON_UNIQUE'] === 0) {
            db()->exec('ALTER TABLE blocks DROP INDEX `' . str_replace('`', '``', $indexName) . '`');
        }
    }
    $uq = db()->query(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='blocks' AND INDEX_NAME='uq_blocks_name_year'"
    )->fetchColumn();
    if (!(int)$uq) {
        db()->exec('ALTER TABLE blocks ADD UNIQUE KEY uq_blocks_name_year (name, year_level)');
    }
}

echo "Migrations applied. Existing records and course names preserved.\n";
