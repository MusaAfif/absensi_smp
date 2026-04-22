<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/dashboard_service.php';
cek_login();

$dashboardService = new DashboardService($conn);
$rows = $dashboardService->getRecentAttendance(5);

if (empty($rows)) {
    echo '<div class="text-center text-muted py-5">Belum ada data.</div>';
}

foreach ($rows as $row) {
    echo '
    <div class="card mb-2 border-0 shadow-sm" style="background: #2d2d44; color: #fff;">
        <div class="card-body p-2 d-flex justify-content-between align-items-center">
            <div>
                <strong class="d-block text-warning">'.htmlspecialchars($row['nama_lengkap']).'</strong>
                <small class="text-white-50">Kelas: '.htmlspecialchars($row['nama_kelas']).'</small>
            </div>
            <div class="text-end">
                <span class="badge bg-'.htmlspecialchars($row['status_class']).'">'.htmlspecialchars($row['status_label']).'</span>
                <small class="d-block mt-1" style="font-size: 10px;">'.htmlspecialchars($row['jam']).'</small>
            </div>
        </div>
    </div>';
}
?>