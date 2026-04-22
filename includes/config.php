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

function generate_uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function get_current_tahun_ajaran_label(): string
{
    $month = (int) date('n');
    $year = (int) date('Y');

    if ($month >= 7) {
        return sprintf('%d/%d', $year, $year + 1);
    }

    return sprintf('%d/%d', $year - 1, $year);
}

function run_schema_query(mysqli $conn, string $sql): bool
{
    try {
        return mysqli_query($conn, $sql) !== false;
    } catch (Throwable $e) {
        app_log_error('Schema query skipped', [
            'error' => $e->getMessage(),
            'sql' => $sql,
        ]);
        return false;
    }
}

function schema_column_exists(mysqli $conn, string $table, string $column): bool
{
    try {
        $table = mysqli_real_escape_string($conn, $table);
        $column = mysqli_real_escape_string($conn, $column);
        $result = mysqli_query($conn, "SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        return $result && mysqli_num_rows($result) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function schema_index_exists(mysqli $conn, string $table, string $index): bool
{
    try {
        $table = mysqli_real_escape_string($conn, $table);
        $index = mysqli_real_escape_string($conn, $index);
        $result = mysqli_query($conn, "SHOW INDEX FROM {$table} WHERE Key_name = '{$index}'");
        return $result && mysqli_num_rows($result) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function ensure_student_identity_schema(mysqli $conn): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $initialized = true;

    if (!schema_column_exists($conn, 'siswa', 'student_uuid')) {
        run_schema_query($conn, "ALTER TABLE siswa ADD COLUMN student_uuid CHAR(36) NULL AFTER id_siswa");
    }
    if (!schema_column_exists($conn, 'siswa', 'status_siswa')) {
        run_schema_query($conn, "ALTER TABLE siswa ADD COLUMN status_siswa ENUM('aktif','lulus','pindah','nonaktif','hapus') NOT NULL DEFAULT 'aktif' AFTER id_kelas");
    }
    if (!schema_index_exists($conn, 'siswa', 'uk_siswa_student_uuid')) {
        run_schema_query($conn, "ALTER TABLE siswa ADD UNIQUE KEY uk_siswa_student_uuid (student_uuid)");
    }
    run_schema_query($conn, "UPDATE siswa SET status_siswa = 'aktif' WHERE status_siswa IS NULL OR status_siswa = ''");

    try {
        $uuidResult = mysqli_query($conn, "SELECT id_siswa FROM siswa WHERE student_uuid IS NULL OR student_uuid = ''");
    } catch (Throwable $e) {
        app_log_error('UUID backfill select failed', ['error' => $e->getMessage()]);
        $uuidResult = false;
    }
    if ($uuidResult) {
        while ($row = mysqli_fetch_assoc($uuidResult)) {
            $uuid = generate_uuid_v4();
            try {
                $stmt = mysqli_prepare($conn, 'UPDATE siswa SET student_uuid = ? WHERE id_siswa = ?');
            } catch (Throwable $e) {
                app_log_error('UUID backfill prepare failed', ['error' => $e->getMessage()]);
                $stmt = false;
            }
            if ($stmt) {
                try {
                    mysqli_stmt_bind_param($stmt, 'si', $uuid, $row['id_siswa']);
                    mysqli_stmt_execute($stmt);
                } catch (Throwable $e) {
                    app_log_error('UUID backfill execute failed', ['error' => $e->getMessage(), 'id_siswa' => $row['id_siswa']]);
                }
                mysqli_stmt_close($stmt);
            }
        }
        mysqli_free_result($uuidResult);
    }

    run_schema_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS tahun_ajaran (
            id_tahun_ajaran INT AUTO_INCREMENT PRIMARY KEY,
            nama_tahun_ajaran VARCHAR(20) NOT NULL UNIQUE,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    run_schema_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS siswa_kelas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_siswa INT NOT NULL,
            id_kelas INT NOT NULL,
            id_tahun_ajaran INT NOT NULL,
            status ENUM('aktif','lulus','pindah','nonaktif') NOT NULL DEFAULT 'aktif',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_siswa_tahun (id_siswa, id_tahun_ajaran),
            KEY idx_siswa_kelas_kelas (id_kelas),
            CONSTRAINT fk_siswa_kelas_siswa FOREIGN KEY (id_siswa) REFERENCES siswa (id_siswa) ON DELETE CASCADE,
            CONSTRAINT fk_siswa_kelas_kelas FOREIGN KEY (id_kelas) REFERENCES kelas (id_kelas) ON DELETE CASCADE,
            CONSTRAINT fk_siswa_kelas_tahun FOREIGN KEY (id_tahun_ajaran) REFERENCES tahun_ajaran (id_tahun_ajaran) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $activeYearLabel = get_current_tahun_ajaran_label();
    try {
        $stmt = mysqli_prepare($conn, 'INSERT INTO tahun_ajaran (nama_tahun_ajaran, is_active) VALUES (?, 1) ON DUPLICATE KEY UPDATE nama_tahun_ajaran = VALUES(nama_tahun_ajaran)');
    } catch (Throwable $e) {
        app_log_error('Active year prepare failed', ['error' => $e->getMessage()]);
        $stmt = false;
    }
    if ($stmt) {
        try {
            mysqli_stmt_bind_param($stmt, 's', $activeYearLabel);
            mysqli_stmt_execute($stmt);
        } catch (Throwable $e) {
            app_log_error('Active year execute failed', ['error' => $e->getMessage()]);
        }
        mysqli_stmt_close($stmt);
    }

    run_schema_query($conn, "UPDATE tahun_ajaran SET is_active = CASE WHEN nama_tahun_ajaran = '" . mysqli_real_escape_string($conn, $activeYearLabel) . "' THEN 1 ELSE 0 END");

    try {
        $activeYearResult = mysqli_query($conn, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE is_active = 1 LIMIT 1");
    } catch (Throwable $e) {
        app_log_error('Active year select failed', ['error' => $e->getMessage()]);
        $activeYearResult = false;
    }
    $activeYear = $activeYearResult ? mysqli_fetch_assoc($activeYearResult) : null;
    if ($activeYearResult) {
        mysqli_free_result($activeYearResult);
    }

    if ($activeYear) {
        $activeYearId = (int) $activeYear['id_tahun_ajaran'];
        $sql = "
            INSERT INTO siswa_kelas (id_siswa, id_kelas, id_tahun_ajaran, status)
            SELECT
                s.id_siswa,
                s.id_kelas,
                ?,
                CASE
                    WHEN s.status_siswa IN ('lulus', 'pindah', 'nonaktif') THEN s.status_siswa
                    ELSE 'aktif'
                END
            FROM siswa s
            WHERE s.id_kelas IS NOT NULL
            ON DUPLICATE KEY UPDATE
                id_kelas = VALUES(id_kelas),
                status = VALUES(status),
                updated_at = CURRENT_TIMESTAMP
        ";
        try {
            $stmt = mysqli_prepare($conn, $sql);
        } catch (Throwable $e) {
            app_log_error('siswa_kelas sync prepare failed', ['error' => $e->getMessage()]);
            $stmt = false;
        }
        if ($stmt) {
            try {
                mysqli_stmt_bind_param($stmt, 'i', $activeYearId);
                mysqli_stmt_execute($stmt);
            } catch (Throwable $e) {
                app_log_error('siswa_kelas sync execute failed', ['error' => $e->getMessage()]);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

function get_student_card_identifier(array $student): string
{
    $studentUuid = trim((string) ($student['student_uuid'] ?? ''));
    if ($studentUuid !== '') {
        return build_student_qr_payload($studentUuid);
    }

    return (string) ($student['id_siswa'] ?? '');
}

function get_qr_signing_secret(): string
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }

    $envSecret = getenv('APP_QR_SIGNING_KEY');
    if (is_string($envSecret) && trim($envSecret) !== '') {
        $secret = trim($envSecret);
        return $secret;
    }

    $secret = hash('sha256', APP_DB_NAME . '|' . APP_DB_HOST . '|permanent-card-signing');
    return $secret;
}

function sign_student_uuid(string $studentUuid): string
{
    $signature = hash_hmac('sha256', $studentUuid, get_qr_signing_secret());
    return substr($signature, 0, 16);
}

function build_student_qr_payload(string $studentUuid): string
{
    $uuid = strtolower(trim($studentUuid));
    if (!preg_match('/^[a-f0-9\-]{36}$/', $uuid)) {
        return $uuid;
    }

    return 'SID1.' . $uuid . '.' . sign_student_uuid($uuid);
}

function resolve_student_identifier_for_lookup(string $identifier): array
{
    $raw = trim($identifier);
    if ($raw === '') {
        return ['value' => '', 'is_signed' => false, 'is_valid' => false];
    }

    if (preg_match('/^SID1\.([a-f0-9\-]{36})\.([a-f0-9]{16})$/i', $raw, $matches)) {
        $uuid = strtolower($matches[1]);
        $signature = strtolower($matches[2]);
        $expected = sign_student_uuid($uuid);

        if (!hash_equals($expected, $signature)) {
            return ['value' => $uuid, 'is_signed' => true, 'is_valid' => false];
        }

        return ['value' => $uuid, 'is_signed' => true, 'is_valid' => true];
    }

    return ['value' => $raw, 'is_signed' => false, 'is_valid' => true];
}

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

mysqli_set_charset($conn, 'utf8mb4');
ensure_student_identity_schema($conn);

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