<?php
/**
 * Advanced Security System
 * Provides enhanced security features including role-based access control,
 * data encryption, session management, and API security
 */

class AdvancedSecurity {
    private $pdo;
    private $encryptionKey;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $key = $_ENV['ENCRYPTION_KEY'] ?? '';
        if (strlen($key) < 32) {
            throw new \RuntimeException('ENCRYPTION_KEY must be set in .env (min 32 chars). Run: openssl rand -hex 32');
        }
        $this->encryptionKey = $key;
    }
    
    /**
     * Check if user has specific permission
     */
    public function hasPermission($userId, $permission) {
        try {
            // Get user role
            $stmt = $this->pdo->prepare("
                SELECT role FROM users WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return false;
            }
            
            // Get permissions for the user's role
            $stmt = $this->pdo->prepare("
                SELECT p.permission_name
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                JOIN user_roles ur ON rp.role_id = ur.id
                WHERE ur.role_name = ?
            ");
            $stmt->execute([$user['role']]);
            $rolePermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Check if user has the specific permission
            return in_array($permission, $rolePermissions);
            
        } catch (Exception $e) {
            error_log("Permission check failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Require specific permission for access
     */
    public function requirePermission($permission) {
        // This would typically be called after session validation
        if (!isset($_SESSION['user_id']) || !$this->hasPermission($_SESSION['user_id'], $permission)) {
            http_response_code(403);
            die('Access denied: Insufficient permissions');
        }
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encryptData($data) {
        try {
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
            $encrypted = openssl_encrypt($data, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
            return base64_encode($iv . $encrypted);
        } catch (Exception $e) {
            error_log("Data encryption failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decryptData($encryptedData) {
        try {
            $data = base64_decode($encryptedData);
            $ivLength = openssl_cipher_iv_length('aes-256-cbc');
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);
            return openssl_decrypt($encrypted, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
        } catch (Exception $e) {
            error_log("Data decryption failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Hash sensitive data for storage
     */
    public function hashData($data) {
        return password_hash($data, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify hashed data
     */
    public function verifyHash($data, $hash) {
        return password_verify($data, $hash);
    }
    
    /**
     * Generate secure token
     */
    public function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Validate API token
     */
    public function validateApiToken($token) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.id, u.role, u.status, at.expires_at
                FROM api_tokens at
                JOIN users u ON at.user_id = u.id
                WHERE at.token = ? AND at.expires_at > NOW() AND u.status = 'active'
            ");
            $stmt->execute([$token]);
            $result = $stmt->fetch();
            
            if ($result) {
                // Update last used timestamp
                $stmt = $this->pdo->prepare("
                    UPDATE api_tokens SET last_used_at = NOW() WHERE token = ?
                ");
                $stmt->execute([$token]);
                
                return $result;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("API token validation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create API token for user
     */
    public function createApiToken($userId, $expiresInDays = 30) {
        try {
            $token = $this->generateToken();
            $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiresInDays days"));
            
            $stmt = $this->pdo->prepare("
                INSERT INTO api_tokens (user_id, token, expires_at, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $token, $expiresAt]);
            
            return $token;
            
        } catch (Exception $e) {
            error_log("API token creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Revoke API token
     */
    public function revokeApiToken($token) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM api_tokens WHERE token = ?
            ");
            return $stmt->execute([$token]);
            
        } catch (Exception $e) {
            error_log("API token revocation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Implement rate limiting for API requests
     */
    public function checkRateLimit($identifier, $maxRequests = 60, $timeWindow = 3600) {
        try {
            // Clean up old rate limit entries
            $stmt = $this->pdo->prepare("
                DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$timeWindow]);
            
            // Check current request count
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as request_count
                FROM rate_limits
                WHERE identifier = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$identifier, $timeWindow]);
            $result = $stmt->fetch();
            
            if ($result['request_count'] >= $maxRequests) {
                return false; // Rate limit exceeded
            }
            
            // Log this request
            $stmt = $this->pdo->prepare("
                INSERT INTO rate_limits (identifier, created_at) VALUES (?, NOW())
            ");
            $stmt->execute([$identifier]);
            
            return true; // Within rate limit
            
        } catch (Exception $e) {
            error_log("Rate limiting check failed: " . $e->getMessage());
            return true; // Allow request if rate limiting fails
        }
    }
    
    /**
     * Log security events
     */
    public function logSecurityEvent($eventType, $details, $severity = 'info') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO security_logs (event_type, details, severity, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $eventType,
                json_encode($details),
                $severity,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
        } catch (Exception $e) {
            error_log("Security event logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get security logs
     */
    public function getSecurityLogs($limit = 100, $severity = null) {
        try {
            $whereClause = '';
            $params = [];
            
            if ($severity) {
                $whereClause = 'WHERE severity = ?';
                $params[] = $severity;
            }
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM security_logs
                $whereClause
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $params[] = $limit;
            $stmt->execute($params);
            
            $logs = $stmt->fetchAll();
            
            // Parse JSON details
            foreach ($logs as &$log) {
                $log['details'] = json_decode($log['details'], true);
            }
            
            return $logs;
            
        } catch (Exception $e) {
            error_log("Getting security logs failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check for suspicious activities
     */
    public function checkForSuspiciousActivities() {
        try {
            $alerts = [];
            
            // Check for failed login attempts
            $stmt = $this->pdo->prepare("
                SELECT 
                    ip_address,
                    COUNT(*) as failed_attempts
                FROM security_logs
                WHERE event_type = 'failed_login'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                GROUP BY ip_address
                HAVING COUNT(*) > 5
            ");
            $stmt->execute();
            $failedLogins = $stmt->fetchAll();
            
            foreach ($failedLogins as $login) {
                $alerts[] = [
                    'type' => 'suspicious_login_activity',
                    'severity' => 'medium',
                    'message' => "High number of failed login attempts from IP: {$login['ip_address']} ({$login['failed_attempts']} attempts)"
                ];
            }
            
            // Check for multiple password reset requests
            $stmt = $this->pdo->prepare("
                SELECT 
                    ip_address,
                    COUNT(*) as reset_requests
                FROM security_logs
                WHERE event_type = 'password_reset_request'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                GROUP BY ip_address
                HAVING COUNT(*) > 3
            ");
            $stmt->execute();
            $resetRequests = $stmt->fetchAll();
            
            foreach ($resetRequests as $reset) {
                $alerts[] = [
                    'type' => 'suspicious_reset_activity',
                    'severity' => 'medium',
                    'message' => "Multiple password reset requests from IP: {$reset['ip_address']} ({$reset['reset_requests']} requests)"
                ];
            }
            
            return $alerts;
            
        } catch (Exception $e) {
            error_log("Checking suspicious activities failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Block IP address temporarily
     */
    public function blockIpAddress($ipAddress, $durationMinutes = 60) {
        try {
            $blockedUntil = date('Y-m-d H:i:s', strtotime("+$durationMinutes minutes"));
            
            $stmt = $this->pdo->prepare("
                INSERT INTO blocked_ips (ip_address, blocked_until, created_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE blocked_until = ?
            ");
            $stmt->execute([$ipAddress, $blockedUntil, $blockedUntil]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("IP blocking failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if IP address is blocked
     */
    public function isIpAddressBlocked($ipAddress) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT blocked_until FROM blocked_ips
                WHERE ip_address = ? AND blocked_until > NOW()
            ");
            $stmt->execute([$ipAddress]);
            $result = $stmt->fetch();
            
            if ($result) {
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("IP blocking check failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up expired blocks
     */
    public function cleanupExpiredBlocks() {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM blocked_ips WHERE blocked_until < NOW()
            ");
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Cleaning up expired blocks failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user audit trail
     */
    public function getUserAuditTrail($userId, $limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT sl.*, u.username
                FROM security_logs sl
                JOIN users u ON u.id = sl.user_id
                WHERE sl.user_id = ?
                ORDER BY sl.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Getting user audit trail failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rotate encryption key
     */
    public function rotateEncryptionKey($newKey) {
        try {
            // In a real implementation, you would need to:
            // 1. Decrypt all data with old key
            // 2. Encrypt all data with new key
            // 3. Update the encryption key in configuration
            
            // For now, we'll just update the key
            $this->encryptionKey = $newKey;
            
            $this->logSecurityEvent('encryption_key_rotated', [
                'timestamp' => date('Y-m-d H:i:s')
            ], 'info');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Encryption key rotation failed: " . $e->getMessage());
            return false;
        }
    }
}
?>