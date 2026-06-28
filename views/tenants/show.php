<?php
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $tenant['full_name']), 0, 2)));
$invMap   = ['paid'=>'success','partial'=>'warning','overdue'=>'danger','unpaid'=>'muted'];
?>

<div style="margin-bottom:20px">
  <a href="<?= url("/tenants") ?>" class="btn btn-ghost btn-sm" style="padding-left:0">← Back to tenants</a>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;align-items:start" class="tenant-detail-grid">

  <!-- Left: profile + actions -->
  <div>
    <!-- Profile card -->
    <div class="card mb-16">
      <div class="card-body" style="text-align:center;padding:28px 20px">
        <div class="avatar" style="width:64px;height:64px;font-size:22px;margin:0 auto 12px">
          <?= htmlspecialchars($initials) ?>
        </div>
        <div class="fw-700" style="font-size:18px"><?= htmlspecialchars($tenant['full_name']) ?></div>
        <div class="text-sm text-muted mt-4"><?= htmlspecialchars($tenant['phone']) ?></div>
        <?php if ($tenant['email']): ?>
          <div class="text-sm text-muted"><?= htmlspecialchars($tenant['email']) ?></div>
        <?php endif; ?>
        <div class="mt-8">
          <span class="badge badge-<?= $tenant['status'] === 'active' ? 'success' : 'muted' ?>">
            <?= ucfirst($tenant['status']) ?>
          </span>
        </div>
      </div>
      <div class="card-body" style="border-top:1px solid var(--border);padding:16px 20px">
        <div style="display:flex;flex-direction:column;gap:10px">
          <div class="d-flex justify-between">
            <span class="text-sm text-muted">ID proof</span>
            <span class="text-sm fw-600"><?= htmlspecialchars($tenant['id_proof_type']) ?></span>
          </div>
          <?php if ($tenant['id_proof_number']): ?>
          <div class="d-flex justify-between">
            <span class="text-sm text-muted">ID number</span>
            <span class="text-sm"><?= htmlspecialchars($tenant['id_proof_number']) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($tenant['emergency_contact']): ?>
          <div class="d-flex justify-between">
            <span class="text-sm text-muted">Emergency</span>
            <span class="text-sm"><?= htmlspecialchars($tenant['emergency_contact']) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($activeTenancy): ?>
          <div class="d-flex justify-between">
            <span class="text-sm text-muted">Room</span>
            <a href="<?= url("/rooms/" . htmlspecialchars($activeTenancy['room_id'])) ?>" class="text-sm fw-600">Room <?= htmlspecialchars($activeTenancy['room_number']) ?></a>
          </div>
          <div class="d-flex justify-between">
            <span class="text-sm text-muted">Agreed rent</span>
            <span class="text-sm fw-600 text-primary-color">₹<?= number_format((float)$activeTenancy['agreed_rent']) ?></span>
          </div>
          <div class="d-flex justify-between">
            <span class="text-sm text-muted">Deposit</span>
            <span class="text-sm">₹<?= number_format((float)$activeTenancy['security_deposit']) ?></span>
          </div>
          <div class="d-flex justify-between">
            <span class="text-sm text-muted"><?= __('tenants.move_in') ?></span>
            <span class="text-sm"><?= htmlspecialchars($activeTenancy['move_in_date']) ?></span>
          </div>
          <div class="d-flex justify-between">
            <span class="text-sm text-muted">Due day</span>
            <span class="text-sm">Day <?= htmlspecialchars($activeTenancy['rent_due_day']) ?> of month</span>
          </div>
          <?php endif; ?>

        <!-- Rent change trigger -->
        <?php if ($activeTenancy): ?>
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
              <input type="date" id="rentEffective" class="form-control mt-8"
                     value="<?= date('Y-m-01') ?>" style="font-size:12px">
              <input type="text" id="rentNote" class="form-control mt-8"
                     placeholder="Reason (optional)" style="font-size:12px">
            </div>
          </div>
        <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Action buttons -->
    <div style="display:flex;flex-direction:column;gap:8px">
      <?php if ($activeTenancy): ?>
        <a href="<?= url("/payments/new") ?>" class="btn btn-primary" style="justify-content:center"><?= __('tenants.record_payment') ?></a>
        <a href="<?= url("/tenants/" . htmlspecialchars($tenant['id']) . "/moveout") ?>" class="btn btn-secondary" style="justify-content:center"><?= __('tenants.process_moveout') ?></a>
      <?php elseif ($tenant['status'] === 'active'): ?>
        <a href="<?= url("/tenants/" . htmlspecialchars($tenant['id']) . "/movein") ?>" class="btn btn-primary" style="justify-content:center"><?= __('tenants.assign_room') ?></a>
      <?php endif; ?>
      <a href="<?= url("/reminders") ?>" class="btn btn-secondary" style="justify-content:center"><?= __('tenants.send_reminder') ?></a>
    </div>

    <!-- ID proof upload -->
    <div class="card mt-16">
      <div class="card-header"><span class="card-title" style="font-size:13px">ID Proof</span></div>
      <div class="card-body" style="padding:14px 16px">
        <?php if (!empty($tenant['id_proof_file'])): ?>
          <div class="d-flex align-center justify-between mb-10">
            <?php $ext = strtolower(pathinfo($tenant['id_proof_file'], PATHINFO_EXTENSION)); ?>
            <?php if (in_array($ext, ['jpg','jpeg','png','webp'])): ?>
              <a href="<?= url(htmlspecialchars($tenant['id_proof_file'])) ?>" target="_blank">
                <img src="<?= url(htmlspecialchars($tenant['id_proof_file'])) ?>" alt="ID proof"
                     style="max-width:100%;border-radius:var(--radius);border:1px solid var(--border)">
              </a>
            <?php else: ?>
              <a href="<?= url(htmlspecialchars($tenant['id_proof_file'])) ?>" target="_blank" class="btn btn-secondary btn-sm">
                📄 View PDF
              </a>
            <?php endif; ?>
          </div>
          <button class="btn btn-danger btn-sm w-full" style="justify-content:center" id="deleteProofBtn">
            Remove file
          </button>
        <?php else: ?>
          <div id="proofDropZone"
               style="border:2px dashed var(--border-md);border-radius:var(--radius);padding:20px;text-align:center;cursor:pointer"
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

  <!-- Right: invoices -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Invoice history</span>
      <?php if ($activeTenancy): ?>
        <a href="<?= url("/payments/new") ?>" class="btn btn-primary btn-sm">+ Record Payment</a>
      <?php endif; ?>
    </div>
    <?php if ($invoices): ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Period</th><th>Amount due</th><th>Paid</th><th>Balance</th><th>Status</th><th>Mode</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($invoices as $inv): ?>
          <?php $bal = (float)$inv['amount_due'] - (float)$inv['amount_paid']; ?>
          <tr>
            <td class="fw-600"><?= date('M Y', strtotime($inv['period_month'])) ?></td>
            <td>₹<?= number_format((float)$inv['amount_due']) ?></td>
            <td class="text-success fw-600">₹<?= number_format((float)$inv['amount_paid']) ?></td>
            <td class="<?= $bal > 0 ? 'text-danger' : '' ?> fw-600">₹<?= number_format($bal) ?></td>
            <td><span class="badge badge-<?= $invMap[$inv['status']] ?? 'muted' ?>"><?= ucfirst($inv['status']) ?></span></td>
            <td class="text-sm text-muted"><?= htmlspecialchars($inv['modes'] ?? '—') ?></td>
            <td>
              <?php if ($inv['status'] !== 'paid'): ?>
                <a href="<?= url("/payments/new?invoice_id=" . htmlspecialchars($inv['id'])) ?>" class="btn btn-primary btn-sm">Pay</a>
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
      <p>No invoices yet.</p>
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
  const newRent  = parseFloat(document.getElementById('newRentInput').value);
  const effDate  = document.getElementById('rentEffective').value;
  const note     = document.getElementById('rentNote').value;
  if (!newRent || newRent <= 0) { alert('Enter a valid rent amount.'); return; }

  const r = await fetch(`${BASE}/tenancies/${TENANCY_ID}/rent-change`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `_csrf=${encodeURIComponent(CSRF)}&new_rent=${newRent}&effective_from=${encodeURIComponent(effDate)}&note=${encodeURIComponent(note)}`
  });
  const d = await r.json();
  if (d.success) {
    alert(`Rent updated to ₹${d.new_rent.toLocaleString('en-IN')}`);
    location.reload();
  } else {
    alert('Error: ' + (d.error || 'Failed to update rent'));
  }
}

async function uploadProof(file) {
  if (!file) return;
  document.getElementById('proofProgress').style.display = 'block';
  document.getElementById('proofLabel').textContent = file.name;
  document.getElementById('proofBar').style.width = '30%';
  document.getElementById('proofStatus').textContent = 'Uploading…';

  const fd = new FormData();
  fd.append('id_proof', file);
  fd.append('_csrf', CSRF);

  try {
    const r = await fetch(`${BASE}/tenants/${TENANT_ID}/upload-proof`, { method: 'POST', body: fd });
    const d = await r.json();
    document.getElementById('proofBar').style.width = '100%';
    if (d.success) {
      document.getElementById('proofStatus').textContent = '✓ Uploaded';
      setTimeout(() => location.reload(), 800);
    } else {
      document.getElementById('proofStatus').textContent = '✕ ' + (d.error || 'Upload failed');
      document.getElementById('proofBar').classList.add('danger');
    }
  } catch(e) {
    document.getElementById('proofStatus').textContent = '✕ Network error';
  }
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
