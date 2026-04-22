<?php
require_once __DIR__ . '/../assets/vendor/phpqrcode/qrlib.php';

function generateQrDataUri(string $data, string $errorLevel = QR_ECLEVEL_H, int $matrixSize = 10, int $margin = 2): string
{
    $payload = trim($data);
    if ($payload === '') {
        return '';
    }

    // Gunakan file sementara agar phpqrcode tidak mengubah Content-Type response jadi image/png.
    $tmpFile = tempnam(sys_get_temp_dir(), 'qr_');
    if ($tmpFile === false) {
        return '';
    }

    $prevReporting = error_reporting(0);
    QRcode::png($payload, $tmpFile, $errorLevel, $matrixSize, $margin);
    error_reporting($prevReporting);

    $pngBinary = @file_get_contents($tmpFile);
    @unlink($tmpFile);

    if ($pngBinary === false || $pngBinary === '') {
        return '';
    }

    return 'data:image/png;base64,' . base64_encode($pngBinary);
}
