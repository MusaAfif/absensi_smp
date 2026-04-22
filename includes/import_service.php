<?php
/**
 * IMPORT SERVICE - ROBUST CSV IMPORT WITH VALIDATION & ERROR LOGGING
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

class StudentImportService
{
    private mysqli $conn;
    private string $logFile;
    private array $errors = [];
    private array $stats = ['success' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
    private array $classMap = [];
    private ?array $activeTahunAjaran = null;
    private bool $hasRelationalSchema = false;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
        $this->logFile = __DIR__ . '/../logs/import_' . date('Ymd_His') . '.log';
        $this->initializeLogFile();
        $this->checkSchema();
    }

    private function initializeLogFile(): void
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        file_put_contents($this->logFile, "=== IMPORT LOG " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
    }

    private function checkSchema(): void
    {
        $result = mysqli_query($this->conn, "SHOW TABLES LIKE 'siswa_kelas'");
        $this->hasRelationalSchema = ($result && mysqli_num_rows($result) > 0);

        if ($this->hasRelationalSchema) {
            $this->activeTahunAjaran = $this->getActiveTahunAjaran();
            if (!$this->activeTahunAjaran) {
                $this->activeTahunAjaran = $this->ensureActiveTahunAjaran();
            }
        }
    }

    private function getActiveTahunAjaran(): ?array
    {
        $stmt = $this->conn->prepare("SELECT id_tahun_ajaran, nama_tahun_ajaran FROM tahun_ajaran WHERE is_active = 1 LIMIT 1");
        if (!$stmt) return null;
        
        $stmt->execute();
        $result = $stmt->get_result();
        $data = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
        $stmt->close();
        return $data;
    }

    private function ensureActiveTahunAjaran(): ?array
    {
        $month = intval(date('m'));
        $year = intval(date('Y'));
        $tahunName = ($month >= 7) ? sprintf('%d/%d', $year, $year + 1) : sprintf('%d/%d', $year - 1, $year);

        $stmt = $this->conn->prepare("INSERT IGNORE INTO tahun_ajaran (nama_tahun_ajaran, is_active) VALUES (?, 1)");
        if (!$stmt) return null;

        $stmt->bind_param('s', $tahunName);
        $stmt->execute();
        $id = $this->conn->insert_id;
        $stmt->close();

        return $id ? ['id_tahun_ajaran' => $id, 'nama_tahun_ajaran' => $tahunName] : null;
    }

    public function loadClassMap(): bool
    {
        $stmt = $this->conn->prepare("SELECT id_kelas, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
        if (!$stmt) {
            $this->logError("Database error: Cannot load class map");
            return false;
        }

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $this->classMap[strtoupper(trim($row['nama_kelas']))] = intval($row['id_kelas']);
        }

        $stmt->close();
        $this->log("Loaded " . count($this->classMap) . " classes into memory");
        return true;
    }

    public function validateFile(string $filePath, int $maxSizeKB = 5120): array
    {
        $errors = [];

        if (!file_exists($filePath)) {
            $errors[] = "File tidak ditemukan: $filePath";
            return $errors;
        }

        $fileSize = filesize($filePath);
        if ($fileSize > ($maxSizeKB * 1024)) {
            $errors[] = "File terlalu besar (max " . $maxSizeKB . "KB, got " . round($fileSize / 1024, 2) . "KB)";
        }

        $mimeType = mime_content_type($filePath);
        $allowedMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];
        if (!in_array($mimeType, $allowedMimes)) {
            $errors[] = "Format file tidak valid (mime: $mimeType)";
        }

        if (($handle = fopen($filePath, 'r')) === false) {
            $errors[] = "Tidak dapat membuka file";
            return $errors;
        }

        $header = fgetcsv($handle, 1000, ',');
        fclose($handle);

        // Remove UTF-8 BOM if present
        if (!empty($header) && isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        $expectedHeaders = ['nis', 'nisn', 'nama_lengkap', 'jk', 'nama_kelas', 'tahun_ajaran', 'status'];
        $actualHeaders = array_map('strtolower', array_map('trim', $header ?? []));

        if ($actualHeaders !== $expectedHeaders) {
            $errors[] = "Format header tidak sesuai. Harapkan: " . implode(', ', $expectedHeaders);
        }

        return $errors;
    }

    public function processFile(string $filePath): bool
    {
        if (!$this->loadClassMap()) {
            $this->logError("Gagal memuat mapping kelas");
            return false;
        }

        if (($handle = fopen($filePath, 'r')) === false) {
            $this->logError("Gagal membuka file: $filePath");
            return false;
        }

        fgetcsv($handle, 1000, ',');
        $lineNumber = 2;

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $this->processRow($data, $lineNumber);
            $lineNumber++;
        }

        fclose($handle);
        $this->log("Import completed. Stats: " . json_encode($this->stats));
        return true;
    }

    private function processRow(array $data, int $lineNumber): void
    {
        $nis = trim($data[0] ?? '');
        $nisn = trim($data[1] ?? '');
        $namaLengkap = trim($data[2] ?? '');
        $jk = strtoupper(substr(trim($data[3] ?? ''), 0, 1));
        $namaKelas = trim($data[4] ?? '');
        $tahunAjaran = trim($data[5] ?? '');
        $status = strtolower(trim($data[6] ?? '')) ?: 'aktif';

        $errors = $this->validateRow($nis, $nisn, $namaLengkap, $jk, $namaKelas, $status, $lineNumber);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->logError("Row $lineNumber: $error");
            }
            $this->stats['failed']++;
            return;
        }

        $idKelas = $this->classMap[strtoupper($namaKelas)] ?? null;
        if ($idKelas === null) {
            $this->logError("Row $lineNumber: Kelas '$namaKelas' tidak ditemukan");
            $this->stats['failed']++;
            return;
        }

        if ($this->hasRelationalSchema) {
            $this->processRowWithSemester($nis, $nisn, $namaLengkap, $jk, $idKelas, $tahunAjaran, $status, $lineNumber);
        } else {
            $this->processRowLegacy($nis, $nisn, $namaLengkap, $jk, $idKelas, $lineNumber);
        }
    }

    private function validateRow(string $nis, string $nisn, string $nama, string $jk, string $namaKelas, string $status, int $lineNumber): array
    {
        $errors = [];

        if (empty($nis)) {
            $errors[] = "NIS kosong";
        } elseif (!preg_match('/^[A-Z0-9]+$/i', $nis)) {
            $errors[] = "NIS hanya boleh alphanumeric";
        }

        if (empty($nama)) {
            $errors[] = "Nama lengkap kosong";
        }

        if ($jk !== 'L' && $jk !== 'P') {
            $errors[] = "Jenis kelamin harus 'L' atau 'P'";
        }

        if (empty($namaKelas)) {
            $errors[] = "Nama kelas kosong";
        }

        if (!in_array($status, ['aktif', 'lulus', 'pindah', 'hapus'])) {
            $errors[] = "Status harus: aktif/lulus/pindah/hapus";
        }

        return $errors;
    }

    private function processRowWithSemester(string $nis, string $nisn, string $nama, string $jk, int $idKelas, string $tahunAjaran, string $status, int $lineNumber): void
    {
        $tahun = $tahunAjaran ?: ($this->activeTahunAjaran['nama_tahun_ajaran'] ?? '');
        $tahunId = $this->activeTahunAjaran['id_tahun_ajaran'] ?? null;

        if (!$tahunId) {
            $this->logError("Row $lineNumber: Tahun ajaran tidak aktif");
            $this->stats['failed']++;
            return;
        }

        $studentId = $this->findOrCreateStudent($nis, $nisn, $nama, $jk, $idKelas, $lineNumber);
        if (!$studentId) {
            $this->stats['failed']++;
            return;
        }

        $this->linkStudentToClass($studentId, $idKelas, $tahunId, $status, $lineNumber);
    }

    private function processRowLegacy(string $nis, string $nisn, string $nama, string $jk, int $idKelas, int $lineNumber): void
    {
        $stmt = $this->conn->prepare("SELECT id_siswa FROM siswa WHERE nis = ?");
        if (!$stmt) {
            $this->logError("Row $lineNumber: Database error");
            $this->stats['failed']++;
            return;
        }

        $stmt->bind_param('s', $nis);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = ($result && $result->num_rows > 0);
        $stmt->close();

        if ($exists) {
            $this->stats['skipped']++;
            return;
        }

        $studentUuid = generate_uuid_v4();
        $stmt = $this->conn->prepare("INSERT INTO siswa (student_uuid, nis, nisn, nama_lengkap, jk, id_kelas, status_siswa, foto) VALUES (?, ?, ?, ?, ?, ?, 'aktif', 'default.png')");
        if (!$stmt) {
            $this->logError("Row $lineNumber: Insert error");
            $this->stats['failed']++;
            return;
        }

        $stmt->bind_param('sssssi', $studentUuid, $nis, $nisn, $nama, $jk, $idKelas);
        if ($stmt->execute()) {
            $this->stats['success']++;
        } else {
            $this->logError("Row $lineNumber: " . $this->conn->error);
            $this->stats['failed']++;
        }
        $stmt->close();
    }

    private function findOrCreateStudent(string $nis, string $nisn, string $nama, string $jk, int $idKelas, int $lineNumber): ?int
    {
        $stmt = $this->conn->prepare("SELECT id_siswa FROM siswa WHERE nis = ? LIMIT 1");
        if (!$stmt) {
            $this->logError("Row $lineNumber: Database error");
            return null;
        }

        $stmt->bind_param('s', $nis);
        $stmt->execute();
        $result = $stmt->get_result();
        $studentId = null;

        if ($result && $result->num_rows > 0) {
            $studentId = intval($result->fetch_assoc()['id_siswa']);
            $this->updateStudent($studentId, $nisn, $nama, $jk, $idKelas, $lineNumber);
            $this->stats['updated']++;
        } else {
            $studentId = $this->insertStudent($nis, $nisn, $nama, $jk, $idKelas, $lineNumber);
            if ($studentId) $this->stats['success']++;
        }

        $stmt->close();
        return $studentId;
    }

    private function insertStudent(string $nis, string $nisn, string $nama, string $jk, int $idKelas, int $lineNumber): ?int
    {
        $studentUuid = generate_uuid_v4();
        $stmt = $this->conn->prepare("INSERT INTO siswa (student_uuid, nis, nisn, nama_lengkap, jk, id_kelas, status_siswa, foto) VALUES (?, ?, ?, ?, ?, ?, 'aktif', 'default.png')");
        if (!$stmt) {
            $this->logError("Row $lineNumber: Insert error");
            return null;
        }

        $stmt->bind_param('sssssi', $studentUuid, $nis, $nisn, $nama, $jk, $idKelas);
        if ($stmt->execute()) {
            $id = $this->conn->insert_id;
            $stmt->close();
            return $id;
        }

        $this->logError("Row $lineNumber: " . $this->conn->error);
        $stmt->close();
        return null;
    }

    private function updateStudent(int $studentId, string $nisn, string $nama, string $jk, int $idKelas, int $lineNumber): void
    {
        $stmt = $this->conn->prepare("UPDATE siswa SET nisn = ?, nama_lengkap = ?, jk = ?, id_kelas = ?, status_siswa = 'aktif' WHERE id_siswa = ?");
        if (!$stmt) {
            $this->logError("Row $lineNumber: Update error");
            return;
        }

        $stmt->bind_param('sssii', $nisn, $nama, $jk, $idKelas, $studentId);
        $stmt->execute();
        $stmt->close();
    }

    private function linkStudentToClass(int $studentId, int $idKelas, int $tahunId, string $status, int $lineNumber): void
    {
        $stmt = $this->conn->prepare("INSERT INTO siswa_kelas (id_siswa, id_kelas, id_tahun_ajaran, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE id_kelas = VALUES(id_kelas), status = VALUES(status), updated_at = CURRENT_TIMESTAMP");
        if (!$stmt) {
            $this->logError("Row $lineNumber: Link error");
            return;
        }

        $stmt->bind_param('iiis', $studentId, $idKelas, $tahunId, $status);
        $stmt->execute();
        $stmt->close();
    }

    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[$timestamp] $message\n", FILE_APPEND);
    }

    private function logError(string $message): void
    {
        $this->errors[] = $message;
        $this->log("ERROR: $message");
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getLogFile(): string
    {
        return $this->logFile;
    }
}
