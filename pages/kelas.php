<?php 
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/DatabaseHelper.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
cek_login();

// Initialize CSRF protection
CSRFProtection::init();
$dbHelper = new DatabaseHelper($conn);

// 1. PROSES TAMBAH KELAS
if (isset($_POST['tambah_kelas'])) {
    // Verify CSRF token
    if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'CSRF token tidak valid. Silakan coba lagi.';
    } else {
        $nama_kelas = SecurityHelper::sanitizeInput($_POST['nama_kelas'] ?? '');
        
        if (empty($nama_kelas)) {
            $_SESSION['error'] = 'Nama kelas tidak boleh kosong!';
        } else {
            $result = $dbHelper->insert(
                "INSERT INTO kelas (nama_kelas) VALUES (?)",
                [$nama_kelas],
                's'
            );
            
            if ($result) {
                safe_redirect('kelas', ['status' => 'success']);
                exit;
            } else {
                $_SESSION['error'] = 'Gagal menambah kelas: ' . $dbHelper->getLastError();
            }
        }
    }
}

// 2. PROSES HAPUS KELAS
if (isset($_GET['hapus'])) {
    // Verify CSRF token from referrer or special delete parameter
    if (!isset($_GET['csrf_token']) || !CSRFProtection::verifyToken($_GET['csrf_token'])) {
        $_SESSION['error'] = 'CSRF token tidak valid untuk penghapusan.';
    } else {
        $id = SecurityHelper::sanitizeInput($_GET['hapus'] ?? '');
        
        if (!SecurityHelper::validateInteger($id)) {
            $_SESSION['error'] = 'ID kelas tidak valid!';
        } else {
            $result = $dbHelper->delete(
                "DELETE FROM kelas WHERE id_kelas = ?",
                [$id],
                'i'
            );
            
            if ($result) {
                safe_redirect('kelas', ['status' => 'deleted']);
                exit;
            } else {
                $_SESSION['error'] = 'Gagal menghapus kelas: ' . $dbHelper->getLastError();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Kelas | SMPN 1 Indonesia</title>
    <link href="../assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='../assets/css/site.css' rel='stylesheet'>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .btn-primary { background: #1a237e; border: none; }
        .btn-primary:hover { background: #0d144d; }
        .table thead { background: #f8f9fa; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }
        .school-header { border-left: 5px solid #1a237e; padding-left: 15px; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="container py-4">
    <!-- Error Message -->
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= SecurityHelper::escapeHTML($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <!-- Status Messages -->
    <?php if(isset($_GET['status'])): ?>
        <?php if($_GET['status'] === 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>Kelas berhasil ditambahkan!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif($_GET['status'] === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>Kelas berhasil dihapus!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4 school-header">
        <div>
            <h2 class="fw-bold mb-0 text-dark">Manajemen Kelas</h2>
            <p class="text-muted small mb-0">Kelola data rombongan belajar SMP NEGERI 1 INDONESIA.</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary fw-bold px-4">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-plus-circle text-primary me-2"></i>Tambah Kelas Baru</h5>
                <form action="" method="POST">
                    <!-- CSRF Token -->
                    <?= CSRFProtection::getTokenField() ?>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Kelas</label>
                        <input type="text" name="nama_kelas" class="form-control" placeholder="Contoh: VII-A" required>
                    </div>
                    <button type="submit" name="tambah_kelas" class="btn btn-primary w-100 fw-bold py-2">
                        SIMPAN KELAS
                    </button>
                </form>
            </div>
            
            <div class="card mt-4 p-3 bg-light border-0">
                <div class="d-flex align-items-center">
                    <i class="fas fa-info-circle fa-2x text-primary me-3 opacity-50"></i>
                    <p class="small mb-0 text-muted">Pastikan nama kelas sesuai dengan data Dapodik sekolah untuk sinkronisasi yang tepat.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4 py-3" width="10%">ID</th>
                                <th width="65%">Nama Kelas</th>
                                <th class="text-center" width="25%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $klasList = $dbHelper->select(
                                "SELECT * FROM kelas ORDER BY id_kelas ASC",
                                [],
                                ''
                            );
                            
                            if(!empty($klasList)):
                                foreach($klasList as $r): 
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted"><?= SecurityHelper::escapeHTML($r['id_kelas']) ?></td>
                                <td class="fw-bold text-dark"><?= SecurityHelper::escapeHTML($r['nama_kelas']) ?></td>
                                <td class="text-center">
                                    <a href="kelas.php?hapus=<?= (int)$r['id_kelas'] ?>&csrf_token=<?= urlencode(CSRFProtection::getToken()) ?>" 
                                       class="btn btn-sm btn-outline-danger px-3 fw-bold" 
                                       onclick="return confirm('Hapus kelas ini? Semua siswa di kelas ini akan kehilangan relasi kelasnya.')">
                                        <i class="fas fa-trash-alt me-1"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="3" class="text-center py-5 text-muted small">Belum ada data kelas yang terdaftar.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<?php include '../includes/footer.php'; ?>
</body>
</html>

