<?php

class DashboardService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function getSettings(): array
    {
        $settings = [
            'logo_sekolah' => 'default_logo.png',
            'nama_sekolah' => 'SMPN 1 Indonesia'
        ];

        $stmt = $this->conn->prepare(
            "SELECT nama_pengaturan, isi_pengaturan
             FROM pengaturan
             WHERE nama_pengaturan IN ('logo_sekolah', 'nama_sekolah')"
        );

        if (!$stmt) {
            return $settings;
        }

        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $settings[$row['nama_pengaturan']] = $row['isi_pengaturan'];
        }
        $stmt->close();

        return $settings;
    }

    public function getSummaryStats(): array
    {
        $totalSiswa = (int)($this->conn->query('SELECT COUNT(*) AS total FROM siswa')->fetch_assoc()['total'] ?? 0);

        $hadir = 0;
        $terlambat = 0;

        $stmt = $this->conn->prepare(
            "SELECT
                SUM(CASE
                    WHEN status_presensi = 'tepat_waktu' OR (status_presensi IS NULL AND status = 'Hadir')
                    THEN 1 ELSE 0 END) AS hadir,
                SUM(CASE
                    WHEN status_presensi = 'terlambat' OR status = 'Terlambat'
                    THEN 1 ELSE 0 END) AS telat
             FROM absensi
             WHERE tanggal = CURDATE()"
        );

        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $hadir = (int)($row['hadir'] ?? 0);
            $terlambat = (int)($row['telat'] ?? 0);
            $stmt->close();
        }

        return [
            'total_siswa' => $totalSiswa,
            'hadir_hari_ini' => $hadir,
            'terlambat_hari_ini' => $terlambat,
            'belum_hadir' => max(0, $totalSiswa - ($hadir + $terlambat))
        ];
    }

    public function getWeeklyAttendance(): array
    {
        $sql = "SELECT
                    tanggal,
                    SUM(CASE
                        WHEN status_presensi = 'tepat_waktu' OR (status_presensi IS NULL AND status = 'Hadir')
                        THEN 1 ELSE 0 END) AS hadir,
                    SUM(CASE
                        WHEN status_presensi = 'terlambat' OR status = 'Terlambat'
                        THEN 1 ELSE 0 END) AS telat
                FROM absensi
                WHERE tanggal BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
                GROUP BY tanggal";

        $raw = [];
        $result = $this->conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $raw[$row['tanggal']] = [
                    'hadir' => (int)$row['hadir'],
                    'telat' => (int)$row['telat']
                ];
            }
        }

        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $series[] = [
                'date' => $date,
                'hadir' => $raw[$date]['hadir'] ?? 0,
                'telat' => $raw[$date]['telat'] ?? 0
            ];
        }

        return $series;
    }

    public function getClassAttendancePercentages(): array
    {
        $sql = "SELECT
                    k.nama_kelas,
                    COUNT(s.id_siswa) AS total_siswa,
                    COUNT(CASE
                        WHEN (a.status_presensi IN ('tepat_waktu', 'terlambat')
                            OR (a.status_presensi IS NULL AND a.status = 'Hadir')
                            OR a.status = 'Terlambat')
                        THEN 1 END) AS hadir
                FROM kelas k
                LEFT JOIN siswa s ON k.id_kelas = s.id_kelas
                LEFT JOIN absensi a ON s.id_siswa = a.id_siswa AND a.tanggal = CURDATE()
                GROUP BY k.id_kelas
                ORDER BY k.nama_kelas ASC";

        $result = $this->conn->query($sql);
        $data = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $total = (int)$row['total_siswa'];
                $hadir = (int)$row['hadir'];
                $data[] = [
                    'kelas' => $row['nama_kelas'],
                    'persen' => $total > 0 ? (int)round(($hadir / $total) * 100) : 0
                ];
            }
        }

        return $data;
    }

    public function getRecentAttendance(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $sql = "SELECT
                    a.waktu_absen,
                    a.jam,
                    a.status_presensi,
                    a.status,
                    a.tanggal,
                    s.nama_lengkap,
                    k.nama_kelas,
                    st.nama_status
                FROM absensi a
                JOIN siswa s ON a.id_siswa = s.id_siswa
                JOIN kelas k ON s.id_kelas = k.id_kelas
                LEFT JOIN status_absen st ON a.id_status = st.id_status
                ORDER BY a.tanggal DESC, COALESCE(a.waktu_absen, a.jam) DESC
                LIMIT ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $statusClass = 'secondary';
            $statusLabel = $row['nama_status'] ?? $row['status'] ?? '-';

            if ($row['status_presensi'] === 'terlambat') {
                $statusClass = 'warning';
                $statusLabel = 'Terlambat';
            } elseif ($row['status_presensi'] === 'tepat_waktu') {
                $statusClass = 'success';
                $statusLabel = 'Tepat Waktu';
            } elseif (($row['status'] ?? '') === 'Hadir' || ($row['nama_status'] ?? '') === 'HADIR') {
                $statusClass = 'success';
            }

            $rows[] = [
                'nama_lengkap' => $row['nama_lengkap'],
                'nama_kelas' => $row['nama_kelas'],
                'jam' => date('H:i', strtotime($row['waktu_absen'] ?? $row['jam'] ?? '00:00:00')),
                'status_class' => $statusClass,
                'status_label' => $statusLabel,
                'tanggal' => $row['tanggal']
            ];
        }

        $stmt->close();
        return $rows;
    }
}
