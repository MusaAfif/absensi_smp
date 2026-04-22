<?php
require_once 'includes/config.php';
require_once 'includes/SecurityHelper.php';
require_once 'includes/DatabaseHelper.php';
require_once 'includes/CSRFProtection.php';

CSRFProtection::init();
$security = new SecurityHelper();
$dbHelper = new DatabaseHelper($conn);

// Ambil logo untuk tampilan
$get_logo = mysqli_query($conn, "SELECT isi_pengaturan FROM pengaturan WHERE nama_pengaturan='logo_sekolah'");
$logo = mysqli_fetch_assoc($get_logo)['isi_pengaturan'] ?? 'default_logo.png';

// Ambil nama sekolah
$get_nama = mysqli_query($conn, "SELECT isi_pengaturan FROM pengaturan WHERE nama_pengaturan='nama_sekolah'");
$nama_sekolah = mysqli_fetch_assoc($get_nama)['isi_pengaturan'] ?? 'SMPN 1 Indonesia';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    // Rate limiting: maks 5 percobaan per 5 menit per IP
    $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $clientIp = trim(explode(',', $clientIp)[0]); // ambil IP pertama jika di balik proxy
    if (!check_login_rate_limit($conn, $clientIp, 5, 300)) {
        $error = "Terlalu banyak percobaan login. Silakan tunggu beberapa menit dan coba lagi.";
        app_log_error('Login rate limit exceeded', ['ip' => $clientIp]);
    } else {
    // Verify CSRF token
    if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
        $error = "CSRF token tidak valid. Segarkan halaman dan coba lagi.";    
    } else {
        $user = trim(SecurityHelper::sanitizeInput($_POST['username']));
        $pass = $_POST['password'];
        $error = "";
        $found_user = false;
        $password_valid = false;
        $legacy_password = false;
        $user_source = '';
        $login_data = null;

        error_log("Login attempt - Username: $user");

    // Cek login di tabel admin terlebih dahulu
    $admin_table_exists = mysqli_query($conn, "SHOW TABLES LIKE 'admin'");
    if ($admin_table_exists && mysqli_num_rows($admin_table_exists) > 0) {
        $admin_data = $dbHelper->selectOne("SELECT *, 'admin' AS source FROM admin WHERE username = ? LIMIT 1", [$user], 's');
        if ($admin_data) {
            $found_user = true;
            $user_source = 'admin';
            if (password_verify($pass, $admin_data['password'])) {
                $password_valid = true;
                $login_data = $admin_data;
            } elseif ($admin_data['password'] === md5($pass) || $admin_data['password'] === $pass) {
                $password_valid = true;
                $login_data = $admin_data;
                $legacy_password = true;
            }

            if ($password_valid && !empty($legacy_password)) {
                $rehash = SecurityHelper::hashPassword($pass);
                $dbHelper->update("UPDATE admin SET password = ? WHERE id = ?", [$rehash, $admin_data['id']], 'si');
            }
        }
    }

    // Jika belum cocok di admin, coba tabel users
    if (!$password_valid) {
        $users_table_exists = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
        if ($users_table_exists && mysqli_num_rows($users_table_exists) > 0) {
            $user_data = $dbHelper->selectOne("SELECT *, 'users' AS source FROM users WHERE username = ? LIMIT 1", [$user], 's');
            if ($user_data) {
                $found_user = true;
                $user_source = 'users';
                if (password_verify($pass, $user_data['password'])) {
                    $password_valid = true;
                    $login_data = $user_data;
                } elseif ($user_data['password'] === $pass) {
                    $password_valid = true;
                    $login_data = $user_data;
                    $legacy_password = true;
                }

                if ($password_valid && !empty($legacy_password)) {
                    $rehash = SecurityHelper::hashPassword($pass);
                    $dbHelper->update("UPDATE users SET password = ? WHERE id_user = ?", [$rehash, $user_data['id_user']], 'si');
                }
            }
        }
    }

    if ($password_valid) {
        $data = $login_data ?: ($admin_data ?? $user_data ?? null);
        if ($data) {
            SecurityHelper::regenerateSessionId();
            $_SESSION['status'] = "login";
            $_SESSION['id_user'] = $data['id_user'] ?? $data['id'] ?? 0;
            $_SESSION['admin'] = $data['username'];
            $_SESSION['nama_admin'] = $data['nama'] ?? $data['nama_admin'] ?? $data['username'];
            $_SESSION['role'] = $data['role'] ?? 'admin';
            error_log("Login berhasil untuk user: $user dari tabel $user_source");
            header("Location: " . BASE_URL . "pages/dashboard.php");
            exit;
        }
    }

    if ($found_user && !$password_valid) {
        $error = "Password salah untuk akun '$user'. Pastikan password sesuai.";
    } else {
        $error = "Username atau Password salah!";
    }
    error_log("Login gagal untuk user: $user - user_found=$found_user password_valid=$password_valid source=$user_source");
    } // end else (CSRF ok)
    } // end else (rate limit ok)
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin | <?= htmlspecialchars($nama_sekolah) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/site.css" rel="stylesheet">
    <link href="assets/css/login.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="login-page">
    <!-- Visual Section (Left) -->
    <div class="visual-section">
        <div class="visual-content">
            <div class="school-illustration">
                <div class="school-building">
                    <div class="school-roof"></div>
                    <div class="school-windows">
                        <div class="window"></div>
                        <div class="window"></div>
                        <div class="window"></div>
                        <div class="window"></div>
                        <div class="window"></div>
                        <div class="window"></div>
                    </div>
                    <div class="school-door"></div>
                </div>
                <div class="tree">
                    <div class="tree-trunk"></div>
                    <div class="tree-leaves"></div>
                </div>
                <div class="road"></div>
                <div class="cloud"></div>
                <div class="cloud"></div>
            </div>
            <h3 class="welcome-title">Selamat Datang</h3>
            <p class="welcome-subtitle-text">Sistem Absensi Sekolah Modern</p>
        </div>
    </div>

    <!-- Form Section (Right) -->
    <div class="form-section">
        <div class="login-card">
            <!-- Logo and Identity -->
            <div class="logo-section">
                <img src="assets/img/logo_sekolah/<?= htmlspecialchars($logo) ?>" alt="Logo Sekolah" class="school-logo">
                <div class="school-name"><?= htmlspecialchars($nama_sekolah) ?></div>
                <div class="welcome-text">Selamat Datang</div>
                <div class="welcome-subtitle">Silakan login untuk melanjutkan</div>
            </div>

            <!-- Alert Container -->
            <div id="alertContainer">
                <?php if(isset($error)) echo "<div class='alert alert-danger py-2 small'>" . htmlspecialchars($error) . "</div>"; ?>
            </div>

            <!-- Login Form -->
            <form id="loginForm" method="POST" action="login.php" data-loading-message="Memverifikasi akun...">
                <?= CSRFProtection::getTokenField() ?>
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Nama Pengguna" required>
                </div>

                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Password" required>
                    <button type="button" id="passwordToggle" class="password-toggle">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <button type="submit" id="loginBtn" name="login" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>

            <!-- Forgot Password Link -->
            <div class="forgot-password">
                <a href="reset_password.php">Lupa password? Gunakan Recovery Code</a>
            </div>

            <!-- Additional Information -->
            <div class="info-section">
                <div class="info-item">
                    <i class="fas fa-check"></i>
                    <span>Semoga Aplikasi ini Dapat Membantu Sekolah Bapak/Ibu</span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/login.js"></script>
</body>
</html>