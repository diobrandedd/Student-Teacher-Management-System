<?php
declare(strict_types=1);
/**
 * Registrar duty permission helpers (no DB required for unit checks).
 */
require dirname(__DIR__) . '/app/bootstrap.php';

function expect(bool $ok, string $label): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: $label\n");
        exit(1);
    }
    echo "OK: $label\n";
}

$fromPost = registrar_permissions_from_post([
    'can_enrollments' => '1',
    'can_assign_teachers' => '1',
]);
expect(
    $fromPost === [
        'can_enrollments' => 1,
        'can_blocking' => 0,
        'can_assign_teachers' => 1,
        'can_view_students' => 0,
    ],
    'POST checkboxes map to flags'
);

$fromAccount = registrar_permissions_from_account([
    'can_enrollments' => '0',
    'can_blocking' => '1',
    'can_assign_teachers' => '0',
    'can_view_students' => '1',
]);
expect(
    $fromAccount === [
        'can_enrollments' => 0,
        'can_blocking' => 1,
        'can_assign_teachers' => 0,
        'can_view_students' => 1,
    ],
    'Account row maps to flags'
);

$session = session_user_from_staff([
    'id' => 9,
    'full_name' => 'Rosa Garcia',
    'username' => 'rgarcia',
    'email' => 'rosa@example.invalid',
    'role' => 'registrar',
    'can_enrollments' => 0,
    'can_blocking' => 1,
    'can_assign_teachers' => 0,
    'can_view_students' => 0,
]);
expect(($session['can_blocking'] ?? null) === 1, 'Session includes blocking duty');
expect(($session['can_enrollments'] ?? null) === 0, 'Session excludes enrollments duty');
expect(home_page_for_user($session) === 'blocking', 'Home follows first granted duty');

$enrollOnly = array_merge($session, [
    'can_enrollments' => 1,
    'can_blocking' => 0,
    'can_assign_teachers' => 0,
    'can_view_students' => 0,
]);
expect(home_page_for_user($enrollOnly) === 'enrollments', 'Enrollments preferred when granted');

$studentsOnly = array_merge($session, [
    'can_enrollments' => 0,
    'can_blocking' => 0,
    'can_assign_teachers' => 0,
    'can_view_students' => 1,
]);
expect(home_page_for_user($studentsOnly) === 'students', 'Students home when only view students');

$none = array_merge($session, [
    'can_enrollments' => 0,
    'can_blocking' => 0,
    'can_assign_teachers' => 0,
    'can_view_students' => 0,
]);
expect(home_page_for_user($none) === 'registrar_home', 'Idle home when no duties');
expect(!registrar_has_any_capability($none), 'No duties detected');

$defaults = registrar_permissions_from_post([]);
expect(
    $defaults === [
        'can_enrollments' => 0,
        'can_blocking' => 0,
        'can_assign_teachers' => 0,
        'can_view_students' => 0,
    ],
    'Empty POST defaults to no permissions'
);

$adminSession = session_user_from_staff([
    'id' => 1,
    'full_name' => 'Admin',
    'username' => 'admin',
    'email' => 'admin@example.invalid',
    'role' => 'admin',
]);
expect(!isset($adminSession['can_enrollments']), 'Admin session omits registrar duty keys');

echo "All registrar permission checks passed.\n";
