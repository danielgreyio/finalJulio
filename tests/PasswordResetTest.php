<?php

namespace VentDepot\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for PasswordReset — the helper class that encapsulates
 * forgot-password.php and reset-password.php logic so it can be
 * unit-tested without HTTP context.
 */
class PasswordResetTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Mirror the real password_reset_tokens table schema
        $this->pdo->exec("
            CREATE TABLE users (
                id       INTEGER PRIMARY KEY,
                email    TEXT NOT NULL,
                name     TEXT NOT NULL,
                password TEXT NOT NULL
            )
        ");
        $this->pdo->exec("
            CREATE TABLE password_reset_tokens (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at    DATETIME NULL
            )
        ");

        $this->pdo->exec("INSERT INTO users (id, email, name, password) VALUES (1, 'test@example.com', 'Test User', 'hashed')");
    }

    // ── Token Generation ──────────────────────────────────────────────────────

    public function testGenerateToken_isCryptographicallyRandom(): void
    {
        $t1 = \PasswordReset::generateToken();
        $t2 = \PasswordReset::generateToken();
        $this->assertNotSame($t1, $t2);
    }

    public function testGenerateToken_isSixtyFourHexChars(): void
    {
        $token = \PasswordReset::generateToken();
        // bin2hex(random_bytes(32)) → 64 hex chars
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    // ── Token Storage ─────────────────────────────────────────────────────────

    public function testStoreToken_savesHashedToken(): void
    {
        $token = \PasswordReset::generateToken();
        \PasswordReset::storeToken($this->pdo, 1, $token);

        $row = $this->pdo->query("SELECT token_hash FROM password_reset_tokens LIMIT 1")->fetch();
        $this->assertNotNull($row);
        // Raw token must not be stored; only its SHA-256 hash
        $this->assertNotSame($token, $row['token_hash']);
        $this->assertSame(hash('sha256', $token), $row['token_hash']);
    }

    public function testStoreToken_expiresInThirtyMinutes(): void
    {
        $token = \PasswordReset::generateToken();
        \PasswordReset::storeToken($this->pdo, 1, $token);

        $row = $this->pdo->query("SELECT expires_at FROM password_reset_tokens LIMIT 1")->fetch();
        $expiresAt = strtotime($row['expires_at']);
        $now       = time();

        // Should expire between 25 and 35 minutes from now (30 min target)
        $this->assertGreaterThan($now + 25 * 60, $expiresAt);
        $this->assertLessThan($now + 35 * 60, $expiresAt);
    }

    public function testStoreToken_usedAt_isNullInitially(): void
    {
        $token = \PasswordReset::generateToken();
        \PasswordReset::storeToken($this->pdo, 1, $token);

        $row = $this->pdo->query("SELECT used_at FROM password_reset_tokens LIMIT 1")->fetch();
        $this->assertNull($row['used_at']);
    }

    // ── Token Validation ──────────────────────────────────────────────────────

    public function testValidateToken_validToken_returnsUserId(): void
    {
        $token = \PasswordReset::generateToken();
        \PasswordReset::storeToken($this->pdo, 1, $token);

        $result = \PasswordReset::validateToken($this->pdo, $token);
        $this->assertNotFalse($result);
        $this->assertSame(1, (int) $result['user_id']);
    }

    public function testValidateToken_wrongToken_returnsFalse(): void
    {
        $token = \PasswordReset::generateToken();
        \PasswordReset::storeToken($this->pdo, 1, $token);

        $result = \PasswordReset::validateToken($this->pdo, 'completely_wrong_token');
        $this->assertFalse($result);
    }

    public function testValidateToken_expiredToken_returnsFalse(): void
    {
        $token = \PasswordReset::generateToken();
        $hash  = hash('sha256', $token);
        // Insert with an already-expired timestamp
        $past  = date('Y-m-d H:i:s', time() - 3600);
        $this->pdo->prepare("
            INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, used_at)
            VALUES (1, ?, ?, NULL)
        ")->execute([$hash, $past]);

        $result = \PasswordReset::validateToken($this->pdo, $token);
        $this->assertFalse($result);
    }

    public function testValidateToken_usedToken_returnsFalse(): void
    {
        $token  = \PasswordReset::generateToken();
        $hash   = hash('sha256', $token);
        $future = date('Y-m-d H:i:s', time() + 1800);
        $usedAt = date('Y-m-d H:i:s', time() - 60);
        $this->pdo->prepare("
            INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, used_at)
            VALUES (1, ?, ?, ?)
        ")->execute([$hash, $future, $usedAt]);

        $result = \PasswordReset::validateToken($this->pdo, $token);
        $this->assertFalse($result);
    }

    // ── Token Consumption ─────────────────────────────────────────────────────

    public function testConsumeToken_marksTokenAsUsed(): void
    {
        $token = \PasswordReset::generateToken();
        \PasswordReset::storeToken($this->pdo, 1, $token);
        \PasswordReset::consumeToken($this->pdo, $token);

        $row = $this->pdo->query("SELECT used_at FROM password_reset_tokens LIMIT 1")->fetch();
        $this->assertNotNull($row['used_at']);
    }

    public function testConsumeToken_preventsReuseOfSameToken(): void
    {
        $token = \PasswordReset::generateToken();
        \PasswordReset::storeToken($this->pdo, 1, $token);
        \PasswordReset::consumeToken($this->pdo, $token);

        // Attempting to validate after consumption must fail
        $result = \PasswordReset::validateToken($this->pdo, $token);
        $this->assertFalse($result);
    }
}
