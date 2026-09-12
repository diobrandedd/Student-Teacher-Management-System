<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/blocking.php';
require dirname(__DIR__) . '/app/students-list.php';

function ok(bool $c, string $m): void
{
    if (!$c) {
        throw new RuntimeException($m);
    }
    echo "ok: $m\n";
}

$f = blocking_list_filters_from_request(['q' => 'test', 'year_level' => '2', 'p' => '2']);
ok($f['q'] === 'test' && $f['year_level'] === 2 && $f['page'] === 2, 'blocking filters parse');
$params = blocking_list_query_params($f, ['id' => 9, 'page' => 1]);
ok(($params['page'] ?? '') === 'blocking' && ($params['id'] ?? null) === 9 && !isset($params['p']), 'blocking query omits page 1');

$sf = students_list_filters_from_request(['block' => 'out', 'active' => '1', 'academic_status' => 'Active']);
ok($sf['block'] === 'out' && $sf['active'] === '1' && $sf['academic_status'] === 'Active', 'students filters parse');
[$where] = students_list_where($sf);
ok(str_contains($where, 'NOT EXISTS') && str_contains($where, 'is_active=1'), 'students where for unblocked active');

echo "blocking/students list helpers passed\n";
