<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
cek_role(['super_admin']);

// Initialize CSRF protection
CSRFProtection::init();

// Ambil Nama Sekolah
$nama_sekolah = 'SISTEM ABSENSI';
$query = "SELECT isi_pengaturan FROM pengaturan WHERE nama_pengaturan='nama_sekolah' LIMIT 1";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $nama_sekolah = $row['isi_pengaturan'] ?? 'SISTEM ABSENSI';
}

// Proses restore jika ada file yang diupload
$pesan = "";
if (isset($_POST['restore'])) {
    // Verify CSRF token
    if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
        $pesan = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle me-2'></i>CSRF token tidak valid. Silakan coba lagi.</div>";
    } else {
        // This is now handled by the API
        $pesan = "<div class='alert alert-info'>Memproses restore...</div>";
    }
}

// Cek status dari URL
if (isset($_GET['status']) && $_GET['status'] == 'restored') {
    $file_name = SecurityHelper::sanitizeInput($_GET['file'] ?? 'Unknown');
    $pesan = "<div class='alert alert-success'><i class='fas fa-check-circle me-2'></i>Database berhasil direstore dari file: <strong>" . SecurityHelper::escapeHTML($file_name) . "</strong></div>";
} elseif (isset($_GET['error'])) {
    $error = SecurityHelper::sanitizeInput(urldecode($_GET['error'] ?? ''));
    $pesan = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle me-2'></i>Error restoring database: <strong>" . SecurityHelper::escapeHTML($error) . "</strong></div>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESTORE DATABASE | <?= $nama_sekolah ?></title>
    <link href="../assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='../assets/css/site.css' rel='stylesheet'>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f8f9fa; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .restore-icon { font-size: 4rem; color: #dc3545; }
        .progress { height: 8px; border-radius: 4px; }
        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .file-upload-area:hover {
            border-color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.05);
        }
        .file-upload-area.dragover {
            border-color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.1);
        }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card p-4">
                <div class="text-center mb-4">
                    <i class="fas fa-sync restore-icon mb-3"></i>
                    <h3 class="fw-bold">Restore Database</h3>
                    <p class="text-muted">Kembalikan data sistem dari file backup</p>
                </div>

                <?= $pesan; ?>

                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Peringatan Penting:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Proses restore akan <strong>MENGHAPUS</strong> semua data yang ada saat ini</li>
                        <li>Pastikan file backup valid dan dari sistem yang sama</li>
                        <li>Buat backup data saat ini sebelum melakukan restore</li>
                        <li>Proses ini tidak dapat dibatalkan</li>
                    </ul>
                </div>

                <form action="<?= BASE_URL ?>api/restore.php" method="POST" enctype="multipart/form-data">
                    <!-- CSRF Token -->
                    <?= CSRFProtection::getTokenField() ?>
                    
                    <div class="file-upload-area mb-4" onclick="document.getElementById('backup_file').click()">
                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                        <h5>Klik untuk memilih file backup</h5>
                        <p class="text-muted">atau drag & drop file .sql di sini</p>
                        <input type="file" name="backup_file" id="backup_file" accept=".sql" style="display: none;" required>
                        <div id="file-info" class="mt-3" style="display: none;">
                            <i class="fas fa-file-code text-primary me-2"></i>
                            <span id="file-name"></span>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" id="btn-restore" class="btn btn-danger btn-lg px-5" disabled>
                            <i class="fas fa-sync me-2"></i>
                            MULAI RESTORE
                        </button>
                        <p class="text-muted mt-3 small">Pastikan file backup sudah dipilih sebelum klik tombol restore</p>
                    </div>
                </form>

                <div id="progress-container" class="mt-4" style="display: none;">
                    <div class="text-center mb-3">
                        <h6>Memproses Restore...</h6>
                    </div>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-danger"
                             role="progressbar" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const MAX_BACKUP_SIZE = 20 * 1024 * 1024;

// File upload handling
document.getElementById('backup_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const ext = file.name.split('.').pop().toLowerCase();
        if (ext !== 'sql') {
            Swal.fire({ icon: 'error', title: 'Format file tidak valid', text: 'File backup harus berformat .sql' });
            this.value = '';
            document.getElementById('file-info').style.display = 'none';
            document.getElementById('btn-restore').disabled = true;
            return;
        }

        if (file.size <= 0 || file.size > MAX_BACKUP_SIZE) {
            Swal.fire({ icon: 'error', title: 'Ukuran file tidak valid', text: 'Ukuran file backup maksimal 20MB.' });
            this.value = '';
            document.getElementById('file-info').style.display = 'none';
            document.getElementById('btn-restore').disabled = true;
            return;
        }

        document.getElementById('file-name').textContent = file.name;
        document.getElementById('file-info').style.display = 'block';
        document.getElementById('btn-restore').disabled = false;
    }
});

// Drag and drop functionality
const uploadArea = document.querySelector('.file-upload-area');
const fileInput = document.getElementById('backup_file');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    uploadArea.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    uploadArea.classList.add('dragover');
}

function unhighlight(e) {
    uploadArea.classList.remove('dragover');
}

uploadArea.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;

    if (files.length > 0) {
        if (files.length > 1) {
            Swal.fire({ icon: 'warning', title: 'Terlalu banyak file', text: 'Pilih satu file backup SQL saja.' });
            return;
        }
        fileInput.files = files;
        const event = new Event('change');
        fileInput.dispatchEvent(event);
    }
}

// Form submission handling
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();

    Swal.fire({
        title: 'Konfirmasi Restore',
        text: 'PERINGATAN: Semua data saat ini akan DIHAPUS dan DIGANTI dengan data dari file backup. Proses ini TIDAK DAPAT DIBATALKAN. Apakah Anda yakin?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Restore Sekarang',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            // Tampilkan progress
            document.getElementById('progress-container').style.display = 'block';
            document.getElementById('btn-restore').disabled = true;
            document.getElementById('btn-restore').innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>MEMPROSES...';

            // Submit form
            e.target.submit();
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>

