<?php
require_once __DIR__ . '/../../includes/SecurityHelper.php';

// Service class - Database connection passed from controller
class PengaturanService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function simpanPengaturan($postData, $filesData) {
        $errors = [];
        $success_count = 0;

        $logoMaxSize = 500 * 1024; // 500KB

        try {
            // 1. Update Nama & Alamat Sekolah
            $nama_sek = trim($postData['nama_sekolah'] ?? '');
            $alamat_sek = trim($postData['alamat'] ?? '');

            if (empty($nama_sek) || empty($alamat_sek)) {
                $errors[] = "Nama sekolah dan alamat tidak boleh kosong.";
            } else {
                $stmt = mysqli_prepare($this->conn, "UPDATE pengaturan SET isi_pengaturan=? WHERE nama_pengaturan='nama_sekolah'");
                mysqli_stmt_bind_param($stmt, "s", $nama_sek);
                if (mysqli_stmt_execute($stmt)) {
                    $success_count++;
                } else {
                    $errors[] = "Gagal update nama sekolah: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);

                $stmt = mysqli_prepare($this->conn, "UPDATE pengaturan SET isi_pengaturan=? WHERE nama_pengaturan='alamat_sekolah'");
                mysqli_stmt_bind_param($stmt, "s", $alamat_sek);
                if (mysqli_stmt_execute($stmt)) {
                    $success_count++;
                } else {
                    $errors[] = "Gagal update alamat sekolah: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            }

            // 2. Proses Upload Logo Sekolah
            if (!empty($filesData['logo_sekolah']['name'])) {
                $upload_dir = __DIR__ . '/../../assets/img/logo_sekolah/';
                $uploadResult = SecurityHelper::uploadImageSecure($filesData['logo_sekolah'], $upload_dir, [
                    'prefix' => 'logo_sekolah',
                    'maxSize' => $logoMaxSize,
                    'maxWidth' => 1000,
                    'maxHeight' => 1000,
                    'recommendedSizes' => [
                        ['w' => 500, 'h' => 500],
                    ],
                ]);

                if (!$uploadResult['success']) {
                    $errors[] = 'Logo sekolah gagal diupload: ' . $uploadResult['message'];
                } else {
                    $nama_file = $uploadResult['filename'];
                    $stmt = mysqli_prepare($this->conn, "UPDATE pengaturan SET isi_pengaturan=? WHERE nama_pengaturan='logo_sekolah'");
                    mysqli_stmt_bind_param($stmt, "s", $nama_file);
                    if (mysqli_stmt_execute($stmt)) {
                        $success_count++;
                    } else {
                        $errors[] = "Gagal simpan logo sekolah ke database: " . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                }
            }

            // 3. Proses Upload Logo Pemda
            if (!empty($filesData['logo_pemda']['name'])) {
                $upload_dir_pemda = __DIR__ . '/../../assets/img/logo_pemda/';
                $uploadResultPemda = SecurityHelper::uploadImageSecure($filesData['logo_pemda'], $upload_dir_pemda, [
                    'prefix' => 'logo_pemda',
                    'maxSize' => $logoMaxSize,
                    'maxWidth' => 1000,
                    'maxHeight' => 1000,
                    'recommendedSizes' => [
                        ['w' => 500, 'h' => 500],
                    ],
                ]);

                if (!$uploadResultPemda['success']) {
                    $errors[] = 'Logo pemda gagal diupload: ' . $uploadResultPemda['message'];
                } else {
                    $nama_file_pemda = $uploadResultPemda['filename'];
                    $stmt = mysqli_prepare($this->conn, "UPDATE pengaturan SET isi_pengaturan=? WHERE nama_pengaturan='logo_pemda'");
                    mysqli_stmt_bind_param($stmt, "s", $nama_file_pemda);
                    if (mysqli_stmt_execute($stmt)) {
                        $success_count++;
                    } else {
                        $errors[] = "Gagal simpan logo pemda ke database: " . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                }
            }

            return ['success_count' => $success_count, 'errors' => $errors];

        } catch (Exception $e) {
            return ['success_count' => $success_count, 'errors' => ["Terjadi kesalahan sistem: " . $e->getMessage()]];
        }
    }

    public function getPengaturan() {
        $settings = [];
        $result = mysqli_query($this->conn, "SELECT nama_pengaturan, isi_pengaturan FROM pengaturan");
        while ($row = mysqli_fetch_assoc($result)) {
            $settings[$row['nama_pengaturan']] = $row['isi_pengaturan'];
        }
        return $settings;
    }
}
?>