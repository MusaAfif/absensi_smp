<?php
require_once __DIR__ . '/../includes/config.php';
cek_role(['super_admin']);

// Nama file hasil backup
$nama_file = "backup_absensi_" . date('Y-m-d_H-i-s') . ".sql";

// Header untuk mendownload file
header("Content-disposition: attachment; filename=" . $nama_file);
header("Content-type: application/sql");

// Logika backup lengkap
$tables = array();
$result = mysqli_query($conn, "SHOW TABLES");
while ($row = mysqli_fetch_row($result)) {
    $tables[] = $row[0];
}

$return = "-- Backup Database Absensi SMP\n";
$return .= "-- Tanggal: " . date('Y-m-d H:i:s') . "\n";
$return .= "-- Sistem: Absensi SMP\n\n";
$return .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

foreach ($tables as $table) {
    $result = mysqli_query($conn, "SELECT * FROM `$table`");
    $num_fields = mysqli_num_fields($result);

    $return .= "DROP TABLE IF EXISTS `$table`;\n";
    $row2 = mysqli_fetch_row(mysqli_query($conn, "SHOW CREATE TABLE `$table`"));
    $return .= $row2[1] . ";\n\n";

    if (mysqli_num_rows($result) > 0) {
        $return .= "INSERT INTO `$table` VALUES\n";

        $rows = array();
        while ($row = mysqli_fetch_row($result)) {
            $row_values = array();
            for ($j = 0; $j < $num_fields; $j++) {
                if ($row[$j] === null) {
                    $row_values[] = "NULL";
                } else {
                    $row_values[] = "'" . mysqli_real_escape_string($conn, $row[$j]) . "'";
                }
            }
            $rows[] = "(" . implode(", ", $row_values) . ")";
        }

        $return .= implode(",\n", $rows) . ";\n\n";
    }
}

$return .= "SET FOREIGN_KEY_CHECKS = 1;\n";
$return .= "\n-- Backup selesai\n";

echo $return;
exit;
