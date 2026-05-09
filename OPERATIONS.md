# VentDepot — Day-to-Day Operations Guide

This guide is written for operators and support staff who manage VentDepot through the admin panel. It covers the tasks you will encounter most often. The admin panel lives at `/admin/`.

---

## 1. Adding a Category

Navigate to Admin → Categories (or go directly to `/admin/categories.php`). Click **Add Category** and fill in the following fields:

- **Name** — the display label shown to buyers (e.g., "Plomería").
- **Slug** — the URL-safe identifier, lowercase with hyphens and no accents (e.g., `plomeria`). The slug must be unique. If you leave it blank the system will attempt to generate one from the name, but it is safer to set it explicitly.
- **Description** — a short phrase describing what belongs in this category. This text appears in category pages and is indexed for search.
- **Sort Order** — controls the display sequence. Lower numbers appear first. Increment by 10 (10, 20, 30…) so you can insert items later without renumbering everything.
- **Parent Category** — leave blank for a top-level category. Set this if you are creating a subcategory.

Click **Save**. The category is immediately available when merchants list new products.

**Resetting categories with migration 009.** If you need to restore the standard construction verticals after a test or a bad import, run migration `009_construction_setup.sql` directly against the database. This migration deactivates generic placeholder categories (electronics, clothing, etc.) and upserts the seven core construction verticals — Construcción, Herramientas, Eléctrico, Plomería, Seguridad Industrial, Acabados, and Ferretería General — using `ON DUPLICATE KEY UPDATE`, so it is safe to run more than once. It also adds the `unit_of_measure` column to products and creates the `product_pricing_tiers` table if they do not already exist. Run it as:

```sql
SOURCE /path/to/migrations/009_construction_setup.sql;
```

Or paste the file contents into your database client directly.

---

## 2. Approving or Rejecting a Merchant

Go to Admin → Merchants (`/admin/merchants.php`). The page lists all users with the `merchant` role. You can search by email and filter by status.

**To approve a pending merchant:**

1. Locate the merchant row and click **Approve**.
2. The system sets their account status to `active` and immediately sends them an email notification via the `Mailer::sendMerchantStatusUpdate` method. No manual email is needed.
3. Once approved, the merchant can log in and begin listing products.

**To reject an application:**

1. Click **Reject** on the merchant's row.
2. A prompt asks for a rejection reason. Provide a clear, specific reason — this text is included in the notification email the merchant receives.
3. The account status is set to `inactive`. The merchant can reapply; their submission will appear in the queue again.

**Suspending an active merchant:**

Use the **Suspend** button on any active merchant's row. Their status changes to `suspended` and they receive an email notification. A suspended merchant cannot list new products. Any existing published listings remain visible to buyers but the merchant loses the ability to add or modify listings until you lift the suspension by approving them again.

If a merchant contacts you after a rejection or suspension, review their profile and product history before making a decision. The merchant detail page at `/admin/merchant-details.php?id={id}` shows order count, revenue, and last activity.

---

## 3. Managing an Order Dispute

Disputes are managed at Admin → Dispute Management (`/admin/dispute-management.php`). The same escrow/dispute records are also accessible from the buyer-facing page at `/buyer-protection.php`, which buyers use to initiate and track their own disputes.

**Reviewing a dispute:**

The dispute list is sorted by priority (urgent → high → medium → low) and then by age, so the most pressing cases appear at the top. Click into a dispute to see the escrow amount, the order it is tied to, and the buyer and seller email addresses. The dispute record stores the reason the buyer gave and any description they provided when opening the case.

Steps to work through a dispute:

1. Read the buyer's stated reason and description carefully.
2. Check the linked order in Admin → Orders to confirm shipment status, tracking, and delivery confirmation.
3. Review any evidence the buyer or seller has submitted (attachments, chat history available from `/chat.php`).
4. Decide on the resolution: full refund to buyer, partial split, or award to seller.

**Issuing a refund through the dispute resolution form:**

At the bottom of the dispute detail view there is a **Resolve Dispute** form. Enter:
- **Resolution** — a written explanation of your decision. This is stored and may be shown to both parties.
- **Award to Buyer Percentage** — enter a number from 0 to 100. A value of 100 refunds the full escrow amount to the buyer; 0 releases it to the seller; 50 splits it evenly.

Click **Resolve**. The system calls `EscrowSystem::resolveDispute`, which handles the fund release and marks the dispute as resolved. The underlying payment refund flows through `PaymentGateway::processRefund`, which routes the request to the correct gateway (Stripe, PayPal, or MercadoPago) based on how the original transaction was processed.

**When to escalate:**

Escalate internally (to a manager or legal) when: the dispute involves a transaction above your authorized refund threshold; the buyer alleges fraud or significant product misrepresentation; the merchant has multiple open disputes suggesting a systemic issue; or either party threatens legal action. Flag the dispute priority as **urgent** before handing it off so it stays visible at the top of the queue.

---

## 4. Running Financial Reports

Go to Admin → Reports (`/admin/reports.php`). At the top of the page, set a **Start Date** and **End Date** and click **Apply Filter**. All figures on the page update for that period.

The reports page provides:

- **Revenue summary** — total orders, total revenue, average order value, and unique customers for the selected period.
- **Top 10 products** — ranked by revenue. Shows times ordered, total quantity sold, and revenue generated. Cancelled and refunded orders are excluded.
- **Top 10 merchants** — ranked by revenue. Shows order count, revenue, and number of active products.
- **Daily sales chart** — a line chart of order count and daily revenue across the period.
- **Shipping summary** — delivered vs. in-transit shipments and average shipping cost.
- **User growth** — total users, split by customer and merchant roles, plus new registrations in the last 30 days.

Commission data is tracked separately. Go to Admin → Commission Tracking (`/admin/commission-tracking.php`) to see the platform's commission totals broken down by pending, approved, and paid status, along with commission tier thresholds.

**Exporting to CSV:**

On the Reports page, three export buttons appear below the date filter: **Export Sales Data**, **Export Product Data**, and **Export Merchant Data**. Each calls `/admin/export-csv.php` with the selected date range and a `type` parameter (`sales`, `products`, or `merchants`). The browser will download the file directly. For a full financial export (income statement, balance sheet, or cash flow statement), use the dedicated Financial Reports page at `/admin/financial-reports.php`, which has its own **Generate Report** button and accepts a report type selector alongside the date range.

For executive-level views, the C-Level Dashboard at `/admin/c-level-dashboard.php` aggregates cash flow, budget vs. actual, unit economics, growth metrics, and risk management in one place.

---

## 5. Updating the Homepage Banner

There are two ways to update the hero section.

**Using Admin → CMS (recommended):**

Go to Admin → CMS → Content (`/admin/cms-content.php`). The page lists all content blocks grouped by frontend section. The homepage hero lives in the section named **Homepage Hero** (slug: `homepage-hero`).

Find the block whose title corresponds to the element you want to change:
- The hero headline is stored as a content block titled something like "Hero Headline" or "Main Headline" in the Homepage Hero section.
- The subheadline is a separate block in the same section.
- The CTA button text is its own block.

Click **Edit** on the block, update the content field, and click **Save**. Changes take effect immediately on the next page load; there is no cache flush required for content blocks.

To find the exact row IDs if you need to update them directly in the database:

```sql
SELECT cb.id, cb.title, cb.content, fs.name AS section_name
FROM content_blocks cb
JOIN frontend_sections fs ON cb.section_id = fs.id
WHERE fs.slug = 'homepage-hero'
ORDER BY cb.sort_order;
```

**Using Admin → CMS → Banners:**

For image-based hero banners (the `frontend_banners` table), go to `/admin/cms-banners.php`. Hero-type banners have `banner_type = 'hero'`. You can set a title, subtitle, button text, button URL, linked image, and active date range. Set `is_active = 1` and leave `start_date` / `end_date` blank for a permanent banner, or set dates for a time-limited campaign.

The **Homepage Manager** at `/admin/homepage-manager.php` provides a form-based interface for the same banner fields (title, image URL, text overlay, button text, button link, sort order, start/end dates) if you prefer a single-screen view.

---

## 6. Handling Failed Payments

**Understanding payment statuses:**

The `orders` table has a `payment_status` column with four possible values:

- `pending` — the order was placed but payment has not been attempted or has not yet settled. This is normal immediately after checkout.
- `paid` — the payment gateway confirmed a successful charge. The `PaymentGateway` records a transaction with status `completed` in `payment_transactions` and sets the order to `paid`.
- `failed` — the charge was attempted and declined or errored. A `payment_failure` event is written to the security log.
- `refunded` — a refund was issued through the system, either via a dispute resolution or the manual refund button in Admin → Orders.

**What to do when a payment shows as failed:**

1. Go to Admin → Orders (`/admin/orders.php`) and locate the order. Filter by status if needed.
2. Note the `payment_transaction_id` — if it is null, no transaction was recorded, which usually means the charge never reached the gateway.
3. Log into the payment gateway dashboard (Stripe, PayPal, or MercadoPago, depending on `PAYMENT_PROVIDER` in `.env`) and search for the transaction by order amount, customer email, and approximate time.
4. If the gateway shows the charge as successful despite the order showing `failed`, the disconnect was on the webhook or callback side.

**Manually marking an order as paid:**

Only do this after you have independently verified in the gateway dashboard that funds were captured. In Admin → Orders, open the order detail and use the **Update Status** action with status `paid`. This updates the `orders.status` field. If you also need to correct the `payment_status`, do it directly in the database:

```sql
UPDATE orders
SET payment_status = 'paid', status = 'confirmed'
WHERE id = <order_id>;
```

After marking it paid, notify the merchant so they can begin fulfillment. If the issue recurs for multiple orders, check the webhook endpoint at `/webhooks/` for errors and review the Apache error log (see Section 7).

---

## 7. Monitoring

**Error log:**

The primary error log is at `/var/log/apache2/ventdepot-error.log`. Watch this file for:

- PHP fatal errors and uncaught exceptions, which will show a stack trace.
- `Redis connection failed` messages — these mean the accounting cache has fallen back to file-based caching (see below). Performance will degrade but the site continues to work.
- `payment_failure` entries logged by `Security::logSecurityEvent` — each one corresponds to a failed payment attempt and includes the order ID and error message.
- Database connection errors, which will cascade into 500 errors for all users.
- Repeated 401 or 403 entries for `/admin/` paths, which may indicate a brute-force attempt against the admin panel.

A quick way to tail the log in real time:

```bash
tail -f /var/log/apache2/ventdepot-error.log
```

**Checking Redis health:**

Redis runs on `127.0.0.1:6379` and is used primarily for the accounting dashboard cache (keys prefixed `accounting:`). The system falls back to file-based caching automatically if Redis is unavailable, so a Redis outage is not site-critical but it will slow down the admin accounting pages noticeably.

To check Redis from the command line:

```bash
redis-cli ping
```

A healthy response is `PONG`. If you get a connection error, restart the service:

```bash
sudo systemctl restart redis
```

To check memory usage and hit rate from the CLI:

```bash
redis-cli info memory
redis-cli info stats
```

Key metrics to watch: `used_memory_human` (should stay well below your server's RAM limit), `keyspace_hits` vs. `keyspace_misses` (a high miss ratio means the cache is not warming properly, which is normal after a restart), and `connected_clients` (a sudden spike may indicate a connection leak).

The Admin → Accounting System Status page (`/admin/accounting-system-status.php`) surfaces Redis health through the PHP `AccountingRedisCache::getStats()` method, so you can also check it from the browser without SSH access.

**Cron jobs:**

Several background tasks run on schedule. Check that the following scripts are executing without errors by reviewing their output in the cron log or by inspecting their side effects:

- `/escrow-cron.php` — releases held funds from escrow after the buyer confirmation window expires.
- `/analytics-cron.php` — refreshes aggregated analytics data.

If a cron job has not run when expected, verify the server's crontab with `crontab -l` and confirm the PHP binary path is correct.
