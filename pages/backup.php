<?php
require_once __DIR__ . '/../includes/config.php';
cek_role(['super_admin']);

// Ambil Nama Sekolah
$res_sekolah = mysqli_query($conn, "SELECT isi_pengaturan FROM pengaturan WHERE nama_pengaturan='nama_sekolah'");
$nama_sekolah = mysqli_fetch_assoc($res_sekolah)['isi_pengaturan'] ?? 'SISTEM ABSENSI';

// Proses backup jika diminta
if (isset($_GET['action']) && $_GET['action'] == 'backup') {
    // Redirect ke API backup untuk download
    header("Location: " . BASE_URL . "api/backup.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BACKUP DATABASE | <?= $nama_sekolah ?></title>
    <link href="../assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='../assets/css/site.css' rel='stylesheet'>
    <link href="../assets/css/backup.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="backup-page">

<?php include '../includes/navbar.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card p-4">
                <div class="text-center mb-4">
                    <i class="fas fa-database backup-icon mb-3"></i>
                    <h3 class="fw-bold">Backup Database</h3>
                    <p class="text-muted">Buat salinan data sistem absensi untuk keamanan</p>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Informasi Backup:</strong>
                    <ul class="mb-0 mt-2">
                        <li>File backup akan berisi semua data siswa, absensi, dan pengaturan</li>
                        <li>Format file: SQL (dapat diimpor ke database MySQL)</li>
                        <li>Ukuran file tergantung jumlah data yang ada</li>
                        <li>Simpan file backup di tempat yang aman</li>
                    </ul>
                </div>

                <div class="text-center">
                    <button id="btn-backup" class="btn btn-primary btn-lg px-5">
                        <i class="fas fa-download me-2"></i>
                        MULAI BACKUP
                    </button>
                    <p class="text-muted mt-3 small">Proses backup akan memakan waktu beberapa detik tergantung ukuran database</p>
                </div>

                <div id="progress-container" class="mt-4" style="display: none;">
                    <div class="text-center mb-3">
                        <h6>Mempersiapkan Backup...</h6>
                    </div>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                             role="progressbar" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/backup.js"></script>

<?php include '../includes/footer.php'; ?>
</body>
</html>

