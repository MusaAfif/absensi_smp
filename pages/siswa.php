<?php 
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/DatabaseHelper.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
// Proteksi halaman
cek_login();

// Initialize CSRF protection
CSRFProtection::init();
$dbHelper = new DatabaseHelper($conn);

function siswaColumnExists($conn, $columnName) {
    $columnName = mysqli_real_escape_string($conn, $columnName);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM siswa LIKE '$columnName'");
    return $result && mysqli_num_rows($result) > 0;
}

$searchQuery = isset($_GET['q']) ? SecurityHelper::sanitizeInput($_GET['q']) : '';
$statusColumnExists = siswaColumnExists($conn, 'status_siswa');
$bulkResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    // Verify CSRF token
    if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
        $bulkResult = ['type' => 'danger', 'message' => 'CSRF token tidak valid. Silakan coba lagi.'];
    } else {
        // Sanitize and validate input
        $selectedIds = [];
        if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
            foreach ($_POST['selected_ids'] as $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $selectedIds[] = $id;
                }
            }
        }
        
        $action = SecurityHelper::sanitizeInput($_POST['bulk_action'] ?? '');
        
        if (empty($selectedIds)) {
            $bulkResult = ['type' => 'warning', 'message' => 'Pilih minimal satu siswa terlebih dahulu.'];
        } else {
            // Use parameterized query for bulk actions
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $types = str_repeat('i', count($selectedIds));
            
            if ($action === 'hapus') {
                if ($statusColumnExists) {
                    $result = $dbHelper->update(
                        "UPDATE siswa SET status_siswa = 'hapus' WHERE id_siswa IN ($placeholders)",
                        $selectedIds,
                        $types
                    );
                    $bulkResult = ['type' => 'success', 'message' => 'Siswa terpilih ditandai sebagai dihapus.'];
                } else {
                    $result = $dbHelper->delete(
                        "DELETE FROM siswa WHERE id_siswa IN ($placeholders)",
                        $selectedIds,
                        $types
                    );
                    $bulkResult = ['type' => 'success', 'message' => 'Siswa terpilih berhasil dihapus.'];
                }
            } elseif ($action === 'lulus' && $statusColumnExists) {
                $result = $dbHelper->update(
                    "UPDATE siswa SET status_siswa = 'lulus' WHERE id_siswa IN ($placeholders)",
                    $selectedIds,
                    $types
                );
                $bulkResult = ['type' => 'success', 'message' => 'Siswa terpilih ditandai lulus.'];
            } elseif ($action === 'pindah' && $statusColumnExists) {
                $result = $dbHelper->update(
                    "UPDATE siswa SET status_siswa = 'pindah' WHERE id_siswa IN ($placeholders)",
                    $selectedIds,
                    $types
                );
                $bulkResult = ['type' => 'success', 'message' => 'Siswa terpilih ditandai pindah.'];
            } elseif ($action === 'aktif' && $statusColumnExists) {
                $result = $dbHelper->update(
                    "UPDATE siswa SET status_siswa = 'aktif' WHERE id_siswa IN ($placeholders)",
                    $selectedIds,
                    $types
                );
                $bulkResult = ['type' => 'success', 'message' => 'Siswa terpilih ditandai aktif.'];
            } else {
                $bulkResult = ['type' => 'danger', 'message' => 'Aksi tidak valid atau belum didukung oleh database.'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Siswa| SMPN 1 Indonesia</title>
    <link href="../assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='../assets/css/site.css' rel='stylesheet'>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .card-siswa { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); background: white; }
        .table thead th { 
            background: #fdfdfd; color: #7f8c8d; font-size: 11px; 
            text-transform: uppercase; padding: 15px 10px; border-bottom: 2px solid #f1f1f1;
        }
        .img-profile { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; }
        .nis-text { font-weight: 700; color: #2c3e50; }
        .nama-text { font-weight: 600; color: #34495e; }
        .badge-kelas { background: #ebf5ff; color: #007bff; font-weight: 700; padding: 5px 10px; border-radius: 4px; font-size: 12px; }
        .btn-group .btn { padding: 5px 10px; font-size: 12px; font-weight: 600; }
        
        /* Search bar styling */
        .search-bar-container {
            background: white;
            border-radius: 10px;
            padding: 16px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .search-bar-container .form-control {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 10px 14px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        .search-bar-container .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        .search-bar-container .btn {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        
        /* Bulk action styling */
        .bulk-action-container {
            background: white;
            border-radius: 10px;
            padding: 12px 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .bulk-action-container .form-select {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 8px 12px;
            font-size: 0.90rem;
            transition: all 0.2s ease;
        }
        .bulk-action-container .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        .bulk-action-help {
            font-size: 0.85rem;
            color: #666;
            margin-left: 10px;
        }
        
        /* Action buttons styling */
        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .action-buttons .btn {
            padding: 6px 10px;
            font-size: 0.82rem;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .action-buttons .btn-edit {
            background: #e7f3ff;
            color: #0066cc;
            border: none;
        }
        .action-buttons .btn-edit:hover {
            background: #cce5ff;
            color: #003d99;
        }
        .action-buttons .btn-print {
            background: #f0f0f0;
            color: #333;
            border: none;
        }
        .action-buttons .btn-print:hover {
            background: #e0e0e0;
            color: #000;
        }
        .action-buttons .btn-delete {
            background: #ffe7e7;
            color: #cc0000;
            border: none;
        }
        .action-buttons .btn-delete:hover {
            background: #ffcccc;
            color: #990000;
        }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="container-fluid px-4 py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Manajemen Siswa</h2>
            <p class="text-muted mb-0">Manajemen data akademik </p>
        </div>
        <div class="d-flex gap-2">
            <a href="rfid_registrasi.php" class="btn btn-outline-dark fw-bold">REGISTRASI RFID</a>
            <a href="cetak_massal.php" class="btn btn-dark fw-bold">CETAK MASSAL</a>
            <a href="naik_kelas_massal.php" class="btn btn-outline-success fw-bold">NAIK KELAS MASSAL</a>
            <a href="import_siswa.php" class="btn btn-outline-primary fw-bold">IMPORT DATA</a> 
            <a href="tambah_data_siswa.php" class="btn btn-primary fw-bold">TAMBAH SISWA</a>
        </div>
    </div>

    <?php if ($bulkResult): ?>
        <div class="alert alert-<?= $bulkResult['type']; ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($bulkResult['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="search-bar-container">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-8 col-lg-9">
                <input type="search" name="q" value="<?= htmlspecialchars($searchQuery); ?>" class="form-control" placeholder="🔍 Cari NIS, NISN, Nama, atau Kelas...">
            </div>
            <div class="col-6 col-md-2 col-lg-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Cari</button>
            </div>
            <div class="col-6 col-md-2 col-lg-1">
                <a href="siswa.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>

    <form method="POST">
        <!-- CSRF Token -->
        <?= CSRFProtection::getTokenField() ?>
        
        <div class="bulk-action-container">
            <div class="d-flex flex-wrap gap-3 align-items-center">
                <div style="min-width: 280px;">
                    <select name="bulk_action" class="form-select">
                        <option value="">📋 Pilih aksi massal...</option>
                        <option value="hapus">🗑️ Hapus Terpilih</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success" style="padding: 8px 20px;"><i class="fas fa-check me-2"></i>Jalankan</button>
                <span class="bulk-action-help">Pilih siswa di tabel, lalu pilih aksi</span>
            </div>
        </div>
        <div class="card card-siswa mb-4">
            <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-center" width="40"><input type="checkbox" id="select_all"></th>
                        <th class="ps-4" width="80">Foto</th>
                        <th width="100">NIS</th>
                        <th width="100">NISN</th>
                        <th width="220">Nama Lengkap</th>
                        <th width="210">UUID Permanen</th>
                        <th width="50" class="text-center">JK</th>
                        <th width="100" class="text-center">Kelas</th>
                        <th width="100" class="text-center">Status</th>
                        <th class="text-center" width="240">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Query mengambil data siswa dan nama kelas
                    $where = 'WHERE 1=1';
                    $params = [];
                    $types = '';
                    if ($statusColumnExists) {
                        $where .= " AND (s.status_siswa IS NULL OR s.status_siswa != 'hapus')";
                    }
                    if ($searchQuery !== '') {
                        $where .= " AND (s.nis LIKE ? OR s.nisn LIKE ? OR s.nama_lengkap LIKE ? OR k.nama_kelas LIKE ?)";
                        $searchLike = '%' . $searchQuery . '%';
                        $params = [$searchLike, $searchLike, $searchLike, $searchLike];
                        $types = 'ssss';
                    }
                    $querySql = "SELECT s.*, k.nama_kelas FROM siswa s 
                                 LEFT JOIN kelas k ON s.id_kelas = k.id_kelas 
                                 $where
                                 ORDER BY s.nama_lengkap ASC";
                    $stmt = mysqli_prepare($conn, $querySql);
                    if (!empty($params)) {
                        mysqli_stmt_bind_param($stmt, $types, ...$params);
                    }
                    mysqli_stmt_execute($stmt);
                    $query = mysqli_stmt_get_result($stmt);
                    while($r = mysqli_fetch_assoc($query)): 
                        $statusLabel = 'Aktif';
                        $badgeClass = 'bg-success text-white';
                        if (!empty($r['status_siswa']) && $r['status_siswa'] !== 'aktif') {
                            $statusLabel = ucfirst($r['status_siswa']);
                            $badgeClass = $r['status_siswa'] === 'lulus' ? 'bg-primary text-white' : ($r['status_siswa'] === 'pindah' ? 'bg-warning text-dark' : 'bg-secondary text-white');
                        }
                    ?>
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="row-checkbox" name="selected_ids[]" value="<?= $r['id_siswa']; ?>">
                        </td>
                        <td class="ps-4">
                            <img src="../assets/img/siswa/<?= $r['foto'] ?: 'default.png'; ?>" class="img-profile">
                        </td>
                        <td class="nis-text"><?= $r['nis']; ?></td>
                        <td class="text-muted"><?= $r['nisn'] ?: '-'; ?></td>
                        <td class="nama-text text-uppercase"><?= $r['nama_lengkap']; ?></td>
                        <td><code style="font-size:0.75rem;"><?= htmlspecialchars($r['student_uuid'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></code></td>
                        <td class="text-center fw-bold"><?= $r['jk']; ?></td>
                        <td class="text-center"><span class="badge-kelas"><?= $r['nama_kelas']; ?></span></td>
                        <td class="text-center"><span class="badge <?= $badgeClass; ?> py-2 px-3 rounded-pill" style="font-size:0.78rem;"><?= $statusLabel; ?></span></td>
                        <td class="text-center">
                            <div class="action-buttons">
                                <a href="edit_siswa.php?id=<?= $r['id_siswa']; ?>" class="btn btn-edit" title="Edit Data"><i class="fas fa-edit"></i></a>
                                <a href="cetak_kartu.php?id=<?= $r['id_siswa']; ?>" target="_blank" class="btn btn-print" title="Cetak Kartu"><i class="fas fa-print"></i></a>
                                <a href="siswa.php?hapus=<?= $r['id_siswa']; ?>" class="btn btn-delete" title="Hapus" onclick="return confirm('Hapus siswa ini?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php mysqli_stmt_close($stmt); ?>
                </tbody>
            </table>
        </div>
    </div>
    </form>
</div>

<script src="../assets/vendor/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('select_all')?.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>

