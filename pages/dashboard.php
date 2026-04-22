<?php 
require_once __DIR__ . '/../includes/config.php'; 
require_once __DIR__ . '/../includes/dashboard_service.php';
cek_login(); 

$dashboardService = new DashboardService($conn);
$set = $dashboardService->getSettings();
$stats = $dashboardService->getSummaryStats();
$data_kehadiran = $dashboardService->getWeeklyAttendance();
$kelas_data = $dashboardService->getClassAttendancePercentages();
$recentAttendance = $dashboardService->getRecentAttendance(20);

$total_siswa = $stats['total_siswa'];
$hadir_hari_ini = $stats['hadir_hari_ini'];
$terlambat_hari_ini = $stats['terlambat_hari_ini'];
$belum_hadir = $stats['belum_hadir'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?= htmlspecialchars($set['nama_sekolah']) ?></title>
    <link href="../assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='../assets/css/site.css' rel='stylesheet'>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.amcharts.com/lib/5/index.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/percent.js"></script>
    <link href="../assets/css/dashboard.css" rel="stylesheet">
</head>
<body data-auto-refresh-minutes="0">

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<!-- Header -->
<div class="container-fluid py-3">
    <div class="header-card p-4 mb-4">
        <div class="row align-items-center">
            <div class="col-auto">
                <img src="../assets/img/logo_sekolah/<?= htmlspecialchars($set['logo_sekolah']) ?>" width="60" class="img-fluid rounded" alt="Logo Sekolah">
            </div>
            <div class="col">
                <h3 class="fw-bold mb-0">Dashboard Absensi</h3>
                <p class="mb-0 opacity-75"><?= htmlspecialchars($set['nama_sekolah']) ?></p>
            </div>
            <div class="col-auto text-end">
                <div class="d-flex align-items-center gap-3">
                    <div>
                        <small class="d-block opacity-75">Tanggal</small>
                        <span class="fw-bold" id="current-date"><?= date('l, d F Y') ?></span>
                    </div>
                    <div>
                        <small class="d-block opacity-75">Jam</small>
                        <span class="fw-bold" id="current-time">00:00:00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card bg-primary text-white p-4 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="text-uppercase opacity-75 fw-bold">Total Siswa</small>
                        <h2 class="display-5 fw-bold mt-2 mb-0" id="stat-total"><?= $total_siswa ?></h2>
                    </div>
                    <i class="fas fa-users fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card bg-success text-white p-4 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="text-uppercase opacity-75 fw-bold">Hadir Hari Ini</small>
                        <h2 class="display-5 fw-bold mt-2 mb-0" id="stat-hadir"><?= $hadir_hari_ini ?></h2>
                    </div>
                    <i class="fas fa-check-circle fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card bg-warning text-white p-4 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="text-uppercase opacity-75 fw-bold">Terlambat</small>
                        <h2 class="display-5 fw-bold mt-2 mb-0" id="stat-terlambat"><?= $terlambat_hari_ini ?></h2>
                    </div>
                    <i class="fas fa-clock fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card bg-danger text-white p-4 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="text-uppercase opacity-75 fw-bold">Belum Hadir</small>
                        <h2 class="display-5 fw-bold mt-2 mb-0" id="stat-belum-hadir"><?= $belum_hadir ?></h2>
                    </div>
                    <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm p-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tanggal Mulai</label>
                        <input type="date" class="form-control" id="start-date" value="<?= date('Y-m-d', strtotime('-7 days')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tanggal Akhir</label>
                        <input type="date" class="form-control" id="end-date" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Kelas</label>
                        <select class="form-select" id="filter-kelas">
                            <option value="">Semua Kelas</option>
                            <?php
                            $kelas = mysqli_query($conn, "SELECT * FROM kelas");
                            while ($k = mysqli_fetch_assoc($kelas)) {
                                echo "<option value='{$k['id_kelas']}'>{$k['nama_kelas']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100" id="apply-filters">Terapkan Filter</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="chart-container">
                <h5 class="fw-bold mb-3">Kehadiran 7 Hari Terakhir</h5>
                <div id="chart-kehadiran" class="chart-canvas"></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-container">
                <h5 class="fw-bold mb-3">Distribusi Status Hari Ini</h5>
                <div id="chart-pie" class="chart-canvas"></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="chart-container">
                <h5 class="fw-bold mb-3">Persentase Kehadiran Per Kelas</h5>
                <div id="chart-kelas" class="chart-canvas"></div>
            </div>
        </div>
    </div>

    <!-- Tabel Data Terbaru -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0">Absensi Terbaru</h5>
                        <div class="d-flex gap-2">
                            <input type="text" class="form-control form-control-sm filter-search" placeholder="Cari nama..." id="search-input">
                            <select class="form-select form-select-sm filter-status" id="status-filter">
                                <option value="">Semua Status</option>
                                <option value="Tepat Waktu">Tepat Waktu</option>
                                <option value="Terlambat">Terlambat</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="absensi-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0 ps-4">Nama Siswa</th>
                                    <th class="border-0">Kelas</th>
                                    <th class="border-0">Jam Masuk</th>
                                    <th class="border-0">Status</th>
                                    <th class="border-0">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (empty($recentAttendance)) {
                                    echo "<tr><td colspan='5' class='text-center py-4 text-muted'>Belum ada data absensi terbaru.</td></tr>";
                                } else {
                                    foreach ($recentAttendance as $item) {
                                        echo "<tr>
                                            <td class='ps-4'>" . htmlspecialchars($item['nama_lengkap']) . "</td>
                                            <td>" . htmlspecialchars($item['nama_kelas']) . "</td>
                                            <td>" . htmlspecialchars($item['jam']) . "</td>
                                            <td><span class='badge bg-" . htmlspecialchars($item['status_class']) . "'>" . htmlspecialchars($item['status_label']) . "</span></td>
                                            <td>" . date('d/m/Y', strtotime($item['tanggal'])) . "</td>
                                        </tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

<!-- Pass PHP data to JavaScript -->
<script>
    // Chart data from PHP
    window.chartDataKehadiran = <?= json_encode($data_kehadiran) ?>;
    window.hadirHariIni = <?= $hadir_hari_ini ?>;
    window.terlambatHariIni = <?= $terlambat_hari_ini ?>;
    window.belumHadir = <?= $belum_hadir ?>;
    window.chartDataKelas = <?= json_encode($kelas_data) ?>;
    window.BASE_PATH = <?= json_encode(BASE_PATH) ?>;
</script>

<!-- Dashboard Scripts -->
<script src="../assets/js/dashboard.js"></script>

<?php include '../includes/footer.php'; ?>
</body>
</html>

