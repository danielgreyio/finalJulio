<?php
/**
 * PHPUnit bootstrap — sets up the test environment without booting the full app.
 * Loads the Security, ShippingService, PaymentService, AdvancedSecurity, and Mailer
 * classes in isolation. No HTTP request, no PDO connection required for unit tests.
 */

// Minimal env so classes that call env() don't crash
$_ENV['PAYMENT_PROVIDER']      = 'stripe';
$_ENV['SHIPPING_PROVIDERS']    = 'estafeta,dhl';
$_ENV['WAREHOUSE_POSTAL_CODE'] = '06600';
$_ENV['ENCRYPTION_KEY']        = str_repeat('a', 64); // 64-char test key
$_ENV['APP_URL']               = 'https://ventdepot.test';
$_ENV['APP_ENV']               = 'testing';
$_ENV['STRIPE_KEY']            = 'pk_test_placeholder';
$_ENV['STRIPE_SECRET']         = 'sk_test_placeholder';
$_ENV['PAYPAL_CLIENT_ID']      = 'paypal_test_client';
$_ENV['PAYPAL_SECRET']         = 'paypal_test_secret';
$_ENV['PAYPAL_MODE']           = 'sandbox';
$_ENV['MP_ACCESS_TOKEN']       = 'TEST-token';
$_ENV['MP_PUBLIC_KEY']         = 'TEST-public';
$_ENV['MAIL_HOST']             = 'localhost';
$_ENV['MAIL_USERNAME']         = '';
$_ENV['MAIL_PASSWORD']         = '';
$_ENV['MAIL_PORT']             = '587';
$_ENV['MAIL_ENCRYPTION']       = 'tls';
$_ENV['MAIL_FROM_ADDRESS']     = 'noreply@ventdepot.test';
$_ENV['MAIL_FROM_NAME']        = 'VentDepot Test';

// env() helper used by many classes
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $val = $_ENV[$key] ?? getenv($key);
        return ($val !== false && $val !== null) ? $val : $default;
    }
}

// Composer autoload
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Start a session for tests that need $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load the classes under test
require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/shipping/ShippingProvider.php';
require_once dirname(__DIR__) . '/includes/shipping/EstafetaProvider.php';
require_once dirname(__DIR__) . '/includes/shipping/DhlMexicoProvider.php';
require_once dirname(__DIR__) . '/includes/shipping/ShippingService.php';
require_once dirname(__DIR__) . '/includes/payments/PaymentProvider.php';
require_once dirname(__DIR__) . '/includes/payments/StripeProvider.php';
require_once dirname(__DIR__) . '/includes/payments/PayPalProvider.php';
require_once dirname(__DIR__) . '/includes/payments/MercadoPagoProvider.php';
require_once dirname(__DIR__) . '/includes/payments/PaymentService.php';
require_once dirname(__DIR__) . '/includes/AdvancedSecurity.php';
require_once dirname(__DIR__) . '/includes/Mailer.php';
require_once dirname(__DIR__) . '/includes/PasswordReset.php';
