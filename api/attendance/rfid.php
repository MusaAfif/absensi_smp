<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/api_error.log');

require_once __DIR__ . '/../../includes/attendance_service.php';

function respondRfidFailure(string $message, ?Throwable $exception = null): void
{
    logAttendanceError($message, [
        'exception' => $exception ? $exception->getMessage() : null,
        'file' => $exception ? $exception->getFile() : null,
        'line' => $exception ? $exception->getLine() : null,
    ]);

    jsonResponse('error', ['message' => 'Terjadi kesalahan pada server. Silakan coba lagi.'], 500);
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    respondRfidFailure('RFID API PHP error', new ErrorException($errstr, 0, $errno, $errfile, $errline));
});

set_exception_handler(function($e) {
    respondRfidFailure('RFID API exception', $e);
});

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', ['message' => 'Method tidak diizinkan. Gunakan POST.'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        jsonResponse('error', ['message' => 'Input JSON tidak valid.'], 400);
    }

    $uid = trim((string)($input['uid'] ?? $input['kode'] ?? ''));
    $deviceKey = trim((string)($input['device_key'] ?? ''));
    $source = trim((string)($input['source'] ?? 'rfid'));

    if ($uid === '') {
        jsonResponse('error', ['message' => 'UID RFID tidak boleh kosong.'], 400);
    }

    if (strlen($uid) > 64) {
        jsonResponse('error', ['message' => 'UID RFID terlalu panjang.'], 400);
    }

    if (!preg_match('/^[a-zA-Z0-9\-_\.]+$/', $uid)) {
        jsonResponse('error', ['message' => 'Format UID RFID tidak valid.'], 400);
    }

    $response = processAttendanceScanRequest($conn, $uid, $source, $deviceKey);
    if ($response['status'] === 'success') {
        jsonResponse('success', $response['data'], 200);
    }

    jsonResponse('error', ['message' => $response['message'] ?? 'Gagal memproses RFID.'], $response['code'] ?? 400);
} catch (Throwable $e) {
    respondRfidFailure('RFID API catch block', $e);
}
