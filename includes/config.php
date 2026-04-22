<?php
// Konfigurasi error global: log ke file, jangan tampilkan ke user
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

function app_log_error(string $message, array $context = []): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $entry = sprintf(
        "[%s] %s %s\n",
        date('Y-m-d H:i:s'),
        $message,
        $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
    );

    @file_put_contents($logDir . '/app.log', $entry, FILE_APPEND | LOCK_EX);
}

function apply_security_headers(bool $isSecureConnection): void
{
    if (headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Resource-Policy: same-origin');

    if ($isSecureConnection) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
apply_security_headers($secure);
require_https();
$hostHeader = $_SERVER['HTTP_HOST'] ?? '';
$cookieDomain = preg_replace('/:\d+$/', '', $hostHeader);
if ($cookieDomain === 'localhost' || filter_var($cookieDomain, FILTER_VALIDATE_IP)) {
    $cookieDomain = '';
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $cookieDomain,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set('Asia/Jakarta');
define('APP_TIMEZONE', 'Asia/Jakarta');
define('APP_ENV', 'production');

// ================================================================
// FIXED: Proper BASE_URL detection for Laragon
// ================================================================
// Detect protocol
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];

// For localhost/Laragon with subfolder - hardcode project path
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    // Check if running on port 8000 (development server) - if so, no basePath needed
    if (strpos($host, ':8000') !== false) {
        // Running on PHP development server - no subfolder needed
        $basePath = '';
    } else {
        // Running on Laragon Apache - need subfolder
        $basePath = '/absensi_smp';
    }
} else {
    // Running on production domain
    $basePath = '';
}

// Full URL for redirects (protocol + host + path)
define('BASE_URL', $protocol . $host . $basePath . '/');

// Path only for relative includes
define('BASE_PATH', $basePath . '/');

// For backward compatibility
define('APP_BASE_URL', $basePath);

define('APP_DB_HOST', 'localhost');
define('APP_DB_USER', 'root');
define('APP_DB_PASS', '');
define('APP_DB_NAME', 'db_absensi_smp');

$conn = mysqli_connect(APP_DB_HOST, APP_DB_USER, APP_DB_PASS, APP_DB_NAME);

if (!$conn) {
    app_log_error('Database connection failed', [
        'host' => APP_DB_HOST,
        'database' => APP_DB_NAME,
        'error' => mysqli_connect_error(),
    ]);

    http_response_code(500);
    exit('Terjadi masalah koneksi database. Periksa konfigurasi server dan coba lagi.');
}

// Fungsi proteksi halaman admin
function cek_login() {
    if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
        header("Location: " . BASE_URL . "login.php?pesan=wajib_login");
        exit;
    }
}

/**
 * Pastikan user sudah login DAN memiliki role yang diizinkan.
 * Gunakan untuk halaman/aksi yang hanya boleh diakses super_admin.
 *
 * @param string|string[] $allowedRoles  Default: ['super_admin']
 */
function cek_role($allowedRoles = ['super_admin']): void {
    cek_login();
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    $currentRole = $_SESSION['role'] ?? '';
    if (!in_array($currentRole, $allowedRoles, true)) {
        http_response_code(403);
        // Untuk request AJAX/non-HTML kembalikan JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Akses ditolak. Anda tidak memiliki izin untuk tindakan ini.']);
        } else {
            $_SESSION['error'] = 'Akses ditolak. Fitur ini hanya tersedia untuk Super Admin.';
            header("Location: " . BASE_URL . "pages/dashboard.php");
        }
        exit;
    }
}

/**
 * Rate limiting untuk login — maks $maxAttempts percobaan per $windowSeconds detik per IP.
 * Return true jika masih boleh, false jika sudah melampaui batas.
 */
function check_login_rate_limit(mysqli $conn, string $ip, int $maxAttempts = 5, int $windowSeconds = 300): bool
{
    // Buat tabel rate_limit jika belum ada (shared dengan attendance rate limit)
    $conn->query(
        "CREATE TABLE IF NOT EXISTS rate_limit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL,
            request_count INT NOT NULL DEFAULT 1,
            window_start INT NOT NULL,
            INDEX idx_identifier_window (identifier, window_start)
        ) ENGINE=InnoDB"
    );

    // Ensure column types are correct (in case table exists with wrong types)
    $conn->query("ALTER TABLE rate_limit MODIFY window_start INT NOT NULL");
    
    $now = time();
    $windowStart = $now - $windowSeconds;
    $key = 'login_' . $ip;

    // Hapus entri lama
    $del = $conn->prepare('DELETE FROM rate_limit WHERE identifier = ? AND window_start < ?');
    if ($del) {
        $del->bind_param('si', $key, $windowStart);
        $del->execute();
        $del->close();
    }

    // Cek jumlah percobaan dalam window
    $sel = $conn->prepare('SELECT request_count FROM rate_limit WHERE identifier = ? AND window_start >= ?');
    if (!$sel) {
        return true; // Gagal cek = boleh masuk (fail open agar user tidak terkunci karena error DB)
    }
    $sel->bind_param('si', $key, $windowStart);
    $sel->execute();
    $result = $sel->get_result();
    $row = $result->fetch_assoc();
    $sel->close();

    if ($row) {
        if ((int)$row['request_count'] >= $maxAttempts) {
            return false; // Batas terlampaui
        }
        $upd = $conn->prepare('UPDATE rate_limit SET request_count = request_count + 1 WHERE identifier = ? AND window_start >= ?');
        if ($upd) {
            $upd->bind_param('si', $key, $windowStart);
            $upd->execute();
            $upd->close();
        }
    } else {
        $ins = $conn->prepare('INSERT INTO rate_limit (identifier, request_count, window_start) VALUES (?, 1, ?)');
        if ($ins) {
            $ins->bind_param('si', $key, $now);
            $ins->execute();
            $ins->close();
        }
    }

    return true;
}

/**
 * Paksa redirect ke HTTPS jika bukan localhost dan belum menggunakan HTTPS.
 * Panggil sebelum output apapun.
 */
function require_https(): void {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1') || str_ends_with($host, '.test') || str_ends_with($host, '.local');
    if ($isLocal) {
        return; // Tidak paksa HTTPS di lokal/dev
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if (!$isHttps) {
        $url = 'https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . $url, true, 301);
        exit;
    }
}

// Safe redirect helper - gunakan untuk semua redirect
function safe_redirect($page = '', $params = []) {
    $routeMap = [
        'dashboard' => 'pages/dashboard.php',
        'siswa' => 'pages/siswa.php',
        'kelas' => 'pages/kelas.php',
        'laporan' => 'pages/laporan.php',
        'laporan_harian' => 'pages/laporan_harian.php',
        'laporan_gabungan' => 'pages/laporan_gabungan.php',
        'scan_center' => 'pages/scan_center.php',
        'scan_masuk' => 'pages/scan_masuk.php',
        'scan_pulang' => 'pages/scan_pulang.php',
        'scan_proses' => 'pages/scan_proses.php',
        'profile' => 'pages/profile.php',
        'import_siswa' => 'pages/import_siswa.php',
        'import_proses' => 'pages/import_proses.php',
        'restore' => 'pages/restore.php',
        'restore_backup' => 'api/restore.php',
        'pengaturan' => 'pengaturan/',
        'pengaturan/process_pengaturan' => 'pengaturan/process_pengaturan.php',
        'pengaturan/process_admin' => 'pengaturan/process_admin.php',
        'admin/user' => 'admin/user.php',
        'admin/process_user' => 'admin/process_user.php',
        'reset_password' => 'reset_password.php',
    ];

    $url = BASE_URL;
    if (!empty($page)) {
        if (isset($routeMap[$page])) {
            $url .= $routeMap[$page];
        } elseif (preg_match('/\.php$/', $page)) {
            $url .= ltrim($page, '/');
        } else {
            $url .= 'pages/' . ltrim($page, '/') . '.php';
        }
    }

    if (!empty($params)) {
        $separator = (strpos($url, '?') !== false) ? '&' : '?';
        foreach ($params as $key => $value) {
            $url .= $separator . urlencode($key) . '=' . urlencode($value);
            $separator = '&';
        }
    }

    header("Location: " . $url);
    exit;
}

// Alternative: direct URL redirect
function redirect_to($url, $params = []) {
    if (!empty($params)) {
        $separator = (strpos($url, '?') !== false) ? '&' : '?';
        foreach ($params as $key => $value) {
            $url .= $separator . urlencode($key) . '=' . urlencode($value);
            $separator = '&';
        }
    }
    header("Location: " . $url);
    exit;
}
?>