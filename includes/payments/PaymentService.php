<?php
require_once __DIR__ . '/PaymentProvider.php';
require_once __DIR__ . '/StripeProvider.php';
require_once __DIR__ . '/PayPalProvider.php';
require_once __DIR__ . '/MercadoPagoProvider.php';

/**
 * Factory that returns the active payment provider based on .env PAYMENT_PROVIDER.
 *
 * To swap providers: change PAYMENT_PROVIDER=stripe (or paypal, mercadopago) in .env.
 * No code changes required.
 */
class PaymentService {
    private static ?PaymentProvider $instance = null;

    public static function getProvider(): PaymentProvider {
        if (self::$instance === null) {
            self::$instance = self::build();
        }
        return self::$instance;
    }

    /**
     * Returns frontend config for ALL configured providers so the checkout page
     * can render the correct payment buttons regardless of which is active.
     */
    public static function getAllFrontendConfigs(): array {
        return [
            'stripe'      => (new StripeProvider())->getFrontendConfig(),
            'paypal'      => (new PayPalProvider())->getFrontendConfig(),
            'mercadopago' => (new MercadoPagoProvider())->getFrontendConfig(),
            'active'      => strtolower(env('PAYMENT_PROVIDER', 'stripe')),
        ];
    }

    private static function build(): PaymentProvider {
        $provider = strtolower(env('PAYMENT_PROVIDER', 'stripe'));

        return match ($provider) {
            'paypal'      => new PayPalProvider(),
            'mercadopago' => new MercadoPagoProvider(),
            default       => new StripeProvider(),
        };
    }
}
