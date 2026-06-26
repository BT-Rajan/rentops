<?php
function statusBadge(string $s): string {
    $map = [
        'vacant'              => 'success',
        'occupied'            => 'info',
        'partially_occupied'  => 'warning',
        'maintenance'         => 'danger',
    ];
    $label = ucfirst(str_replace('_', ' ', $s));
    $cls   = $map[$s] ?? 'muted';
    return "<span class=\"badge badge-{$cls}\">{$label}</span>";
}
?>

<div class="d-flex align-center justify-between mb-24">
  <div class="d-flex align-center gap-8">
    <button class="btn btn-secondary btn-sm view-toggle active" data-view="grid" aria-pressed="true">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
    </button>
    <button class="btn btn-secondary btn-sm view-toggle" data-view="list" aria-pressed="false">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
    </button>
  </div>
  <div class="d-flex align-center gap-8">
    <span class="text-sm text-muted"><?= count($rooms) ?> rooms total</span>
  </div>
</div>

<!-- Occupancy summary strip -->
<?php
$byStatus = array_count_values(array_column($rooms, 'status'));
$total    = count($rooms);
$occupied = ($byStatus['occupied'] ?? 0) + ($byStatus['partially_occupied'] ?? 0);
$pct      = $total > 0 ? round($occupied / $total * 100) : 0;
?>
<div class="card mb-24">
  <div class="card-body" style="padding:16px 20px">
    <div class="d-flex align-center justify-between mb-8">
      <span class="text-sm fw-600">Occupancy — <?= $pct ?>%</span>
      <div class="d-flex gap-12 text-sm">
        <span><span class="badge badge-info" style="margin-right:4px"><?= $byStatus['occupied'] ?? 0 ?></span> Occupied</span>
        <span><span class="badge badge-warning" style="margin-right:4px"><?= $byStatus['partially_occupied'] ?? 0 ?></span> Partial</span>
        <span><span class="badge badge-success" style="margin-right:4px"><?= $byStatus['vacant'] ?? 0 ?></span> Vacant</span>
        <span><span class="badge badge-danger" style="margin-right:4px"><?= $byStatus['maintenance'] ?? 0 ?></span> Maintenance</span>
      </div>
    </div>
    <div class="progress">
      <div class="progress-bar" style="width:<?= $pct ?>%"></div>
    </div>
  </div>
</div>

<!-- Grid view -->
<div id="roomGrid" class="room-grid">
  <?php foreach ($rooms as $r): ?>
    <a href="<?= url("/rooms/" . htmlspecialchars($r['id'])) ?>" class="room-tile <?= htmlspecialchars($r['status']) ?>">
      <div class="room-number">Room <?= htmlspecialchars($r['room_number']) ?></div>
      <div class="room-type"><?= htmlspecialchars($r['room_type']) ?></div>
      <div class="room-rent">₹<?= number_format((float)$r['agreed_rent'] ?: (float)$r['base_rent']) ?>/mo</div>
      <?php if ($r['tenant_name']): ?>
        <div class="room-tenant">👤 <?= htmlspecialchars($r['tenant_name']) ?></div>
      <?php else: ?>
        <div class="room-tenant text-hint">Vacant</div>
      <?php endif; ?>
      <div style="margin-top:10px"><?= statusBadge($r['status']) ?></div>
    </a>
  <?php endforeach; ?>
</div>

<!-- List view (hidden by default) -->
<div id="roomList" style="display:none">
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Room</th><th>Type</th><th>Tenant</th><th>Rent</th><th>Status</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rooms as $r): ?>
            <tr>
              <td class="fw-600">Room <?= htmlspecialchars($r['room_number']) ?></td>
              <td class="text-sm text-muted" style="text-transform:capitalize"><?= htmlspecialchars($r['room_type']) ?></td>
              <td>
                <?php if ($r['tenant_name']): ?>
                  <a href="<?= url("/tenants/" . htmlspecialchars($r['tenant_id'])) ?>"><?= htmlspecialchars($r['tenant_name']) ?></a>
                <?php else: ?>
                  <span class="text-hint">—</span>
                <?php endif; ?>
              </td>
              <td class="fw-600">₹<?= number_format((float)$r['agreed_rent'] ?: (float)$r['base_rent']) ?></td>
              <td><?= statusBadge($r['status']) ?></td>
              <td><a href="<?= url("/rooms/" . htmlspecialchars($r['id'])) ?>" class="btn btn-ghost btn-sm">View</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.view-toggle').forEach(btn => {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.view-toggle').forEach(b => {
      b.classList.remove('active');
      b.setAttribute('aria-pressed', 'false');
    });
    this.classList.add('active');
    this.setAttribute('aria-pressed', 'true');
    const v = this.dataset.view;
    document.getElementById('roomGrid').style.display = v === 'grid' ? 'grid' : 'none';
    document.getElementById('roomList').style.display = v === 'list' ? 'block' : 'none';
    localStorage.setItem('roomView', v);
  });
});
// Restore preference
const saved = localStorage.getItem('roomView');
if (saved === 'list') document.querySelector('[data-view="list"]').click();
</script>
