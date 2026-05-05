# VentDepot — Shipping Carriers Operations Guide

## Current Carriers

Two carriers are active: **Estafeta** and **DHL Mexico**. Both are configured exclusively through `.env`. No code changes are required to add, remove, or reconfigure them.

Carrier selection at runtime is controlled by:

```
SHIPPING_PROVIDERS=estafeta,dhl
```

The `ShippingService` reads this on each request, instantiates the listed providers, and merges their quotes sorted cheapest-first.

---

## Updating Estafeta Credentials

**Keys to change in `.env`:**

```
ESTAFETA_USER=your_username
ESTAFETA_PASSWORD=your_password
ESTAFETA_CUSTOMER_NUMBER=123456
ESTAFETA_ENVIRONMENT=production
```

- `ESTAFETA_USER` — your login username for the Estafeta eCommerce portal
- `ESTAFETA_PASSWORD` — corresponding password
- `ESTAFETA_CUSTOMER_NUMBER` — numeric `idusuario` assigned by Estafeta (shown as "Número de cliente" in the portal)
- `ESTAFETA_ENVIRONMENT` — set to `production` for live traffic, `sandbox` for testing (the provider will point at the QA WSDL when this is anything other than `production`)

**Where to get credentials:** Log into the [Estafeta eCommerce portal](https://ecommerce.estafeta.com). Under your account settings you will find your customer number and can manage API credentials.

**How to test:** Send a POST request to `shipping-quotes.php`:

```bash
curl -s -X POST https://yourdomain.com/shipping-quotes.php \
  -H "Cookie: PHPSESSID=<valid_session>" \
  -d "destination_postal=06600&weight=1&length=20&width=15&height=10&_csrf=<token>"
```

A successful response returns a JSON array of quote objects. Each object includes `carrier: "estafeta"`, a `service_name`, `price` in MXN, and `transit_days`. If the credentials are wrong or the SOAP call fails, Estafeta returns an empty array (not an error); the response will contain only the fallback flat-rate or DHL quotes.

---

## Updating DHL Credentials

**Keys to change in `.env`:**

```
DHL_API_KEY=PICXXXXXX
DHL_API_SECRET=your_api_secret
DHL_ACCOUNT_NUMBER=123456789
DHL_ENVIRONMENT=production
```

- `DHL_API_KEY` — MyDHL API username, always formatted `PICXXXXXX` (eight alphanumeric characters after "PIC")
- `DHL_API_SECRET` — API password for that key
- `DHL_ACCOUNT_NUMBER` — your 9-digit DHL Express account number
- `DHL_ENVIRONMENT` — set to `production` for live traffic, `sandbox` for testing (the provider switches between `https://express.api.dhl.com/mydhlapi/test` and `https://express.api.dhl.com/mydhlapi`)

**Where to get credentials:** Log into the [MyDHL API portal](https://developer.dhl.com). Under "My Apps" you will find your API key and secret. Your account number is on your DHL Express contract or invoices.

**How to test:** Same curl command as Estafeta above. A successful DHL response includes objects with `carrier: "dhl"` and `service_name` values such as "Domestic Express" or "Express Worldwide". If the credentials are wrong, DHL returns an empty array and checkout falls back to flat rates.

---

## Enabling and Disabling a Carrier

Change the `SHIPPING_PROVIDERS` value in `.env`. No code change or redeploy needed — PHP reads `.env` on each request.

```
# Both carriers active (default)
SHIPPING_PROVIDERS=estafeta,dhl

# Estafeta only
SHIPPING_PROVIDERS=estafeta

# DHL only
SHIPPING_PROVIDERS=dhl
```

Removing a carrier name from this list means `ShippingService` will not instantiate that provider. Quotes from that carrier will not appear at checkout.

---

## Adding a Third Carrier

Follow these four steps:

**1. Create the provider class**

Create `includes/shipping/NewCarrierProvider.php`. The class must implement the `ShippingProvider` interface:

```php
<?php
require_once __DIR__ . '/ShippingProvider.php';

class NewCarrierProvider implements ShippingProvider {

    public function __construct() {
        // Read credentials from .env using env()
    }

    public function getQuotes(array $params): array {
        // $params keys: origin_postal, destination_postal,
        //               weight, length, width, height
        // Return array of quote arrays, each with:
        //   carrier, service_code, service_name, price (MXN float),
        //   currency ('MXN'), transit_days (int), carrier_label
        // Return [] if the carrier cannot service this route or is misconfigured.
    }

    public function createShipment(array $orderData): array {
        // Return ['success' => bool, 'tracking_number' => string,
        //         'label_url' => string, 'error' => string]
    }

    public function getTracking(string $trackingNumber): array {
        // Return ['status' => string, 'location' => string,
        //         'timestamp' => string, 'events' => array]
    }
}
```

**2. Add credentials to `.env`**

```
NEWCARRIER_API_KEY=...
NEWCARRIER_API_SECRET=...
```

**3. Register the carrier in `ShippingService.php`**

Open `includes/shipping/ShippingService.php`. Add a `require_once` at the top and an `elseif` (or additional `case`) inside the provider loading loop:

```php
require_once __DIR__ . '/NewCarrierProvider.php';
```

Then in the `foreach` / `switch` block (around line 21):

```php
case 'newcarrier':
    $this->providers['newcarrier'] = new NewCarrierProvider();
    break;
```

**4. Activate the carrier in `.env`**

```
SHIPPING_PROVIDERS=estafeta,dhl,newcarrier
```

That is all. The `getQuotes()` loop in `ShippingService` will call the new provider automatically alongside the existing ones.

---

## Warehouse Origin Postal Code

```
WAREHOUSE_POSTAL_CODE=06600
```

This is the shipment origin used for all rate queries. It must be a valid 5-digit Mexico postal code (CP). `ShippingService` defaults to `06600` if this key is absent.

Change this when the fulfillment warehouse location changes. All carriers use the same origin; there is no per-carrier origin override.

---

## Fallback Rates

If all configured carriers fail to return quotes — because credentials are missing, the carrier API is unreachable, or the route is not serviceable — `ShippingService` falls back to flat rates:

| Weight | Rate |
|--------|------|
| 5 kg or under | MXN 150 |
| Over 5 kg | MXN 250 |

The fallback option is labeled "Envío Estándar (3–5 días hábiles)" and appears at checkout as a standard service option. This is expected behavior, not an error condition. Check `logs/` or your server error log for carrier-specific errors if you want to diagnose why live rates were not returned.
