<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';

cek_login();
CSRFProtection::init();

if (!isset($_SESSION['mass_class_update'])) {
    $_SESSION['mass_class_update'] = [];
}

if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=template_naik_kelas_massal_' . date('Ymd') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, "\xEF\xBB\xBF");
    fputcsv($output, ['nisn', 'nama', 'kelas_baru']);
    fputcsv($output, ['1234567890', 'BUDI SANTOSO', '8A']);
    fputcsv($output, ['1234567891', 'SRI ASTUTIK', '8B']);
    fclose($output);
    exit;
}

function normalize_class_header(string $value): string
{
    $value = trim(strtolower($value));
    $value = preg_replace('/\s+/', '_', $value);
    return $value;
}

function parse_csv_rows(string $filePath): array
{
    $rows = [];
    $errors = [];
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return ['rows' => [], 'errors' => ['Gagal membaca file CSV.']];
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return ['rows' => [], 'errors' => ['Header CSV tidak ditemukan.']];
    }

    $headerMap = array_map('normalize_class_header', $header);

    $line = 1;
    while (($data = fgetcsv($handle)) !== false) {
        $line++;
        if (count(array_filter($data, static fn($v) => trim((string)$v) !== '')) === 0) {
            continue;
        }

        $row = [];
        foreach ($headerMap as $idx => $key) {
            $row[$key] = trim((string)($data[$idx] ?? ''));
        }
        $row['_line'] = $line;
        $rows[] = $row;
    }

    fclose($handle);
    return ['rows' => $rows, 'errors' => $errors];
}

function parse_xlsx_rows(string $filePath): array
{
    $errors = [];
    if (!class_exists('ZipArchive')) {
        return ['rows' => [], 'errors' => ['Server belum mendukung pembacaan file XLSX (ZipArchive tidak tersedia).']];
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return ['rows' => [], 'errors' => ['File XLSX tidak dapat dibuka.']];
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = @simplexml_load_string($sharedXml);
        if ($xml && isset($xml->si)) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } else {
                    $text = '';
                    if (isset($si->r)) {
                        foreach ($si->r as $run) {
                            $text .= (string)$run->t;
                        }
                    }
                    $sharedStrings[] = $text;
                }
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if ($sheetXml === false) {
        return ['rows' => [], 'errors' => ['Worksheet pertama (sheet1) tidak ditemukan pada file XLSX.']];
    }

    $sheet = @simplexml_load_string($sheetXml);
    if (!$sheet || !isset($sheet->sheetData->row)) {
        return ['rows' => [], 'errors' => ['Format worksheet XLSX tidak valid.']];
    }

    $rawRows = [];
    foreach ($sheet->sheetData->row as $rowNode) {
        $line = (int)($rowNode['r'] ?? 0);
        $values = [];

        foreach ($rowNode->c as $cell) {
            $ref = (string)($cell['r'] ?? '');
            $colLetters = preg_replace('/\d+/', '', $ref);
            $colIndex = 0;
            $letters = str_split($colLetters);
            foreach ($letters as $ch) {
                $colIndex = ($colIndex * 26) + (ord($ch) - 64);
            }
            $colIndex = max(1, $colIndex) - 1;

            $cellType = (string)($cell['t'] ?? '');
            $value = '';
            if ($cellType === 's') {
                $stringIndex = (int)($cell->v ?? -1);
                $value = $sharedStrings[$stringIndex] ?? '';
            } else {
                $value = (string)($cell->v ?? '');
            }
            $values[$colIndex] = trim($value);
        }

        if (!empty($values)) {
            ksort($values);
            $rawRows[] = ['line' => $line, 'values' => $values];
        }
    }

    if (count($rawRows) === 0) {
        return ['rows' => [], 'errors' => ['Tidak ada data ditemukan pada worksheet.']];
    }

    $header = $rawRows[0]['values'];
    $maxIndex = max(array_keys($header));
    $headerMap = [];
    for ($i = 0; $i <= $maxIndex; $i++) {
        $headerMap[$i] = normalize_class_header((string)($header[$i] ?? ''));
    }

    $rows = [];
    for ($i = 1; $i < count($rawRows); $i++) {
        $values = $rawRows[$i]['values'];
        if (count(array_filter($values, static fn($v) => trim((string)$v) !== '')) === 0) {
            continue;
        }

        $row = [];
        foreach ($headerMap as $idx => $key) {
            $row[$key] = trim((string)($values[$idx] ?? ''));
        }
        $row['_line'] = $rawRows[$i]['line'] ?: ($i + 1);
        $rows[] = $row;
    }

    return ['rows' => $rows, 'errors' => $errors];
}

function parse_uploaded_class_file(array $file): array
{
    $name = strtolower((string)($file['name'] ?? ''));
    $tmp = (string)($file['tmp_name'] ?? '');
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $size = (int)($file['size'] ?? 0);

    if ($error !== UPLOAD_ERR_OK || $tmp === '') {
        return ['rows' => [], 'errors' => ['Upload file gagal.']];
    }

    if (!is_uploaded_file($tmp)) {
        return ['rows' => [], 'errors' => ['Sumber file upload tidak valid.']];
    }

    if ($size <= 0 || $size > 10 * 1024 * 1024) {
        return ['rows' => [], 'errors' => ['Ukuran file tidak valid (maksimal 10MB).']];
    }

    $extension = pathinfo($name, PATHINFO_EXTENSION);
    if ($extension === 'csv') {
        return parse_csv_rows($tmp);
    }
    if ($extension === 'xlsx') {
        return parse_xlsx_rows($tmp);
    }

    return ['rows' => [], 'errors' => ['Format file belum didukung. Gunakan CSV atau XLSX.']];
}

function get_active_tahun_ajaran_id(mysqli $conn): ?int
{
    $result = mysqli_query($conn, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE is_active = 1 AND status = 'aktif' ORDER BY id_tahun_ajaran DESC LIMIT 1");
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return (int)$row['id_tahun_ajaran'];
    }

    return null;
}

function build_mass_update_preview(mysqli $conn, array $rows): array
{
    $errors = [];
    $updates = [];
    $inserts = [];
    $unchanged = [];

    $classMap = [];
    $classResult = mysqli_query($conn, 'SELECT id_kelas, nama_kelas FROM kelas');
    if ($classResult) {
        while ($row = mysqli_fetch_assoc($classResult)) {
            $classMap[strtoupper(trim((string)$row['nama_kelas']))] = [
                'id_kelas' => (int)$row['id_kelas'],
                'nama_kelas' => (string)$row['nama_kelas'],
            ];
        }
    }

    $studentMap = [];
    $studentResult = mysqli_query($conn, "SELECT s.id_siswa, s.student_uuid, s.nisn, s.nis, s.nama_lengkap, s.id_kelas, k.nama_kelas
        FROM siswa s
        LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
        WHERE s.status_siswa IS NULL OR s.status_siswa != 'hapus'");
    if ($studentResult) {
        while ($row = mysqli_fetch_assoc($studentResult)) {
            $studentMap[(string)$row['nisn']] = $row;
        }
    }

    $seenNisn = [];
    foreach ($rows as $row) {
        $line = (int)($row['_line'] ?? 0);
        $nisn = preg_replace('/\D+/', '', (string)($row['nisn'] ?? ''));
        $nama = trim((string)($row['nama'] ?? $row['nama_lengkap'] ?? ''));
        $kelasBaru = strtoupper(trim((string)($row['kelas_baru'] ?? $row['kelas'] ?? '')));

        if ($nisn === '' || strlen($nisn) < 5 || strlen($nisn) > 20) {
            $errors[] = "Baris {$line}: NISN tidak valid.";
            continue;
        }

        if (isset($seenNisn[$nisn])) {
            $errors[] = "Baris {$line}: NISN {$nisn} duplikat di file (juga muncul di baris {$seenNisn[$nisn]}).";
            continue;
        }
        $seenNisn[$nisn] = $line;

        if ($kelasBaru === '' || !isset($classMap[$kelasBaru])) {
            $errors[] = "Baris {$line}: Kelas baru tidak valid untuk NISN {$nisn}.";
            continue;
        }

        $targetClass = $classMap[$kelasBaru];
        if (isset($studentMap[$nisn])) {
            $student = $studentMap[$nisn];
            $currentClassId = (int)($student['id_kelas'] ?? 0);
            if ($currentClassId === (int)$targetClass['id_kelas']) {
                $unchanged[] = [
                    'line' => $line,
                    'nisn' => $nisn,
                    'nama' => $student['nama_lengkap'],
                    'kelas' => $targetClass['nama_kelas'],
                ];
                continue;
            }

            $updates[] = [
                'line' => $line,
                'id_siswa' => (int)$student['id_siswa'],
                'student_uuid' => (string)($student['student_uuid'] ?? ''),
                'nisn' => $nisn,
                'nama' => $student['nama_lengkap'],
                'kelas_lama' => (string)($student['nama_kelas'] ?? '-'),
                'id_kelas_baru' => (int)$targetClass['id_kelas'],
                'kelas_baru' => $targetClass['nama_kelas'],
            ];
        } else {
            if ($nama === '') {
                $errors[] = "Baris {$line}: Siswa baru NISN {$nisn} harus memiliki nama.";
                continue;
            }

            $inserts[] = [
                'line' => $line,
                'nisn' => $nisn,
                'nama' => $nama,
                'id_kelas_baru' => (int)$targetClass['id_kelas'],
                'kelas_baru' => $targetClass['nama_kelas'],
            ];
        }
    }

    return [
        'updates' => $updates,
        'inserts' => $inserts,
        'unchanged' => $unchanged,
        'errors' => $errors,
        'stats' => [
            'total' => count($rows),
            'update' => count($updates),
            'insert' => count($inserts),
            'unchanged' => count($unchanged),
            'invalid' => count($errors),
        ],
    ];
}

function generate_auto_nis(mysqli $conn, string $nisn): string
{
    $digits = preg_replace('/\D+/', '', $nisn);
    $base = 'AUTO' . substr(str_pad($digits, 8, '0', STR_PAD_LEFT), -8);

    for ($i = 0; $i < 100; $i++) {
        $candidate = $i === 0 ? $base : ($base . str_pad((string)$i, 2, '0', STR_PAD_LEFT));
        $stmt = mysqli_prepare($conn, 'SELECT id_siswa FROM siswa WHERE nis = ? LIMIT 1');
        if (!$stmt) {
            return $candidate;
        }
        mysqli_stmt_bind_param($stmt, 's', $candidate);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $exists = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        if (!$exists) {
            return $candidate;
        }
    }

    return 'AUTO' . date('His') . substr($digits, -4);
}

function apply_mass_update(mysqli $conn, array $preview): array
{
    $appliedUpdate = 0;
    $appliedInsert = 0;

    $activeYearId = get_active_tahun_ajaran_id($conn);
    if (!$activeYearId) {
        return [
            'success' => false,
            'message' => 'Tidak ada semester aktif. Silakan aktifkan semester terlebih dahulu.',
        ];
    }

    mysqli_begin_transaction($conn);
    try {
        $updateStmt = mysqli_prepare($conn, "UPDATE siswa SET id_kelas = ?, status_siswa = 'aktif' WHERE id_siswa = ?");
        $insertStmt = mysqli_prepare($conn, "INSERT INTO siswa (student_uuid, nis, nisn, nama_lengkap, jk, id_kelas, status_siswa, foto) VALUES (?, ?, ?, ?, ?, ?, 'aktif', 'default.png')");
        $linkStmt = mysqli_prepare($conn, "INSERT INTO siswa_kelas (id_siswa, id_kelas, id_tahun_ajaran, status) VALUES (?, ?, ?, 'aktif') ON DUPLICATE KEY UPDATE id_kelas = VALUES(id_kelas), status = VALUES(status), updated_at = CURRENT_TIMESTAMP");

        if (!$updateStmt || !$insertStmt || !$linkStmt) {
            throw new RuntimeException('Gagal menyiapkan statement database.');
        }

        foreach ($preview['updates'] as $row) {
            $idKelasBaru = (int)$row['id_kelas_baru'];
            $idSiswa = (int)$row['id_siswa'];
            mysqli_stmt_bind_param($updateStmt, 'ii', $idKelasBaru, $idSiswa);
            if (!mysqli_stmt_execute($updateStmt)) {
                throw new RuntimeException('Gagal memperbarui kelas siswa ID ' . $idSiswa);
            }

            if ($activeYearId) {
                mysqli_stmt_bind_param($linkStmt, 'iii', $idSiswa, $idKelasBaru, $activeYearId);
                if (!mysqli_stmt_execute($linkStmt)) {
                    throw new RuntimeException('Gagal sinkron riwayat kelas siswa ID ' . $idSiswa);
                }
            }
            $appliedUpdate++;
        }

        foreach ($preview['inserts'] as $row) {
            $studentUuid = generate_uuid_v4();
            $nisn = (string)$row['nisn'];
            $nama = (string)$row['nama'];
            $nis = generate_auto_nis($conn, $nisn);
            $jk = null;
            $idKelasBaru = (int)$row['id_kelas_baru'];

            mysqli_stmt_bind_param($insertStmt, 'sssssi', $studentUuid, $nis, $nisn, $nama, $jk, $idKelasBaru);
            if (!mysqli_stmt_execute($insertStmt)) {
                throw new RuntimeException('Gagal menambah siswa baru NISN ' . $nisn);
            }

            $idSiswaBaru = (int)mysqli_insert_id($conn);
            if ($activeYearId) {
                mysqli_stmt_bind_param($linkStmt, 'iii', $idSiswaBaru, $idKelasBaru, $activeYearId);
                if (!mysqli_stmt_execute($linkStmt)) {
                    throw new RuntimeException('Gagal sinkron riwayat kelas siswa baru NISN ' . $nisn);
                }
            }

            $appliedInsert++;
        }

        mysqli_stmt_close($updateStmt);
        mysqli_stmt_close($insertStmt);
        mysqli_stmt_close($linkStmt);

        mysqli_commit($conn);

        return [
            'success' => true,
            'updated' => $appliedUpdate,
            'inserted' => $appliedInsert,
            'unchanged' => count($preview['unchanged']),
            'invalid' => count($preview['errors']),
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        return [
            'success' => false,
            'message' => $e->getMessage(),
        ];
    }
}

$alert = null;
$previewData = null;
$applyResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
        $alert = ['type' => 'danger', 'message' => 'CSRF token tidak valid. Silakan refresh halaman.'];
    } else {
        $action = trim((string)($_POST['action'] ?? 'preview'));

        if ($action === 'preview') {
            $parsed = parse_uploaded_class_file($_FILES['file_kelas'] ?? []);
            if (!empty($parsed['errors'])) {
                $alert = ['type' => 'danger', 'message' => implode(' ', $parsed['errors'])];
            } else {
                $previewData = build_mass_update_preview($conn, $parsed['rows']);
                $token = bin2hex(random_bytes(24));
                $_SESSION['mass_class_update'][$token] = [
                    'created_at' => time(),
                    'preview' => $previewData,
                ];
                $previewData['token'] = $token;
            }
        }

        if ($action === 'apply') {
            $token = trim((string)($_POST['preview_token'] ?? ''));
            $stored = $_SESSION['mass_class_update'][$token] ?? null;

            if (!$stored) {
                $alert = ['type' => 'warning', 'message' => 'Data preview tidak ditemukan atau sudah kadaluarsa. Silakan upload ulang file.'];
            } else {
                $applyResult = apply_mass_update($conn, $stored['preview']);
                unset($_SESSION['mass_class_update'][$token]);
                if ($applyResult['success']) {
                    $alert = [
                        'type' => 'success',
                        'message' => 'Proses selesai. Kelas siswa berhasil diperbarui tanpa mengubah ID siswa atau kartu QR.',
                    ];
                } else {
                    $alert = ['type' => 'danger', 'message' => 'Gagal menerapkan perubahan: ' . ($applyResult['message'] ?? 'Unknown error')];
                }
            }
        }
    }
}

if (!$previewData && isset($_GET['token'])) {
    $token = trim((string)$_GET['token']);
    if (isset($_SESSION['mass_class_update'][$token])) {
        $previewData = $_SESSION['mass_class_update'][$token]['preview'];
        $previewData['token'] = $token;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Naik Kelas Massal | SMPN 1 Indonesia</title>
    <link href="../assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='../assets/css/site.css' rel='stylesheet'>
    <style>
        body { background: #f7f8fb; }
        .card-clean { border: none; border-radius: 14px; box-shadow: 0 8px 25px rgba(0,0,0,.06); }
        .stat-box { border-radius: 10px; padding: 14px; background: #f2f5fa; }
        .table-preview th { font-size: .8rem; text-transform: uppercase; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">Naik Kelas Massal</h3>
            <p class="text-muted mb-0">Import Data → Match by NISN → Update Class → Preserve Identity</p>
        </div>
        <a href="siswa.php" class="btn btn-outline-secondary">Kembali ke Siswa</a>
    </div>

    <?php if ($alert): ?>
        <div class="alert alert-<?= htmlspecialchars($alert['type']); ?>"><?= htmlspecialchars($alert['message']); ?></div>
    <?php endif; ?>

    <div class="card card-clean p-4 mb-4">
        <h5 class="mb-3">Upload File Kenaikan Kelas</h5>
        <p class="text-muted small mb-3">Format kolom: <b>NISN</b>, <b>Nama</b>, <b>Kelas Baru</b>. File dapat berupa CSV atau XLSX dari data resmi sekolah.</p>
        <div class="mb-3">
            <a class="btn btn-sm btn-outline-success" href="?download_template=1">Download Template</a>
        </div>
        <form method="POST" enctype="multipart/form-data" class="row g-3">
            <?= CSRFProtection::getTokenField(); ?>
            <input type="hidden" name="action" value="preview">
            <div class="col-md-8">
                <input type="file" class="form-control" name="file_kelas" accept=".csv,.xlsx" required>
            </div>
            <div class="col-md-4 d-grid">
                <button class="btn btn-primary">Preview Perubahan</button>
            </div>
        </form>
    </div>

    <?php if ($previewData): ?>
        <div class="card card-clean p-4 mb-4">
            <h5 class="mb-3">Preview Perubahan</h5>
            <div class="row g-3 mb-3">
                <div class="col-md-2"><div class="stat-box text-center"><div class="fw-bold fs-4"><?= (int)$previewData['stats']['total']; ?></div><div class="small text-muted">Total Baris</div></div></div>
                <div class="col-md-2"><div class="stat-box text-center"><div class="fw-bold fs-4 text-primary"><?= (int)$previewData['stats']['update']; ?></div><div class="small text-muted">Update Kelas</div></div></div>
                <div class="col-md-2"><div class="stat-box text-center"><div class="fw-bold fs-4 text-success"><?= (int)$previewData['stats']['insert']; ?></div><div class="small text-muted">Siswa Baru</div></div></div>
                <div class="col-md-2"><div class="stat-box text-center"><div class="fw-bold fs-4 text-secondary"><?= (int)$previewData['stats']['unchanged']; ?></div><div class="small text-muted">Tidak Berubah</div></div></div>
                <div class="col-md-2"><div class="stat-box text-center"><div class="fw-bold fs-4 text-danger"><?= (int)$previewData['stats']['invalid']; ?></div><div class="small text-muted">Invalid</div></div></div>
            </div>

            <?php if (!empty($previewData['updates'])): ?>
                <h6>Siswa Lama - Akan Update Kelas</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered table-preview">
                        <thead><tr><th>NISN</th><th>Nama</th><th>Kelas Lama</th><th>Kelas Baru</th><th>UUID</th></tr></thead>
                        <tbody>
                        <?php foreach ($previewData['updates'] as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nisn']); ?></td>
                                <td><?= htmlspecialchars($row['nama']); ?></td>
                                <td><?= htmlspecialchars($row['kelas_lama']); ?></td>
                                <td><b><?= htmlspecialchars($row['kelas_baru']); ?></b></td>
                                <td><code><?= htmlspecialchars($row['student_uuid'] ?: '-'); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($previewData['inserts'])): ?>
                <h6>Siswa Baru - Akan Ditambahkan</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered table-preview">
                        <thead><tr><th>NISN</th><th>Nama</th><th>Kelas Baru</th></tr></thead>
                        <tbody>
                        <?php foreach ($previewData['inserts'] as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nisn']); ?></td>
                                <td><?= htmlspecialchars($row['nama']); ?></td>
                                <td><b><?= htmlspecialchars($row['kelas_baru']); ?></b></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($previewData['errors'])): ?>
                <h6>Data Invalid</h6>
                <ul class="small text-danger">
                    <?php foreach ($previewData['errors'] as $message): ?>
                        <li><?= htmlspecialchars($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="POST" class="mt-3">
                <?= CSRFProtection::getTokenField(); ?>
                <input type="hidden" name="action" value="apply">
                <input type="hidden" name="preview_token" value="<?= htmlspecialchars($previewData['token']); ?>">
                <button class="btn btn-success" <?= ($previewData['stats']['update'] + $previewData['stats']['insert']) === 0 ? 'disabled' : ''; ?>>
                    Konfirmasi & Proses Update Massal
                </button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($applyResult && $applyResult['success']): ?>
        <div class="card card-clean p-4">
            <h5>Ringkasan Hasil Proses</h5>
            <ul class="mb-0">
                <li>Update kelas siswa lama: <b><?= (int)$applyResult['updated']; ?></b></li>
                <li>Tambah siswa baru: <b><?= (int)$applyResult['inserted']; ?></b></li>
                <li>Data tidak berubah: <b><?= (int)$applyResult['unchanged']; ?></b></li>
                <li>Data invalid (dilewati): <b><?= (int)$applyResult['invalid']; ?></b></li>
                <li>ID siswa lama tetap dipertahankan, kartu QR lama tetap valid.</li>
            </ul>
        </div>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
