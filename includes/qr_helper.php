<?php
require_once __DIR__ . '/../assets/vendor/phpqrcode/qrlib.php';

function generateQrDataUri(string $data, string $errorLevel = QR_ECLEVEL_H, int $matrixSize = 10, int $margin = 2): string
{
    $payload = trim($data);
    if ($payload === '') {
        return '';
    }

    // Suppress error output agar notices/warnings dari phpqrcode (library lama)
    // tidak tercampur ke dalam binary PNG dan merusaknya
    $prevReporting = error_reporting(0);
    ob_start();
    QRcode::png($payload, false, $errorLevel, $matrixSize, $margin);
    $pngBinary = ob_get_clean();
    error_reporting($prevReporting);

    if ($pngBinary === false || $pngBinary === '') {
        return '';
    }

    return 'data:image/png;base64,' . base64_encode($pngBinary);
}
