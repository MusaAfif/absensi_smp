<?php 
require_once __DIR__ . '/../includes/config.php'; 
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
cek_login(); 

CSRFProtection::init();

// Fitur Download Template Langsung (tanpa file fisik di server)
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=template_import_siswa_' . date('Ymd') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, "\xEF\xBB\xBF");
    fputcsv($output, array('nis', 'nisn', 'nama_lengkap', 'jk', 'nama_kelas', 'tahun_ajaran', 'status'));
    fputcsv($output, array('2024001', '1234567890', 'BUDI SANTOSO', 'L', '7A', '2024/2025', 'aktif'));
    fputcsv($output, array('2024002', '1234567891', 'SRI ASTUTIK', 'P', '7A', '2024/2025', 'aktif'));
    fputcsv($output, array('2024003', '1234567892', 'AHMAD RIYANTO', 'L', '7B', '2024/2025', 'aktif'));
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Import Data Siswa | SMPN 1 Indonesia</title>
    <link href="../assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='../assets/css/site.css' rel='stylesheet'>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .card-import { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .template-box { background: #eef2f7; border-radius: 8px; padding: 15px; border-left: 5px solid #0d6efd; }
        .result-box { border-radius: 10px; padding: 16px; margin-bottom: 20px; }
        .result-stats { display: flex; gap: 15px; flex-wrap: wrap; }
        .stat-item { flex: 1; min-width: 120px; text-align: center; padding: 12px; background: #f5f5f5; border-radius: 8px; }
        .stat-value { font-size: 24px; font-weight: 700; margin-bottom: 5px; }
        .stat-label { font-size: 12px; color: #666; text-transform: uppercase; }
        .error-list { max-height: 200px; overflow-y: auto; background: #fff; border: 1px solid #f5d5d5; border-radius: 6px; padding: 10px; }
        .error-item { padding: 6px; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem; color: #666; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card card-import p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-bold mb-0">Import Data Siswa</h4>
                    <a href="siswa.php" class="btn btn-light btn-sm">Kembali</a>
                </div>

                <?php if (isset($_SESSION['import_success']) && $_SESSION['import_success']): 
                    $stats = $_SESSION['import_stats'] ?? [];
                    $errors = $_SESSION['import_errors'] ?? [];
                    $logFile = $_SESSION['import_log'] ?? '';
                ?>
                    <div class="result-box bg-light border border-success">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-check-circle text-success" style="font-size:24px; margin-right:10px;"></i>
                            <h5 class="mb-0 text-success">Import Berhasil!</h5>
                        </div>
                        <div class="result-stats">
                            <div class="stat-item" style="background: #d4edda;">
                                <div class="stat-value text-success"><?= $stats['success'] ?? 0; ?></div>
                                <div class="stat-label">Berhasil</div>
                            </div>
                            <div class="stat-item" style="background: #cfe2ff;">
                                <div class="stat-value text-info"><?= $stats['updated'] ?? 0; ?></div>
                                <div class="stat-label">Diperbarui</div>
                            </div>
                            <div class="stat-item" style="background: #fff3cd;">
                                <div class="stat-value text-warning"><?= $stats['skipped'] ?? 0; ?></div>
                                <div class="stat-label">Di-Skip</div>
                            </div>
                            <div class="stat-item" style="background: #f8d7da;">
                                <div class="stat-value text-danger"><?= $stats['failed'] ?? 0; ?></div>
                                <div class="stat-label">Gagal</div>
                            </div>
                        </div>
                        <?php if (!empty($errors) && count($errors) > 0): ?>
                            <div class="mt-3">
                                <h6 class="fw-bold text-danger">Detail Error (<?= count($errors); ?>):</h6>
                                <div class="error-list">
                                    <?php foreach (array_slice($errors, 0, 20) as $error): ?>
                                        <div class="error-item"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error); ?></div>
                                    <?php endforeach; ?>
                                    <?php if (count($errors) > 20): ?>
                                        <div class="error-item text-center text-muted small">... dan <?= count($errors) - 20; ?> error lainnya (lihat log)</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($logFile && file_exists($logFile)): ?>
                            <div class="mt-3">
                                <a href="<?= htmlspecialchars($logFile); ?>" class="btn btn-sm btn-outline-secondary" download>
                                    <i class="fas fa-download me-1"></i> Download Log File
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="text-center">
                        <a href="siswa.php" class="btn btn-primary">Lihat Data Siswa</a>
                        <a href="import_siswa.php" class="btn btn-outline-primary">Import Ulang</a>
                    </div>
                    <?php unset($_SESSION['import_success'], $_SESSION['import_stats'], $_SESSION['import_errors'], $_SESSION['import_log']); ?>
                <?php elseif (isset($_SESSION['import_error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Error:</strong> <?= htmlspecialchars($_SESSION['import_error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['import_error']); ?>
                <?php elseif (isset($_SESSION['import_errors']) && !empty($_SESSION['import_errors'])): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Validasi File Gagal:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($_SESSION['import_errors'] as $error): ?>
                                <li><?= htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['import_errors']); ?>
                <?php else: ?>

                <div class="template-box mb-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-1">Belum punya formatnya?</h6>
                            <p class="small text-muted mb-0">Download template CSV agar urutan kolom sesuai dengan sistem.</p>
                        </div>
                        <a href="?download_template=true" class="btn btn-success btn-sm fw-bold">
                            <i class="fas fa-download me-1"></i> DOWNLOAD TEMPLATE
                        </a>
                    </div>
                </div>
                
                <form action="<?= BASE_URL ?>pages/import_proses.php" method="POST" enctype="multipart/form-data">
                    <?= CSRFProtection::getTokenField() ?>
                    <div class="mb-4">
                        <label class="form-label fw-bold small">PILIH FILE CSV</label>
                        <input type="file" name="file_siswa" class="form-control" accept=".csv" required>
                        <div class="form-text mt-2" style="font-size: 0.75rem;">
                            <i class="fas fa-info-circle me-1"></i> Pastikan file menggunakan format <b>.csv</b> dengan header: <b>nis, nisn, nama_lengkap, jk, nama_kelas, tahun_ajaran, status</b>.
                        </div>
                    </div>
                    
                    <button type="submit" name="import" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">
                        <i class="fas fa-upload me-2"></i>MULAI PROSES IMPORT
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<?php include '../includes/footer.php'; ?>
</body>
</html>

