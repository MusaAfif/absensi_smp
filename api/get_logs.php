<?php
require_once __DIR__ . '/../includes/config.php';
cek_login();

$stmt = mysqli_prepare(
    $conn,
    "SELECT a.jam, s.nama_lengkap, st.nama_status, st.id_status
     FROM absensi a
     JOIN siswa s ON a.id_siswa = s.id_siswa
     JOIN status_absen st ON a.id_status = st.id_status
     WHERE a.tanggal = CURDATE()
     ORDER BY a.id_absen DESC
     LIMIT 10"
);

mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($res) > 0) {
    while($row = mysqli_fetch_assoc($res)) {
        $color = ($row['id_status'] == 5) ? 'bg-danger' : 'bg-success';
        echo "<tr>
                <td class='ps-3 fw-bold'>".htmlspecialchars(substr($row['jam'], 0, 5))."</td>
                <td>".htmlspecialchars($row['nama_lengkap'])."</td>
                <td class='text-center'><span class='badge $color'>".htmlspecialchars($row['nama_status'])."</span></td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='3' class='text-center py-4 text-muted'>Belum ada siswa yang absen hari ini.</td></tr>";
}

mysqli_stmt_close($stmt);
