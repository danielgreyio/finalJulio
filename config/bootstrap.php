<?php
/**
 * Bootstrap — loaded by every page.
 * Handles: .env loading, session start, autoload, helper includes.
 */

// ── .env loader ──────────────────────────────────────────────────────────────
// Simple parser — no library needed. Lines starting with # are comments.
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key   = trim($key);
        $value = trim($value, " \t\"'");
        if ($key !== '' && !array_key_exists($key, $_ENV)) {
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// ── Composer autoload ─────────────────────────────────────────────────────────
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// ── Session ───────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', 3600);
    session_start();
}

// ── Core helpers ──────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/includes/security.php';

// ── Convenience helpers ───────────────────────────────────────────────────────
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $val = $_ENV[$key] ?? getenv($key);
        return ($val !== false && $val !== null) ? $val : $default;
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('getUserRole')) {
    function getUserRole(): string {
        return $_SESSION['user_role'] ?? '';
    }
}

if (!function_exists('requireRole')) {
    function requireRole(string $role): void {
        if (!isLoggedIn() || getUserRole() !== $role) {
            header('Location: /login.php');
            exit;
        }
    }
}

if (!function_exists('getCartCount')) {
    function getCartCount(): int {
        return array_sum($_SESSION['cart'] ?? []);
    }
}

// ── CSRF helpers ──────────────────────────────────────────────────────────────
if (!function_exists('requireCSRF')) {
    function requireCSRF(): void {
        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            Security::logSecurityEvent('csrf_token_mismatch', ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
            http_response_code(403);
            die('Invalid request. Please go back and try again.');
        }
    }
}

if (!function_exists('generateCSRFInput')) {
    function generateCSRFInput(): string {
        return Security::getCSRFInput();
    }
}

// ── Global CSRF enforcement ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $path   = $_SERVER['PHP_SELF'] ?? '';
    $bypass = strpos($path, 'webhook') !== false || strpos($path, '/api/') !== false;
    if (!$bypass) {
        $submitted = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Security::validateCSRFToken($submitted)) {
            Security::logSecurityEvent('csrf_validation_failed', ['uri' => $path]);
            http_response_code(403);
            die('CSRF validation failed. Please go back and try again.');
        }
    }
}

// ── Tax constants (from .env, fallback to Mexico IVA 16%) ─────────────────────
define('TAX_RATE',  (float)(env('TAX_RATE',  '0.16')));
define('TAX_LABEL', env('TAX_LABEL', 'IVA (16%)'));
