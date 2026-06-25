<div style="max-width:900px">
  <a href="/import" class="btn btn-ghost btn-sm" style="padding-left:0;margin-bottom:20px">← Back to import</a>

  <!-- Summary bar -->
  <div class="stat-grid mb-20">
    <div class="stat-card accent-green">
      <div class="stat-label">Ready to import</div>
      <div class="stat-value text-success"><?= count($valid) ?></div>
      <div class="stat-sub">rows</div>
    </div>
    <div class="stat-card <?= $errors ? 'accent-red' : '' ?>">
      <div class="stat-label">Errors</div>
      <div class="stat-value <?= $errors ? 'text-danger' : '' ?>"><?= count($errors) ?></div>
      <div class="stat-sub"><?= $errors ? 'rows skipped' : 'all clear' ?></div>
    </div>
  </div>

  <!-- Errors -->
  <?php if ($errors): ?>
  <div class="card mb-20" style="border-color:#F7C1C1">
    <div class="card-header" style="background:#FCEBEB">
      <span class="card-title text-danger">⚠ Rows with errors (will be skipped)</span>
    </div>
    <div class="card-body" style="padding:12px 20px">
      <?php foreach ($errors as $e): ?>
        <div class="text-sm" style="padding:4px 0;border-bottom:1px solid var(--border);color:var(--c-danger)"><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Valid rows preview -->
  <?php if ($valid): ?>
  <div class="card mb-20">
    <div class="card-header">
      <span class="card-title">Preview — <?= count($valid) ?> rows to be imported</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th><th>Name</th><th>Phone</th><th>Room</th>
            <th>Move-in</th><th class="text-right">Rent</th><th class="text-right">Deposit</th><th>Due day</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($valid as $i => $r): ?>
          <tr>
            <td class="text-muted text-sm"><?= $i + 1 ?></td>
            <td class="fw-600"><?= htmlspecialchars($r['full_name']) ?></td>
            <td class="text-sm"><?= htmlspecialchars($r['phone']) ?></td>
            <td class="fw-600">Room <?= htmlspecialchars($r['room_number']) ?></td>
            <td class="text-sm"><?= htmlspecialchars($r['move_in_date']) ?></td>
            <td class="text-right">₹<?= number_format((float)$r['agreed_rent']) ?></td>
            <td class="text-right">₹<?= number_format((float)$r['security_deposit']) ?></td>
            <td class="text-sm text-muted"><?= htmlspecialchars($r['rent_due_day'] ?? '5') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="d-flex gap-12">
    <form action="/import/confirm" method="POST">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
      <button type="submit" class="btn btn-primary"
              onclick="return confirm('Import <?= count($valid) ?> tenant(s)? This will create tenants, assign rooms, and generate all invoices from move-in date.')">
        Confirm — import <?= count($valid) ?> tenant<?= count($valid) !== 1 ? 's' : '' ?>
      </button>
    </form>
    <a href="/import" class="btn btn-secondary">Cancel</a>
  </div>

  <?php else: ?>
  <div class="card">
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <p>No valid rows to import. Fix the errors above and re-upload.</p>
      <a href="/import" class="btn btn-primary btn-sm" style="margin-top:12px">Upload again</a>
    </div>
  </div>
  <?php endif; ?>
</div>
