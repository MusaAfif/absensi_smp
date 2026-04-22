<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
cek_login();

CSRFProtection::init();

// Ambil Nama Sekolah
$res_sekolah = mysqli_query($conn, "SELECT isi_pengaturan FROM pengaturan WHERE nama_pengaturan='nama_sekolah'");
$nama_sekolah = mysqli_fetch_assoc($res_sekolah)['isi_pengaturan'] ?? 'SISTEM ABSENSI';

// Ambil Statistik Awal (kompatibel format lama & baru)
$q_stat = mysqli_query($conn, "
    SELECT
        COUNT(DISTINCT CASE
            WHEN status = 'masuk'
              OR status = 'Hadir'
              OR status_presensi IN ('tepat_waktu', 'terlambat')
            THEN id_siswa
        END) AS jml_masuk,
        COUNT(DISTINCT CASE
            WHEN status = 'pulang' OR id_status = 3
            THEN id_siswa
        END) AS jml_pulang,
        (SELECT COUNT(*) FROM siswa) AS total_siswa
    FROM absensi
    WHERE tanggal = CURDATE()
");
$stat = mysqli_fetch_assoc($q_stat);
$masuk = (int)($stat['jml_masuk'] ?? 0);
$pulang = (int)($stat['jml_pulang'] ?? 0);
$total = (int)($stat['total_siswa'] ?? 0);

// Belum hadir = total siswa - siswa yang sudah tercatat masuk
$belum = max(0, $total - $masuk);

// Ambil User Saat Ini
$username = $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCAN ABSEN PULANG | <?= $nama_sekolah ?></title>
    <link href="../assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='../assets/css/site.css' rel='stylesheet'>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="../assets/css/scan_center.css" rel="stylesheet">
    <link rel="icon" href="/absensi_smp/favicon.ico.php" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-blue: #3b82f6;
            --primary-blue-dark: #2563eb;
            --primary-blue-light: #dbeafe;
        }
        .scan-header { background: linear-gradient(135deg, var(--primary-blue), var(--primary-blue-dark)); }
        .scanner-section { border-left: 4px solid var(--primary-blue); }
        .stat-card.pulang { background: linear-gradient(135deg, var(--primary-blue-light), #bfdbfe); border-color: var(--primary-blue); }
        .stat-card.pulang .stat-number { color: var(--primary-blue-dark); }
        .control-btn.active { background-color: var(--primary-blue); border-color: var(--primary-blue); }
        .control-btn.active:hover { background-color: var(--primary-blue-dark); }
        .btn-primary { background-color: var(--primary-blue); border-color: var(--primary-blue); }
        .btn-primary:hover { background-color: var(--primary-blue-dark); }
    </style>
</head>
<body>

<!-- ===== HEADER ===== -->
<div class="scan-header">
    <div class="header-top">
        <div class="header-info">
            <h3><i class="fas fa-sign-out-alt me-2"></i>SCAN ABSEN PULANG</h3>
            <p><?= $nama_sekolah ?></p>
        </div>

        <div class="header-right">
            <div class="datetime-info">
                <div class="time" id="clock">00:00:00</div>
                <div class="date-day" id="date-display">Jumat, 01 Januari 2025</div>
            </div>
            <div class="status-badge" id="system-status-badge">
                <span class="indicator"></span>
                <span>ONLINE</span>
            </div>
        </div>
    </div>

    <div class="header-nav">
        <a href="scan_masuk.php" class="btn btn-outline-light" title="Halaman Absen Masuk">
            <i class="fas fa-sign-in-alt me-1"></i> Absen Masuk
        </a>
        <a href="../index.php" class="btn btn-light" title="Kembali ke Dashboard">
            <i class="fas fa-home me-1"></i> Dashboard
        </a>
        <a href="../pages/dashboard.php" class="btn btn-light" title="Halaman Admin">
            <i class="fas fa-cog me-1"></i> Admin
        </a>
        <a href="../logout.php" class="btn btn-light" title="Logout">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </a>
    </div>
</div>

<!-- ===== MAIN CONTAINER ===== -->
<div class="main-container">
    <!-- SCANNER SECTION -->
    <div class="scanner-section">
        <!-- Kamera Scanner -->
        <div class="scanner-container shadow-lg">
            <div class="scan-overlay">
                <div class="corner top-left"></div>
                <div class="corner top-right"></div>
                <div class="corner bottom-left"></div>
                <div class="corner bottom-right"></div>
                <div class="scan-line"></div>
            </div>
            <video id="preview" class="video-feed"></video>
            <div id="camera-placeholder" style="display: none; width: 100%; height: 100%; background: linear-gradient(135deg, #1a1a1a, #2d2d2d); display: flex; align-items: center; justify-content: center; color: #999;">
                <div text-align="center">
                    <i class="fas fa-camera fa-3x mb-3"></i>
                    <p>Kamera tidak tersedia</p>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="scanner-controls">
            <button class="control-btn active" id="btn-camera" title="Aktif/Nonaktif Kamera" data-status="on">
                <i class="fas fa-video"></i>
            </button>
            <button class="control-btn" id="btn-refresh" title="Reset Scanner">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button class="control-btn" id="btn-settings" title="Pengaturan">
                <i class="fas fa-sliders-h"></i>
            </button>
        </div>

        <!-- Barcode Input -->
        <div class="barcode-input-section">
            <div class="input-group">
                <span class="input-group-text">
                    <i class="fas fa-barcode"></i>
                </span>
                <input type="text" id="barcode-input" class="form-control"
                       placeholder="Scan kartu ID siswa, QR Code, atau Barcode..."
                       autofocus autocomplete="off">
                <button class="btn btn-primary" id="btn-proses">
                    <i class="fas fa-check me-1"></i> SCAN PULANG
                </button>
            </div>
        </div>
    </div>

    <!-- INFO PANEL -->
    <div class="info-panel">
        <!-- Statistik -->
        <div class="stats-row">
            <div class="stat-card masuk">
                <div class="stat-label">Sudah Masuk</div>
                <p class="stat-number" id="stat-masuk"><?= $masuk ?></p>
            </div>
            <div class="stat-card pulang">
                <div class="stat-label">Sudah Pulang</div>
                <p class="stat-number" id="stat-pulang"><?= $pulang ?></p>
            </div>
            <div class="stat-card belum">
                <div class="stat-label">Belum Hadir</div>
                <p class="stat-number" id="stat-belum"><?= $belum ?></p>
            </div>
        </div>

        <!-- Last Scan Card -->
        <div id="result-display">
            <div class="last-scan-card">
                <div class="empty-state">
                    <i class="fas fa-id-card"></i>
                    <p style="font-weight: 500; margin-bottom: 4px;">Menunggu Scan Pulang</p>
                    <p style="font-size: 0.85rem;">Arahkan QR Code atau scan dengan barcode scanner</p>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="system-status">
            <h6><i class="fas fa-info-circle me-2"></i>Status Sistem</h6>
            <div class="status-item">
                <div class="status-icon success">
                    <i class="fas fa-check"></i>
                </div>
                <span class="status-text success" id="status-scanner-check">Scanner aktif & siap</span>
            </div>
            <div class="status-item">
                <div class="status-icon success">
                    <i class="fas fa-check"></i>
                </div>
                <span class="status-text success" id="status-connection-check">Data tersimpan</span>
            </div>
            <div class="status-item">
                <div class="status-icon success">
                    <i class="fas fa-check"></i>
                </div>
                <span class="status-text success" id="status-audio-check">Audio aktif</span>
            </div>
            <div class="status-item" style="padding-top: 12px; border-top: 1px solid var(--border-light); border-bottom: none;">
                <small style="color: #9ca3af;">
                    <i class="fas fa-history me-1"></i> Last sync: <span id="last-sync">sekarang</span>
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Audio Elements -->
<audio id="sound-success" src="../assets/audio/succes.mp3"></audio>
<audio id="sound-error" src="../assets/audio/error.mp3"></audio>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/@zxing/library@0.20.0/umd/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script src="../assets/js/qr-scanner.min.js"></script>
<script>
    // Inject BASE_PATH for JavaScript files
    window.BASE_PATH = <?php echo json_encode(BASE_PATH); ?>;
    window.CSRF_TOKEN = <?php echo json_encode(CSRFProtection::getToken()); ?>;
</script>
<script src="../assets/js/scan_pulang.js?v=1.1"></script>
<?php include '../includes/footer.php'; ?>
</body>
</html>