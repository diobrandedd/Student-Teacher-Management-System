<?php
declare(strict_types=1);
/**
 * Copy sample PSA/ID files into storage for enrollment apps from sample-data.sql.
 * Run after importing database/sample-data.sql:
 *   php database/link-sample-enrollment-files.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$assetDir = __DIR__ . '/sample-assets/enrollment';
$psaSrc = $assetDir . '/sample-psa.pdf';
$idSrc = $assetDir . '/sample-id.png';
if (!is_file($psaSrc) || !is_file($idSrc)) {
    fwrite(STDERR, "Missing assets. Run: php database/write-sample-enrollment-assets.php\n");
    exit(1);
}

$apps = db()->query(
    "SELECT id FROM enrollment_applications WHERE email LIKE '%@enroll.sample.edu' ORDER BY id"
)->fetchAll(PDO::FETCH_COLUMN);

if (!$apps) {
    fwrite(STDERR, "No sample enrollment applications found. Import database/sample-data.sql first.\n");
    exit(1);
}

$root = dirname(__DIR__) . '/storage/enrollment';
if (!is_dir($root)) {
    mkdir($root, 0775, true);
}

$copied = 0;
foreach ($apps as $appId) {
    $dir = $root . '/' . (int)$appId;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    copy($psaSrc, $dir . '/psa_birth_sample.pdf');
    copy($idSrc, $dir . '/id_photo_sample.png');
    $copied++;
}

echo "Linked sample enrollment files for {$copied} application(s).\n";
echo "Registrar login: rgarcia / DemoTemp1234\n";
