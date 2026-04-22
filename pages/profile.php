<?php 
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/DatabaseHelper.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
cek_login();

// Initialize CSRF protection
CSRFProtection::init();
$dbHelper = new DatabaseHelper($conn);

$msg = "";
if(isset($_POST['update_profile'])) {
    // Verify CSRF token
    if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger shadow-sm'>CSRF token tidak valid. Silakan coba lagi.</div>";
    } else {
        $user = $_SESSION['admin'] ?? '';
        $nama = SecurityHelper::sanitizeInput($_POST['nama_admin'] ?? '');
        $pass_baru = $_POST['pass_baru'] ?? '';
        $konfirmasi = $_POST['konfirmasi'] ?? '';

        // Validasi input
        if (empty($nama)) {
            $msg = "<div class='alert alert-warning shadow-sm'>Nama admin tidak boleh kosong!</div>";
        } else {
            // Update Nama Admin dengan prepared statement
            $result = $dbHelper->update(
                "UPDATE users SET nama_admin = ? WHERE username = ?",
                [$nama, $user],
                'ss'
            );
            
            if ($result) {
                $_SESSION['nama_admin'] = $nama;
                
                // Jika password diisi, lakukan update password
                if(!empty($pass_baru)) {
                    if($pass_baru === $konfirmasi) {
                        // Gunakan bcrypt untuk password hashing
                        $hashed_password = SecurityHelper::hashPassword($pass_baru);
                        $result = $dbHelper->update(
                            "UPDATE users SET password = ? WHERE username = ?",
                            [$hashed_password, $user],
                            'ss'
                        );
                        
                        if ($result) {
                            $msg = "<div class='alert alert-success shadow-sm'>Profil dan Password berhasil diperbarui!</div>";
                        } else {
                            $msg = "<div class='alert alert-danger shadow-sm'>Gagal memperbarui password!</div>";
                        }
                    } else {
                        $msg = "<div class='alert alert-danger shadow-sm'>Konfirmasi password tidak cocok!</div>";
                    }
                } else {
                    $msg = "<div class='alert alert-success shadow-sm'>Nama admin berhasil diperbarui!</div>";
                }
            } else {
                $msg = "<div class='alert alert-danger shadow-sm'>Gagal memperbarui profil!</div>";
            }
        }
    }
}

// Ambil data admin terbaru
$user_now = $_SESSION['admin'] ?? '';
$data_admin_result = $dbHelper->selectOne(
    "SELECT * FROM users WHERE username = ? LIMIT 1",
    [$user_now],
    's'
);
$data_admin = $data_admin_result ?? [];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Profil Admin | E-Absensi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='../assets/css/site.css' rel='stylesheet'>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">E-ABSENSI</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link text-danger fw-bold" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-4"><i class="fas fa-user-circle me-2"></i>Pengaturan Akun</h4>
                        <?= $msg; ?>
                        
                        <form method="POST">
                            <!-- CSRF Token -->
                            <?= CSRFProtection::getTokenField() ?>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Username (Tetap)</label>
                                <input type="text" class="form-control bg-light" value="<?= $data_admin['username']; ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Nama Admin</label>
                                <input type="text" name="nama_admin" class="form-control" value="<?= $data_admin['nama_admin']; ?>" required>
                            </div>
                            <hr class="my-4">
                            <p class="text-muted small">Kosongkan kolom password di bawah jika tidak ingin mengubah password.</p>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Password Baru</label>
                                <input type="password" name="pass_baru" class="form-control" placeholder="Isi jika ingin ganti">
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold small">Konfirmasi Password Baru</label>
                                <input type="password" name="konfirmasi" class="form-control" placeholder="Ulangi password baru">
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">SIMPAN PERUBAHAN</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php include '../includes/footer.php'; ?>
</body>
</html>

