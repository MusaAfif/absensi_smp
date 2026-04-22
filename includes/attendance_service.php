<?php
require_once __DIR__ . '/config.php';

function jsonResponse($status, array $payload = [], int $httpCode = 200): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($httpCode);
    echo json_encode(array_merge(['status' => $status], $payload));
    exit;
}

function logAttendanceError(string $message, array $context = []): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/attendance.log';
    $time = date('Y-m-d H:i:s');
    $entry = sprintf("[%s] %s - %s\n", $time, $message, json_encode($context, JSON_UNESCAPED_UNICODE));
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

function tableExists(mysqli $conn, string $tableName): bool
{
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$tableName'");
    if (!$result) {
        return false;
    }
    return mysqli_num_rows($result) > 0;
}

function columnExists(mysqli $conn, string $tableName, string $columnName): bool
{
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $columnName = mysqli_real_escape_string($conn, $columnName);
    
    $result = mysqli_query($conn, "SHOW COLUMNS FROM $tableName WHERE Field='$columnName'");
    if (!$result) {
        return false;
    }
    return mysqli_num_rows($result) > 0;
}

function getJadwalHariIni(mysqli $conn): array
{
    // Array mapping hari dalam bahasa Indonesia ke English
    $hariMap = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu'
    ];

    $hariInggris = date('l'); // Monday, Tuesday, etc.
    $hariIndonesia = $hariMap[$hariInggris] ?? 'Senin'; // Default ke Senin jika tidak ada

    // Cek apakah kolom range waktu sudah ada
    if (columnExists($conn, 'jadwal_absensi', 'jam_masuk_mulai')) {
        // Gunakan range waktu baru
        $stmt = $conn->prepare('SELECT jam_masuk_mulai, jam_masuk_selesai, jam_pulang_mulai, jam_pulang_selesai, batas_terlambat FROM jadwal_absensi WHERE hari = ? LIMIT 1');
        if (!$stmt) {
            // Fallback ke default jika error
            return [
                'jam_masuk_mulai' => '06:00',
                'jam_masuk_tepat' => '06:00',
                'jam_masuk_selesai' => '08:00',
                'jam_pulang_mulai' => ($hariIndonesia == 'Jumat' ? '11:00' : '12:00'),
                'jam_pulang_selesai' => ($hariIndonesia == 'Jumat' ? '12:00' : '15:00'),
                'batas_terlambat' => 15,
                'hari' => $hariIndonesia
            ];
        }
        
        $stmt->bind_param('s', $hariIndonesia);
        $stmt->execute();
        $result = $stmt->get_result();
        $jadwal = $result->fetch_assoc();
        $stmt->close();

        if ($jadwal) {
            return [
                'jam_masuk_mulai' => $jadwal['jam_masuk_mulai'],
                'jam_masuk_tepat' => $jadwal['jam_masuk_mulai'],
                'jam_masuk_selesai' => $jadwal['jam_masuk_selesai'],
                'jam_pulang_mulai' => $jadwal['jam_pulang_mulai'],
                'jam_pulang_selesai' => $jadwal['jam_pulang_selesai'],
                'batas_terlambat' => (int)($jadwal['batas_terlambat'] ?? 15),
                'hari' => $hariIndonesia
            ];
        }
    } else {
        // Fallback ke sistem lama jika kolom belum ada
        $stmt = $conn->prepare('SELECT jam_masuk, jam_pulang FROM jadwal_absensi WHERE hari = ? LIMIT 1');
        if (!$stmt) {
            return [
                'jam_masuk_mulai' => '06:00',
                'jam_masuk_tepat' => '07:00',
                'jam_masuk_selesai' => '08:00',
                'jam_pulang_mulai' => ($hariIndonesia == 'Jumat' ? '11:00' : '12:00'),
                'jam_pulang_selesai' => ($hariIndonesia == 'Jumat' ? '12:00' : '15:00'),
                'batas_terlambat' => 15,
                'hari' => $hariIndonesia
            ];
        }
        
        $stmt->bind_param('s', $hariIndonesia);
        $stmt->execute();
        $result = $stmt->get_result();
        $jadwal = $result->fetch_assoc();
        $stmt->close();

        if ($jadwal) {
            // Convert old format to new format
            return [
                'jam_masuk_mulai' => '06:00',
                'jam_masuk_tepat' => $jadwal['jam_masuk'],
                'jam_masuk_selesai' => date('H:i', strtotime($jadwal['jam_masuk']) + 3600), // +1 jam
                'jam_pulang_mulai' => $jadwal['jam_pulang'],
                'jam_pulang_selesai' => date('H:i', strtotime($jadwal['jam_pulang']) + 10800), // +3 jam
                'batas_terlambat' => 15,
                'hari' => $hariIndonesia
            ];
        }
    }

    // Fallback default jika tidak ada jadwal
    return [
        'jam_masuk_mulai' => '06:00',
        'jam_masuk_tepat' => '07:00',
        'jam_masuk_selesai' => '08:00',
        'jam_pulang_mulai' => ($hariIndonesia == 'Jumat' ? '11:00' : '12:00'),
        'jam_pulang_selesai' => ($hariIndonesia == 'Jumat' ? '12:00' : '15:00'),
        'batas_terlambat' => 15,
        'hari' => $hariIndonesia
    ];
}

function getActiveTahunAjaran(mysqli $conn): ?array
{
    if (!tableExists($conn, 'tahun_ajaran')) {
        return null;
    }

    $result = mysqli_query($conn, "SELECT id_tahun_ajaran, nama_tahun_ajaran FROM tahun_ajaran WHERE is_active = 1 LIMIT 1");
    if (!$result || mysqli_num_rows($result) === 0) {
        return null;
    }

    return mysqli_fetch_assoc($result);
}

function validateDeviceKey(mysqli $conn, string $apiKey): bool
{
    if (trim($apiKey) === '') {
        return false;
    }

    // Create devices table if not exists
    $createTable = "CREATE TABLE IF NOT EXISTS devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_name VARCHAR(100) NOT NULL,
        api_key VARCHAR(255) NOT NULL UNIQUE,
        ip_address VARCHAR(45),
        user_agent TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    mysqli_query($conn, $createTable);

    $stmt = $conn->prepare('SELECT id FROM devices WHERE api_key = ? AND is_active = TRUE LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $apiKey);
    $stmt->execute();
    $stmt->store_result();
    $valid = $stmt->num_rows > 0;
    $stmt->close();

    // Update last_used if valid
    if ($valid) {
        $stmt = $conn->prepare('UPDATE devices SET last_used = CURRENT_TIMESTAMP, ip_address = ?, user_agent = ? WHERE api_key = ?');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt->bind_param('sss', $ip, $ua, $apiKey);
        $stmt->execute();
        $stmt->close();
    }

    return $valid;
}

function checkRateLimit(mysqli $conn, string $identifier, int $maxRequests = 10, int $timeWindow = 60): bool
{
    mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS rate_limit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL,
            request_count INT NOT NULL DEFAULT 1,
            window_start INT NOT NULL,
            INDEX idx_identifier_window (identifier, window_start)
        ) ENGINE=InnoDB"
    );
    mysqli_query($conn, "ALTER TABLE rate_limit MODIFY window_start INT NOT NULL");

    $currentTime = time();
    $windowStart = $currentTime - $timeWindow;

    // Clean old entries
    $cleanupStmt = $conn->prepare('DELETE FROM rate_limit WHERE window_start < ?');
    if ($cleanupStmt) {
        $cleanupStmt->bind_param('i', $windowStart);
        $cleanupStmt->execute();
        $cleanupStmt->close();
    }

    // Check current count
    $stmt = $conn->prepare('SELECT request_count FROM rate_limit WHERE identifier = ? AND window_start >= ?');
    $stmt->bind_param('si', $identifier, $windowStart);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row && $row['request_count'] >= $maxRequests) {
        return false; // Rate limit exceeded
    }

    // Update or insert
    if ($row) {
        $updateStmt = $conn->prepare('UPDATE rate_limit SET request_count = request_count + 1 WHERE identifier = ? AND window_start >= ?');
        if (!$updateStmt) {
            return false;
        }
        $updateStmt->bind_param('si', $identifier, $windowStart);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        $stmt = $conn->prepare('INSERT INTO rate_limit (identifier, request_count, window_start) VALUES (?, 1, ?)');
        $stmt->bind_param('si', $identifier, $currentTime);
        $stmt->execute();
        $stmt->close();
    }

    return true;
}

function findStudentByIdentifier(mysqli $conn, string $identifier)
{
    $identifier = trim($identifier);
    if ($identifier === '') {
        return null;
    }

    $matchColumns = ['student_uuid', 'nisn'];
    if (columnExists($conn, 'siswa', 'rfid_uid')) {
        $matchColumns[] = 'rfid_uid';
    }
    if (columnExists($conn, 'siswa', 'barcode_id')) {
        $matchColumns[] = 'barcode_id';
    }

    $whereParts = array_map(function ($col) {
        return "s.$col = ?";
    }, $matchColumns);

    $sql = 'SELECT s.id_siswa, s.student_uuid, s.nama_lengkap, s.foto, s.nisn, s.nis, s.id_kelas, s.status_siswa, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.id_kelas = k.id_kelas WHERE ' . implode(' OR ', $whereParts) . ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logAttendanceError('findStudentByIdentifier prepare failed', ['sql' => $sql, 'error' => $conn->error]);
        return null;
    }

    $params = array_fill(0, count($matchColumns), $identifier);
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $student;
}

function validateStudentAttendanceEligibility(mysqli $conn, array $student): array
{
    $studentStatus = trim((string) ($student['status_siswa'] ?? 'aktif'));
    if ($studentStatus !== '' && $studentStatus !== 'aktif') {
        return [
            'allowed' => false,
            'message' => 'Siswa tidak aktif untuk absensi.',
        ];
    }

    $activeYear = getActiveTahunAjaran($conn);
    if (!$activeYear || !tableExists($conn, 'siswa_kelas')) {
        return [
            'allowed' => true,
        ];
    }

    $stmt = $conn->prepare('SELECT status FROM siswa_kelas WHERE id_siswa = ? AND id_tahun_ajaran = ? LIMIT 1');
    if (!$stmt) {
        return [
            'allowed' => true,
        ];
    }

    $studentId = (int) $student['id_siswa'];
    $activeYearId = (int) $activeYear['id_tahun_ajaran'];
    $stmt->bind_param('ii', $studentId, $activeYearId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return [
            'allowed' => false,
            'message' => 'Siswa belum terdaftar pada tahun ajaran aktif.',
        ];
    }

    if (($row['status'] ?? 'aktif') !== 'aktif') {
        return [
            'allowed' => false,
            'message' => 'Status siswa pada tahun ajaran aktif tidak mengizinkan absensi.',
        ];
    }

    return [
        'allowed' => true,
    ];
}

function getAttendanceRecords(mysqli $conn, int $studentId, string $date): array
{
    $timeColumn = columnExists($conn, 'absensi', 'jam') ? 'jam' : (columnExists($conn, 'absensi', 'waktu') ? 'waktu' : null);
    $selectFields = 'id_absen, id_siswa, tanggal, status';
    if ($timeColumn) {
        $selectFields .= ', ' . $timeColumn . ' AS scan_time';
    }

    $stmt = $conn->prepare("SELECT $selectFields FROM absensi WHERE id_siswa = ? AND tanggal = ? ORDER BY id_absen ASC");
    if (!$stmt) {
        logAttendanceError('getAttendanceRecords prepare failed', ['error' => $conn->error]);
        return [];
    }
    $stmt->bind_param('is', $studentId, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function determineAttendanceAction(array $records, string $currentTime, mysqli $conn): array
{
    $scanCount = count($records);
    $currentTimestamp = strtotime($currentTime);
    $minInterval = 2; // minimum 2 detik antar scan

    // Check anti-spam: minimum interval between scans
    if ($scanCount > 0) {
        $lastScan = end($records);
        $timeValue = $lastScan['scan_time'] ?? null;
        if ($timeValue) {
            $lastScanTimestamp = strtotime($timeValue);
            if (($currentTimestamp - $lastScanTimestamp) < $minInterval) {
                return [
                    'allowed' => false,
                    'phase' => 'denied',
                    'reason' => 'Scan terlalu cepat. Harap tunggu ' . $minInterval . ' detik sebelum scan ulang.',
                    'status' => 'Ditolak',
                    'status_code' => 4,
                ];
            }
        }
    }

    // Ambil jadwal hari ini dari database dengan range waktu
    $jadwal = getJadwalHariIni($conn);
    $masukMulai = strtotime($jadwal['jam_masuk_mulai']);
    $masukSelesai = strtotime($jadwal['jam_masuk_selesai']);
    $batasTerlambat = strtotime('+' . ((int)($jadwal['batas_terlambat'] ?? 15)) . ' minutes', $masukSelesai);
    $pulangMulai = strtotime($jadwal['jam_pulang_mulai']);
    $pulangSelesai = strtotime($jadwal['jam_pulang_selesai']);

    // FIRST SCAN: MASUK
    if ($scanCount === 0) {
        // Logic absen masuk berdasarkan range waktu
        if ($currentTimestamp < $masukMulai) {
            return [
                'allowed' => false,
                'phase' => 'denied',
                'reason' => 'Absensi masuk belum dibuka. Buka pukul ' . date('H:i', $masukMulai) . '.',
                'status' => 'Belum Dibuka',
                'status_code' => 4,
            ];
        } elseif ($currentTimestamp >= $masukMulai && $currentTimestamp <= $masukSelesai) {
            return [
                'allowed' => true,
                'phase' => 'Masuk',
                'status' => 'Hadir',
                'status_code' => 1,
                'reason' => null,
            ];
        } elseif ($currentTimestamp > $masukSelesai && $currentTimestamp <= $batasTerlambat) {
            return [
                'allowed' => true,
                'phase' => 'Masuk',
                'status' => 'Terlambat',
                'status_code' => 2,
                'reason' => null,
            ];
        } else {
            return [
                'allowed' => false,
                'phase' => 'denied',
                'reason' => 'Waktu absensi masuk sudah ditutup.',
                'status' => 'Ditutup',
                'status_code' => 4,
            ];
        }
    }

    // SECOND SCAN: PULANG
    if ($scanCount === 1) {
        // Logic absen pulang berdasarkan range waktu
        if ($currentTimestamp < $pulangMulai) {
            return [
                'allowed' => false,
                'phase' => 'denied',
                'reason' => 'Belum waktunya absensi pulang. Tunggu sampai jam ' . date('H:i', $pulangMulai) . '.',
                'status' => 'Belum Waktunya',
                'status_code' => 4,
            ];
        } elseif ($currentTimestamp >= $pulangMulai && $currentTimestamp <= $pulangSelesai) {
            return [
                'allowed' => true,
                'phase' => 'Pulang',
                'status' => 'Pulang',
                'status_code' => 3,
                'reason' => null,
            ];
        } else {
            return [
                'allowed' => false,
                'phase' => 'denied',
                'reason' => 'Waktu absensi pulang sudah ditutup.',
                'status' => 'Ditutup',
                'status_code' => 4,
            ];
        }
    }

    // THIRD OR MORE SCANS: DENIED
    return [
        'allowed' => false,
        'phase' => 'denied',
        'reason' => 'Absensi sudah lengkap untuk hari ini (Masuk & Pulang).',
        'status' => 'Ditolak',
        'status_code' => 4,
    ];
}

function insertAttendanceRecord(mysqli $conn, int $studentId, string $date, string $time, string $attendanceStatus, int $statusCode, string $scanSource = 'manual'): bool
{
    // Start transaction to prevent race conditions
    mysqli_begin_transaction($conn);

    try {
        // Note: Unique constraint uk_absensi_user_date_status will prevent duplicates
        // No need for manual count check

        $columns = ['id_siswa', 'tanggal'];
        $types = 'is';
        $params = [$studentId, $date];

        if (columnExists($conn, 'absensi', 'status')) {
            $columns[] = 'status';
            $types .= 's';
            $params[] = $attendanceStatus;
        }

        if (columnExists($conn, 'absensi', 'jam')) {
            $columns[] = 'jam';
            $types .= 's';
            $params[] = $time;
        } elseif (columnExists($conn, 'absensi', 'waktu')) {
            $columns[] = 'waktu';
            $types .= 's';
            $params[] = $time;
        }

        if (columnExists($conn, 'absensi', 'id_status')) {
            $columns[] = 'id_status';
            $types .= 'i';
            $params[] = $statusCode;
        }

        if (columnExists($conn, 'absensi', 'scan_source')) {
            $columns[] = 'scan_source';
            $types .= 's';
            $params[] = $scanSource;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO absensi (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            logAttendanceError('insertAttendanceRecord prepare failed', ['sql' => $sql, 'error' => $conn->error]);
            mysqli_rollback($conn);
            return false;
        }
        $stmt->bind_param($types, ...$params);
        $success = $stmt->execute();
        $stmt->close();

        if (!$success) {
            logAttendanceError('insertAttendanceRecord execute failed', ['error' => $conn->error, 'query' => $sql]);
            mysqli_rollback($conn);
            return false;
        }

        mysqli_commit($conn);
        return true;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        logAttendanceError('Transaction failed in insertAttendanceRecord', ['error' => $e->getMessage()]);
        return false;
    }
}

function processAttendanceScanRequest(mysqli $conn, string $identifier, string $source = 'manual', string $deviceKey = ''): array
{
    require_once __DIR__ . '/attendance_logic.php';

    $identifier = trim($identifier);
    if ($identifier === '') {
        return [
            'status' => 'error',
            'code' => 400,
            'message' => 'Input scan kosong. Coba lagi.',
        ];
    }

    // Rate limiting - 10 requests per minute per identifier
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitKey = $clientIP . '_' . md5($identifier);

    if (!checkRateLimit($conn, $rateLimitKey, 10, 60)) {
        logAttendanceError('Rate limit exceeded', ['identifier' => $identifier, 'ip' => $clientIP]);
        return [
            'status' => 'error',
            'code' => 429,
            'message' => 'Terlalu banyak permintaan. Tunggu sebentar.',
        ];
    }

    if ($deviceKey !== '' && !validateDeviceKey($conn, $deviceKey)) {
        logAttendanceError('Invalid device key', ['device_key' => substr($deviceKey, 0, 10) . '...']);
        return [
            'status' => 'error',
            'code' => 401,
            'message' => 'Device tidak terautentikasi. Periksa API key.',
        ];
    }

    // Cari siswa berdasarkan identifier permanen kartu / fallback identifier lama
    $student = findStudentByIdentifier($conn, $identifier);
    if (!$student) {
        return [
            'status' => 'error',
            'code' => 404,
            'message' => 'Data siswa tidak ditemukan untuk kode ini.',
        ];
    }

    $eligibility = validateStudentAttendanceEligibility($conn, $student);
    if (!$eligibility['allowed']) {
        return [
            'status' => 'error',
            'code' => 422,
            'message' => $eligibility['message'],
            'student' => [
                'nama' => $student['nama_lengkap'],
                'kelas' => $student['nama_kelas'] ?? '',
                'foto' => $student['foto'] ?? 'default.png',
            ],
        ];
    }

    // Gunakan AttendanceLogic untuk proses absensi
    $attendanceLogic = new AttendanceLogic($conn);
    $jadwal = $attendanceLogic->getJadwalHariIni();
    $time = date('H:i:s');

    $result = $attendanceLogic->saveAttendance($student['id_siswa'], $time, $jadwal);

    if (!$result['success']) {
        return [
            'status' => 'error',
            'code' => 422,
            'message' => $result['message'],
            'student' => [
                'nama' => $student['nama_lengkap'],
                'kelas' => $student['nama_kelas'] ?? '',
                'foto' => $student['foto'] ?? 'default.png',
            ],
        ];
    }

    return [
        'status' => 'success',
        'code' => 200,
        'data' => [
            'nama' => $student['nama_lengkap'],
            'kelas' => $student['nama_kelas'] ?? 'Tanpa kelas',
            'jam' => substr($time, 0, 5),
            'status_absen' => $result['message'],
            'status' => $result['status'],
            'keterlambatan' => $result['keterlambatan_menit'],
            'foto' => $student['foto'] ?? 'default.png',
            'message' => $result['message'],
        ],
    ];
}

function processAttendanceMasuk(mysqli $conn, string $kode, string $tipe = 'rfid'): array
{
    $kode = trim($kode);
    if ($kode === '') {
        return [
            'status' => 'error',
            'code' => 400,
            'message' => 'Input scan kosong. Coba lagi.',
        ];
    }

    // Rate limiting - 10 requests per minute per identifier
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitKey = $clientIP . '_' . md5($kode);

    if (!checkRateLimit($conn, $rateLimitKey, 10, 60)) {
        logAttendanceError('Rate limit exceeded', ['kode' => $kode, 'ip' => $clientIP]);
        return [
            'status' => 'error',
            'code' => 429,
            'message' => 'Terlalu banyak permintaan. Tunggu sebentar.',
        ];
    }

    $student = findStudentByIdentifier($conn, $kode);
    if (!$student) {
        return [
            'status' => 'error',
            'code' => 404,
            'message' => 'Data siswa tidak ditemukan untuk kode ini.',
        ];
    }

    $eligibility = validateStudentAttendanceEligibility($conn, $student);
    if (!$eligibility['allowed']) {
        return [
            'status' => 'error',
            'code' => 422,
            'message' => $eligibility['message'],
            'student' => [
                'nama' => $student['nama_lengkap'],
                'kelas' => $student['nama_kelas'] ?? '',
                'foto' => $student['foto'] ?? 'default.png',
            ],
        ];
    }

    $date = date('Y-m-d');
    $time = date('H:i:s');
    $status_masuk = 'masuk';

    // Check if already masuk today
    $stmt = $conn->prepare('SELECT id_absen FROM absensi WHERE id_siswa = ? AND tanggal = ? AND status = ? LIMIT 1');
    $stmt->bind_param('iss', $student['id_siswa'], $date, $status_masuk);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        return [
            'status' => 'error',
            'code' => 422,
            'message' => 'Sudah absen masuk hari ini.',
            'student' => [
                'nama' => $student['nama_lengkap'],
                'kelas' => $student['nama_kelas'] ?? '',
                'foto' => $student['foto'] ?? 'default.png',
            ],
        ];
    }

    // Insert masuk record
    $saved = insertAttendanceRecord(
        $conn,
        (int)$student['id_siswa'],
        $date,
        $time,
        'masuk',
        1, // status code for masuk
        'web'
    );

    if (!$saved) {
        return [
            'status' => 'error',
            'code' => 500,
            'message' => 'Gagal menyimpan data absensi masuk. Hubungi administrator.',
        ];
    }

    return [
        'status' => 'success',
        'code' => 200,
        'data' => [
            'nama' => $student['nama_lengkap'],
            'kelas' => $student['nama_kelas'] ?? 'Tanpa kelas',
            'jam' => substr($time, 0, 5),
            'status_absen' => 'masuk',
            'foto' => $student['foto'] ?? 'default.png',
            'message' => 'Absen masuk berhasil.',
        ],
    ];
}

function processAttendancePulang(mysqli $conn, string $kode, string $tipe = 'rfid'): array
{
    $kode = trim($kode);
    if ($kode === '') {
        return [
            'status' => 'error',
            'code' => 400,
            'message' => 'Input scan kosong. Coba lagi.',
        ];
    }

    // Rate limiting - 10 requests per minute per identifier
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitKey = $clientIP . '_' . md5($kode);

    if (!checkRateLimit($conn, $rateLimitKey, 10, 60)) {
        logAttendanceError('Rate limit exceeded', ['kode' => $kode, 'ip' => $clientIP]);
        return [
            'status' => 'error',
            'code' => 429,
            'message' => 'Terlalu banyak permintaan. Tunggu sebentar.',
        ];
    }

    $student = findStudentByIdentifier($conn, $kode);
    if (!$student) {
        return [
            'status' => 'error',
            'code' => 404,
            'message' => 'Data siswa tidak ditemukan untuk kode ini.',
        ];
    }

    $eligibility = validateStudentAttendanceEligibility($conn, $student);
    if (!$eligibility['allowed']) {
        return [
            'status' => 'error',
            'code' => 422,
            'message' => $eligibility['message'],
            'student' => [
                'nama' => $student['nama_lengkap'],
                'kelas' => $student['nama_kelas'] ?? '',
                'foto' => $student['foto'] ?? 'default.png',
            ],
        ];
    }

    $date = date('Y-m-d');
    $time = date('H:i:s');
    $status_masuk = 'masuk';

    // Check if sudah masuk today
    $stmt = $conn->prepare('SELECT id_absen FROM absensi WHERE id_siswa = ? AND tanggal = ? AND status = ? LIMIT 1');
    $stmt->bind_param('iss', $student['id_siswa'], $date, $status_masuk);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        return [
            'status' => 'error',
            'code' => 422,
            'message' => 'Belum absen masuk hari ini.',
            'student' => [
                'nama' => $student['nama_lengkap'],
                'kelas' => $student['nama_kelas'] ?? '',
                'foto' => $student['foto'] ?? 'default.png',
            ],
        ];
    }

    // Check if sudah pulang today
    $status_pulang = 'pulang';
    $stmt = $conn->prepare('SELECT id_absen FROM absensi WHERE id_siswa = ? AND tanggal = ? AND status = ? LIMIT 1');
    $stmt->bind_param('iss', $student['id_siswa'], $date, $status_pulang);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        return [
            'status' => 'error',
            'code' => 422,
            'message' => 'Sudah absen pulang hari ini.',
            'student' => [
                'nama' => $student['nama_lengkap'],
                'kelas' => $student['nama_kelas'] ?? '',
                'foto' => $student['foto'] ?? 'default.png',
            ],
        ];
    }

    // Insert pulang record
    $saved = insertAttendanceRecord(
        $conn,
        (int)$student['id_siswa'],
        $date,
        $time,
        'pulang',
        3, // status code for pulang
        'web'
    );

    if (!$saved) {
        return [
            'status' => 'error',
            'code' => 500,
            'message' => 'Gagal menyimpan data absensi pulang. Hubungi administrator.',
        ];
    }

    return [
        'status' => 'success',
        'code' => 200,
        'data' => [
            'nama' => $student['nama_lengkap'],
            'kelas' => $student['nama_kelas'] ?? 'Tanpa kelas',
            'jam' => substr($time, 0, 5),
            'status_absen' => 'pulang',
            'foto' => $student['foto'] ?? 'default.png',
            'message' => 'Absen pulang berhasil.',
        ],
    ];
}
