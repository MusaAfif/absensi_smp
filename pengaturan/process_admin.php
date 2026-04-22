<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
cek_role(['super_admin']);

// Inisialisasi session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/service/admin_service.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Token keamanan tidak valid.';
        safe_redirect('pengaturan');
    }
    
    $adminService = new AdminService($conn);
    $result = $adminService->tambahAdmin($_POST);

    if ($result['success']) {
        $_SESSION['success'] = $result['message'];
        $_SESSION['generated_codes'] = $result['generated_codes'];
    } else {
        $_SESSION['error'] = $result['message'];
    }

    // Redirect untuk mencegah resubmit
    safe_redirect('pengaturan');
}

// Jika bukan POST, redirect ke index
safe_redirect('pengaturan');
?>