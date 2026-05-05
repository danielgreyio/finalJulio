<?php

/**
 * Common shape for a single shipping quote option.
 *
 * @property string $carrier        e.g. 'estafeta' | 'dhl'
 * @property string $service_code   carrier-specific code
 * @property string $service_name   human label, e.g. 'Día Siguiente'
 * @property float  $price          total cost in MXN
 * @property string $currency       always 'MXN'
 * @property int    $transit_days   estimated transit days (0 = same-day, -1 = unknown)
 * @property string $carrier_label  display string for checkout UI
 */

interface ShippingProvider {
    /**
     * Return available rate quotes for a shipment.
     *
     * Required keys in $params:
     *   origin_postal      string  5-digit Mexico CP
     *   destination_postal string  5-digit Mexico CP
     *   weight             float   kg
     *   length             float   cm
     *   width              float   cm
     *   height             float   cm
     *
     * Returns array of quote arrays, each matching the shape above.
     * Returns empty array if the carrier cannot service this route.
     */
    public function getQuotes(array $params): array;

    /**
     * Create a shipment and return tracking info.
     *
     * $orderData keys:
     *   order_id, service_code, origin (address array), destination (address array),
     *   weight, length, width, height, declared_value
     *
     * Returns ['success' => bool, 'tracking_number' => string, 'label_url' => string, 'error' => string]
     */
    public function createShipment(array $orderData): array;

    /**
     * Get tracking status for a tracking number.
     *
     * Returns ['status' => string, 'location' => string, 'timestamp' => string, 'events' => array]
     */
    public function getTracking(string $trackingNumber): array;
}
