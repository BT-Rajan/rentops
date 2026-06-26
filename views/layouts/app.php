<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#0F6E56">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?= htmlspecialchars($pageTitle ?? 'RentOps') ?> — RentOps</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="stylesheet" href="/assets/css/main.css">
  <link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body>

<div class="app-layout">

  <!-- Sidebar overlay (mobile) -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <h1>RentOps</h1>
      <p><?= htmlspecialchars(\App\DB::scalar('SELECT name FROM properties LIMIT 1') ?? 'Property') ?></p>
    </div>

    <nav class="sidebar-nav" aria-label="Main navigation">
      <span class="nav-section-label">Overview</span>
      <?php $cur = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); ?>
      <a href="/" class="nav-item <?= ($cur === '/' || $cur === '/dashboard') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Dashboard
      </a>

      <span class="nav-section-label">Property</span>
      <a href="/rooms" class="nav-item <?= str_starts_with($cur, '/rooms') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
        Rooms
      </a>
      <a href="/tenants" class="nav-item <?= str_starts_with($cur, '/tenants') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
        Tenants
      </a>

      <span class="nav-section-label">Finance</span>
      <a href="/dues" class="nav-item <?= str_starts_with($cur, '/dues') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Dues & Overdue
      </a>
      <a href="/payments/new" class="nav-item <?= str_starts_with($cur, '/payments') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        Record Payment
      </a>
      <a href="/reminders" class="nav-item <?= str_starts_with($cur, '/reminders') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        Reminders
      </a>
      <a href="/reports" class="nav-item <?= str_starts_with($cur, '/reports') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Reports
      </a>

      <span class="nav-section-label">System</span>
      <a href="/audit" class="nav-item <?= str_starts_with($cur, '/audit') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        Audit & QA
      </a>
      <a href="/import" class="nav-item <?= str_starts_with($cur, '/import') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Import Data
      </a>
      <a href="/settings" class="nav-item <?= str_starts_with($cur, '/settings') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
        Settings
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="owner-name"><?= htmlspecialchars($user['name'] ?? 'Owner') ?></div>
      <form action="/logout" method="POST" style="margin:0">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <button type="submit" class="logout-btn">Sign out</button>
      </form>
    </div>
  </aside>

  <!-- Main -->
  <div class="main-wrap">
    <header class="topbar">
      <div class="d-flex align-center gap-12">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <h2 class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'RentOps') ?></h2>
      </div>
      <div class="topbar-actions">
        <a href="/payments/new" class="btn btn-primary btn-sm">+ Record Payment</a>
      </div>
    </header>

    <main class="page-content">
      <?php if (!empty($flash)): ?>
        <div class="flash flash-<?= $flash['type'] ?>">
          <?= htmlspecialchars($flash['message']) ?>
        </div>
      <?php endif; ?>

      <?= $content ?>
    </main>
  </div>

</div>

<script src="/assets/js/app.js"></script>
</body>
</html>
