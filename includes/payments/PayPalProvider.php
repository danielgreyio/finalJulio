<?php
require_once __DIR__ . '/PaymentProvider.php';

/**
 * PayPal payment provider.
 *
 * Uses PayPal REST v2 API via curl.
 * Credentials from .env:
 *   PAYPAL_CLIENT_ID  — client ID
 *   PAYPAL_SECRET     — client secret
 *   PAYPAL_MODE       — 'sandbox' or 'live'
 */
class PayPalProvider implements PaymentProvider {
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;

    private const PLATFORM_FEE_RATE = 0.029;

    public function __construct() {
        $this->clientId     = env('PAYPAL_CLIENT_ID', '');
        $this->clientSecret = env('PAYPAL_SECRET', '');

        $live = strtolower(env('PAYPAL_MODE', 'sandbox')) === 'live';
        $this->baseUrl = $live
            ? 'https://api.paypal.com'
            : 'https://api.sandbox.paypal.com';
    }

    public function charge(array $order, array $paymentData): array {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'error_message' => 'Failed to authenticate with PayPal'];
        }

        $total       = number_format($order['total_amount'], 2, '.', '');
        $platformFee = number_format($order['total_amount'] * self::PLATFORM_FEE_RATE, 2, '.', '');
        $netAmount   = number_format($order['total_amount'] - (float) $platformFee, 2, '.', '');

        $payload = [
            'intent'         => 'CAPTURE',
            'purchase_units' => [[
                'reference_id'        => 'order_' . $order['id'],
                'amount'              => ['currency_code' => 'MXN', 'value' => $total],
                'payment_instruction' => [
                    'platform_fees' => [[
                        'amount' => ['currency_code' => 'MXN', 'value' => $platformFee],
                    ]],
                ],
            ]],
        ];

        $response = $this->api('POST', '/v2/checkout/orders', $payload, $accessToken);
        if (!$response) {
            return ['success' => false, 'error_message' => 'No response from PayPal'];
        }

        $data = json_decode($response, true);

        if (($data['status'] ?? '') === 'COMPLETED') {
            return [
                'success'           => true,
                'gateway_reference' => $data['id'],
                'net_amount'        => (float) $netAmount,
                'platform_fee'      => (float) $platformFee,
                'gateway_fee'       => 0,
                'raw_response'      => $response,
            ];
        }

        return ['success' => false, 'error_message' => $data['message'] ?? 'PayPal payment failed'];
    }

    public function refund(string $gatewayReference, float $amount, string $reason): array {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'error' => 'Failed to authenticate with PayPal'];
        }

        $payload  = ['amount' => ['currency_code' => 'MXN', 'value' => number_format($amount, 2, '.', '')]];
        $response = $this->api('POST', "/v2/payments/captures/{$gatewayReference}/refund", $payload, $accessToken);

        if (!$response) {
            return ['success' => false, 'error' => 'No response from PayPal'];
        }

        $data = json_decode($response, true);

        if (($data['status'] ?? '') === 'COMPLETED') {
            return ['success' => true, 'refund_id' => $data['id'], 'amount' => $amount];
        }

        return ['success' => false, 'error' => $data['message'] ?? 'Refund failed'];
    }

    public function getFrontendConfig(): array {
        return [
            'enabled'   => !empty($this->clientId),
            'client_id' => $this->clientId,
            'mode'      => env('PAYPAL_MODE', 'sandbox'),
        ];
    }

    private function getAccessToken(): ?string {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->baseUrl . '/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => $this->clientId . ':' . $this->clientSecret,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: en_US'],
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    private function api(string $method, string $path, array $payload, string $accessToken): ?string {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->baseUrl . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('PayPalProvider curl error: ' . $error);
            return null;
        }

        return $response ?: null;
    }
}
