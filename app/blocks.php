<?php
declare(strict_types=1);

function save_block(PDO $pdo, array $input, ?int $id = null): int
{
    $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
    if ($name === '' || mb_strlen($name) > 100) throw new InvalidArgumentException('Enter a block name of 1–100 characters.');

    $hasRoster = array_key_exists('student_ids', $input);
    $students = [];
    if ($hasRoster) {
        $ids = $input['student_ids'];
        if (!is_array($ids)) throw new InvalidArgumentException('Select valid students.');
        foreach ($ids as $value) {
            $student = filter_var($value, FILTER_VALIDATE_INT);
            if (!$student || $student < 1) throw new InvalidArgumentException('Select valid students.');
            $students[] = $student;
        }
        $students = array_values(array_unique($students));
        sort($students);
    }

    $pdo->beginTransaction();
    try {
        if ($id) {
            $statement = $pdo->prepare('SELECT id FROM blocks WHERE id = ? FOR UPDATE');
            $statement->execute([$id]);
            if (!$statement->fetchColumn()) throw new InvalidArgumentException('This block no longer exists. Return to Blocks and try again.');
            $pdo->prepare('UPDATE blocks SET name=? WHERE id=?')->execute([$name, $id]);
        } else {
            $pdo->prepare('INSERT INTO blocks (name) VALUES (?)')->execute([$name]);
            $id = (int)$pdo->lastInsertId();
        }

        if ($hasRoster) {
            if ($students) {
                $placeholders = implode(',', array_fill(0, count($students), '?'));
                $statement = $pdo->prepare("SELECT id FROM students WHERE id IN ($placeholders) AND (is_active=1 OR id IN (SELECT student_id FROM block_students WHERE block_id=?)) ORDER BY id FOR UPDATE");
                $statement->execute([...$students, $id]);
                if (count($statement->fetchAll()) !== count($students)) throw new InvalidArgumentException('A selected student is inactive or no longer available. Review the selection.');
            }
            $pdo->prepare('DELETE FROM block_students WHERE block_id=?')->execute([$id]);
            $statement = $pdo->prepare('INSERT INTO block_students (block_id, student_id) VALUES (?, ?)');
            foreach ($students as $student) $statement->execute([$id, $student]);
            audit('SAVE', 'block', $id, 'Saved block name and students');
        } else {
            audit('SAVE', 'block', $id, 'Saved block name');
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) === 1062) {
            throw new InvalidArgumentException('That block name is already in use. Choose a different name.');
        }
        throw $exception;
    }
}

function assign_block_student(PDO $pdo, int $blockId, int $studentId): void
{
    if ($studentId < 1) throw new InvalidArgumentException('Select a student to assign.');
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT id FROM blocks WHERE id=? FOR UPDATE');
        $statement->execute([$blockId]);
        if (!$statement->fetchColumn()) throw new InvalidArgumentException('This block no longer exists.');

        $statement = $pdo->prepare('SELECT id, is_active FROM students WHERE id=? FOR UPDATE');
        $statement->execute([$studentId]);
        $student = $statement->fetch();
        if (!$student) throw new InvalidArgumentException('That student record was not found.');
        if (!(int)$student['is_active']) throw new InvalidArgumentException('That student is inactive and cannot be assigned.');

        $exists = $pdo->prepare('SELECT COUNT(*) FROM block_students WHERE block_id=? AND student_id=?');
        $exists->execute([$blockId, $studentId]);
        if ((int)$exists->fetchColumn() > 0) throw new InvalidArgumentException('That student is already in this block.');

        $pdo->prepare('INSERT INTO block_students (block_id, student_id) VALUES (?, ?)')->execute([$blockId, $studentId]);
        audit('ASSIGN', 'block_student', $blockId, "Assigned student #$studentId");
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function remove_block_student(PDO $pdo, int $blockId, int $studentId): void
{
    if ($studentId < 1) throw new InvalidArgumentException('Select a student to remove.');
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT id FROM blocks WHERE id=? FOR UPDATE');
        $statement->execute([$blockId]);
        if (!$statement->fetchColumn()) throw new InvalidArgumentException('This block no longer exists.');

        $statement = $pdo->prepare('DELETE FROM block_students WHERE block_id=? AND student_id=?');
        $statement->execute([$blockId, $studentId]);
        if ($statement->rowCount() < 1) throw new InvalidArgumentException('That student is not in this block.');
        audit('REMOVE', 'block_student', $blockId, "Removed student #$studentId");
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function sync_assignment_enrollments(PDO $pdo, int $assignmentId): int
{
    $statement = $pdo->prepare('SELECT id, block_id, teacher_id, subject_code, subject_name FROM block_subject_assignments WHERE id=? FOR UPDATE');
    $statement->execute([$assignmentId]);
    $assignment = $statement->fetch();
    if (!$assignment) throw new InvalidArgumentException('That subject assignment no longer exists.');
    $pdo->prepare('DELETE FROM block_subject_enrollments WHERE assignment_id=?')->execute([$assignmentId]);
    $members = $pdo->prepare('SELECT student_id FROM block_students WHERE block_id=? ORDER BY student_id');
    $members->execute([(int)$assignment['block_id']]);
    $insert = $pdo->prepare('INSERT INTO block_subject_enrollments (assignment_id, student_id, block_id, teacher_id, subject_code, subject_name, synced_at) VALUES (?,?,?,?,?,?,NOW())');
    $count = 0;
    foreach ($members as $row) {
        $insert->execute([
            $assignmentId,
            (int)$row['student_id'],
            (int)$assignment['block_id'],
            (int)$assignment['teacher_id'],
            $assignment['subject_code'],
            $assignment['subject_name'],
        ]);
        $count++;
    }
    return $count;
}

function save_block_assignment(PDO $pdo, int $blockId, array $input, ?int $assignmentId = null): int
{
    $teacher = filter_var($input['teacher_id'] ?? null, FILTER_VALIDATE_INT);
    $code = is_string($input['subject_code'] ?? null) ? trim($input['subject_code']) : '';
    $name = is_string($input['subject_name'] ?? null) ? trim($input['subject_name']) : '';
    if (!$teacher || $teacher < 1) throw new InvalidArgumentException('Select an active teacher for this subject.');
    if ($code === '' || mb_strlen($code) > 30) throw new InvalidArgumentException('Enter a subject code of 1–30 characters.');
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,29}$/', $code)) {
        throw new InvalidArgumentException('Subject code may use letters, numbers, dots, underscores, or hyphens.');
    }
    if ($name === '' || mb_strlen($name) > 150) throw new InvalidArgumentException('Enter a subject name of 1–150 characters.');

    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT id FROM blocks WHERE id=? FOR UPDATE');
        $statement->execute([$blockId]);
        if (!$statement->fetchColumn()) throw new InvalidArgumentException('This block no longer exists.');

        $statement = $pdo->prepare("SELECT t.id FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.id=? AND u.role='staff' AND u.is_active=1 FOR UPDATE");
        $statement->execute([$teacher]);
        if (!$statement->fetchColumn()) throw new InvalidArgumentException('Select an active teacher for this subject.');

        if ($assignmentId) {
            $statement = $pdo->prepare('SELECT id FROM block_subject_assignments WHERE id=? AND block_id=? FOR UPDATE');
            $statement->execute([$assignmentId, $blockId]);
            if (!$statement->fetchColumn()) throw new InvalidArgumentException('That subject assignment no longer exists.');
            $pdo->prepare('UPDATE block_subject_assignments SET teacher_id=?, subject_code=?, subject_name=? WHERE id=? AND block_id=?')
                ->execute([$teacher, $code, $name, $assignmentId, $blockId]);
        } else {
            $pdo->prepare('INSERT INTO block_subject_assignments (block_id, teacher_id, subject_code, subject_name) VALUES (?,?,?,?)')
                ->execute([$blockId, $teacher, $code, $name]);
            $assignmentId = (int)$pdo->lastInsertId();
        }
        $enrolled = sync_assignment_enrollments($pdo, $assignmentId);
        audit('SAVE', 'block_subject', $assignmentId, "Saved subject $code for block #$blockId; synced $enrolled students");
        $pdo->commit();
        return $assignmentId;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) === 1062) {
            throw new InvalidArgumentException('That subject code is already assigned in this block.');
        }
        throw $exception;
    }
}

function resync_block_assignment(PDO $pdo, int $blockId, int $assignmentId): int
{
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT id FROM block_subject_assignments WHERE id=? AND block_id=? FOR UPDATE');
        $statement->execute([$assignmentId, $blockId]);
        if (!$statement->fetchColumn()) throw new InvalidArgumentException('That subject assignment no longer exists.');
        $enrolled = sync_assignment_enrollments($pdo, $assignmentId);
        audit('RESYNC', 'block_subject', $assignmentId, "Re-synced $enrolled students for block #$blockId");
        $pdo->commit();
        return $enrolled;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function delete_block_assignment(PDO $pdo, int $blockId, int $assignmentId): void
{
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT id, subject_code FROM block_subject_assignments WHERE id=? AND block_id=? FOR UPDATE');
        $statement->execute([$assignmentId, $blockId]);
        $row = $statement->fetch();
        if (!$row) throw new InvalidArgumentException('That subject assignment no longer exists.');
        $pdo->prepare('DELETE FROM block_subject_enrollments WHERE assignment_id=?')->execute([$assignmentId]);
        $pdo->prepare('DELETE FROM block_subject_assignments WHERE id=? AND block_id=?')->execute([$assignmentId, $blockId]);
        audit('DELETE', 'block_subject', $assignmentId, 'Removed subject ' . $row['subject_code'] . " from block #$blockId");
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}
