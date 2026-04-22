<?php
require_once __DIR__ . '/../includes/config.php';
$kls = $_GET['kelas'] ?? '';
$where = "WHERE a.tanggal = CURDATE()";
if($kls != '') $where .= " AND s.id_kelas = '$kls'";

$sql = "SELECT a.jam, s.nama_lengkap, k.nama_kelas, a.id_status 
        FROM absensi a 
        JOIN siswa s ON a.id_siswa = s.id_siswa 
        JOIN kelas k ON s.id_kelas = k.id_kelas 
        $where ORDER BY a.id_absen DESC LIMIT 6";

$res = mysqli_query($conn, $sql);
while($row = mysqli_fetch_assoc($res)) {
    $border = ($row['id_status'] == 5) ? 'border-danger' : 'border-success';
    $badge = ($row['id_status'] == 5) ? 'bg-danger' : 'bg-success';
    $status = ($row['id_status'] == 5) ? 'Terlambat' : 'Hadir';
    
    echo '
    <div class="col-md-6">
        <div class="card log-card p-3 shadow-sm border-0" style="border-left: 5px solid '.($row['id_status'] == 5 ? "#dc3545":"#198754").' !important;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fw-bold mb-0 text-white">'.$row['nama_lengkap'].'</h6>
                    <small class="text-muted">'.$row['nama_kelas'].'</small>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-primary">'.substr($row['jam'],0,5).'</div>
                    <span class="status-badge '.$badge.' text-white">'.$status.'</span>
                </div>
            </div>
        </div>
    </div>';
}
