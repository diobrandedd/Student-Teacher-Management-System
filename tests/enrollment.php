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
assert_true(enrollment_format_mobile('9631014585') === '63+9631014585', 'formats local mobile');
assert_true(enrollment_valid_mobile('63+9631014585'), 'valid mobile accepts 63+10 digits');
assert_true(enrollment_valid_mobile('9631014585'), '10 digits are accepted and normalized');
assert_true(!enrollment_valid_mobile('63+96310145ab'), 'mobile rejects letters');
assert_true(!enrollment_valid_mobile('63+96310'), 'mobile rejects short numbers');
assert_true(enrollment_mobile_digits('63+9631014585') === '9631014585', 'extracts local digits');
assert_true(enrollment_normalize_name_part('  Maria   Clara  ') === 'maria clara', 'normalizes name spacing and case');
assert_true(enrollment_names_match(
    ['first_name' => 'Juan', 'middle_name' => '', 'last_name' => 'Dela Cruz'],
    ['first_name' => ' juan ', 'middle_name' => null, 'last_name' => 'dela cruz']
), 'compares legal student names consistently');

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

$filters = enrollment_list_filters_from_request([
    'status' => 'pending',
    'q' => 'Reyes',
    'course_id' => '3',
    'year_level' => '2',
    'application_type' => 'new',
    'academic_year' => '2026-2027',
    'semester' => '1',
    'p' => '2',
]);
assert_true($filters['status'] === 'pending' && $filters['course_id'] === 3 && $filters['year_level'] === 2, 'parses queue filters');
assert_true($filters['application_type'] === 'new' && $filters['academic_year'] === '2026-2027' && $filters['semester'] === '1', 'parses term and type filters');
assert_true($filters['page'] === 2 && $filters['per_page'] === 25, 'paginates at 25 for registrar scale');
$qs = enrollment_list_query_params($filters);
assert_true(($qs['page'] ?? '') === 'enrollments' && ($qs['p'] ?? null) === 2 && ($qs['course_id'] ?? null) === 3, 'builds shareable filter query');
assert_true(!isset(enrollment_list_query_params($filters, ['page' => 1])['p']), 'omits page=1 from query string');

$newYear = enrollment_empty_input();
$newYear['application_type'] = 'new';
$newYear['year_level'] = '3';
$newYear['last_name'] = 'Reyes';
$newYear['first_name'] = 'Ana';
$newYear['no_middle_name'] = true;
$newYear['sex'] = 'female';
$newYear['civil_status'] = 'single';
$newYear['citizenship'] = 'Filipino';
$newYear['religion'] = 'Catholic';
$newYear['date_of_birth'] = '2005-01-15';
$newYear['place_of_birth'] = 'Manila';
$newYear['email'] = 'ana.reyes@example.com';
$newYear['mobile'] = '63+9631014585';
$newYear['province_code'] = '130000000';
$newYear['province_name'] = 'Metro Manila';
$newYear['city_code'] = '137404000';
$newYear['city_name'] = 'Quezon City';
$newYear['barangay_code'] = '137404001';
$newYear['barangay_name'] = 'Diliman';
$newYear['address_line1'] = '12 Rizal St';
$newYear['mother_maiden_name'] = 'Maria Santos';
$newYear['father_name'] = 'Juan Reyes';
$newYear['guardian_name'] = 'Maria Santos';
$newYear['guardian_relationship'] = 'mother';
$newYear['guardian_number'] = '63+9123456789';
$newYear['emergency_name'] = 'Maria Santos';
$newYear['emergency_relationship'] = 'Mother';
$newYear['emergency_number'] = '63+9123456789';
$newYear['last_school'] = 'Sample SHS';
$newYear['year_graduated'] = '2025';
$newYear['course_id'] = '1';
$newYear['academic_year'] = '2026-2027';
$newYear['semester'] = '1';
$newYear['privacy_consent'] = true;
$yearGate = enrollment_validate($newYear, false);
assert_true(
    in_array('New students enroll as 1st year. Use Moving up if you already study here and are advancing.', $yearGate['messages'], true)
    || isset($yearGate['fields']['year_level']),
    'new students cannot apply as 2nd–4th year'
);
$newYear['year_level'] = '1';
$yearOk = enrollment_validate($newYear, false);
assert_true(!isset($yearOk['fields']['year_level']) || !str_contains((string)$yearOk['fields']['year_level'], '1st year'), '1st year is allowed for new students');

assert_true(enrollment_move_year_label(2, 3) === '2nd year → 3rd year', 'move label 2→3');
assert_true(enrollment_move_year_label(3, 4) === '3rd year → 4th year', 'move label 3→4');
assert_true(str_contains(enrollment_move_year_label(null, 3), '3rd year'), 'move label without from-year');

echo "enrollment tests passed\n";
