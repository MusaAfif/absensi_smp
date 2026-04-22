<?php
class UserService {
    private $conn;
    private $recoveryService;

    public function __construct($conn, RecoveryService $recoveryService) {
        $this->conn = $conn;
        $this->recoveryService = $recoveryService;
    }

    private function validatePasswordStrength(string $password): ?string {
        if (strlen($password) < 8) {
            return 'Password minimal 8 karakter.';
        }

        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return 'Password harus mengandung minimal 1 huruf besar dan 1 angka.';
        }

        return null;
    }

    private function tableExists(string $table): bool {
        $table = mysqli_real_escape_string($this->conn, $table);
        $result = mysqli_query($this->conn, "SHOW TABLES LIKE '$table'");
        return $result && mysqli_num_rows($result) > 0;
    }

    public function ensureSchema(): void {
        if (!$this->tableExists('users')) {
            throw new RuntimeException('Tabel users tidak ditemukan.');
        }

        if (!columnExists($this->conn, 'users', 'role')) {
            mysqli_query($this->conn, "ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin'");
        }

        $this->recoveryService->ensureSchema();
    }

    public function getAllAdmins(?int $currentUserId = null): array {
        $query = "SELECT u.id_user, u.username, u.nama_admin, COALESCE(u.role, 'admin') AS role,
                     COUNT(r.id) AS recovery_left
                  FROM users u
                  LEFT JOIN recovery_codes r ON u.id_user = r.id_user AND r.used = 0
                  GROUP BY u.id_user
                  ORDER BY u.id_user DESC";
        $result = mysqli_query($this->conn, $query);
        $admins = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['is_current_user'] = $currentUserId && ((int)$row['id_user'] === (int)$currentUserId);
            $admins[] = $row;
        }
        return $admins;
    }

    public function getAdminById(int $id): ?array {
        $stmt = mysqli_prepare($this->conn, "SELECT id_user, username, nama_admin, role FROM users WHERE id_user = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $admin ?: null;
    }

    public function createAdmin(string $username, string $nama, string $password, string $role): array {
        $username = trim($username);
        $nama = trim($nama);
        $role = trim($role);

        if ($username === '' || $nama === '' || $password === '' || $role === '') {
            return ['success' => false, 'message' => 'Semua kolom wajib diisi.'];
        }

        if (!in_array($role, ['admin', 'super_admin'], true)) {
            return ['success' => false, 'message' => 'Role tidak valid.'];
        }

        $passwordError = $this->validatePasswordStrength($password);
        if ($passwordError !== null) {
            return ['success' => false, 'message' => $passwordError];
        }

        $stmt = mysqli_prepare($this->conn, "SELECT id_user FROM users WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $duplicate = mysqli_stmt_get_result($stmt);
        if ($duplicate && mysqli_num_rows($duplicate) > 0) {
            mysqli_stmt_close($stmt);
            return ['success' => false, 'message' => 'Username sudah terdaftar.'];
        }
        mysqli_stmt_close($stmt);

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare($this->conn, "INSERT INTO users (username, password, nama_admin, role) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssss', $username, $passwordHash, $nama, $role);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            return ['success' => false, 'message' => 'Gagal membuat admin: ' . $error];
        }

        $userId = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        $generatedCodes = $this->recoveryService->generateCodes($userId);

        return [
            'success' => true,
            'message' => 'Admin berhasil dibuat. Recovery code otomatis digenerate.',
            'generated_codes' => $generatedCodes
        ];
    }

    public function updateAdmin(int $id, string $nama, string $role, ?string $password = null): array {
        $nama = trim($nama);
        $role = trim($role);

        if ($nama === '' || $role === '') {
            return ['success' => false, 'message' => 'Nama dan role wajib diisi.'];
        }

        if (!in_array($role, ['admin', 'super_admin'], true)) {
            return ['success' => false, 'message' => 'Role tidak valid.'];
        }

        if (!$this->getAdminById($id)) {
            return ['success' => false, 'message' => 'Admin tidak ditemukan.'];
        }

        if ($password !== null && $password !== '') {
            $passwordError = $this->validatePasswordStrength($password);
            if ($passwordError !== null) {
                return ['success' => false, 'message' => $passwordError];
            }
        }

        if ($password !== null && $password !== '') {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = mysqli_prepare($this->conn, "UPDATE users SET nama_admin = ?, role = ?, password = ? WHERE id_user = ?");
            mysqli_stmt_bind_param($stmt, 'sssi', $nama, $role, $passwordHash, $id);
        } else {
            $stmt = mysqli_prepare($this->conn, "UPDATE users SET nama_admin = ?, role = ? WHERE id_user = ?");
            mysqli_stmt_bind_param($stmt, 'ssi', $nama, $role, $id);
        }

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            return ['success' => false, 'message' => 'Gagal simpan perubahan: ' . $error];
        }

        mysqli_stmt_close($stmt);
        return ['success' => true, 'message' => 'Data admin diperbarui.'];
    }

    public function deleteAdmin(int $id, int $currentUserId): array {
        if ($id === $currentUserId) {
            return ['success' => false, 'message' => 'Akun sedang login tidak boleh dihapus.'];
        }

        if (!$this->getAdminById($id)) {
            return ['success' => false, 'message' => 'Admin tidak ditemukan.'];
        }

        $stmt = mysqli_prepare($this->conn, "DELETE FROM recovery_codes WHERE id_user = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($this->conn, "DELETE FROM users WHERE id_user = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            return ['success' => false, 'message' => 'Gagal hapus admin: ' . $error];
        }

        mysqli_stmt_close($stmt);
        return ['success' => true, 'message' => 'Admin berhasil dihapus.'];
    }
}
