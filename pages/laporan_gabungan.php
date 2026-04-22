<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/attendance_logic.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/DatabaseHelper.php';
cek_login();

// Inisialisasi session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize helpers
$attendanceLogic = new AttendanceLogic($conn);
$dbHelper = new DatabaseHelper($conn);
$security = new SecurityHelper();

// Parameter filter dengan validasi
$tgl_mulai = SecurityHelper::sanitizeInput($_GET['tgl_mulai'] ?? date('Y-m-01'));
$tgl_selesai = SecurityHelper::sanitizeInput($_GET['tgl_selesai'] ?? date('Y-m-d'));
$id_kelas = SecurityHelper::sanitizeInput($_GET['id_kelas'] ?? '');

// Validasi tanggal
if (!SecurityHelper::validateSQLDate($tgl_mulai)) {
    $tgl_mulai = date('Y-m-01');
}
if (!SecurityHelper::validateSQLDate($tgl_selesai)) {
    $tgl_selesai = date('Y-m-d');
}
if ($id_kelas !== '' && !SecurityHelper::validateID($id_kelas)) {
    $id_kelas = '';
}

// Parameter filter untuk rekap keterlambatan
$filter_tanggal = SecurityHelper::sanitizeInput($_GET['tanggal'] ?? date('Y-m-d'));
$filter_bulan = SecurityHelper::sanitizeInput($_GET['bulan'] ?? date('m'));
$filter_tahun = SecurityHelper::sanitizeInput($_GET['tahun'] ?? date('Y'));
$filter_type = SecurityHelper::sanitizeInput($_GET['type'] ?? 'bulan');

// Validasi filter
if (!SecurityHelper::validateSQLDate($filter_tanggal)) {
    $filter_tanggal = date('Y-m-d');
}
if (!preg_match('/^\d{2}$/', $filter_bulan) || $filter_bulan < 1 || $filter_bulan > 12) {
    $filter_bulan = date('m');
}
if (!preg_match('/^\d{4}$/', $filter_tahun) || $filter_tahun < 2000 || $filter_tahun > 2100) {
    $filter_tahun = date('Y');
}
if (!in_array($filter_type, ['hari', 'bulan', 'semester'])) {
    $filter_type = 'bulan';
}

// Query laporan absensi dengan prepared statement
$kelas_label = '';
$sql_absensi = "SELECT a.*, s.nis, s.nama_lengkap, k.nama_kelas, st.nama_status,
                       a.status_presensi, a.keterlambatan_menit, a.waktu_absen
                FROM absensi a
                JOIN siswa s ON a.id_siswa = s.id_siswa
                JOIN kelas k ON s.id_kelas = k.id_kelas
                JOIN status_absen st ON a.id_status = st.id_status
                WHERE a.tanggal BETWEEN ? AND ?";

$params = [$tgl_mulai, $tgl_selesai];
$types = 'ss';

if ($id_kelas !== '') {
    $sql_absensi .= " AND s.id_kelas = ?";
    $params[] = $id_kelas;
    $types .= 'i';
    
    // Get kelas nama dengan prepared statement
    $kelas_result = $dbHelper->selectOne("SELECT nama_kelas FROM kelas WHERE id_kelas = ?", [$id_kelas], 'i');
    $kelas_label = $kelas_result['nama_kelas'] ?? '';
}

$sql_absensi .= " ORDER BY a.tanggal DESC, a.waktu_absen DESC";

// Execute query
$data_absensi_result = $dbHelper->select($sql_absensi, $params, $types);
if ($data_absensi_result === false) {
    $data_absensi_result = [];
}

// Query rekap keterlambatan
if ($filter_type === 'hari') {
    $rekap_data = $attendanceLogic->getRekapKeterlambatanHarian($filter_tanggal);
    $title_rekap = "Rekap Keterlambatan - " . date('d/m/Y', strtotime($filter_tanggal));
} elseif ($filter_type === 'bulan') {
    $rekap_data = $attendanceLogic->getRekapKeterlambatanBulanan($filter_bulan, $filter_tahun);
    $title_rekap = "Rekap Keterlambatan - " . date('F Y', strtotime("$filter_tahun-$filter_bulan-01"));
} else { // semester
    $semester = $filter_bulan <= 6 ? 1 : 2;
    $rekap_data = $attendanceLogic->getRekapKeterlambatanSemester($semester, $filter_tahun);
    $title_rekap = "Rekap Keterlambatan - Semester $semester Tahun $filter_tahun";
}

// Handle error pada rekap data
if ($rekap_data === false) {
    $rekap_data = [];
}

// Urutkan rekap berdasarkan jumlah keterlambatan
usort($rekap_data, function($a, $b) {
    return $b['total_terlambat'] <=> $a['total_terlambat'];
});

// Hitung statistik gabungan
$total_absensi = count($data_absensi_result);
$total_siswa_terlambat = count(array_filter($rekap_data, function($siswa) { return $siswa['total_terlambat'] > 0; }));
$total_keterlambatan = array_sum(array_column($rekap_data, 'total_terlambat'));

// Hitung statistik absensi
$stats_absensi = [
    'hadir' => 0,
    'sakit' => 0,
    'izin' => 0,
    'alfa' => 0,
    'tepat_waktu' => 0,
    'terlambat' => 0
];

foreach($data_absensi_result as $row) {
    $status = strtolower($row['nama_status']);
    if (isset($stats_absensi[$status])) {
        $stats_absensi[$status]++;
    }

    if ($row['status_presensi'] === 'tepat_waktu') {
        $stats_absensi['tepat_waktu']++;
    } elseif ($row['status_presensi'] === 'terlambat') {
        $stats_absensi['terlambat']++;
    }
}

$page_title = 'Laporan Absensi & Rekap Keterlambatan | E-Absensi SMP';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $page_title ?></title>
    <link href="../assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='../assets/css/site.css' rel='stylesheet'>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f8f9fa; }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            transition: transform 0.2s;
        }
        .stats-card:hover { transform: translateY(-2px); }
        .stats-card.success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stats-card.warning { background: linear-gradient(135deg, #fcb045 0%, #fd1d1d 100%); }
        .stats-card.info { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stats-card.danger { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); }

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

        .section-header {
            border-left: 5px solid #0d6efd;
            padding-left: 15px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #0d6efd;
        }

        .no-print { display: inline-block; }
        @media print {
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            body { background: #fff !important; color: #000 !important; font-size: 10pt !important; }
            .no-print, .app-navbar, nav.navbar, .site-footer, .btn, .btn-group,
            .form-control, .form-select, .stats-card { display: none !important; }
            .container, .container-fluid { max-width: 100% !important; width: 100% !important; padding: 0 4mm !important; margin: 0 !important; }
            .card { border: 1px solid #ccc !important; box-shadow: none !important; margin: 0 0 6px 0 !important; page-break-inside: avoid; }
            .card-header { padding: 4px 8px !important; }
            .card-body { padding: 0 !important; }
            .table { width: 100% !important; border-collapse: collapse !important; font-size: 8.5pt !important; }
            .table th, .table td { border: 1px solid #555 !important; padding: 3px 4px !important; vertical-align: middle !important; }
            .table thead th { background: #2c3e50 !important; color: #fff !important; font-size: 8pt !important; }
            .table-dark th { background: #2c3e50 !important; color: #fff !important; }
            .badge { border: 1px solid #666 !important; padding: 1px 4px !important; font-size: 7.5pt !important; border-radius: 2px !important; }
            .badge.bg-success { background: #28a745 !important; color: #fff !important; }
            .badge.bg-danger  { background: #dc3545 !important; color: #fff !important; }
            .badge.bg-warning { background: #ffc107 !important; color: #000 !important; }
            .badge.bg-secondary { background: #6c757d !important; color: #fff !important; }
            .progress { display: none !important; }
            .ranking-badge { display: none !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 8mm; }
            .print-header h2 { font-size: 13pt !important; font-weight: bold; margin-bottom: 2px; }
            .print-header p  { font-size: 9pt !important; margin: 1px 0; }
            .section-header { border-left: 3px solid #000 !important; padding-left: 6px !important; font-size: 10pt !important; }
            .page-break { page-break-after: always; }
            @page { size: A4 portrait; margin: 12mm 10mm; }
        }
    </style>
</head>
<body class="bg-light">

<?php include '../includes/navbar.php'; ?>

<div class="container py-4">
    <!-- Header -->
    <div class="print-header" style="display:none;">
        <div class="text-center mb-4">
            <h2 class="fw-bold mb-1">ABSENSI SMPN 1</h2>
            <p class="mb-1">Laporan Absensi & Rekap Keterlambatan</p>
            <p class="small text-muted">
                Periode Absensi: <?= date('d/m/Y', strtotime($tgl_mulai)) ?> - <?= date('d/m/Y', strtotime($tgl_selesai)) ?>
                <?= $kelas_label ? ' | Kelas: '.htmlspecialchars($kelas_label) : '' ?>
            </p>
            <p class="small text-muted">Rekap: <?= $title_rekap ?></p>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h2 class="fw-bold mb-0"><i class="fas fa-chart-line me-2 text-primary"></i>Laporan Absensi & Rekap Keterlambatan</h2>
            <p class="text-muted mb-0">Monitoring lengkap absensi dan keterlambatan siswa</p>
        </div>
        <div class="btn-group shadow-sm">
            <a href="export_excel.php?tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>&id_kelas=<?= $id_kelas ?>&include_rekap=1&type=<?= $filter_type ?>&tanggal=<?= $filter_tanggal ?>&bulan=<?= $filter_bulan ?>&tahun=<?= $filter_tahun ?>" class="btn btn-success fw-bold">
                <i class="fas fa-file-excel me-2"></i>Excel
            </a>
            <button onclick="window.print()" class="btn btn-dark fw-bold">
                <i class="fas fa-print me-2"></i>Cetak
            </button>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card p-3 shadow-sm border-0 mb-4 no-print">
        <form method="GET" class="row g-2">
            <div class="col-md-2">
                <label class="form-label fw-bold">Periode Absensi</label>
                <input type="date" name="tgl_mulai" class="form-control" value="<?= $tgl_mulai ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <input type="date" name="tgl_selesai" class="form-control" value="<?= $tgl_selesai ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Kelas</label>
                <select name="id_kelas" class="form-select">
                    <option value="">Semua Kelas</option>
                    <?php
                    $kelasList = $dbHelper->select("SELECT * FROM kelas ORDER BY nama_kelas ASC");
                    foreach ($kelasList as $rk): ?>
                        <option value="<?= $rk['id_kelas']; ?>" <?= $id_kelas === $rk['id_kelas'] ? 'selected' : ''; ?>><?= htmlspecialchars($rk['nama_kelas']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Tipe Rekap</label>
                <select name="type" class="form-select">
                    <option value="hari" <?= $filter_type === 'hari' ? 'selected' : '' ?>>Per Hari</option>
                    <option value="bulan" <?= $filter_type === 'bulan' ? 'selected' : '' ?>>Per Bulan</option>
                    <option value="semester" <?= $filter_type === 'semester' ? 'selected' : '' ?>>Per Semester</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Tanggal/Bulan</label>
                <input type="date" name="tanggal" class="form-control" value="<?= $filter_tanggal ?>" style="display: <?= $filter_type === 'hari' ? 'block' : 'none' ?>">
                <select name="bulan" class="form-select" style="display: <?= in_array($filter_type, ['bulan', 'semester']) ? 'block' : 'none' ?>">
                    <?php for($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= $filter_bulan == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                            <?= date('F', strtotime("2024-$i-01")) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Tahun</label>
                <select name="tahun" class="form-select">
                    <?php for($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
                        <option value="<?= $i ?>" <?= $filter_tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-12 mt-3">
                <button type="submit" class="btn btn-primary w-100 fw-bold">
                    <i class="fas fa-search me-2"></i>FILTER LAPORAN
                </button>
            </div>
        </form>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col">
            <div class="card stats-card success text-white h-100">
                <div class="card-body text-center py-3">
                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                    <h6 class="card-title mb-1">Hadir</h6>
                    <h3 class="mb-0"><?= $stats_absensi['hadir'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card stats-card text-white h-100">
                <div class="card-body text-center py-3">
                    <i class="fas fa-clock fa-2x mb-2"></i>
                    <h6 class="card-title mb-1">Tepat Waktu</h6>
                    <h3 class="mb-0"><?= $stats_absensi['tepat_waktu'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card stats-card warning text-white h-100">
                <div class="card-body text-center py-3">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                    <h6 class="card-title mb-1">Terlambat</h6>
                    <h3 class="mb-0"><?= $stats_absensi['terlambat'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card stats-card danger text-white h-100">
                <div class="card-body text-center py-3">
                    <i class="fas fa-times-circle fa-2x mb-2"></i>
                    <h6 class="card-title mb-1">Sakit/Izin/Alfa</h6>
                    <h3 class="mb-0"><?= $stats_absensi['sakit'] + $stats_absensi['izin'] + $stats_absensi['alfa'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card stats-card info text-white h-100">
                <div class="card-body text-center py-3">
                    <i class="fas fa-users fa-2x mb-2"></i>
                    <h6 class="card-title mb-1">Siswa Terlambat</h6>
                    <h3 class="mb-0"><?= $total_siswa_terlambat ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Laporan Absensi Section -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Laporan Absensi Lengkap</h5>
            <small class="text-light">Periode: <?= date('d/m/Y', strtotime($tgl_mulai)) ?> - <?= date('d/m/Y', strtotime($tgl_selesai)) ?><?= $kelas_label ? ' | Kelas: '.htmlspecialchars($kelas_label) : '' ?></small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Tgl</th><th>Jam</th><th>NIS</th><th>Nama</th><th>Kelas</th><th>Status</th><th>Presensi</th><th>Keterlambatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (empty($data_absensi_result)):
                        ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Tidak ada data absensi</td>
                        </tr>
                        <?php else:
                            foreach($data_absensi_result as $r):
                        ?>
                        <tr>
                            <td><?= date('d/m/y', strtotime($r['tanggal'])) ?></td>
                            <td><?= date('H:i', strtotime($r['waktu_absen'] ?? $r['jam'])) ?></td>
                            <td><?= SecurityHelper::escapeHTML($r['nis']) ?></td>
                            <td><?= SecurityHelper::escapeHTML($r['nama_lengkap']) ?></td>
                            <td><?= SecurityHelper::escapeHTML($r['nama_kelas']) ?></td>
                            <td><span class="badge bg-<?= $r['nama_status']=='HADIR'?'success':'danger' ?>"><?= SecurityHelper::escapeHTML($r['nama_status']) ?></span></td>
                            <td>
                                <?php if ($r['status_presensi'] === 'tepat_waktu'): ?>
                                    <span class="badge bg-success">Tepat Waktu</span>
                                <?php elseif ($r['status_presensi'] === 'terlambat'): ?>
                                    <span class="badge bg-warning text-dark">Terlambat</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['keterlambatan_menit'] > 0): ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-clock me-1"></i><?= (int)$r['keterlambatan_menit'] ?> menit
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endforeach;
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Rekap Keterlambatan Section -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i><?= $title_rekap ?></h5>
            <small class="text-muted">Analisis keterlambatan siswa berdasarkan periode</small>
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
                                <th>#</th><th>NISN</th><th>Nama Siswa</th><th>Kelas</th><th>Total Terlambat</th><th>Rata-rata/Hari</th><th>Persentase</th><th>Status</th>
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
                                    <td><strong><?= htmlspecialchars($siswa['nama_lengkap']) ?></strong></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($siswa['nama_kelas'] ?? 'N/A') ?></span></td>
                                    <td>
                                        <span class="badge bg-warning text-dark fs-6">
                                            <i class="fas fa-clock me-1"></i><?= $siswa['total_terlambat'] ?> kali
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $total_hari = $filter_type === 'hari' ? 1 :
                                                    ($filter_type === 'bulan' ? date('t', strtotime("$filter_tahun-$filter_bulan-01")) : 17);
                                        $avg_daily = round($siswa['total_terlambat'] / $total_hari, 1);
                                        ?>
                                        <span class="text-muted"><?= $avg_daily ?>/hari</span>
                                    </td>
                                    <td>
                                        <?php
                                        $persentase = round(($siswa['total_terlambat'] / $total_hari) * 100, 1);
                                        $color_class = $persentase >= 50 ? 'bg-danger' : ($persentase >= 25 ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?= $color_class ?>" role="progressbar" style="width: <?= min($persentase, 100) ?>%">
                                                <?= $persentase ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($siswa['total_terlambat'] == 0): ?>
                                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Disiplin</span>
                                        <?php elseif ($siswa['total_terlambat'] <= 3): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i>Perlu Perhatian</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Butuh Pembinaan</span>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle filter inputs based on type selection
    const typeSelect = document.querySelector('select[name="type"]');
    const tanggalInput = document.querySelector('input[name="tanggal"]');
    const bulanSelect = document.querySelector('select[name="bulan"]');

    function toggleFilters() {
        const selectedType = typeSelect.value;

        if (selectedType === 'hari') {
            tanggalInput.style.display = 'block';
            bulanSelect.style.display = 'none';
            tanggalInput.required = true;
            bulanSelect.required = false;
        } else {
            tanggalInput.style.display = 'none';
            bulanSelect.style.display = 'block';
            tanggalInput.required = false;
            bulanSelect.required = true;
        }
    }

    // Initial toggle
    toggleFilters();

    // Add event listener
    typeSelect.addEventListener('change', toggleFilters);
});
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html></content>
<parameter name="filePath">c:\laragon\www\absensi_smp\pages\laporan_gabungan.php