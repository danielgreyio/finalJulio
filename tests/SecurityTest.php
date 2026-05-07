<?php

namespace VentDepot\Tests;

use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset session state before each test
        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    // ── CSRF ──────────────────────────────────────────────────────────────────

    public function testCsrfTokenGeneration_isUnique(): void
    {
        $token1 = \Security::generateCSRFToken();
        $_SESSION = []; // reset
        $token2 = \Security::generateCSRFToken();
        $this->assertNotSame($token1, $token2);
    }

    public function testCsrfTokenGeneration_isSixtyFourHexChars(): void
    {
        $token = \Security::generateCSRFToken();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testCsrfTokenValidation_valid(): void
    {
        $token = \Security::generateCSRFToken();
        $this->assertTrue(\Security::validateCSRFToken($token));
    }

    public function testCsrfTokenValidation_invalidToken(): void
    {
        \Security::generateCSRFToken(); // ensure session token exists
        $this->assertFalse(\Security::validateCSRFToken('invalid_token_value'));
    }

    public function testCsrfTokenValidation_missingToken(): void
    {
        \Security::generateCSRFToken();
        $this->assertFalse(\Security::validateCSRFToken(''));
    }

    public function testCsrfTokenValidation_noSession(): void
    {
        $_SESSION = [];
        $this->assertFalse(\Security::validateCSRFToken('anything'));
    }

    public function testCsrfTokenGeneration_returnsSameTokenOnRepeatCall(): void
    {
        $token1 = \Security::generateCSRFToken();
        $token2 = \Security::generateCSRFToken();
        $this->assertSame($token1, $token2);
    }

    // ── Input Validation ──────────────────────────────────────────────────────

    public function testInputValidation_requiredField_empty(): void
    {
        $errors = \Security::validateInput(['name' => ''], ['name' => ['required' => true]]);
        $this->assertArrayHasKey('name', $errors);
    }

    public function testInputValidation_requiredField_present(): void
    {
        $errors = \Security::validateInput(['name' => 'Alice'], ['name' => ['required' => true]]);
        $this->assertArrayNotHasKey('name', $errors);
    }

    public function testInputValidation_maxLength_exceeded(): void
    {
        $errors = \Security::validateInput(
            ['title' => str_repeat('x', 256)],
            ['title' => ['max_length' => 255]]
        );
        $this->assertArrayHasKey('title', $errors);
    }

    public function testInputValidation_maxLength_withinLimit(): void
    {
        $errors = \Security::validateInput(
            ['title' => str_repeat('x', 100)],
            ['title' => ['max_length' => 255]]
        );
        $this->assertArrayNotHasKey('title', $errors);
    }

    public function testInputValidation_emailFormat_valid(): void
    {
        $errors = \Security::validateInput(
            ['email' => 'user@example.com'],
            ['email' => ['type' => 'email']]
        );
        $this->assertArrayNotHasKey('email', $errors);
    }

    public function testInputValidation_emailFormat_invalid(): void
    {
        $errors = \Security::validateInput(
            ['email' => 'not-an-email'],
            ['email' => ['type' => 'email']]
        );
        $this->assertArrayHasKey('email', $errors);
    }

    public function testInputValidation_integer_valid(): void
    {
        $errors = \Security::validateInput(
            ['qty' => '5'],
            ['qty' => ['type' => 'integer', 'min_value' => 1]]
        );
        $this->assertArrayNotHasKey('qty', $errors);
    }

    public function testInputValidation_integer_invalid(): void
    {
        $errors = \Security::validateInput(
            ['qty' => 'abc'],
            ['qty' => ['type' => 'integer']]
        );
        $this->assertArrayHasKey('qty', $errors);
    }

    // ── Sanitize ──────────────────────────────────────────────────────────────

    public function testSanitizeInput_stripsHtmlTags(): void
    {
        $result = \Security::sanitizeInput('<script>alert(1)</script>Hello', 'string');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function testSanitizeInput_string_trimsWhitespace(): void
    {
        $result = \Security::sanitizeInput('  hello  ', 'string');
        $this->assertSame('hello', $result);
    }

    public function testSanitizeInput_int_returnsNumericString(): void
    {
        $result = \Security::sanitizeInput('42abc', 'int');
        $this->assertSame('42', $result);
    }

    // ── File Upload Validation ─────────────────────────────────────────────────

    public function testFileUpload_noError_validMime(): void
    {
        // Build a fake $file array simulating a successful JPEG upload
        $tmpFile = tempnam(sys_get_temp_dir(), 'vu_test_');
        // Write a minimal JPEG header so finfo can detect it
        file_put_contents($tmpFile, "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 100));

        $file = [
            'name'     => 'photo.jpg',
            'tmp_name' => $tmpFile,
            'size'     => 100,
            'error'    => UPLOAD_ERR_OK,
        ];
        $errors = \Security::validateFileUpload($file, ['image/jpeg'], 5 * 1024 * 1024);
        unlink($tmpFile);

        $this->assertEmpty($errors);
    }

    public function testFileUpload_tooLarge(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'vu_test_');
        file_put_contents($tmpFile, "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 100));

        $file = [
            'name'     => 'big.jpg',
            'tmp_name' => $tmpFile,
            'size'     => 10 * 1024 * 1024, // 10 MB
            'error'    => UPLOAD_ERR_OK,
        ];
        $errors = \Security::validateFileUpload($file, ['image/jpeg'], 5 * 1024 * 1024);
        unlink($tmpFile);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('size', strtolower($errors[0]));
    }

    public function testFileUpload_uploadError_noFile(): void
    {
        $file = ['name' => '', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE];
        $errors = \Security::validateFileUpload($file);
        $this->assertNotEmpty($errors);
    }

    public function testFileUpload_uploadError_tooLargeByIni(): void
    {
        $file = ['name' => 'x.jpg', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_INI_SIZE];
        $errors = \Security::validateFileUpload($file);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('large', strtolower($errors[0]));
    }

    // ── Rate Limiting ──────────────────────────────────────────────────────────

    public function testRateLimit_allowsUnderThreshold(): void
    {
        $_SESSION = [];
        for ($i = 0; $i < 4; $i++) {
            $this->assertTrue(\Security::checkRateLimit('test_action', 5, 3600));
        }
    }

    public function testRateLimit_blocksAtThreshold(): void
    {
        $_SESSION = [];
        for ($i = 0; $i < 5; $i++) {
            \Security::checkRateLimit('limit_action', 5, 3600);
        }
        $this->assertFalse(\Security::checkRateLimit('limit_action', 5, 3600));
    }

    public function testRateLimit_differentActionsAreIndependent(): void
    {
        $_SESSION = [];
        for ($i = 0; $i < 5; $i++) {
            \Security::checkRateLimit('action_a', 5, 3600);
        }
        // action_b should still be allowed
        $this->assertTrue(\Security::checkRateLimit('action_b', 5, 3600));
    }

    // ── Password Hashing ──────────────────────────────────────────────────────

    public function testPasswordHash_verifyRoundtrip(): void
    {
        $hash = \Security::hashPassword('mySecret123!');
        $this->assertTrue(\Security::verifyPassword('mySecret123!', $hash));
    }

    public function testPasswordHash_wrongPasswordFails(): void
    {
        $hash = \Security::hashPassword('correct');
        $this->assertFalse(\Security::verifyPassword('wrong', $hash));
    }

    public function testPasswordHash_differentHashEachTime(): void
    {
        $hash1 = \Security::hashPassword('same');
        $hash2 = \Security::hashPassword('same');
        $this->assertNotSame($hash1, $hash2); // bcrypt/argon2 salts differ
    }
}
