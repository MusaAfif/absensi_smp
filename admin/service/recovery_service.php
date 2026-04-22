<?php
class RecoveryService {
    private $conn;
    private const CODE_LENGTH = 8;
    private const CODE_COUNT = 5;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    private function tableExists(string $table): bool {
        $table = mysqli_real_escape_string($this->conn, $table);
        $result = mysqli_query($this->conn, "SHOW TABLES LIKE '$table'");
        return $result && mysqli_num_rows($result) > 0;
    }

    public function ensureSchema(): void {
        if (!$this->tableExists('recovery_codes')) {
            $create = "CREATE TABLE IF NOT EXISTS recovery_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_user INT NOT NULL,
                code VARCHAR(64) NOT NULL UNIQUE,
                used BOOLEAN NOT NULL DEFAULT FALSE,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                used_at DATETIME DEFAULT NULL,
                INDEX (id_user)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            mysqli_query($this->conn, $create);
        }
    }

    public function generateRecoveryCode(int $length = self::CODE_LENGTH): string {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }

    public function generateCodes(int $userId, int $count = self::CODE_COUNT): array {
        $codes = [];
        $stmt = mysqli_prepare($this->conn, "INSERT INTO recovery_codes (id_user, code) VALUES (?, ?)");
        if (!$stmt) {
            throw new RuntimeException('Gagal siapkan statement recovery_codes: ' . mysqli_error($this->conn));
        }

        while (count($codes) < $count) {
            $code = $this->generateRecoveryCode();
            mysqli_stmt_bind_param($stmt, 'is', $userId, $code);
            if (mysqli_stmt_execute($stmt)) {
                $codes[] = $code;
            } else {
                if (mysqli_errno($this->conn) === 1062) {
                    continue;
                }
                throw new RuntimeException('Gagal buat recovery code: ' . mysqli_stmt_error($stmt));
            }
        }
        mysqli_stmt_close($stmt);
        return $codes;
    }

    public function getCodesByUser(int $userId): array {
        $stmt = mysqli_prepare($this->conn, "SELECT id, code, used, created_at, used_at FROM recovery_codes WHERE id_user = ? ORDER BY created_at DESC");
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $codes = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $codes[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $codes;
    }

    public function countAvailableCodes(int $userId): int {
        $stmt = mysqli_prepare($this->conn, "SELECT COUNT(*) AS total FROM recovery_codes WHERE id_user = ? AND used = 0");
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return (int)($row['total'] ?? 0);
    }

    public function resetCodesForUser(int $userId): bool {
        $stmt = mysqli_prepare($this->conn, "UPDATE recovery_codes SET used = 0, used_at = NULL WHERE id_user = ?");
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        $executed = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $executed;
    }

    public function resetAllCodes(): bool {
        return mysqli_query($this->conn, "UPDATE recovery_codes SET used = 0, used_at = NULL");
    }
}
