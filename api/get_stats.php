<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/dashboard_service.php';
cek_login();

$dashboardService = new DashboardService($conn);
$summary = $dashboardService->getSummaryStats();

$total = (int)($summary['total_siswa'] ?? 0);
$hadir = (int)($summary['hadir_hari_ini'] ?? 0) + (int)($summary['terlambat_hari_ini'] ?? 0);
$terlambat = (int)($summary['terlambat_hari_ini'] ?? 0);
$belum = (int)($summary['belum_hadir'] ?? 0);
$persen = ($total > 0) ? round(($hadir / $total) * 100, 1) : 0;

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'total' => $total,
    'hadir' => $hadir,
    'terlambat' => $terlambat,
    'belum' => $belum,
    'persen' => $persen
]);
