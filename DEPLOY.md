# VentDepot — Deployment Guide

## Overview

`deploy.sh` handles the full deploy cycle from your local machine:

1. Commits any uncommitted changes and pushes to GitHub
2. Packages the project (excluding `.git`, `.env`, deploy scripts)
3. SCPs the archive to the Linode server
4. On the server: extracts, copies files, **runs all SQL migrations**, sets permissions, reloads Apache

## Prerequisites

- SSH access to `root@198.58.124.137` (key-based auth recommended)
- `scp` and `ssh` available locally (standard on macOS/Linux; use Git Bash or WSL on Windows)
- `.env` already present on the server at `/var/www/html/.env` before the first deploy

## Running a Deploy

```bash
cd /path/to/finalJulio
bash deploy.sh
```

The script prints each step. A successful deploy ends with:

```
Deployment completed at <timestamp>
=== Deployment Process Completed ===
```

## What the Migration Step Does

After copying files to `/var/www/html`, the script reads DB credentials from `/var/www/html/.env` and runs every file matching `migrations/*.sql` in filename order (001, 002, … 009, etc.). All migration files use `IF NOT EXISTS` / `ON DUPLICATE KEY UPDATE`, so re-running them is safe.

If `.env` is missing, migrations are skipped with a warning — you'll need to run them manually (see below).

## First Deploy on a Fresh Server

Before running `deploy.sh` for the first time on a new server:

1. SSH in and create the database:
   ```bash
   mysql -u root -p
   CREATE DATABASE ventdepot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'ventdepot'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
   GRANT ALL PRIVILEGES ON ventdepot.* TO 'ventdepot'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. Create `/var/www/html/.env` from the example and fill in all values:
   ```bash
   cp /var/www/html/.env.example /var/www/html/.env
   nano /var/www/html/.env
   chmod 640 /var/www/html/.env
   ```

3. Now run `deploy.sh` — it will copy files and run all migrations automatically.

## Running Migrations Manually

If you need to apply a specific migration or the automatic step failed:

```bash
ssh root@198.58.124.137
source <(grep -E '^(DB_HOST|DB_NAME|DB_USER|DB_PASS)=' /var/www/html/.env)
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < /var/www/html/migrations/009_construction_setup.sql
```

Replace `009_construction_setup.sql` with whichever migration you need.

To run all migrations in order:

```bash
for f in $(ls -v /var/www/html/migrations/*.sql); do
    echo "Running $f..."
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$f"
done
```

## Adding a New Migration

1. Create `migrations/010_your_change.sql` (next number in sequence)
2. Write SQL using `IF NOT EXISTS` and `ON DUPLICATE KEY` so it's safe to re-run
3. Deploy normally — the migration runs automatically on next deploy

## Rollback

There is no automated rollback. To revert a deploy:

1. **Code**: Check out the previous commit locally, run `deploy.sh` again
2. **Database**: Restore from the backup you took before deploying (see below)

### Taking a DB Backup Before Deploying

```bash
ssh root@198.58.124.137
source <(grep -E '^(DB_HOST|DB_NAME|DB_USER|DB_PASS)=' /var/www/html/.env)
mysqldump -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > /root/backups/ventdepot-$(date +%Y%m%d-%H%M).sql
```

Keep at least 3 recent backups. Set up an automated nightly backup cron if this is a production site:

```bash
0 2 * * * source <(grep -E '^(DB_HOST|DB_NAME|DB_USER|DB_PASS)=' /var/www/html/.env) && mysqldump -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > /root/backups/ventdepot-$(date +\%Y\%m\%d).sql.gz && find /root/backups -name '*.sql.gz' -mtime +7 -delete
```

## Protecting .env

The deploy script sets `.env` permissions to `640` (owner read/write, group read, world none). Apache should never serve this file directly. Verify with:

```bash
curl -I http://198.58.124.137/.env
# Should return 403 Forbidden
```

If it returns 200, add to `/var/www/html/.htaccess`:

```apache
<Files ".env">
    Require all denied
</Files>
```

## Troubleshooting

| Problem | What to check |
|---------|---------------|
| 500 error after deploy | `/var/log/apache2/error.log` on the server |
| Migration warnings | Warnings about "already exists" are normal (idempotent SQL). Errors about unknown columns indicate a real problem — check the migration SQL. |
| Apache not reloading | `systemctl status apache2` — check for config syntax errors |
| `.env` not found | Make sure it exists at `/var/www/html/.env` with correct permissions |
| SSH connection refused | Check Linode firewall allows port 22 from your IP |
