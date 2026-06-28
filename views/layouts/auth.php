<!DOCTYPE html>
<html lang="<?= \App\Helpers\Lang::current() === 'ta' ? 'ta' : 'en' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= __("auth.sign_in") ?> — <?= __("app_name") ?></title>
  <link rel="stylesheet" href="<?= asset("/assets/css/main.css") ?>">
  <link rel="stylesheet" href="<?= asset("/assets/css/responsive.css") ?>">
  <style>
    body { background: var(--bg); display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .auth-wrap { width: 100%; max-width: 400px; padding: 24px 16px; }
    .auth-brand { text-align: center; margin-bottom: 32px; }
    .auth-brand h1 { font-size: 28px; font-weight: 800; color: var(--c-primary); letter-spacing: -.5px; }
    .auth-brand p  { font-size: 14px; color: var(--text-secondary); margin-top: 4px; }
    .auth-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 32px; box-shadow: var(--shadow); }
    .auth-card h2 { font-size: 20px; font-weight: 700; margin-bottom: 24px; }
  </style>
</head>
<body>
<div class="auth-wrap">
  <div class="auth-brand">
    <h1><?= __("app_name") ?></h1>
    
  </div>
  <?= $content ?>
</div>
</body>
</html>
