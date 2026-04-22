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
$logoQuery = mysqli_query($conn, "SELECT nama_pengaturan, isi_pengaturan FROM pengaturan WHERE nama_pengaturan IN ('logo_sekolah','logo_pemda')");
while ($logoRow = mysqli_fetch_assoc($logoQuery)) {
    if ($logoRow['nama_pengaturan'] == 'logo_sekolah') {
        $logoSekolah = $logoRow['isi_pengaturan'];
    }
    if ($logoRow['nama_pengaturan'] == 'logo_pemda') {
        $logoPemda = $logoRow['isi_pengaturan'];
    }
}

$qrSource = get_student_card_identifier($d);
$qrDataUri = generateQrDataUri($qrSource, QR_ECLEVEL_H, 10, 2);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Kartu - <?= $d['nama_lengkap']; ?></title>
    <link href="../assets/css/site.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap');
        
        body { margin: 0; padding: 0; font-family: 'Poppins', Arial, sans-serif; background: #f0f0f0; }
        .no-print { padding: 20px; background: #fff; border-bottom: 1px solid #ccc; margin-bottom: 20px; text-align: center; }
        
        /* Layout ID Card Standar Landscape */
        .kartu-container { 
            width: 8.56cm; height: 5.4cm; 
            background: #fff; border-radius: 8px; 
            overflow: hidden; position: relative;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border: 1px solid #ddd;
        }

        /* A. Header (Teks Putih, Teks di Tengah) */
        .header { 
            background: #003399; color: #fff; 
            height: 28%; display: flex; align-items: center; justify-content: space-between; padding: 0 10px;
            border-bottom: 2px solid #FFD700;
        }
        .logo-box { width: 18%; display: flex; align-items: center; justify-content: center; }
        .logo-box img { max-height: 1.9cm; max-width: 100%; object-fit: contain; }
        .header-text { flex: 1; text-align: center; padding: 0 10px; }
        .header-text h1 { margin: 0; font-size: 10pt; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
        .header-text p { margin: 0; font-size: 6pt; opacity: 0.9; }

        /* B. Body (Dibagi 3 Kolom) */
        .main-body { display: flex; height: 60%; padding: 8px; align-items: center; }
        
        /* 1. Kolom Kiri: Foto */
        .col-foto { width: 30%; text-align: center; }
        .col-foto img { width: 2.1cm; height: 2.7cm; object-fit: cover; border: 1px solid #003399; border-radius: 4px; }

        /* 2. Kolom Tengah: Data Siswa (Alignment Kiri) */
        .col-data { width: 45%; padding-left: 10px; }
        .col-data h2 { margin: 0 0 5px 0; font-size: 9pt; color: #003399; font-weight: 700; border-bottom: 1px solid #eee; text-transform: uppercase; }
        .data-table { font-size: 7pt; width: 100%; border-collapse: collapse; }
        .data-table td { padding: 1px 0; vertical-align: top; }
        .label { width: 42px; color: #666; font-weight: 600; }
        .val { color: #000; font-weight: 600; }

        /* 3. Kolom Kanan: QR Code Besar */
        .col-qr { width: 25%; text-align: center; }
        .col-qr img { width: 1.9cm; height: 1.9cm; display: block; margin: 0 auto; }
        .qr-label { font-size: 5pt; font-weight: bold; margin-top: 2px; color: #003399; }

        /* C. Footer (Background Abu) */
        .footer { 
            position: absolute; bottom: 0; width: 100%; height: 15%;
            background: #f8f9fa; border-top: 1px solid #eee;
            display: flex; align-items: center; justify-content: center;
        }
        .footer p { font-size: 5.5pt; color: #555; margin: 0; font-style: italic; }

        @media print {
            body { background: none; }
            .no-print { display: none !important; }
            .kartu-container { 
                box-shadow: none; border: 1px solid #000; 
                position: absolute; top: 0; left: 0; /* Paksa ke pojok kiri atas A4 */
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact; 
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #003399; color: #fff; border: none; border-radius: 4px; font-weight: bold;">PRINT KARTU</button>
    <a href="siswa.php" style="margin-left: 10px; text-decoration: none; color: #666;">Kembali ke Daftar Siswa</a>
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
            <p>Kartu permanen reusable untuk absensi harian</p>
        </div>
        <div class="logo-box">
            <?php if ($logoPemda && file_exists('../assets/img/logo_pemda/'.$logoPemda)): ?>
                <img src="../assets/img/logo_pemda/<?= $logoPemda ?>" alt="Logo Pemda">
            <?php endif; ?>
        </div>
    </div>
    
    <div class="main-body">
        <div class="col-foto">
            <img src="../assets/img/siswa/<?= $d['foto'] ?: 'default.png'; ?>" alt="Foto">
        </div>
        
        <div class="col-data">
            <h2><?= strtoupper($d['nama_lengkap']); ?></h2>
            <table class="data-table">
                <tr><td class="label">NIS</td><td class="val">: <?= $d['nis']; ?></td></tr>
                <tr><td class="label">NISN</td><td class="val">: <?= $d['nisn'] ?: '-'; ?></td></tr>
                <tr><td class="label">JK</td><td class="val">: <?= ($d['jk'] == 'L' ? 'LAKI-LAKI' : 'PEREMPUAN'); ?></td></tr>
                <tr><td class="label">ID</td><td class="val">: <?= htmlspecialchars($qrSource, ENT_QUOTES, 'UTF-8'); ?></td></tr>
            </table>
        </div>
        
        <div class="col-qr">
            <img src="<?= htmlspecialchars($qrDataUri, ENT_QUOTES, 'UTF-8'); ?>" alt="QR">
            <div class="qr-label">SCAN UNTUK ABSENSI</div>
        </div>
    </div>

    <div class="footer">
        <p>Kartu ini tetap berlaku sampai siswa lulus atau kartu diganti</p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>

