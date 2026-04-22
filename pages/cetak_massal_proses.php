<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
cek_login();
require_once __DIR__ . '/../includes/qr_helper.php';

CSRFProtection::init();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Metode tidak diizinkan.');
}

if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
    header('HTTP/1.1 403 Forbidden');
    exit('CSRF token tidak valid.');
}

// Ambil ID siswa yang dipilih (jika dikirim lewat checkbox) atau cetak semua jika kosong
$ids = isset($_POST['id_siswa']) && is_array($_POST['id_siswa']) ? $_POST['id_siswa'] : [];

$validIds = [];
foreach ($ids as $id) {
    if (SecurityHelper::validateInteger($id)) {
        $validIds[] = (int) $id;
    }
}

// Use prepared statement for safety
$dbHelper = new DatabaseHelper($conn);
if (!empty($validIds)) {
    $placeholders = implode(',', array_fill(0, count($validIds), '?'));
    $sql = "SELECT s.*, k.nama_kelas FROM siswa s 
            JOIN kelas k ON s.id_kelas = k.id_kelas 
            WHERE s.id_siswa IN ($placeholders) 
            ORDER BY k.nama_kelas ASC, s.nama_lengkap ASC";
    
    $types = str_repeat('i', count($validIds));
    $query_result = $dbHelper->select($sql, $validIds, $types);
} else {
    $sql = "SELECT s.*, k.nama_kelas FROM siswa s 
            JOIN kelas k ON s.id_kelas = k.id_kelas 
            ORDER BY k.nama_kelas ASC, s.nama_lengkap ASC";
    $query_result = $dbHelper->select($sql);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Massal Kartu Siswa</title>
    <link href="../assets/css/site.css" rel="stylesheet">
    <style>
        /* Pengaturan Kertas A4 */
        @page { size: A4; margin: 10mm; }
        @media print { .no-print { display: none; } body { background: none; padding: 0; } }

        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f0f0; margin: 0; padding: 20px; }

        /* Grid Layout: 2 Kolom per Baris */
        .grid-container { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 10px; 
            width: 210mm; /* Lebar A4 standar minus margin */
            margin: auto;
        }

        /* Desain Kartu (Satu Sisi) */
        .card { 
            width: 90mm; 
            height: 55mm; 
            background: white; 
            border: 1px solid #000; 
            position: relative; 
            overflow: hidden; 
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }

        .header { background: #1a43bf; color: white; padding: 5px; text-align: center; border-bottom: 2px solid #ffd700; }
        .header h2 { margin: 0; font-size: 10pt; text-transform: uppercase; }
        .header p { margin: 0; font-size: 6pt; }

        .body-card { display: flex; padding: 8px; gap: 10px; flex-grow: 1; }
        
        .photo-box { width: 25mm; text-align: center; }
        .photo { width: 22mm; height: 28mm; object-fit: cover; border: 1px solid #1a43bf; border-radius: 3px; }
        .kelas-txt { font-weight: bold; font-size: 10pt; color: #1a43bf; margin-top: 3px; }

        .info-box { flex: 1; font-size: 8pt; line-height: 1.3; }
        .info-table td { vertical-align: top; padding: 1px 0; }
        .label { color: #666; width: 35px; font-weight: bold; font-size: 7pt; }
        .val { font-weight: bold; text-transform: uppercase; }

        /* QR Code Perbesar (75px) sesuai instruksi */
        .qr-box { position: absolute; bottom: 5px; right: 5px; width: 20mm; height: 20mm; }
        .qr-box img { width: 100%; height: 100%; }

        .footer { position: absolute; bottom: 3px; left: 8px; font-size: 5pt; color: #999; }

        .btn-print { position: fixed; top: 20px; right: 20px; z-index: 1000; background: #222; color: #fff; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<a href="#" class="no-print btn-print" onclick="window.print();">CETAK KE A4</a>

<div class="grid-container">
    <?php foreach($query_result as $d): 
        $qrSource = trim((string)($d['nisn'] ?? ''));
        if ($qrSource === '') {
            $qrSource = 'NISN_TIDAK_VALID_' . (string)($d['id_siswa'] ?? '0');
        }
        $qrDataUri = generateQrDataUri($qrSource, QR_ECLEVEL_L, 10, 2);
    ?>
    <div class="card">
        <div class="header">
            <h2>SMP NEGERI 1 INDONESIA</h2>
            <p>Jl. Pendidikan No. 123, Kota Jambi</p>
        </div>
        
        <div class="body-card">
            <div class="photo-box">
                <img src="../assets/img/siswa/<?= htmlspecialchars($d['foto'] ?: 'default.png', ENT_QUOTES, 'UTF-8'); ?>" class="photo">
                <div class="kelas-txt"><?= SecurityHelper::escapeHTML($d['nama_kelas']); ?></div>
            </div>
            
            <div class="info-box">
                <table class="info-table">
                    <tr><td class="label">NAMA</td><td class="val">: <?= SecurityHelper::escapeHTML($d['nama_lengkap']); ?></td></tr>
                    <tr><td class="label">NIS</td><td class="val">: <?= SecurityHelper::escapeHTML($d['nis']); ?></td></tr>
                    <tr><td class="label">NISN</td><td class="val">: <?= SecurityHelper::escapeHTML($d['nisn'] ?: '-'); ?></td></tr>
                    <tr><td class="label">JK</td><td class="val">: <?= SecurityHelper::escapeHTML($d['jk']); ?></td></tr>
                </table>
            </div>
        </div>

        <div class="qr-box">
            <img src="<?= htmlspecialchars($qrDataUri, ENT_QUOTES, 'UTF-8'); ?>" alt="QR">
        </div>
        <div class="footer">Kartu Siswa Aktif - Digital Absensi</div>
    </div>
    <?php endforeach; ?>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>

