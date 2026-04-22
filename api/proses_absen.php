<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/DatabaseHelper.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
require_once __DIR__ . '/../includes/attendance_logic.php';

// Set header JSON agar bisa dibaca oleh AJAX di scan_center.php
header('Content-Type: application/json');

$security = new SecurityHelper();
$dbHelper = new DatabaseHelper($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nisn'])) {
    // Verify CSRF token
    if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'msg' => 'Token keamanan tidak valid.']);
        exit;
    }
    
    $nisn = trim($_POST['nisn']);

    if (empty($nisn) || strlen($nisn) > 50 || !preg_match('/^[a-zA-Z0-9\-]+$/', $nisn)) {
        echo json_encode(['status' => 'error', 'msg' => 'NISN tidak valid.']);
        exit;
    }

    $siswa = $dbHelper->selectOne("SELECT id_siswa, nama_lengkap, jenis_kelamin FROM siswa WHERE nisn = ?", [$nisn], 's');
    if (!$siswa) {
        echo json_encode([
            'status' => 'error',
            'msg' => 'NISN ' . htmlspecialchars($nisn, ENT_QUOTES, 'UTF-8') . ' tidak terdaftar di sistem!'
        ]);
        exit;
    }

    $id_siswa = $siswa['id_siswa'];
    $nama_siswa = $siswa['nama_lengkap'];
    $jam_sekarang = date('H:i:s');

    // 2. Inisialisasi AttendanceLogic dan dapatkan jadwal hari ini
    $attendanceLogic = new AttendanceLogic($conn);
    $jadwal = $attendanceLogic->getJadwalHariIni();

    // 3. Simpan absensi dengan logic yang akurat
    $result = $attendanceLogic->saveAttendance($id_siswa, $jam_sekarang, $jadwal);

    // 4. Return response sesuai hasil
    if ($result['success']) {
        echo json_encode([
            'status' => 'success',
            'nama' => $nama_siswa,
            'st' => $result['message'],
            'jam' => substr($jam_sekarang, 0, 5),
            'status_presensi' => $result['status'],
            'keterlambatan' => $result['keterlambatan_menit']
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'msg' => $result['message']
        ]);
    }
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Akses tidak sah.']);
}
