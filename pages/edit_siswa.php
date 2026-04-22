<?php 
include __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/DatabaseHelper.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';

// Initialize CSRF protection
CSRFProtection::init();
$dbHelper = new DatabaseHelper($conn);

// Ambil data dari URL dan Database dengan prepared statement
$data = null;
if (isset($_GET['id'])) {
    $id = SecurityHelper::sanitizeInput($_GET['id'] ?? '');
    if (!SecurityHelper::validateInteger($id)) {
        $error = "ID tidak valid";
    } else {
        $result = $dbHelper->selectOne(
            "SELECT * FROM siswa WHERE id_siswa = ? LIMIT 1",
            [$id],
            'i'
        );
        $data = $result ?: null;
    }
}

// Logika Update Data
$error = '';
if (isset($_POST['update'])) {
    // Verify CSRF token
    if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF token tidak valid. Silakan coba lagi.';
    } else {
        $id               = SecurityHelper::sanitizeInput($_POST['id'] ?? '');
        $nis              = SecurityHelper::sanitizeInput($_POST['nis'] ?? '');
        $nisn             = SecurityHelper::sanitizeInput($_POST['nisn'] ?? '');
        $nama_lengkap     = SecurityHelper::sanitizeInput($_POST['nama_lengkap'] ?? '');
        $id_kelas         = SecurityHelper::sanitizeInput($_POST['id_kelas'] ?? '');
        $jenis_kelamin    = SecurityHelper::sanitizeInput($_POST['jenis_kelamin'] ?? '');
        
        // Validasi input
        if (!SecurityHelper::validateInteger($id)) {
            $error = "ID tidak valid";
        } elseif (empty($nis) || empty($nisn) || empty($nama_lengkap) || empty($id_kelas)) {
            $error = "Semua field harus diisi!";
        } elseif (!SecurityHelper::validateInteger($id_kelas)) {
            $error = "ID Kelas tidak valid!";
        } else {
            // Solusi: Ambil inisial L/P saja
            $jk = ($jenis_kelamin == 'Laki-laki') ? 'L' : 'P';
            
            // Upload foto jika ada
            $foto_baru = null;
            if (!empty($_FILES['foto']['name'])) {
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
            
            // Update jika tidak ada error
            if (empty($error)) {
                if ($foto_baru) {
                    // Update dengan foto baru
                    $result = $dbHelper->update(
                        "UPDATE siswa SET nis = ?, nisn = ?, nama_lengkap = ?, jenis_kelamin = ?, id_kelas = ?, foto = ? WHERE id_siswa = ?",
                        [$nis, $nisn, $nama_lengkap, $jk, $id_kelas, $foto_baru, $id],
                        'sssssii'
                    );
                } else {
                    // Update tanpa foto
                    $result = $dbHelper->update(
                        "UPDATE siswa SET nis = ?, nisn = ?, nama_lengkap = ?, jenis_kelamin = ?, id_kelas = ? WHERE id_siswa = ?",
                        [$nis, $nisn, $nama_lengkap, $jk, $id_kelas, $id],
                        'sssssi'
                    );
                }
                
                if ($result) {
                    echo "<script>alert('Data Berhasil Diperbarui'); window.location='siswa.php';</script>";
                    exit;
                } else {
                    $error = "Gagal Update: " . $dbHelper->getLastError();
                }
            }
        }
    }
}

// Load Header & Navbar
$page_title = 'Edit Data Siswa | E-Absensi SMP';
include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0">Edit Data Siswa</h5>
                </div>
                <div class="card-body p-4">
                    <?php if(!empty($error)): ?>
                        <div class="alert alert-danger"><?= SecurityHelper::escapeHTML($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if($data): ?>
                    <form action="" method="POST" enctype="multipart/form-data">
                        <!-- CSRF Token -->
                        <?= CSRFProtection::getTokenField() ?>
                        
                        <!-- Hidden ID -->
                        <input type="hidden" name="id" value="<?= SecurityHelper::escapeHTML($data['id_siswa'] ?? '') ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">NIS</label>
                                <input type="text" name="nis" class="form-control" value="<?= $data['nis']; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">NISN</label>
                                <input type="text" name="nisn" class="form-control" value="<?= $data['nisn']; ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Nama Lengkap</label>
                                <input type="text" name="nama_lengkap" class="form-control" value="<?= $data['nama_lengkap']; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Jenis Kelamin</label>
                                <select name="jenis_kelamin" class="form-select">
                                    <option value="Laki-laki" <?= ($data['jenis_kelamin'] == 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                                    <option value="Perempuan" <?= ($data['jenis_kelamin'] == 'P') ? 'selected' : ''; ?>>Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Kelas</label>
                                <select name="id_kelas" class="form-select">
                                    <?php 
                                    $kelas = mysqli_query($conn, "SELECT * FROM kelas");
                                    while($k = mysqli_fetch_assoc($kelas)) {
                                        $sel = ($k['id_kelas'] == $data['id_kelas']) ? 'selected' : '';
                                        echo "<option value='$k[id_kelas]' $sel>$k[nama_kelas]</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Foto Siswa</label>
                                <div class="d-flex align-items-center gap-3 p-2 border rounded bg-light">
                                    <img id="foto-preview" src="../assets/img/siswa/<?= htmlspecialchars($data['foto'] ?: 'default.png', ENT_QUOTES, 'UTF-8'); ?>" width="90" height="90" class="rounded shadow-sm" style="object-fit:cover;">
                                    <div class="flex-grow-1">
                                        <input type="file" id="foto-input" name="foto" class="form-control" accept="image/png, image/jpeg, image/jpg">
                                        <small class="text-muted d-block mt-1">Format: JPG/PNG | Maks: 1MB | Disarankan: 400x400px</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" name="update" class="btn btn-primary w-100 py-2 fw-bold">SIMPAN PERUBAHAN</button>
                            <a href="siswa.php" class="btn btn-outline-secondary w-100 mt-2">Kembali ke Database</a>
                        </div>
                    </form>
                    <?php else: ?>
                        <div class="alert alert-danger">Data siswa tidak ditemukan!</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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
        if (!file) return;

        if (!['image/jpeg', 'image/png'].includes(file.type)) {
            alert('File harus JPG atau PNG');
            this.value = '';
            return;
        }

        if (file.size > 1024 * 1024) {
            alert('Ukuran foto maksimal 1MB');
            this.value = '';
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
