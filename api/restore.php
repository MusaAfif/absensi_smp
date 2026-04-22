<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
cek_role(['super_admin']);

function restore_redirect_error(string $message): void
{
    header('Location: ' . BASE_URL . 'pages/restore.php?error=' . urlencode($message));
    exit;
}

function restore_redirect_success(string $fileName): void
{
    header('Location: ' . BASE_URL . 'pages/restore.php?status=restored&file=' . urlencode($fileName));
    exit;
}

function validate_restore_sql(string $sql): array
{
    if (strlen($sql) < 20) {
        return ['valid' => false, 'message' => 'Isi backup terlalu pendek atau kosong.'];
    }

    $hasStructure = preg_match('/\bCREATE\s+TABLE\b/i', $sql) === 1;
    $hasData = preg_match('/\bINSERT\s+INTO\b/i', $sql) === 1;
    if (!$hasStructure && !$hasData) {
        return ['valid' => false, 'message' => 'File SQL tidak berisi struktur/data backup yang valid.'];
    }

    $forbiddenPatterns = [
        '/\bINTO\s+OUTFILE\b/i',
        '/\bLOAD_FILE\s*\(/i',
        '/\bLOAD\s+DATA\s+(LOCAL\s+)?INFILE\b/i',
        '/\bCREATE\s+USER\b/i',
        '/\bALTER\s+USER\b/i',
        '/\bDROP\s+USER\b/i',
        '/\bGRANT\b/i',
        '/\bREVOKE\b/i',
        '/\bSET\s+GLOBAL\b/i',
        '/\bFLUSH\s+PRIVILEGES\b/i',
        '/\bSHUTDOWN\b/i',
        '/\bmysql\s*\./i',
        '/\binformation_schema\b/i',
        '/\bperformance_schema\b/i',
        '/\bsys\s*\./i',
    ];

    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $sql) === 1) {
            return ['valid' => false, 'message' => 'File SQL mengandung statement yang tidak diizinkan untuk restore aplikasi.'];
        }
    }

    return ['valid' => true, 'message' => 'OK'];
}

// Initialize CSRF protection
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
CSRFProtection::init();

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
    restore_redirect_error('CSRF token tidak valid.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    restore_redirect_error('Metode request tidak diizinkan.');
}

if (!isset($_FILES['backup_file']) || !is_array($_FILES['backup_file'])) {
    restore_redirect_error('File backup tidak ditemukan.');
}

$upload = $_FILES['backup_file'];
$file_name = SecurityHelper::sanitizeInput($upload['name'] ?? 'backup.sql');
$tmpFile = $upload['tmp_name'] ?? '';
$maxSize = 20 * 1024 * 1024; // 20MB

if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    restore_redirect_error('Upload file backup gagal.');
}

if ($tmpFile === '' || !is_uploaded_file($tmpFile) || !file_exists($tmpFile)) {
    restore_redirect_error('Sumber file upload tidak valid.');
}

$fileSize = (int) ($upload['size'] ?? 0);
if ($fileSize <= 0 || $fileSize > $maxSize) {
    restore_redirect_error('Ukuran file backup tidak valid (maksimal 20MB).');
}

$ext = strtolower(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION));
if ($ext !== 'sql') {
    restore_redirect_error('Format file harus .sql');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($tmpFile);

$allowedMimes = ['text/plain', 'application/sql', 'text/x-sql', 'application/octet-stream'];
if (!in_array($mime, $allowedMimes, true)) {
    restore_redirect_error('MIME type file backup tidak diizinkan.');
}

$sql = file_get_contents($tmpFile);
if ($sql === false) {
    restore_redirect_error('Tidak dapat membaca isi file backup.');
}

$sqlValidation = validate_restore_sql($sql);
if (!$sqlValidation['valid']) {
    restore_redirect_error($sqlValidation['message']);
}

mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS = 0');

try {
    if (!mysqli_multi_query($conn, $sql)) {
        app_log_error('Restore SQL failed at start', [
            'file' => $file_name,
            'error' => mysqli_error($conn),
        ]);
        restore_redirect_error('Restore database gagal dijalankan.');
    }

    $loopGuard = 0;
    do {
        if ($result = mysqli_store_result($conn)) {
            mysqli_free_result($result);
        }

        if (++$loopGuard > 50000) {
            app_log_error('Restore SQL aborted by loop guard', ['file' => $file_name]);
            restore_redirect_error('Restore dihentikan: file backup terlalu kompleks.');
        }
    } while (mysqli_more_results($conn) && mysqli_next_result($conn));

    if (mysqli_errno($conn) !== 0) {
        app_log_error('Restore SQL failed while iterating', [
            'file' => $file_name,
            'error' => mysqli_error($conn),
        ]);
        restore_redirect_error('Restore database gagal diproses.');
    }

    app_log_error('Restore SQL success', [
        'file' => $file_name,
        'size' => $fileSize,
        'user' => $_SESSION['admin'] ?? 'unknown',
    ]);

    restore_redirect_success($file_name);
} finally {
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS = 1');
}
?>
