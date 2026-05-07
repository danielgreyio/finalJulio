<?php

namespace VentDepot\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for Security::validateRedirect() — a method we're adding to the Security class
 * to extract the login redirect validation into a pure, testable function.
 */
class OpenRedirectTest extends TestCase
{
    private array $whitelist = [
        'index.php',
        'merchant/dashboard.php',
        'admin/dashboard.php',
        'profile.php',
        'cart.php',
    ];

    // ── Allowed URLs ──────────────────────────────────────────────────────────

    public function testValidateRedirect_allowsIndexPhp(): void
    {
        $result = \Security::validateRedirect('index.php', $this->whitelist);
        $this->assertSame('index.php', $result);
    }

    public function testValidateRedirect_allowsMerchantDashboard(): void
    {
        $result = \Security::validateRedirect('merchant/dashboard.php', $this->whitelist);
        $this->assertSame('merchant/dashboard.php', $result);
    }

    public function testValidateRedirect_allowsAdminDashboard(): void
    {
        $result = \Security::validateRedirect('admin/dashboard.php', $this->whitelist);
        $this->assertSame('admin/dashboard.php', $result);
    }

    // ── Blocked: External URLs ────────────────────────────────────────────────

    public function testValidateRedirect_blocksHttpsExternalUrl(): void
    {
        $result = \Security::validateRedirect('https://evil.com/steal', $this->whitelist);
        $this->assertSame('index.php', $result);
    }

    public function testValidateRedirect_blocksHttpExternalUrl(): void
    {
        $result = \Security::validateRedirect('http://evil.com', $this->whitelist);
        $this->assertSame('index.php', $result);
    }

    public function testValidateRedirect_blocksProtocolRelativeUrl(): void
    {
        $result = \Security::validateRedirect('//evil.com/phish', $this->whitelist);
        $this->assertSame('index.php', $result);
    }

    // ── Blocked: Directory Traversal ──────────────────────────────────────────

    public function testValidateRedirect_blocksParentDirectoryTraversal(): void
    {
        $result = \Security::validateRedirect('../../etc/passwd.php', $this->whitelist);
        $this->assertSame('index.php', $result);
    }

    public function testValidateRedirect_blocksEncodedTraversal(): void
    {
        $result = \Security::validateRedirect('%2e%2e%2fadmin.php', $this->whitelist);
        $this->assertSame('index.php', $result);
    }

    // ── Blocked: Unknown PHP files ────────────────────────────────────────────

    public function testValidateRedirect_blocksArbitraryPhpFile(): void
    {
        // A PHP file not in the whitelist must be blocked (no regex fallback)
        $result = \Security::validateRedirect('some-random-page.php', $this->whitelist);
        $this->assertSame('index.php', $result);
    }

    public function testValidateRedirect_blocksNullByte(): void
    {
        $result = \Security::validateRedirect("index.php\0.txt", $this->whitelist);
        $this->assertSame('index.php', $result);
    }

    // ── Default fallback ──────────────────────────────────────────────────────

    public function testValidateRedirect_emptyString_returnsDefault(): void
    {
        $result = \Security::validateRedirect('', $this->whitelist);
        $this->assertSame('index.php', $result);
    }

    public function testValidateRedirect_nullBehavior_returnsDefault(): void
    {
        $result = \Security::validateRedirect('null', $this->whitelist);
        $this->assertSame('index.php', $result);
    }
}
