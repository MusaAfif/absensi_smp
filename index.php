<?php
// Ensure errors are visible for API calls
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'includes/config.php';

// Handle API routing (for PHP dev server compatibility)
if (!empty($_GET['api'])) {
    $api = $_GET['api'];
    if ($api === 'attendance') {
        header('Content-Type: application/json; charset=utf-8');
        $api_file = 'api/attendance.php';
        if (file_exists($api_file)) {
            require_once $api_file;
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'API file not found']);
        }
        exit;
    }
}

// Handle routing for sub-applications
$page = $_GET['page'] ?? '';

if ($page === 'pengaturan') {
    require_once 'pengaturan/index.php';
    exit;
} elseif ($page === 'user') {
    safe_redirect('admin/user');
} elseif ($page === 'dashboard') {
    header("Location: " . BASE_URL . "pages/dashboard.php");
    exit;
} elseif ($page === 'admin/user') {
    require_once 'admin/user.php';
    exit;
} elseif ($page === 'admin/process_user') {
    require_once 'admin/process_user.php';
    exit;
} elseif ($page === 'pengaturan/process_pengaturan') {
    require_once 'pengaturan/process_pengaturan.php';
    exit;
} elseif ($page === 'pengaturan/process_admin') {
    require_once 'pengaturan/process_admin.php';
    exit;
} elseif ($page === 'restore_backup') {
    require_once 'api/restore.php';
    exit;
} elseif ($page === 'import_proses') {
    require_once 'pages/import_proses.php';
    exit;
} elseif (!empty($page)) {
    // Generic routing for pages folder
    // Convert page parameter to file path (e.g., 'dashboard' -> 'pages/dashboard.php')
    $page_file = 'pages/' . str_replace('/', '', $page) . '.php';
    $page_file = realpath($page_file);
    $pages_dir = realpath('pages/');
    
    // Security: verify file is in pages directory
    if ($page_file && $pages_dir && strpos($page_file, $pages_dir) === 0 && file_exists($page_file)) {
        require_once $page_file;
        exit;
    }
}

// Default routing
// Jika sudah ada session login, langsung ke Dashboard
if (isset($_SESSION['status']) && $_SESSION['status'] === 'login') {
    header("Location: " . BASE_URL . "pages/dashboard.php");
    exit;
} else {
    // Jika belum login, paksa ke halaman Login
    header("Location: " . BASE_URL . "login.php");
    exit;
}