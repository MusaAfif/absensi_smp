<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';

cek_role(['admin', 'super_admin']);
CSRFProtection::init();

$flash = null;

function valid_rfid_uid(string $uid): bool
{
    if ($uid === '' || strlen($uid) > 64) {
        return false;
    }
    return preg_match('/^[a-zA-Z0-9\-_\.]+$/', $uid) === 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
        $flash = ['type' => 'danger', 'message' => 'CSRF token tidak valid.'];
    } else {
        $action = trim((string)($_POST['action'] ?? 'assign'));
        $idSiswa = (int)($_POST['id_siswa'] ?? 0);

        if ($idSiswa <= 0) {
            $flash = ['type' => 'danger', 'message' => 'Siswa tidak valid.'];
        } elseif ($action === 'unlink') {
            $stmt = mysqli_prepare($conn, "UPDATE siswa SET rfid_uid = NULL WHERE id_siswa = ? LIMIT 1");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $idSiswa);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $flash = ['type' => 'success', 'message' => 'UID RFID berhasil dilepas dari siswa.'];
            } else {
                $flash = ['type' => 'danger', 'message' => 'Gagal melepaskan UID RFID.'];
            }
        } else {
            $uid = strtoupper(trim((string)($_POST['rfid_uid'] ?? '')));
            if (!valid_rfid_uid($uid)) {
                $flash = ['type' => 'danger', 'message' => 'Format UID RFID tidak valid. Gunakan karakter A-Z, 0-9, -, _, .'];
            } else {
                $dupStmt = mysqli_prepare($conn, "SELECT id_siswa, nama_lengkap FROM siswa WHERE rfid_uid = ? AND id_siswa <> ? LIMIT 1");
                if (!$dupStmt) {
                    $flash = ['type' => 'danger', 'message' => 'Gagal memvalidasi duplikasi UID.'];
                } else {
                    mysqli_stmt_bind_param($dupStmt, 'si', $uid, $idSiswa);
                    mysqli_stmt_execute($dupStmt);
                    $dupResult = mysqli_stmt_get_result($dupStmt);
                    $dupRow = $dupResult ? mysqli_fetch_assoc($dupResult) : null;
                    mysqli_stmt_close($dupStmt);

                    if ($dupRow) {
                        $flash = [
                            'type' => 'danger',
                            'message' => 'UID RFID sudah dipakai oleh siswa lain: ' . ($dupRow['nama_lengkap'] ?? 'Unknown')
                        ];
                    } else {
                        $studentStmt = mysqli_prepare($conn, "SELECT id_siswa FROM siswa WHERE id_siswa = ? LIMIT 1");
                        if (!$studentStmt) {
                            $flash = ['type' => 'danger', 'message' => 'Gagal memuat data siswa.'];
                        } else {
                            mysqli_stmt_bind_param($studentStmt, 'i', $idSiswa);
                            mysqli_stmt_execute($studentStmt);
                            mysqli_stmt_store_result($studentStmt);
                            $exists = mysqli_stmt_num_rows($studentStmt) > 0;
                            mysqli_stmt_close($studentStmt);

                            if (!$exists) {
                                $flash = ['type' => 'danger', 'message' => 'Siswa tidak ditemukan.'];
                            } else {
                                $stmt = mysqli_prepare($conn, "UPDATE siswa SET rfid_uid = ? WHERE id_siswa = ? LIMIT 1");
                                if ($stmt) {
                                    mysqli_stmt_bind_param($stmt, 'si', $uid, $idSiswa);
                                    mysqli_stmt_execute($stmt);
                                    mysqli_stmt_close($stmt);
                                    $flash = ['type' => 'success', 'message' => 'UID RFID berhasil didaftarkan ke siswa.'];
                                } else {
                                    $flash = ['type' => 'danger', 'message' => 'Gagal menyimpan UID RFID.'];
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

$students = [];
$studentResult = mysqli_query(
    $conn,
    "SELECT s.id_siswa, s.nama_lengkap, s.nisn, s.rfid_uid, s.status_siswa, k.nama_kelas
     FROM siswa s
     LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
     WHERE s.status_siswa IS NULL OR s.status_siswa <> 'hapus'
     ORDER BY s.nama_lengkap ASC"
);
if ($studentResult) {
    while ($row = mysqli_fetch_assoc($studentResult)) {
        $students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Registrasi RFID | Absensi Siswa</title>
    <link href="../assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/site.css" rel="stylesheet">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">Registrasi Kartu RFID</h3>
            <p class="text-muted mb-0">RFID as Key -> Database as Source of Truth</p>
        </div>
        <a href="siswa.php" class="btn btn-outline-secondary">Kembali ke Siswa</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= SecurityHelper::escapeHTML($flash['type']); ?>"><?= SecurityHelper::escapeHTML($flash['message']); ?></div>
    <?php endif; ?>

    <div class="card p-3 mb-4">
        <h5 class="mb-3">Pasangkan UID RFID ke Siswa</h5>
        <form method="POST" class="row g-3">
            <?= CSRFProtection::getTokenField(); ?>
            <input type="hidden" name="action" value="assign">

            <div class="col-md-4">
                <label class="form-label">UID RFID (hasil scan)</label>
                <input type="text" name="rfid_uid" class="form-control" placeholder="Contoh: 04A1B2C3D4" maxlength="64" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Pilih Siswa</label>
                <select name="id_siswa" class="form-select" required>
                    <option value="">-- Pilih Siswa --</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= (int)$s['id_siswa']; ?>">
                            <?= SecurityHelper::escapeHTML($s['nama_lengkap']); ?>
                            | NISN: <?= SecurityHelper::escapeHTML((string)($s['nisn'] ?? '-')); ?>
                            | Kelas: <?= SecurityHelper::escapeHTML((string)($s['nama_kelas'] ?? '-')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 d-grid align-items-end">
                <button class="btn btn-primary">Simpan UID</button>
            </div>
        </form>
    </div>

    <div class="card p-3">
        <h5 class="mb-3">Daftar Mapping RFID</h5>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>NISN</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th>UID RFID</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="6" class="text-center text-muted">Belum ada data siswa.</td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td><?= SecurityHelper::escapeHTML($s['nama_lengkap']); ?></td>
                                <td><?= SecurityHelper::escapeHTML((string)($s['nisn'] ?? '-')); ?></td>
                                <td><?= SecurityHelper::escapeHTML((string)($s['nama_kelas'] ?? '-')); ?></td>
                                <td><?= SecurityHelper::escapeHTML((string)($s['status_siswa'] ?? 'aktif')); ?></td>
                                <td>
                                    <?php if (!empty($s['rfid_uid'])): ?>
                                        <code><?= SecurityHelper::escapeHTML((string)$s['rfid_uid']); ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">Belum terdaftar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($s['rfid_uid'])): ?>
                                        <form method="POST" class="d-inline">
                                            <?= CSRFProtection::getTokenField(); ?>
                                            <input type="hidden" name="action" value="unlink">
                                            <input type="hidden" name="id_siswa" value="<?= (int)$s['id_siswa']; ?>">
                                            <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Lepas UID RFID dari siswa ini?')">Lepas UID</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="../assets/vendor/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<?php include '../includes/footer.php'; ?>
</body>
</html>
