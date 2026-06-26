<?php
$filterMap = [
    'all'     => ['label' => 'All',     'cls' => 'btn-secondary'],
    'pending' => ['label' => 'Pending', 'cls' => 'btn-secondary'],
    'overdue' => ['label' => 'Overdue', 'cls' => 'btn-secondary'],
    'partial' => ['label' => 'Partial', 'cls' => 'btn-secondary'],
    'paid'    => ['label' => 'Paid',    'cls' => 'btn-secondary'],
];
$filterMap[$filter]['cls'] = 'btn-primary';

$statusCls = ['paid'=>'success','partial'=>'warning','overdue'=>'danger','unpaid'=>'muted'];
?>

<!-- Summary strip -->
<?php if ($summary): ?>
<div class="stat-grid mb-24">
  <div class="stat-card accent-amber">
    <div class="stat-label">Overdue</div>
    <div class="stat-value text-danger"><?= $summary['overdue'] ?></div>
    <div class="stat-sub">invoices</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Partial</div>
    <div class="stat-value"><?= $summary['partial'] ?></div>
    <div class="stat-sub">invoices</div>
  </div>
  <div class="stat-card accent-green">
    <div class="stat-label">Paid</div>
    <div class="stat-value text-success"><?= $summary['paid'] ?></div>
    <div class="stat-sub">of <?= $summary['total'] ?> total</div>
  </div>
  <div class="stat-card accent-blue">
    <div class="stat-label">Outstanding</div>
    <div class="stat-value">₹<?= number_format((float)$summary['total_due'] - (float)$summary['total_paid']) ?></div>
    <div class="stat-sub">₹<?= number_format((float)$summary['total_paid']) ?> collected</div>
  </div>
</div>
<?php endif; ?>

<!-- Controls -->
<div class="d-flex align-center justify-between mb-16" style="flex-wrap:wrap;gap:10px">
  <div class="d-flex gap-8">
    <?php foreach ($filterMap as $key => $f): ?>
      <a href="/dues?filter=<?= $key ?>&month=<?= htmlspecialchars($month) ?>"
         class="btn btn-sm <?= $f['cls'] ?>"><?= $f['label'] ?></a>
    <?php endforeach; ?>
  </div>
  <div class="d-flex gap-8 align-center">
    <input type="month" id="monthPicker" class="form-control" style="width:150px"
           value="<?= htmlspecialchars($month) ?>"
           onchange="location.href='/dues?filter=<?= htmlspecialchars($filter) ?>&month='+this.value">
    <button class="btn btn-secondary btn-sm" id="generateBtn">Generate invoices</button>
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
          <th>Tenant</th><th>Room</th><th class="hide-mobile">Period</th>
          <th class="text-right hide-mobile">Due</th><th class="text-right">Paid</th><th class="text-right">Balance</th>
          <th>Status</th><th class="hide-mobile">Overdue by</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($dues as $d): ?>
        <tr>
          <td><input type="checkbox" class="row-select" value="<?= htmlspecialchars($d['id']) ?>"></td>
          <td>
            <a href="/tenants/<?= htmlspecialchars($d['tenant_id']) ?>" class="fw-600">
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
              <a href="/payments/new?invoice_id=<?= htmlspecialchars($d['id']) ?>" class="btn btn-primary btn-sm">Pay</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <p>No invoices match this filter for <?= htmlspecialchars($month) ?>.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Bulk reminder bar (shows when rows selected) -->
<div id="bulkBar" style="display:none;position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:var(--text-primary);color:#fff;padding:12px 20px;border-radius:var(--radius-lg);box-shadow:var(--shadow);display:none;align-items:center;gap:16px;z-index:200">
  <span id="bulkCount">0 selected</span>
  <a id="bulkReminder" href="/reminders" class="btn btn-sm" style="background:#fff;color:var(--text-primary)">Send reminders</a>
</div>

<script>
// Select all
const selectAll = document.getElementById('selectAll');
const checks    = () => document.querySelectorAll('.row-select');
const bulkBar   = document.getElementById('bulkBar');
const bulkCount = document.getElementById('bulkCount');

function updateBulk() {
  const sel = [...checks()].filter(c => c.checked);
  if (sel.length > 0) {
    bulkCount.textContent = sel.length + ' selected';
    bulkBar.style.display = 'flex';
    document.getElementById('bulkReminder').href =
      '/reminders?ids=' + sel.map(c => c.value).join(',');
  } else {
    bulkBar.style.display = 'none';
  }
}

selectAll?.addEventListener('change', function () {
  checks().forEach(c => c.checked = this.checked);
  updateBulk();
});
document.addEventListener('change', e => {
  if (e.target.classList.contains('row-select')) updateBulk();
});

// Generate invoices
document.getElementById('generateBtn').addEventListener('click', async function () {
  const month = document.getElementById('monthPicker').value;
  this.disabled = true; this.textContent = 'Generating…';
  try {
    const r = await fetch('/api/invoices/generate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `_csrf=<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>&month=${month}`
    });
    const d = await r.json();
    alert(`Generated ${d.created} invoice(s) for ${month}.`);
    location.reload();
  } catch(e) { alert('Failed.'); }
  this.disabled = false; this.textContent = 'Generate invoices';
});
</script>
