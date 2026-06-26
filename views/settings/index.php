<div style="max-width:560px;display:flex;flex-direction:column;gap:20px">

  <!-- Property settings -->
  <div class="card">
    <div class="card-header"><span class="card-title">Property</span></div>
    <div class="card-body">
      <form action="/settings" method="POST" novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="form-group">
          <label class="form-label" for="name">Property name</label>
          <input type="text" id="name" name="name" class="form-control"
                 value="<?= htmlspecialchars($property['name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="address">Address</label>
          <textarea id="address" name="address" class="form-control" rows="2"><?= htmlspecialchars($property['address'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="default_due_day">Default rent due day</label>
          <select id="default_due_day" name="default_due_day" class="form-control" style="max-width:200px">
            <?php for ($d = 1; $d <= 28; $d++): ?>
              <option value="<?= $d ?>" <?= ($property['default_due_day'] ?? 5) == $d ? 'selected' : '' ?>>
                <?= $d ?><?= match(true) { $d===1=>'st',$d===2=>'nd',$d===3=>'rd',default=>'th' } ?> of month
              </option>
            <?php endfor; ?>
          </select>
          <div class="form-hint">Applied to new tenancies. Can be overridden per tenant.</div>
        </div>

        <button type="submit" class="btn btn-primary">Save property settings</button>
      </form>
    </div>
  </div>

  <!-- Account -->
  <div class="card">
    <div class="card-header"><span class="card-title">Account</span></div>
    <div class="card-body">
      <div class="d-flex align-center gap-12 mb-20">
        <div class="avatar" style="width:48px;height:48px;font-size:18px">
          <?= strtoupper(substr($user['name'], 0, 1)) ?>
        </div>
        <div>
          <div class="fw-600"><?= htmlspecialchars($user['name']) ?></div>
          <div class="text-sm text-muted">Property owner</div>
        </div>
      </div>

      <!-- Password change -->
      <div style="border-top:1px solid var(--border);padding-top:16px">
        <div class="text-sm fw-600 mb-12">Change password</div>
        <form action="/settings/password" method="POST" novalidate id="pwForm">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

          <div class="form-group">
            <label class="form-label" for="current_password">Current password</label>
            <div style="position:relative">
              <input type="password" id="current_password" name="current_password"
                     class="form-control" autocomplete="current-password" required>
              <button type="button" class="pw-toggle" data-target="current_password"
                      style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-hint)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="new_password">New password</label>
              <div style="position:relative">
                <input type="password" id="new_password" name="new_password"
                       class="form-control" autocomplete="new-password"
                       oninput="checkStrength(this.value)" required>
                <button type="button" class="pw-toggle" data-target="new_password"
                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-hint)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
              <!-- Strength bar -->
              <div style="margin-top:6px">
                <div class="progress"><div id="strengthBar" class="progress-bar" style="width:0%;transition:width .3s,background .3s"></div></div>
                <div id="strengthLabel" class="text-xs text-muted mt-4"></div>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" for="confirm_password">Confirm new password</label>
              <input type="password" id="confirm_password" name="confirm_password"
                     class="form-control" autocomplete="new-password"
                     oninput="checkMatch()" required>
              <div id="matchHint" class="form-hint" style="min-height:16px"></div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary" id="pwSubmit" disabled>Change password</button>
        </form>
      </div>
    </div>
  </div>

  <!-- System info -->
  <div class="card">
    <div class="card-header"><span class="card-title">System info</span></div>
    <div class="card-body">
      <div style="display:flex;flex-direction:column;gap:10px">
        <div class="d-flex justify-between text-sm">
          <span class="text-muted">PHP version</span>
          <span class="fw-600"><?= PHP_VERSION ?></span>
        </div>
        <div class="d-flex justify-between text-sm">
          <span class="text-muted">Server time</span>
          <span><?= date('d M Y, H:i:s') ?></span>
        </div>
        <div class="d-flex justify-between text-sm">
          <span class="text-muted">Total rooms</span>
          <span class="fw-600"><?= $property['total_rooms'] ?? 22 ?></span>
        </div>
        <div class="d-flex justify-between text-sm">
          <span class="text-muted">RentOps version</span>
          <span><?= APP_VERSION ?></span>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
// Show/hide password toggles
document.querySelectorAll('.pw-toggle').forEach(btn => {
  btn.addEventListener('click', function () {
    const input = document.getElementById(this.dataset.target);
    input.type  = input.type === 'password' ? 'text' : 'password';
  });
});

// Password strength meter
function checkStrength(pw) {
  const bar   = document.getElementById('strengthBar');
  const label = document.getElementById('strengthLabel');
  let score = 0;
  if (pw.length >= 8)                      score++;
  if (pw.length >= 12)                     score++;
  if (/[A-Z]/.test(pw))                   score++;
  if (/[0-9]/.test(pw))                   score++;
  if (/[^A-Za-z0-9]/.test(pw))            score++;

  const levels = [
    { pct: '0%',   color: 'var(--c-danger)',   text: '' },
    { pct: '20%',  color: '#E24B4A',           text: 'Very weak' },
    { pct: '40%',  color: '#EF9F27',           text: 'Weak' },
    { pct: '60%',  color: '#D4BF00',           text: 'Fair' },
    { pct: '80%',  color: 'var(--c-primary)',  text: 'Strong' },
    { pct: '100%', color: '#0F6E56',           text: 'Very strong' },
  ];
  const l = levels[score] || levels[0];
  bar.style.width      = l.pct;
  bar.style.background = l.color;
  label.textContent    = l.text;
  checkSubmit();
}

function checkMatch() {
  const nw   = document.getElementById('new_password').value;
  const cn   = document.getElementById('confirm_password').value;
  const hint = document.getElementById('matchHint');
  if (!cn) { hint.textContent = ''; return; }
  if (nw === cn) {
    hint.textContent = '✓ Passwords match';
    hint.style.color = 'var(--c-success)';
  } else {
    hint.textContent = 'Passwords do not match';
    hint.style.color = 'var(--c-danger)';
  }
  checkSubmit();
}

function checkSubmit() {
  const cur  = document.getElementById('current_password').value;
  const nw   = document.getElementById('new_password').value;
  const cn   = document.getElementById('confirm_password').value;
  const ok   = cur.length > 0 && nw.length >= 8 && nw === cn;
  document.getElementById('pwSubmit').disabled = !ok;
}

document.getElementById('current_password').addEventListener('input', checkSubmit);
</script>
