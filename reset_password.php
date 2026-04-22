<?php
require_once 'includes/config.php';
require_once 'includes/SecurityHelper.php';
require_once 'includes/CSRFProtection.php';

SecurityHelper::regenerateSessionId();
CSRFProtection::init();

// Ambil nama sekolah
$get_nama = mysqli_query($conn, "SELECT isi_pengaturan FROM pengaturan WHERE nama_pengaturan='nama_sekolah'");
$nama_sekolah = mysqli_fetch_assoc($get_nama)['isi_pengaturan'] ?? 'SMPN 1 Indonesia';

// Buat tabel recovery_codes jika belum ada
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS recovery_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    code VARCHAR(64) NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME DEFAULT NULL,
    INDEX (id_user)
)");

$message = '';
if (isset($_POST['reset_password'])) {
    if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
        $message = "<div class='alert alert-danger py-2 small'>CSRF token tidak valid. Segarkan halaman dan coba lagi.</div>";
    } else {
        $username = SecurityHelper::sanitizeInput($_POST['username'] ?? '');
        $recovery_code = SecurityHelper::sanitizeInput($_POST['recovery_code'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($username) || empty($recovery_code) || empty($new_password) || empty($confirm_password)) {
            $message = "<div class='alert alert-danger py-2 small'>Semua kolom wajib diisi.</div>";
        } elseif ($new_password !== $confirm_password) {
            $message = "<div class='alert alert-danger py-2 small'>Password baru dan konfirmasi tidak cocok.</div>";
        } else {
            $stmt = mysqli_prepare($conn, "SELECT rc.id, rc.id_user FROM recovery_codes rc INNER JOIN users u ON rc.id_user = u.id_user WHERE u.username = ? AND rc.code = ? AND rc.used = 0 LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'ss', $username, $recovery_code);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$row) {
                $message = "<div class='alert alert-danger py-2 small'>Username atau kode cadangan tidak valid / sudah digunakan.</div>";
            } else {
                $hashed_password = SecurityHelper::hashPassword($new_password);
                $stmtUpdate = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id_user = ?");
                mysqli_stmt_bind_param($stmtUpdate, 'si', $hashed_password, $row['id_user']);
                $update_pass = mysqli_stmt_execute($stmtUpdate);
                mysqli_stmt_close($stmtUpdate);

                $stmtMark = mysqli_prepare($conn, "UPDATE recovery_codes SET used = 1, used_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmtMark, 'i', $row['id']);
                $mark_used = mysqli_stmt_execute($stmtMark);
                mysqli_stmt_close($stmtMark);

                if ($update_pass && $mark_used) {
                    $message = "<div class='alert alert-success py-2 small'><i class='fas fa-check-circle me-2'></i>Password berhasil direset. Silakan login dengan password baru. <a href='login.php' class='alert-link'>Kembali ke Login</a></div>";
                } else {
                    $message = "<div class='alert alert-danger py-2 small'>Gagal mereset password. Coba lagi.</div>";
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password Admin | <?= $nama_sekolah ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/site.css" rel="stylesheet">
    <link href="assets/css/reset_password.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="reset-password-page">
    <div class="reset-password-card">
        <h4 class="fw-bold mb-3 text-center">Reset Password dengan Recovery Code</h4>
        <?= $message; ?>
        <form id="resetForm" method="POST">
            <?= CSRFProtection::getTokenField() ?>
            <div class="mb-3">
                <label class="form-label small fw-bold">Username</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username Anda" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold">Kode Cadangan</label>
                <input type="text" id="recoveryCode" name="recovery_code" class="form-control" placeholder="Masukkan kode cadangan" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold">Password Baru</label>
                <input type="password" id="newPassword" name="new_password" class="form-control" placeholder="Minimal 6 karakter" required>
            </div>
            <div class="mb-4">
                <label class="form-label small fw-bold">Konfirmasi Password Baru</label>
                <input type="password" id="confirmPassword" name="confirm_password" class="form-control" placeholder="Ulangi password baru" required>
            </div>
            <button type="submit" id="resetBtn" name="reset_password" class="btn btn-primary w-100">
                Reset Password
            </button>
        </form>
        <div class="text-center mt-3">
            <a href="login.php">Kembali ke halaman login</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/reset_password.js"></script>
</body>
</html>
