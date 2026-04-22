<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/attendance_service.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
cek_role(['super_admin']);

require_once __DIR__ . '/service/recovery_service.php';
require_once __DIR__ . '/service/user_service.php';

CSRFProtection::init();

$recoveryService = new RecoveryService($conn);
$userService = new UserService($conn, $recoveryService);
$userService->ensureSchema();

$action = $_POST['action'] ?? '';
$redirect_page = 'admin/user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'CSRF token tidak valid. Silakan coba lagi.';
        safe_redirect($redirect_page);
    }

    switch ($action) {
        case 'add':
            $username = $_POST['username'] ?? '';
            $nama = $_POST['nama_admin'] ?? '';
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';
            $role = $_POST['role'] ?? 'admin';

            if ($password !== $passwordConfirm) {
                $result = ['success' => false, 'message' => 'Password dan konfirmasi tidak cocok.'];
                break;
            }

            $result = $userService->createAdmin($username, $nama, $password, $role);
            break;

        case 'edit':
            $id = (int)($_POST['id_user'] ?? 0);
            $nama = $_POST['nama_admin'] ?? '';
            $role = $_POST['role'] ?? 'admin';
            $password = $_POST['password'] ?? null;
            $passwordConfirm = $_POST['password_confirm'] ?? null;

            if ($password !== null && $password !== '' && $password !== $passwordConfirm) {
                $result = ['success' => false, 'message' => 'Password dan konfirmasi tidak cocok.'];
                break;
            }

            $result = $userService->updateAdmin($id, $nama, $role, $password);
            break;

        case 'delete':
            $id = (int)($_POST['id_user'] ?? 0);
            $currentUserId = $_SESSION['id_user'] ?? 0;
            $result = $userService->deleteAdmin($id, $currentUserId);
            break;

        case 'regenerate_codes':
            $id = (int)($_POST['id_user'] ?? 0);
            if (!$userService->getAdminById($id)) {
                $result = ['success' => false, 'message' => 'Admin tidak ditemukan.'];
            } else {
                $codes = $recoveryService->generateCodes($id);
                $result = ['success' => true, 'message' => 'Recovery code baru berhasil dibuat.', 'generated_codes' => $codes];
            }
            break;

        case 'reset_codes':
            $id = (int)($_POST['id_user'] ?? 0);
            if (!$userService->getAdminById($id)) {
                $result = ['success' => false, 'message' => 'Admin tidak ditemukan.'];
            } else {
                $success = $recoveryService->resetCodesForUser($id);
                $result = $success
                    ? ['success' => true, 'message' => 'Recovery code berhasil direset untuk admin.']
                    : ['success' => false, 'message' => 'Gagal reset recovery code.'];
            }
            break;

        case 'reset_all_codes':
            $success = $recoveryService->resetAllCodes();
            $result = $success
                ? ['success' => true, 'message' => 'Semua recovery code berhasil direset.']
                : ['success' => false, 'message' => 'Gagal reset seluruh recovery code.'];
            break;

        default:
            $result = ['success' => false, 'message' => 'Aksi tidak dikenali.'];
            break;
    }

    if ($result['success']) {
        $_SESSION['success'] = $result['message'];
        if (!empty($result['generated_codes'])) {
            $_SESSION['generated_codes'] = $result['generated_codes'];
        }
    } else {
        $_SESSION['error'] = $result['message'];
    }
}

safe_redirect($redirect_page);
