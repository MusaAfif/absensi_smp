<?php
require_once __DIR__ . '/../includes/attendance_service.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', ['message' => 'Metode tidak diizinkan. Gunakan POST.'], 405);
}

// Verify CSRF token
if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
    jsonResponse('error', ['message' => 'Token keamanan tidak valid.'], 403);
}

if (isset($_POST['check'])) {
    jsonResponse('success', ['database' => 'connected']);
}

$barcode = trim($_POST['barcode'] ?? '');
if ($barcode === '') {
    jsonResponse('error', ['message' => 'Input scan kosong.'], 400);
}

// Validate input - prevent SQL injection and malicious input
if (strlen($barcode) > 100) {
    jsonResponse('error', ['message' => 'Input scan terlalu panjang.'], 400);
}

if (!preg_match('/^[a-zA-Z0-9\-_\.]+$/', $barcode)) {
    jsonResponse('error', ['message' => 'Format scan tidak valid.'], 400);
}

$response = processAttendanceScanRequest($conn, $barcode, 'web', '');
if ($response['status'] === 'success') {
    jsonResponse('success', $response['data'], 200);
}

jsonResponse('error', ['message' => $response['message'] ?? 'Gagal memproses scan.'], $response['code'] ?? 400);
