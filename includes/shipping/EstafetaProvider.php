<?php
require_once __DIR__ . '/ShippingProvider.php';

/**
 * Estafeta eCommerce shipping provider.
 *
 * Uses the FrecuenciaCotizador SOAP service.
 * Credentials are set via .env:
 *   ESTAFETA_CUSTOMER_NUMBER  — numeric idusuario assigned by Estafeta
 *   ESTAFETA_USER             — login username
 *   ESTAFETA_PASSWORD         — password
 *   ESTAFETA_ENVIRONMENT      — 'sandbox' or 'production'
 */
class EstafetaProvider implements ShippingProvider {
    private int    $userId;
    private string $username;
    private string $password;
    private string $wsdl;

    // Maps DescripcionServicio keywords to approximate transit days
    private const TRANSIT_MAP = [
        'dia siguiente' => 1,
        'siguiente'     => 1,
        'dos dias'      => 2,
        '2 dias'        => 2,
        'tres dias'     => 3,
        '3 dias'        => 3,
        'terrestre'     => 5,
        '11:30'         => 1,
        '14:30'         => 1,
        'express'       => 1,
    ];

    public function __construct() {
        $this->userId   = (int) env('ESTAFETA_CUSTOMER_NUMBER', '0');
        $this->username = env('ESTAFETA_USER', '');
        $this->password = env('ESTAFETA_PASSWORD', '');

        $sandbox = strtolower(env('ESTAFETA_ENVIRONMENT', 'sandbox')) !== 'production';
        $this->wsdl = $sandbox
            ? 'https://frecuenciacotizadorqa.estafeta.com/Service.asmx?WSDL'
            : 'https://frecuenciacotizador.estafeta.com/Service.asmx?WSDL';
    }

    public function getQuotes(array $params): array {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $client = new SoapClient($this->wsdl, [
                'trace'      => false,
                'encoding'   => 'UTF-8',
                'exceptions' => true,
            ]);

            $result = $client->FrecuenciaCotizadorPlano([
                'idusuario'    => $this->userId,
                'usuario'      => $this->username,
                'contra'       => $this->password,
                'esFrecuencia' => true,
                'esLista'      => false,
                'espaquete'    => true,
                'peso'         => (float) ($params['weight'] ?? 1.0),
                'largo'        => (float) ($params['length'] ?? 10.0),
                'alto'         => (float) ($params['height'] ?? 10.0),
                'ancho'        => (float) ($params['width']  ?? 10.0),
                'datosOrigen'  => ['string' => [$params['origin_postal'],      '']],
                'datosDestino' => ['string' => [$params['destination_postal'], '']],
            ]);

            return $this->parseQuoteResponse($result);

        } catch (Exception $e) {
            error_log('EstafetaProvider::getQuotes error: ' . $e->getMessage());
            return [];
        }
    }

    public function createShipment(array $orderData): array {
        // Shipment creation requires Estafeta LabelGenerator SOAP service —
        // to be wired once the client provides production credentials.
        return ['success' => false, 'error' => 'Shipment creation not yet configured.'];
    }

    public function getTracking(string $trackingNumber): array {
        // Tracking uses the Estafeta Rastreo service.
        return ['status' => 'unknown', 'location' => '', 'timestamp' => '', 'events' => []];
    }

    private function isConfigured(): bool {
        return $this->userId > 0 && $this->username !== '' && $this->password !== '';
    }

    private function parseQuoteResponse(object $result): array {
        $quotes = [];

        // Result can be a single Respuesta or an array
        $responses = $result->FrecuenciaCotizadorPlanoResult->Respuesta ?? null;
        if (!$responses) {
            return [];
        }
        if (!is_array($responses)) {
            $responses = [$responses];
        }

        foreach ($responses as $respuesta) {
            if (!isset($respuesta->TipoServicio)) {
                continue;
            }

            $services = $respuesta->TipoServicio;
            if (!is_array($services)) {
                $services = [$services];
            }

            foreach ($services as $service) {
                if (empty($service->AplicaServicio)) {
                    continue;
                }

                $name  = (string) ($service->DescripcionServicio ?? '');
                $price = (float)  ($service->CostoTotal ?? 0);
                $code  = (string) ($service->TipoEnvioRes ?? '');

                if ($price <= 0) {
                    continue;
                }

                $quotes[] = [
                    'carrier'       => 'estafeta',
                    'service_code'  => $code,
                    'service_name'  => $name,
                    'price'         => $price,
                    'currency'      => 'MXN',
                    'transit_days'  => $this->guessTransitDays($name),
                    'carrier_label' => 'Estafeta — ' . $name,
                ];
            }
        }

        // Sort cheapest first
        usort($quotes, fn($a, $b) => $a['price'] <=> $b['price']);
        return $quotes;
    }

    private function guessTransitDays(string $description): int {
        $lower = mb_strtolower($description);
        foreach (self::TRANSIT_MAP as $keyword => $days) {
            if (str_contains($lower, $keyword)) {
                return $days;
            }
        }
        return -1;
    }
}
