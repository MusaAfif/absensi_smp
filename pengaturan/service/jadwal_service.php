<?php
// Service class - Database connection passed from controller
class JadwalService {
    private $conn;

    private function normalizeTime(string $time): ?string {
        $time = trim($time);
        if ($time === '') {
            return null;
        }

        // Accept HH:MM and HH:MM:SS, normalize to HH:MM.
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time) !== 1) {
            return null;
        }

        return substr($time, 0, 5);
    }

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function simpanJadwal($postData) {
        $errors = [];
        $success_count = 0;
        $hari_list = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

        foreach ($hari_list as $hari) {
            // Validasi input waktu - handle empty values with defaults
            $masuk_mulai = trim($postData['masuk_mulai_' . $hari] ?? '');
            $masuk_selesai = trim($postData['masuk_selesai_' . $hari] ?? '');
            $pulang_mulai = trim($postData['pulang_mulai_' . $hari] ?? '');
            $pulang_selesai = trim($postData['pulang_selesai_' . $hari] ?? '');
            $toleransi = (int)($postData['toleransi_' . $hari] ?? 15);

            // Set defaults if empty
            if (empty($masuk_mulai)) $masuk_mulai = '06:00';
            if (empty($masuk_selesai)) $masuk_selesai = '08:00';
            if (empty($pulang_mulai)) $pulang_mulai = ($hari == 'Jumat') ? '11:00' : '12:00';
            if (empty($pulang_selesai)) $pulang_selesai = ($hari == 'Jumat') ? '12:00' : '15:00';

            // Kompatibilitas skema lama: jam_masuk_tepat tetap ada tapi disamakan dengan jam_masuk_mulai
            $masuk_tepat = $masuk_mulai;

            $masuk_mulai_norm = $this->normalizeTime($masuk_mulai);
            $masuk_selesai_norm = $this->normalizeTime($masuk_selesai);
            $pulang_mulai_norm = $this->normalizeTime($pulang_mulai);
            $pulang_selesai_norm = $this->normalizeTime($pulang_selesai);

            if ($masuk_mulai_norm === null || $masuk_selesai_norm === null || $pulang_mulai_norm === null || $pulang_selesai_norm === null) {
                $errors[] = "Format waktu untuk $hari tidak valid (gunakan HH:MM).";
                continue;
            }

            $masuk_mulai = $masuk_mulai_norm;
            $masuk_selesai = $masuk_selesai_norm;
            $pulang_mulai = $pulang_mulai_norm;
            $pulang_selesai = $pulang_selesai_norm;

            // Validasi toleransi
            if ($toleransi < 0 || $toleransi > 120) {
                $errors[] = "Toleransi untuk $hari tidak valid (0-120 menit).";
                continue;
            }

            if (strtotime($masuk_mulai) > strtotime($masuk_selesai)) {
                $errors[] = "Urutan waktu masuk $hari tidak valid: mulai harus sebelum atau sama dengan selesai.";
                continue;
            }

            if (strtotime($pulang_mulai) > strtotime($pulang_selesai)) {
                $errors[] = "Urutan waktu pulang $hari tidak valid: mulai harus sebelum atau sama dengan selesai.";
                continue;
            }

            // Gunakan prepared statement untuk security
            $stmt = mysqli_prepare($this->conn, "INSERT INTO jadwal_absensi (hari, jam_masuk_mulai, jam_masuk_tepat, jam_masuk_selesai, jam_pulang_mulai, jam_pulang_selesai, batas_terlambat)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                jam_masuk_mulai=VALUES(jam_masuk_mulai),
                jam_masuk_tepat=VALUES(jam_masuk_tepat),
                jam_masuk_selesai=VALUES(jam_masuk_selesai),
                jam_pulang_mulai=VALUES(jam_pulang_mulai),
                jam_pulang_selesai=VALUES(jam_pulang_selesai),
                batas_terlambat=VALUES(batas_terlambat)");

            mysqli_stmt_bind_param($stmt, "ssssssi", $hari, $masuk_mulai, $masuk_tepat, $masuk_selesai, $pulang_mulai, $pulang_selesai, $toleransi);

            if (mysqli_stmt_execute($stmt)) {
                $success_count++;
            } else {
                $errors[] = "Gagal simpan jadwal $hari: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        }

        return ['success_count' => $success_count, 'errors' => $errors];
    }

    public function getJadwal() {
        $jadwal_data = [];
        $q_jadwal = mysqli_query($this->conn, "SELECT * FROM jadwal_absensi");
        while ($row = mysqli_fetch_assoc($q_jadwal)) {
            $jadwal_data[$row['hari']] = $row;
        }
        return $jadwal_data;
    }
}
?>