<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>404 — RentOps</title>
<link rel="stylesheet" href="<?= asset("/assets/css/main.css") ?>">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh">
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <p style="font-size:18px;font-weight:600;margin-bottom:8px"><?= __('errors.page_not_found') ?></p>
    <p style="margin-bottom:16px"><?= __('errors.page_not_found_desc') ?></p>
    <a href="<?= url("/") ?>" class="btn btn-primary">Go to dashboard</a>
  </div>
</body>
</html>
