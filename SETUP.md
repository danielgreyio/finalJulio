# VentDepot — Production Server Setup Guide

**Stack:** Ubuntu 22.04 LTS · Apache 2.4 · MySQL 8 · PHP 8.1 · Redis · Composer  
**Target:** Fresh Linode VPS

---

## 1. Server Provisioning

### Initial OS hardening

Log in as root, then run:

```bash
apt update && apt upgrade -y
apt install -y ufw fail2ban curl unzip git
```

Create a non-root deploy user:

```bash
adduser deploy
usermod -aG sudo deploy
```

Copy your SSH public key to the new user:

```bash
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys
```

### SSH hardening

Edit `/etc/ssh/sshd_config` and set:

```
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
```

Restart SSH:

```bash
systemctl restart sshd
```

Open a second terminal and verify you can still log in as `deploy` before closing the root session.

### Firewall (ufw)

```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
ufw status
```

---

## 2. Install the Stack

All commands below run as `deploy` with `sudo`.

### Apache 2.4

```bash
sudo apt install -y apache2
sudo a2enmod rewrite headers ssl
sudo systemctl enable apache2
```

### MySQL 8

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

During `mysql_secure_installation`: set a strong root password, remove anonymous users, disallow remote root login, remove test database.

### PHP 8.1 with required extensions

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y \
  php8.1 php8.1-cli php8.1-fpm \
  php8.1-mysql php8.1-mbstring php8.1-curl \
  php8.1-soap php8.1-redis php8.1-bcmath \
  php8.1-intl php8.1-xml php8.1-zip \
  libapache2-mod-php8.1
```

Verify:

```bash
php8.1 -m | grep -E "pdo_mysql|mbstring|curl|soap|redis|bcmath|intl"
```

### Redis

```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server
redis-cli ping   # should return PONG
```

### Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

---

## 3. Deploy the Application

### Clone / upload the repo

```bash
sudo mkdir -p /var/www/ventdepot
sudo chown deploy:www-data /var/www/ventdepot
git clone <your-repo-url> /var/www/ventdepot
```

If you are uploading a zip instead:

```bash
unzip ventdepot.zip -d /var/www/ventdepot
```

### Install PHP dependencies

```bash
cd /var/www/ventdepot
composer install --no-dev --optimize-autoloader
```

### File permissions

```bash
cd /var/www/ventdepot

# Web server needs to read everything
sudo chown -R deploy:www-data .

# Writable directories
sudo chmod -R 775 storage/
sudo chmod -R 775 logs/

# .env must not be world-readable
chmod 640 .env
```

The `storage/` directory contains `cache/` and `sessions/` subdirectories — both must be writable by the web server process (`www-data`).

---

## 4. Database Setup

### Create database and user

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE ventdepot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ventdepot_user'@'127.0.0.1' IDENTIFIED BY 'StrongPasswordHere';
GRANT ALL PRIVILEGES ON ventdepot.* TO 'ventdepot_user'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

### Import migrations in order

Run each migration file in numeric sequence from the `migrations/` directory:

```bash
cd /var/www/ventdepot

for f in migrations/001_initial_schema.sql \
          migrations/002_homepage_components.sql \
          migrations/003_fixed_marketplace_schema.sql \
          migrations/004_engineering_task_management.sql \
          migrations/005_user_profiles.sql \
          migrations/006_chat_system.sql \
          migrations/007_two_factor_trusted_devices.sql \
          migrations/008_orders_shipping_columns.sql \
          migrations/009_construction_setup.sql \
          migrations/010_password_reset_tokens.sql \
          migrations/011_add_orders_subtotal.sql \
          migrations/012_add_orders_tax_amount.sql; do
    echo "Running $f..."
    mysql -u ventdepot_user -p'StrongPasswordHere' -h 127.0.0.1 ventdepot < "$f"
done
```

Check for errors after each file. The remaining `.sql` files in `migrations/` (credit management, CMS schema, etc.) are optional add-ons — apply them only if the features they back are required.

---

## 5. Environment Configuration

Copy the example file and edit it:

```bash
cp /var/www/ventdepot/.env.example /var/www/ventdepot/.env
nano /var/www/ventdepot/.env
```

Fill in every value in the table below. Leave nothing as a placeholder.

| Variable | Description |
|---|---|
| `APP_URL` | Full URL including scheme, e.g. `https://ventdepot.com` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` in production |
| `DB_HOST` | `127.0.0.1` (localhost via TCP) |
| `DB_DATABASE` | `ventdepot` |
| `DB_USERNAME` | `ventdepot_user` |
| `DB_PASSWORD` | The password set in step 4 |
| `REDIS_HOST` | `127.0.0.1` |
| `REDIS_PORT` | `6379` |
| `REDIS_PASSWORD` | Leave `null` unless you set a Redis password |
| `MAIL_HOST` | SMTP hostname, e.g. `smtp.mailgun.org` |
| `MAIL_PORT` | `587` (STARTTLS) or `465` (SSL) |
| `MAIL_USERNAME` | SMTP account username |
| `MAIL_PASSWORD` | SMTP account password |
| `MAIL_ENCRYPTION` | `tls` or `ssl` |
| `MAIL_FROM_ADDRESS` | Sender address, e.g. `noreply@ventdepot.com` |
| `MAIL_FROM_NAME` | `VentDepot` |
| `STRIPE_KEY` | Stripe publishable key (`pk_live_…`) |
| `STRIPE_SECRET` | Stripe secret key (`sk_live_…`) |
| `PAYPAL_CLIENT_ID` | PayPal app client ID |
| `PAYPAL_SECRET` | PayPal app secret |
| `PAYPAL_MODE` | `live` (use `sandbox` only for testing) |
| `MP_ACCESS_TOKEN` | MercadoPago access token |
| `MP_PUBLIC_KEY` | MercadoPago public key |
| `PAYMENT_PROVIDER` | Active provider: `stripe`, `paypal`, or `mercadopago` |
| `ESTAFETA_USER` | Estafeta API username |
| `ESTAFETA_PASSWORD` | Estafeta API password |
| `ESTAFETA_CUSTOMER_NUMBER` | Numeric `idusuario` assigned by Estafeta (not the login name) |
| `DHL_API_KEY` | DHL MyDHL API username (format `PICXXXXXX`) |
| `DHL_API_SECRET` | DHL MyDHL API password |
| `DHL_ACCOUNT_NUMBER` | DHL 9-digit account number |
| `SHIPPING_PROVIDERS` | Comma-separated list of active providers, e.g. `estafeta,dhl` |
| `WAREHOUSE_POSTAL_CODE` | Origin postal code for shipping rate calculation, e.g. `06600` |
| `TAX_RATE` | Decimal tax rate, e.g. `0.16` for 16% IVA |
| `TAX_LABEL` | Display label, e.g. `IVA (16%)` |

After saving, verify the file is not world-readable:

```bash
ls -la /var/www/ventdepot/.env
# Should show -rw-r----- (640)
```

---

## 6. Apache Virtual Host

Create the virtual host config:

```bash
sudo nano /etc/apache2/sites-available/ventdepot.conf
```

Paste the following (replace `ventdepot.com` with your domain):

```apacheconf
<VirtualHost *:80>
    ServerName ventdepot.com
    ServerAlias www.ventdepot.com
    DocumentRoot /var/www/ventdepot/public

    <Directory /var/www/ventdepot/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/ventdepot-error.log
    CustomLog ${APACHE_LOG_DIR}/ventdepot-access.log combined

    # Redirect all HTTP to HTTPS once cert is in place
    RewriteEngine On
    RewriteCond %{SERVER_NAME} =ventdepot.com [OR]
    RewriteCond %{SERVER_NAME} =www.ventdepot.com
    RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]
</VirtualHost>
```

Enable the site and reload:

```bash
sudo a2ensite ventdepot.conf
sudo a2dissite 000-default.conf
sudo systemctl reload apache2
```

### SSL with Let's Encrypt (certbot)

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d ventdepot.com -d www.ventdepot.com
```

Certbot will automatically write the HTTPS VirtualHost block and configure the redirect. Test auto-renewal:

```bash
sudo certbot renew --dry-run
```

The final HTTPS block certbot creates will look like:

```apacheconf
<VirtualHost *:443>
    ServerName ventdepot.com
    ServerAlias www.ventdepot.com
    DocumentRoot /var/www/ventdepot/public

    <Directory /var/www/ventdepot/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/ventdepot.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/ventdepot.com/privkey.pem
    Include /etc/letsencrypt/options-ssl-apache.conf

    ErrorLog ${APACHE_LOG_DIR}/ventdepot-error.log
    CustomLog ${APACHE_LOG_DIR}/ventdepot-access.log combined
</VirtualHost>
```

Make sure `AllowOverride All` is present — the app uses `.htaccess` for routing.

---

## 7. Cron Jobs

Edit the crontab for the `deploy` user:

```bash
crontab -e
```

Add the following lines (replace `/var/www/ventdepot` with your actual path):

```cron
# VentDepot scheduled tasks
MAILTO=""

# Business automation — commission tiers, inventory alerts, financial period close
0 2 * * *  php /var/www/ventdepot/analytics-cron.php >> /var/www/ventdepot/logs/analytics-cron.log 2>&1

# Escrow auto-release — checks and releases eligible held payments
0 * * * *  php /var/www/ventdepot/escrow-cron.php >> /var/www/ventdepot/logs/escrow-cron.log 2>&1

# Business automation — commission tier progression, inventory alerts, marketing ROI
30 1 * * *  php /var/www/ventdepot/cron/business-automation.php >> /var/www/ventdepot/logs/business-automation.log 2>&1

# Business metrics monitor — threshold checks and alerting
*/15 * * * *  php /var/www/ventdepot/cron/business-metrics-monitor.php >> /var/www/ventdepot/logs/metrics-monitor.log 2>&1

# Collection status updater — marks overdue accounts receivable
0 6 * * *  php /var/www/ventdepot/cron/update-collection-statuses.php >> /var/www/ventdepot/logs/collections.log 2>&1

# Webhook processor — retries failed webhook deliveries
*/5 * * * *  php /var/www/ventdepot/cron/webhook-processor.php >> /var/www/ventdepot/logs/webhook-processor.log 2>&1
```

Summary table:

| Script | Schedule | Purpose |
|---|---|---|
| `analytics-cron.php` | Daily at 02:00 | Calculates daily metrics; maintains analytics data |
| `escrow-cron.php` | Every hour | Auto-releases eligible escrow holds; sends release notifications |
| `cron/business-automation.php` | Daily at 01:30 | Commission tier progression, inventory low-stock alerts, financial period closing, marketing ROI |
| `cron/business-metrics-monitor.php` | Every 15 minutes | Checks KPI thresholds and sends breach alerts |
| `cron/update-collection-statuses.php` | Daily at 06:00 | Transitions overdue accounts receivable into collection status |
| `cron/webhook-processor.php` | Every 5 minutes | Processes pending webhook events; retries failures |

---

## 8. Verify the Install

Work through this checklist after the full setup is complete.

- [ ] **Site loads** — visit `https://ventdepot.com` in a browser; page renders with no PHP errors or blank screen.
- [ ] **HTTPS is valid** — browser padlock is green; certificate shows Let's Encrypt and the correct domain.
- [ ] **Login works** — register a test account, log in, and log out successfully.
- [ ] **Vendor registration works** — register as a vendor/seller; dashboard loads without errors.
- [ ] **Add to cart works** — browse to a product, add to cart, verify cart count updates and the cart page shows the item.
- [ ] **Checkout shows shipping rates** — proceed to checkout with a product, enter a destination postal code, and confirm both Estafeta and DHL rates appear (or whichever providers are set in `SHIPPING_PROVIDERS`).
- [ ] **Tax line is correct** — the checkout summary shows the IVA line using the label and rate from `.env`.
- [ ] **Order confirmation email arrives** — complete a test purchase (use Stripe test card `4242 4242 4242 4242`) and confirm the confirmation email reaches the inbox within a few minutes.
- [ ] **Redis is caching** — run `redis-cli monitor` in one terminal, browse the site in another, and verify keys are being read/written.
- [ ] **Cron is running** — after waiting 15 minutes, check that `/var/www/ventdepot/logs/metrics-monitor.log` has been written to.
- [ ] **Log directory is writable** — confirm `logs/` and `storage/cache/` and `storage/sessions/` are owned by `www-data` and not throwing permission errors in `/var/log/apache2/ventdepot-error.log`.

---

## Quick reference — useful commands

```bash
# Reload Apache after config changes
sudo systemctl reload apache2

# Check Apache config syntax
sudo apache2ctl configtest

# Tail application error log
sudo tail -f /var/log/apache2/ventdepot-error.log

# Flush Redis cache
redis-cli FLUSHDB

# Check PHP-MySQL connectivity
php8.1 -r "new PDO('mysql:host=127.0.0.1;dbname=ventdepot', 'ventdepot_user', 'YourPassword');"

# Renew SSL cert manually
sudo certbot renew
```
