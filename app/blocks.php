<?php
declare(strict_types=1);

/** Display label: "Block 1 · 1st year". */
function block_label(array|string $blockOrName, int|string|null $yearLevel = null): string
{
    if (is_array($blockOrName)) {
        $name = trim((string)($blockOrName['name'] ?? ''));
        $yearLevel = $blockOrName['year_level'] ?? $yearLevel;
    } else {
        $name = trim($blockOrName);
    }
    if ($name === '') {
        return '—';
    }
    $year = college_year_label($yearLevel);
    return $year !== '—' ? $name . ' · ' . $year : $name;
}

function save_block(PDO $pdo, array $input, ?int $id = null): int
{
    $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
    if ($name === '' || mb_strlen($name) > 100) {
        throw new InvalidArgumentException('Enter a block name of 1–100 characters.');
    }
    $yearLevel = filter_var($input['year_level'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]]);
    if (!$yearLevel) {
        throw new InvalidArgumentException('Select a college year (1st–4th year) for this block.');
    }

    $hasRoster = array_key_exists('student_ids', $input);
    $students = [];
    if ($hasRoster) {
        $ids = $input['student_ids'];
        if (!is_array($ids)) {
            throw new InvalidArgumentException('Select valid students.');
        }
        foreach ($ids as $value) {
            $student = filter_var($value, FILTER_VALIDATE_INT);
            if (!$student || $student < 1) {
                throw new InvalidArgumentException('Select valid students.');
            }
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
            if (!$statement->fetchColumn()) {
                throw new InvalidArgumentException('This block no longer exists. Return to Blocks and try again.');
            }
            $pdo->prepare('UPDATE blocks SET name=?, year_level=? WHERE id=?')->execute([$name, $yearLevel, $id]);
        } else {
            $pdo->prepare('INSERT INTO blocks (name, year_level) VALUES (?,?)')->execute([$name, $yearLevel]);
            $id = (int)$pdo->lastInsertId();
        }

        if ($hasRoster) {
            if ($students) {
                $placeholders = implode(',', array_fill(0, count($students), '?'));
                $statement = $pdo->prepare(
                    "SELECT id FROM students
                     WHERE id IN ($placeholders)
                       AND year_level=?
                       AND (is_active=1 OR id IN (SELECT student_id FROM block_students WHERE block_id=?))
                     ORDER BY id FOR UPDATE"
                );
                $statement->execute([...$students, $yearLevel, $id]);
                if (count($statement->fetchAll()) !== count($students)) {
                    throw new InvalidArgumentException('Every student in this block must match the block’s college year, and must be active (or already in the block).');
                }
            }
            $pdo->prepare('DELETE FROM block_students WHERE block_id=?')->execute([$id]);
            $statement = $pdo->prepare('INSERT INTO block_students (block_id, student_id) VALUES (?, ?)');
            foreach ($students as $student) {
                $statement->execute([$id, $student]);
            }
            sync_block_enrollments($pdo, $id);
            audit('SAVE', 'block', $id, 'Saved block name, year, and students');
        } else {
            audit('SAVE', 'block', $id, 'Saved block name and year');
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) === 1062) {
            throw new InvalidArgumentException('That block name is already used for this college year. Choose a different name or year.');
        }
        throw $exception;
    }
}

function assign_block_student(PDO $pdo, int $blockId, int $studentId): void
{
    if ($studentId < 1) {
        throw new InvalidArgumentException('Select a student to assign.');
    }
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT id, year_level FROM blocks WHERE id=? FOR UPDATE');
        $statement->execute([$blockId]);
        $block = $statement->fetch();
        if (!$block) {
            throw new InvalidArgumentException('This block no longer exists.');
        }

        $statement = $pdo->prepare('SELECT id, is_active, year_level FROM students WHERE id=? FOR UPDATE');
        $statement->execute([$studentId]);
        $student = $statement->fetch();
        if (!$student) {
            throw new InvalidArgumentException('That student record was not found.');
        }
        if (!(int)$student['is_active']) {
            throw new InvalidArgumentException('That student is inactive and cannot be assigned.');
        }
        if ((int)$student['year_level'] !== (int)$block['year_level']) {
            throw new InvalidArgumentException(
                'This student is ' . college_year_label($student['year_level'])
                . '; choose a ' . college_year_label($block['year_level']) . ' block.'
            );
        }

        $exists = $pdo->prepare('SELECT COUNT(*) FROM block_students WHERE block_id=? AND student_id=?');
        $exists->execute([$blockId, $studentId]);
        if ((int)$exists->fetchColumn() > 0) {
            throw new InvalidArgumentException('That student is already in this block.');
        }

        $pdo->prepare('INSERT INTO block_students (block_id, student_id) VALUES (?, ?)')->execute([$blockId, $studentId]);
        sync_block_enrollments($pdo, $blockId);
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
        sync_block_enrollments($pdo, $blockId);
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

/** Rebuild enrollments for every teacher–subject assignment on a block from current members. */
function sync_block_enrollments(PDO $pdo, int $blockId): int
{
    $statement = $pdo->prepare('SELECT id FROM block_subject_assignments WHERE block_id=? ORDER BY id');
    $statement->execute([$blockId]);
    $total = 0;
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $assignmentId) {
        $total += sync_assignment_enrollments($pdo, (int)$assignmentId);
    }
    return $total;
}

function save_block_assignment(PDO $pdo, int $blockId, array $input, ?int $assignmentId = null): int
{
    $teacher = filter_var($input['teacher_id'] ?? null, FILTER_VALIDATE_INT);
    $subjectId = filter_var($input['subject_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$teacher || $teacher < 1) {
        throw new InvalidArgumentException('Select an active teacher for this subject.');
    }
    if (!$subjectId || $subjectId < 1) {
        throw new InvalidArgumentException('Select a subject from the catalog.');
    }

    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT id FROM blocks WHERE id=? FOR UPDATE');
        $statement->execute([$blockId]);
        if (!$statement->fetchColumn()) {
            throw new InvalidArgumentException('This block no longer exists.');
        }

        $statement = $pdo->prepare("SELECT t.id FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.id=? AND u.role='staff' AND u.is_active=1 FOR UPDATE");
        $statement->execute([$teacher]);
        if (!$statement->fetchColumn()) {
            throw new InvalidArgumentException('Select an active teacher for this subject.');
        }

        $statement = $pdo->prepare('SELECT id, code, title FROM subjects WHERE id=? FOR UPDATE');
        $statement->execute([$subjectId]);
        $subject = $statement->fetch();
        if (!$subject) {
            throw new InvalidArgumentException('Select a subject from the catalog.');
        }
        $code = (string)$subject['code'];
        $name = (string)$subject['title'];

        if ($assignmentId) {
            $statement = $pdo->prepare('SELECT id FROM block_subject_assignments WHERE id=? AND block_id=? FOR UPDATE');
            $statement->execute([$assignmentId, $blockId]);
            if (!$statement->fetchColumn()) {
                throw new InvalidArgumentException('That subject assignment no longer exists.');
            }
            $pdo->prepare('UPDATE block_subject_assignments SET teacher_id=?, subject_id=?, subject_code=?, subject_name=? WHERE id=? AND block_id=?')
                ->execute([$teacher, $subjectId, $code, $name, $assignmentId, $blockId]);
        } else {
            $pdo->prepare('INSERT INTO block_subject_assignments (block_id, teacher_id, subject_id, subject_code, subject_name) VALUES (?,?,?,?,?)')
                ->execute([$blockId, $teacher, $subjectId, $code, $name]);
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
