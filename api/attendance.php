<?php
require_once __DIR__ . '/../includes/attendance_service.php';

function respondAttendanceServerError(string $message, ?Throwable $exception = null): void
{
    logAttendanceError($message, [
        'exception' => $exception ? $exception->getMessage() : null,
        'file' => $exception ? $exception->getFile() : null,
        'line' => $exception ? $exception->getLine() : null,
    ]);

    jsonResponse('error', ['message' => 'Terjadi kesalahan pada server. Silakan coba lagi.'], 500);
}

// Set reasonable timeout to prevent hanging
set_time_limit(15);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', ['message' => 'Method tidak diizinkan. Gunakan POST.'], 405);
    exit;
}

$apiKey = '';
if (!empty($_SERVER['HTTP_X_API_KEY'])) {
    $apiKey = trim($_SERVER['HTTP_X_API_KEY']);
} elseif (!empty($_POST['device_key'])) {
    $apiKey = trim($_POST['device_key']);
}

$input = $_POST;
if (empty($input)) {
    $rawBody = file_get_contents('php://input');
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$identifier = trim($input['identifier'] ?? $input['barcode'] ?? '');
if ($identifier === '') {
    jsonResponse('error', ['message' => 'Identifier tidak boleh kosong.'], 400);
    exit;
}

try {
    $response = processAttendanceScanRequest($conn, $identifier, 'device', $apiKey);
} catch (Exception $e) {
    respondAttendanceServerError('Attendance device scan exception', $e);
} catch (Throwable $e) {
    respondAttendanceServerError('Attendance device scan fatal', $e);
}

// Verify response structure
if (!is_array($response)) {
    jsonResponse('error', ['message' => 'Invalid response from processor'], 500);
    exit;
}

if ($response['status'] === 'success') {
    jsonResponse('success', $response['data'], 200);
    exit;
}

jsonResponse('error', ['message' => $response['message'] ?? 'Unknown error'], $response['code'] ?? 400);
exit;
