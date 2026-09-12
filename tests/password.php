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

$issued = issue_temporary_password();
expect(password_is_strong($issued['plain']), 'generated temp password meets strength rules');
expect(password_verify($issued['plain'], $issued['hash']), 'issued temp password verifies against hash');
expect(strlen($issued['plain']) >= 12, 'generated temp password length >= 12');
expect(default_temp_password() === 'DemoTemp1234' || default_temp_password() === trim((string)getenv('DEFAULT_TEMP_PASSWORD')), 'default temp password comes from env or fallback');
expect(password_verify(default_temp_password(), issue_default_temporary_password()['hash']), 'default temp password verifies against issued hash');
expect(demo_seed_password() === default_temp_password(), 'demo_seed_password aliases default_temp_password');
expect(password_verify(demo_seed_password(), hash_temp_password(demo_seed_password())), 'hash_temp_password verifies demo seed password');
expect(!password_is_strong('123'), 'short numeric password is not strong');
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
