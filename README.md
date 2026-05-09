# VentDepot — Construction Materials Marketplace

A full-stack PHP marketplace for buying and selling construction materials in Mexico. Supports customers, merchants, and admins with real payment processing, live shipping rate quotes, escrow/buyer protection, and a full accounting suite.

**Stack:** PHP 8.1 · MySQL 8 · Apache 2.4 · Tailwind CSS · Alpine.js · PHPUnit

**Server:** Linode `198.58.124.137` → `/var/www/html`

---

## Table of Contents

1. [What Was Built Originally](#1-what-was-built-originally)
2. [What We Added](#2-what-we-added)
3. [Architecture Overview](#3-architecture-overview)
4. [Directory Structure](#4-directory-structure)
5. [Database & Migrations](#5-database--migrations)
6. [Payment Providers](#6-payment-providers)
7. [Shipping Providers](#7-shipping-providers)
8. [Security](#8-security)
9. [Environment Setup](#9-environment-setup)
10. [First-Time Setup](#10-first-time-setup)
11. [Deployment](#11-deployment)
12. [Testing](#12-testing)
13. [What Is Still Missing / To Do](#13-what-is-still-missing--to-do)

---

## 1. What Was Built Originally

Farida's original codebase (`bf07c3e`) delivered a working PHP marketplace with:

**Customer-facing:**
- Homepage with hero banner, category grid, featured products
- Product detail pages with image gallery, reviews, 5-star ratings
- Shopping cart (session-based)
- Checkout with mocked payment (Stripe placeholder)
- Order confirmation and tracking pages
- Account management, address book, messaging with merchants

**Merchant portal (`/merchant/`):**
- Merchant application and approval workflow
- Product management (add, edit, list, image upload)
- Sales dashboard with Chart.js graphs
- Commission tracking and payout reports

**Admin panel (`/admin/`):**
- Full user management (customers, merchants, admins)
- Order management and refund handling
- Financial reports, C-level dashboard, accounting system
- CMS for homepage banners and content blocks
- Inventory management and supplier tracking
- SEO management

**Infrastructure:**
- MySQL schema across 6 migration files (001–006)
- Escrow/buyer protection system (`EscrowSystem.php`)
- Two-factor authentication (`TwoFactorAuth.php`)
- Commission management (`CommissionManagementSystem.php`)
- Inventory manager (`InventoryManager.php`)
- Messaging and notification systems
- Deployment scripts for Linode (bash + PowerShell + batch)
- CMS frontend system (`CMSFrontend.php`)

---

## 2. What We Added

Four commits were added to `main` and merged into the `start` branch:

### Real Shipping Rate Integration (`dd6e8b2`)

Replaced hardcoded flat-rate shipping with **live carrier API quotes**.

**New files:**
- `includes/shipping/ShippingProvider.php` — provider interface
- `includes/shipping/ShippingService.php` — factory/selector, reads `SHIPPING_PROVIDERS` from `.env`
- `includes/shipping/EstafetaProvider.php` — Estafeta Mexico rate quotes
- `includes/shipping/DhlMexicoProvider.php` — DHL Mexico rate quotes
- `includes/Mailer.php` — PHPMailer wrapper for transactional emails
- `config/bootstrap.php` — centralised `.env` loading, session config, CSRF helpers, TAX_RATE constant
- `shipping-quotes.php` — AJAX endpoint that returns carrier rate options for a given postal code

**Changes to existing files:**
- `checkout.php` — collects Mexico postal code, fetches live rates via AJAX, stores chosen carrier/service on order
- `includes/Mailer.php` — sends order confirmation, password reset, and merchant status emails
- Migrations `007` (2FA trusted devices) and `008` (carrier/tracking columns on orders)

**New operations docs:** `CARRIERS.md`, `DEPLOY.md`, `OPERATIONS.md`, `SETUP.md`

---

### Provider-Based Payment Abstraction (`e08c328`)

Replaced the monolithic `PaymentGateway.php` with a **provider pattern** — swap payment processors without touching business logic.

**New files:**
- `includes/payments/PaymentProvider.php` — interface (charge, refund, getFrontendConfig)
- `includes/payments/PaymentService.php` — factory that reads `PAYMENT_PROVIDER` from `.env`
- `includes/payments/StripeProvider.php` — Stripe implementation
- `includes/payments/PayPalProvider.php` — PayPal implementation
- `includes/payments/MercadoPagoProvider.php` — Mercado Pago + **OXXO cash payments** (Mexico)

**How to switch providers:** Change `PAYMENT_PROVIDER=stripe` to `paypal` or `mercadopago` in `.env`. No code changes required. Refunds automatically route to the original provider.

---

### Construction Marketplace Pivot (`4cb2817`)

Reoriented VentDepot from a generic e-commerce template to a **Mexico construction materials marketplace**.

**What changed:**
- Migration `009` deactivates generic categories (electronics, clothing) and upserts 7 construction verticals: Construcción, Herramientas, Eléctrico, Plomería, Seguridad Industrial, Acabados, Ferretería General
- `unit_of_measure` column added to products table (m², kg, pieza, litro, rollo, etc.)
- `product_pricing_tiers` table — quantity-based bulk pricing for B2B orders
- Homepage now queries real DB categories with Font Awesome icons (not hardcoded)
- Product pages show unit-of-measure labels and bulk pricing tables
- Merchant add/edit product forms expose unit_of_measure field and dynamic tier builder
- `deploy.sh` hardened: quoted heredoc, `set -e`, runs all migrations, locks `.env` to 640 permissions

---

### Security Hardening (`0352fce`)

Comprehensive security pass across the entire codebase.

**What was fixed:**

| Area | Change |
|------|--------|
| CSRF | Global enforcement via `bootstrap.php`; `navigation.php` auto-injects tokens into all forms; API/webhooks exempted |
| Sessions | Secure/HttpOnly/SameSite=Strict cookie params; strict mode; 1-hour GC lifetime |
| Open redirect | `Security::validateRedirect()` whitelist-only; rejects traversal, protocol-relative, absolute URLs |
| CORS | Replaced wildcard `Access-Control-Allow-Origin: *` with `APP_URL`-bound origin check |
| JWT | Removed `default_secret` fallback — throws if `JWT_SECRET` unset |
| Image upload | Extension derived from `mime_content_type()`, not user-supplied filename |
| Stock check | `SELECT ... FOR UPDATE` before order insert; atomic decrement prevents overselling |
| Encryption | `ENCRYPTION_KEY` must be ≥ 32 chars; throws on weak key |
| PaymentGateway | Raw exception messages no longer surfaced to client |
| Email enumeration | Register/login return generic errors to prevent username discovery |
| PDO | Emulated prepares disabled for true parameterised queries |
| Password reset | New `forgot-password.php` / `reset-password.php` with rate-limited, token-hashed, expiry-enforced flow |
| `.htaccess` | Deny directory listing, block `.env`/`.git`/sensitive files, add CSP/HSTS/X-Frame-Options headers |

**New files:** `forgot-password.php`, `reset-password.php`, `includes/PasswordReset.php`, `includes/security.php`, `.htaccess`, `phpunit.xml`, `tests/`

**Test suite (PHPUnit):**
- `tests/SecurityTest.php`
- `tests/PasswordResetTest.php`
- `tests/AdvancedSecurityTest.php`
- `tests/OpenRedirectTest.php`
- `tests/PaymentServiceTest.php`
- `tests/ShippingServiceTest.php`
- `tests/MailerTest.php`

---

## 3. Architecture Overview

```
Browser
  │
  ├── config/bootstrap.php      ← Loaded first. Env, session, CSRF helpers, TAX_RATE
  ├── config/database.php       ← PDO connection (reads DB_* from .env)
  │
  ├── index.php                 ← Homepage (CMS + DB categories)
  ├── product.php               ← Product detail, reviews, add to cart
  ├── checkout.php              ← Cart → address → carrier selection → payment
  ├── cart.php / api/cart.php   ← Cart page + AJAX cart API
  │
  ├── includes/
  │   ├── payments/             ← Payment provider abstraction
  │   │   ├── PaymentService.php      ← Factory (reads PAYMENT_PROVIDER env var)
  │   │   ├── StripeProvider.php
  │   │   ├── PayPalProvider.php
  │   │   └── MercadoPagoProvider.php
  │   │
  │   ├── shipping/             ← Shipping provider abstraction
  │   │   ├── ShippingService.php     ← Factory (reads SHIPPING_PROVIDERS env var)
  │   │   ├── EstafetaProvider.php
  │   │   └── DhlMexicoProvider.php
  │   │
  │   ├── PaymentGateway.php    ← Orchestration: DB transaction, escrow, commissions
  │   ├── OrderProcessor.php    ← Order creation and processing
  │   ├── EscrowSystem.php      ← Buyer protection, fund holds, disputes
  │   ├── CommissionManagementSystem.php
  │   ├── InventoryManager.php
  │   ├── Mailer.php            ← PHPMailer wrapper (order confirm, password reset, etc.)
  │   ├── PasswordReset.php     ← Token generation, expiry, hashing
  │   ├── AdvancedSecurity.php  ← Encryption, key management
  │   ├── security.php          ← CSRF, rate limiting, input validation, open redirect
  │   └── TwoFactorAuth.php
  │
  ├── admin/                    ← Admin panel (60+ pages)
  ├── merchant/                 ← Merchant portal
  ├── webhooks/                 ← Payment/shipping provider callbacks
  ├── migrations/               ← 11 numbered SQL migrations (run in order)
  └── tests/                    ← PHPUnit test suite
```

---

## 4. Directory Structure

```
/
├── admin/                  Admin panel
│   ├── api/                Admin AJAX endpoints (accounting, etc.)
│   ├── includes/           Admin sidebar include
│   └── *.php               Dashboard, orders, products, users, reports, CMS...
├── api/                    Public AJAX API (cart, search, shipping quotes)
├── config/
│   ├── bootstrap.php       App bootstrap (load .env, sessions, CSRF)
│   └── database.php        PDO connection
├── includes/
│   ├── payments/           Payment provider classes
│   └── shipping/           Shipping provider classes
├── merchant/               Merchant portal
├── migrations/             SQL migration files (001–011)
├── tests/                  PHPUnit tests
├── webhooks/               Payment/shipping webhooks
├── .env.example            Environment variable template ← START HERE
├── .htaccess               Apache security rules, CSP/HSTS headers
├── deploy.sh               Deployment script (Linux/macOS)
├── phpunit.xml             PHPUnit configuration
└── SETUP.md / DEPLOY.md / CARRIERS.md / PAYMENTS.md / OPERATIONS.md
```

---

## 5. Database & Migrations

11 numbered migrations must be run **in order** on a fresh database:

| File | What it creates |
|------|----------------|
| `001_initial_schema.sql` | users, addresses, categories, products, orders, reviews, merchant_applications, shipping |
| `002_homepage_components.sql` | CMS banners, featured products, hero sections |
| `003_fixed_marketplace_schema.sql` | marketplace transactions, disputes, payments, commissions, escrow |
| `004_engineering_task_management.sql` | Internal engineering dashboard tables |
| `005_user_profiles.sql` | Extended user profile fields |
| `006_chat_system.sql` | Conversation/message tables |
| `007_two_factor_trusted_devices.sql` | 2FA tokens and trusted device storage |
| `008_orders_shipping_columns.sql` | `carrier`, `shipping_service`, `tracking_number` on orders |
| `009_construction_setup.sql` | 7 construction categories, `unit_of_measure` on products, `product_pricing_tiers` table |
| `010_password_reset_tokens.sql` | Password reset token storage with expiry |
| `011_add_orders_subtotal.sql` | `subtotal` column on orders |

**Optional migrations** (run only if those features are needed):
- `add_currency_support.sql`
- `c_level_reporting_schema.sql`
- `cms_frontend_schema.sql`
- `credit_management_schema.sql`
- `enhance_tax_management.sql`

**Run all at once:**
```bash
for f in migrations/0*.sql; do mysql -u USER -p DATABASE < "$f"; done
```

---

## 6. Payment Providers

Controlled by `PAYMENT_PROVIDER` in `.env`. No code changes needed to switch.

| Provider | env value | Notes |
|----------|-----------|-------|
| Stripe | `stripe` | Default. Test keys start with `pk_test_` / `sk_test_` |
| PayPal | `paypal` | Set `PAYPAL_MODE=sandbox` for testing |
| Mercado Pago | `mercadopago` | Supports OXXO cash payments. Test tokens start with `TEST-` |

Refunds are automatically routed to whichever provider processed the original transaction, regardless of what `PAYMENT_PROVIDER` is currently set to.

See `PAYMENTS.md` for credential setup.

---

## 7. Shipping Providers

Controlled by `SHIPPING_PROVIDERS` in `.env` (comma-separated list). Checkout fetches live rate quotes via AJAX using the customer's postal code.

| Provider | env value | Notes |
|----------|-----------|-------|
| Estafeta | `estafeta` | Mexico's largest domestic carrier |
| DHL Mexico | `dhl` | International + domestic |

Set `ESTAFETA_ENVIRONMENT=sandbox` and `DHL_ENVIRONMENT=sandbox` for testing.

See `CARRIERS.md` for credential setup.

---

## 8. Security

All of the following are active:

- **CSRF** — every non-GET form request validated. Token auto-injected by `navigation.php`.
- **Sessions** — HttpOnly, Secure, SameSite=Strict, strict mode, 1-hour lifetime.
- **Password reset** — rate-limited, hashed token, 1-hour expiry. See `forgot-password.php`.
- **2FA** — TOTP-based, with trusted device support (migration 007).
- **Open redirect** — whitelist only. `Security::validateRedirect()` blocks all external redirects.
- **Image upload** — MIME type validated server-side, not from filename extension.
- **Stock atomicity** — `SELECT ... FOR UPDATE` prevents race conditions on checkout.
- **ENCRYPTION_KEY** — must be ≥ 32 chars. App throws on startup if weak or missing.
- **JWT_SECRET** — throws if unset. No default fallback.
- **`.htaccess`** — blocks `.env`, `.git`, sensitive directories; sets CSP, HSTS, X-Frame-Options.

---

## 9. Environment Setup

Copy `.env.example` to `.env` and fill in all values:

```bash
cp .env.example .env
```

**Minimum required to run locally:**

```env
APP_URL=http://localhost
APP_DEBUG=true

DB_HOST=127.0.0.1
DB_DATABASE=ventdepot
DB_USERNAME=root
DB_PASSWORD=yourpassword

PAYMENT_PROVIDER=stripe
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...

SHIPPING_PROVIDERS=estafeta
ESTAFETA_CUSTOMER_NUMBER=...
ESTAFETA_USER=...
ESTAFETA_PASSWORD=...
ESTAFETA_ENVIRONMENT=sandbox

ENCRYPTION_KEY=at_least_32_characters_long_random_string
JWT_SECRET=another_random_secret

MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@ventdepot.com
MAIL_FROM_NAME=VentDepot
```

> **ENCRYPTION_KEY must be at least 32 characters or the app will throw on startup.**

---

## 10. First-Time Setup

```bash
# 1. Install PHP dependencies
composer install

# 2. Copy environment file and fill in values
cp .env.example .env
nano .env

# 3. Create database
mysql -u root -p -e "CREATE DATABASE ventdepot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Run all migrations in order
for f in migrations/0*.sql; do
    echo "Running $f..."
    mysql -u root -p ventdepot < "$f"
done

# 5. Seed dummy data (optional but recommended for testing)
php includes/dummy_data.php

# 6. Create first admin user
mysql -u root -p ventdepot -e "
  INSERT INTO users (name, email, password, role, status)
  VALUES ('Admin', 'admin@ventdepot.com', '$(php -r "echo password_hash(\"changeme\", PASSWORD_DEFAULT);")', 'admin', 'active');
"

# 7. Start local server (or configure Apache vhost per SETUP.md)
php -S localhost:8000
```

---

## 11. Deployment

The deploy script handles packaging, upload, extraction, migrations, and Apache reload:

```bash
./deploy.sh
```

It will:
1. Package all files (excluding `.git`, `.env`, deployment scripts)
2. SCP the archive to `198.58.124.137`
3. Extract to `/var/www/html`
4. Run all `migrations/0*.sql` files
5. Set `.env` permissions to 640
6. Reload Apache

**Prerequisites:**
- SSH key configured for the Linode server
- `.env` already exists on the server at `/var/www/html/.env`

See `DEPLOY.md` for full details including rollback procedure, backup cron, and troubleshooting.

---

## 12. Testing

```bash
# Install test dependencies (included in composer.json)
composer install

# Run the full test suite
./vendor/bin/phpunit --testdox

# Run a specific test class
./vendor/bin/phpunit tests/PaymentServiceTest.php
```

**Test coverage:**

| Test file | What it covers |
|-----------|----------------|
| `SecurityTest.php` | CSRF validation, rate limiting, input sanitization |
| `PasswordResetTest.php` | Token generation, expiry, rate limiting |
| `AdvancedSecurityTest.php` | Encryption key validation, AES-256 encrypt/decrypt |
| `OpenRedirectTest.php` | Redirect whitelist enforcement |
| `PaymentServiceTest.php` | Provider factory, charge/refund routing |
| `ShippingServiceTest.php` | Provider selection, rate quote parsing |
| `MailerTest.php` | Email composition, SMTP handoff |

---

## 13. What Is Still Missing / To Do

### Blocking (must fix before go-live)

- [ ] **`.env` on production server** — nothing runs without it. Get real API keys for Estafeta, Stripe (or chosen provider), SMTP.
- [ ] **Run all 11 migrations on the production DB** — verify each one completes cleanly.
- [ ] **End-to-end checkout test** — add product → cart → address → shipping quote → payment (sandbox) → order confirmation email. This path touches `OrderProcessor`, `PaymentGateway`, `ShippingService`, and `Mailer` together.
- [ ] **Admin user creation** — no admin account exists on a fresh install. Run the SQL above or add a proper seeder.

### Important (should fix before go-live)

- [ ] **Product images / storage** — image uploads go to the path in `STORAGE_ROOT`. This directory must exist on the server and Apache must be configured to serve it. Storefront will look broken without real product images.
- [ ] **Composer install on server** — `vendor/` is not in the repo. `composer install --no-dev` must be run on the server.
- [ ] **Verify webhooks** — Stripe/PayPal/MercadoPago webhooks are in `/webhooks/`. Each provider's dashboard must be configured to point to `https://yourdomain.com/webhooks/stripe.php` etc. OXXO payments (MercadoPago) are async and only complete via webhook.
- [ ] **HTTPS** — `.htaccess` sets HSTS. Server must have TLS configured (Let's Encrypt via SETUP.md) before going live.
- [ ] **Optional migrations** — decide which of the 5 optional schema files apply and run them: `cms_frontend_schema.sql` is likely required for the CMS admin to work.

### Nice to have

- [ ] **Seed data** — `includes/dummy_data.php` exists but has no CLI entry point. Wrap it in a runnable script.
- [ ] **Cron jobs** — `analytics-cron.php` and escrow cron need to be registered (see `SETUP.md`).
- [ ] **Redis** — optional but the accounting dashboard uses it for caching. Fine to skip initially.
- [ ] **Rate limiting on API endpoints** — currently only on login/register. Cart and shipping-quotes endpoints are unprotected.
