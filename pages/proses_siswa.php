<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once '../includes/DatabaseHelper.php';
require_once '../includes/CSRFProtection.php';

cek_login();

CSRFProtection::init();
$dbHelper = new DatabaseHelper($conn);

if (isset($_POST['update'])) {
    if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
        safe_redirect('siswa', ['status' => 'error', 'message' => 'CSRF token tidak valid']);
        exit;
    }

    $id_siswa     = SecurityHelper::sanitizeInput($_POST['id_siswa'] ?? '');
    $nis          = SecurityHelper::sanitizeInput($_POST['nis'] ?? '');
    $nisn         = SecurityHelper::sanitizeInput($_POST['nisn'] ?? '');
    $nama_lengkap = SecurityHelper::sanitizeInput($_POST['nama_lengkap'] ?? '');
    $jk           = SecurityHelper::sanitizeInput($_POST['jk'] ?? '');
    $id_kelas     = SecurityHelper::sanitizeInput($_POST['id_kelas'] ?? '');

    if (!SecurityHelper::validateInteger($id_siswa) || !SecurityHelper::validateInteger($id_kelas)) {
        safe_redirect('siswa', ['status' => 'error', 'message' => 'ID tidak valid']);
        exit;
    }

    if (empty($nis) || empty($nisn) || empty($nama_lengkap) || empty($jk)) {
        safe_redirect('siswa', ['status' => 'error', 'message' => 'Semua field harus diisi']);
        exit;
    }

    $existing = $dbHelper->selectOne(
        'SELECT foto FROM siswa WHERE id_siswa = ? LIMIT 1',
        [$id_siswa],
        'i'
    );
    $foto_lama = $existing['foto'] ?? '';
    $foto_baru = $foto_lama;

    if (!empty($_FILES['foto']['name'])) {
        $uploadFoto = SecurityHelper::uploadImageSecure(
            $_FILES['foto'],
            __DIR__ . '/../assets/img/siswa/',
            [
                'prefix' => 'siswa',
                'maxSize' => 1024 * 1024,
                'recommendedSizes' => [
                    ['w' => 400, 'h' => 400],
                    ['w' => 300, 'h' => 400],
                ],
            ]
        );

        if (!$uploadFoto['success']) {
            safe_redirect('siswa', ['status' => 'error', 'message' => 'Upload foto gagal: ' . $uploadFoto['message']]);
            exit;
        }

        $foto_baru = $uploadFoto['filename'];

        if (!empty($foto_lama) && file_exists("../assets/img/siswa/" . $foto_lama)) {
            @unlink("../assets/img/siswa/" . $foto_lama);
        }
    }

    if ($foto_baru !== $foto_lama) {
        $result = $dbHelper->update(
            'UPDATE siswa SET nis = ?, nisn = ?, nama_lengkap = ?, jk = ?, id_kelas = ?, foto = ? WHERE id_siswa = ?',
            [$nis, $nisn, $nama_lengkap, $jk, $id_kelas, $foto_baru, $id_siswa],
            'sssssii'
        );
    } else {
        $result = $dbHelper->update(
            'UPDATE siswa SET nis = ?, nisn = ?, nama_lengkap = ?, jk = ?, id_kelas = ? WHERE id_siswa = ?',
            [$nis, $nisn, $nama_lengkap, $jk, $id_kelas, $id_siswa],
            'ssssii'
        );
    }

    if ($result !== false) {
        safe_redirect('siswa', ['status' => 'sukses']);
    } else {
        safe_redirect('siswa', ['status' => 'error', 'message' => 'Gagal memperbarui data siswa']);
    }
    exit;
}
?>
