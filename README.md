# RentOps MVP v1

Rent operations platform for a single 22-room property. Built with PHP 8.2, MySQL 8, Vanilla JS, HTML/CSS.

## Setup

### 1. Requirements
- PHP 8.2+
- MySQL 8.0+
- Apache with `mod_rewrite`

### 2. Database
```sql
CREATE DATABASE rentops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
```bash
mysql -u root -p rentops < database/migrations/001_initial_schema.sql
mysql -u root -p rentops < database/seeds/001_seed.sql
```

### 3. Environment
Copy and edit:
```bash
cp .env.example .env
```
```
APP_URL=http://localhost/rentops/public
DB_HOST=localhost
DB_NAME=rentops
DB_USER=root
DB_PASS=yourpassword
```

### 4. Apache vhost
```apache
<VirtualHost *:80>
    DocumentRoot /path/to/rentops/public
    <Directory /path/to/rentops/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 5. First login
- URL: `http://your-domain/login`
- Email: `owner@rentops.local`
- Password: `RentOps@2024`
- **Change password immediately** (update hash via `password_hash()`)

### 6. Cron (monthly invoice generation)
```cron
0 6 1 * * php /path/to/rentops/cron/generate_invoices.php >> /var/log/rentops_cron.log 2>&1
```

## Project structure
```
rentops/
├── public/          # Web root (index.php, .htaccess, assets/)
├── src/
│   ├── Controllers/ # One controller per module
│   ├── Helpers/     # Router, RentEngine
│   ├── Middleware/  # AuthMiddleware
│   ├── bootstrap.php
│   ├── config.php
│   ├── db.php
│   └── routes.php
├── views/           # PHP templates per module
│   └── layouts/     # app.php (sidebar shell), auth.php
├── database/
│   ├── migrations/
│   └── seeds/
├── cron/
└── assets/
    ├── css/main.css
    └── js/app.js
```

## Phase roadmap
- [x] **Phase 1** — Foundation: DB, auth, routing, design system, all controllers & views
- [ ] **Phase 2** — Data migration: import existing 22 tenants from spreadsheet
- [ ] **Phase 3** — QA: edge cases, overdue logic, pro-rata testing
- [ ] **Phase 4** — Hardening: rate limiting, file upload for ID proofs, WhatsApp API
