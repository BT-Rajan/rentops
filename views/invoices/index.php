<?php
$statusMap = ['paid'=>'success','partial'=>'warning','overdue'=>'danger','unpaid'=>'muted'];
?>

<!-- Search bar -->
<div class="card mb-20">
  <div class="card-body">
    <div class="d-flex gap-12 flex-wrap align-center">
      <input type="text" id="searchTenant" class="form-control" style="flex:2;min-width:180px"
             placeholder="Search tenant name…" oninput="applyFilters()" value="<?= htmlspecialchars($_GET['tenant'] ?? '') ?>">
      <input type="text" id="searchRoom" class="form-control" style="flex:1;min-width:120px"
             placeholder="Room number…" oninput="applyFilters()" value="<?= htmlspecialchars($_GET['room'] ?? '') ?>">
      <input type="month" id="searchMonth" class="form-control" style="flex:1;min-width:140px"
             oninput="applyFilters()" value="<?= htmlspecialchars($_GET['month'] ?? '') ?>">
      <select id="searchStatus" class="form-control" style="flex:1;min-width:120px" onchange="applyFilters()">
        <option value="">All statuses</option>
        <option value="unpaid"  <?= ($_GET['status']??'')==='unpaid'  ?'selected':'' ?>>Unpaid</option>
        <option value="partial" <?= ($_GET['status']??'')==='partial' ?'selected':'' ?>>Partial</option>
        <option value="overdue" <?= ($_GET['status']??'')==='overdue' ?'selected':'' ?>>Overdue</option>
        <option value="paid"    <?= ($_GET['status']??'')==='paid'    ?'selected':'' ?>>Paid</option>
      </select>
      <button class="btn btn-ghost btn-sm" onclick="clearFilters()">Clear</button>
      <a href="?<?= http_build_query(array_merge($_GET, ['download'=>'xlsx'])) ?>" id="xlsxBtn" class="btn btn-secondary btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export XLSX
      </a>
    </div>
    <div class="mt-8 text-sm text-muted">
      Showing <span id="visibleCount"><?= count($invoices) ?></span> of <?= count($invoices) ?> invoices
    </div>
  </div>
</div>

<!-- Summary strip -->
<?php
$totalDue  = array_sum(array_column($invoices, 'amount_due'));
$totalPaid = array_sum(array_column($invoices, 'amount_paid'));
$balance   = $totalDue - $totalPaid;
?>
<div class="d-flex gap-12 mb-20 flex-wrap">
  <div class="stat-card accent-blue" style="flex:1;min-width:140px;padding:14px 18px">
    <div class="stat-label">Total Invoiced</div>
    <div class="stat-value" style="font-size:18px">₹<?= number_format($totalDue) ?></div>
  </div>
  <div class="stat-card accent-green" style="flex:1;min-width:140px;padding:14px 18px">
    <div class="stat-label">Collected</div>
    <div class="stat-value" style="font-size:18px">₹<?= number_format($totalPaid) ?></div>
  </div>
  <div class="stat-card accent-amber" style="flex:1;min-width:140px;padding:14px 18px">
    <div class="stat-label">Outstanding</div>
    <div class="stat-value" style="font-size:18px">₹<?= number_format($balance) ?></div>
  </div>
  <div class="stat-card" style="flex:1;min-width:140px;padding:14px 18px">
    <div class="stat-label">Invoices</div>
    <div class="stat-value" style="font-size:18px"><?= count($invoices) ?></div>
  </div>
</div>

<!-- Table -->
<div class="card">
  <?php if ($invoices): ?>
  <div class="table-wrap">
    <table id="invoiceTable">
      <thead>
        <tr>
          <th>Period</th>
          <th>Tenant</th>
          <th>Room</th>
          <th class="text-right">Rent</th>
          <th class="text-right">EB</th>
          <th class="text-right">GST</th>
          <th class="text-right">Other</th>
          <th class="text-right">Total Due</th>
          <th class="text-right">Paid</th>
          <th class="text-right">Balance</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($invoices as $inv):
          $bal = (float)$inv['amount_due'] - (float)$inv['amount_paid'];
          $hasPdf = !empty($inv['pdf_path']) && file_exists($inv['pdf_path']);
          $monthVal = substr($inv['period_month'], 0, 7); // Y-m
        ?>
        <tr data-tenant="<?= strtolower(htmlspecialchars($inv['full_name'])) ?>"
            data-room="<?= strtolower(htmlspecialchars($inv['room_number'])) ?>"
            data-month="<?= htmlspecialchars($monthVal) ?>"
            data-status="<?= htmlspecialchars($inv['status']) ?>">
          <td class="fw-600"><?= date('M Y', strtotime($inv['period_month'])) ?></td>
          <td>
            <a href="<?= url('/tenants/' . htmlspecialchars($inv['tenant_id'])) ?>" class="fw-600 text-sm">
              <?= htmlspecialchars($inv['full_name']) ?>
            </a>
          </td>
          <td class="text-sm text-muted">Room <?= htmlspecialchars($inv['room_number']) ?></td>
          <td class="text-right text-sm">₹<?= number_format((float)$inv['agreed_rent']) ?></td>
          <td class="text-right text-sm"><?= (float)$inv['eb_units'] > 0 ? '₹'.number_format((float)$inv['eb_amount']) : '—' ?></td>
          <td class="text-right text-sm">₹<?= number_format((float)$inv['rent_gst']) ?></td>
          <td class="text-right text-sm"><?= (float)$inv['other_charges'] > 0 ? '₹'.number_format((float)$inv['other_charges']) : '—' ?></td>
          <td class="text-right fw-600">₹<?= number_format((float)$inv['amount_due']) ?></td>
          <td class="text-right text-success fw-600">₹<?= number_format((float)$inv['amount_paid']) ?></td>
          <td class="text-right fw-600 <?= $bal > 0 ? 'text-danger' : 'text-success' ?>">₹<?= number_format($bal) ?></td>
          <td><span class="badge badge-<?= $statusMap[$inv['status']] ?? 'muted' ?>"><?= ucfirst($inv['status']) ?></span></td>
          <td>
            <div class="d-flex gap-4">
              <a href="<?= url('/invoices/' . htmlspecialchars($inv['id'])) ?>" class="btn btn-ghost btn-sm">View</a>
              <?php if ($hasPdf): ?>
              <a href="<?= url('/invoices/' . htmlspecialchars($inv['id']) . '/pdf') ?>" target="_blank" class="btn btn-ghost btn-sm" title="PDF">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              </a>
              <?php endif; ?>
              <?php if ($bal > 0): ?>
              <a href="<?= url('/payments/new?invoice_id=' . htmlspecialchars($inv['id'])) ?>" class="btn btn-primary btn-sm">Pay</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:48px;height:48px"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    <p>No invoices yet. <a href="<?= url('/invoices/new') ?>">Generate the first one →</a></p>
  </div>
  <?php endif; ?>
</div>

<script>
function applyFilters() {
  const tenant = document.getElementById('searchTenant').value.toLowerCase().trim();
  const room   = document.getElementById('searchRoom').value.toLowerCase().trim();
  const month  = document.getElementById('searchMonth').value;
  const status = document.getElementById('searchStatus').value;
  let visible  = 0;

  document.querySelectorAll('#invoiceTable tbody tr').forEach(row => {
    const show = (!tenant || row.dataset.tenant.includes(tenant))
              && (!room   || row.dataset.room.includes(room))
              && (!month  || row.dataset.month === month)
              && (!status || row.dataset.status === status);
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  document.getElementById('visibleCount').textContent = visible;

  // Update XLSX export link to reflect current filters
  const params = new URLSearchParams(window.location.search);
  if (tenant) params.set('tenant', tenant); else params.delete('tenant');
  if (room)   params.set('room', room);     else params.delete('room');
  if (month)  params.set('month', month);   else params.delete('month');
  if (status) params.set('status', status); else params.delete('status');
  params.set('download', 'xlsx');
  document.getElementById('xlsxBtn').href = '?' + params.toString();
}

function clearFilters() {
  document.getElementById('searchTenant').value = '';
  document.getElementById('searchRoom').value   = '';
  document.getElementById('searchMonth').value  = '';
  document.getElementById('searchStatus').value = '';
  applyFilters();
}

// Apply any URL params on load
applyFilters();
</script>
