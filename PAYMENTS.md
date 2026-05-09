# VentDepot — Payment Gateway Operations Guide

## Current Providers

Three payment providers are configured:

- **Stripe** — credit and debit cards
- **PayPal** — credit/debit cards via PayPal checkout
- **Mercado Pago** — cards and OXXO cash payments

All credentials are set in `.env`. PHP reads `.env` on every request, so no redeploy is needed after a credential or provider change.

---

## Switching the Active Provider

Change one line in `.env`:

```
PAYMENT_PROVIDER=stripe
```

Valid values: `stripe`, `paypal`, `mercadopago`. The `PaymentService` factory reads this key and returns the matching provider. The change takes effect immediately on the next page load — no server restart or deploy needed.

---

## Provider Capabilities

| Provider | Cards | OXXO Cash | Bank Transfer | Sandbox Mode |
|---|---|---|---|---|
| Stripe | Yes | No | No | Yes (`sk_test_` / `pk_test_` keys) |
| PayPal | Yes | No | No | Yes (`PAYPAL_MODE=sandbox`) |
| Mercado Pago | Yes | Yes | No | Yes (`TEST-` prefix on tokens) |

---

## Updating Stripe Credentials

**Keys in `.env`:**

```
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
```

- `STRIPE_KEY` — publishable key, used client-side by Stripe.js
- `STRIPE_SECRET` — secret key, used server-side for API calls (never exposed to the browser)
- Currency is always **MXN** and is hardcoded in `StripeProvider.php`; no env key for it

**Where to find them:** Log into the [Stripe Dashboard](https://dashboard.stripe.com). Go to **Developers → API keys**. "Publishable key" maps to `STRIPE_KEY`; "Secret key" maps to `STRIPE_SECRET`. Use keys prefixed `pk_test_` / `sk_test_` for sandbox testing.

---

## Updating PayPal Credentials

**Keys in `.env`:**

```
PAYPAL_CLIENT_ID=...
PAYPAL_SECRET=...
PAYPAL_MODE=sandbox
```

- `PAYPAL_CLIENT_ID` — OAuth client ID from the PayPal developer portal
- `PAYPAL_SECRET` — OAuth client secret
- `PAYPAL_MODE` — `sandbox` routes API calls to `https://api.sandbox.paypal.com`; `live` routes to `https://api.paypal.com`

**Where to find them:** Log into the [PayPal Developer Dashboard](https://developer.paypal.com). Under **My Apps & Credentials**, select or create an app. The client ID and secret are shown on the app detail page. Sandbox and live apps have separate credentials.

---

## PayPal Webhook Signature Verification

VentDepot verifies PayPal webhook signatures before processing any event. This requires one additional credential:

**Key in `.env`:**
```
PAYPAL_WEBHOOK_ID=your_paypal_webhook_id
```

**How to get it:**
1. Log into the [PayPal Developer Dashboard](https://developer.paypal.com)
2. Go to **My Apps & Credentials** → select your app
3. Scroll to **Webhooks** → click **Add Webhook**
4. Set the URL to `https://yourdomain.com/webhooks/payment-webhook.php?gateway=paypal`
5. Subscribe to at least: `CHECKOUT.ORDER.APPROVED`, `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`
6. After saving, copy the **Webhook ID** shown on the webhook detail page into `PAYPAL_WEBHOOK_ID`

If `PAYPAL_WEBHOOK_ID` is empty, incoming PayPal webhooks are acknowledged (HTTP 200) but not processed. If it is set but the signature fails, the request is rejected with HTTP 401 and logged as a security event.

Sandbox webhooks can be simulated from the PayPal developer dashboard under **Webhooks → Simulate**.

---

## Updating Mercado Pago Credentials

**Keys in `.env`:**

```
MP_ACCESS_TOKEN=TEST-...
MP_PUBLIC_KEY=TEST-...
```

- `MP_ACCESS_TOKEN` — server-side access token used for API calls. Prefixed `TEST-` in sandbox, `APP_USR-` in production.
- `MP_PUBLIC_KEY` — client-side public key used by the Mercado Pago JS SDK in the browser.

**Where to find them:** Log into the [Mercado Pago Developer Dashboard](https://www.mercadopago.com.mx/developers). Under **Mis aplicaciones → Credenciales**, toggle between "Credenciales de prueba" and "Credenciales de producción" to find the correct pair. Never use sandbox tokens in production or vice versa.

---

## OXXO Cash Payments

OXXO is a Mercado Pago feature. When a customer selects OXXO at checkout:

1. Mercado Pago returns a `pending` payment status along with a `voucher_url`.
2. The order is saved with status **pending** in VentDepot's orders table. This is normal and expected — do not treat it as a failed payment.
3. The customer receives the voucher URL and pays at any OXXO store within the voucher's expiry window (typically 3 days).
4. After the payment is confirmed, Mercado Pago sends a webhook notification.

**Webhook handler:** `webhooks/payment-webhook.php`. If the webhook is not yet wired for Mercado Pago notifications, check the [Mercado Pago dashboard](https://www.mercadopago.com.mx/developers/panel/notifications) under **Notificaciones** to confirm payment manually, then update the order status in Admin → Orders.

Until the OXXO payment clears, do not ship the order.

---

## Refunds

Go to **Admin → Orders**, open the order, and click **Refund**.

The system reads the `gateway_reference` and original payment provider stored on the transaction record and routes the refund call to the correct provider automatically. You do not need to know which provider was used.

- **Stripe refunds** — processed via `/v1/refunds`, typically instant.
- **PayPal refunds** — processed via `/v2/payments/captures/{id}/refund`.
- **Mercado Pago refunds** — processed via `/v1/payments/{id}/refunds`. OXXO payments that have been confirmed can be refunded the same way.

Partial refunds are supported: enter the amount in the refund dialog.

---

## Platform Fee

The platform fee is **2.9%** of the order total. It is deducted from the amount settled to the vendor and is applied uniformly across all three providers.

The rate is defined as a constant in each provider class:

```
includes/payments/StripeProvider.php       PLATFORM_FEE_RATE = 0.029
includes/payments/PayPalProvider.php       PLATFORM_FEE_RATE = 0.029
includes/payments/MercadoPagoProvider.php  PLATFORM_FEE_RATE = 0.029
```

To change the fee rate, update the constant in each of the three files. All three must be changed to keep fees consistent across providers.
