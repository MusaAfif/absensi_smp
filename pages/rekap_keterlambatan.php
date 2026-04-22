<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/attendance_logic.php';
cek_login();

// Inisialisasi session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$attendanceLogic = new AttendanceLogic($conn);

// Ambil parameter filter
$filter_tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');
$filter_type = $_GET['type'] ?? 'hari'; // hari, bulan, semester

// Ambil data rekap berdasarkan filter
if ($filter_type === 'hari') {
    $rekap_data = $attendanceLogic->getRekapKeterlambatanHarian($filter_tanggal);
    $title = "Rekap Keterlambatan - " . date('d/m/Y', strtotime($filter_tanggal));
} elseif ($filter_type === 'bulan') {
    $rekap_data = $attendanceLogic->getRekapKeterlambatanBulanan($filter_bulan, $filter_tahun);
    $title = "Rekap Keterlambatan - " . date('F Y', strtotime("$filter_tahun-$filter_bulan-01"));
} else { // semester
    $semester = $filter_bulan <= 6 ? 1 : 2;
    $rekap_data = $attendanceLogic->getRekapKeterlambatanSemester($semester, $filter_tahun);
    $title = "Rekap Keterlambatan - Semester $semester Tahun $filter_tahun";
}

// Urutkan berdasarkan jumlah keterlambatan (descending)
usort($rekap_data, function($a, $b) {
    return $b['total_terlambat'] <=> $a['total_terlambat'];
});
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Keterlambatan | db_absensi_smp</title>
    <link href="../assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='../assets/css/site.css' rel='stylesheet'>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .rekap-card {
            transition: transform 0.2s;
        }
        .rekap-card:hover {
            transform: translateY(-2px);
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .ranking-badge {
            position: absolute;
            top: -10px;
            left: -10px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        .ranking-1 { background: #ffd700; color: #000; }
        .ranking-2 { background: #c0c0c0; color: #000; }
        .ranking-3 { background: #cd7f32; color: #000; }
        .ranking-other { background: #6c757d; color: #fff; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0"><i class="fas fa-chart-line me-2 text-warning"></i>Rekap Keterlambatan</h2>
            <p class="text-muted mb-0">Monitoring dan analisis keterlambatan siswa</p>
        </div>
        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-outline-primary">
                <i class="fas fa-print me-2"></i>CETAK
            </button>
            <button onclick="exportToExcel()" class="btn btn-success">
                <i class="fas fa-file-excel me-2"></i>EXPORT EXCEL
            </button>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tipe Rekap</label>
                    <select name="type" class="form-select" onchange="toggleFilters()">
                        <option value="hari" <?= $filter_type === 'hari' ? 'selected' : '' ?>>Per Hari</option>
                        <option value="bulan" <?= $filter_type === 'bulan' ? 'selected' : '' ?>>Per Bulan</option>
                        <option value="semester" <?= $filter_type === 'semester' ? 'selected' : '' ?>>Per Semester</option>
                    </select>
                </div>
                <div class="col-md-3" id="tanggal-filter" style="display: <?= $filter_type === 'hari' ? 'block' : 'none' ?>">
                    <label class="form-label">Tanggal</label>
                    <input type="date" name="tanggal" class="form-control" value="<?= $filter_tanggal ?>">
                </div>
                <div class="col-md-3" id="bulan-filter" style="display: <?= in_array($filter_type, ['bulan', 'semester']) ? 'block' : 'none' ?>">
                    <label class="form-label">Bulan</label>
                    <select name="bulan" class="form-select">
                        <?php for($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= $filter_bulan == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                                <?= date('F', strtotime("2024-$i-01")) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tahun</label>
                    <select name="tahun" class="form-select">
                        <?php for($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
                            <option value="<?= $i ?>" <?= $filter_tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>FILTER
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-0">Total Siswa</h6>
                            <h3 class="mb-0"><?= count($rekap_data) ?></h3>
                        </div>
                        <i class="fas fa-users fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-0">Total Keterlambatan</h6>
                            <h3 class="mb-0"><?= array_sum(array_column($rekap_data, 'total_terlambat')) ?></h3>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-0">Rata-rata/Minggu</h6>
                            <h3 class="mb-0">
                                <?php
                                $total_terlambat = array_sum(array_column($rekap_data, 'total_terlambat'));
                                $avg_weekly = $filter_type === 'hari' ? $total_terlambat :
                                             ($filter_type === 'bulan' ? round($total_terlambat / 4, 1) :
                                             round($total_terlambat / 17, 1));
                                echo $avg_weekly;
                                ?>
                            </h3>
                        </div>
                        <i class="fas fa-chart-bar fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-0">Siswa Terlambat</h6>
                            <h3 class="mb-0">
                                <?= count(array_filter($rekap_data, function($siswa) { return $siswa['total_terlambat'] > 0; })) ?>
                            </h3>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rekap Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list-ol me-2"></i><?= $title ?></h5>
        </div>
        <div class="card-body">
            <?php if (empty($rekap_data)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada data keterlambatan</h5>
                    <p class="text-muted">Belum ada siswa yang terlambat dalam periode ini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="rekap-table">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>NISN</th>
                                <th>Nama Siswa</th>
                                <th>Kelas</th>
                                <th>Total Terlambat</th>
                                <th>Rata-rata/Hari</th>
                                <th>Persentase</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rekap_data as $index => $siswa): ?>
                                <tr>
                                    <td>
                                        <strong><?= $index + 1 ?></strong>
                                        <?php if ($index < 3): ?>
                                            <span class="badge ranking-badge ranking-<?= $index + 1 ?>">
                                                <?= $index + 1 ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= $siswa['nisn'] ?></code></td>
                                    <td>
                                        <strong><?= htmlspecialchars($siswa['nama_lengkap']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($siswa['nama_kelas'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning text-dark fs-6">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= $siswa['total_terlambat'] ?> kali
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $avg_daily = $filter_type === 'hari' ? $siswa['total_terlambat'] :
                                                   ($filter_type === 'bulan' ? round($siswa['total_terlambat'] / date('t', strtotime("$filter_tahun-$filter_bulan-01")), 1) :
                                                   round($siswa['total_terlambat'] / 17, 1));
                                        ?>
                                        <span class="text-muted"><?= $avg_daily ?>/hari</span>
                                    </td>
                                    <td>
                                        <?php
                                        $total_hari = $filter_type === 'hari' ? 1 :
                                                    ($filter_type === 'bulan' ? date('t', strtotime("$filter_tahun-$filter_bulan-01")) : 17);
                                        $persentase = round(($siswa['total_terlambat'] / $total_hari) * 100, 1);
                                        $color_class = $persentase >= 50 ? 'bg-danger' : ($persentase >= 25 ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?= $color_class ?>" role="progressbar"
                                                 style="width: <?= min($persentase, 100) ?>%">
                                                <?= $persentase ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($siswa['total_terlambat'] == 0): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i>Disiplin
                                            </span>
                                        <?php elseif ($siswa['total_terlambat'] <= 3): ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-exclamation-triangle me-1"></i>Perlu Perhatian
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times-circle me-1"></i>Butuh Pembinaan
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleFilters() {
    const type = document.querySelector('select[name="type"]').value;
    document.getElementById('tanggal-filter').style.display = type === 'hari' ? 'block' : 'none';
    document.getElementById('bulan-filter').style.display = type === 'bulan' || type === 'semester' ? 'block' : 'none';
}

function exportToExcel() {
    // Simple CSV export (could be enhanced with proper Excel library)
    const table = document.getElementById('rekap-table');
    let csv = [];

    // Get headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    csv.push(headers.join(','));

    // Get data rows
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            // Remove HTML tags and extra spaces
            row.push(td.textContent.replace(/<[^>]*>/g, '').trim());
        });
        csv.push(row.join(','));
    });

    // Download CSV
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'rekap_keterlambatan_<?= date('Y-m-d') ?>.csv';
    link.click();
}
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>