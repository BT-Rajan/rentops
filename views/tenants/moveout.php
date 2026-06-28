<div style="max-width:560px">
  <a href="<?= url("/tenants/" . htmlspecialchars($tenant['id'])) ?>" class="btn btn-ghost btn-sm" style="padding-left:0;margin-bottom:20px">← Back to tenant</a>

  <div class="card">
    <div class="card-header">
      <span class="card-title">Move-out — <?= htmlspecialchars($tenant['full_name']) ?></span>
    </div>
    <div class="card-body">

      <?php if (!$tenancy): ?>
        <div class="flash flash-error">No active tenancy found for this tenant.</div>
      <?php else: ?>

      <!-- Tenancy summary -->
      <div style="background:var(--surface-2);border-radius:var(--radius);padding:14px 16px;margin-bottom:20px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div><div class="text-xs text-muted">Room</div><div class="fw-600">Room <?= htmlspecialchars($tenancy['room_number']) ?></div></div>
          <div><div class="text-xs text-muted">Move-in</div><div class="fw-600"><?= htmlspecialchars($tenancy['move_in_date']) ?></div></div>
          <div><div class="text-xs text-muted">Monthly rent</div><div class="fw-600">₹<?= number_format((float)$tenancy['agreed_rent']) ?></div></div>
          <div><div class="text-xs text-muted">Deposit held</div><div class="fw-600">₹<?= number_format((float)$tenancy['security_deposit']) ?></div></div>
        </div>
      </div>

      <form action="<?= url("/tenants/" . htmlspecialchars($tenant['id']) . "/moveout") ?>" method="POST" novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="form-group">
          <label class="form-label" for="move_out_date">Move-out date <span class="req">*</span></label>
          <input type="date" id="move_out_date" name="move_out_date" class="form-control"
                 value="<?= date('Y-m-d') ?>"
                 min="<?= htmlspecialchars($tenancy['move_in_date']) ?>"
                 required>
          <div class="form-hint" id="proRataHint"></div>
          <div id="sameMonthWarn" style="display:none;margin-top:6px" class="flash flash-info" style="padding:8px 12px">
            ⚠ Same-month exit — only days occupied will be charged.
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="deposit_deduction">Deposit deduction (₹)</label>
          <input type="number" id="deposit_deduction" name="deposit_deduction" class="form-control"
                 value="0" min="0" max="<?= (float)$tenancy['security_deposit'] ?>" step="100"
                 oninput="updateRefund()">
          <div class="form-hint">Damage, unpaid dues, cleaning, etc.</div>
        </div>

        <!-- Deposit reconciliation preview -->
        <div style="background:var(--surface-2);border-radius:var(--radius);padding:14px 16px;margin-bottom:20px" id="reconcileBox">
          <div class="text-sm fw-600 mb-8">Deposit reconciliation</div>
          <div class="d-flex justify-between text-sm mb-4">
            <span class="text-muted">Deposit held</span>
            <span>₹<?= number_format((float)$tenancy['security_deposit']) ?></span>
          </div>
          <div class="d-flex justify-between text-sm mb-4">
            <span class="text-muted">Deduction</span>
            <span class="text-danger" id="deductionDisplay">₹0</span>
          </div>
          <div style="border-top:1px solid var(--border);margin:8px 0"></div>
          <div class="d-flex justify-between text-sm fw-600">
            <span>Refund to tenant</span>
            <span class="text-success" id="refundDisplay">₹<?= number_format((float)$tenancy['security_deposit']) ?></span>
          </div>
        </div>

        <div class="d-flex gap-12">
          <button type="submit" class="btn btn-danger" onclick="return confirm('Confirm move-out for <?= htmlspecialchars(addslashes($tenant['full_name'])) ?>?')">
            Confirm move-out
          </button>
          <a href="<?= url("/tenants/" . htmlspecialchars($tenant['id'])) ?>" class="btn btn-secondary"><?= __('common.cancel') ?></a>
        </div>
      </form>

      <?php endif; ?>
    </div>
  </div>
</div>

<script>
const deposit  = <?= (float)($tenancy['security_deposit'] ?? 0) ?>;
const rent     = <?= (float)($tenancy['agreed_rent'] ?? 0) ?>;
const moveInDate = '<?= htmlspecialchars($tenancy['move_in_date'] ?? '') ?>';

function updateRefund() {
  const ded = Math.min(parseFloat(document.getElementById('deposit_deduction').value) || 0, deposit);
  document.getElementById('deductionDisplay').textContent = '₹' + ded.toLocaleString('en-IN');
  document.getElementById('refundDisplay').textContent    = '₹' + Math.max(0, deposit - ded).toLocaleString('en-IN');
}

function updateProRata() {
  const date = document.getElementById('move_out_date').value;
  const hint = document.getElementById('proRataHint');
  const warn = document.getElementById('sameMonthWarn');
  if (!date) { hint.textContent = ''; return; }

  const outDate = new Date(date + 'T00:00:00');
  const inDate  = new Date(moveInDate + 'T00:00:00');
  const day     = outDate.getDate();
  const days    = new Date(outDate.getFullYear(), outDate.getMonth() + 1, 0).getDate();

  const sameMonth = inDate.getFullYear() === outDate.getFullYear() &&
                    inDate.getMonth()     === outDate.getMonth();

  if (sameMonth) {
    const occupied = day - inDate.getDate() + 1;
    const pro = Math.round((rent / days) * Math.max(1, occupied));
    hint.textContent = `Same-month exit: ₹${pro.toLocaleString('en-IN')} (${occupied} day(s) occupied)`;
    if (warn) warn.style.display = 'block';
  } else if (day >= days) {
    hint.textContent = 'Full month — no pro-rata.';
    if (warn) warn.style.display = 'none';
  } else {
    const pro = Math.round((rent / days) * day);
    hint.textContent = `Final invoice: ₹${pro.toLocaleString('en-IN')} (${day} of ${days} days)`;
    if (warn) warn.style.display = 'none';
  }
}

document.getElementById('move_out_date').addEventListener('change', updateProRata);
updateProRata();
</script>
