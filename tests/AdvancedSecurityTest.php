<?php

namespace VentDepot\Tests;

use PHPUnit\Framework\TestCase;

class AdvancedSecurityTest extends TestCase
{
    private function makePdo(): \PDO
    {
        // In-memory SQLite so AdvancedSecurity constructor doesn't fail.
        // AdvancedSecurity only uses PDO for role/permission queries, not encryption.
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    private function makeInstance(string $key = null): \AdvancedSecurity
    {
        $key = $key ?? str_repeat('a', 64);
        $_ENV['ENCRYPTION_KEY'] = $key;
        return new \AdvancedSecurity($this->makePdo());
    }

    // ── Encryption Roundtrip ──────────────────────────────────────────────────

    public function testEncryptDecrypt_roundtrip(): void
    {
        $sec = $this->makeInstance();
        $plaintext = 'My secret account number 1234567890';
        $ciphertext = $sec->encryptData($plaintext);
        $this->assertSame($plaintext, $sec->decryptData($ciphertext));
    }

    public function testEncrypt_differentCiphertextForSamePlaintext(): void
    {
        $sec = $this->makeInstance();
        $plaintext = 'same input';
        $cipher1 = $sec->encryptData($plaintext);
        $cipher2 = $sec->encryptData($plaintext);
        // Each call uses a random IV, so ciphertexts should differ
        $this->assertNotSame($cipher1, $cipher2);
    }

    public function testEncrypt_producesBase64Output(): void
    {
        $sec = $this->makeInstance();
        $cipher = $sec->encryptData('hello');
        $this->assertNotFalse(base64_decode($cipher, true));
    }

    public function testDecrypt_wrongKey_doesNotReturnOriginal(): void
    {
        $sec1 = $this->makeInstance(str_repeat('a', 64));
        $sec2 = $this->makeInstance(str_repeat('b', 64));

        $cipher = $sec1->encryptData('secret');
        $result = $sec2->decryptData($cipher);
        $this->assertNotSame('secret', $result);
    }

    // ── Key Validation ────────────────────────────────────────────────────────

    public function testConstructor_throwsIfKeyTooShort(): void
    {
        $_ENV['ENCRYPTION_KEY'] = 'tooshort';
        $this->expectException(\RuntimeException::class);
        new \AdvancedSecurity($this->makePdo());
    }

    public function testConstructor_throwsIfKeyMissing(): void
    {
        unset($_ENV['ENCRYPTION_KEY']);
        $this->expectException(\RuntimeException::class);
        new \AdvancedSecurity($this->makePdo());
    }

    public function testConstructor_throwsIfKeyEmpty(): void
    {
        $_ENV['ENCRYPTION_KEY'] = '';
        $this->expectException(\RuntimeException::class);
        new \AdvancedSecurity($this->makePdo());
    }

    public function testConstructor_acceptsKeyOf32Chars(): void
    {
        $_ENV['ENCRYPTION_KEY'] = str_repeat('x', 32);
        $sec = new \AdvancedSecurity($this->makePdo());
        $this->assertInstanceOf(\AdvancedSecurity::class, $sec);
    }

    protected function tearDown(): void
    {
        // Restore test key so other tests aren't affected
        $_ENV['ENCRYPTION_KEY'] = str_repeat('a', 64);
    }
}
