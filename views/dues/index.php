<?php
$filterMap = [
    'all'     => ['label' => __('common.all'),     'cls' => 'btn-secondary'],
    'pending' => ['label' => __('dues.pending'), 'cls' => 'btn-secondary'],
    'overdue' => ['label' => __('dues.overdue'), 'cls' => 'btn-secondary'],
    'partial' => ['label' => __('dues.partial'), 'cls' => 'btn-secondary'],
    'paid'    => ['label' => __('dues.paid'),    'cls' => 'btn-secondary'],
];
$filterMap[$filter]['cls'] = 'btn-primary';

$statusCls = ['paid'=>'success','partial'=>'warning','overdue'=>'danger','unpaid'=>'muted'];
?>

<div id="duesRoot"
     data-csrf="<?= htmlspecialchars($csrf ?? '') ?>"
     data-i18n-selected="<?= htmlspecialchars(__('dues.selected')) ?>"
     data-i18n-generating="<?= htmlspecialchars(__('dues.generating')) ?>"
     data-i18n-gen-invoices="<?= htmlspecialchars(__('dues.generate_invoices')) ?>"
     data-i18n-already-exist="<?= htmlspecialchars(__('dues.already_exist')) ?>">

<!-- Summary strip -->
<?php if ($summary): ?>
<div class="stat-grid mb-24">
  <div class="stat-card accent-amber">
    <div class="stat-label"><?= __('dues.overdue') ?></div>
    <div class="stat-value text-danger"><?= $summary['overdue'] ?></div>
    <div class="stat-sub"><?= __('dues.invoices') ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label"><?= __('dues.partial') ?></div>
    <div class="stat-value"><?= $summary['partial'] ?></div>
    <div class="stat-sub"><?= __('dues.invoices') ?></div>
  </div>
  <div class="stat-card accent-green">
    <div class="stat-label"><?= __('dues.paid') ?></div>
    <div class="stat-value text-success"><?= $summary['paid'] ?></div>
    <div class="stat-sub">of <?= $summary['total'] ?> total</div>
  </div>
  <div class="stat-card accent-blue">
    <div class="stat-label">Outstanding</div>
    <div class="stat-value">₹<?= number_format((float)$summary['total_due'] - (float)$summary['total_paid']) ?></div>
    <div class="stat-sub">₹<?= number_format((float)$summary['total_paid']) ?> <?= __('dues.collected') ?></div>
  </div>
</div>
<?php endif; ?>

<!-- Controls -->
<div class="d-flex align-center justify-between mb-16" style="flex-wrap:wrap;gap:10px">
  <div class="d-flex gap-8">
    <?php foreach ($filterMap as $key => $f): ?>
      <a href="<?= url("/dues?filter=" . $key . "&month=" . htmlspecialchars($month)) ?>"
         class="btn btn-sm <?= $f['cls'] ?>"><?= $f['label'] ?></a>
    <?php endforeach; ?>
  </div>
  <div class="d-flex gap-8 align-center">
    <input type="month" id="monthPicker" class="form-control" style="width:150px"
           value="<?= htmlspecialchars($month) ?>"
           id="duesMonthNav">
    <button class="btn btn-secondary btn-sm" id="generateBtn" data-action="generate-invoices">Generate <?= __('dues.invoices') ?></button>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="table-wrap">
    <?php if ($dues): ?>
    <table>
      <thead>
        <tr>
          <th><input type="checkbox" id="selectAll" title="Select all"></th>
          <th><?= __('common.tenant') ?></th><th><?= __('common.room') ?></th><th class="hide-mobile">Period</th>
          <th class="text-right hide-mobile">Due</th><th class="text-right"><?= __('dues.paid') ?></th><th class="text-right">Balance</th>
          <th>Status</th><th class="hide-mobile">Overdue by</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($dues as $d): ?>
        <tr>
          <td><input type="checkbox" class="row-select" value="<?= htmlspecialchars($d['id']) ?>"></td>
          <td>
            <a href="<?= url("/tenants/" . htmlspecialchars($d['tenant_id'])) ?>" class="fw-600">
              <?= htmlspecialchars($d['full_name']) ?>
            </a>
            <div class="text-xs text-muted"><?= htmlspecialchars($d['phone']) ?></div>
          </td>
          <td class="fw-600">Room <?= htmlspecialchars($d['room_number']) ?></td>
          <td class="text-sm hide-mobile"><?= date("M Y", strtotime($d["period_month"])) ?></td>
          <td class="text-right">₹<?= number_format((float)$d['amount_due']) ?></td>
          <td class="text-right text-success fw-600">₹<?= number_format((float)$d['amount_paid']) ?></td>
          <td class="text-right fw-600 <?= (float)$d['balance'] > 0 ? 'text-danger' : '' ?>">
            ₹<?= number_format((float)$d['balance']) ?>
          </td>
          <td><span class="badge badge-<?= $statusCls[$d['status']] ?? 'muted' ?>"><?= ucfirst($d['status']) ?></span></td>
          <td class="text-sm <?= (int)$d['days_overdue'] > 0 ? 'text-danger fw-600' : 'text-hint' ?>">
            <?= (int)$d['days_overdue'] > 0 ? (int)$d['days_overdue'] . 'd' : '—' ?>
          </td>
          <td>
            <?php if ($d['status'] !== 'paid'): ?>
              <a href="<?= url("/payments/new?invoice_id=" . htmlspecialchars($d['id'])) ?>" class="btn btn-primary btn-sm"><?= __('dues.pay') ?></a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <p>No <?= __('dues.invoices') ?> match this filter for <?= htmlspecialchars($month) ?>.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Bulk reminder bar (shows when rows <?= __('dues.selected') ?>) -->
<div id="bulkBar" style="display:none;position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:var(--text-primary);color:#fff;padding:12px 20px;border-radius:var(--radius-lg);box-shadow:var(--shadow);display:none;align-items:center;gap:16px;z-index:200">
  <span id="bulkCount">0 <?= __('dues.selected') ?></span>
  <a id="bulkReminder" href="<?= url("/reminders") ?>" class="btn btn-sm" style="background:#fff;color:var(--text-primary)"><?= __('dues.send_reminders') ?></a>
</div>

</div><!-- /#duesRoot -->

<script src="<?= asset("/assets/js/dues.js") ?>"></script>
