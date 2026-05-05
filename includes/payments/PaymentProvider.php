<?php

/**
 * Payment provider interface.
 *
 * charge() result shape:
 *   success          bool
 *   gateway_reference string   provider's transaction/charge ID
 *   net_amount        float    amount after platform fee, in MXN
 *   platform_fee      float    platform's cut, in MXN
 *   gateway_fee       float    provider's processing fee (if separated), in MXN
 *   raw_response      string   JSON-encoded full provider response
 *   error_message     string   only present on failure
 *
 * refund() result shape:
 *   success    bool
 *   refund_id  string
 *   amount     float
 *   error      string  only present on failure
 *
 * getFrontendConfig() returns whatever the checkout JS needs:
 *   enabled    bool
 *   + provider-specific keys (publishable_key, client_id, public_key, etc.)
 */

interface PaymentProvider {
    public function charge(array $order, array $paymentData): array;
    public function refund(string $gatewayReference, float $amount, string $reason): array;
    public function getFrontendConfig(): array;
}
