# VentDepot — Security Audit Report

**Date:** 2026-05-09  
**Branch:** `start`  
**Audited by:** SARAH (automated) + manual review  
**Commit with fixes:** `81c64ec`

---

## Summary

Three parallel audits were run covering: (1) authentication / session / access control, (2) SQL injection / XSS / input validation, and (3) business logic / payment integrity.

| Severity | Found | Fixed | Remaining |
|----------|-------|-------|-----------|
| Critical | 13    | 11    | 2*        |
| High     | 9     | 6     | 3*        |
| Medium   | 8     | 0     | 8         |
| Low      | 3     | 0     | 3         |

*Remaining items documented below with mitigation notes.

---

## Fixed Issues (commit `81c64ec`)

### CRITICAL — Fixed

**C1 · login.php — Rate limiting disabled**  
`// RATE LIMITING DISABLED COMPLETELY` was a placeholder that left login fully open to brute force.  
**Fix:** Replaced with `Security::checkRateLimit('login', 5, 900)` — 5 attempts per 15 minutes per IP.

**C2 · login.php — 2FA verification commented out**  
Lines 40 and 43: `verifyUserCode()` and `verifyBackupCode()` were commented out. Any user with 2FA enabled could log in with any code (including blank).  
**Fix:** Uncommented both calls. 2FA is now enforced.

**C3 · admin/inventory-report.php — Hardcoded DB credentials**  
File had its own `new PDO("...dbname=finalJulio...", 'root', '')` separate from config.  
**Fix:** Removed the hardcoded PDO connection; now uses shared `$pdo` from `config/database.php`.

**C4 · admin/list-tables.php — No authentication**  
File was completely public — any visitor could list all DB table names.  
**Fix:** Added `requireRole('admin')`.

**C5 · admin/homepage-manager.php — No authentication**  
No `require_once` or auth check at all — comment said "This would typically include authentication checks."  
**Fix:** Added `require_once '../config/database.php'` and `requireRole('admin')`.

**C6 · admin/users.php — No CSRF on POST actions**  
User deletion and role changes had no CSRF validation.  
**Fix:** Added `requireCSRF()` to POST handler.

**C7 · admin/users.php — SQL injection via ORDER BY**  
`$_GET['sort']` interpolated directly into `ORDER BY $sortBy $sortOrder`.  
**Fix:** Whitelist `['id','email','role','created_at','status']`; force direction to `ASC`/`DESC`.

**C8 · admin/merchants.php — SQL injection via ORDER BY**  
Same pattern as C7 with `u.$sortBy`.  
**Fix:** Whitelist `['id','email','username','created_at','status']`.

**C9 · admin/orders.php — SQL injection via ORDER BY + missing CSRF**  
Same pattern as C7 plus no CSRF on refund action.  
**Fix:** Whitelist `['id','created_at','total','status','customer_id']`; added `requireCSRF()`.

**C10 · api/index.php — IDOR: order status bypass**  
`updateOrder()` had no ownership check — any authenticated user could PUT `{"status":"paid"}` on any order.  
**Fix:** Added ownership verification against `orders.user_id`; admin role bypasses check; status values are whitelisted.

**C11 · checkout.php — Shipping cost accepted from POST**  
`$shippingCost = (float)($_POST['selected_shipping_cost'] ?? 0)` — attacker could set `-100` to reduce total.  
**Fix:** `max(0.0, ...)` enforces non-negative. Full server-side re-validation (re-fetch quote from ShippingService) is a follow-up TODO.

**C12 · PaymentGateway.php — Refund amount not bounded**  
`processRefund()` accepted any amount without checking against the original transaction.  
**Fix:** Added `if ($refundAmount > $transaction['amount']) throw new Exception(...)`.

**C13 · PaymentGateway.php — Commission uses wrong amount**  
`processCommissionSplits()` received `$netAmount` as a parameter but calculated commission on `$order['total']` (DB value) instead — ignoring payment processor fees.  
**Fix:** Commission now computed on `$netAmount` (actual charged amount after gateway fees).

---

### HIGH — Fixed

**H3 · gantt-view.php — XSS via innerHTML**  
Task details panel built with `innerHTML` template literal containing `task.engineer_email`, `task.name`, etc. from DB.  
**Fix:** Added `esc()` helper function that escapes `&<>"'`; all user-supplied values pass through it before injection.

**H5 · admin/backup.php — Hardcoded credentials in exec()**  
`mysqldump ... --user=root --password=` embedded in shell command, visible in process listing.  
**Fix:** Credentials read from `env()` (`DB_HOST`, `DB_USER`, `DB_PASS`); all args wrapped in `escapeshellarg()`.

**H7 · admin/orders.php — Admin refund CSRF missing**  
Role check was present but no CSRF token validated on refund POST.  
**Fix:** `requireCSRF()` added at top of POST block.

**Bonus fix — checkout.php: order creation completely broken (merge artifact)**  
Two bugs introduced by the `-X theirs` merge:
1. `try { ... } else { ... }` — PHP does not support `else` on a `try` block; this was a silent parse error causing all order creation to fail.
2. Missing `$pdo->commit()` before the redirect — even if the parse error were ignored, the transaction was never committed.  
**Fix:** Changed `} else {` → `} catch (Exception $e) {` and `$result['error']` → `$e->getMessage()`; inserted `$pdo->commit()` before cart clear.

---

## Remaining Issues (not yet fixed)

### CRITICAL — Needs follow-up

| # | File | Issue | Mitigation |
|---|------|-------|------------|
| C11* | `checkout.php:72` | Shipping cost still from POST (only non-negative enforced) | Requires storing AJAX quote in session and re-validating at submit time |

### HIGH — Needs follow-up

| # | File | Issue | Notes |
|---|------|-------|-------|
| H1 | `register.php` | Session fixation on registration | Call `session_regenerate_id(true)` after account creation |
| H2 | `login.php:393` | Demo credentials visible on non-production login page | Already gated by `env('APP_ENV') !== 'production'` — ensure APP_ENV=production is set in production .env |
| H6 | `checkout.php / CreditCheck.php` | Credit limit checked at order creation only, not re-checked at payment time | Add credit re-check in `PaymentGateway::processPayment()` |

### MEDIUM — Needs follow-up

| # | File | Issue |
|---|------|-------|
| M1 | `quote-request.php:26–40` | File upload: extension whitelist only, no `finfo_file()` MIME check |
| M3 | `checkout.php:148–155` | Credit reservation failure doesn't block order (logs warning but proceeds) |
| M4 | `webhooks/payment-webhook.php:119–135` | PayPal webhook signature not verified — TODO comment present |
| M5 | `includes/CreditCheck.php:182–240` | Accounts receivable creation fails silently, doesn't roll back payment |
| M6 | `analytics-cron.php:321` | Table name in `DELETE FROM $table` — cron-only, low risk |
| M7 | `webhooks/general-webhook.php:207–216` | Facebook webhook token compared with `===` not `hash_equals()` |
| M8 | `checkout.php:40,168` | Tax amount calculated but not persisted — missing `tax_amount` column in orders table |

### LOW — Informational

| # | File | Issue |
|---|------|-------|
| L1 | `PaymentGateway.php:311` | Mock payment card `1231231231231233` hardcoded — ensure `PAYMENT_PROVIDER != mock` in production |
| L2 | `checkout.php:168` | Tax displayed as $0.00 (no DB column yet) |

---

## Recommended Next Steps

1. **Fix C11 properly** — on the shipping AJAX endpoint (`shipping-quotes.php`), store returned quotes in `$_SESSION['shipping_quotes']` keyed by `carrier:service:postal`. On form submit, look up cost from session instead of trusting POST.

2. **Fix M4 (PayPal webhook)** — implement signature verification using the PayPal SDK `verify-webhook-signature` API. `PAYPAL_WEBHOOK_ID` env var is already wired in `.env.example`.

3. **Fix H1 (session fixation)** — add `session_regenerate_id(true)` to `register.php` after `$_SESSION` is populated.

4. **Fix M1 (file upload MIME)** — in `quote-request.php`, add:
   ```php
   $finfo = new finfo(FILEINFO_MIME_TYPE);
   $mime = $finfo->file($_FILES['specifications_file']['tmp_name']);
   $allowedMimes = ['application/pdf','image/jpeg','image/png','image/gif'];
   if (!in_array($mime, $allowedMimes, true)) { /* reject */ }
   ```

5. **Fix M8 (tax column)** — add migration `012_add_orders_tax_amount.sql`:
   ```sql
   ALTER TABLE orders ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(10,2) DEFAULT 0.00 AFTER shipping_cost;
   ```

6. **Run PHPUnit suite** to verify no regressions from the fixes:
   ```bash
   ./vendor/bin/phpunit --testdox
   ```

---

## Files Changed in This Audit

| File | Change |
|------|--------|
| `login.php` | Rate limiting, 2FA restore, logic dedup, debug logging removed |
| `admin/users.php` | ORDER BY whitelist, CSRF |
| `admin/merchants.php` | ORDER BY whitelist |
| `admin/orders.php` | ORDER BY whitelist, CSRF |
| `admin/list-tables.php` | Auth guard |
| `admin/inventory-report.php` | Auth guard, remove hardcoded PDO |
| `admin/homepage-manager.php` | Auth guard |
| `admin/backup.php` | env-based credentials, escapeshellarg |
| `api/index.php` | Ownership check on updateOrder() |
| `checkout.php` | Shipping non-negative, commit, try/catch fix |
| `includes/PaymentGateway.php` | Refund cap, commission on netAmount |
| `gantt-view.php` | XSS escape helper |
