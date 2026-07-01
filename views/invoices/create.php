<?php
$ebPrice = (float)($property['eb_unit_price'] ?? 0);
$gstRate = (float)($property['rent_gst_rate'] ?? 18);
?>

<!-- SECURITY FIX: 5 inline onchange/oninput handlers + inline <script> with
     PHP-interpolated EB_PRICE/GST_RATE moved to /assets/js/invoice-create.js -->
<div class="d-flex gap-20 align-start" style="flex-wrap:wrap" id="invoiceCreateRoot"
     data-eb-price="<?= htmlspecialchars((string)$ebPrice) ?>"
     data-gst-rate="<?= htmlspecialchars((string)$gstRate) ?>">

  <!-- Form -->
  <div style="flex:1;min-width:340px">
    <div class="card">
      <div class="card-header"><span class="card-title">Bill Details</span></div>
      <div class="card-body">
        <form action="<?= url('/invoices') ?>" method="POST" id="billForm">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

          <!-- Tenant dropdown -->
          <div class="form-group">
            <label class="form-label" for="tenancy_id">Active Tenant <span class="req">*</span></label>
            <select id="tenancy_id" name="tenancy_id" class="form-control" required>
              <option value="">— Select tenant —</option>
              <?php foreach ($tenants as $t): ?>
              <option value="<?= htmlspecialchars($t['tenancy_id']) ?>"
                      data-rent="<?= (float)$t['effective_rent'] ?>"
                      data-room="<?= htmlspecialchars($t['room_number']) ?>">
                Room <?= htmlspecialchars($t['room_number']) ?> — <?= htmlspecialchars($t['full_name']) ?>
                (₹<?= number_format((float)$t['effective_rent']) ?>/mo)
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Month -->
          <div class="form-group">
            <label class="form-label" for="month">Billing Month <span class="req">*</span></label>
            <input type="month" id="month" name="month" class="form-control"
                   value="<?= date('Y-m') ?>" max="<?= date('Y-m') ?>" required>
          </div>

          <hr style="margin:20px 0;border:none;border-top:1px solid var(--border)">

          <!-- Rent (read-only) -->
          <div class="form-group">
            <label class="form-label">Rent</label>
            <input type="text" id="rentDisplay" class="form-control" value="₹0" readonly
                   style="background:var(--surface-2);cursor:default">
          </div>

          <!-- GST on Rent -->
          <div class="form-group">
            <label class="form-label">
              GST on Rent
              <span class="badge badge-muted" style="font-size:10px;margin-left:4px"><?= $gstRate ?>%</span>
            </label>
            <input type="text" id="gstDisplay" class="form-control" value="₹0" readonly
                   style="background:var(--surface-2);cursor:default">
          </div>

          <hr style="margin:20px 0;border:none;border-top:1px solid var(--border)">

          <!-- EB Units -->
          <div class="form-group">
            <label class="form-label" for="eb_units">
              Electricity Units Consumed
              <?php if ($ebPrice > 0): ?>
              <span class="text-hint text-sm" style="font-weight:400"> @ ₹<?= number_format($ebPrice, 2) ?>/unit</span>
              <?php else: ?>
              <span class="text-hint text-sm" style="font-weight:400;color:var(--warning)"> (Set unit price in Settings)</span>
              <?php endif; ?>
            </label>
            <input type="number" id="eb_units" name="eb_units" class="form-control"
                   min="0" step="0.01" value="0" placeholder="e.g. 100">
          </div>

          <!-- EB Amount (read-only) -->
          <div class="form-group" id="ebAmtRow" style="display:none">
            <label class="form-label">Electricity Amount</label>
            <input type="text" id="ebAmtDisplay" class="form-control" readonly
                   style="background:var(--surface-2);cursor:default">
          </div>

          <hr style="margin:20px 0;border:none;border-top:1px solid var(--border)">

          <!-- Other charges -->
          <div class="form-group">
            <label class="form-label" for="other_charges">Other Charges</label>
            <div class="d-flex gap-8">
              <input type="text" id="other_charges_desc" name="other_charges_desc" class="form-control"
                     placeholder="Description (e.g. Water, Maintenance)" style="flex:2">
              <input type="number" id="other_charges" name="other_charges" class="form-control"
                     min="0" step="0.01" value="0" placeholder="₹0" style="flex:1">
            </div>
          </div>

          <!-- Total -->
          <div style="background:color-mix(in srgb,var(--primary) 8%,transparent);border:1px solid color-mix(in srgb,var(--primary) 20%,transparent);border-radius:var(--radius);padding:16px 20px;margin:20px 0">
            <div class="d-flex justify-between align-center">
              <span class="fw-600" style="font-size:15px">Total Amount Due</span>
              <span class="fw-600" style="font-size:22px;color:var(--primary)" id="totalDisplay">₹0</span>
            </div>
          </div>

          <div class="d-flex gap-10">
            <button type="submit" class="btn btn-primary" style="flex:1">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              Generate Invoice & PDF
            </button>
            <a href="<?= url('/dues') ?>" class="btn btn-ghost">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Live preview panel -->
  <div style="width:320px;min-width:260px;flex-shrink:0">
    <div class="card" style="position:sticky;top:20px">
      <div class="card-header"><span class="card-title">Invoice Preview</span></div>
      <div class="card-body" id="previewPanel">
        <p class="text-muted text-sm">Select a tenant to preview.</p>
      </div>
    </div>
  </div>

</div>

<script src="<?= asset("/assets/js/invoice-create.js") ?>"></script>
