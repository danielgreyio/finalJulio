<?php
/**
 * Security Helper Class for VentDepot
 * Handles CSRF protection, input validation, sanitization, and other security features
 */
class Security {
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get CSRF token input field HTML
     */
    public static function getCSRFInput() {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Comprehensive input validation
     */
    public static function validateInput($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // Required field check
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = ucfirst($field) . ' is required.';
                continue;
            }
            
            // Skip validation if field is empty and not required
            if (empty($value) && (!isset($rule['required']) || !$rule['required'])) {
                continue;
            }
            
            // Type validation
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = 'Please enter a valid email address.';
                        }
                        break;
                        
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $errors[$field] = ucfirst($field) . ' must be a number.';
                        }
                        break;
                        
                    case 'integer':
                        if (!filter_var($value, FILTER_VALIDATE_INT)) {
                            $errors[$field] = ucfirst($field) . ' must be an integer.';
                        }
                        break;
                        
                    case 'float':
                        if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                            $errors[$field] = ucfirst($field) . ' must be a decimal number.';
                        }
                        break;
                        
                    case 'url':
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            $errors[$field] = 'Please enter a valid URL.';
                        }
                        break;
                        
                    case 'phone':
                        if (!preg_match('/^[\+]?[1-9][\d]{0,15}$/', $value)) {
                            $errors[$field] = 'Please enter a valid phone number.';
                        }
                        break;
                        
                    case 'alphanumeric':
                        if (!ctype_alnum($value)) {
                            $errors[$field] = ucfirst($field) . ' must contain only letters and numbers.';
                        }
                        break;
                }
            }
            
            // Length validation
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[$field] = ucfirst($field) . ' must be at least ' . $rule['min_length'] . ' characters long.';
            }
            
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[$field] = ucfirst($field) . ' must not exceed ' . $rule['max_length'] . ' characters.';
            }
            
            // Value range validation
            if (isset($rule['min_value']) && $value < $rule['min_value']) {
                $errors[$field] = ucfirst($field) . ' must be at least ' . $rule['min_value'] . '.';
            }
            
            if (isset($rule['max_value']) && $value > $rule['max_value']) {
                $errors[$field] = ucfirst($field) . ' must not exceed ' . $rule['max_value'] . '.';
            }
            
            // Custom pattern validation
            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $errors[$field] = isset($rule['pattern_message']) 
                    ? $rule['pattern_message'] 
                    : ucfirst($field) . ' format is invalid.';
            }
            
            // Array validation
            if (isset($rule['in_array']) && !in_array($value, $rule['in_array'])) {
                $errors[$field] = 'Please select a valid ' . $field . '.';
            }
        }
        
        return $errors;
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data, $type = 'string') {
        switch ($type) {
            case 'string':
                return trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
                
            case 'email':
                return filter_var(trim($data), FILTER_SANITIZE_EMAIL);
                
            case 'url':
                return filter_var(trim($data), FILTER_SANITIZE_URL);
                
            case 'int':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
                
            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
            case 'html':
                // Allow safe HTML tags
                return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
                
            case 'sql':
                // For SQL queries - use prepared statements instead
                return trim($data);
                
            default:
                return trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
        }
    }
    
    /**
     * Sanitize array of data
     */
    public static function sanitizeArray($data, $types = []) {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $type = $types[$key] ?? 'string';
            
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value, $types);
            } else {
                $sanitized[$key] = self::sanitizeInput($value, $type);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Check if user has permission
     */
    public static function hasPermission($requiredRole) {
        if (!isset($_SESSION['user_role'])) {
            return false;
        }
        
        $roleHierarchy = [
            'customer' => 1,
            'merchant' => 2,
            'admin' => 3
        ];
        
        $userRole = $_SESSION['user_role'];
        
        return isset($roleHierarchy[$userRole]) && 
               isset($roleHierarchy[$requiredRole]) && 
               $roleHierarchy[$userRole] >= $roleHierarchy[$requiredRole];
    }
    
    /**
     * Rate limiting check
     */
    public static function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 3600) {
        $key = $action . '_' . self::getClientIP();
        
        if (!isset($_SESSION['rate_limits'])) {
            $_SESSION['rate_limits'] = [];
        }
        
        $now = time();
        
        // Clean old entries
        foreach ($_SESSION['rate_limits'] as $limitKey => $data) {
            if ($data['reset_time'] < $now) {
                unset($_SESSION['rate_limits'][$limitKey]);
            }
        }
        
        if (!isset($_SESSION['rate_limits'][$key])) {
            $_SESSION['rate_limits'][$key] = [
                'attempts' => 1,
                'reset_time' => $now + $timeWindow
            ];
            return true;
        }
        
        if ($_SESSION['rate_limits'][$key]['attempts'] >= $maxAttempts) {
            return false;
        }
        
        $_SESSION['rate_limits'][$key]['attempts']++;
        return true;
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Log security events
     */
    public static function logSecurityEvent($event, $details = [], $severity = 'info') {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => self::getClientIP(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'event' => $event,
            'details' => $details,
            'severity' => $severity,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        $logFile = '../logs/security_' . date('Y-m-d') . '.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Generate secure password hash
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random token
     */
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Validate a redirect URL against an exact whitelist.
     * Returns the URL if allowed, or 'index.php' as the safe fallback.
     */
    public static function validateRedirect(string $url, array $whitelist = []): string
    {
        $default = 'index.php';

        if ($url === '' || $url === 'null') {
            return $default;
        }

        // Reject null bytes
        if (strpos($url, "\0") !== false) {
            return $default;
        }

        // Reject URL-encoded traversal sequences
        $decoded = urldecode($url);
        if (strpos($decoded, '..') !== false) {
            return $default;
        }

        // Reject protocol-relative and absolute URLs
        if (preg_match('#^(https?:)?//#i', $url)) {
            return $default;
        }

        // Use default whitelist if none provided
        if (empty($whitelist)) {
            $whitelist = ['index.php', 'merchant/dashboard.php', 'admin/dashboard.php', 'profile.php', 'cart.php'];
        }

        return in_array($url, $whitelist, true) ? $url : $default;
    }

    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880) { // 5MB default
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = 'File is too large.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = 'File upload was interrupted.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errors[] = 'No file was uploaded.';
                    break;
                default:
                    $errors[] = 'File upload error.';
            }
            return $errors;
        }
        
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size.';
        }
        
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                $errors[] = 'File type not allowed.';
            }
        }
        
        return $errors;
    }
}
?>