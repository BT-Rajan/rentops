# RentOps MVP v1

Enterprise-grade rent operations platform for a single 22-room property.
Built with PHP 8.2, MySQL 8, Vanilla JS, HTML/CSS — zero framework dependencies.

## Quick start

### Requirements
- PHP 8.2+ (`pdo_mysql`, `mbstring`, `fileinfo`, `openssl`)
- MySQL 8.0+
- Apache 2.4+ with `mod_rewrite` + `mod_headers`

### Database setup
```bash
mysql -u root -p -e "
  CREATE DATABASE rentops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'rentops_user'@'localhost' IDENTIFIED BY 'CHANGE_ME';
  GRANT SELECT,INSERT,UPDATE,DELETE ON rentops.* TO 'rentops_user'@'localhost';
"
mysql -u rentops_user -p rentops < database/migrations/001_initial_schema.sql
mysql -u rentops_user -p rentops < database/migrations/002_phase2.sql
mysql -u rentops_user -p rentops < database/migrations/003_rent_changes.sql
mysql -u rentops_user -p rentops < database/migrations/004_rate_limits_audit.sql
mysql -u rentops_user -p rentops < database/seeds/001_seed.sql
```

### Environment
```bash
cp .env.example .env
# Set DB_USER, DB_PASS, APP_URL
```

### Apache vhost
Point `DocumentRoot` to `public/` and enable `AllowOverride All`.

### Default login
- Email: `owner@rentops.local`
- Password: `RentOps@2024`
- **Change immediately** via Settings → Change password

### Cron
```cron
0 6 1 * * php /path/to/rentops/cron/generate_invoices.php
0 7 * * * php /path/to/rentops/cron/refresh_overdue.php
0 3 * * 0 php /path/to/rentops/cron/cleanup.php
```

---

## Features

| Module            | What it does |
|-------------------|---|
| **Dashboard**     | KPI cards (collection %, collected, outstanding), 6-month trend chart, live AJAX refresh |
| **Rooms**         | Grid/list toggle, occupancy bar, room detail with 12-month invoice history |
| **Tenants**       | Search, filter active/vacated, outstanding balance with overdue month count |
| **Move-in**       | Room assignment, pro-rata first invoice preview, security deposit |
| **Move-out**      | Pro-rata final invoice, same-month exit detection, deposit reconciliation |
| **Rent engine**   | Monthly invoice generation, pro-rata, accumulated overdue, mid-tenancy rent changes |
| **Payments**      | Record cash/UPI/bank/other, partial payments, overpayment detection, printable receipt |
| **Dues**          | Filter by status, bulk select, day-level overdue counter, one-click pay |
| **Reminders**     | Select overdue tenants, WhatsApp message generator, copy/open in WhatsApp |
| **Reports**       | Monthly collection summary, room-wise breakdown, CSV export |
| **Import**        | Drag-and-drop CSV bulk import, dry-run validation preview, invoice backfill |
| **Audit / QA**    | Gap detection, overpayment flags, auto-fix missing invoices, paginated audit log |
| **Settings**      | Property config, password change with strength meter, system info |

## Architecture

```
rentops/
├── public/            ← Web root (index.php, .htaccess, assets/, uploads/)
├── src/
│   ├── Controllers/   ← 13 controllers, all extend BaseController
│   ├── Helpers/       ← RentEngine, Router, UuidHelper, RateLimiter, AuditLog
│   ├── Middleware/    ← AuthMiddleware (session + remember-me)
│   ├── bootstrap.php  ← Autoloader, config, DB, session, error handler
│   ├── config.php
│   ├── db.php         ← PDO singleton
│   ├── error_handler.php
│   └── routes.php     ← All 30 routes in one place
├── views/             ← PHP templates per module + layouts/
├── database/
│   ├── migrations/    ← 4 SQL files, run in order
│   └── seeds/         ← Owner user + property + 22 rooms
├── cron/              ← 3 scripts: invoices, overdue, cleanup
├── assets/
│   ├── css/main.css       ← Design system (tokens, components)
│   └── css/responsive.css ← Breakpoints + print styles
└── DEPLOY.md          ← Full production checklist
```

## Phase roadmap
- [x] **Phase 1** — Foundation: DB, auth, routing, design system, all controllers & views
- [x] **Phase 2** — Data migration: CSV import, file uploads, payment receipts, rent changes
- [x] **Phase 3** — QA & hardening: RentEngine edge cases, rate limiting, audit log, error handling
- [x] **Phase 4** — Production: password change, cron hardening, responsive QA, deployment guide
