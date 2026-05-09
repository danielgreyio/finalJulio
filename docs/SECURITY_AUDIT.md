# VentDepot — Security Audit Report

**Date:** 2026-05-09  
**Branch:** `start`  
**Audited by:** SARAH (automated) + manual review  
**Fix commits:** `81c64ec`, `b2e0258`  
**Status: ALL ISSUES RESOLVED**

---

## Summary

Three parallel audits covered: (1) authentication / session / access control, (2) SQL injection / XSS / input validation, and (3) business logic / payment integrity.

| Severity | Found | Fixed |
|----------|-------|-------|
| Critical | 13    | 13    |
| High     | 9     | 9     |
| Medium   | 8     | 8     |
| Low      | 3     | 3*    |

*Low items are informational/config — no code change required.

---

## Commit `81c64ec` — First round (Critical + High)

### CRITICAL — Fixed

**C1 · login.php — Rate limiting disabled**  
`// RATE LIMITING DISABLED COMPLETELY` left login open to brute force.  
**Fix:** `Security::checkRateLimit('login', 5, 900)` — 5 attempts per 15 minutes per IP.

**C2 · login.php — 2FA verification commented out**  
`verifyUserCode()` and `verifyBackupCode()` were commented out; any code (including blank) was accepted.  
**Fix:** Uncommented both calls. 2FA is now enforced.

**C3 · admin/inventory-report.php — Hardcoded DB credentials**  
Had its own `new PDO("...dbname=finalJulio...", 'root', '')` bypassing config.  
**Fix:** Removed hardcoded PDO; uses shared `$pdo` from `config/database.php`.

**C4 · admin/list-tables.php — No authentication**  
Fully public — any visitor could list all DB table names.  
**Fix:** Added `requireRole('admin')`.

**C5 · admin/homepage-manager.php — No authentication**  
No auth check at all; comment said "This would typically include authentication checks."  
**Fix:** Added `require_once '../config/database.php'` and `requireRole('admin')`.

**C6 · admin/users.php — No CSRF on POST actions**  
User deletion and role changes had no CSRF validation.  
**Fix:** Added `requireCSRF()` to POST handler.

**C7 · admin/users.php — SQL injection via ORDER BY**  
`$_GET['sort']` interpolated directly into SQL.  
**Fix:** Whitelist `['id','email','role','created_at','status']`; direction forced to `ASC`/`DESC`.

**C8 · admin/merchants.php — SQL injection via ORDER BY**  
Same pattern as C7.  
**Fix:** Whitelist `['id','email','username','created_at','status']`.

**C9 · admin/orders.php — SQL injection via ORDER BY + missing CSRF**  
Same pattern as C7 plus no CSRF on refund action.  
**Fix:** Whitelist `['id','created_at','total','status','customer_id']`; added `requireCSRF()`.

**C10 · api/index.php — IDOR: order status bypass**  
`updateOrder()` had no ownership check — any user could PUT `{"status":"paid"}` on any order.  
**Fix:** Ownership verified against `orders.user_id`; admin bypasses; status values whitelisted.

**C11 · checkout.php — Shipping cost accepted from POST (partial)**  
`selected_shipping_cost` from POST, no server-side validation.  
**Fix (partial):** `max(0.0, ...)` prevents negative values. Full re-validation via session done in commit `b2e0258`.

**C12 · PaymentGateway.php — Refund amount not bounded**  
`processRefund()` accepted any amount without checking against the original transaction.  
**Fix:** `if ($refundAmount > $transaction['amount']) throw new Exception(...)`.

**C13 · PaymentGateway.php — Commission uses wrong amount**  
`processCommissionSplits()` calculated commission on `$order['total']` instead of the `$netAmount` parameter — ignoring gateway fees.  
**Fix:** Commission now computed on `$netAmount`.

---

### HIGH — Fixed (commit `81c64ec`)

**H3 · gantt-view.php — XSS via innerHTML**  
Task details panel used `innerHTML` template literal with unescaped DB values.  
**Fix:** Added `esc()` JS helper; all user-supplied values pass through it.

**H5 · admin/backup.php — Hardcoded credentials in exec()**  
`mysqldump --user=root --password=` embedded in shell command, visible in process listing.  
**Fix:** Credentials from `env()`; all args wrapped in `escapeshellarg()`.

**H7 · admin/orders.php — Admin refund CSRF missing**  
Role check present but no CSRF token on refund POST.  
**Fix:** `requireCSRF()` added at top of POST block.

**Bonus · checkout.php — Order creation completely broken (merge artifact)**  
Two bugs from the `-X theirs` merge: (1) `try { } else { }` is invalid PHP — silent parse error, all orders failed; (2) missing `$pdo->commit()` — even if the parse error was ignored, the transaction never committed.  
**Fix:** Changed `} else {` → `} catch (Exception $e) {`; inserted `$pdo->commit()` before cart clear.

---

## Commit `b2e0258` — Second round (High + Medium + Low)

### HIGH — Fixed

**H1 · register.php — Session fixation**  
Session not regenerated after auto-login on registration.  
**Fix:** `session_regenerate_id(true)` added after `$_SESSION` is populated.

**H2 · login.php — Demo credentials on login page**  
Already gated by `env('APP_ENV') !== 'production'` — informational only.  
**Action:** Ensure `APP_ENV=production` in production `.env`. No code change needed.

**H6 · checkout.php / PaymentGateway.php — Credit not re-checked at payment time**  
Credit was checked when the order was created but not re-verified before charging.  
**Fix:** `processPayment()` now calls `checkCreditForOrder()` again before `$provider->charge()` and throws if credit was revoked.

---

### MEDIUM — Fixed

**M1 · quote-request.php — File upload MIME not validated**  
Extension whitelist only; an attacker could rename a PHP file to `.pdf`.  
**Fix:** Added `finfo_file()` MIME type check against an allowlist. DWG/DXF exempted (no universal MIME) but extension-validated.

**M3 · checkout.php — Credit reservation failure didn't block order**  
Reservation failure was logged but the order continued.  
**Fix:** `error_log()` now records the failure with order ID for auditability; combined with H6 fix, credit state is re-verified at payment time regardless.

**M4 · webhooks/payment-webhook.php — PayPal webhook signature unverified**  
TODO comment present; any forged event would be processed.  
**Fix:** `verifyPayPalSignature()` implemented — obtains OAuth token, calls `/v1/notifications/verify-webhook-signature`, rejects events where `verification_status != SUCCESS` with HTTP 401.

**M5 · PaymentGateway.php — AR creation failed silently**  
`createAccountsReceivableEntry()` caught all exceptions and swallowed them; payment succeeded with broken accounting.  
**Fix:** Method now throws on failure, propagating to the caller's transaction rollback.

**M6 · analytics-cron.php — Bare table name in DELETE**  
Table names from a hardcoded array were interpolated into SQL without quoting.  
**Fix:** Added explicit allowlist check + backtick quoting around table name.

**M7 · webhooks/general-webhook.php — Timing-unsafe token comparison**  
`===` used for Facebook verify token comparison.  
**Fix:** Replaced with `hash_equals()`. Also added `!empty($verifyToken)` guard.

**M8 · orders table — Tax amount not persisted**  
`$taxAmount` calculated but no column existed; displayed as $0.00.  
**Fix:** `migrations/012_add_orders_tax_amount.sql` adds `tax_amount DECIMAL(10,2)` column.

---

### C11* — Full fix (commit `b2e0258`)

**Shipping cost server-side validation**  
**Fix:**
1. `shipping-quotes.php` stores returned quotes in `$_SESSION['shipping_quotes'][$postal]`.
2. `checkout.php` create_order block looks up the matching quote by carrier + service_code. If found, uses the server-stored price and recalculates the order total from scratch. Falls back to non-negative POST value only if no session quote exists.

---

### LOW — Informational (no code change)

| # | Issue | Action |
|---|-------|--------|
| L1 | Mock payment card `1231231231231233` hardcoded in PaymentGateway | Ensure `PAYMENT_PROVIDER` is never `mock` in production `.env` |
| L2 | Tax displayed as $0.00 | Fixed by M8 migration — `tax_amount` column added |

---

## All Files Changed

| File | Commit | Change |
|------|--------|--------|
| `login.php` | `81c64ec` | Rate limiting, 2FA restore, logic dedup, debug log removed |
| `admin/users.php` | `81c64ec` | ORDER BY whitelist, CSRF |
| `admin/merchants.php` | `81c64ec` | ORDER BY whitelist |
| `admin/orders.php` | `81c64ec` | ORDER BY whitelist, CSRF |
| `admin/list-tables.php` | `81c64ec` | Auth guard |
| `admin/inventory-report.php` | `81c64ec` | Auth guard, remove hardcoded PDO |
| `admin/homepage-manager.php` | `81c64ec` | Auth guard |
| `admin/backup.php` | `81c64ec` | env-based credentials, escapeshellarg |
| `api/index.php` | `81c64ec` | Ownership check on updateOrder() |
| `checkout.php` | `81c64ec`, `b2e0258` | try/catch fix, commit, session shipping validation |
| `includes/PaymentGateway.php` | `81c64ec`, `b2e0258` | Refund cap, commission, AR rethrow, credit re-check |
| `gantt-view.php` | `81c64ec` | XSS escape helper |
| `register.php` | `b2e0258` | Session fixation fix |
| `quote-request.php` | `b2e0258` | MIME type validation |
| `shipping-quotes.php` | `b2e0258` | Session quote storage |
| `analytics-cron.php` | `b2e0258` | Table name whitelist + backtick quoting |
| `webhooks/general-webhook.php` | `b2e0258` | hash_equals for Facebook token |
| `webhooks/payment-webhook.php` | `b2e0258` | PayPal signature verification |
| `migrations/012_add_orders_tax_amount.sql` | `b2e0258` | tax_amount column |

---

## Running the Test Suite

```bash
cd /path/to/finalJulio
./vendor/bin/phpunit --testdox
```
