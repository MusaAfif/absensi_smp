<?php
// Enable error reporting to log file instead of display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/api_error.log');

require_once __DIR__ . '/../../includes/attendance_service.php';

function respondAttendanceApiFailure(string $message, ?Throwable $exception = null): void
{
    logAttendanceError($message, [
        'exception' => $exception ? $exception->getMessage() : null,
        'file' => $exception ? $exception->getFile() : null,
        'line' => $exception ? $exception->getLine() : null,
    ]);

    jsonResponse('error', ['message' => 'Terjadi kesalahan pada server. Silakan coba lagi.'], 500);
}

// Always return JSON, catch all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    respondAttendanceApiFailure('Attendance pulang PHP error', new ErrorException($errstr, 0, $errno, $errfile, $errline));
});

set_exception_handler(function($e) {
    respondAttendanceApiFailure('Attendance pulang exception', $e);
});

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', ['message' => 'Method tidak diizinkan. Gunakan POST.'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        jsonResponse('error', ['message' => 'Input JSON tidak valid.'], 400);
    }

    $kode = trim($input['kode'] ?? '');
    $tipe = trim($input['tipe'] ?? 'rfid');

    if ($kode === '') {
        jsonResponse('error', ['message' => 'Kode tidak boleh kosong.'], 400);
    }

    // Validate input - prevent SQL injection and malicious input
    if (strlen($kode) > 100) {
        jsonResponse('error', ['message' => 'Kode terlalu panjang.'], 400);
    }

    if (!preg_match('/^[a-zA-Z0-9\-_\.]+$/', $kode)) {
        jsonResponse('error', ['message' => 'Format kode tidak valid.'], 400);
    }

    // Check if function exists
    if (!function_exists('processAttendancePulang')) {
        throw new Exception('Function processAttendancePulang tidak ditemukan');
    }

    // Check if database connected
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection tidak tersedia');
    }

    $response = processAttendancePulang($conn, $kode, $tipe);
    if ($response['status'] === 'success') {
        jsonResponse('success', $response['data'], 200);
    }

    jsonResponse('error', ['message' => $response['message'] ?? 'Unknown error'], $response['code'] ?? 400);

} catch (Exception $e) {
    respondAttendanceApiFailure('Attendance pulang catch block', $e);
}