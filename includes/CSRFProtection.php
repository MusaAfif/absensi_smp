<?php
/**
 * CSRF Protection Middleware
 * Implements double-submit cookie pattern with token storage
 */

class CSRFProtection {
    private static $token_name = 'csrf_token';
    private static $cookie_name = '__csrf_cookie';
    private static $token_lifetime = 3600; // 1 hour
    
    /**
     * Initialize CSRF protection
     */
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        self::generateToken();
    }
    
    /**
     * Generate or get existing CSRF token
     */
    public static function generateToken() {
        if (!isset($_SESSION[self::$token_name])) {
            $_SESSION[self::$token_name] = bin2hex(random_bytes(32));
            $_SESSION[self::$token_name . '_time'] = time();
        }
        
        // Check token expiration
        if (time() - $_SESSION[self::$token_name . '_time'] > self::$token_lifetime) {
            $_SESSION[self::$token_name] = bin2hex(random_bytes(32));
            $_SESSION[self::$token_name . '_time'] = time();
        }
        
        return $_SESSION[self::$token_name];
    }
    
    /**
     * Get token for HTML forms
     */
    public static function getTokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
    }
    
    /**
     * Get token value
     */
    public static function getToken() {
        return self::generateToken();
    }
    
    /**
     * Verify CSRF token from POST request
     */
    public static function verifyToken($token = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? '';
        }
        
        // Token tidak ada
        if (empty($token)) {
            return false;
        }
        
        // Session token tidak ada
        if (!isset($_SESSION[self::$token_name])) {
            return false;
        }
        
        // Token tidak cocok
        if (!hash_equals($_SESSION[self::$token_name], $token)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Middleware untuk validasi CSRF pada POST request
     */
    public static function middleware() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!self::verifyToken()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'CSRF token tidak valid. Mohon coba lagi.'
                ]);
                exit;
            }
        }
    }
    
    /**
     * Regenerate token after successful validation
     * (optional, untuk extra security)
     */
    public static function regenerateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[self::$token_name] = bin2hex(random_bytes(32));
        $_SESSION[self::$token_name . '_time'] = time();
    }
    
    /**
     * Destroy token (on logout)
     */
    public static function destroy() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION[self::$token_name]);
        unset($_SESSION[self::$token_name . '_time']);
    }
}
?>
