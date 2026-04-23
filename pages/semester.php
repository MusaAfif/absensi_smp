<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';

cek_role(['super_admin']);
CSRFProtection::init();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function build_semester_dates(string $namaTahun, string $semester): ?array
{
    if (!preg_match('/^(\d{4})\/(\d{4})$/', $namaTahun, $m)) {
        return null;
    }

    $y1 = (int) $m[1];
    $y2 = (int) $m[2];
    if ($y2 !== $y1 + 1) {
        return null;
    }

    if ($semester === 'ganjil') {
        return [
            'mulai' => sprintf('%04d-07-01', $y1),
            'selesai' => sprintf('%04d-12-31', $y1),
        ];
    }

    return [
        'mulai' => sprintf('%04d-01-01', $y2),
        'selesai' => sprintf('%04d-06-30', $y2),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['semester_msg'] = ['type' => 'danger', 'text' => 'CSRF token tidak valid.'];
        header('Location: semester.php');
        exit;
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'add') {
        $namaTahun = trim((string) ($_POST['nama_tahun_ajaran'] ?? ''));
        $semester = trim((string) ($_POST['semester'] ?? ''));

        if (!in_array($semester, ['ganjil', 'genap'], true)) {
            $_SESSION['semester_msg'] = ['type' => 'danger', 'text' => 'Semester tidak valid.'];
            header('Location: semester.php');
            exit;
        }

        $dates = build_semester_dates($namaTahun, $semester);
        if (!$dates) {
            $_SESSION['semester_msg'] = ['type' => 'danger', 'text' => 'Format tahun ajaran harus YYYY/YYYY dan berurutan, contoh 2025/2026.'];
            header('Location: semester.php');
            exit;
        }

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO tahun_ajaran (nama_tahun_ajaran, semester, tanggal_mulai, tanggal_selesai, is_active, status)
             VALUES (?, ?, ?, ?, 0, 'tidak')"
        );

        if (!$stmt) {
            $_SESSION['semester_msg'] = ['type' => 'danger', 'text' => 'Gagal menyiapkan query tambah semester.'];
            header('Location: semester.php');
            exit;
        }

        mysqli_stmt_bind_param($stmt, 'ssss', $namaTahun, $semester, $dates['mulai'], $dates['selesai']);
        $ok = mysqli_stmt_execute($stmt);
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) {
            $_SESSION['semester_msg'] = ['type' => 'success', 'text' => 'Semester berhasil ditambahkan.'];
        } else {
            $_SESSION['semester_msg'] = ['type' => 'danger', 'text' => 'Gagal menambah semester: ' . $err];
        }

        header('Location: semester.php');
        exit;
    }

    if ($action === 'activate') {
        $id = (int) ($_POST['id_tahun_ajaran'] ?? 0);
        if ($id <= 0) {
            $_SESSION['semester_msg'] = ['type' => 'danger', 'text' => 'ID semester tidak valid.'];
            header('Location: semester.php');
            exit;
        }

        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, "UPDATE tahun_ajaran SET is_active = 0, status = 'tidak'");

            $stmt = mysqli_prepare($conn, "UPDATE tahun_ajaran SET is_active = 1, status = 'aktif' WHERE id_tahun_ajaran = ?");
            if (!$stmt) {
                throw new RuntimeException('Gagal menyiapkan query aktivasi semester.');
            }
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (!mysqli_stmt_execute($stmt)) {
                $err = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                throw new RuntimeException('Gagal aktivasi semester: ' . $err);
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            $_SESSION['semester_msg'] = ['type' => 'success', 'text' => 'Semester aktif berhasil diperbarui.'];
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $_SESSION['semester_msg'] = ['type' => 'danger', 'text' => $e->getMessage()];
        }

        header('Location: semester.php');
        exit;
    }
}

$semesterRows = [];
$result = mysqli_query(
    $conn,
    "SELECT id_tahun_ajaran, nama_tahun_ajaran, semester, tanggal_mulai, tanggal_selesai, is_active, status
     FROM tahun_ajaran
     ORDER BY id_tahun_ajaran DESC"
);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $semesterRows[] = $row;
    }
}

$msg = $_SESSION['semester_msg'] ?? null;
unset($_SESSION['semester_msg']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Semester | Absensi Siswa</title>
    <link href="../assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/site.css" rel="stylesheet">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">Manajemen Semester Aktif</h3>
            <p class="text-muted mb-0">Manual Setup -> Automatic System. Satu klik untuk aktivasi semester.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">Kembali</a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= SecurityHelper::escapeHTML($msg['type']); ?>"><?= SecurityHelper::escapeHTML($msg['text']); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-3">
                <h5 class="mb-3">Tambah Semester</h5>
                <form method="POST">
                    <?= CSRFProtection::getTokenField(); ?>
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Tahun Ajaran</label>
                        <input type="text" class="form-control" name="nama_tahun_ajaran" placeholder="Contoh: 2025/2026" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Semester</label>
                        <select class="form-select" name="semester" required>
                            <option value="ganjil">Ganjil</option>
                            <option value="genap">Genap</option>
                        </select>
                    </div>
                    <button class="btn btn-primary w-100">Simpan Semester</button>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card p-3">
                <h5 class="mb-3">Daftar Semester</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Tahun Ajaran</th>
                                <th>Semester</th>
                                <th>Periode</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($semesterRows)): ?>
                            <tr><td colspan="5" class="text-center text-muted">Belum ada data semester.</td></tr>
                        <?php else: ?>
                            <?php foreach ($semesterRows as $row): ?>
                                <tr>
                                    <td><?= SecurityHelper::escapeHTML($row['nama_tahun_ajaran']); ?></td>
                                    <td><?= SecurityHelper::escapeHTML(ucfirst((string)$row['semester'])); ?></td>
                                    <td>
                                        <?= SecurityHelper::escapeHTML((string)$row['tanggal_mulai']); ?>
                                        s.d.
                                        <?= SecurityHelper::escapeHTML((string)$row['tanggal_selesai']); ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$row['is_active'] === 1): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Tidak Aktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$row['is_active'] === 1): ?>
                                            <button class="btn btn-sm btn-success" disabled>Sedang Aktif</button>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <?= CSRFProtection::getTokenField(); ?>
                                                <input type="hidden" name="action" value="activate">
                                                <input type="hidden" name="id_tahun_ajaran" value="<?= (int)$row['id_tahun_ajaran']; ?>">
                                                <button class="btn btn-sm btn-primary" onclick="return confirm('Aktifkan semester ini?')">Aktifkan</button>
                                            </form>
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
    </div>
</div>
<script src="../assets/vendor/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<?php include '../includes/footer.php'; ?>
</body>
</html>
