<div class="auth-card">
  <h2><?= __('auth.sign_in') ?></h2>

  <?php if ($error): ?>
    <div class="flash flash-error" style="margin-bottom:20px">
      <?= htmlspecialchars($error) ?>
      <?php if ($remaining !== null && $remaining > 0): ?>
        <div class="text-xs mt-4"><?= $remaining ?> attempt<?= $remaining !== 1 ? 's' : '' ?> remaining before lockout.</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form action="<?= url("/login") ?>" method="POST" novalidate>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <div class="form-group">
      <label class="form-label" for="email"><?= __('auth.email') ?> <span class="req">*</span></label>
      <input class="form-control" type="email" id="email" name="email"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             autocomplete="email" autofocus required>
    </div>

    <div class="form-group">
      <label class="form-label" for="password"><?= __('auth.password') ?> <span class="req">*</span></label>
      <input class="form-control" type="password" id="password" name="password"
             autocomplete="current-password" required>
    </div>

    <div class="form-group" style="display:flex;align-items:center;gap:8px">
      <input type="checkbox" id="remember" name="remember" value="1" style="width:16px;height:16px;cursor:pointer">
      <label for="remember" style="font-size:13px;cursor:pointer;color:var(--text-secondary)"><?= __('auth.remember_me') ?></label>
    </div>

    <button type="submit" class="btn btn-primary w-full" style="justify-content:center;padding:11px"><?= __('auth.sign_in') ?></button>
  </form>

  <p style="text-align:center;margin-top:16px;font-size:13px;color:var(--text-secondary)">
    <a href="<?= url('/forgot-password') ?>" style="color:var(--c-primary)"><?= __('auth.forgot_password') ?></a>
  </p>
</div>
