<div style="max-width:580px">
  <a href="/dues" class="btn btn-ghost btn-sm" style="padding-left:0;margin-bottom:20px">← Back to dues</a>

  <div class="card">
    <div class="card-header"><span class="card-title">Record payment</span></div>
    <div class="card-body">
      <form action="/payments/new" method="POST" novalidate id="paymentForm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <!-- Invoice selector (if not pre-selected) -->
        <?php if ($invoice): ?>
          <input type="hidden" name="invoice_id" value="<?= htmlspecialchars($invoice['id']) ?>">
          <div style="background:var(--surface-2);border-radius:var(--radius);padding:14px 16px;margin-bottom:20px">
            <div class="d-flex justify-between align-center">
              <div>
                <div class="fw-600"><?= htmlspecialchars($invoice['full_name']) ?> — Room <?= htmlspecialchars($invoice['room_number']) ?></div>
                <div class="text-sm text-muted"><?= date('F Y', strtotime($invoice['period_month'])) ?></div>
              </div>
              <div class="text-right">
                <div class="fw-700 text-danger" style="font-size:18px">₹<?= number_format((float)$invoice['balance']) ?></div>
                <div class="text-xs text-muted">balance due</div>
              </div>
            </div>
            <?php if ((float)$invoice['amount_paid'] > 0): ?>
            <div class="mt-8 text-sm text-muted">
              Already paid: ₹<?= number_format((float)$invoice['amount_paid']) ?> of ₹<?= number_format((float)$invoice['amount_due']) ?>
            </div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="form-group">
            <label class="form-label" for="invoice_id">Invoice <span class="req">*</span></label>
            <select id="invoice_id" name="invoice_id" class="form-control" required onchange="setAmount(this)">
              <option value="">— Select invoice —</option>
              <?php foreach ($invoices as $inv): ?>
                <option value="<?= htmlspecialchars($inv['id']) ?>"
                        data-balance="<?= (float)$inv['balance'] ?>"
                        data-status="<?= htmlspecialchars($inv['status']) ?>">
                  <?= htmlspecialchars($inv['full_name']) ?> | Room <?= htmlspecialchars($inv['room_number']) ?> |
                  <?= date('M Y', strtotime($inv['period_month'])) ?> |
                  Balance ₹<?= number_format((float)$inv['balance']) ?>
                  <?= $inv['status'] === 'overdue' ? ' ⚠ OVERDUE' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="amount">Amount (₹) <span class="req">*</span></label>
            <input type="number" id="amount" name="amount" class="form-control"
                   value="<?= $invoice ? (float)$invoice['balance'] : '' ?>"
                   min="1" step="1" required>
            <div class="form-hint" id="amountHint">Enter full or partial payment.</div>
          </div>
          <div class="form-group">
            <label class="form-label" for="payment_date">Payment date <span class="req">*</span></label>
            <input type="date" id="payment_date" name="payment_date" class="form-control"
                   value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="mode">Payment mode <span class="req">*</span></label>
            <select id="mode" name="mode" class="form-control" required>
              <option value="cash">Cash</option>
              <option value="UPI">UPI</option>
              <option value="bank_transfer">Bank transfer</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="note">Note</label>
            <input type="text" id="note" name="note" class="form-control"
                   placeholder="Transaction ID, cheque number, etc.">
          </div>
        </div>

        <div class="d-flex gap-12 mt-8">
          <button type="submit" class="btn btn-primary">Record payment</button>
          <a href="/dues" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setAmount(sel) {
  const opt = sel.options[sel.selectedIndex];
  const bal = opt.dataset.balance || '';
  if (bal) {
    document.getElementById('amount').value = bal;
    document.getElementById('amountHint').textContent = `Balance due: ₹${parseFloat(bal).toLocaleString('en-IN')}`;
  }
}
</script>
