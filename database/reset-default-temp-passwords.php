<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__) . '/app/bootstrap.php';

$plain = default_temp_password();
$hash = password_hash($plain, PASSWORD_DEFAULT);

$users = db()->prepare(
    "UPDATE users
     SET password_hash = ?, must_change_password = 1, failed_attempts = 0, locked_until = NULL
     WHERE role IN ('staff', 'registrar')"
);
$users->execute([$hash]);
$userCount = $users->rowCount();

$students = db()->prepare(
    'UPDATE students
     SET password_hash = ?, must_change_password = 1, failed_attempts = 0, locked_until = NULL
     WHERE username IS NOT NULL AND username <> \'\''
);
$students->execute([$hash]);
$studentCount = $students->rowCount();

$byRole = db()->query(
    "SELECT role, COUNT(*) AS c FROM users WHERE role IN ('staff', 'registrar') GROUP BY role"
)->fetchAll(PDO::FETCH_KEY_PAIR);

echo "DEFAULT_TEMP_PASSWORD={$plain}\n";
echo "Users updated (staff+registrar): {$userCount}\n";
echo "  staff: " . (int)($byRole['staff'] ?? 0) . "\n";
echo "  registrar: " . (int)($byRole['registrar'] ?? 0) . "\n";
echo "Students updated: {$studentCount}\n";
echo "Admins left unchanged.\n";
