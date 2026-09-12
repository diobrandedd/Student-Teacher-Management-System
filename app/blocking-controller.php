<?php
declare(strict_types=1);
require_registrar_capability('blocking');
require_once __DIR__ . '/blocking.php';
require_once __DIR__ . '/blocks.php';

$blockingFilters = blocking_list_filters_from_request();
$blockingErrors = [];
$selectedId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$selectedStudent = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $selectedId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $blockId = filter_var($_POST['block_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $selectedStudent = $selectedId > 0 ? blocking_fetch_student($selectedId) : null;
    if (!$selectedStudent) {
        $blockingErrors[] = 'That student was not found.';
    } else {
        $check = db()->prepare('SELECT COUNT(*) FROM block_students WHERE student_id=?');
        $check->execute([$selectedId]);
        if ((int)$check->fetchColumn() > 0) {
            flash('success', 'That student is already in a block.');
            redirect('blocking', blocking_list_query_params($blockingFilters, ['id' => null]));
        }
        try {
            assign_block_student(db(), $blockId, $selectedId);
            flash('success', $selectedStudent['last_name'] . ', ' . $selectedStudent['first_name'] . ' assigned to the block.');
            redirect('blocking', blocking_list_query_params($blockingFilters, ['id' => null]));
        } catch (InvalidArgumentException $exception) {
            $blockingErrors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('Blocking assign failed: ' . $exception->getMessage());
            $blockingErrors[] = 'Could not assign this student. Please try again.';
        }
    }
}

$list = blocking_list_page($blockingFilters);
$blockingRows = $list['rows'];
$blockingTotal = $list['total'];
$blockingPage = $list['page'];
$blockingPages = $list['pages'];

if ($selectedId && !$selectedStudent) {
    $selectedStudent = blocking_fetch_student($selectedId);
    if ($selectedStudent) {
        $check = db()->prepare('SELECT COUNT(*) FROM block_students WHERE student_id=?');
        $check->execute([$selectedId]);
        if ((int)$check->fetchColumn() > 0) {
            $selectedStudent = null;
            $selectedId = null;
        }
    } else {
        $selectedId = null;
    }
}

$blockOptions = [];
if ($selectedStudent) {
    $year = (int)($selectedStudent['year_level'] ?? 0);
    $statement = db()->prepare(
        'SELECT id, name, year_level FROM blocks WHERE year_level=? ORDER BY name, id'
    );
    $statement->execute([$year]);
    $blockOptions = $statement->fetchAll();
}
$courseOptions = db()->query('SELECT id, name FROM courses ORDER BY name')->fetchAll();

$filterQs = static function (array $overrides = []) use ($blockingFilters): string {
    return http_build_query(blocking_list_query_params($blockingFilters, $overrides));
};
$hasExtraFilters = $blockingFilters['q'] !== ''
    || $blockingFilters['course_id']
    || $blockingFilters['year_level'];
