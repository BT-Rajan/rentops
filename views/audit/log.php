<div style="margin-bottom:20px">
  <a href="<?= url("/audit") ?>" class="btn btn-ghost btn-sm" style="padding-left:0">← Back to audit</a>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Audit log</span>
    <span class="text-sm text-muted"><?= number_format($total) ?> entries</span>
  </div>
  <div class="table-wrap">
    <?php if ($entries): ?>
    <table>
      <thead>
        <tr><th>Time</th><th>Actor</th><th>Action</th><th>Entity</th><th>IP</th><th>Detail</th></tr>
      </thead>
      <tbody>
        <?php foreach ($entries as $e): ?>
        <?php
          $actionCls = match(true) {
              str_contains($e['action'], 'fail')   => 'badge-danger',
              str_contains($e['action'], 'delete') => 'badge-warning',
              str_contains($e['action'], 'login')  => 'badge-info',
              default                              => 'badge-muted',
          };
          $payload = $e['payload'] ? json_decode($e['payload'], true) : [];
        ?>
        <tr>
          <td class="text-xs text-muted" style="white-space:nowrap">
            <?= date('d M Y', strtotime($e['created_at'])) ?><br>
            <?= date('H:i:s', strtotime($e['created_at'])) ?>
          </td>
          <td class="fw-600 text-sm"><?= htmlspecialchars($e['actor']) ?></td>
          <td><span class="badge <?= $actionCls ?>"><?= htmlspecialchars(str_replace('_', ' ', $e['action'])) ?></span></td>
          <td class="text-sm text-muted">
            <?= htmlspecialchars($e['entity_type']) ?>
            <?php if ($e['entity_id']): ?>
              <div class="text-xs" style="font-family:var(--font-mono)"><?= htmlspecialchars(substr($e['entity_id'], 0, 8)) ?>…</div>
            <?php endif; ?>
          </td>
          <td class="text-xs text-muted"><?= htmlspecialchars($e['ip'] ?? '—') ?></td>
          <td class="text-xs text-muted">
            <?php if ($payload): ?>
              <?php foreach (array_slice($payload, 0, 3) as $k => $v): ?>
                <div><?= htmlspecialchars($k) ?>: <strong><?= htmlspecialchars((string)$v) ?></strong></div>
              <?php endforeach; ?>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
      <p>No audit log entries yet.</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:8px;align-items:center">
    <?php if ($page > 1): ?>
      <a href="<?= url("/audit/log?page=" . ($page - 1)) ?>" class="btn btn-secondary btn-sm">← Prev</a>
    <?php endif; ?>
    <span class="text-sm text-muted">Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?>
      <a href="<?= url("/audit/log?page=" . ($page + 1)) ?>" class="btn btn-secondary btn-sm">Next →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
