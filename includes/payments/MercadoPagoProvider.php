<?php
require_once __DIR__ . '/PaymentProvider.php';

/**
 * Mercado Pago payment provider.
 *
 * Uses the Mercado Pago v1 REST API via curl.
 * Supports: credit/debit cards, OXXO cash (cash payments), bank transfer.
 *
 * Credentials from .env:
 *   MP_ACCESS_TOKEN — server-side access token (TEST-xxx... or APP_USR-xxx...)
 *   MP_PUBLIC_KEY   — client-side public key for the JS SDK
 */
class MercadoPagoProvider implements PaymentProvider {
    private string $accessToken;
    private string $publicKey;

    private const BASE_URL          = 'https://api.mercadopago.com';
    private const PLATFORM_FEE_RATE = 0.029;

    // MP payment type IDs that indicate OXXO / cash
    private const OFFLINE_METHODS = ['oxxo', 'efectivo', 'atm'];

    public function __construct() {
        $this->accessToken = env('MP_ACCESS_TOKEN', '');
        $this->publicKey   = env('MP_PUBLIC_KEY', '');
    }

    public function charge(array $order, array $paymentData): array {
        $platformFee = $order['total_amount'] * self::PLATFORM_FEE_RATE;
        $netAmount   = $order['total_amount'] - $platformFee;

        $payload = [
            'transaction_amount'  => (float) $order['total_amount'],
            'token'               => $paymentData['mp_token']         ?? null,
            'description'         => 'Orden VentDepot #' . $order['id'],
            'installments'        => (int) ($paymentData['installments'] ?? 1),
            'payment_method_id'   => $paymentData['payment_method_id']  ?? null,
            'payer'               => [
                'email' => $order['customer_email'] ?? '',
            ],
            'external_reference'  => (string) $order['id'],
            'statement_descriptor'=> 'VENTDEPOT',
            'application_fee'     => round($platformFee, 2),
        ];

        // Remove nulls — MP rejects unknown null fields
        $payload = array_filter($payload, fn($v) => $v !== null);

        $response = $this->api('POST', '/v1/payments', $payload);
        if (!$response) {
            return ['success' => false, 'error_message' => 'No response from Mercado Pago'];
        }

        $data = json_decode($response, true);
        $status = $data['status'] ?? '';

        if ($status === 'approved') {
            return [
                'success'           => true,
                'gateway_reference' => (string) ($data['id'] ?? ''),
                'net_amount'        => round($netAmount, 2),
                'platform_fee'      => round($platformFee, 2),
                'gateway_fee'       => 0,
                'raw_response'      => $response,
            ];
        }

        if ($status === 'pending' && in_array($data['payment_type_id'] ?? '', self::OFFLINE_METHODS)) {
            // OXXO / cash — pending is expected; return voucher info
            return [
                'success'           => true,
                'gateway_reference' => (string) ($data['id'] ?? ''),
                'net_amount'        => round($netAmount, 2),
                'platform_fee'      => round($platformFee, 2),
                'gateway_fee'       => 0,
                'raw_response'      => $response,
                'pending_cash'      => true,
                'voucher_url'       => $data['transaction_details']['external_resource_url'] ?? null,
            ];
        }

        $errorMessage = $data['message'] ?? ($data['status_detail'] ?? 'Payment failed');
        return ['success' => false, 'error_message' => $errorMessage];
    }

    public function refund(string $gatewayReference, float $amount, string $reason): array {
        $payload  = ['amount' => round($amount, 2)];
        $response = $this->api('POST', "/v1/payments/{$gatewayReference}/refunds", $payload);

        if (!$response) {
            return ['success' => false, 'error' => 'No response from Mercado Pago'];
        }

        $data = json_decode($response, true);

        if (isset($data['id'])) {
            return [
                'success'   => true,
                'refund_id' => (string) $data['id'],
                'amount'    => (float) ($data['amount'] ?? $amount),
            ];
        }

        return ['success' => false, 'error' => $data['message'] ?? 'Refund failed'];
    }

    public function getFrontendConfig(): array {
        return [
            'enabled'    => !empty($this->accessToken),
            'public_key' => $this->publicKey,
        ];
    }

    private function api(string $method, string $path, array $payload = []): ?string {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::BASE_URL . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('MercadoPagoProvider curl error: ' . $error);
            return null;
        }

        return $response ?: null;
    }
}
