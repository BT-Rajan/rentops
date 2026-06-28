<?php
$statusMap = [
    'vacant'             => 'success',
    'occupied'           => 'info',
    'partially_occupied' => 'warning',
    'maintenance'        => 'danger',
];
$invoiceMap = [
    'paid'    => 'success',
    'partial' => 'warning',
    'overdue' => 'danger',
    'unpaid'  => 'muted',
];
$active = null;
foreach ($tenancies as $te) {
    if ($te['status'] === 'active') { $active = $te; break; }
}
?>

<div style="margin-bottom:20px">
  <a href="<?= url("/rooms") ?>" class="btn btn-ghost btn-sm" style="padding-left:0">← Back to rooms</a>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;align-items:start" class="room-detail-grid">

  <!-- Room info card -->
  <div>
    <div class="card mb-16">
      <div class="card-header">
        <span class="card-title">Room <?= htmlspecialchars($room['room_number']) ?></span>
        <span class="badge badge-<?= $statusMap[$room['status']] ?? 'muted' ?>"><?= ucfirst(str_replace('_',' ',$room['status'])) ?></span>
      </div>
      <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:12px">
          <div class="d-flex justify-between">
            <span class="text-sm text-muted">Type</span>
            <span class="text-sm fw-600" style="text-transform:capitalize"><?= htmlspecialchars($room['room_type']) ?></span>
          </div>
          <div class="d-flex justify-between">
            <span class="text-sm text-muted"><?= __('rooms.base_rent') ?></span>
            <span class="text-sm fw-600">₹<?= number_format((float)$room['base_rent']) ?></span>
          </div>
          <?php if ($active): ?>
          <div class="d-flex justify-between">
            <span class="text-sm text-muted"><?= __('rooms.agreed_rent') ?></span>
            <span class="text-sm fw-600 text-primary-color">₹<?= number_format((float)$active['agreed_rent']) ?></span>
          </div>
          <div class="d-flex justify-between">
            <span class="text-sm text-muted"><?= __('common.tenant') ?></span>
            <a href="<?= url("/tenants/" . htmlspecialchars($active['tenant_id'])) ?>" class="text-sm fw-600"><?= htmlspecialchars($active['full_name']) ?></a>
          </div>
          <div class="d-flex justify-between">
            <span class="text-sm text-muted"><?= __('tenants.move_in') ?></span>
            <span class="text-sm"><?= htmlspecialchars($active['move_in_date']) ?></span>
          </div>
          <div class="d-flex justify-between">
            <span class="text-sm text-muted"><?= __('tenants.outstanding') ?></span>
            <span class="text-sm fw-600 <?= (float)$active['outstanding'] > 0 ? 'text-danger' : 'text-success' ?>">
              ₹<?= number_format((float)$active['outstanding']) ?>
            </span>
          </div>
          <?php endif; ?>
        </div>

        <?php if (!$active): ?>
        <div class="mt-16">
          <?php if ($room['status'] === 'occupied'): ?>
            <button class="btn btn-primary w-full" style="justify-content:center;opacity:.45;cursor:not-allowed" disabled title="<?= __('rooms.room_occupied') ?>"><?= __('rooms.add_tenant') ?></button>
            <p class="text-sm text-danger" style="text-align:center;margin-top:6px"><?= __('rooms.room_occupied') ?></p>
          <?php else: ?>
            <a href="<?= url("/tenants/new") ?>" class="btn btn-primary w-full" style="justify-content:center"><?= __('rooms.add_tenant') ?></a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Edit room form -->
    <div class="card">
      <div class="card-header"><span class="card-title"><?= __('rooms.room_settings') ?></span></div>
      <div class="card-body">
        <form action="<?= url("/rooms/" . htmlspecialchars($room['id'])) ?>" method="POST">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
          <div class="form-group">
            <label class="form-label">Base rent (₹)</label>
            <input type="number" name="base_rent" class="form-control" value="<?= (float)$room['base_rent'] ?>" min="0" step="100">
          </div>
          <div class="form-group">
            <label class="form-label"><?= __('rooms.room_type') ?></label>
            <select name="room_type" class="form-control">
              <?php foreach (['single','sharing','dorm'] as $t): ?>
                <option value="<?= $t ?>" <?= $room['room_type'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <?php foreach (['vacant','occupied','partially_occupied','maintenance'] as $s): ?>
                <option value="<?= $s ?>" <?= $room['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary w-full" style="justify-content:center"><?= __('rooms.save_changes') ?></button>
        </form>
      </div>
    </div>
  </div>

  <!-- Invoice history -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><?= __('rooms.invoice_history') ?></span>
    </div>
    <?php if ($invoices): ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Period</th><th>Due</th><th>Paid</th><th>Balance</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($invoices as $inv): ?>
          <?php $bal = (float)$inv['amount_due'] - (float)$inv['collected']; ?>
          <tr>
            <td class="fw-600"><?= date('M Y', strtotime($inv['period_month'])) ?></td>
            <td>₹<?= number_format((float)$inv['amount_due']) ?></td>
            <td class="text-success fw-600">₹<?= number_format((float)$inv['collected']) ?></td>
            <td class="<?= $bal > 0 ? 'text-danger' : '' ?> fw-600">₹<?= number_format($bal) ?></td>
            <td><span class="badge badge-<?= $invoiceMap[$inv['status']] ?? 'muted' ?>"><?= ucfirst($inv['status']) ?></span></td>
            <td>
              <?php if ($inv['status'] !== 'paid'): ?>
                <a href="<?= url("/payments/new?invoice_id=" . htmlspecialchars($inv['id'])) ?>" class="btn btn-primary btn-sm"><?= __('dues.pay') ?></a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      <p><?= __('rooms.no_invoices') ?></p>
    </div>
    <?php endif; ?>
  </div>

</div>

<style>
@media(max-width:768px) { .room-detail-grid { grid-template-columns: 1fr !important; } }
</style>
