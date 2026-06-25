<div class="auth-card">
  <h2>Sign in</h2>

  <?php if ($error): ?>
    <div class="flash flash-error" style="margin-bottom:20px"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form action="/login" method="POST" novalidate>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <div class="form-group">
      <label class="form-label" for="email">Email <span class="req">*</span></label>
      <input class="form-control" type="email" id="email" name="email"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             autocomplete="email" autofocus required>
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Password <span class="req">*</span></label>
      <input class="form-control" type="password" id="password" name="password"
             autocomplete="current-password" required>
    </div>

    <div class="form-group" style="display:flex;align-items:center;gap:8px">
      <input type="checkbox" id="remember" name="remember" value="1" style="width:16px;height:16px;cursor:pointer">
      <label for="remember" style="font-size:13px;cursor:pointer;color:var(--text-secondary)">Remember me for 7 days</label>
    </div>

    <button type="submit" class="btn btn-primary w-full" style="justify-content:center;padding:11px">Sign in</button>
  </form>
</div>
