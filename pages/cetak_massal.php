<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/DatabaseHelper.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
cek_login();
require_once __DIR__ . '/../includes/qr_helper.php';

CSRFProtection::init();
$dbHelper = new DatabaseHelper($conn);

$id_filter = SecurityHelper::sanitizeInput($_GET['id_kelas'] ?? '');
if (!SecurityHelper::validateInteger($id_filter)) {
    $id_filter = '';
}

// Use prepared statement for safety
if (!empty($id_filter)) {
    $sql = "SELECT s.*, k.nama_kelas FROM siswa s 
            LEFT JOIN kelas k ON s.id_kelas = k.id_kelas 
            WHERE s.id_kelas = ? 
            ORDER BY k.nama_kelas ASC, s.nama_lengkap ASC";
    $query_result = $dbHelper->select($sql, [$id_filter], 'i');
} else {
    $sql = "SELECT s.*, k.nama_kelas FROM siswa s 
            LEFT JOIN kelas k ON s.id_kelas = k.id_kelas 
            ORDER BY k.nama_kelas ASC, s.nama_lengkap ASC";
    $query_result = $dbHelper->select($sql);
}

$jumlah_data = count($query_result);

// Ambil logo sekolah dan logo pemda dari pengaturan dengan prepared statement
$logoSettings = $dbHelper->select("SELECT nama_pengaturan, isi_pengaturan FROM pengaturan WHERE nama_pengaturan IN ('logo_sekolah','logo_pemda')");
$logoSekolah = '';
$logoPemda = '';
foreach ($logoSettings as $setting) {
    if ($setting['nama_pengaturan'] === 'logo_sekolah') {
        $logoSekolah = SecurityHelper::sanitizeInput($setting['isi_pengaturan']);
    }
    if ($setting['nama_pengaturan'] === 'logo_pemda') {
        $logoPemda = SecurityHelper::sanitizeInput($setting['isi_pengaturan']);
    }
}
?>
<?php
$page_title = 'Cetak Massal Kartu | E-Absensi SMP';
$extra_head = <<<'CSS'
<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap');

    body { font-family: 'Poppins', sans-serif; background: #f4f4f4; padding: 20px; margin: 0; }
    .no-print-area { background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ddd; }

    /* Grid untuk A4 Landscape */
    .grid-cetak { 
        display: grid; 
        grid-template-columns: repeat(2, 8.56cm); 
        gap: 15px; 
        justify-content: center; 
    }

    /* DESAIN KARTU SAMA PERSIS DENGAN CETAK_KARTU.PHP */
    .kartu-container { 
        width: 8.56cm; height: 5.4cm; 
        background: #fff; border-radius: 8px; 
        overflow: hidden; position: relative;
        border: 1px solid #ccc;
        page-break-inside: avoid;
    }

    .header { background: #003399; color: #fff; height: 28%; display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #FFD700; padding: 0 5px; }
    .logo-box { width: 18%; display: flex; align-items: center; justify-content: center; }
    .logo-box img { max-height: 1.9cm; max-width: 100%; object-fit: contain; }
    .header-text { flex: 1; text-align: center; padding: 0 5px; }
    .header-text h1 { margin: 0; font-size: 10pt; text-transform: uppercase; font-weight: 700; }
    .header-text p { margin: 0; font-size: 6pt; opacity: 0.9; }

    .main-body { display: flex; height: 60%; padding: 8px; align-items: center; }
    .col-foto { width: 30%; text-align: center; }
    .col-foto img { width: 2.1cm; height: 2.7cm; object-fit: cover; border: 1px solid #003399; border-radius: 3px; }

    .col-data { width: 45%; padding-left: 10px; }
    .col-data h2 { margin: 0 0 5px 0; font-size: 9pt; color: #003399; font-weight: 700; border-bottom: 1px solid #eee; text-transform: uppercase; }
    .data-table { font-size: 7pt; width: 100%; border-collapse: collapse; }
    .data-table td { padding: 1px 0; vertical-align: top; }
    .label { width: 35px; color: #666; font-weight: 600; }
    .val { color: #000; font-weight: 600; }

    .col-qr { width: 25%; text-align: center; }
    .col-qr img { width: 1.9cm; height: 1.9cm; display: block; margin: 0 auto; }
    .qr-label { font-size: 5pt; font-weight: bold; color: #003399; margin-top: 2px; }

    .footer { position: absolute; bottom: 0; width: 100%; height: 15%; background: #f8f9fa; display: flex; align-items: center; justify-content: center; border-top: 1px solid #eee; }
    .footer p { font-size: 5.5pt; color: #555; margin: 0; font-style: italic; }

    @media print {
        body { background: none; padding: 0; }
        .no-print-area { display: none !important; }
        .grid-cetak { gap: 10px; }
        .kartu-container { border: 1px solid #000; -webkit-print-color-adjust: exact; }
        .page-break { page-break-after: always; }
    }
</style>
CSS;

include '../includes/header.php';
?>


<div class="no-print-area container">
    <div class="row align-items-center">
        <div class="col-md-6">
            <form action="" method="GET" class="d-flex gap-2">
                <select name="id_kelas" class="form-select form-select-sm">
                    <option value="">Semua Kelas</option>
                    <?php 
                    $kelasList = $dbHelper->select("SELECT * FROM kelas ORDER BY nama_kelas ASC");
                    foreach($kelasList as $rk): ?>
                        <option value="<?= SecurityHelper::escapeHTML($rk['id_kelas']); ?>" <?= ($id_filter == $rk['id_kelas']) ? 'selected' : ''; ?>><?= SecurityHelper::escapeHTML($rk['nama_kelas']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-primary fw-bold">FILTER</button>
            </form>
        </div>
        <div class="col-md-6 text-end">
            <button onclick="window.print()" class="btn btn-sm btn-success fw-bold px-3">PRINT KARTU (<?= $jumlah_data; ?>)</button>
            <a href="siswa.php" class="btn btn-sm btn-secondary">Batal</a>
        </div>
    </div>
</div>

<div class="grid-cetak">
    <?php 
    $i = 0;
    foreach($query_result as $row): 
        $i++;
        $qrSource = trim((string)($row['nisn'] ?? ''));
        if ($qrSource === '') {
            $qrSource = 'NISN_TIDAK_VALID_' . (string)($row['id_siswa'] ?? '0');
        }
        $qrDataUri = generateQrDataUri($qrSource, QR_ECLEVEL_H, 10, 2);
    ?>
    <div class="kartu-container">
        <div class="header">
            <div class="logo-box">
                <?php if ($logoSekolah && file_exists('../assets/img/logo_sekolah/'.$logoSekolah)): ?>
                    <img src="../assets/img/logo_sekolah/<?= htmlspecialchars($logoSekolah, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo Sekolah">
                <?php endif; ?>
            </div>
            <div class="header-text">
                <h1>KARTU ABSENSI SISWA</h1>
                <p>SMP NEGERI 1 INDONESIA - Tahun Ajaran 2023/2024</p>
            </div>
            <div class="logo-box">
                <?php if ($logoPemda && file_exists('../assets/img/logo_pemda/'.$logoPemda)): ?>
                    <img src="../assets/img/logo_pemda/<?= htmlspecialchars($logoPemda, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo Pemda">
                <?php endif; ?>
            </div>
        </div>
        <div class="main-body">
            <div class="col-foto">
                <img src="../assets/img/siswa/<?= htmlspecialchars($row['foto'] ?: 'default.png', ENT_QUOTES, 'UTF-8'); ?>" alt="Foto">
            </div>
            <div class="col-data">
                <h2><?= SecurityHelper::escapeHTML(strtoupper($row['nama_lengkap'])); ?></h2>
                <table class="data-table">
                    <tr><td class="label">NIS</td><td class="val">: <?= SecurityHelper::escapeHTML($row['nis']); ?></td></tr>
                    <tr><td class="label">NISN</td><td class="val">: <?= SecurityHelper::escapeHTML($row['nisn'] ?: '-'); ?></td></tr>
                    <tr><td class="label">KELAS</td><td class="val">: <?= SecurityHelper::escapeHTML($row['nama_kelas']); ?></td></tr>
                    <tr><td class="label">JK</td><td class="val">: <?= ($row['jk'] === 'L' ? 'L' : 'P'); ?></td></tr>
                </table>
            </div>
            <div class="col-qr">
                <img src="<?= htmlspecialchars($qrDataUri, ENT_QUOTES, 'UTF-8'); ?>" alt="QR">
                <div class="qr-label">SCAN UNTUK ABSENSI</div>
            </div>
        </div>
        <div class="footer"><p>Tunjukkan kartu ini saat melakukan absensi</p></div>
    </div>
    <?php if($i % 10 == 0) echo '</div><div class="page-break"></div><div class="grid-cetak mt-4">'; ?>
    <?php endforeach; ?>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>

