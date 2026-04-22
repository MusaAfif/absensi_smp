<?php
// Service class - Database connection passed from controller
class AdminService {
    private $conn;

    private function validatePassword(string $password): ?string {
        if (strlen($password) < 8) {
            return 'Password minimal 8 karakter.';
        }

        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return 'Password harus mengandung minimal 1 huruf besar dan 1 angka.';
        }

        return null;
    }

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function generateRecoveryCode($length = 8) {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $maxIndex = strlen($characters) - 1;
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[mt_rand(0, $maxIndex)];
        }
        return $code;
    }

    public function tambahAdmin($postData) {
        $username = trim($postData['username'] ?? '');
        $nama_admin = trim($postData['nama_admin'] ?? '');
        $password = $postData['password'] ?? '';
        $konfirmasi = $postData['konfirmasi'] ?? '';

        if (empty($username) || empty($nama_admin) || empty($password) || empty($konfirmasi)) {
            return ['success' => false, 'message' => "Semua kolom admin wajib diisi."];
        }

        if ($password !== $konfirmasi) {
            return ['success' => false, 'message' => "Password dan konfirmasi tidak cocok."];
        }

        $passwordError = $this->validatePassword($password);
        if ($passwordError !== null) {
            return ['success' => false, 'message' => $passwordError];
        }

        $stmt = mysqli_prepare($this->conn, 'SELECT id_user FROM users WHERE username = ? LIMIT 1');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Gagal validasi username.'];
        }

        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $cek = mysqli_stmt_get_result($stmt);
        $exists = $cek && mysqli_num_rows($cek) > 0;
        mysqli_stmt_close($stmt);

        if ($exists) {
            return ['success' => false, 'message' => "Username admin sudah terdaftar."];
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare($this->conn, 'INSERT INTO users (username, password, nama_admin) VALUES (?, ?, ?)');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Gagal menyiapkan penyimpanan admin.'];
        }

        mysqli_stmt_bind_param($stmt, 'sss', $username, $passwordHash, $nama_admin);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            $user_id = mysqli_insert_id($this->conn);
            $generated_codes = [];

            $codeStmt = mysqli_prepare($this->conn, 'INSERT INTO recovery_codes (id_user, code) VALUES (?, ?)');
            if (!$codeStmt) {
                return ['success' => false, 'message' => 'Admin dibuat, tetapi gagal menyiapkan recovery code.'];
            }

            for ($i = 0; $i < 5; $i++) {
                $code = $this->generateRecoveryCode();
                mysqli_stmt_bind_param($codeStmt, 'is', $user_id, $code);
                mysqli_stmt_execute($codeStmt);
                $generated_codes[] = $code;
            }

            mysqli_stmt_close($codeStmt);

            return [
                'success' => true,
                'message' => "Admin baru berhasil ditambahkan. Simpan kode cadangan dengan aman.",
                'generated_codes' => $generated_codes
            ];
        } else {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            return ['success' => false, 'message' => "Gagal menambah admin: " . $error];
        }
    }

    public function getAdminList() {
        $admins = [];
        $q_admins = mysqli_query($this->conn, "SELECT u.username, u.nama_admin, COUNT(r.id) AS kode_tersisa FROM users u LEFT JOIN recovery_codes r ON u.id_user = r.id_user AND r.used = 0 GROUP BY u.id_user ORDER BY u.id_user DESC");
        while ($admin = mysqli_fetch_assoc($q_admins)) {
            $admins[] = $admin;
        }
        return $admins;
    }
}
?>