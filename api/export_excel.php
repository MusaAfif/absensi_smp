<?php
require_once __DIR__ . '/../includes/config.php';
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Absensi_".date('Ymd').".xls");

$tgl_mulai = $_GET['tgl_mulai'];
$tgl_selesai = $_GET['tgl_selesai'];
$id_kelas = $_GET['id_kelas'];

$sql = "SELECT a.*, s.nis, s.nama_lengkap, k.nama_kelas, st.nama_status 
        FROM absensi a JOIN siswa s ON a.id_siswa = s.id_siswa JOIN kelas k ON s.id_kelas = k.id_kelas JOIN status_absen st ON a.id_status = st.id_status 
        WHERE a.tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai'";
if($id_kelas != '') $sql .= " AND s.id_kelas = '$id_kelas'";
$data = mysqli_query($conn, $sql);
?>
<table border="1">
    <tr><th colspan="5">LAPORAN ABSENSI SMP NEGERI 1 INDONESIA</th></tr>
    <tr><th>Tgl</th><th>NIS</th><th>Nama</th><th>Kelas</th><th>Status</th></tr>
    <?php while($r = mysqli_fetch_assoc($data)): ?>
    <tr>
        <td><?= $r['tanggal'] ?></td>
        <td>'<?= $r['nis'] ?></td> <td><?= $r['nama_lengkap'] ?></td>
        <td><?= $r['nama_kelas'] ?></td>
        <td><?= $r['nama_status'] ?></td>
    </tr>
    <?php endwhile; ?>
</table>
