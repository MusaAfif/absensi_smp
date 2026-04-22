<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/attendance_service.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
cek_login();

// Initialize CSRF protection
CSRFProtection::init();

// AUTO-MIGRATION: Redirect ke migrasi jika kolom range waktu belum ada
if (!columnExists($conn, 'jadwal_absensi', 'jam_masuk_mulai')) {
    header('Location: ' . BASE_URL . 'scripts/migrate_now.php');
    exit;
}

// Inisialisasi session untuk pesan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$generated_codes = [];

// Buat tabel recovery code jika belum ada
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS recovery_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    code VARCHAR(64) NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME DEFAULT NULL,
    INDEX (id_user)
)");

// Buat tabel jadwal_absensi jika belum ada (dengan struktur range waktu)
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS jadwal_absensi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hari VARCHAR(10) NOT NULL UNIQUE,
    jam_masuk_mulai TIME DEFAULT '06:00',
    jam_masuk_tepat TIME DEFAULT '07:00',
    jam_masuk_selesai TIME DEFAULT '08:00',
    jam_pulang_mulai TIME DEFAULT '12:00',
    jam_pulang_selesai TIME DEFAULT '15:00',
    batas_terlambat INT DEFAULT 15
)");

// Load services
require_once 'service/pengaturan_service.php';
require_once 'service/jadwal_service.php';

$pengaturanService = new PengaturanService($conn);
$jadwalService = new JadwalService($conn);

// Ambil data untuk tampilan
$settings = $pengaturanService->getPengaturan();
$jadwal_data = $jadwalService->getJadwal();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pengaturan Sistem | db_absensi_smp</title>
    <link href="<?= BASE_URL ?>assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/site.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/pengaturan.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="pengaturan-page">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/absensi_smp/includes/navbar.php'; ?>

<div class="container py-4">
    <!-- DISPLAY SESSION MESSAGES -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <strong>BERHASIL!</strong>
            <br>
            <?= nl2br(htmlspecialchars($_SESSION['success'])) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>ERROR!</strong>
            <br>
            <?= nl2br(htmlspecialchars($_SESSION['error'])) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Pengaturan Sistem</h2>
            <p class="text-muted">Kelola konfigurasi dasar aplikasi db_absensi_smp</p>
        </div>
        <div class="btn-group shadow-sm">
            <button id="btn-backup" class="btn btn-dark"><i class="fas fa-database me-2"></i>BACKUP</button>
            <a href="<?= BASE_URL ?>pages/restore.php" id="btn-restore" class="btn btn-outline-dark"><i class="fas fa-sync me-2"></i>RESTORE</a>
        </div>
    </div>

    <!-- FORM PENGATURAN -->
    <form action="<?= BASE_URL ?>pengaturan/process_pengaturan.php" method="POST" enctype="multipart/form-data">
        <div class="row g-4">
            <div class="col-md-7">
                <div class="card p-4 h-100">
                    <h5 class="section-header text-primary">IDENTITAS INSTANSI</h5>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Sekolah / Instansi</label>
                        <input type="text" name="nama_sekolah" class="form-control" value="<?= htmlspecialchars($settings['nama_sekolah'] ?? 'SMPN 1 Indonesia') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Alamat Lengkap</label>
                        <textarea name="alamat" class="form-control" rows="3" required><?= htmlspecialchars($settings['alamat_sekolah'] ?? 'Jl. Raya Pendidikan No. 123, Indonesia') ?></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-success"><i class="fas fa-image me-1"></i> Logo Sekolah (JPG/PNG)</label>
                            <input type="file" id="logo-sekolah-input" name="logo_sekolah" class="form-control form-control-sm" accept=".jpg,.jpeg,.png">
                            <small class="text-muted d-block mt-1">Format: JPG/PNG | Maks: 500KB | Disarankan: 500x500px</small>
                            <img
                                id="logo-sekolah-preview"
                                src="<?= BASE_URL ?>assets/img/logo_sekolah/<?= htmlspecialchars($settings['logo_sekolah'] ?? 'default_logo.png', ENT_QUOTES, 'UTF-8') ?>"
                                alt="Preview Logo Sekolah"
                                class="img-thumbnail mt-2"
                                style="width:90px;height:90px;object-fit:contain;background:#fff;"
                            >
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-success"><i class="fas fa-image me-1"></i> Logo Pemda (JPG/PNG)</label>
                            <input type="file" id="logo-pemda-input" name="logo_pemda" class="form-control form-control-sm" accept=".jpg,.jpeg,.png">
                            <small class="text-muted d-block mt-1">Format: JPG/PNG | Maks: 500KB | Disarankan: 500x500px</small>
                            <img
                                id="logo-pemda-preview"
                                src="<?= BASE_URL ?>assets/img/logo_pemda/<?= htmlspecialchars($settings['logo_pemda'] ?? 'default_logo.png', ENT_QUOTES, 'UTF-8') ?>"
                                alt="Preview Logo Pemda"
                                class="img-thumbnail mt-2"
                                style="width:90px;height:90px;object-fit:contain;background:#fff;"
                            >
                        </div>
                    </div>

                    <h5 class="section-header text-primary mt-4">KONFIGURASI TAHUN AJARAN</h5>
                    <div class="row align-items-end">
                        <div class="col-8">
                            <label class="form-label small fw-bold">Tahun Ajaran Aktif</label>
                            <select name="tahun_aktif" class="form-select">
                                <option value="2025/2026" <?= ($settings['tahun_aktif'] ?? '') == '2025/2026' ? 'selected' : '' ?>>2025/2026</option>
                                <option value="2024/2025" <?= ($settings['tahun_aktif'] ?? '') == '2024/2025' ? 'selected' : '' ?>>2024/2025</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-primary w-100 btn-sm py-2">TAMBAH TA</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="card p-4 h-100">
                    <h5 class="section-header text-primary">JADWAL ABSENSI HARIAN (RANGE WAKTU)</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr class="small text-uppercase">
                                    <th>Hari</th>
                                    <th colspan="3">Absen Masuk</th>
                                    <th colspan="2">Absen Pulang</th>
                                </tr>
                                <tr class="small text-muted">
                                    <th></th>
                                    <th>Mulai</th>
                                    <th>Selesai</th>
                                    <th>Toleransi</th>
                                    <th>Mulai</th>
                                    <th>Selesai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $hari_list = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
                                foreach($hari_list as $hari):
                                    $data = $jadwal_data[$hari] ?? [];
                                    $masuk_mulai = substr((string)($data['jam_masuk_mulai'] ?? '06:00'), 0, 5);
                                    $batas_terlambat = $data['batas_terlambat'] ?? 15;
                                    $masuk_selesai = substr((string)($data['jam_masuk_selesai'] ?? '08:00'), 0, 5);
                                    $pulang_mulai = substr((string)($data['jam_pulang_mulai'] ?? ($hari == 'Jumat' ? '11:00' : '12:00')), 0, 5);
                                    $pulang_selesai = substr((string)($data['jam_pulang_selesai'] ?? ($hari == 'Jumat' ? '12:00' : '15:00')), 0, 5);
                                ?>
                                <tr>
                                    <td class="fw-bold"><?= $hari ?></td>
                                    <td><input type="time" name="masuk_mulai_<?= $hari ?>" class="form-control form-control-sm" value="<?= $masuk_mulai ?>" required></td>
                                    <td><input type="time" name="masuk_selesai_<?= $hari ?>" class="form-control form-control-sm" value="<?= $masuk_selesai ?>" required></td>
                                    <td><input type="number" name="toleransi_<?= $hari ?>" class="form-control form-control-sm" value="<?= $batas_terlambat ?>" min="0" max="60" required></td>
                                    <td><input type="time" name="pulang_mulai_<?= $hari ?>" class="form-control form-control-sm" value="<?= $pulang_mulai ?>" required></td>
                                    <td><input type="time" name="pulang_selesai_<?= $hari ?>" class="form-control form-control-sm" value="<?= $pulang_selesai ?>" required></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Range Waktu:</strong> Masuk (Mulai-Selesai=Tepat Waktu, setelah selesai + toleransi=Terlambat), Pulang (Mulai=BolehPulang, Selesai=Ditutup)
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-5 mb-5">
            <!-- CSRF Token -->
            <?= CSRFProtection::getTokenField() ?>
            
            <button type="submit" class="btn btn-primary px-5 py-3 fw-bold rounded-pill shadow">
                <i class="fas fa-save me-2"></i>SIMPAN PENGATURAN
            </button>
        </div>
    </form>
</div>

<script src="<?= BASE_URL ?>assets/vendor/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/pengaturan.js"></script>

<script>
// Popup notifications menggunakan SweetAlert2
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: '<?= addslashes($_SESSION['success']); ?>',
            confirmButtonColor: '#0d6efd',
            timer: 5000,
            timerProgressBar: true
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: '<?= addslashes($_SESSION['error']); ?>',
            confirmButtonColor: '#dc3545',
            footer: '<small>Pastikan semua data valid dan coba lagi</small>'
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    function setupPreview(inputId, previewId, maxBytes) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        if (!input || !preview) return;

        input.addEventListener('change', function () {
            const file = this.files && this.files[0] ? this.files[0] : null;
            if (!file) return;

            if (!['image/jpeg', 'image/png'].includes(file.type)) {
                Swal.fire({ icon: 'warning', title: 'Format tidak valid', text: 'Hanya JPG atau PNG yang diizinkan.' });
                this.value = '';
                return;
            }

            if (file.size > maxBytes) {
                Swal.fire({ icon: 'warning', title: 'Ukuran terlalu besar', text: 'Ukuran file maksimal 500KB.' });
                this.value = '';
                return;
            }

            const img = new Image();
            img.onload = function () {
                if (img.width > 1000 || img.height > 1000) {
                    Swal.fire({ icon: 'warning', title: 'Dimensi terlalu besar', text: 'Maksimal dimensi gambar adalah 1000x1000px.' });
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            };
            img.src = URL.createObjectURL(file);
        });
    }

    setupPreview('logo-sekolah-input', 'logo-sekolah-preview', 500 * 1024);
    setupPreview('logo-pemda-input', 'logo-pemda-preview', 500 * 1024);
});
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/absensi_smp/includes/footer.php'; ?>
</body>
</html>