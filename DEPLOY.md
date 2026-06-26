# RentOps — Production Deployment Checklist

## Pre-deployment

### 1. Server requirements
- [ ] PHP 8.2+ with extensions: `pdo_mysql`, `mbstring`, `fileinfo`, `json`, `openssl`
- [ ] MySQL 8.0+
- [ ] Apache 2.4+ with `mod_rewrite`, `mod_headers`
- [ ] Disk space for uploads (ID proofs): min 500 MB recommended

### 2. Database
```bash
# Create dedicated DB user (never use root)
mysql -u root -p -e "
  CREATE DATABASE rentops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'rentops_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
  GRANT SELECT, INSERT, UPDATE, DELETE ON rentops.* TO 'rentops_user'@'localhost';
  FLUSH PRIVILEGES;
"

# Run migrations in order
mysql -u rentops_user -p rentops < database/migrations/001_initial_schema.sql
mysql -u rentops_user -p rentops < database/migrations/002_phase2.sql
mysql -u rentops_user -p rentops < database/migrations/003_rent_changes.sql
mysql -u rentops_user -p rentops < database/migrations/004_rate_limits_audit.sql

# Seed initial data
mysql -u rentops_user -p rentops < database/seeds/001_seed.sql
```

### 3. File permissions
```bash
# Web root readable by Apache
chmod -R 755 public/
chmod -R 644 public/assets/

# Uploads writable by web server only
chmod 750 public/uploads/
chown -R www-data:www-data public/uploads/

# Application files NOT web-accessible
chmod 750 src/ views/ database/ cron/
chmod 640 .env

# Cron scripts executable
chmod +x cron/*.php
```

### 4. Environment
```bash
cp .env.example .env
# Edit .env — set DB credentials, APP_URL, APP_ENV=production

# Generate session secret
php -r "echo bin2hex(random_bytes(32));" >> /dev/null
```

### 5. Apache vhost
```apache
<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot /var/www/rentops/public

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.com/privkey.pem

    <Directory /var/www/rentops/public>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>

    # Block access to application internals
    <DirectoryMatch "^/var/www/rentops/(src|database|cron|vendor)">
        Require all denied
    </DirectoryMatch>

    ErrorLog  /var/log/apache2/rentops_error.log
    CustomLog /var/log/apache2/rentops_access.log combined
</VirtualHost>

# Redirect HTTP → HTTPS
<VirtualHost *:80>
    ServerName yourdomain.com
    Redirect permanent / https://yourdomain.com/
</VirtualHost>
```

### 6. Crontab
```bash
crontab -e
# Add:
# Monthly invoice generation — 6 AM on 1st of each month
0 6 1 * * php /var/www/rentops/cron/generate_invoices.php >> /var/log/rentops_cron.log 2>&1

# Daily overdue sweep — 7 AM every day
0 7 * * * php /var/www/rentops/cron/refresh_overdue.php >> /var/log/rentops_cron.log 2>&1

# Weekly housekeeping — 3 AM every Sunday
0 3 * * 0 php /var/www/rentops/cron/cleanup.php >> /var/log/rentops_cron.log 2>&1
```

### 7. PHP.ini hardening (php.ini or .htaccess)
```ini
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/rentops_errors.log
upload_max_filesize = 6M
post_max_size = 8M
max_execution_time = 30
session.cookie_secure = 1
session.cookie_httponly = 1
session.use_strict_mode = 1
```

## Post-deployment

### First login
1. Navigate to `https://yourdomain.com/login`
2. Email: `owner@rentops.local`
3. Password: `RentOps@2024`
4. **Immediately** go to Settings → Change password
5. Update property name and address in Settings

### Dry-run cron test
```bash
php /var/www/rentops/cron/generate_invoices.php $(date +%Y-%m) --dry-run
```

### Data import
1. Go to `/import` → Download CSV template
2. Fill in existing tenant data (one row per tenant)
3. Upload → preview → confirm
4. Run `/audit` to verify all invoices generated correctly
5. Use `/audit/fix` to patch any gaps

## Security hardening checklist
- [ ] HTTPS enforced (redirect HTTP → HTTPS in vhost)
- [ ] `.env` not web-accessible (outside `public/`)
- [ ] `uploads/` blocks PHP execution (`.htaccess` in place)
- [ ] Default password changed
- [ ] DB user has minimal privileges (no DROP, no GRANT)
- [ ] `APP_ENV=production` (disables stack trace display)
- [ ] Cron log file writable only by cron user
- [ ] Firewall: only 80/443 open externally

## Monitoring
- Check `/var/log/rentops_cron.log` monthly after invoice generation
- Check `/audit` after any bulk data operations
- Check `/audit/log` for suspicious login patterns
