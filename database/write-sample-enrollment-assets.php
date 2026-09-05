<?php
declare(strict_types=1);
$dir = __DIR__ . '/sample-assets/enrollment';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
file_put_contents($dir . '/sample-id.png', $png);
$pdf = "%PDF-1.4\n1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n"
    . "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj\n"
    . "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Contents 4 0 R >>endobj\n"
    . "4 0 obj<< /Length 44 >>stream\nBT /F1 12 Tf 40 100 Td (Sample PSA) Tj ET\nendstream\nendobj\n"
    . "xref\n0 5\ntrailer<< /Root 1 0 R /Size 5 >>\nstartxref\n0\n%%EOF\n";
file_put_contents($dir . '/sample-psa.pdf', $pdf);
echo "Wrote sample assets to {$dir}\n";
