CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token_hash (token_hash),
    KEY idx_user_id (user_id),
    CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
