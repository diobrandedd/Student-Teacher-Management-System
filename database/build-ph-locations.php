<?php
declare(strict_types=1);
/**
 * Build public/data/ph-locations.json from PSA PSGC dumps (psgc.gitlab.io).
 * Run: php database/build-ph-locations.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$outPath = dirname(__DIR__) . '/public/data/ph-locations.json';
$base = 'https://psgc.gitlab.io/api';
$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ssis-psgc';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0775, true);
}

function http_json_cached(string $url, string $cacheFile): array
{
    if (is_file($cacheFile) && filesize($cacheFile) > 100) {
        $raw = file_get_contents($cacheFile);
    } else {
        echo "Downloading {$url}…\n";
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 300,
                'header' => "Accept: application/json\r\nUser-Agent: SSIS-ph-locations-builder/1.0\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new RuntimeException('Failed to fetch ' . $url);
        }
        file_put_contents($cacheFile, $raw);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid JSON from ' . $url);
    }
    return $data;
}

$provincesRaw = http_json_cached($base . '/provinces.json', $cacheDir . '/provinces.json');
$citiesRaw = http_json_cached($base . '/cities-municipalities.json', $cacheDir . '/cities.json');
$barangaysRaw = http_json_cached($base . '/barangays.json', $cacheDir . '/barangays.json');

echo count($provincesRaw) . " provinces, " . count($citiesRaw) . " cities, " . count($barangaysRaw) . " barangays\n";

$ncrCode = '130000000';
$byProvince = [];
foreach ($provincesRaw as $p) {
    $code = (string)($p['code'] ?? '');
    $name = (string)($p['name'] ?? '');
    if ($code === '' || $name === '') continue;
    $byProvince[$code] = ['code' => $code, 'name' => $name, 'cities' => []];
}
$byProvince[$ncrCode] = ['code' => $ncrCode, 'name' => 'Metro Manila (NCR)', 'cities' => []];

$citiesByCode = [];
$independentCityParents = [
    '099701000' => '150700000', // City of Isabela → Basilan
    '129804000' => '124700000', // City of Cotabato → Cotabato
];
foreach ($citiesRaw as $city) {
    $code = (string)($city['code'] ?? '');
    $name = (string)($city['name'] ?? '');
    $provinceCode = (string)($city['provinceCode'] ?? '');
    $regionCode = (string)($city['regionCode'] ?? '');
    if ($code === '' || $name === '') continue;
    if ($provinceCode === '' && isset($independentCityParents[$code])) {
        $provinceCode = $independentCityParents[$code];
    }

    $parent = $provinceCode !== '' ? $provinceCode : '';
    if ($parent === '' && $regionCode === $ncrCode) {
        $parent = $ncrCode;
    }
    if ($parent === '' || !isset($byProvince[$parent])) {
        fwrite(STDERR, "Skip city without province: {$name} ({$code}) region={$regionCode}\n");
        continue;
    }

    $entry = ['code' => $code, 'name' => $name, 'barangays' => []];
    $byProvince[$parent]['cities'][$code] = $entry;
    $citiesByCode[$code] = &$byProvince[$parent]['cities'][$code];
}

$matched = 0;
$skipped = 0;
foreach ($barangaysRaw as $b) {
    $bc = (string)($b['code'] ?? '');
    $bn = (string)($b['name'] ?? '');
    $cityCode = '';
    foreach (['cityCode', 'municipalityCode', 'subMunicipalityCode'] as $key) {
        $val = $b[$key] ?? null;
        if (is_string($val) && $val !== '') {
            $cityCode = $val;
            break;
        }
    }
    if ($bc === '' || $bn === '' || $cityCode === '' || !isset($citiesByCode[$cityCode])) {
        $skipped++;
        continue;
    }
    $citiesByCode[$cityCode]['barangays'][] = ['code' => $bc, 'name' => $bn];
    $matched++;
}
echo "Barangays matched: {$matched}, skipped: {$skipped}\n";

$provinces = [];
foreach ($byProvince as $prov) {
    $cities = array_values($prov['cities']);
    foreach ($cities as &$city) {
        usort($city['barangays'], static fn($a, $b) => strcasecmp($a['name'], $b['name']));
    }
    unset($city);
    usort($cities, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
    $prov['cities'] = $cities;
    $provinces[] = $prov;
}
usort($provinces, static fn($a, $b) => strcasecmp($a['name'], $b['name']));

$payload = [
    'source' => 'PSA Philippine Standard Geographic Code via psgc.gitlab.io',
    'generated_at' => gmdate('c'),
    'provinces' => $provinces,
];

$json = json_encode($payload, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    throw new RuntimeException('json_encode failed');
}
file_put_contents($outPath, $json);

$cityTotal = 0;
$brgyTotal = 0;
foreach ($provinces as $p) {
    $cityTotal += count($p['cities']);
    foreach ($p['cities'] as $c) {
        $brgyTotal += count($c['barangays']);
    }
}
echo "Wrote {$outPath} (" . number_format(filesize($outPath)) . " bytes)\n";
echo count($provinces) . " provinces (incl. NCR), {$cityTotal} cities/municipalities, {$brgyTotal} barangays\n";
