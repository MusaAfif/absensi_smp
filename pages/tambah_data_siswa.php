<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/DatabaseHelper.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
cek_login();

// Initialize CSRF protection
CSRFProtection::init();
$dbHelper = new DatabaseHelper($conn);

$error = '';

if (isset($_POST['simpan'])) {
    // Verify CSRF token
    if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF token tidak valid. Silakan coba lagi.';
    } else {
        // Sanitasi input dengan prepared statements
        $nis          = SecurityHelper::sanitizeInput($_POST['nis'] ?? '');
        $nisn         = SecurityHelper::sanitizeInput($_POST['nisn'] ?? '');
        $nama_lengkap = SecurityHelper::sanitizeInput($_POST['nama_lengkap'] ?? '');
        $jk           = SecurityHelper::sanitizeInput($_POST['jk'] ?? '');
        $id_kelas     = SecurityHelper::sanitizeInput($_POST['id_kelas'] ?? '');

        // Validasi input
        if (empty($nis) || empty($nisn) || empty($nama_lengkap) || empty($jk) || empty($id_kelas)) {
            $error = 'Semua field harus diisi!';
        } elseif (!SecurityHelper::validateInteger($id_kelas)) {
            $error = 'ID Kelas tidak valid!';
        } else {
            // Upload foto aman: validasi ketat + nama file unik
            $foto_name = $_FILES['foto']['name'] ?? '';
            $foto_baru = 'default.png';
            
            if ($foto_name != "") {
                $uploadFoto = SecurityHelper::uploadImageSecure(
                    $_FILES['foto'],
                    __DIR__ . '/../assets/img/siswa/',
                    [
                        'prefix' => 'siswa',
                        'maxSize' => 1024 * 1024,
                        'recommendedSizes' => [
                            ['w' => 400, 'h' => 400],
                            ['w' => 300, 'h' => 400],
                        ],
                    ]
                );

                if ($uploadFoto['success']) {
                    $foto_baru = $uploadFoto['filename'];
                } else {
                    $error = 'Upload foto gagal: ' . $uploadFoto['message'];
                }
            }

            // Insert jika tidak ada error
            if (empty($error)) {
                $result = $dbHelper->insert(
                    "INSERT INTO siswa (nis, nisn, nama_lengkap, jk, id_kelas, foto) 
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$nis, $nisn, $nama_lengkap, $jk, $id_kelas, $foto_baru],
                    'sssssi'
                );

                if ($result) {
                    echo "<script>alert('Data Berhasil Disimpan'); window.location='siswa.php';</script>";
                    exit;
                } else {
                    $error = "Gagal menambah data: " . $dbHelper->getLastError();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Siswa | E-Absensi</title>
    <link href="../assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='../assets/css/site.css' rel='stylesheet'>
    <style>
        body { background: #f8f9fa; }
        .form-container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .btn-save { background: #0d6efd; font-weight: bold; padding: 10px; }
    </style>
</head>
<body>

<div class="container">
    <div class="form-container">
        <h4 class="fw-bold mb-4">Tambah Data Siswa Baru</h4>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= $error; ?></div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <!-- CSRF Token -->
            <?= CSRFProtection::getTokenField() ?>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Nomor Induk Siswa (NIS)</label>
                    <input type="text" name="nis" class="form-control" placeholder="Contoh: 10212" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">NISN</label>
                    <input type="text" name="nisn" class="form-control" placeholder="Contoh: 12315" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Nama Lengkap</label>
                <input type="text" name="nama_lengkap" class="form-control" placeholder="Nama sesuai ijazah" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Jenis Kelamin</label>
                    <select name="jk" class="form-select" required>
                        <option value="">-- Pilih --</option>
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Kelas</label>
                    <select name="id_kelas" class="form-select" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php 
                        $k = mysqli_query($conn, "SELECT * FROM kelas ORDER BY nama_kelas ASC");
                        while($rk = mysqli_fetch_assoc($k)): ?>
                            <option value="<?= $rk['id_kelas']; ?>"><?= $rk['nama_kelas']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Foto Siswa (Opsional)</label>
                <input type="file" id="foto-input" name="foto" class="form-control" accept="image/png, image/jpeg, image/jpg">
                <small class="text-muted d-block mt-1">Format: JPG/PNG | Maks: 1MB | Disarankan: 400x400px</small>
                <div class="mt-3">
                    <img id="foto-preview" src="../assets/img/siswa/default.png" alt="Preview Foto" class="rounded border" style="width: 120px; height: 120px; object-fit: cover;">
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" name="simpan" class="btn btn-primary btn-save">SIMPAN DATA SISWA</button>
                <a href="siswa.php" class="btn btn-light border text-center text-decoration-none">Kembali ke Database</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('foto-input');
    const preview = document.getElementById('foto-preview');

    if (!input || !preview) return;

    input.addEventListener('change', function () {
        const file = this.files && this.files[0] ? this.files[0] : null;
        if (!file) {
            preview.src = '../assets/img/siswa/default.png';
            return;
        }

        if (!['image/jpeg', 'image/png'].includes(file.type)) {
            alert('File harus JPG atau PNG');
            this.value = '';
            preview.src = '../assets/img/siswa/default.png';
            return;
        }

        if (file.size > 1024 * 1024) {
            alert('Ukuran foto maksimal 1MB');
            this.value = '';
            preview.src = '../assets/img/siswa/default.png';
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
});
</script>
</body>
</html>

