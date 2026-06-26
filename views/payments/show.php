<style>
@media print {
  .topbar, .sidebar, .no-print { display: none !important; }
  .main-wrap { margin-left: 0 !important; }
  .page-content { padding: 0 !important; }
  .receipt-card { border: none !important; box-shadow: none !important; }
}
</style>

<div style="max-width:560px;margin:0 auto">
  <div class="d-flex align-center justify-between mb-20 no-print">
    <a href="<?= url("/dues") ?>" class="btn btn-ghost btn-sm" style="padding-left:0">← Back to dues</a>
    <button onclick="window.print()" class="btn btn-secondary btn-sm">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
      Print receipt
    </button>
  </div>

  <div class="card receipt-card">
    <!-- Header -->
    <div style="padding:28px 28px 20px;border-bottom:1px solid var(--border);text-align:center">
      <div style="font-size:22px;font-weight:800;color:var(--c-primary);letter-spacing:-.3px">RentOps</div>
      <div style="font-size:13px;color:var(--text-secondary);margin-top:2px">Payment Receipt</div>
      <div style="font-size:11px;color:var(--text-hint);margin-top:4px">Receipt #<?= strtoupper(substr($payment['id'], 0, 8)) ?></div>
    </div>

    <!-- Amount -->
    <div style="padding:28px;text-align:center;border-bottom:1px solid var(--border)">
      <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--text-secondary);margin-bottom:8px">Amount paid</div>
      <div style="font-size:42px;font-weight:800;color:var(--c-primary)">₹<?= number_format((float)$payment['amount']) ?></div>
      <div style="margin-top:12px">
        <span class="badge badge-success" style="font-size:13px;padding:5px 14px">✓ Payment recorded</span>
      </div>
    </div>

    <!-- Details -->
    <div style="padding:24px 28px">
      <div style="display:flex;flex-direction:column;gap:14px">
        <div class="d-flex justify-between">
          <span class="text-sm text-muted">Tenant</span>
          <span class="fw-600"><?= htmlspecialchars($payment['full_name']) ?></span>
        </div>
        <div class="d-flex justify-between">
          <span class="text-sm text-muted">Room</span>
          <span class="fw-600">Room <?= htmlspecialchars($payment['room_number']) ?></span>
        </div>
        <div class="d-flex justify-between">
          <span class="text-sm text-muted">Period</span>
          <span><?= date('F Y', strtotime($payment['period_month'])) ?></span>
        </div>
        <div class="d-flex justify-between">
          <span class="text-sm text-muted">Payment date</span>
          <span><?= date('d M Y', strtotime($payment['payment_date'])) ?></span>
        </div>
        <div class="d-flex justify-between">
          <span class="text-sm text-muted">Mode</span>
          <span class="badge badge-info"><?= htmlspecialchars(strtoupper(str_replace('_',' ',$payment['mode']))) ?></span>
        </div>
        <?php if ($payment['note']): ?>
        <div class="d-flex justify-between">
          <span class="text-sm text-muted">Note</span>
          <span class="text-sm"><?= htmlspecialchars($payment['note']) ?></span>
        </div>
        <?php endif; ?>
        <div style="border-top:1px solid var(--border);padding-top:14px">
          <div class="d-flex justify-between">
            <span class="text-sm text-muted">Invoice total</span>
            <span>₹<?= number_format((float)$payment['amount_due']) ?></span>
          </div>
          <div class="d-flex justify-between mt-8">
            <span class="text-sm text-muted">Total paid</span>
            <span class="fw-600 text-success">₹<?= number_format((float)$payment['amount_paid']) ?></span>
          </div>
          <?php $balance = (float)$payment['amount_due'] - (float)$payment['amount_paid']; ?>
          <?php if ($balance > 0): ?>
          <div class="d-flex justify-between mt-8">
            <span class="text-sm text-muted">Balance remaining</span>
            <span class="fw-600 text-danger">₹<?= number_format($balance) ?></span>
          </div>
          <?php endif; ?>
        </div>
        <div class="d-flex justify-between">
          <span class="text-sm text-muted">Invoice status</span>
          <?php $sc = ['paid'=>'success','partial'=>'warning','overdue'=>'danger','unpaid'=>'muted']; ?>
          <span class="badge badge-<?= $sc[$payment['invoice_status']] ?? 'muted' ?>"><?= ucfirst($payment['invoice_status']) ?></span>
        </div>
        <div class="d-flex justify-between">
          <span class="text-sm text-muted">Recorded by</span>
          <span class="text-sm"><?= htmlspecialchars($payment['recorded_by']) ?></span>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div style="padding:16px 28px;background:var(--surface-2);border-top:1px solid var(--border);text-align:center">
      <div class="text-xs text-hint">Generated by RentOps · <?= date('d M Y, h:i A') ?></div>
    </div>
  </div>

  <div class="d-flex gap-8 mt-16 no-print" style="justify-content:center">
    <a href="<?= url("/payments/new?invoice_id=" . htmlspecialchars($payment['invoice_id'])) ?>" class="btn btn-secondary btn-sm">
      Record another payment
    </a>
    <a href="<?= url("/tenants/" . htmlspecialchars(DB::scalar('SELECT tenant_id FROM tenancies WHERE id = (SELECT tenancy_id FROM rent_invoices WHERE id = ?)', [$payment['invoice_id']]) ?? '')) ?>" class="btn btn-ghost btn-sm">
      View tenant
    </a>
  </div>
</div>
