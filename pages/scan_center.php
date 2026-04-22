<?php
require_once __DIR__ . '/../includes/config.php';
cek_login(); 

// Ambil Nama Sekolah
$res_sekolah = mysqli_query($conn, "SELECT isi_pengaturan FROM pengaturan WHERE nama_pengaturan='nama_sekolah'");
$nama_sekolah = mysqli_fetch_assoc($res_sekolah)['isi_pengaturan'] ?? 'SISTEM ABSENSI';

// Ambil Statistik Hari Ini (kompatibel format lama & baru)
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
    <title>CENTER SCAN ABSENSI | <?= $nama_sekolah ?></title>
    <link href="../assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='../assets/css/site.css' rel='stylesheet'>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        .choice-container {
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .choice-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 800px;
            width: 100%;
            margin: 20px;
        }
        .choice-header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .choice-body {
            padding: 40px;
        }
        .choice-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .choice-option {
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
        }
        .choice-option:hover {
            transform: translateY(-5px);
        }
        .option-card {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }
        .option-card.pulang {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }
        .option-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .option-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .option-desc {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .stats-overview {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            text-align: center;
        }
        .stat-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #1f2937;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 5px;
        }
        .nav-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        .nav-btn {
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .nav-btn:hover {
            transform: translateY(-2px);
        }
        @media (max-width: 768px) {
            .choice-options {
                grid-template-columns: 1fr;
            }
            .choice-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<div class="choice-container">
    <div class="choice-card animate__animated animate__fadeInUp">
        <div class="choice-header">
            <h2><i class="fas fa-qrcode me-3"></i>CENTER SCAN ABSENSI</h2>
            <p class="mb-0"><?= $nama_sekolah ?></p>
        </div>
        
        <div class="choice-body">
            <div class="choice-options">
                <a href="scan_masuk.php" class="choice-option">
                    <div class="option-card masuk">
                        <div class="option-icon">
                            <i class="fas fa-sign-in-alt"></i>
                        </div>
                        <div class="option-title">ABSEN MASUK</div>
                        <div class="option-desc">Scan untuk mencatat waktu masuk siswa</div>
                    </div>
                </a>
                
                <a href="scan_pulang.php" class="choice-option">
                    <div class="option-card pulang">
                        <div class="option-icon">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <div class="option-title">ABSEN PULANG</div>
                        <div class="option-desc">Scan untuk mencatat waktu pulang siswa</div>
                    </div>
                </a>
            </div>
            
            <div class="stats-overview">
                <h5 class="text-center mb-3"><i class="fas fa-chart-bar me-2"></i>Statistik Hari Ini</h5>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number text-success" id="sc-stat-masuk"><?= $masuk ?></div>
                        <div class="stat-label">Sudah Masuk</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number text-primary" id="sc-stat-pulang"><?= $pulang ?></div>
                        <div class="stat-label">Sudah Pulang</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number text-warning" id="sc-stat-belum"><?= $belum ?></div>
                        <div class="stat-label">Belum Hadir</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number text-info" id="sc-stat-total"><?= $total ?></div>
                        <div class="stat-label">Total Siswa</div>
                    </div>
                </div>
            </div>
            
            <div class="nav-buttons">
                <a href="../index.php" class="nav-btn btn btn-outline-secondary">
                    <i class="fas fa-home me-1"></i> Dashboard
                </a>
                <a href="../pages/dashboard.php" class="nav-btn btn btn-outline-primary">
                    <i class="fas fa-cog me-1"></i> Admin Panel
                </a>
                <a href="../logout.php" class="nav-btn btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
(function() {
    var basePath = <?= json_encode(BASE_PATH) ?>;
    basePath = basePath.replace(/\/+$/, '');
    var statsUrl = '/' + basePath.replace(/^\/+/, '') + (basePath ? '/' : '') + 'api/get_stats.php';

    function refreshStats() {
        fetch(statsUrl, { credentials: 'same-origin' })
            .then(function(r) { return r.ok ? r.json() : null; })
            .then(function(d) {
                if (!d) return;
                var m = document.getElementById('sc-stat-masuk');
                var p = document.getElementById('sc-stat-pulang');
                var b = document.getElementById('sc-stat-belum');
                var t = document.getElementById('sc-stat-total');
                if (m) m.textContent = d.hadir;
                if (p) p.textContent = Math.max(0, d.hadir - d.belum);
                if (b) b.textContent = d.belum;
                if (t) t.textContent = d.total;
            })
            .catch(function() {});
    }

    setInterval(refreshStats, 30000);
})();
</script>
</body>
</html>
