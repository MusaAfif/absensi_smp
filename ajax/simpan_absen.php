<?php
/**
 * LEGACY ENDPOINT - DEPRECATED
 * This file is deprecated and will be removed in future versions.
 * All attendance processing now uses pages/scan_proses.php
 */

require_once __DIR__ . '/../includes/config.php';

// Redirect to new endpoint
header('HTTP/1.1 301 Moved Permanently');
header('Location: ' . BASE_URL . 'pages/scan_proses.php');
exit;

// For backward compatibility, if someone still calls this directly
require_once __DIR__ . '/../includes/attendance_service.php';

$identifier = trim($_POST['nisn'] ?? $_POST['identifier'] ?? $_POST['nis'] ?? '');
if ($identifier === '') {
    jsonResponse('error', ['message' => 'Input scan kosong.'], 400);
}

// Validate input to prevent SQL injection
if (strlen($identifier) > 20 || !preg_match('/^[a-zA-Z0-9\-_]+$/', $identifier)) {
    jsonResponse('error', ['message' => 'Format input tidak valid.'], 400);
}

$response = processAttendanceScanRequest($conn, $identifier, 'legacy_ajax', '');
if ($response['status'] === 'success') {
    // Convert to old format for backward compatibility
    jsonResponse('success', [
        'nama' => $response['data']['nama'],
        'nisn' => $identifier, // Legacy field
        'kelas' => $response['data']['kelas'],
        'foto' => $response['data']['foto'],
        'jam' => $response['data']['jam'],
        'status_absen' => $response['data']['status_absen']
    ]);
}

jsonResponse('error', ['message' => $response['message'] ?? 'Gagal memproses scan.'], $response['code'] ?? 400);