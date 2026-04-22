<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
require_once __DIR__ . '/../includes/attendance_service.php';
cek_login();

// Inisialisasi session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'service/pengaturan_service.php';
require_once 'service/jadwal_service.php';

// AUTO-MIGRATION: Pastikan tabel jadwal_absensi memiliki struktur range waktu
$table_exists = mysqli_query($conn, "SHOW TABLES LIKE 'jadwal_absensi'");
$has_table = mysqli_num_rows($table_exists) > 0;

if (!$has_table) {
    // Table doesn't exist, create new structure
    $create_query = "CREATE TABLE jadwal_absensi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hari VARCHAR(10) NOT NULL UNIQUE,
        jam_masuk_mulai TIME DEFAULT '06:00',
        jam_masuk_tepat TIME DEFAULT '07:00',
        jam_masuk_selesai TIME DEFAULT '08:00',
        jam_pulang_mulai TIME DEFAULT '12:00',
        jam_pulang_selesai TIME DEFAULT '15:00',
        batas_terlambat INT DEFAULT 15
    )";

    if (!mysqli_query($conn, $create_query)) {
        app_log_error('Gagal membuat tabel jadwal_absensi', ['error' => mysqli_error($conn)]);
        $_SESSION['error'] = 'Gagal menyiapkan jadwal absensi. Silakan cek log server.';
        header("Location: index.php");
        exit;
    }

    // Insert data default untuk hari kerja
    $hari_list = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
    foreach ($hari_list as $hari) {
        $masuk_mulai = '06:00';
        $masuk_tepat = '07:00';
        $masuk_selesai = '08:00';
        $pulang_mulai = ($hari == 'Jumat') ? '11:00' : '12:00';
        $pulang_selesai = ($hari == 'Jumat') ? '12:00' : '15:00';

        $insert_query = "INSERT INTO jadwal_absensi (hari, jam_masuk_mulai, jam_masuk_tepat, jam_masuk_selesai, jam_pulang_mulai, jam_pulang_selesai)
            VALUES ('$hari', '$masuk_mulai', '$masuk_tepat', '$masuk_selesai', '$pulang_mulai', '$pulang_selesai')";

        mysqli_query($conn, $insert_query);
    }
} else {
    // Table exists, check if it has the correct structure
    $has_new_structure = columnExists($conn, 'jadwal_absensi', 'jam_masuk_mulai');
    $has_old_structure = columnExists($conn, 'jadwal_absensi', 'jam_masuk');

    if (!$has_new_structure) {
        // Table has old structure, recreate it
        mysqli_query($conn, "DROP TABLE jadwal_absensi");

        $create_query = "CREATE TABLE jadwal_absensi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            hari VARCHAR(10) NOT NULL UNIQUE,
            jam_masuk_mulai TIME DEFAULT '06:00',
            jam_masuk_tepat TIME DEFAULT '07:00',
            jam_masuk_selesai TIME DEFAULT '08:00',
            jam_pulang_mulai TIME DEFAULT '12:00',
            jam_pulang_selesai TIME DEFAULT '15:00',
            batas_terlambat INT DEFAULT 15
        )";

        if (!mysqli_query($conn, $create_query)) {
            app_log_error('Gagal membuat ulang tabel jadwal_absensi', ['error' => mysqli_error($conn)]);
            $_SESSION['error'] = 'Gagal menyiapkan ulang jadwal absensi. Silakan cek log server.';
            header("Location: index.php");
            exit;
        }

        // Insert data default untuk hari kerja
        $hari_list = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        foreach ($hari_list as $hari) {
            $masuk_mulai = '06:00';
            $masuk_tepat = '07:00';
            $masuk_selesai = '08:00';
            $pulang_mulai = ($hari == 'Jumat') ? '11:00' : '12:00';
            $pulang_selesai = ($hari == 'Jumat') ? '12:00' : '15:00';

            $insert_query = "INSERT INTO jadwal_absensi (hari, jam_masuk_mulai, jam_masuk_tepat, jam_masuk_selesai, jam_pulang_mulai, jam_pulang_selesai)
                VALUES ('$hari', '$masuk_mulai', '$masuk_tepat', '$masuk_selesai', '$pulang_mulai', '$pulang_selesai')";

            mysqli_query($conn, $insert_query);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Token keamanan tidak valid.';
        safe_redirect('pengaturan');
    }
    
    $pengaturanService = new PengaturanService($conn);
    $jadwalService = new JadwalService($conn);

    // Simpan pengaturan umum
    $resultPengaturan = $pengaturanService->simpanPengaturan($_POST, $_FILES);

    // Simpan jadwal
    $resultJadwal = $jadwalService->simpanJadwal($_POST);

    // Gabungkan hasil
    $total_success = $resultPengaturan['success_count'] + $resultJadwal['success_count'];
    $all_errors = array_merge($resultPengaturan['errors'], $resultJadwal['errors']);

    if (empty($all_errors)) {
        $_SESSION['success'] = "✓ Pengaturan berhasil diperbarui! ($total_success perubahan)";
    } else {
        $_SESSION['error'] = "⚠ Error:" . "\n" . implode("\n", $all_errors);
        app_log_error('Pengaturan gagal diperbarui', ['errors' => $all_errors]);
    }

    // Redirect untuk mencegah resubmit
    safe_redirect('pengaturan');
}

// Jika bukan POST, redirect ke index
safe_redirect('pengaturan');
exit;
?>