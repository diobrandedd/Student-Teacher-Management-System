<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/enrollment.php';

function assert_true(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "ok: $msg\n";
}

assert_true(enrollment_age_from_dob('2005-01-15') !== null && enrollment_age_from_dob('2005-01-15') >= 10, 'age from dob');
assert_true(enrollment_validate_upload('psa_birth', null) !== null, 'missing psa fails');
assert_true(enrollment_validate_upload('id_photo', ['error' => UPLOAD_ERR_NO_FILE]) !== null, 'missing id photo fails');

$fake = ['error' => UPLOAD_ERR_OK, 'size' => 100, 'tmp_name' => '', 'name' => 'x.pdf'];
assert_true(enrollment_validate_upload('psa_birth', $fake) !== null, 'invalid tmp fails');

$addr = enrollment_compose_address([
    'address_line1' => '12 Rizal St',
    'address_line2' => '',
    'barangay_name' => 'Diliman',
    'city_name' => 'Quezon City',
    'province_name' => 'Metro Manila (NCR)',
]);
assert_true(str_contains($addr, 'Diliman') && str_contains($addr, 'Quezon City'), 'compose address');

echo "enrollment tests passed\n";
