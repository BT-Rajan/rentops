<div style="max-width:560px;display:flex;flex-direction:column;gap:20px">

  <!-- Property settings -->
  <div class="card">
    <div class="card-header"><span class="card-title"><?= __('settings.property') ?></span></div>
    <div class="card-body">
      <form action="<?= url("/settings") ?>" method="POST" novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="form-group">
          <label class="form-label" for="name"><?= __('settings.property_name') ?></label>
          <input type="text" id="name" name="name" class="form-control"
                 value="<?= htmlspecialchars($property['name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="address"><?= __('common.address') ?></label>
          <textarea id="address" name="address" class="form-control" rows="2"><?= htmlspecialchars($property['address'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="default_due_day"><?= __('settings.default_due_day') ?></label>
          <select id="default_due_day" name="default_due_day" class="form-control" style="max-width:200px">
            <?php for ($d = 1; $d <= 28; $d++): ?>
              <option value="<?= $d ?>" <?= ($property['default_due_day'] ?? 5) == $d ? 'selected' : '' ?>>
                <?= $d ?><?= match(true) { $d===1=>'st',$d===2=>'nd',$d===3=>'rd',default=>'th' } ?> of month
              </option>
            <?php endfor; ?>
          </select>
          <div class="form-hint"><?= __('settings.due_day_hint') ?></div>
        </div>

        <button type="submit" class="btn btn-primary"><?= __('settings.save_property') ?></button>
      </form>
    </div>
  </div>

  <!-- Account -->
  <div class="card">
    <div class="card-header"><span class="card-title"><?= __('settings.account') ?></span></div>
    <div class="card-body">
      <div class="d-flex align-center gap-12 mb-20">
        <div class="avatar" style="width:48px;height:48px;font-size:18px">
          <?= strtoupper(substr($user['name'], 0, 1)) ?>
        </div>
        <div>
          <div class="fw-600"><?= htmlspecialchars($user['name']) ?></div>
          <div class="text-sm text-muted"><?= __('tenants.property_owner') ?></div>
        </div>
      </div>

      <!-- Password change -->
      <div style="border-top:1px solid var(--border);padding-top:16px">
        <div class="text-sm fw-600 mb-12"><?= __('settings.change_password') ?></div>
        <form action="<?= url("/settings/password") ?>" method="POST" novalidate id="pwForm">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

          <div class="form-group">
            <label class="form-label" for="current_password"><?= __('settings.current_password') ?></label>
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
              <label class="form-label" for="new_password"><?= __('settings.new_password') ?></label>
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
              <label class="form-label" for="confirm_password"><?= __('settings.confirm_password') ?></label>
              <input type="password" id="confirm_password" name="confirm_password"
                     class="form-control" autocomplete="new-password"
                     oninput="checkMatch()" required>
              <div id="matchHint" class="form-hint" style="min-height:16px"></div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary" id="pwSubmit" disabled><?= __('settings.change_password') ?></button>
        </form>
      </div>
    </div>
  </div>

  <!-- System info -->
  <div class="card">
    <div class="card-header"><span class="card-title"><?= __('settings.system_info') ?></span></div>
    <div class="card-body">
      <div style="display:flex;flex-direction:column;gap:10px">
        <div class="d-flex justify-between text-sm">
          <span class="text-muted"><?= __('settings.php_version') ?></span>
          <span class="fw-600"><?= PHP_VERSION ?></span>
        </div>
        <div class="d-flex justify-between text-sm">
          <span class="text-muted"><?= __('settings.server_time') ?></span>
          <span><?= date('d M Y, H:i:s') ?></span>
        </div>
        <div class="d-flex justify-between text-sm">
          <span class="text-muted"><?= __('settings.total_rooms') ?></span>
          <span class="fw-600"><?= $property['total_rooms'] ?? 22 ?></span>
        </div>
        <div class="d-flex justify-between text-sm">
          <span class="text-muted"><?= __('settings.version') ?></span>
          <span><?= APP_VERSION ?></span>
        </div>
      </div>
    </div>
  </div>


  <!-- Billing & EB -->
  <div class="card mb-16">
    <div class="card-header"><span class="card-title">Billing Settings</span></div>
    <div class="card-body">
      <?php
        $unpaidInvoiceCount = (int)\App\DB::scalar("
            SELECT COUNT(*) FROM rent_invoices WHERE status IN ('unpaid','partial','overdue')
        ");
      ?>
      <?php if ($unpaidInvoiceCount > 0): ?>
      <div style="background:color-mix(in srgb, var(--warning, #F59E0B) 10%, transparent);border:1px solid var(--warning, #F59E0B);border-radius:var(--radius);padding:10px 12px;margin-bottom:16px">
        <div class="text-sm fw-600" style="color:#92400E">Rate changes apply forward-only</div>
        <div class="text-xs text-muted mt-4">
          Changing the unit price or GST rate here only affects invoices generated after saving.
          You currently have <strong><?= $unpaidInvoiceCount ?></strong> unpaid invoice(s) billed at the existing rate —
          they will not be recalculated automatically.
        </div>
      </div>
      <?php endif; ?>
      <form action="<?= url('/settings/billing') ?>" method="POST">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <div class="form-group">
          <label class="form-label" for="eb_unit_price">Electricity Unit Price (₹ per unit)</label>
          <input type="number" id="eb_unit_price" name="eb_unit_price" class="form-control"
                 min="0" step="0.01" value="<?= htmlspecialchars($property['eb_unit_price'] ?? '0') ?>"
                 placeholder="e.g. 10">
          <div class="form-hint">Used to auto-calculate EB charges in invoices (units × price). Applies to new invoices only.</div>
        </div>
        <div class="form-group">
          <label class="form-label" for="rent_gst_rate">GST on Rent (%)</label>
          <input type="number" id="rent_gst_rate" name="rent_gst_rate" class="form-control"
                 min="0" max="100" step="0.01" value="<?= htmlspecialchars($property['rent_gst_rate'] ?? '18') ?>">
          <div class="form-hint">Applied to rent amount only. Electricity has no GST. Applies to new invoices only.</div>
        </div>
        <button type="submit" class="btn btn-primary">Save Billing Settings</button>
      </form>
    </div>
  </div>

  <!-- Razorpay -->
  <div class="card mb-16">
    <div class="card-header"><span class="card-title">Razorpay Integration</span></div>
    <div class="card-body">
      <form action="<?= url('/settings/razorpay') ?>" method="POST">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <div class="form-group">
          <label class="form-label" for="razorpay_key_id">Razorpay Key ID</label>
          <input type="text" id="razorpay_key_id" name="razorpay_key_id" class="form-control"
                 value="<?= htmlspecialchars($property['razorpay_key_id'] ?? '') ?>"
                 placeholder="rzp_live_...">
        </div>
        <div class="form-group">
          <label class="form-label" for="razorpay_key_secret">Razorpay Key Secret</label>
          <input type="password" id="razorpay_key_secret" name="razorpay_key_secret" class="form-control"
                 placeholder="<?= !empty($property['razorpay_key_secret']) ? '••••••••••• (saved)' : 'Enter secret key' ?>">
          <div class="form-hint">Stored encrypted. Leave blank to keep existing secret.</div>
        </div>
        <div class="form-group">
          <label class="form-label" for="razorpay_webhook_secret">Webhook Secret</label>
          <input type="password" id="razorpay_webhook_secret" name="razorpay_webhook_secret" class="form-control"
                 placeholder="<?= !empty($property['razorpay_webhook_secret']) ? '••••••••••• (saved)' : 'Enter webhook secret' ?>">
          <div class="form-hint">
            Create a webhook in your Razorpay Dashboard → Settings → Webhooks pointing to:<br>
            <code style="font-size:11px;background:var(--surface-2);padding:2px 6px;border-radius:4px"><?= htmlspecialchars(rtrim($_ENV['APP_URL'] ?? '', '/')) ?>/payments/razorpay/webhook</code><br>
            Subscribe to the <strong>payment_link.paid</strong> event, then paste its secret here.
            Without this, payments made via the link will not auto-reconcile — you'll need to record them manually.
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Save Razorpay Keys</button>
      </form>
    </div>
  </div>

  <!-- Language -->
  <div class="card">
    <div class="card-header"><span class="card-title"><?= __('settings.language_settings') ?></span></div>
    <div class="card-body">
      <form action="<?= url('/lang/switch') ?>" method="POST">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <div class="form-group">
          <label class="form-label"><?= __('common.language') ?></label>
          <div class="d-flex gap-12 align-center">
            <label class="d-flex align-center gap-8" style="cursor:pointer;font-size:14px">
              <input type="radio" name="locale" value="en" <?= \App\Helpers\Lang::current() === 'en' ? 'checked' : '' ?>>
              🇬🇧 English
            </label>
            <label class="d-flex align-center gap-8" style="cursor:pointer;font-size:14px">
              <input type="radio" name="locale" value="ta" <?= \App\Helpers\Lang::current() === 'ta' ? 'checked' : '' ?>>
              🇮🇳 தமிழ் (Tamil)
            </label>
          </div>
        </div>
        <button type="submit" class="btn btn-primary"><?= __('settings.save_language') ?></button>
      </form>
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
    { pct: '20%',  color: '#E24B4A',           text: <?= json_encode(__('settings.very_weak')) ?> },
    { pct: '40%',  color: '#EF9F27',           text: <?= json_encode(__('settings.weak')) ?> },
    { pct: '60%',  color: '#D4BF00',           text: <?= json_encode(__('settings.fair')) ?> },
    { pct: '80%',  color: 'var(--c-primary)',  text: <?= json_encode(__('settings.strong')) ?> },
    { pct: '100%', color: '#0F6E56',           text: <?= json_encode(__('settings.very_strong')) ?> },
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
