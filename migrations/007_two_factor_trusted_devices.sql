-- Migration 007: user_trusted_devices table
-- Required by TwoFactorAuth.php for "remember this device" 2FA bypass

CREATE TABLE IF NOT EXISTS user_trusted_devices (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED     NOT NULL,
    device_name     VARCHAR(255)     NOT NULL DEFAULT 'Unknown Device',
    device_fingerprint VARCHAR(64)   NOT NULL,
    user_agent      TEXT             NOT NULL,
    ip_address      VARCHAR(45)      NOT NULL,
    is_active       TINYINT(1)       NOT NULL DEFAULT 1,
    last_used       DATETIME             NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME             NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_fingerprint (user_id, device_fingerprint),
    INDEX idx_user_active (user_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
