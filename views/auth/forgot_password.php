<div class="auth-card">
  <h2>Reset password</h2>
  <p style="font-size:14px;color:var(--text-secondary);margin-bottom:20px">
    Enter your account email and we'll send a reset link valid for 30 minutes.
  </p>

  <?php if (!empty($success)): ?>
    <div class="flash flash-success" style="margin-bottom:20px">
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="flash flash-error" style="margin-bottom:20px">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form action="<?= url('/forgot-password') ?>" method="POST" novalidate>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <div class="form-group">
      <label class="form-label" for="email">Email <span class="req">*</span></label>
      <input class="form-control" type="email" id="email" name="email"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             autocomplete="email" autofocus required>
    </div>

    <button type="submit" class="btn btn-primary w-full" style="justify-content:center;padding:11px">
      Send reset link
    </button>
  </form>

  <p style="text-align:center;margin-top:20px;font-size:13px;color:var(--text-secondary)">
    <a href="<?= url('/login') ?>" style="color:var(--c-primary)">Back to sign in</a>
  </p>
</div>
