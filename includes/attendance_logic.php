<?php
/**
 * AttendanceLogic - Sistem Absensi yang Akurat & Profesional
 *
 * Logika 4 zona waktu:
 * 1. Sebelum mulai → ❌ Belum dibuka
 * 2. Mulai - selesai → ✅ Tepat waktu
 * 3. Setelah selesai - batas toleransi → ⚠️ Terlambat
 * 4. Setelah batas → ❌ Ditolak
 */

class AttendanceLogic {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Hitung status absensi berdasarkan waktu scan
     *
     * @param string $scanTime Waktu scan dalam format H:i:s
     * @param array $jadwal Array jadwal hari ini
     * @return array Status absensi dengan detail
     */
    public function calculateAttendanceStatus(string $scanTime, array $jadwal): array {
        $scanTimestamp = strtotime($scanTime);
        $jamMasukMulai = strtotime($jadwal['jam_masuk_mulai']);
        $jamMasukSelesai = strtotime($jadwal['jam_masuk_selesai']);
        $toleransiMenit = (int)($jadwal['batas_terlambat'] ?? 15);
        $batasTerlambat = strtotime("+{$toleransiMenit} minutes", $jamMasukSelesai);

        // Zona 1: Sebelum mulai
        if ($scanTimestamp < $jamMasukMulai) {
            return [
                'allowed' => false,
                'status' => 'belum_dibuka',
                'message' => 'Absensi belum dibuka',
                'keterlambatan_menit' => 0,
                'icon' => 'error',
                'color' => 'danger'
            ];
        }

        // Zona 2: Mulai - selesai (Tepat waktu)
        if ($scanTimestamp >= $jamMasukMulai && $scanTimestamp <= $jamMasukSelesai) {
            return [
                'allowed' => true,
                'status' => 'tepat_waktu',
                'message' => 'Absensi berhasil (Tepat Waktu)',
                'keterlambatan_menit' => 0,
                'icon' => 'success',
                'color' => 'success'
            ];
        }

        // Zona 3: Setelah selesai - batas toleransi (Terlambat)
        if ($scanTimestamp > $jamMasukSelesai && $scanTimestamp <= $batasTerlambat) {
            $keterlambatanMenit = round(($scanTimestamp - $jamMasukSelesai) / 60);
            return [
                'allowed' => true,
                'status' => 'terlambat',
                'message' => "Anda terlambat {$keterlambatanMenit} menit",
                'keterlambatan_menit' => $keterlambatanMenit,
                'icon' => 'warning',
                'color' => 'warning'
            ];
        }

        // Zona 4: Setelah batas (Ditolak)
        return [
            'allowed' => false,
            'status' => 'ditolak',
            'message' => 'Absensi ditutup',
            'keterlambatan_menit' => 0,
            'icon' => 'error',
            'color' => 'danger'
        ];
    }

    /**
     * Simpan absensi dengan status yang akurat
     *
     * @param int $idSiswa ID siswa
     * @param string $scanTime Waktu scan
     * @param array $jadwal Jadwal hari ini
     * @return array Hasil penyimpanan
     */
    public function saveAttendance(int $idSiswa, string $scanTime, array $jadwal): array {
        // Hitung status absensi
        $statusResult = $this->calculateAttendanceStatus($scanTime, $jadwal);

        // Jika tidak diizinkan absen, return error
        if (!$statusResult['allowed']) {
            return [
                'success' => false,
                'message' => $statusResult['message'],
                'status' => $statusResult['status'],
                'icon' => $statusResult['icon'],
                'color' => $statusResult['color']
            ];
        }

        // Cek apakah sudah absen hari ini
        $tanggal = date('Y-m-d');
        $activeYearId = function_exists('get_active_academic_year_id')
            ? get_active_academic_year_id($this->conn)
            : null;
        $hasYearColumn = function_exists('columnExists')
            ? columnExists($this->conn, 'absensi', 'id_tahun_ajaran')
            : false;

        $checkSql = "SELECT id_absen FROM absensi WHERE id_siswa = ? AND tanggal = ?";
        if ($hasYearColumn && $activeYearId) {
            $checkSql .= " AND id_tahun_ajaran = ?";
        }
        $checkSql .= " LIMIT 1";

        $stmt = mysqli_prepare($this->conn, $checkSql);
        if ($hasYearColumn && $activeYearId) {
            mysqli_stmt_bind_param($stmt, 'isi', $idSiswa, $tanggal, $activeYearId);
        } else {
            mysqli_stmt_bind_param($stmt, 'is', $idSiswa, $tanggal);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existing = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($existing) {
            return [
                'success' => false,
                'message' => 'Anda sudah absen hari ini',
                'status' => 'sudah_absen',
                'icon' => 'info',
                'color' => 'info'
            ];
        }

        // Simpan absensi baru
        $columns = ['id_siswa', 'status_presensi', 'keterlambatan_menit', 'waktu_absen', 'tanggal', 'jam', 'waktu', 'scan_source', 'status', 'id_status'];
        $placeholders = ['?', '?', '?', 'NOW()', '?', '?', '?', '?', '?', '?'];
        $types = 'isisssssi';
        $params = [
            $idSiswa,
            $statusResult['status'],
            $statusResult['keterlambatan_menit'],
            $tanggal,
            $scanTime,
            $scanTime,
            'qr',
            'Hadir',
            1,
        ];

        if ($hasYearColumn && $activeYearId) {
            $columns[] = 'id_tahun_ajaran';
            $placeholders[] = '?';
            $types .= 'i';
            $params[] = $activeYearId;
        }

        $insertSql = 'INSERT INTO absensi (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = mysqli_prepare($this->conn, $insertSql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return [
                'success' => true,
                'message' => $statusResult['message'],
                'status' => $statusResult['status'],
                'keterlambatan_menit' => $statusResult['keterlambatan_menit'],
                'icon' => $statusResult['icon'],
                'color' => $statusResult['color']
            ];
        } else {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            return [
                'success' => false,
                'message' => 'Gagal menyimpan absensi: ' . $error,
                'status' => 'error',
                'icon' => 'error',
                'color' => 'danger'
            ];
        }
    }

    /**
     * Get jadwal hari ini dengan toleransi
     *
     * @return array Jadwal lengkap dengan toleransi
     */
    public function getJadwalHariIni(): array {
        require_once __DIR__ . '/attendance_service.php';
        $jadwal = getJadwalHariIni($this->conn);

        // Tambahkan toleransi dari database
        $hari = $jadwal['hari'];
        $stmt = mysqli_prepare($this->conn, "SELECT batas_terlambat FROM jadwal_absensi WHERE hari = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $hari);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        $jadwal['batas_terlambat'] = $row ? (int)$row['batas_terlambat'] : 15;
        return $jadwal;
    }

    /**
     * Get rekap keterlambatan siswa
     *
     * @param string $filter 'hari'|'bulan'|'semester'
     * @param ?string $value Nilai filter (tanggal/bulan/tahun ajaran)
     * @return array Rekap keterlambatan
     */
    public function getRekapKeterlambatan(string $filter = 'bulan', ?string $value = null): array {
        if ($value === null) {
            $value = date('Y-m');
        }

        $whereClause = "";
        switch ($filter) {
            case 'hari':
                $whereClause = "WHERE a.tanggal = ?";
                break;
            case 'bulan':
                $whereClause = "WHERE DATE_FORMAT(a.tanggal, '%Y-%m') = ?";
                break;
            case 'semester':
                // Asumsi semester ganjil: Juli-Desember, genap: Januari-Juni
                $year = date('Y');
                if (date('n') >= 7) {
                    $whereClause = "WHERE a.tanggal BETWEEN '{$year}-07-01' AND '{$year}-12-31'";
                } else {
                    $whereClause = "WHERE a.tanggal BETWEEN '" . ($year-1) . "-07-01' AND '{$year}-06-30'";
                }
                break;
        }

        $query = "SELECT
                    s.nama_lengkap,
                    s.nis,
                    COUNT(CASE WHEN a.status_presensi = 'terlambat' THEN 1 END) as total_terlambat,
                    SUM(a.keterlambatan_menit) as total_menit_terlambat,
                    ROUND(AVG(a.keterlambatan_menit), 1) as rata_rata_menit
                  FROM siswa s
                  LEFT JOIN absensi a ON s.id_siswa = a.id_siswa {$whereClause}
                  GROUP BY s.id_siswa, s.nama_lengkap, s.nis
                  HAVING total_terlambat > 0
                  ORDER BY total_terlambat DESC, total_menit_terlambat DESC";

        if ($filter !== 'semester') {
            $stmt = mysqli_prepare($this->conn, $query);
            mysqli_stmt_bind_param($stmt, 's', $value);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
        } else {
            $result = mysqli_query($this->conn, $query);
        }

        $rekap = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rekap[] = $row;
        }

        if ($filter !== 'semester') {
            mysqli_stmt_close($stmt);
        }

        return $rekap;
    }

    /**
     * Get rekap keterlambatan harian
     */
    public function getRekapKeterlambatanHarian(string $tanggal): array {
        $query = "SELECT
                    s.nama_lengkap,
                    s.nisn,
                    k.nama_kelas,
                    COUNT(CASE WHEN a.status_presensi = 'terlambat' THEN 1 END) as total_terlambat,
                    SUM(a.keterlambatan_menit) as total_menit_terlambat
                  FROM siswa s
                  LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
                  LEFT JOIN absensi a ON s.id_siswa = a.id_siswa AND a.tanggal = ?
                  WHERE a.status_presensi = 'terlambat'
                  GROUP BY s.id_siswa, s.nama_lengkap, s.nisn, k.nama_kelas
                  ORDER BY total_terlambat DESC, total_menit_terlambat DESC";

        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, 's', $tanggal);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rekap = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rekap[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $rekap;
    }

    /**
     * Get rekap keterlambatan bulanan
     */
    public function getRekapKeterlambatanBulanan(string $bulan, string $tahun): array {
        $periode = $tahun . '-' . $bulan;

        $query = "SELECT
                    s.nama_lengkap,
                    s.nisn,
                    k.nama_kelas,
                    COUNT(CASE WHEN a.status_presensi = 'terlambat' THEN 1 END) as total_terlambat,
                    SUM(a.keterlambatan_menit) as total_menit_terlambat
                  FROM siswa s
                  LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
                  LEFT JOIN absensi a ON s.id_siswa = a.id_siswa
                  WHERE a.status_presensi = 'terlambat'
                  AND DATE_FORMAT(a.tanggal, '%Y-%m') = ?
                  GROUP BY s.id_siswa, s.nama_lengkap, s.nisn, k.nama_kelas
                  ORDER BY total_terlambat DESC, total_menit_terlambat DESC";

        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, 's', $periode);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rekap = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rekap[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $rekap;
    }

    /**
     * Get rekap keterlambatan semester
     */
    public function getRekapKeterlambatanSemester(int $semester, string $tahun): array {
        // Semester 1: Juli-Desember, Semester 2: Januari-Juni
        if ($semester == 1) {
            $start_date = $tahun . '-07-01';
            $end_date = $tahun . '-12-31';
        } else {
            $start_date = ($tahun + 1) . '-01-01';
            $end_date = ($tahun + 1) . '-06-30';
        }

        $query = "SELECT
                    s.nama_lengkap,
                    s.nisn,
                    k.nama_kelas,
                    COUNT(CASE WHEN a.status_presensi = 'terlambat' THEN 1 END) as total_terlambat,
                    SUM(a.keterlambatan_menit) as total_menit_terlambat
                  FROM siswa s
                  LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
                  LEFT JOIN absensi a ON s.id_siswa = a.id_siswa
                  WHERE a.status_presensi = 'terlambat'
                  AND a.tanggal BETWEEN ? AND ?
                  GROUP BY s.id_siswa, s.nama_lengkap, s.nisn, k.nama_kelas
                  ORDER BY total_terlambat DESC, total_menit_terlambat DESC";

        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rekap = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rekap[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $rekap;
    }
}
?>