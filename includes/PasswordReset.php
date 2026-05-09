<?php

class PasswordReset {

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function storeToken(\PDO $pdo, int $userId, string $token): void
    {
        $hash      = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 30 * 60);

        $pdo->prepare("
            INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, used_at)
            VALUES (?, ?, ?, NULL)
        ")->execute([$userId, $hash, $expiresAt]);
    }

    /**
     * Returns the matching row (at minimum user_id) or false if invalid/expired/used.
     */
    public static function validateToken(\PDO $pdo, string $token): array|false
    {
        $hash = hash('sha256', $token);
        $now  = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            SELECT id, user_id FROM password_reset_tokens
            WHERE token_hash = ?
              AND expires_at > ?
              AND used_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$hash, $now]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: false;
    }

    public static function consumeToken(\PDO $pdo, string $token): void
    {
        $hash = hash('sha256', $token);
        $now  = date('Y-m-d H:i:s');

        $pdo->prepare("
            UPDATE password_reset_tokens SET used_at = ? WHERE token_hash = ?
        ")->execute([$now, $hash]);
    }
}
