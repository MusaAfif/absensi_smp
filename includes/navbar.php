<?php
// Helper function untuk menentukan status scan berdasarkan jadwal
function getJadwalStatus($conn) {
    require_once __DIR__ . '/attendance_service.php';
    
    $jadwal = getJadwalHariIni($conn);
    $jamSekarang = strtotime(date('H:i'));
    
    // Gunakan range waktu baru
    $masukMulai = strtotime($jadwal['jam_masuk_mulai']);
    $masukSelesai = strtotime($jadwal['jam_masuk_selesai']);
    $batasTerlambat = strtotime('+' . ((int)($jadwal['batas_terlambat'] ?? 15)) . ' minutes', $masukSelesai);
    $pulangMulai = strtotime($jadwal['jam_pulang_mulai']);
    $pulangSelesai = strtotime($jadwal['jam_pulang_selesai']);
    
    // Status: 'masuk', 'pulang', 'tutup'
    if ($jamSekarang < $masukMulai) {
        return [
            'status' => 'tutup',
            'label' => 'Jadwal Belum Dimulai',
            'text' => '⏰ Menunggu ' . $jadwal['jam_masuk_mulai'],
            'disabled' => true,
            'phase' => 'before'
        ];
    } elseif ($jamSekarang >= $masukMulai && $jamSekarang <= $batasTerlambat) {
        return [
            'status' => 'masuk',
            'label' => 'Buka Absen Masuk',
            'text' => '📥 Absen Masuk',
            'disabled' => false,
            'phase' => 'entry'
        ];
    } elseif ($jamSekarang >= $pulangMulai && $jamSekarang <= $pulangSelesai) {
        return [
            'status' => 'pulang',
            'label' => 'Buka Absen Pulang',
            'text' => '📤 Absen Pulang',
            'disabled' => false,
            'phase' => 'exit'
        ];
    } else {
        return [
            'status' => 'tutup',
            'label' => 'Jadwal Sudah Tutup',
            'text' => '🔒 Tutup',
            'disabled' => true,
            'phase' => 'after'
        ];
    }
}

$currentPath = $_SERVER['SCRIPT_NAME'];
$scanStatus = getJadwalStatus($conn);

// Menu items dengan icon - urutan: Dashboard, Siswa, Kelas, Laporan Gabungan, Scan, User Management, Pengaturan
$navItems = [
    APP_BASE_URL . '/pages/dashboard.php' => ['label' => 'Dashboard', 'icon' => 'fas fa-chart-line'],
    APP_BASE_URL . '/pages/siswa.php' => ['label' => 'Data Siswa', 'icon' => 'fas fa-users'],
    APP_BASE_URL . '/pages/kelas.php' => ['label' => 'Kelas', 'icon' => 'fas fa-door-open'],
    APP_BASE_URL . '/pages/laporan_gabungan.php' => ['label' => 'Laporan & Rekap', 'icon' => 'fas fa-file-alt'],
    APP_BASE_URL . '/pages/scan_center.php' => ['label' => 'Scan Absensi', 'icon' => 'fas fa-qrcode'],
    APP_BASE_URL . '/pengaturan/' => ['label' => 'Pengaturan', 'icon' => 'fas fa-cog'],
];

// Fitur super_admin only
if (($_SESSION['role'] ?? '') === 'super_admin') {
    $navItems[APP_BASE_URL . '/pages/semester.php'] = ['label' => 'Semester Aktif', 'icon' => 'fas fa-calendar-check'];
    $navItems[APP_BASE_URL . '/admin/user.php'] = ['label' => 'User Management', 'icon' => 'fas fa-user-shield'];
    $navItems[APP_BASE_URL . '/pages/backup.php'] = ['label' => 'Backup & Restore', 'icon' => 'fas fa-database'];
}

$namaAdmin = $_SESSION['nama_admin'] ?? $_SESSION['admin'] ?? 'Administrator';
$roleLabel = $_SESSION['role'] ?? 'Admin';
$initials = trim(preg_replace('/[^A-Z]/', '', strtoupper(substr($namaAdmin, 0, 2))));
if (empty($initials)) {
    $parts = explode(' ', trim($namaAdmin));
    $initials = strtoupper(substr($parts[0], 0, 1));
}

function renderMenuItems($navItems, $currentPath) {
    foreach ($navItems as $url => $label_data):
        $label = is_array($label_data) ? $label_data['label'] : $label_data;
        $icon = is_array($label_data) ? $label_data['icon'] : 'fas fa-link';
        $isActive = $currentPath === $url ? 'active' : '';
        ?>
        <li class="nav-item">
            <a class="nav-link <?= $isActive ?>" href="<?= $url ?>" title="<?= htmlspecialchars($label) ?>">
                <i class="<?= $icon ?> nav-icon"></i>
                <span class="nav-label"><?= htmlspecialchars($label) ?></span>
            </a>
        </li>
        <?php
    endforeach;
}

function renderScanButton($scanStatus, $full_width = false) {
    $disabled_attr = $scanStatus['disabled'] ? 'disabled aria-disabled="true" onclick="return false;"' : '';
    $class = trim(''.($scanStatus['disabled'] ? 'disabled' : '').($full_width ? ' w-100' : ''));
    ?>
    <a href="<?= BASE_URL ?>pages/scan_center.php" 
       class="btn btn-scan <?= $class ?>"
       <?= $disabled_attr ?>
       title="<?= htmlspecialchars($scanStatus['label']) ?>"
       data-phase="<?= $scanStatus['phase'] ?>">
        <span class="scan-text"><?= htmlspecialchars($scanStatus['text']) ?></span>
    </a>
    <?php
}
?>

<nav class="navbar navbar-expand-xl navbar-dark app-navbar mb-4">
    <div class="container-fluid d-flex align-items-center justify-content-between">
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>pages/dashboard.php">
            <div class="brand-text">
                <div class="brand-title">Absensi Siswa</div>
                <div class="brand-subtitle">Sistem Absensi Sekolah</div>
            </div>
        </a>

        <!-- Mobile Toggle -->
        <button class="navbar-toggler d-xl-none" type="button" data-bs-toggle="offcanvas" 
                data-bs-target="#appNavbarOffcanvas" aria-controls="appNavbarOffcanvas" 
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Desktop Navigation -->
        <div class="navbar-collapse d-none d-xl-flex ms-auto">
            <ul class="navbar-nav nav-menu flex-row">
                <?php renderMenuItems($navItems, $currentPath); ?>
            </ul>

            <div class="navbar-right d-flex align-items-center gap-2">
                <?php renderScanButton($scanStatus); ?>

                <div class="dropdown user-dropdown">
                    <button class="btn btn-user dropdown-toggle d-flex align-items-center" 
                            type="button" id="userDropdown" data-bs-toggle="dropdown" 
                            aria-expanded="false" title="<?= htmlspecialchars($namaAdmin) ?>">
                        <span class="avatar avatar-gradient">
                            <?= htmlspecialchars($initials) ?>
                        </span>
                        <span class="user-text d-none d-sm-inline-flex">
                            <span class="user-name"><?= htmlspecialchars(substr($namaAdmin, 0, 16)) ?></span>
                            <span class="user-role"><?= htmlspecialchars($roleLabel) ?></span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/profile.php"><i class="fas fa-user-circle me-2"></i>Profil</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>reset_password.php"><i class="fas fa-key me-2"></i>Ubah Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation (Offcanvas) -->
        <div class="offcanvas offcanvas-start app-navbar-offcanvas d-xl-none" tabindex="-1" id="appNavbarOffcanvas">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Menu Navigasi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column">
                <ul class="navbar-nav nav-menu flex-column w-100 mb-3">
                    <?php renderMenuItems($navItems, $currentPath); ?>
                </ul>

                <hr class="bg-light">

                <div class="navbar-right d-flex flex-column w-100 gap-2">
                    <?php renderScanButton($scanStatus, true); ?>

                    <div class="mobile-user-info p-3 bg-light rounded">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="avatar avatar-gradient avatar-mobile">
                                <?= htmlspecialchars($initials) ?>
                            </span>
                            <div>
                                <div class="font-weight-bold text-dark"><?= htmlspecialchars($namaAdmin) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($roleLabel) ?></small>
                            </div>
                        </div>
                        <a href="<?= BASE_URL ?>pages/profile.php" class="btn btn-sm btn-outline-dark w-100 mb-2">
                            <i class="fas fa-user-circle me-1"></i>Profil
                        </a>
                        <a href="<?= BASE_URL ?>reset_password.php" class="btn btn-sm btn-outline-dark w-100 mb-2">
                            <i class="fas fa-key me-1"></i>Ubah Password
                        </a>
                        <a href="<?= BASE_URL ?>logout.php" class="btn btn-sm btn-danger w-100">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

