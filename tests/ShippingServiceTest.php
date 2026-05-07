<?php

namespace VentDepot\Tests;

use PHPUnit\Framework\TestCase;

class ShippingServiceTest extends TestCase
{
    // ── Postal Code Validation ────────────────────────────────────────────────

    public function testIsValidMexicoPostal_validFiveDigits(): void
    {
        $this->assertTrue(\ShippingService::isValidMexicoPostal('06600'));
    }

    public function testIsValidMexicoPostal_leadingZero_valid(): void
    {
        $this->assertTrue(\ShippingService::isValidMexicoPostal('01234'));
    }

    public function testIsValidMexicoPostal_fourDigits_fails(): void
    {
        $this->assertFalse(\ShippingService::isValidMexicoPostal('1234'));
    }

    public function testIsValidMexicoPostal_sixDigits_fails(): void
    {
        $this->assertFalse(\ShippingService::isValidMexicoPostal('123456'));
    }

    public function testIsValidMexicoPostal_withLetters_fails(): void
    {
        $this->assertFalse(\ShippingService::isValidMexicoPostal('1234A'));
    }

    public function testIsValidMexicoPostal_empty_fails(): void
    {
        $this->assertFalse(\ShippingService::isValidMexicoPostal(''));
    }

    public function testIsValidMexicoPostal_withSpaces_fails(): void
    {
        $this->assertFalse(\ShippingService::isValidMexicoPostal('123 45'));
    }

    public function testIsValidMexicoPostal_withDash_fails(): void
    {
        $this->assertFalse(\ShippingService::isValidMexicoPostal('1234-5'));
    }

    // ── Fallback Quotes ───────────────────────────────────────────────────────

    public function testGetQuotes_fallbackWhenNoProviders(): void
    {
        // Override env to provide no shipping providers
        $_ENV['SHIPPING_PROVIDERS'] = '';
        $service = new \ShippingService();
        $quotes = $service->getQuotes([
            'destination_postal' => '64000',
            'weight' => 1.0,
        ]);
        $_ENV['SHIPPING_PROVIDERS'] = 'estafeta,dhl'; // restore

        $this->assertNotEmpty($quotes);
        $this->assertArrayHasKey('price', $quotes[0]);
        $this->assertArrayHasKey('carrier', $quotes[0]);
        $this->assertArrayHasKey('transit_days', $quotes[0]);
    }

    public function testFallbackQuote_lightWeight_isCheaper(): void
    {
        $_ENV['SHIPPING_PROVIDERS'] = '';
        $service = new \ShippingService();

        $lightQuotes = $service->getQuotes(['weight' => 1.0, 'destination_postal' => '64000']);
        $heavyQuotes = $service->getQuotes(['weight' => 10.0, 'destination_postal' => '64000']);

        $_ENV['SHIPPING_PROVIDERS'] = 'estafeta,dhl';

        $this->assertLessThan($heavyQuotes[0]['price'], $lightQuotes[0]['price'] + 0.01);
    }

    public function testFallbackQuote_weightOver5kg_returnsHigherRate(): void
    {
        $_ENV['SHIPPING_PROVIDERS'] = '';
        $service = new \ShippingService();
        $quotes = $service->getQuotes(['weight' => 6.0, 'destination_postal' => '64000']);
        $_ENV['SHIPPING_PROVIDERS'] = 'estafeta,dhl';

        $this->assertGreaterThan(150, $quotes[0]['price']);
    }

    public function testFallbackQuote_weightUnder5kg_returnsBaseRate(): void
    {
        $_ENV['SHIPPING_PROVIDERS'] = '';
        $service = new \ShippingService();
        $quotes = $service->getQuotes(['weight' => 2.0, 'destination_postal' => '64000']);
        $_ENV['SHIPPING_PROVIDERS'] = 'estafeta,dhl';

        $this->assertEquals(150.00, $quotes[0]['price']);
    }

    // ── createShipment with unknown carrier ───────────────────────────────────

    public function testCreateShipment_unknownCarrier_returnsError(): void
    {
        $_ENV['SHIPPING_PROVIDERS'] = 'estafeta';
        $service = new \ShippingService();
        $result = $service->createShipment('nonexistent', []);
        $_ENV['SHIPPING_PROVIDERS'] = 'estafeta,dhl';

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }
}
