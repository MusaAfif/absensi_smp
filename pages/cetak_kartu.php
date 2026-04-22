<?php
require_once __DIR__ . '/../includes/config.php';
cek_login();
require_once __DIR__ . '/../includes/qr_helper.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stmt = mysqli_prepare(
    $conn,
    "SELECT s.*, k.nama_kelas FROM siswa s
     LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
     WHERE s.id_siswa = ? LIMIT 1"
);
$d = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $d = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
}

if (!$d) { die("Data tidak ditemukan."); }

// Ambil logo dari pengaturan
$logoSekolah = '';
$logoPemda = '';
$namaSekolah = 'SMPN 1 Indonesia';
$logoQuery = mysqli_query($conn, "SELECT nama_pengaturan, isi_pengaturan FROM pengaturan WHERE nama_pengaturan IN ('logo_sekolah','logo_pemda','nama_sekolah')");
while ($logoRow = mysqli_fetch_assoc($logoQuery)) {
    if ($logoRow['nama_pengaturan'] == 'logo_sekolah') {
        $logoSekolah = $logoRow['isi_pengaturan'];
    }
    if ($logoRow['nama_pengaturan'] == 'logo_pemda') {
        $logoPemda = $logoRow['isi_pengaturan'];
    }
    if ($logoRow['nama_pengaturan'] == 'nama_sekolah' && trim((string)$logoRow['isi_pengaturan']) !== '') {
        $namaSekolah = trim((string)$logoRow['isi_pengaturan']);
    }
}

$qrSource = get_student_card_identifier($d);
$qrDataUri = generateQrDataUri($qrSource, QR_ECLEVEL_H, 10, 2);
$cardIdentity = trim((string)($d['student_uuid'] ?? ''));
if ($cardIdentity === '') {
    $cardIdentity = 'SID-' . str_pad((string)$d['id_siswa'], 6, '0', STR_PAD_LEFT);
}

$fotoSiswa = trim((string)($d['foto'] ?? ''));
if ($fotoSiswa === '' || !file_exists('../assets/img/siswa/' . $fotoSiswa)) {
    $fotoSiswa = 'default.png';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Kartu - <?= $d['nama_lengkap']; ?></title>
    <link href="../assets/css/site.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

        :root {
            --brand-navy: #0b2f6b;
            --brand-blue: #114aa3;
            --brand-accent: #22c3a6;
            --ink-dark: #1f2a3d;
            --ink-soft: #5f6d85;
            --paper: #f4f7fd;
        }

        body {
            margin: 0;
            padding: 26px;
            font-family: 'Poppins', Arial, sans-serif;
            background: radial-gradient(circle at 20% 10%, #f9fbff 0, #eef3ff 35%, #e8edf8 100%);
            color: var(--ink-dark);
        }

        .no-print {
            max-width: 860px;
            margin: 0 auto 18px;
            padding: 14px 18px;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(20, 40, 90, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .no-print .title {
            font-size: 13px;
            color: var(--ink-soft);
            margin: 0;
        }

        .action-wrap {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            border: none;
            border-radius: 10px;
            padding: 9px 14px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--brand-blue), var(--brand-navy));
            color: #fff;
        }

        .btn-light {
            background: #edf1fb;
            color: var(--brand-navy);
        }

        .kartu-container {
            width: 8.56cm;
            height: 5.4cm;
            margin: 0 auto;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
            border: 1px solid #d5def0;
            box-shadow: 0 14px 30px rgba(23, 45, 92, 0.20);
            background: linear-gradient(175deg, #ffffff 0%, var(--paper) 100%);
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
            grid-template-columns: 44px 1fr;
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
            justify-content: space-between;
            padding: 0 8px;
            position: relative;
            z-index: 2;
        }

        .footer .left {
            font-size: 5pt;
            color: #566480;
            font-weight: 500;
        }

        .footer .right {
            font-size: 4.9pt;
            color: #73819a;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .kartu-container {
                margin: 0;
                border-radius: 0;
                box-shadow: none;
                border: 1px solid #b7c6e6;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <p class="title">Pratinjau kartu siswa untuk cetak ukuran ID-1 (8.56 x 5.40 cm)</p>
    <div class="action-wrap">
        <button onclick="window.print()" class="btn-action btn-primary">Print Kartu</button>
        <a href="siswa.php" class="btn-action btn-light">Kembali</a>
    </div>
</div>

<div class="kartu-container">
    <div class="header">
        <div class="logo-box">
            <?php if ($logoSekolah && file_exists('../assets/img/logo_sekolah/'.$logoSekolah)): ?>
                <img src="../assets/img/logo_sekolah/<?= $logoSekolah ?>" alt="Logo Sekolah">
            <?php endif; ?>
        </div>
        <div class="header-text">
            <h1>KARTU ABSENSI SISWA</h1>
            <p class="school"><?= htmlspecialchars(strtoupper($namaSekolah), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="logo-box">
            <?php if ($logoPemda && file_exists('../assets/img/logo_pemda/'.$logoPemda)): ?>
                <img src="../assets/img/logo_pemda/<?= $logoPemda ?>" alt="Logo Pemda">
            <?php endif; ?>
        </div>
    </div>
    
    <div class="main-body">
        <div class="photo-wrap">
            <img src="../assets/img/siswa/<?= htmlspecialchars($fotoSiswa, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto">
            <span class="chip">Kartu Permanen</span>
        </div>
        
        <div>
            <h2 class="student-name"><?= htmlspecialchars($d['nama_lengkap'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="data-grid">
                <div class="label">NIS</div><div class="value">: <?= htmlspecialchars((string)$d['nis'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="label">NISN</div><div class="value">: <?= htmlspecialchars((string)($d['nisn'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="label">JK</div><div class="value">: <?= ($d['jk'] == 'L' ? 'Laki-laki' : 'Perempuan'); ?></div>
            </div>
            <div class="id-box">
                <p class="id-title">Identitas Kartu</p>
                <p class="id-value"><?= htmlspecialchars($cardIdentity, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        
        <div class="qr-wrap">
            <img src="<?= htmlspecialchars($qrDataUri, ENT_QUOTES, 'UTF-8'); ?>" alt="QR">
            <div class="qr-text">Scan untuk absensi</div>
        </div>
    </div>

    <div class="footer">
        <div class="left">Berlaku sampai siswa lulus / kartu diganti resmi</div>
        <div class="right">SID Secure Card</div>
    </div>
</div>

</body>
</html>

