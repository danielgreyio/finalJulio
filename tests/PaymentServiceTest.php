<?php

namespace VentDepot\Tests;

use PHPUnit\Framework\TestCase;

class PaymentServiceTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset singleton between tests
        $ref = new \ReflectionClass(\PaymentService::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    // ── Provider Factory ──────────────────────────────────────────────────────

    public function testGetProvider_stripe_returnsStripeProvider(): void
    {
        $_ENV['PAYMENT_PROVIDER'] = 'stripe';
        $this->setUp(); // reset singleton
        $provider = \PaymentService::getProvider();
        $this->assertInstanceOf(\StripeProvider::class, $provider);
    }

    public function testGetProvider_paypal_returnsPayPalProvider(): void
    {
        $_ENV['PAYMENT_PROVIDER'] = 'paypal';
        $this->setUp();
        $provider = \PaymentService::getProvider();
        $this->assertInstanceOf(\PayPalProvider::class, $provider);
    }

    public function testGetProvider_mercadopago_returnsMercadoPagoProvider(): void
    {
        $_ENV['PAYMENT_PROVIDER'] = 'mercadopago';
        $this->setUp();
        $provider = \PaymentService::getProvider();
        $this->assertInstanceOf(\MercadoPagoProvider::class, $provider);
    }

    public function testGetProvider_unknownValue_defaultsToStripe(): void
    {
        // Unknown values default to stripe (match default branch)
        $_ENV['PAYMENT_PROVIDER'] = 'unknown_gateway';
        $this->setUp();
        $provider = \PaymentService::getProvider();
        $this->assertInstanceOf(\StripeProvider::class, $provider);
    }

    public function testGetProvider_caseInsensitive(): void
    {
        $_ENV['PAYMENT_PROVIDER'] = 'PAYPAL';
        $this->setUp();
        $provider = \PaymentService::getProvider();
        $this->assertInstanceOf(\PayPalProvider::class, $provider);
    }

    // ── Frontend Configs ──────────────────────────────────────────────────────

    public function testGetAllFrontendConfigs_containsActiveKey(): void
    {
        $_ENV['PAYMENT_PROVIDER'] = 'stripe';
        $configs = \PaymentService::getAllFrontendConfigs();
        $this->assertArrayHasKey('active', $configs);
        $this->assertSame('stripe', $configs['active']);
    }

    public function testGetAllFrontendConfigs_containsAllProviders(): void
    {
        $configs = \PaymentService::getAllFrontendConfigs();
        $this->assertArrayHasKey('stripe', $configs);
        $this->assertArrayHasKey('paypal', $configs);
        $this->assertArrayHasKey('mercadopago', $configs);
    }

    public function testGetAllFrontendConfigs_stripeConfig_hasEnabledKey(): void
    {
        $configs = \PaymentService::getAllFrontendConfigs();
        $this->assertArrayHasKey('enabled', $configs['stripe']);
    }

    public function testGetAllFrontendConfigs_activeReflectsEnv(): void
    {
        $_ENV['PAYMENT_PROVIDER'] = 'mercadopago';
        $configs = \PaymentService::getAllFrontendConfigs();
        $this->assertSame('mercadopago', $configs['active']);
    }

    // ── Interface Compliance ──────────────────────────────────────────────────

    public function testStripeProvider_implementsPaymentProvider(): void
    {
        $this->assertInstanceOf(\PaymentProvider::class, new \StripeProvider());
    }

    public function testPayPalProvider_implementsPaymentProvider(): void
    {
        $this->assertInstanceOf(\PaymentProvider::class, new \PayPalProvider());
    }

    public function testMercadoPagoProvider_implementsPaymentProvider(): void
    {
        $this->assertInstanceOf(\PaymentProvider::class, new \MercadoPagoProvider());
    }
}
