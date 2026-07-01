<div style="max-width:560px">
  <a href="<?= url("/tenants/" . htmlspecialchars($tenant['id'])) ?>" class="btn btn-ghost btn-sm" style="padding-left:0;margin-bottom:20px">← Back to tenant</a>

<div id="moveoutRoot"
     data-deposit="<?= (float)($tenancy['security_deposit'] ?? 0) ?>"
     data-rent="<?= (float)($tenancy['agreed_rent'] ?? 0) ?>"
     data-move-in-date="<?= htmlspecialchars($tenancy['move_in_date'] ?? '') ?>">
  <div class="card">
    <div class="card-header">
      <span class="card-title">Move-out — <?= htmlspecialchars($tenant['full_name']) ?></span>
    </div>
    <div class="card-body">

      <?php if (!$tenancy): ?>
        <div class="flash flash-error">No active tenancy found for this tenant.</div>
      <?php else: ?>

      <!-- Tenancy summary -->
      <div style="background:var(--surface-2);border-radius:var(--radius);padding:14px 16px;margin-bottom:20px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div><div class="text-xs text-muted">Room</div><div class="fw-600">Room <?= htmlspecialchars($tenancy['room_number']) ?></div></div>
          <div><div class="text-xs text-muted">Move-in</div><div class="fw-600"><?= htmlspecialchars($tenancy['move_in_date']) ?></div></div>
          <div><div class="text-xs text-muted">Monthly rent</div><div class="fw-600">₹<?= number_format((float)$tenancy['agreed_rent']) ?></div></div>
          <div><div class="text-xs text-muted">Deposit held</div><div class="fw-600">₹<?= number_format((float)$tenancy['security_deposit']) ?></div></div>
        </div>
      </div>

      <form action="<?= url("/tenants/" . htmlspecialchars($tenant['id']) . "/moveout") ?>" method="POST" novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <?php if (!empty($tenancy['scheduled_move_out_date'])): ?>
        <div class="flash flash-info mb-16">
          A move-out is already scheduled for <?= date('d M Y', strtotime($tenancy['scheduled_move_out_date'])) ?>.
          Submitting below will update or execute it.
        </div>
        <?php endif; ?>

        <div class="form-group">
          <label class="form-label" for="move_out_date">Move-out date <span class="req">*</span></label>
          <input type="date" id="move_out_date" name="move_out_date" class="form-control"
                 value="<?= htmlspecialchars($tenancy['scheduled_move_out_date'] ?? date('Y-m-d')) ?>"
                 min="<?= htmlspecialchars($tenancy['move_in_date']) ?>"
                 required>
          <div class="form-hint" id="proRataHint"></div>
          <div class="form-hint" style="margin-top:4px">A future date schedules the move-out without affecting the tenant or room today. A past or today's date executes it immediately.</div>
          <div id="sameMonthWarn" style="display:none;margin-top:6px" class="flash flash-info" style="padding:8px 12px">
            ⚠ Same-month exit — only days occupied will be charged.
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="deposit_deduction">Deposit deduction (₹)</label>
          <input type="number" id="deposit_deduction" name="deposit_deduction" class="form-control"
                 value="0" min="0" max="<?= (float)$tenancy['security_deposit'] ?>" step="100"
                >
          <div class="form-hint">Damage, unpaid dues, cleaning, etc.</div>
        </div>

        <!-- Deposit reconciliation preview -->
        <div style="background:var(--surface-2);border-radius:var(--radius);padding:14px 16px;margin-bottom:20px" id="reconcileBox">
          <div class="text-sm fw-600 mb-8">Deposit reconciliation</div>
          <div class="d-flex justify-between text-sm mb-4">
            <span class="text-muted">Deposit held</span>
            <span>₹<?= number_format((float)$tenancy['security_deposit']) ?></span>
          </div>
          <div class="d-flex justify-between text-sm mb-4">
            <span class="text-muted">Deduction</span>
            <span class="text-danger" id="deductionDisplay">₹0</span>
          </div>
          <div style="border-top:1px solid var(--border);margin:8px 0"></div>
          <div class="d-flex justify-between text-sm fw-600">
            <span>Refund to tenant</span>
            <span class="text-success" id="refundDisplay">₹<?= number_format((float)$tenancy['security_deposit']) ?></span>
          </div>
        </div>

        <div class="d-flex gap-12">
          <button type="submit" class="btn btn-danger" data-confirm="Confirm move-out for <?= htmlspecialchars($tenant['full_name']) ?>?">
            Confirm move-out
          </button>
          <a href="<?= url("/tenants/" . htmlspecialchars($tenant['id'])) ?>" class="btn btn-secondary"><?= __('common.cancel') ?></a>
        </div>
      </form>

      <?php endif; ?>
    </div>
  </div>
</div>

</div><!-- /#moveoutRoot -->
<script src="<?= asset("/assets/js/moveout.js") ?>"></script>
