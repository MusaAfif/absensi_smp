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
$logoSettings = $dbHelper->select("SELECT nama_pengaturan, isi_pengaturan FROM pengaturan WHERE nama_pengaturan IN ('logo_sekolah','logo_pemda','nama_sekolah')");
$logoSekolah = '';
$logoPemda = '';
$namaSekolah = 'SMPN 1 Indonesia';
foreach ($logoSettings as $setting) {
    if ($setting['nama_pengaturan'] === 'logo_sekolah') {
        $logoSekolah = SecurityHelper::sanitizeInput($setting['isi_pengaturan']);
    }
    if ($setting['nama_pengaturan'] === 'logo_pemda') {
        $logoPemda = SecurityHelper::sanitizeInput($setting['isi_pengaturan']);
    }
    if ($setting['nama_pengaturan'] === 'nama_sekolah' && trim((string)$setting['isi_pengaturan']) !== '') {
        $namaSekolah = trim((string)$setting['isi_pengaturan']);
    }
}
?>
<?php
$page_title = 'Cetak Massal Kartu | E-Absensi SMP';
$extra_head = <<<'CSS'
<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap');

    :root {
        --brand-navy: #0b2f6b;
        --brand-blue: #114aa3;
        --brand-accent: #22c3a6;
        --ink-dark: #1f2a3d;
        --ink-soft: #5f6d85;
        --paper: #f4f7fd;
    }

    body { font-family: 'Poppins', sans-serif; background: radial-gradient(circle at 20% 10%, #f9fbff 0, #eef3ff 35%, #e8edf8 100%); padding: 20px; margin: 0; color: var(--ink-dark); }
    .no-print-area { background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ddd; }

    .grid-cetak { 
        display: grid; 
        grid-template-columns: repeat(2, 8.56cm); 
        gap: 14px; 
        justify-content: center; 
    }

    .kartu-container { 
        width: 8.56cm;
        height: 5.4cm;
        border-radius: 12px;
        position: relative;
        overflow: hidden; position: relative;
        border: 1px solid #d5def0;
        box-shadow: 0 14px 30px rgba(23, 45, 92, 0.20);
        background: linear-gradient(175deg, #ffffff 0%, var(--paper) 100%);
        page-break-inside: avoid;
    }

    .kartu-container::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(120deg, rgba(17, 74, 163, 0.08), transparent 40%, rgba(34, 195, 166, 0.10) 100%);
        pointer-events: none;
    }

    .header {
        height: 31%;
        padding: 7px 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(125deg, var(--brand-navy), var(--brand-blue));
        border-bottom: 2px solid rgba(255, 255, 255, 0.22);
        color: #fff;
        position: relative;
        z-index: 2;
    }

    .logo-box {
        width: 17%;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .logo-box img {
        max-width: 100%;
        max-height: 1.55cm;
        object-fit: contain;
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.18));
    }

    .header-text {
        width: 66%;
        text-align: center;
    }

    .header-text h1 {
        margin: 0;
        font-size: 9.2pt;
        letter-spacing: 0.7px;
        text-transform: uppercase;
        font-weight: 700;
        line-height: 1.15;
    }

    .header-text .school {
        margin: 3px 0 0;
        font-size: 6pt;
        font-weight: 500;
        opacity: 0.95;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .main-body {
        height: 56%;
        padding: 7px 8px;
        display: grid;
        grid-template-columns: 26% 45% 29%;
        gap: 8px;
        align-items: center;
        position: relative;
        z-index: 2;
    }

    .photo-wrap {
        text-align: center;
    }

    .photo-wrap img {
        width: 1.95cm;
        height: 2.45cm;
        object-fit: cover;
        border: 1px solid #c6d3ee;
        border-radius: 8px;
        background: #fff;
        padding: 2px;
    }

    .photo-wrap .chip {
        margin-top: 4px;
        display: inline-block;
        border-radius: 999px;
        background: #e8eefc;
        color: var(--brand-navy);
        font-size: 5.3pt;
        padding: 2px 7px;
        font-weight: 600;
    }

    .student-name {
        margin: 0 0 5px;
        font-size: 8.3pt;
        line-height: 1.2;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--brand-navy);
        border-bottom: 1px dashed #cfd7ea;
        padding-bottom: 4px;
    }

    .data-grid {
        display: grid;
        grid-template-columns: 40px 1fr;
        row-gap: 2px;
        column-gap: 6px;
        font-size: 6.2pt;
    }

    .label {
        color: var(--ink-soft);
        font-weight: 600;
    }

    .value {
        color: #1f2a3d;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .id-box {
        margin-top: 5px;
        border-radius: 6px;
        background: #eef3ff;
        border: 1px solid #d7e1f6;
        padding: 4px 5px;
    }

    .id-box .id-title {
        margin: 0;
        font-size: 4.8pt;
        text-transform: uppercase;
        letter-spacing: 0.35px;
        color: var(--ink-soft);
    }

    .id-box .id-value {
        margin: 1px 0 0;
        font-size: 5.7pt;
        color: var(--brand-navy);
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .qr-wrap {
        text-align: center;
        border-radius: 10px;
        background: #fff;
        border: 1px solid #d8e0f2;
        padding: 6px 4px;
        width: 100%;
        box-sizing: border-box;
    }

    .qr-wrap img {
        width: 1.65cm;
        height: 1.65cm;
        margin: 0 auto;
        display: block;
    }

    .qr-wrap .qr-text {
        margin-top: 3px;
        font-size: 5.1pt;
        font-weight: 700;
        color: var(--brand-navy);
        text-transform: uppercase;
        letter-spacing: 0.35px;
    }

    .footer {
        height: 13%;
        border-top: 1px solid #d7e0f3;
        background: rgba(255, 255, 255, 0.82);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 8px;
        position: relative;
        z-index: 2;
    }

    .footer p {
        font-size: 5pt;
        color: #566480;
        margin: 0;
        font-style: italic;
    }

    @media print {
        body { background: #fff; padding: 0; }
        .no-print-area { display: none !important; }
        .grid-cetak { gap: 10px; }
        .kartu-container {
            border: 1px solid #b7c6e6;
            box-shadow: none;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
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
        $qrSource = get_student_card_identifier($row);
        if ($qrSource === '') {
            $qrSource = 'SID-TEMP-' . (string)($row['id_siswa'] ?? '0');
        }
        $qrDataUri = generateQrDataUri($qrSource, QR_ECLEVEL_H, 10, 2);
        $cardIdentity = trim((string)($row['student_uuid'] ?? ''));
        if ($cardIdentity === '') {
            $cardIdentity = 'SID-' . str_pad((string)($row['id_siswa'] ?? '0'), 6, '0', STR_PAD_LEFT);
        }
        $fotoSiswa = trim((string)($row['foto'] ?? ''));
        if ($fotoSiswa === '' || !file_exists('../assets/img/siswa/' . $fotoSiswa)) {
            $fotoSiswa = 'default.png';
        }
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
                <p class="school"><?= SecurityHelper::escapeHTML(strtoupper($namaSekolah)); ?> - <?= SecurityHelper::escapeHTML(get_current_tahun_ajaran_label()); ?></p>
            </div>
            <div class="logo-box">
                <?php if ($logoPemda && file_exists('../assets/img/logo_pemda/'.$logoPemda)): ?>
                    <img src="../assets/img/logo_pemda/<?= htmlspecialchars($logoPemda, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo Pemda">
                <?php endif; ?>
            </div>
        </div>
        <div class="main-body">
            <div class="photo-wrap">
                <img src="../assets/img/siswa/<?= htmlspecialchars($fotoSiswa, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto">
                <span class="chip">Kartu Permanen</span>
            </div>

            <div>
                <h2 class="student-name"><?= SecurityHelper::escapeHTML(strtoupper($row['nama_lengkap'])); ?></h2>
                <div class="data-grid">
                    <div class="label">NIS</div><div class="value">: <?= SecurityHelper::escapeHTML((string)$row['nis']); ?></div>
                    <div class="label">NISN</div><div class="value">: <?= SecurityHelper::escapeHTML((string)($row['nisn'] ?: '-')); ?></div>
                    <div class="label">Kelas</div><div class="value">: <?= SecurityHelper::escapeHTML((string)($row['nama_kelas'] ?? '-')); ?></div>
                    <div class="label">JK</div><div class="value">: <?= ($row['jk'] === 'L' ? 'Laki-laki' : 'Perempuan'); ?></div>
                </div>
                <div class="id-box">
                    <p class="id-title">Identitas Kartu</p>
                    <p class="id-value"><?= SecurityHelper::escapeHTML($cardIdentity); ?></p>
                </div>
            </div>

            <div class="qr-wrap">
                <img src="<?= htmlspecialchars($qrDataUri, ENT_QUOTES, 'UTF-8'); ?>" alt="QR">
                <div class="qr-text">Scan untuk absensi</div>
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

