<?php
$fmt = fn(float $v): string => '₹' . number_format($v, 2);
$rent       = (float)$invoice['agreed_rent'];
$ebUnits    = (float)$invoice['eb_units'];
$ebAmt      = (float)$invoice['eb_amount'];
$rentGst    = (float)$invoice['rent_gst'];
$other      = (float)$invoice['other_charges'];
$otherDesc  = $invoice['other_charges_desc'] ?: 'Other charges';
$amtDue     = (float)$invoice['amount_due'];
$amtPaid    = (float)$invoice['amount_paid'];
$balance    = $amtDue - $amtPaid;
$hasPdf     = !empty($invoice['pdf_path']) && file_exists($invoice['pdf_path']);
$rzLink     = $invoice['razorpay_link'] ?? '';
$statusCls  = ['paid'=>'success','partial'=>'warning','overdue'=>'danger','unpaid'=>'muted'];
$period     = date('F Y', strtotime($invoice['period_month']));
?>

<div class="d-flex gap-20 align-start" style="flex-wrap:wrap">

  <!-- Invoice card -->
  <div style="flex:1;min-width:340px">
    <div class="card mb-16">
      <!-- Invoice header -->
      <div style="background:var(--primary);color:#fff;padding:24px 28px;border-radius:var(--radius-xl) var(--radius-xl) 0 0">
        <div class="d-flex justify-between align-center">
          <div>
            <div style="font-size:11px;opacity:.8;text-transform:uppercase;letter-spacing:1px">Invoice</div>
            <div style="font-size:18px;font-weight:700;margin-top:2px"><?= $period ?></div>
            <div style="font-size:12px;opacity:.8;margin-top:4px">
              <?= htmlspecialchars($invoice['full_name']) ?> · Room <?= htmlspecialchars($invoice['room_number']) ?>
            </div>
          </div>
          <div style="text-align:right">
            <span class="badge badge-<?= $statusCls[$invoice['status']] ?? 'muted' ?>"
                  style="font-size:13px;padding:5px 12px"><?= ucfirst($invoice['status']) ?></span>
            <div style="font-size:11px;opacity:.7;margin-top:6px">
              Due: <?= date('d M Y', strtotime($invoice['due_date'])) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Line items -->
      <div style="padding:24px 28px">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
          <thead>
            <tr style="border-bottom:2px solid var(--border)">
              <th style="padding:8px 0;text-align:left;color:var(--text-muted);font-weight:600;font-size:11px;text-transform:uppercase">Description</th>
              <th style="padding:8px 0;text-align:right;color:var(--text-muted);font-weight:600;font-size:11px;text-transform:uppercase">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr><td style="padding:10px 0;border-bottom:1px solid var(--border)">Rent — <?= $period ?></td><td style="padding:10px 0;text-align:right;border-bottom:1px solid var(--border)"><?= $fmt($rent) ?></td></tr>
            <tr style="background:var(--surface-2)">
              <td style="padding:8px 12px;font-size:12px;color:var(--text-muted)">GST <?= (float)($property['rent_gst_rate'] ?? 18) ?>% on Rent</td>
              <td style="padding:8px 12px;text-align:right;font-size:12px;color:var(--primary);font-weight:600"><?= $fmt($rentGst) ?></td>
            </tr>
            <?php if ($ebUnits > 0): ?>
            <tr>
              <td style="padding:10px 0;border-bottom:1px solid var(--border)">
                Electricity — <?= number_format($ebUnits, 2) ?> units
                <span class="text-hint text-sm">@ ₹<?= number_format((float)($property['eb_unit_price'] ?? 0), 2) ?>/unit</span>
              </td>
              <td style="padding:10px 0;text-align:right;border-bottom:1px solid var(--border)"><?= $fmt($ebAmt) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($other > 0): ?>
            <tr>
              <td style="padding:10px 0;border-bottom:1px solid var(--border)"><?= htmlspecialchars($otherDesc) ?></td>
              <td style="padding:10px 0;text-align:right;border-bottom:1px solid var(--border)"><?= $fmt($other) ?></td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>

        <!-- Totals -->
        <div style="margin-top:16px;border-top:2px solid var(--primary);padding-top:14px">
          <div class="d-flex justify-between mb-6">
            <span class="text-muted text-sm">Total Due</span>
            <span class="fw-600"><?= $fmt($amtDue) ?></span>
          </div>
          <div class="d-flex justify-between mb-6">
            <span class="text-muted text-sm">Paid</span>
            <span class="fw-600 text-success"><?= $fmt($amtPaid) ?></span>
          </div>
          <div class="d-flex justify-between align-center" style="background:var(--surface-2);padding:12px 16px;border-radius:var(--radius);margin-top:8px">
            <span class="fw-600" style="font-size:15px">Balance Due</span>
            <span class="fw-600" style="font-size:20px;color:<?= $balance > 0 ? 'var(--danger)' : 'var(--success)' ?>"><?= $fmt($balance) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Actions panel -->
  <div style="width:280px;min-width:240px;flex-shrink:0">

    <!-- Download PDF -->
    <div class="card mb-16">
      <div class="card-header"><span class="card-title fw-600">PDF Invoice</span></div>
      <div class="card-body">
        <?php if ($hasPdf): ?>
        <a href="<?= url('/invoices/' . $invoice['id'] . '/pdf') ?>" target="_blank"
           class="btn btn-primary" style="width:100%;justify-content:center;margin-bottom:8px">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Download PDF
        </a>
        <?php else: ?>
        <p class="text-muted text-sm">PDF not yet generated.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Record Payment -->
    <?php if ($balance > 0): ?>
    <div class="card mb-16">
      <div class="card-header"><span class="card-title fw-600">Payment</span></div>
      <div class="card-body">
        <a href="<?= url('/payments/new?invoice_id=' . $invoice['id']) ?>"
           class="btn btn-secondary" style="width:100%;justify-content:center;margin-bottom:10px">
          Record Payment
        </a>

        <!-- Razorpay Payment Link -->
        <div style="border-top:1px solid var(--border);padding-top:12px;margin-top:4px">
          <p class="text-sm text-muted mb-8">Get Payment Link</p>
          <?php
            $linkAmount = $invoice['razorpay_link_amount'] ?? null;
            $linkStatus = $invoice['razorpay_link_status'] ?? null;
            $isStale    = $rzLink && (
                $linkStatus === 'expired'
                || ($linkAmount !== null && abs((float)$linkAmount - $balance) > 0.01)
            );
          ?>
          <?php if ($rzLink && !$isStale): ?>
          <a href="<?= htmlspecialchars($rzLink) ?>" target="_blank"
             class="btn" style="width:100%;justify-content:center;background:#072654;color:#fff;border-color:#072654">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
            Open Payment Link
          </a>
          <button onclick="copyLink()" class="btn btn-ghost btn-sm" style="width:100%;margin-top:6px;justify-content:center">
            Copy Link
          </button>
          <?php elseif ($isStale): ?>
          <div style="background:color-mix(in srgb, var(--warning, #F59E0B) 12%, transparent);border:1px solid var(--warning, #F59E0B);border-radius:var(--radius);padding:10px 12px;margin-bottom:10px">
            <div class="text-sm fw-600" style="color:#92400E">Link outdated</div>
            <div class="text-xs text-muted mt-4">
              The balance changed since this link was created (₹<?= number_format((float)$linkAmount) ?> → ₹<?= number_format($balance) ?>).
              The old link has been disabled. Generate a fresh one below.
            </div>
          </div>
          <button class="btn" style="width:100%;justify-content:center;background:#072654;color:#fff;border-color:#072654" onclick="getRazorpayLink()" id="rzBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
            Generate Fresh Link
          </button>
          <?php else: ?>
          <button class="btn" style="width:100%;justify-content:center;background:#072654;color:#fff;border-color:#072654" onclick="getRazorpayLink()" id="rzBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
            Get Payment Link
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Back -->
    <a href="<?= url('/dues') ?>" class="btn btn-ghost" style="width:100%;justify-content:center">
      ← Back to Dues
    </a>
  </div>
</div>

<!-- Hidden CSRF + data for JS -->
<input type="hidden" id="invoiceId" value="<?= htmlspecialchars($invoice['id']) ?>">
<input type="hidden" id="rzExisting" value="<?= htmlspecialchars($rzLink) ?>">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

<script>
const CSRF = document.querySelector('[name="_csrf"]').value;
const INV_ID = document.getElementById('invoiceId').value;

async function getRazorpayLink() {
  const btn = document.getElementById('rzBtn');
  if (!btn) return;
  btn.disabled = true;
  btn.textContent = 'Generating link…';

  try {
    const res  = await fetch(BASE + '/invoices/' + INV_ID + '/razorpay-link', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
      body: '_csrf=' + encodeURIComponent(CSRF)
    });
    const data = await res.json().catch(() => null);
    if (!data?.ok) {
      alert('Error: ' + (data?.error || 'Failed to create payment link'));
      btn.disabled = false;
      btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg> Get Payment Link';
      return;
    }
    window.rzPayUrl = data.url;
    const reuseNote = data.reused ? '<div class="text-hint text-sm mt-4">Reusing existing link (balance unchanged).</div>' : '';
    // Replace button with link
    btn.parentElement.innerHTML = `
      <a href="${data.url}" target="_blank" class="btn" style="width:100%;justify-content:center;background:#072654;color:#fff;border-color:#072654">
        Open Payment Link
      </a>
      <button onclick="navigator.clipboard.writeText('${data.url}').then(()=>alert('Copied!'))" class="btn btn-ghost btn-sm" style="width:100%;margin-top:6px;justify-content:center">
        Copy Link
      </button>
      <div class="text-hint text-sm mt-6" style="word-break:break-all">${data.url}</div>${reuseNote}`;
  } catch(e) {
    alert('Network error: ' + e.message);
    btn.disabled = false;
    btn.textContent = 'Get Payment Link';
  }
}

function copyLink() {
  const url = '<?= htmlspecialchars($rzLink) ?>';
  navigator.clipboard.writeText(url).then(() => alert('Payment link copied!')).catch(() => {
    prompt('Copy this link:', url);
  });
}
</script>
