<?php
// Query yang sudah disesuaikan agar tidak error lagi
$query = "SELECT 
            s.nama AS nama_tampilan, 
            k.nama_kelas, 
            a.jam_masuk, 
            a.status 
          FROM absensi a
          JOIN siswa s ON a.id_siswa = s.id_siswa
          JOIN kelas k ON s.id_kelas = k.id_kelas
          WHERE a.tanggal = CURDATE()
          ORDER BY a.jam_masuk DESC";
?>
