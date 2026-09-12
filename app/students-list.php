<?php
declare(strict_types=1);

/** Shared Students directory filters (admin edit + registrar view). */
function students_list_filters_from_request(?array $get = null): array
{
    $get ??= $_GET;
    $courseId = filter_var($get['course_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $yearLevel = filter_var($get['year_level'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]]);
    $status = trim((string)($get['academic_status'] ?? ''));
    if (!in_array($status, academic_statuses(), true)) {
        $status = '';
    }
    $block = trim((string)($get['block'] ?? 'all'));
    if (!in_array($block, ['all', 'in', 'out'], true)) {
        $block = 'all';
    }
    $active = trim((string)($get['active'] ?? 'all'));
    if (!in_array($active, ['all', '1', '0'], true)) {
        $active = 'all';
    }
    return [
        'q' => mb_substr(trim((string)($get['q'] ?? '')), 0, 100),
        'course_id' => $courseId ?: null,
        'year_level' => $yearLevel ?: null,
        'academic_status' => $status !== '' ? $status : null,
        'block' => $block,
        'active' => $active,
        'page' => max(1, (int)($get['p'] ?? 1)),
        'per_page' => 25,
    ];
}

function students_list_query_params(array $filters, array $overrides = []): array
{
    $f = array_merge($filters, $overrides);
    $params = ['page' => 'students'];
    foreach (['q' => 'q', 'course_id' => 'course_id', 'year_level' => 'year_level', 'academic_status' => 'academic_status'] as $key => $param) {
        $val = $f[$key] ?? null;
        if ($val !== null && $val !== '') {
            $params[$param] = $val;
        }
    }
    if (($f['block'] ?? 'all') !== 'all') {
        $params['block'] = $f['block'];
    }
    if (($f['active'] ?? 'all') !== 'all') {
        $params['active'] = $f['active'];
    }
    $page = max(1, (int)($f['page'] ?? 1));
    if ($page > 1) {
        $params['p'] = $page;
    }
    if (!empty($f['id'])) {
        $params['id'] = (int)$f['id'];
    }
    return $params;
}

function students_list_where(array $filters): array
{
    $sql = ' WHERE 1=1';
    $params = [];
    if (!empty($filters['course_id'])) {
        $sql .= ' AND s.course_id=?';
        $params[] = (int)$filters['course_id'];
    }
    if (!empty($filters['year_level'])) {
        $sql .= ' AND s.year_level=?';
        $params[] = (int)$filters['year_level'];
    }
    if (!empty($filters['academic_status'])) {
        $sql .= ' AND s.academic_status=?';
        $params[] = (string)$filters['academic_status'];
    }
    $active = $filters['active'] ?? 'all';
    if ($active === '1') {
        $sql .= ' AND s.is_active=1';
    } elseif ($active === '0') {
        $sql .= ' AND s.is_active=0';
    }
    $block = $filters['block'] ?? 'all';
    if ($block === 'in') {
        $sql .= ' AND EXISTS (SELECT 1 FROM block_students bs WHERE bs.student_id=s.id)';
    } elseif ($block === 'out') {
        $sql .= ' AND NOT EXISTS (SELECT 1 FROM block_students bs WHERE bs.student_id=s.id)';
    }
    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . $q . '%';
        $sql .= ' AND (
            s.student_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?
            OR s.course LIKE ? OR COALESCE(s.username,\'\') LIKE ? OR s.email LIKE ?
            OR CONCAT(s.last_name, \', \', s.first_name) LIKE ?
            OR COALESCE(s.phone,\'\') LIKE ?
        )';
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
    }
    return [$sql, $params];
}

function students_list_page(array $filters): array
{
    $perPage = max(1, min(100, (int)($filters['per_page'] ?? 25)));
    [$where, $params] = students_list_where($filters);
    $countStmt = db()->prepare('SELECT COUNT(*) FROM students s' . $where);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($pages, (int)($filters['page'] ?? 1)));
    $offset = ($page - 1) * $perPage;
    $sql = "SELECT s.*,
        (SELECT GROUP_CONCAT(b.name ORDER BY b.name SEPARATOR ', ')
           FROM block_students bs JOIN blocks b ON b.id=bs.block_id WHERE bs.student_id=s.id) AS block_names
     FROM students s"
        . $where
        . ' ORDER BY s.last_name, s.first_name, s.id'
        . " LIMIT $perPage OFFSET $offset";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return [
        'rows' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
    ];
}
