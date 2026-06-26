<!-- Summary strip -->
<div class="stat-grid mb-24">
  <div class="stat-card <?= $stats['with_issues'] > 0 ? 'accent-red' : 'accent-green' ?>">
    <div class="stat-label">Tenancies with issues</div>
    <div class="stat-value <?= $stats['with_issues'] > 0 ? 'text-danger' : 'text-success' ?>"><?= $stats['with_issues'] ?></div>
    <div class="stat-sub">of <?= $stats['total'] ?> total</div>
  </div>
  <div class="stat-card accent-green">
    <div class="stat-label">Clean tenancies</div>
    <div class="stat-value text-success"><?= $stats['clean'] ?></div>
    <div class="stat-sub">no issues found</div>
  </div>
  <div class="stat-card <?= $stats['missing_inv'] > 0 ? 'accent-amber' : '' ?>">
    <div class="stat-label">Missing invoices</div>
    <div class="stat-value <?= $stats['missing_inv'] > 0 ? 'text-warning' : '' ?>"><?= $stats['missing_inv'] ?></div>
    <div class="stat-sub">gap months detected</div>
  </div>
  <div class="stat-card <?= $stats['overpayments'] > 0 ? 'accent-amber' : '' ?>">
    <div class="stat-label">Overpayments</div>
    <div class="stat-value <?= $stats['overpayments'] > 0 ? 'text-warning' : '' ?>"><?= $stats['overpayments'] ?></div>
    <div class="stat-sub">need review</div>
  </div>
</div>

<!-- Actions -->
<div class="d-flex gap-10 mb-24">
  <?php if ($stats['missing_inv'] > 0): ?>
  <form action="<?= url("/audit/fix") ?>" method="POST">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <button type="submit" class="btn btn-primary"
            onclick="return confirm('Auto-generate <?= $stats['missing_inv'] ?> missing invoice(s)?')">
      ⚡ Auto-fix missing invoices
    </button>
  </form>
  <?php else: ?>
    <span class="btn btn-secondary" style="cursor:default;opacity:.6">⚡ Auto-fix (nothing to fix)</span>
  <?php endif; ?>
  <a href="<?= url("/audit/log") ?>" class="btn btn-secondary">View audit log →</a>
</div>

<!-- Results -->
<?php if ($results): ?>
<div class="card">
  <div class="card-header">
    <span class="card-title">Tenancy audit results</span>
    <span class="text-sm text-muted"><?= $stats['total'] ?> tenancies checked</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Tenant</th><th>Room</th><th>Move-in</th><th>Status</th><th>Issues</th></tr>
      </thead>
      <tbody>
        <?php foreach ($results as $r): ?>
        <tr>
          <td>
            <a href="<?= url("/tenants/" . htmlspecialchars($r['tenancy']['tenant_id'] ?? '')) ?>" class="fw-600">
              <?= htmlspecialchars($r['tenancy']['full_name']) ?>
            </a>
          </td>
          <td>Room <?= htmlspecialchars($r['tenancy']['room_number']) ?></td>
          <td class="text-sm text-muted"><?= htmlspecialchars($r['tenancy']['move_in_date']) ?></td>
          <td>
            <span class="badge badge-<?= $r['tenancy']['status'] === 'active' ? 'success' : 'muted' ?>">
              <?= ucfirst($r['tenancy']['status']) ?>
            </span>
          </td>
          <td>
            <?php if (empty($r['issues'])): ?>
              <span class="text-success text-sm">✓ Clean</span>
            <?php else: ?>
              <div style="display:flex;flex-direction:column;gap:4px">
                <?php foreach ($r['issues'] as $issue): ?>
                  <?php $cls = $issue['type'] === 'overpayment' ? 'badge-warning' : 'badge-danger'; ?>
                  <span class="badge <?= $cls ?>" style="font-size:11px">
                    <?= htmlspecialchars($issue['msg']) ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
