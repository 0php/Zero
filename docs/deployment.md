# Deployment Guide

This document outlines how to deploy Zero Framework to a production server. The examples assume Ubuntu + Nginx + PHP-FPM, but the same structure applies to other Linux distributions.

## Prerequisites

- PHP 8.1 or newer with extensions: `pdo`, `pdo_mysql` (or `pdo_sqlite`/`pdo_pgsql` depending on your driver), `openssl`, `mbstring`, `json`, `xml`.
- A supported web server (Nginx is demonstrated below) and PHP-FPM.
- A configured database (MySQL, PostgreSQL, or SQLite).
- Access to update environment variables (`.env` and variants) on the server.

## Directory Layout

Deploy the repository in a dedicated directory, e.g. `/var/www/zero-framework`. Ensure the `storage/` directory is writable by the web server user (commonly `www-data`).

```bash
sudo chown -R www-data:www-data storage
sudo find storage -type d -exec chmod 775 {} \;
```

## Environment Configuration

Copy `.env.example` to `.env` and set production values:

```ini
APP_URL=https://{domain}
APP_DEBUG=false
DB_CONNECTION=mysql
MYSQL_HOST=127.0.0.1
MYSQL_DATABASE=zero
MYSQL_USER=zero_user
MYSQL_PASSWORD=secret
LOG_DRIVER=file
```

For SQLite deployments, set `DB_CONNECTION=sqlite` and point `SQLITE_DATABASE` at an absolute path. Enable database logging by setting `LOG_DRIVER=database` and create the logs table (default `logs`).

## PHP-FPM Pool

Verify PHP-FPM is running and note the socket (e.g. `/run/php/php8.2-fpm.sock`). Adjust your Nginx config accordingly.

## Nginx Configuration

Create a server block at `/etc/nginx/sites-available/zero-framework`:

```nginx
server {
    listen 80;
    server_name {domain};

    root /var/www/zero-framework/public;
    index index.php;

    access_log /var/log/nginx/zero-framework.access.log;
    error_log /var/log/nginx/zero-framework.error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param APP_ENV production;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site and reload Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/zero-framework /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### HTTPS

Use Certbot or your preferred ACME client to obtain TLS certificates:

```bash
sudo certbot --nginx -d {domain}
```

## Database Migrations & Seeders

Run migrations and seeders via the CLI (from the project root):

```bash
php zero migrate
php zero db:seed
```

For zero-downtime deployments, ensure migrations are idempotent and consider running them during maintenance windows.

## Scheduler & Background Jobs

Zero's task scheduler runs via the CLI (`php zero schedule:run`). Configure a cron entry so it executes every minute and let the framework decide which jobs are due.

```cron
* * * * * www-data php /var/www/zero-framework/zero schedule:run >> /var/log/zero-schedule.log 2>&1
```

### Cron Setup Checklist {#cron-and-scheduler}

1. SSH into the server and edit the crontab for the runtime user (usually `www-data`):
   ```bash
   sudo crontab -u www-data -e
   ```
2. Paste the cron line above, adjusting the PHP binary path, project root, and log destination for your environment.
3. Save the file and confirm installation:
   ```bash
   sudo crontab -u www-data -l
   ```
4. Ensure the log directory exists and is writable by the cron user (e.g., `sudo mkdir -p /var/log && sudo chown www-data /var/log/zero-schedule.log`).
5. Watch the log after deployment (`tail -f /var/log/zero-schedule.log`) to verify tasks run as expected.

Cron only determines *when* `schedule:run` executes—the framework handles overlap prevention and task eligibility.

## Logging

Logs default to `storage/framework/logs/YYYY-MM-DD.log`. Ensure the directory is writable and consider rotating logs with `logrotate`. For database logging, confirm the `logs` table exists before switching `LOG_DRIVER` to `database`.

## Zero CLI in Production

For convenience, symlink the CLI:

```bash
sudo ln -s /var/www/zero-framework/zero /usr/local/bin/zero
```

Then run commands like `zero migrate` or `zero make:model` directly.

## Maintenance Mode

You can disable the site temporarily by pointing Nginx to a static maintenance page or modifying routes to return 503 responses. (A dedicated maintenance command can be added to the CLI in the future.)

## Deployment Checklist

- [ ] Pull the latest code and composer dependencies if applicable.
- [ ] Update `.env` with any new configuration values.
- [ ] Clear caches if implemented (views, config, etc.).
- [ ] Run migrations and seeders.
- [ ] Reload PHP-FPM/Nginx if configuration changed.
- [ ] Verify logs for errors.
- [ ] Hit health-check endpoints or run smoke tests.

## Zero-Downtime Strategies

Given the framework's simplicity, standard techniques apply:

- Use atomic symlink deployments (`/var/www/zero-framework/releases/{timestamp}` with `current` symlink).
- Run migrations before swapping symlinks.
- Warm caches or compiled views in the new release directory.
- Swap the symlink, reload services, and prune old releases.

## Troubleshooting

- **500 errors immediately after deploy**: check `storage/framework/logs/` or the configured database log channel.
- **Permission issues**: ensure `storage/` and database directories are writable by the web server user.
- **Blank responses**: enable `APP_DEBUG=true` temporarily (only in controlled environments) or tail PHP-FPM logs for stack traces.

Happy shipping!

## Updating the Framework

Configure `UPDATE_MANIFEST_URL` to point at your signed release manifest, then run:

Review the output, apply migrations, and restart services as required. For manual deployments, continue to use your existing workflow.
