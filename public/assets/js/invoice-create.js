/* RentOps invoice-create.js — live bill calculation preview.
 * SECURITY: replaces 5 inline onchange/oninput handlers and an inline
 * <script> body that embedded EB_PRICE/GST_RATE via json_encode().
 */
(function () {
  'use strict';

  const root = document.getElementById('invoiceCreateRoot');
  if (!root) return;

  const EB_PRICE = parseFloat(root.dataset.ebPrice) || 0;
  const GST_RATE = parseFloat(root.dataset.gstRate) || 0;

  const fmt = v => '₹' + Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  const tenancySelect   = document.getElementById('tenancy_id');
  const monthInput      = document.getElementById('month');
  const ebUnitsInput     = document.getElementById('eb_units');
  const otherChargesInput = document.getElementById('other_charges');
  const otherDescInput   = document.getElementById('other_charges_desc');

  let currentRent = 0;

  function onTenantChange() {
    const opt = tenancySelect.options[tenancySelect.selectedIndex];
    currentRent = parseFloat(opt.dataset.rent || 0);
    recalc();
  }

  function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  function recalc() {
    const ebUnits      = parseFloat(ebUnitsInput.value) || 0;
    const otherCharges = parseFloat(otherChargesInput.value) || 0;
    const otherDesc    = otherDescInput.value.trim() || 'Other charges';
    const month        = monthInput.value;

    const rent  = currentRent;
    const gst   = Math.round(rent * GST_RATE / 100 * 100) / 100;
    const ebAmt = Math.round(ebUnits * EB_PRICE * 100) / 100;
    const total = rent + gst + ebAmt + otherCharges;

    document.getElementById('rentDisplay').value = fmt(rent);
    document.getElementById('gstDisplay').value  = fmt(gst);
    document.getElementById('totalDisplay').textContent = fmt(total);

    const ebAmtRow = document.getElementById('ebAmtRow');
    if (ebUnits > 0 && EB_PRICE > 0) {
      ebAmtRow.style.display = '';
      document.getElementById('ebAmtDisplay').value = fmt(ebAmt) + ` (${ebUnits} × ${fmt(EB_PRICE)})`;
    } else {
      ebAmtRow.style.display = 'none';
    }

    const opt = tenancySelect.options[tenancySelect.selectedIndex];
    const tenantName = opt.textContent?.trim() || '—';
    const monthLabel = month ? new Date(month + '-01').toLocaleDateString('en-IN', { month: 'long', year: 'numeric' }) : '—';

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

  tenancySelect.addEventListener('change', onTenantChange);
  monthInput.addEventListener('change', recalc);
  ebUnitsInput.addEventListener('input', recalc);
  otherChargesInput.addEventListener('input', recalc);
  otherDescInput.addEventListener('input', recalc);

  recalc();
})();
