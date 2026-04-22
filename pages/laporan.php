<?php 
require_once __DIR__ . '/../includes/config.php'; 
cek_login(); 

function normalizeDateInput(string $value, string $fallback): string {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $fallback;
}

$tgl_mulai = normalizeDateInput($_GET['tgl_mulai'] ?? date('Y-m-01'), date('Y-m-01'));
$tgl_selesai = normalizeDateInput($_GET['tgl_selesai'] ?? date('Y-m-d'), date('Y-m-d'));
$id_kelas = isset($_GET['id_kelas']) && ctype_digit((string) $_GET['id_kelas']) ? (string) $_GET['id_kelas'] : '';

$kelas_label = '';
if ($id_kelas !== '') {
    $kelasStmt = mysqli_prepare($conn, "SELECT nama_kelas FROM kelas WHERE id_kelas = ?");
    $idKelasInt = (int) $id_kelas;
    mysqli_stmt_bind_param($kelasStmt, 'i', $idKelasInt);
    mysqli_stmt_execute($kelasStmt);
    $kelasResult = mysqli_stmt_get_result($kelasStmt);
    $kelas_data = mysqli_fetch_assoc($kelasResult);
    $kelas_label = $kelas_data['nama_kelas'] ?? '';
    mysqli_stmt_close($kelasStmt);
}

$page_title = 'Laporan Absensi | E-Absensi SMP';
$extra_head = <<<'CSS'
<style>
    body { background: #f8f9fa; }
    .no-print { display: inline-block; }
    @media print {
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body { background: #fff !important; color: #000 !important; font-size: 11pt !important; }
        .no-print, .app-navbar, nav.navbar, .site-footer, .btn, .btn-group,
        .form-control, .form-select, .card.p-3 { display: none !important; }
        .container { max-width: 100% !important; width: 100% !important; padding: 0 6mm !important; margin: 0 !important; }
        .card { border: none !important; box-shadow: none !important; margin: 0 0 8px 0 !important; padding: 0 !important; }
        .card-body { padding: 0 !important; }
        .table { width: 100% !important; border-collapse: collapse !important; font-size: 9pt !important; }
        .table th, .table td { border: 1px solid #333 !important; padding: 3px 5px !important; vertical-align: middle !important; }
        .table thead th { background: #2c3e50 !important; color: #fff !important; font-size: 9pt !important; }
        .badge { border: 1px solid #666 !important; padding: 2px 5px !important; font-size: 8pt !important; border-radius: 3px !important; }
        .badge.bg-success { background: #28a745 !important; color: #fff !important; }
        .badge.bg-danger  { background: #dc3545 !important; color: #fff !important; }
        .badge.bg-warning { background: #ffc107 !important; color: #000 !important; }
        .print-header { display: block !important; text-align: center; margin-bottom: 10mm; }
        .print-header h2 { font-size: 14pt !important; font-weight: bold; margin-bottom: 2px; }
        .print-header p  { font-size: 10pt !important; margin: 1px 0; }
        .page-break { page-break-after: always; }
        @page { size: A4 portrait; margin: 15mm 12mm; }
    }
</style>
CSS;

$sql = "SELECT a.*, s.nis, s.nama_lengkap, k.nama_kelas, st.nama_status
        FROM absensi a
        JOIN siswa s ON a.id_siswa = s.id_siswa
        JOIN kelas k ON s.id_kelas = k.id_kelas
        JOIN status_absen st ON a.id_status = st.id_status
        WHERE a.tanggal BETWEEN ? AND ?";

if ($id_kelas !== '') {
    $sql .= " AND s.id_kelas = ?";
}

$sql .= " ORDER BY a.tanggal DESC";
$stmt = mysqli_prepare($conn, $sql);

if ($id_kelas !== '') {
    $idKelasInt = (int) $id_kelas;
    mysqli_stmt_bind_param($stmt, 'ssi', $tgl_mulai, $tgl_selesai, $idKelasInt);
} else {
    mysqli_stmt_bind_param($stmt, 'ss', $tgl_mulai, $tgl_selesai);
}

mysqli_stmt_execute($stmt);
$data = mysqli_stmt_get_result($stmt);

include '../includes/header.php';
?>

<body class="bg-light">

<?php include '../includes/navbar.php'; ?>

<div class="container py-4">
    <div class="print-header" style="display:none;">
        <div class="text-center mb-4">
            <h2 class="fw-bold mb-1">ABSENSI SMPN 1</h2>
            <p class="mb-1">Laporan Absensi</p>
            <p class="small text-muted">Periode: <?= date('d/m/Y', strtotime($tgl_mulai)) ?> - <?= date('d/m/Y', strtotime($tgl_selesai)) ?><?= $kelas_label ? ' | Kelas: '.htmlspecialchars($kelas_label) : '' ?></p>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2 class="fw-bold">Laporan Absensi</h2>
        <div class="btn-group shadow-sm">
            <a href="export_excel.php?tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>&id_kelas=<?= $id_kelas ?>" class="btn btn-success fw-bold">
            <a href="export_excel.php?tgl_mulai=<?= urlencode($tgl_mulai) ?>&tgl_selesai=<?= urlencode($tgl_selesai) ?>&id_kelas=<?= urlencode($id_kelas) ?>" class="btn btn-success fw-bold">
                <i class="fas fa-file-excel me-2"></i>Excel
            </a>
            <button onclick="window.print()" class="btn btn-dark fw-bold">
                <i class="fas fa-print me-2"></i>Cetak
            </button>
        </div>
    </div>

    <div class="card p-3 shadow-sm border-0 mb-4 no-print">
        <form method="GET" class="row g-2">
            <div class="col-md-3"><input type="date" name="tgl_mulai" class="form-control" value="<?= $tgl_mulai ?>"></div>
            <div class="col-md-3"><input type="date" name="tgl_selesai" class="form-control" value="<?= $tgl_selesai ?>"></div>
            <div class="col-md-4">
                <select name="id_kelas" class="form-select">
                    <option value="">Semua Kelas</option>
                    <?php
                    $kls = mysqli_query($conn, "SELECT * FROM kelas ORDER BY nama_kelas ASC");
                    while ($rk = mysqli_fetch_assoc($kls)): ?>
                        <option value="<?= $rk['id_kelas']; ?>" <?= $id_kelas === $rk['id_kelas'] ? 'selected' : ''; ?>><?= htmlspecialchars($rk['nama_kelas']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100 fw-bold">Filter</button></div>
        </form>
    </div>

    <div class="card shadow-sm border-0">
        <table class="table table-bordered mb-0">
            <thead class="table-dark"><tr><th>Tgl</th><th>NIS</th><th>Nama</th><th>Kelas</th><th>Status</th></tr></thead>
            <tbody>
                <?php while($r = mysqli_fetch_assoc($data)): ?>
                <tr>
                    <td><?= date('d/m/y', strtotime($r['tanggal'])) ?></td>
                    <td><?= htmlspecialchars($r['nis']) ?></td>
                    <td><?= htmlspecialchars($r['nama_lengkap']) ?></td>
                    <td><?= htmlspecialchars($r['nama_kelas']) ?></td>
                    <td><span class="badge bg-<?= $r['nama_status']=='HADIR'?'success':'danger' ?>"><?= htmlspecialchars($r['nama_status']) ?></span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
<?php mysqli_stmt_close($stmt); ?>
</body>
</html>

