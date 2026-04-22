<?php
/**
 * SecurityHelper - Utility class untuk keamanan aplikasi
 * Includes: Input validation, output escaping, CSRF protection, password hashing
 */

class SecurityHelper {
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get CSRF token field HTML
     */
    public static function getCSRFField() {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Hash password dengan bcrypt
     */
    public static function hashPassword($password) {
        if (empty($password)) {
            throw new Exception('Password tidak boleh kosong');
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Verify password dengan bcrypt
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Sanitize input string
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map(function($item) {
                return self::sanitizeInput($item);
            }, $input);
        }
        
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        return $input;
    }
    
    /**
     * Escape output untuk HTML
     */
    public static function escapeHTML($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Escape output untuk JavaScript
     */
    public static function escapeJS($text) {
        return json_encode($text, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    }
    
    /**
     * Escape output untuk URL
     */
    public static function escapeURL($text) {
        return urlencode($text);
    }
    
    /**
     * Validate date format
     */
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * Validate integer
     */
    public static function validateInteger($value) {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowedExtensions = ['xlsx', 'xls', 'csv'], $maxSize = 5242880) {
        // Backward compatibility: some legacy code passes tmp_name string directly.
        if (!is_array($file)) {
            return false;
        }

        // Check if file exists
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['success' => false, 'message' => 'File tidak ditemukan'];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'Ukuran file terlalu besar (max: ' . round($maxSize / 1024 / 1024) . 'MB)'];
        }
        
        // Get file extension
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check extension
        if (!in_array($fileExtension, $allowedExtensions)) {
            return ['success' => false, 'message' => 'Tipe file tidak diizinkan'];
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        
        $allowedMimeTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'text/csv',
            'application/csv'
        ];
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            return ['success' => false, 'message' => 'MIME type file tidak valid'];
        }
        
        return ['success' => true, 'message' => 'File valid'];
    }

    /**
     * Validate image upload with strict security checks.
     */
    public static function validateImageUpload(
        array $file,
        array $allowedExtensions = ['jpg', 'jpeg', 'png'],
        int $maxSize = 1048576,
        ?int $maxWidth = null,
        ?int $maxHeight = null,
        array $recommendedSizes = []
    ) {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'message' => 'Data upload file tidak valid'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $map = [
                UPLOAD_ERR_INI_SIZE => 'Ukuran file melebihi batas server',
                UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas form',
                UPLOAD_ERR_PARTIAL => 'Upload file tidak selesai',
                UPLOAD_ERR_NO_FILE => 'Tidak ada file yang dipilih',
            ];
            return ['success' => false, 'message' => $map[$file['error']] ?? 'Gagal upload file'];
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Sumber upload tidak valid'];
        }

        if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'Ukuran file melebihi batas (' . round($maxSize / 1024) . 'KB)'];
        }

        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            return ['success' => false, 'message' => 'Format file tidak didukung. Hanya JPG/JPEG/PNG'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        $allowedMimes = ['image/jpeg', 'image/png'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            return ['success' => false, 'message' => 'MIME type file tidak valid'];
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false || !isset($imageInfo[0], $imageInfo[1])) {
            return ['success' => false, 'message' => 'File bukan gambar yang valid'];
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];

        if ($maxWidth !== null && $width > $maxWidth) {
            return ['success' => false, 'message' => 'Lebar gambar melebihi batas maksimal ' . $maxWidth . 'px'];
        }

        if ($maxHeight !== null && $height > $maxHeight) {
            return ['success' => false, 'message' => 'Tinggi gambar melebihi batas maksimal ' . $maxHeight . 'px'];
        }

        $warnings = [];
        if (!empty($recommendedSizes)) {
            $isRecommended = false;
            foreach ($recommendedSizes as $size) {
                if (!isset($size['w'], $size['h'])) {
                    continue;
                }
                if ($width === (int) $size['w'] && $height === (int) $size['h']) {
                    $isRecommended = true;
                    break;
                }
            }

            if (!$isRecommended) {
                $warnings[] = 'Dimensi gambar ' . $width . 'x' . $height . 'px belum sesuai rekomendasi';
            }
        }

        return [
            'success' => true,
            'message' => 'File valid',
            'mime' => $mimeType,
            'extension' => $extension,
            'width' => $width,
            'height' => $height,
            'warnings' => $warnings,
        ];
    }

    /**
     * Store uploaded image safely with random filename.
     */
    public static function uploadImageSecure(array $file, string $targetDir, array $options = []) {
        $maxSize = (int) ($options['maxSize'] ?? 1048576);
        $maxWidth = isset($options['maxWidth']) ? (int) $options['maxWidth'] : null;
        $maxHeight = isset($options['maxHeight']) ? (int) $options['maxHeight'] : null;
        $recommendedSizes = $options['recommendedSizes'] ?? [];
        $prefix = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($options['prefix'] ?? 'img'));

        $validation = self::validateImageUpload(
            $file,
            ['jpg', 'jpeg', 'png'],
            $maxSize,
            $maxWidth,
            $maxHeight,
            $recommendedSizes
        );

        if (!$validation['success']) {
            return $validation;
        }

        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
            return ['success' => false, 'message' => 'Folder upload tidak dapat dibuat'];
        }

        if (!is_writable($targetDir)) {
            return ['success' => false, 'message' => 'Folder upload tidak bisa ditulis'];
        }

        $filename = '';
        $targetPath = '';
        for ($i = 0; $i < 5; $i++) {
            $filename = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $validation['extension'];
            $targetPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
            if (!file_exists($targetPath)) {
                break;
            }
        }

        if ($filename === '' || $targetPath === '' || file_exists($targetPath)) {
            return ['success' => false, 'message' => 'Gagal membuat nama file unik'];
        }

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'message' => 'Gagal menyimpan file upload'];
        }

        return [
            'success' => true,
            'message' => 'Upload berhasil',
            'filename' => $filename,
            'path' => $targetPath,
            'warnings' => $validation['warnings'] ?? [],
            'width' => $validation['width'] ?? null,
            'height' => $validation['height'] ?? null,
        ];
    }
    
    /**
     * Generate random filename
     */
    public static function generateRandomFilename($originalName) {
        $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = strtolower(ltrim((string) $originalName, '.'));
        }

        $safeExtension = preg_replace('/[^a-z0-9]/', '', $extension);
        $randomName = bin2hex(random_bytes(16));
        if ($safeExtension !== '') {
            $randomName .= '.' . $safeExtension;
        }
        return $randomName;
    }
    
    /**
     * Regenerate session ID (untuk security)
     */
    public static function regenerateSessionId() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);
    }
    
    /**
     * Validate SQL date input
     */
    public static function validateSQLDate($date) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        return self::validateDate($date, 'Y-m-d');
    }
    
    /**
     * Validate integer ID
     */
    public static function validateID($id) {
        return self::validateInteger($id) && $id > 0;
    }
}
?>
