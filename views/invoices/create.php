<?php
$ebPrice = (float)($property['eb_unit_price'] ?? 0);
$gstRate = (float)($property['rent_gst_rate'] ?? 18);
?>

<div class="d-flex gap-20 align-start" style="flex-wrap:wrap">

  <!-- Form -->
  <div style="flex:1;min-width:340px">
    <div class="card">
      <div class="card-header"><span class="card-title">Bill Details</span></div>
      <div class="card-body">
        <form action="<?= url('/invoices') ?>" method="POST" id="billForm">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

          <!-- Tenant dropdown -->
          <div class="form-group">
            <label class="form-label" for="tenancy_id">Active Tenant <span class="req">*</span></label>
            <select id="tenancy_id" name="tenancy_id" class="form-control" required onchange="onTenantChange(this)">
              <option value="">— Select tenant —</option>
              <?php foreach ($tenants as $t): ?>
              <option value="<?= htmlspecialchars($t['tenancy_id']) ?>"
                      data-rent="<?= (float)$t['agreed_rent'] ?>"
                      data-room="<?= htmlspecialchars($t['room_number']) ?>">
                Room <?= htmlspecialchars($t['room_number']) ?> — <?= htmlspecialchars($t['full_name']) ?>
                (₹<?= number_format((float)$t['agreed_rent']) ?>/mo)
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Month -->
          <div class="form-group">
            <label class="form-label" for="month">Billing Month <span class="req">*</span></label>
            <input type="month" id="month" name="month" class="form-control"
                   value="<?= date('Y-m') ?>" max="<?= date('Y-m') ?>" required onchange="recalc()">
          </div>

          <hr style="margin:20px 0;border:none;border-top:1px solid var(--border)">

          <!-- Rent (read-only) -->
          <div class="form-group">
            <label class="form-label">Rent</label>
            <input type="text" id="rentDisplay" class="form-control" value="₹0" readonly
                   style="background:var(--surface-2);cursor:default">
          </div>

          <!-- GST on Rent -->
          <div class="form-group">
            <label class="form-label">
              GST on Rent
              <span class="badge badge-muted" style="font-size:10px;margin-left:4px"><?= $gstRate ?>%</span>
            </label>
            <input type="text" id="gstDisplay" class="form-control" value="₹0" readonly
                   style="background:var(--surface-2);cursor:default">
          </div>

          <hr style="margin:20px 0;border:none;border-top:1px solid var(--border)">

          <!-- EB Units -->
          <div class="form-group">
            <label class="form-label" for="eb_units">
              Electricity Units Consumed
              <?php if ($ebPrice > 0): ?>
              <span class="text-hint text-sm" style="font-weight:400"> @ ₹<?= number_format($ebPrice, 2) ?>/unit</span>
              <?php else: ?>
              <span class="text-hint text-sm" style="font-weight:400;color:var(--warning)"> (Set unit price in Settings)</span>
              <?php endif; ?>
            </label>
            <input type="number" id="eb_units" name="eb_units" class="form-control"
                   min="0" step="0.01" value="0" placeholder="e.g. 100" oninput="recalc()">
          </div>

          <!-- EB Amount (read-only) -->
          <div class="form-group" id="ebAmtRow" style="display:none">
            <label class="form-label">Electricity Amount</label>
            <input type="text" id="ebAmtDisplay" class="form-control" readonly
                   style="background:var(--surface-2);cursor:default">
          </div>

          <hr style="margin:20px 0;border:none;border-top:1px solid var(--border)">

          <!-- Other charges -->
          <div class="form-group">
            <label class="form-label" for="other_charges">Other Charges</label>
            <div class="d-flex gap-8">
              <input type="text" id="other_charges_desc" name="other_charges_desc" class="form-control"
                     placeholder="Description (e.g. Water, Maintenance)" style="flex:2" oninput="recalc()">
              <input type="number" id="other_charges" name="other_charges" class="form-control"
                     min="0" step="0.01" value="0" placeholder="₹0" style="flex:1" oninput="recalc()">
            </div>
          </div>

          <!-- Total -->
          <div style="background:color-mix(in srgb,var(--primary) 8%,transparent);border:1px solid color-mix(in srgb,var(--primary) 20%,transparent);border-radius:var(--radius);padding:16px 20px;margin:20px 0">
            <div class="d-flex justify-between align-center">
              <span class="fw-600" style="font-size:15px">Total Amount Due</span>
              <span class="fw-600" style="font-size:22px;color:var(--primary)" id="totalDisplay">₹0</span>
            </div>
          </div>

          <div class="d-flex gap-10">
            <button type="submit" class="btn btn-primary" style="flex:1">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              Generate Invoice & PDF
            </button>
            <a href="<?= url('/dues') ?>" class="btn btn-ghost">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Live preview panel -->
  <div style="width:320px;min-width:260px;flex-shrink:0">
    <div class="card" style="position:sticky;top:20px">
      <div class="card-header"><span class="card-title">Invoice Preview</span></div>
      <div class="card-body" id="previewPanel">
        <p class="text-muted text-sm">Select a tenant to preview.</p>
      </div>
    </div>
  </div>

</div>

<script>
const EB_PRICE = <?= json_encode($ebPrice) ?>;
const GST_RATE = <?= json_encode($gstRate) ?>;
const fmt = v => '₹' + Number(v).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});

let currentRent = 0;

function onTenantChange(sel) {
  const opt = sel.options[sel.selectedIndex];
  currentRent = parseFloat(opt.dataset.rent || 0);
  recalc();
}

function recalc() {
  const ebUnits      = parseFloat(document.getElementById('eb_units').value) || 0;
  const otherCharges = parseFloat(document.getElementById('other_charges').value) || 0;
  const otherDesc    = document.getElementById('other_charges_desc').value.trim() || 'Other charges';
  const month        = document.getElementById('month').value;

  const rent    = currentRent;
  const gst     = Math.round(rent * GST_RATE / 100 * 100) / 100;
  const ebAmt   = Math.round(ebUnits * EB_PRICE * 100) / 100;
  const total   = rent + gst + ebAmt + otherCharges;

  document.getElementById('rentDisplay').value = fmt(rent);
  document.getElementById('gstDisplay').value  = fmt(gst);
  document.getElementById('totalDisplay').textContent = fmt(total);

  if (ebUnits > 0 && EB_PRICE > 0) {
    document.getElementById('ebAmtRow').style.display = '';
    document.getElementById('ebAmtDisplay').value = fmt(ebAmt) + ` (${ebUnits} × ${fmt(EB_PRICE)})`;
  } else {
    document.getElementById('ebAmtRow').style.display = 'none';
  }

  const sel = document.getElementById('tenancy_id');
  const opt = sel.options[sel.selectedIndex];
  const tenantName = opt.textContent?.trim() || '—';
  const monthLabel = month ? new Date(month + '-01').toLocaleDateString('en-IN', {month:'long', year:'numeric'}) : '—';

  document.getElementById('previewPanel').innerHTML = !currentRent ? '<p class="text-muted text-sm">Select a tenant to preview.</p>' : `
    <div style="font-size:12px;line-height:2">
      <div class="d-flex justify-between"><span class="text-muted">Tenant</span><span class="fw-600">${escHtml(tenantName)}</span></div>
      <div class="d-flex justify-between"><span class="text-muted">Period</span><span>${escHtml(monthLabel)}</span></div>
      <hr style="border:none;border-top:1px solid var(--border);margin:8px 0">
      <div class="d-flex justify-between"><span class="text-muted">Rent</span><span>${fmt(rent)}</span></div>
      <div class="d-flex justify-between"><span class="text-muted">GST (${GST_RATE}%)</span><span>${fmt(gst)}</span></div>
      ${ebUnits > 0 ? `<div class="d-flex justify-between"><span class="text-muted">EB (${ebUnits} units)</span><span>${fmt(ebAmt)}</span></div>` : ''}
      ${otherCharges > 0 ? `<div class="d-flex justify-between"><span class="text-muted">${escHtml(otherDesc)}</span><span>${fmt(otherCharges)}</span></div>` : ''}
      <hr style="border:none;border-top:2px solid var(--primary);margin:8px 0">
      <div class="d-flex justify-between align-center">
        <span class="fw-600" style="font-size:14px">Total</span>
        <span class="fw-600" style="font-size:18px;color:var(--primary)">${fmt(total)}</span>
      </div>
    </div>`;
}

function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
recalc();
</script>
