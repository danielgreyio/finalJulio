<?php
require_once __DIR__ . '/ShippingProvider.php';
require_once __DIR__ . '/EstafetaProvider.php';
require_once __DIR__ . '/DhlMexicoProvider.php';

/**
 * Factory that loads active shipping providers from .env SHIPPING_PROVIDERS
 * and aggregates rate quotes across all of them.
 *
 * To swap or add carriers: change SHIPPING_PROVIDERS=estafeta,dhl in .env.
 * No code changes needed.
 */
class ShippingService {
    /** @var ShippingProvider[] */
    private array $providers = [];

    public function __construct() {
        $active = array_map('trim', explode(',', env('SHIPPING_PROVIDERS', 'estafeta')));

        foreach ($active as $name) {
            switch (strtolower($name)) {
                case 'estafeta':
                    $this->providers['estafeta'] = new EstafetaProvider();
                    break;
                case 'dhl':
                    $this->providers['dhl'] = new DhlMexicoProvider();
                    break;
            }
        }
    }

    /**
     * Get quotes from all active providers.
     *
     * $params keys: origin_postal, destination_postal, weight, length, width, height
     *
     * Returns combined, price-sorted array of quote options.
     * Falls back to flat rate if no carriers are configured or all fail.
     */
    public function getQuotes(array $params): array {
        $params = $this->applyDefaults($params);

        $all = [];
        foreach ($this->providers as $provider) {
            $quotes = $provider->getQuotes($params);
            $all    = array_merge($all, $quotes);
        }

        if (empty($all)) {
            $all = $this->fallbackQuote($params);
        }

        usort($all, fn($a, $b) => $a['price'] <=> $b['price']);
        return $all;
    }

    /**
     * Create a shipment using the specified carrier.
     */
    public function createShipment(string $carrier, array $orderData): array {
        $provider = $this->providers[strtolower($carrier)] ?? null;
        if (!$provider) {
            return ['success' => false, 'error' => "Carrier '$carrier' not configured."];
        }
        return $provider->createShipment($orderData);
    }

    /**
     * Get tracking info for a shipment.
     */
    public function getTracking(string $carrier, string $trackingNumber): array {
        $provider = $this->providers[strtolower($carrier)] ?? null;
        if (!$provider) {
            return ['status' => 'unknown', 'location' => '', 'timestamp' => '', 'events' => []];
        }
        return $provider->getTracking($trackingNumber);
    }

    /**
     * Validate a Mexico postal code (5 digits).
     */
    public static function isValidMexicoPostal(string $postal): bool {
        return (bool) preg_match('/^\d{5}$/', trim($postal));
    }

    private function applyDefaults(array $params): array {
        return array_merge([
            'origin_postal'      => env('WAREHOUSE_POSTAL_CODE', '06600'),
            'destination_postal' => '',
            'weight'             => 1.0,
            'length'             => 20.0,
            'width'              => 15.0,
            'height'             => 10.0,
        ], $params);
    }

    private function fallbackQuote(array $params): array {
        $weight = (float) ($params['weight'] ?? 1.0);
        $price  = $weight > 5 ? 250.00 : 150.00;

        return [[
            'carrier'       => 'standard',
            'service_code'  => 'standard',
            'service_name'  => 'Envío Estándar',
            'price'         => $price,
            'currency'      => 'MXN',
            'transit_days'  => 5,
            'carrier_label' => 'Envío Estándar (3–5 días hábiles)',
        ]];
    }
}
