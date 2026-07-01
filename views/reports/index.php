<?php $statusCls = ['paid'=>'success','partial'=>'warning','overdue'=>'danger','unpaid'=>'muted']; ?>

<div class="d-flex align-center justify-between mb-24">
  <form method="GET" action="<?= url("/reports") ?>" class="d-flex align-center gap-8">
    <input type="month" name="month" class="form-control" style="width:160px"
           value="<?= htmlspecialchars($month) ?>">
  </form>
  <a href="<?= url("/reports/export?month=" . htmlspecialchars($month)) ?>" class="btn btn-secondary btn-sm">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Export CSV
  </a>
</div>

<!-- Summary cards -->
<?php if ($summary): ?>
<div class="stat-grid mb-24">
  <div class="stat-card accent-blue">
    <div class="stat-label">Total due</div>
    <div class="stat-value">₹<?= number_format((float)$summary['total_due']) ?></div>
    <div class="stat-sub"><?= $summary['invoices'] ?> invoices</div>
  </div>
  <div class="stat-card accent-green">
    <div class="stat-label">Collected</div>
    <div class="stat-value text-success">₹<?= number_format((float)$summary['total_paid']) ?></div>
    <div class="stat-sub"><?= $summary['paid_count'] ?> fully paid</div>
  </div>
  <div class="stat-card accent-amber">
    <div class="stat-label">Outstanding</div>
    <div class="stat-value text-danger">₹<?= number_format((float)$summary['outstanding']) ?></div>
    <div class="stat-sub"><?= (int)$summary['partial_count'] + (int)$summary['overdue_count'] ?> pending</div>
  </div>
  <div class="stat-card accent-green">
    <div class="stat-label">Collection %</div>
    <?php $pct = (float)$summary['total_due'] > 0 ? round((float)$summary['total_paid'] / (float)$summary['total_due'] * 100, 1) : 0; ?>
    <div class="stat-value"><?= $pct ?>%</div>
    <div class="progress mt-8"><div class="progress-bar <?= $pct < 60 ? 'danger' : ($pct < 85 ? 'warn' : '') ?>" style="width:<?= $pct ?>%"></div></div>
  </div>
</div>
<?php endif; ?>

<!-- Detail table -->
<div class="card">
  <div class="card-header">
    <span class="card-title">Room-wise breakdown — <?= date('F Y', strtotime($month . '-01')) ?></span>
  </div>
  <div class="table-wrap">
    <?php if ($rows): ?>
    <table>
      <thead>
        <tr>
          <th>Room</th><th>Tenant</th><th class="text-right">Agreed</th>
          <th class="text-right">Due</th><th class="text-right">Paid</th><th class="text-right">Balance</th>
          <th>Mode</th><th>Last payment</th><th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <?php $bal = (float)$r['amount_due'] - (float)$r['amount_paid']; ?>
        <tr>
          <td class="fw-600">Room <?= htmlspecialchars($r['room_number']) ?></td>
          <td><?= htmlspecialchars($r['full_name']) ?></td>
          <td class="text-right text-muted">₹<?= number_format((float)$r['agreed_rent']) ?></td>
          <td class="text-right">₹<?= number_format((float)$r['amount_due']) ?></td>
          <td class="text-right text-success fw-600">₹<?= number_format((float)$r['amount_paid']) ?></td>
          <td class="text-right fw-600 <?= $bal > 0 ? 'text-danger' : '' ?>">₹<?= number_format($bal) ?></td>
          <td class="text-sm text-muted"><?= htmlspecialchars($r['modes'] ?? '—') ?></td>
          <td class="text-sm text-muted"><?= $r['last_paid'] ?? '—' ?></td>
          <td><span class="badge badge-<?= $statusCls[$r['status']] ?? 'muted' ?>"><?= ucfirst($r['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      <p>No invoices found for <?= date('F Y', strtotime($month . '-01')) ?>.</p>
    </div>
    <?php endif; ?>
  </div>
</div>
