<?php
require_once __DIR__ . '/PaymentProvider.php';

/**
 * Stripe payment provider.
 *
 * Uses the Stripe REST API directly via curl.
 * Credentials from .env:
 *   STRIPE_SECRET  — secret key (sk_live_... or sk_test_...)
 *   STRIPE_KEY     — publishable key (pk_live_... or pk_test_...)
 */
class StripeProvider implements PaymentProvider {
    private string $secretKey;
    private string $publishableKey;

    private const PLATFORM_FEE_RATE = 0.029; // 2.9%

    public function __construct() {
        $this->secretKey      = env('STRIPE_SECRET', '');
        $this->publishableKey = env('STRIPE_KEY', '');
    }

    public function charge(array $order, array $paymentData): array {
        $totalCents   = (int) round($order['total_amount'] * 100);
        $platformCents = (int) round($order['total_amount'] * self::PLATFORM_FEE_RATE * 100);

        $payload = [
            'amount'                => $totalCents,
            'currency'              => 'mxn',
            'payment_method'        => $paymentData['payment_method_id'] ?? '',
            'confirm'               => 'true',
            'metadata[order_id]'    => $order['id'],
            'metadata[customer_id]' => $order['customer_id'],
            'application_fee_amount'=> $platformCents,
        ];

        $response = $this->api('POST', '/v1/payment_intents', $payload);
        if (!$response) {
            return ['success' => false, 'error_message' => 'No response from Stripe'];
        }

        $data = json_decode($response, true);

        if (($data['status'] ?? '') === 'succeeded') {
            return [
                'success'           => true,
                'gateway_reference' => $data['id'],
                'net_amount'        => ($totalCents - $platformCents) / 100,
                'platform_fee'      => $platformCents / 100,
                'gateway_fee'       => 0,
                'raw_response'      => $response,
            ];
        }

        $errorMessage = $data['error']['message'] ?? ($data['last_payment_error']['message'] ?? 'Payment failed');
        return ['success' => false, 'error_message' => $errorMessage];
    }

    public function refund(string $gatewayReference, float $amount, string $reason): array {
        $payload = [
            'charge' => $gatewayReference,
            'amount' => (int) round($amount * 100),
            'reason' => 'requested_by_customer',
        ];

        $response = $this->api('POST', '/v1/refunds', $payload);
        if (!$response) {
            return ['success' => false, 'error' => 'No response from Stripe'];
        }

        $data = json_decode($response, true);

        if (($data['status'] ?? '') === 'succeeded') {
            return ['success' => true, 'refund_id' => $data['id'], 'amount' => $amount];
        }

        return ['success' => false, 'error' => $data['error']['message'] ?? 'Refund failed'];
    }

    public function getFrontendConfig(): array {
        return [
            'enabled'         => !empty($this->publishableKey),
            'publishable_key' => $this->publishableKey,
        ];
    }

    private function api(string $method, string $path, array $payload = []): ?string {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.stripe.com' . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERPWD        => $this->secretKey . ':',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        }

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('StripeProvider curl error: ' . $error);
            return null;
        }

        return $response ?: null;
    }
}
