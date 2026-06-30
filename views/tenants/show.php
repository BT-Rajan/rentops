<?php
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $tenant['full_name']), 0, 2)));
$invMap   = ['paid'=>'success','partial'=>'warning','overdue'=>'danger','unpaid'=>'muted'];
?>

<div class="mb-20">
  <a href="<?= url("/tenants") ?>" class="btn btn-ghost btn-sm" style="padding-left:0">← Back to tenants</a>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;align-items:start" class="tenant-detail-grid">

  <!-- Left: profile + actions -->
  <div>
    <div class="card mb-16">
      <div class="card-body" style="text-align:center;padding:28px 20px">
        <div class="avatar" style="width:64px;height:64px;font-size:22px;margin:0 auto 12px"><?= htmlspecialchars($initials) ?></div>
        <div class="fw-700" style="font-size:18px"><?= htmlspecialchars($tenant['full_name']) ?></div>
        <div class="text-sm text-muted mt-4"><?= htmlspecialchars($tenant['phone']) ?></div>
        <?php if ($tenant['email']): ?><div class="text-sm text-muted"><?= htmlspecialchars($tenant['email']) ?></div><?php endif; ?>
        <div class="mt-8"><span class="badge badge-<?= $tenant['status'] === 'active' ? 'success' : 'muted' ?>"><?= ucfirst($tenant['status']) ?></span></div>
      </div>
      <div class="card-body" style="border-top:1px solid var(--border);padding:16px 20px">
        <div style="display:flex;flex-direction:column;gap:10px">
          <div class="d-flex justify-between"><span class="text-sm text-muted">ID proof</span><span class="text-sm fw-600"><?= htmlspecialchars($tenant['id_proof_type']) ?></span></div>
          <?php if ($tenant['id_proof_number']): ?>
          <div class="d-flex justify-between"><span class="text-sm text-muted">ID number</span><span class="text-sm"><?= htmlspecialchars($tenant['id_proof_number']) ?></span></div>
          <?php endif; ?>
          <?php if ($tenant['emergency_contact']): ?>
          <div class="d-flex justify-between"><span class="text-sm text-muted">Emergency</span><span class="text-sm"><?= htmlspecialchars($tenant['emergency_contact']) ?></span></div>
          <?php endif; ?>
          <?php if ($activeTenancy): ?>
          <div class="d-flex justify-between"><span class="text-sm text-muted">Room</span><a href="<?= url("/rooms/" . htmlspecialchars($activeTenancy['room_id'])) ?>" class="text-sm fw-600">Room <?= htmlspecialchars($activeTenancy['room_number']) ?></a></div>
          <div class="d-flex justify-between"><span class="text-sm text-muted">Agreed rent</span><span class="text-sm fw-600 text-primary-color">₹<?= number_format((float)$activeTenancy['agreed_rent']) ?></span></div>
          <div class="d-flex justify-between"><span class="text-sm text-muted">Deposit</span><span class="text-sm">₹<?= number_format((float)$activeTenancy['security_deposit']) ?></span></div>
          <div class="d-flex justify-between"><span class="text-sm text-muted">Move-in</span><span class="text-sm"><?= htmlspecialchars($activeTenancy['move_in_date']) ?></span></div>
          <?php if (!empty($activeTenancy['scheduled_move_out_date'])): ?>
          <div style="background:color-mix(in srgb, var(--warning, #F59E0B) 12%, transparent);border:1px solid var(--warning, #F59E0B);border-radius:var(--radius);padding:10px 12px;margin-top:8px">
            <div class="text-sm fw-600" style="color:#92400E">
              Move-out scheduled — <?= date('d M Y', strtotime($activeTenancy['scheduled_move_out_date'])) ?>
            </div>
            <div class="text-xs text-muted mt-4">Tenant remains active and billable until this date.</div>
          </div>
          <?php endif; ?>
          <div class="d-flex justify-between"><span class="text-sm text-muted">Due day</span><span class="text-sm">Day <?= htmlspecialchars($activeTenancy['rent_due_day']) ?></span></div>

          <!-- Outstanding summary -->
          <?php
          $totalDue  = array_sum(array_column($invoices, 'amount_due'));
          $totalPaid = array_sum(array_column($invoices, 'amount_paid'));
          $outstanding = $totalDue - $totalPaid;
          ?>
          <div style="border-top:1px solid var(--border);padding-top:12px;margin-top:4px">
            <div class="d-flex justify-between align-center">
              <span class="text-sm text-muted">Total outstanding</span>
              <span class="fw-700 <?= $outstanding > 0 ? 'text-danger' : 'text-success' ?>" style="font-size:15px">₹<?= number_format($outstanding) ?></span>
            </div>
          </div>

          <!-- Rent adjustment -->
          <div style="border-top:1px solid var(--border);padding-top:12px;margin-top:4px">
            <button class="btn btn-ghost btn-sm w-full" style="justify-content:center;color:var(--text-secondary)"
                    onclick="document.getElementById('rentChangePanel').style.display='block';this.style.display='none'">
              Adjust rent ↓
            </button>
            <div id="rentChangePanel" style="display:none">
              <div class="text-xs text-muted mb-8 mt-4">New monthly rent</div>
              <div class="d-flex gap-8">
                <input type="number" id="newRentInput" class="form-control" style="flex:1"
                       placeholder="₹" value="<?= (float)$activeTenancy['agreed_rent'] ?>" min="1" step="100">
                <button class="btn btn-primary btn-sm" onclick="submitRentChange()">Save</button>
              </div>
              <input type="date" id="rentEffective" class="form-control mt-8" value="<?= date('Y-m-01') ?>" style="font-size:12px">
              <input type="text" id="rentNote" class="form-control mt-8" placeholder="Reason (optional)" style="font-size:12px">
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div style="display:flex;flex-direction:column;gap:8px">
      <?php if ($activeTenancy): ?>
        <a href="<?= url("/invoices/new") ?>" class="btn btn-primary" style="justify-content:center">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:6px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Generate Invoice
        </a>
        <a href="<?= url("/payments/new") ?>" class="btn btn-secondary" style="justify-content:center">Record Payment</a>
        <a href="<?= url("/tenants/" . htmlspecialchars($tenant['id']) . "/moveout") ?>" class="btn btn-ghost" style="justify-content:center">Process Move-out</a>
      <?php elseif ($tenant['status'] === 'active'): ?>
        <a href="<?= url("/tenants/" . htmlspecialchars($tenant['id']) . "/movein") ?>" class="btn btn-primary" style="justify-content:center">Assign Room</a>
      <?php endif; ?>
      <a href="<?= url("/reminders") ?>" class="btn btn-ghost" style="justify-content:center">Send Reminder</a>
    </div>

    <!-- ID proof upload -->
    <div class="card mt-16">
      <div class="card-header"><span class="card-title" style="font-size:13px">ID Proof</span></div>
      <div class="card-body" style="padding:14px 16px">
        <?php if (!empty($tenant['id_proof_file'])): ?>
          <?php $ext = strtolower(pathinfo($tenant['id_proof_file'], PATHINFO_EXTENSION)); ?>
          <?php if (in_array($ext, ['jpg','jpeg','png','webp'])): ?>
            <a href="<?= url(htmlspecialchars($tenant['id_proof_file'])) ?>" target="_blank">
              <img src="<?= url(htmlspecialchars($tenant['id_proof_file'])) ?>" alt="ID proof" style="max-width:100%;border-radius:var(--radius);border:1px solid var(--border)">
            </a>
          <?php else: ?>
            <a href="<?= url(htmlspecialchars($tenant['id_proof_file'])) ?>" target="_blank" class="btn btn-secondary btn-sm">📄 View PDF</a>
          <?php endif; ?>
          <button class="btn btn-danger btn-sm w-full mt-10" style="justify-content:center" id="deleteProofBtn">Remove file</button>
        <?php else: ?>
          <div id="proofDropZone" style="border:2px dashed var(--border-md);border-radius:var(--radius);padding:20px;text-align:center;cursor:pointer"
               onclick="document.getElementById('proofInput').click()"
               ondragover="event.preventDefault();this.style.borderColor='var(--c-primary)'"
               ondragleave="this.style.borderColor='var(--border-md)'"
               ondrop="handleProofDrop(event)">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--text-hint)" stroke-width="1.5" style="margin:0 auto 8px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <div class="text-sm text-muted" id="proofLabel">Upload JPG, PNG, PDF — max 5 MB</div>
          </div>
          <input type="file" id="proofInput" accept=".jpg,.jpeg,.png,.webp,.pdf" style="display:none" onchange="uploadProof(this.files[0])">
          <div id="proofProgress" style="display:none;margin-top:8px">
            <div class="progress"><div class="progress-bar" id="proofBar" style="width:0%"></div></div>
            <div class="text-xs text-muted mt-4" id="proofStatus">Uploading…</div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Right: invoices + payment history -->
  <div>

    <!-- Invoice history card -->
    <div class="card mb-16">
      <div class="card-header">
        <span class="card-title">Invoice History</span>
        <div class="d-flex gap-8 align-center">
          <?php if ($invoices): ?>
          <a href="<?= url("/invoices?tenant_id=" . $tenant['id'] . "&download=xlsx") ?>" class="btn btn-ghost btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export XLSX
          </a>
          <?php endif; ?>
          <?php if ($activeTenancy): ?>
          <a href="<?= url("/invoices/new") ?>" class="btn btn-primary btn-sm">+ Generate Invoice</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($invoices): ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Period</th>
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
            ?>
            <tr>
              <td class="fw-600"><?= date('M Y', strtotime($inv['period_month'])) ?></td>
              <td class="text-right text-sm">₹<?= number_format((float)$inv['agreed_rent']) ?></td>
              <td class="text-right text-sm"><?= (float)$inv['eb_units'] > 0 ? '₹' . number_format((float)$inv['eb_amount']) : '—' ?></td>
              <td class="text-right text-sm">₹<?= number_format((float)$inv['rent_gst']) ?></td>
              <td class="text-right text-sm"><?= (float)$inv['other_charges'] > 0 ? '₹' . number_format((float)$inv['other_charges']) : '—' ?></td>
              <td class="text-right fw-600">₹<?= number_format((float)$inv['amount_due']) ?></td>
              <td class="text-right text-success fw-600">₹<?= number_format((float)$inv['amount_paid']) ?></td>
              <td class="text-right fw-600 <?= $bal > 0 ? 'text-danger' : 'text-success' ?>">₹<?= number_format($bal) ?></td>
              <td><span class="badge badge-<?= $invMap[$inv['status']] ?? 'muted' ?>"><?= ucfirst($inv['status']) ?></span></td>
              <td>
                <div class="d-flex gap-4">
                  <a href="<?= url("/invoices/" . htmlspecialchars($inv['id'])) ?>" class="btn btn-ghost btn-sm">View</a>
                  <?php if ($hasPdf): ?>
                  <a href="<?= url("/invoices/" . htmlspecialchars($inv['id']) . "/pdf") ?>" target="_blank" class="btn btn-ghost btn-sm" title="Download PDF">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                  </a>
                  <?php endif; ?>
                  <?php if ($bal > 0): ?>
                  <a href="<?= url("/payments/new?invoice_id=" . htmlspecialchars($inv['id'])) ?>" class="btn btn-primary btn-sm">Pay</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <!-- Payment breakdown row (collapsed) -->
            <?php if (!empty($inv['payments'])): ?>
            <tr style="background:var(--surface-2)">
              <td colspan="10" style="padding:8px 16px">
                <div style="font-size:12px;color:var(--text-muted);display:flex;gap:16px;flex-wrap:wrap">
                  <strong>Payments:</strong>
                  <?php foreach ($inv['payments'] as $pay): ?>
                  <span>
                    <?= date('d M', strtotime($pay['payment_date'])) ?>
                    — ₹<?= number_format((float)$pay['amount']) ?>
                    <span class="badge badge-muted" style="font-size:10px"><?= htmlspecialchars($pay['mode']) ?></span>
                    <?php if ($pay['receipt_number']): ?><span class="text-hint">#<?= htmlspecialchars($pay['receipt_number']) ?></span><?php endif; ?>
                  </span>
                  <?php endforeach; ?>
                </div>
              </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <p>No invoices yet. <a href="<?= url("/invoices/new") ?>">Generate the first invoice →</a></p>
      </div>
      <?php endif; ?>
    </div>

    <!-- Payment history card -->
    <?php if ($payments): ?>
    <div class="card">
      <div class="card-header">
        <span class="card-title">Payment History</span>
        <span class="text-muted text-sm"><?= count($payments) ?> transactions</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Date</th><th>Period</th><th class="text-right">Amount</th><th>Mode</th><th>Receipt</th><th>Notes</th></tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $p): ?>
            <tr>
              <td class="text-sm"><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
              <td class="text-sm text-muted"><?= $p['period_month'] ? date('M Y', strtotime($p['period_month'])) : '—' ?></td>
              <td class="text-right fw-600 text-success">₹<?= number_format((float)$p['amount']) ?></td>
              <td><span class="badge badge-muted" style="font-size:11px"><?= htmlspecialchars(ucfirst($p['mode'])) ?></span></td>
              <td class="text-sm text-muted"><?= htmlspecialchars($p['receipt_number'] ?? '—') ?></td>
              <td class="text-sm text-muted"><?= htmlspecialchars($p['notes'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<style>
@media(max-width:768px) { .tenant-detail-grid { grid-template-columns: 1fr !important; } }
</style>

<script>
const TENANT_ID  = '<?= htmlspecialchars($tenant['id']) ?>';
const TENANCY_ID = '<?= htmlspecialchars($activeTenancy['id'] ?? '') ?>';
const CSRF       = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';

async function submitRentChange() {
  const newRent = parseFloat(document.getElementById('newRentInput').value);
  const effDate = document.getElementById('rentEffective').value;
  const note    = document.getElementById('rentNote').value;
  if (!newRent || newRent <= 0) { alert('Enter a valid rent amount.'); return; }
  const r = await fetch(`${BASE}/tenancies/${TENANCY_ID}/rent-change`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `_csrf=${encodeURIComponent(CSRF)}&new_rent=${newRent}&effective_from=${encodeURIComponent(effDate)}&note=${encodeURIComponent(note)}`
  });
  const d = await r.json();
  if (d.success) { alert(`Rent updated to ₹${d.new_rent.toLocaleString('en-IN')}`); location.reload(); }
  else alert('Error: ' + (d.error || 'Failed'));
}

async function uploadProof(file) {
  if (!file) return;
  document.getElementById('proofProgress').style.display = 'block';
  document.getElementById('proofLabel').textContent = file.name;
  document.getElementById('proofBar').style.width = '30%';
  const fd = new FormData();
  fd.append('id_proof', file);
  fd.append('_csrf', CSRF);
  try {
    const r = await fetch(`${BASE}/tenants/${TENANT_ID}/upload-proof`, { method: 'POST', body: fd });
    const d = await r.json();
    document.getElementById('proofBar').style.width = '100%';
    if (d.success) { document.getElementById('proofStatus').textContent = '✓ Uploaded'; setTimeout(() => location.reload(), 800); }
    else document.getElementById('proofStatus').textContent = '✕ ' + (d.error || 'Upload failed');
  } catch(e) { document.getElementById('proofStatus').textContent = '✕ Network error'; }
}

function handleProofDrop(e) {
  e.preventDefault();
  document.getElementById('proofDropZone').style.borderColor = 'var(--border-md)';
  const file = e.dataTransfer.files[0];
  if (file) uploadProof(file);
}

document.getElementById('deleteProofBtn')?.addEventListener('click', async function () {
  if (!confirm('Remove this ID proof file?')) return;
  const r = await fetch(`${BASE}/tenants/${TENANT_ID}/delete-proof`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `_csrf=${encodeURIComponent(CSRF)}`
  });
  const d = await r.json();
  if (d.success) location.reload();
  else alert(d.error || 'Failed to delete.');
});
</script>
