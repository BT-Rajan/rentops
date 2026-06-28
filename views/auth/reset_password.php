<div class="auth-card">
  <h2><?= __('auth.reset_title') ?></h2>

  <?php if (!empty($error)): ?>
    <div class="flash flash-error" style="margin-bottom:20px">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($expired)): ?>
    <p style="font-size:14px;color:var(--text-secondary);margin-bottom:16px">
      This link has expired or already been used.
      <a href="<?= url('/forgot-password') ?>" style="color:var(--c-primary)">Request a new one →</a>
    </p>
  <?php else: ?>

  <form action="<?= url('/reset-password') ?>" method="POST" novalidate>
    <input type="hidden" name="_csrf"  value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="token"  value="<?= htmlspecialchars($token ?? '') ?>">

    <div class="form-group">
      <label class="form-label" for="password">New password <span class="req">*</span></label>
      <input class="form-control" type="password" id="password" name="password"
             autocomplete="new-password" autofocus required minlength="8">
      <span style="font-size:12px;color:var(--text-secondary)">Minimum 8 characters</span>
    </div>

    <div class="form-group">
      <label class="form-label" for="password_confirm">Confirm password <span class="req">*</span></label>
      <input class="form-control" type="password" id="password_confirm" name="password_confirm"
             autocomplete="new-password" required minlength="8">
    </div>

    <button type="submit" class="btn btn-primary w-full" style="justify-content:center;padding:11px">
      Update password
    </button>
  </form>

  <?php endif; ?>

  <p style="text-align:center;margin-top:20px;font-size:13px;color:var(--text-secondary)">
    <a href="<?= url('/login') ?>" style="color:var(--c-primary)">Back to sign in</a>
  </p>
</div>
