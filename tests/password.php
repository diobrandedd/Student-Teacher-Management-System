<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__) . '/app/bootstrap.php';

$failures = 0;
function expect(bool $ok, string $label): void
{
    global $failures;
    if ($ok) {
        echo "OK  $label\n";
        return;
    }
    $failures++;
    echo "FAIL $label\n";
}

expect(default_temp_password() === '123', 'default temp password is 123');
expect(password_verify('123', hash_temp_password()), 'hash_temp_password verifies as 123');
expect(!password_is_strong('123'), 'temp password is not strong');
expect(!password_is_strong('short'), 'short password is not strong');
expect(!password_is_strong('alllowercase1'), 'missing uppercase is not strong');
expect(password_is_strong('ValidPassw0rd!'), '12+ mixed password is strong');
expect(login_username_base('Fin', 'Noli') === 'Fin_N', 'Fin, Noli → Fin_N');
expect(login_username_base('Delos Santos', 'Brent') === 'Delossantos_B', 'Delos Santos, Brent → Delossantos_B');

if ($failures) {
    fwrite(STDERR, "$failures password helper check(s) failed.\n");
    exit(1);
}
echo "Password helper checks passed.\n";
