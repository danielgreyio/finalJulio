<?php
require_once __DIR__ . '/ShippingProvider.php';

/**
 * DHL Express Mexico shipping provider.
 *
 * Uses the MyDHL API (REST/JSON) via curl.
 * Credentials are set via .env:
 *   DHL_API_KEY       — username (format PICXXXXXX)
 *   DHL_API_SECRET    — password
 *   DHL_ACCOUNT_NUMBER — 9-digit DHL Express account number
 *   DHL_ENVIRONMENT   — 'sandbox' or 'production'
 */
class DhlMexicoProvider implements ShippingProvider {
    private string $apiKey;
    private string $apiSecret;
    private string $accountNumber;
    private string $baseUrl;

    private const PRODUCT_NAMES = [
        'P' => 'Express Worldwide',
        'D' => 'Express Worldwide Doc',
        'K' => 'Express 9:00',
        'T' => 'Express 12:00',
        'N' => 'Domestic Express',
        'H' => 'Economy Select',
        'W' => 'Economy Select Doc',
        'X' => 'Express Envelope',
        'E' => 'Express 9:00 Doc',
        'M' => 'Express 10:30',
        'L' => 'Express 10:30 Doc',
    ];

    public function __construct() {
        $this->apiKey        = env('DHL_API_KEY', '');
        $this->apiSecret     = env('DHL_API_SECRET', '');
        $this->accountNumber = env('DHL_ACCOUNT_NUMBER', '');

        $sandbox = strtolower(env('DHL_ENVIRONMENT', 'sandbox')) !== 'production';
        $this->baseUrl = $sandbox
            ? 'https://express.api.dhl.com/mydhlapi/test'
            : 'https://express.api.dhl.com/mydhlapi';
    }

    public function getQuotes(array $params): array {
        if (!$this->isConfigured()) {
            return [];
        }

        $query = http_build_query([
            'accountNumber'              => $this->accountNumber,
            'originCountryCode'          => 'MX',
            'originPostalCode'           => $params['origin_postal'],
            'destinationCountryCode'     => 'MX',
            'destinationPostalCode'      => $params['destination_postal'],
            'weight'                     => (float) ($params['weight'] ?? 1.0),
            'length'                     => (float) ($params['length'] ?? 10.0),
            'width'                      => (float) ($params['width']  ?? 10.0),
            'height'                     => (float) ($params['height'] ?? 10.0),
            'plannedShippingDateAndTime' => (new DateTimeImmutable('tomorrow'))->format('Y-m-d\TH:i:s\G\M\T+00:00'),
            'isCustomsDeclarable'        => 'false',
            'unitOfMeasurement'          => 'metric',
            'nextBusinessDay'            => 'false',
        ]);

        $response = $this->curl('GET', '/rates?' . $query);
        if (!$response) {
            return [];
        }

        $data = json_decode($response, true);
        return $this->parseRateResponse($data ?? []);
    }

    public function createShipment(array $orderData): array {
        return ['success' => false, 'error' => 'Shipment creation not yet configured.'];
    }

    public function getTracking(string $trackingNumber): array {
        $response = $this->curl('GET', '/tracking?shipmentTrackingNumber=' . urlencode($trackingNumber));
        if (!$response) {
            return ['status' => 'unknown', 'location' => '', 'timestamp' => '', 'events' => []];
        }

        $data   = json_decode($response, true) ?? [];
        $events = $data['shipments'][0]['events'] ?? [];
        $latest = $events[0] ?? [];

        return [
            'status'    => $latest['description'] ?? 'unknown',
            'location'  => $latest['serviceArea'][0]['description'] ?? '',
            'timestamp' => $latest['timestamp'] ?? '',
            'events'    => $events,
        ];
    }

    private function isConfigured(): bool {
        return $this->apiKey !== '' && $this->apiSecret !== '' && $this->accountNumber !== '';
    }

    private function curl(string $method, string $path): ?string {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->baseUrl . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERPWD        => $this->apiKey . ':' . $this->apiSecret,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('DhlMexicoProvider curl error: ' . $error);
            return null;
        }

        return $response ?: null;
    }

    private function parseRateResponse(array $data): array {
        $quotes = [];

        foreach ($data['products'] ?? [] as $product) {
            $code  = $product['productCode'] ?? '';
            $name  = $product['productName'] ?? (self::PRODUCT_NAMES[$code] ?? $code);
            $price = 0.0;

            foreach ($product['totalPrice'] ?? [] as $priceEntry) {
                if (($priceEntry['currencyType'] ?? '') === 'BILLC') {
                    $price = (float) ($priceEntry['price'] ?? 0);
                    break;
                }
            }

            if ($price <= 0) {
                continue;
            }

            $transitDays = (int) ($product['deliveryCapabilities']['transitDays'] ?? -1);

            $quotes[] = [
                'carrier'       => 'dhl',
                'service_code'  => $code,
                'service_name'  => $name,
                'price'         => $price,
                'currency'      => 'MXN',
                'transit_days'  => $transitDays,
                'carrier_label' => 'DHL — ' . $name,
            ];
        }

        usort($quotes, fn($a, $b) => $a['price'] <=> $b['price']);
        return $quotes;
    }
}
