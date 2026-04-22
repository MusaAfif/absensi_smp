<?php
require_once __DIR__ . '/../includes/config.php';
cek_login();

// 1. Tentukan nama file yang akan muncul di komputer user
$filename = "template_import_siswa_" . date('Ymd') . ".csv";

// 2. Set header agar browser mendownload file sebagai CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// 3. Buka output stream (php://output) untuk menulis data langsung ke browser
$output = fopen('php://output', 'w');

// 4. Tulis baris header (sesuai instruksi Mas Hari)
// Gunakan urutan yang konsisten dengan logika import_proses.php
fputcsv($output, array('nis', 'nisn', 'nama_lengkap', 'jk', 'id_kelas'));

// 5. Tambahkan 1 baris contoh (Opsional, agar user tidak bingung formatnya)
fputcsv($output, array('10212', '0012345678', 'HARI SAPUTRA', 'L', '1'));

// Tutup stream
fclose($output);
exit;
