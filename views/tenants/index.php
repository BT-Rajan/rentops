<div class="d-flex align-center justify-between mb-24">
  <div class="d-flex gap-8">
    <a href="/tenants?status=active"  class="btn btn-sm <?= $status==='active'  ? 'btn-primary' : 'btn-secondary' ?>">Active</a>
    <a href="/tenants?status=vacated" class="btn btn-sm <?= $status==='vacated' ? 'btn-primary' : 'btn-secondary' ?>">Vacated</a>
    <a href="/tenants"                class="btn btn-sm <?= !in_array($status,['active','vacated']) ? 'btn-primary' : 'btn-secondary' ?>">All</a>
  </div>
  <a href="/tenants/new" class="btn btn-primary btn-sm">+ Add Tenant</a>
</div>

<!-- Search -->
<form method="GET" action="/tenants" class="mb-16">
  <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
  <div style="position:relative;max-width:340px">
    <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-hint)" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="search" name="q" class="form-control" style="padding-left:34px"
           placeholder="Search name, phone, room…"
           value="<?= htmlspecialchars($search) ?>">
  </div>
</form>

<!-- Table -->
<div class="card">
  <div class="table-wrap">
    <?php if ($tenants): ?>
    <table>
      <thead>
        <tr>
          <th>Tenant</th><th>Phone</th><th>Room</th><th>Rent</th><th>Outstanding</th><th>Move-in</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tenants as $t): ?>
        <?php
          $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $t['full_name']), 0, 2)));
          $outstanding = (float)$t['outstanding'];
        ?>
        <tr>
          <td>
            <div class="d-flex align-center gap-12">
              <div class="avatar"><?= htmlspecialchars($initials) ?></div>
              <div>
                <div class="fw-600"><a href="/tenants/<?= htmlspecialchars($t['id']) ?>"><?= htmlspecialchars($t['full_name']) ?></a></div>
                <div class="text-xs text-muted"><?= htmlspecialchars($t['email'] ?: '—') ?></div>
              </div>
            </div>
          </td>
          <td class="text-sm"><?= htmlspecialchars($t['phone']) ?></td>
          <td class="fw-600"><?= $t['room_number'] ? 'Room ' . htmlspecialchars($t['room_number']) : '<span class="text-hint">—</span>' ?></td>
          <td class="text-sm">₹<?= $t['agreed_rent'] ? number_format((float)$t['agreed_rent']) : '—' ?></td>
          <td>
            <?php if ($outstanding > 0): ?>
              <span class="text-danger fw-600">₹<?= number_format($outstanding) ?></span>
              <?php if ((int)$t['overdue_count'] > 0): ?>
                <div class="text-xs text-danger"><?= (int)$t['overdue_count'] ?> month<?= $t['overdue_count'] > 1 ? 's' : '' ?> overdue</div>
              <?php endif; ?>
            <?php elseif ($t['tenancy_id']): ?>
              <span class="text-success">₹0</span>
            <?php else: ?>
              <span class="text-hint">—</span>
            <?php endif; ?>
          </td>
          <td class="text-sm text-muted"><?= $t['move_in_date'] ?? '—' ?></td>
          <td>
            <span class="badge badge-<?= $t['status'] === 'active' ? 'success' : 'muted' ?>">
              <?= ucfirst($t['status']) ?>
            </span>
          </td>
          <td>
            <a href="/tenants/<?= htmlspecialchars($t['id']) ?>" class="btn btn-ghost btn-sm">View</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      <p>No tenants found<?= $search ? " for "$search"" : '' ?>.</p>
      <a href="/tenants/new" class="btn btn-primary btn-sm" style="margin-top:12px">+ Add first tenant</a>
    </div>
    <?php endif; ?>
  </div>
</div>
