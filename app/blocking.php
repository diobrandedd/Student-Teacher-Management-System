<?php
declare(strict_types=1);

/** Filters for Blocking queue (students with no block). */
function blocking_list_filters_from_request(?array $get = null): array
{
    $get ??= $_GET;
    $courseId = filter_var($get['course_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $yearLevel = filter_var($get['year_level'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]]);
    $page = max(1, (int)($get['p'] ?? 1));
    return [
        'q' => mb_substr(trim((string)($get['q'] ?? '')), 0, 100),
        'course_id' => $courseId ?: null,
        'year_level' => $yearLevel ?: null,
        'page' => $page,
        'per_page' => 25,
    ];
}

function blocking_list_query_params(array $filters, array $overrides = []): array
{
    $f = array_merge($filters, $overrides);
    $params = ['page' => 'blocking'];
    $q = trim((string)($f['q'] ?? ''));
    if ($q !== '') {
        $params['q'] = $q;
    }
    if (!empty($f['course_id'])) {
        $params['course_id'] = (int)$f['course_id'];
    }
    if (!empty($f['year_level'])) {
        $params['year_level'] = (int)$f['year_level'];
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

function blocking_list_where(array $filters): array
{
    $sql = ' WHERE s.is_active=1 AND s.academic_status=\'Active\'
      AND NOT EXISTS (SELECT 1 FROM block_students bs WHERE bs.student_id=s.id)';
    $params = [];
    if (!empty($filters['course_id'])) {
        $sql .= ' AND s.course_id=?';
        $params[] = (int)$filters['course_id'];
    }
    if (!empty($filters['year_level'])) {
        $sql .= ' AND s.year_level=?';
        $params[] = (int)$filters['year_level'];
    }
    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . $q . '%';
        $digits = preg_replace('/\D+/', '', $q) ?? '';
        $digitLike = $digits !== '' ? '%' . $digits . '%' : $like;
        $sql .= ' AND (
            s.last_name LIKE ? OR s.first_name LIKE ? OR s.email LIKE ?
            OR CONCAT(s.last_name, \', \', s.first_name) LIKE ?
            OR COALESCE(s.student_number, \'\') LIKE ?
            OR REPLACE(COALESCE(s.student_number, \'\'), \'-\', \'\') LIKE ?
            OR COALESCE(s.phone, \'\') LIKE ?
            OR COALESCE(s.username, \'\') LIKE ?
        )';
        array_push($params, $like, $like, $like, $like, $like, $digitLike, $like, $like);
    }
    return [$sql, $params];
}

function blocking_list_page(array $filters): array
{
    $perPage = max(1, min(100, (int)($filters['per_page'] ?? 25)));
    [$where, $params] = blocking_list_where($filters);

    $countStmt = db()->prepare('SELECT COUNT(*) FROM students s' . $where);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($pages, (int)($filters['page'] ?? 1)));
    $offset = ($page - 1) * $perPage;

    $sql = 'SELECT s.*, c.name AS course_name
            FROM students s
            LEFT JOIN courses c ON c.id=s.course_id'
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
        'per_page' => $perPage,
    ];
}

function blocking_fetch_student(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT s.*, c.name AS course_name, u.full_name AS approver_name
         FROM students s
         LEFT JOIN courses c ON c.id=s.course_id
         LEFT JOIN users u ON u.id=s.enrollment_approved_by
         WHERE s.id=? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}
