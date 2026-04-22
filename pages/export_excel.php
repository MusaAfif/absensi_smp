<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/attendance_logic.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/DatabaseHelper.php';
cek_login();

$attendanceLogic = new AttendanceLogic($conn);
$dbHelper = new DatabaseHelper($conn);

// Parameter dengan validasi dan sanitasi
$tgl_mulai = SecurityHelper::sanitizeInput($_GET['tgl_mulai'] ?? date('Y-m-01'));
$tgl_selesai = SecurityHelper::sanitizeInput($_GET['tgl_selesai'] ?? date('Y-m-d'));
$id_kelas = SecurityHelper::sanitizeInput($_GET['id_kelas'] ?? '');

// Validasi tanggal
if (!SecurityHelper::validateSQLDate($tgl_mulai)) $tgl_mulai = date('Y-m-01');
if (!SecurityHelper::validateSQLDate($tgl_selesai)) $tgl_selesai = date('Y-m-d');
if ($id_kelas !== '' && !SecurityHelper::validateID($id_kelas)) $id_kelas = '';

// Rekap parameters
$include_rekap = isset($_GET['include_rekap']) && $_GET['include_rekap'] == '1';
$filter_type = SecurityHelper::sanitizeInput($_GET['type'] ?? 'bulan');
$filter_tanggal = SecurityHelper::sanitizeInput($_GET['tanggal'] ?? date('Y-m-d'));
$filter_bulan = SecurityHelper::sanitizeInput($_GET['bulan'] ?? date('m'));
$filter_tahun = SecurityHelper::sanitizeInput($_GET['tahun'] ?? date('Y'));

// Validate filter type and values
if (!in_array($filter_type, ['hari', 'bulan', 'semester'])) $filter_type = 'bulan';
if (!SecurityHelper::validateSQLDate($filter_tanggal)) $filter_tanggal = date('Y-m-d');
if (!preg_match('/^\d{2}$/', $filter_bulan) || $filter_bulan < 1 || $filter_bulan > 12) $filter_bulan = date('m');
if (!preg_match('/^\d{4}$/', $filter_tahun) || $filter_tahun < 2000 || $filter_tahun > 2100) $filter_tahun = date('Y');

// Set headers untuk Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="laporan_absensi_' . date('Y-m-d_H-i-s') . '.xls"');
header('Cache-Control: max-age=0');

// Mulai output Excel
echo "<html xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns=\"http://www.w3.org/TR/REC-html40\">";
echo "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"></head><body>";

// Header informasi
echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";
echo "<tr><td colspan='8' style='background-color: #4CAF50; color: white; font-weight: bold; text-align: center; font-size: 16px;'>LAPORAN ABSENSI & REKAP KETERLAMBATAN</td></tr>";
echo "<tr><td colspan='8' style='background-color: #f2f2f2; font-weight: bold;'>Periode Absensi: " . SecurityHelper::escapeHTML(date('d/m/Y', strtotime($tgl_mulai))) . " - " . SecurityHelper::escapeHTML(date('d/m/Y', strtotime($tgl_selesai))) . "</td></tr>";

// Query data absensi dengan prepared statement
$sql_absensi = "SELECT a.*, s.nis, s.nama_lengkap, k.nama_kelas, st.nama_status,
                       a.status_presensi, a.keterlambatan_menit, a.waktu_absen
                FROM absensi a
                JOIN siswa s ON a.id_siswa = s.id_siswa
                JOIN kelas k ON s.id_kelas = k.id_kelas
                JOIN status_absen st ON a.id_status = st.id_status
                WHERE a.tanggal BETWEEN ? AND ?";

$params = [$tgl_mulai, $tgl_selesai];
$types = 'ss';

if ($id_kelas !== '') {
    $sql_absensi .= " AND s.id_kelas = ?";
    $params[] = $id_kelas;
    $types .= 'i';
    
    // Get class name
    $kelas_result = $dbHelper->selectOne("SELECT nama_kelas FROM kelas WHERE id_kelas = ?", [$id_kelas], 'i');
    $kelas_label = $kelas_result['nama_kelas'] ?? '';
    echo "<tr><td colspan='8' style='background-color: #f2f2f2; font-weight: bold;'>Kelas: " . SecurityHelper::escapeHTML($kelas_label) . "</td></tr>";
}

$sql_absensi .= " ORDER BY a.tanggal DESC, a.waktu_absen DESC";

// Execute query
$data_absensi = $dbHelper->select($sql_absensi, $params, $types);
if ($data_absensi === false) {
    $data_absensi = [];
}

// Header tabel absensi
echo "<tr><td colspan='8' style='background-color: #2196F3; color: white; font-weight: bold; text-align: center;'>DATA ABSENSI LENGKAP</td></tr>";
echo "<tr style='background-color: #e3f2fd; font-weight: bold;'>";
echo "<td>Tanggal</td>";
echo "<td>Jam Absen</td>";
echo "<td>NIS</td>";
echo "<td>Nama Siswa</td>";
echo "<td>Kelas</td>";
echo "<td>Status</td>";
echo "<td>Presensi</td>";
echo "<td>Keterlambatan</td>";
echo "</tr>";

// Data absensi
foreach($data_absensi as $r) {
    echo "<tr>";
    echo "<td>" . SecurityHelper::escapeHTML(date('d/m/Y', strtotime($r['tanggal']))) . "</td>";
    echo "<td>" . SecurityHelper::escapeHTML(date('H:i', strtotime($r['waktu_absen'] ?? $r['jam']))) . "</td>";
    echo "<td>" . SecurityHelper::escapeHTML($r['nis']) . "</td>";
    echo "<td>" . SecurityHelper::escapeHTML($r['nama_lengkap']) . "</td>";
    echo "<td>" . SecurityHelper::escapeHTML($r['nama_kelas']) . "</td>";
    echo "<td>" . SecurityHelper::escapeHTML($r['nama_status']) . "</td>";

    if ($r['status_presensi'] === 'tepat_waktu') {
        echo "<td>Tepat Waktu</td>";
    } elseif ($r['status_presensi'] === 'terlambat') {
        echo "<td>Terlambat</td>";
    } else {
        echo "<td>-</td>";
    }

    if ($r['keterlambatan_menit'] > 0) {
        echo "<td>" . (int)$r['keterlambatan_menit'] . " menit</td>";
    } else {
        echo "<td>-</td>";
    }

    echo "</tr>";
}

// Jika include rekap keterlambatan
if ($include_rekap) {
    echo "<tr><td colspan='8' style='background-color: #fff; height: 20px;'></td></tr>";
    echo "<tr><td colspan='8' style='background-color: #FF9800; color: white; font-weight: bold; text-align: center;'>REKAP KETERLAMBATAN</td></tr>";

    // Ambil data rekap berdasarkan filter
    if ($filter_type === 'hari') {
        $rekap_data = $attendanceLogic->getRekapKeterlambatanHarian($filter_tanggal);
        $title_rekap = "Rekap Keterlambatan - " . date('d/m/Y', strtotime($filter_tanggal));
    } elseif ($filter_type === 'bulan') {
        $rekap_data = $attendanceLogic->getRekapKeterlambatanBulanan($filter_bulan, $filter_tahun);
        $title_rekap = "Rekap Keterlambatan - " . date('F Y', strtotime("$filter_tahun-$filter_bulan-01"));
    } else { // semester
        $semester = $filter_bulan <= 6 ? 1 : 2;
        $rekap_data = $attendanceLogic->getRekapKeterlambatanSemester($semester, $filter_tahun);
        $title_rekap = "Rekap Keterlambatan - Semester $semester Tahun $filter_tahun";
    }

    // Urutkan rekap
    usort($rekap_data, function($a, $b) {
        return $b['total_terlambat'] <=> $a['total_terlambat'];
    });

    echo "<tr><td colspan='8' style='background-color: #f2f2f2; font-weight: bold;'>$title_rekap</td></tr>";

    // Header tabel rekap
    echo "<tr style='background-color: #fff3e0; font-weight: bold;'>";
    echo "<td>Peringkat</td>";
    echo "<td>NISN</td>";
    echo "<td>Nama Siswa</td>";
    echo "<td>Kelas</td>";
    echo "<td>Total Terlambat</td>";
    echo "<td>Rata-rata/Hari</td>";
    echo "<td>Persentase</td>";
    echo "<td>Status</td>";
    echo "</tr>";

    // Data rekap
    foreach ($rekap_data as $index => $siswa) {
        $total_hari = $filter_type === 'hari' ? 1 :
                    ($filter_type === 'bulan' ? date('t', strtotime("$filter_tahun-$filter_bulan-01")) : 17);
        $avg_daily = round($siswa['total_terlambat'] / $total_hari, 1);
        $persentase = round(($siswa['total_terlambat'] / $total_hari) * 100, 1);

        echo "<tr>";
        echo "<td>" . ($index + 1) . "</td>";
        echo "<td>" . $siswa['nisn'] . "</td>";
        echo "<td>" . htmlspecialchars($siswa['nama_lengkap']) . "</td>";
        echo "<td>" . htmlspecialchars($siswa['nama_kelas'] ?? 'N/A') . "</td>";
        echo "<td>" . $siswa['total_terlambat'] . " kali</td>";
        echo "<td>" . $avg_daily . "/hari</td>";
        echo "<td>" . $persentase . "%</td>";

        if ($siswa['total_terlambat'] == 0) {
            echo "<td>Disiplin</td>";
        } elseif ($siswa['total_terlambat'] <= 3) {
            echo "<td>Perlu Perhatian</td>";
        } else {
            echo "<td>Butuh Pembinaan</td>";
        }

        echo "</tr>";
    }

    // Statistik rekap
    echo "<tr><td colspan='8' style='background-color: #fff; height: 10px;'></td></tr>";
    echo "<tr style='background-color: #e8f5e8; font-weight: bold;'>";
    echo "<td colspan='3'>STATISTIK REKAP KETERLAMBATAN</td>";
    echo "<td>Total Siswa: " . count($rekap_data) . "</td>";
    echo "<td>Total Keterlambatan: " . array_sum(array_column($rekap_data, 'total_terlambat')) . "</td>";
    echo "<td>Siswa Terlambat: " . count(array_filter($rekap_data, function($siswa) { return $siswa['total_terlambat'] > 0; })) . "</td>";
    echo "<td colspan='2'>Rata-rata/Minggu: " . round(array_sum(array_column($rekap_data, 'total_terlambat')) / 4, 1) . "</td>";
    echo "</tr>";
}

echo "</table>";
echo "</body></html>";
exit;
?>