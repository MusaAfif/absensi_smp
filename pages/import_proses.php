<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
require_once __DIR__ . '/../includes/import_service.php';

cek_login();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
CSRFProtection::init();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['import'])) {
    safe_redirect('import_siswa');
}

if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['import_error'] = 'CSRF token tidak valid. Silakan coba lagi.';
    safe_redirect('import_siswa');
}

$fileName = SecurityHelper::sanitizeInput($_FILES['file_siswa']['name'] ?? '');
$fileTmp = $_FILES['file_siswa']['tmp_name'] ?? '';
$fileError = $_FILES['file_siswa']['error'] ?? UPLOAD_ERR_NO_FILE;
$fileSize = (int)($_FILES['file_siswa']['size'] ?? 0);

if ($fileError !== UPLOAD_ERR_OK) {
    $_SESSION['import_error'] = 'Upload file gagal. Silakan pilih ulang file CSV Anda.';
    safe_redirect('import_siswa');
}

if (empty($fileName) || empty($fileTmp)) {
    $_SESSION['import_error'] = 'File tidak dipilih';
    safe_redirect('import_siswa');
}

if (!is_uploaded_file($fileTmp)) {
    $_SESSION['import_error'] = 'Sumber file upload tidak valid';
    safe_redirect('import_siswa');
}

if ($fileSize <= 0 || $fileSize > (5 * 1024 * 1024)) {
    $_SESSION['import_error'] = 'Ukuran file CSV tidak valid (maksimal 5MB)';
    safe_redirect('import_siswa');
}

$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if ($extension !== 'csv') {
    $_SESSION['import_error'] = 'Format file harus CSV. File Anda: .' . htmlspecialchars($extension);
    safe_redirect('import_siswa');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = (string) $finfo->file($fileTmp);

$allowedCsvMimes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
if (!in_array($mimeType, $allowedCsvMimes, true)) {
    $_SESSION['import_error'] = 'MIME type file tidak valid untuk CSV';
    safe_redirect('import_siswa');
}

$tempPath = sys_get_temp_dir() . '/import_' . uniqid() . '.csv';
if (!move_uploaded_file($fileTmp, $tempPath)) {
    $_SESSION['import_error'] = 'Gagal mengupload file ke server';
    safe_redirect('import_siswa');
}

$service = new StudentImportService($conn);

$validationErrors = $service->validateFile($tempPath);
if (!empty($validationErrors)) {
    $_SESSION['import_errors'] = $validationErrors;
    unlink($tempPath);
    safe_redirect('import_siswa');
}

if (!$service->processFile($tempPath)) {
    $_SESSION['import_error'] = 'Gagal memproses file. Lihat log untuk detail.';
    unlink($tempPath);
    safe_redirect('import_siswa');
}

$stats = $service->getStats();
$errors = $service->getErrors();
$logFile = $service->getLogFile();

$_SESSION['import_success'] = true;
$_SESSION['import_stats'] = $stats;
$_SESSION['import_errors'] = $errors;
$_SESSION['import_log'] = $logFile;

unlink($tempPath);
safe_redirect('import_siswa', ['result' => 'success']);
exit;

