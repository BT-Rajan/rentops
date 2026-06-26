<div style="max-width:640px">
  <a href="<?= url("/tenants/" . htmlspecialchars($tenant['id'])) ?>" class="btn btn-ghost btn-sm" style="padding-left:0;margin-bottom:20px">← Back to tenant</a>

  <div class="card">
    <div class="card-header">
      <span class="card-title">Move-in — <?= htmlspecialchars($tenant['full_name']) ?></span>
    </div>
    <div class="card-body">
      <form action="<?= url("/tenants/" . htmlspecialchars($tenant['id']) . "/movein") ?>" method="POST" novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="form-group">
          <label class="form-label" for="room_id">Assign room <span class="req">*</span></label>
          <select id="room_id" name="room_id" class="form-control" required onchange="setBaseRent(this)">
            <option value="">— Select room —</option>
            <?php foreach ($rooms as $r): ?>
              <option value="<?= htmlspecialchars($r['id']) ?>"
                      data-rent="<?= (float)$r['base_rent'] ?>"
                      <?= ($_POST['room_id'] ?? '') === $r['id'] ? 'selected' : '' ?>>
                Room <?= htmlspecialchars($r['room_number']) ?> — <?= ucfirst($r['room_type']) ?> — ₹<?= number_format((float)$r['base_rent']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($rooms)): ?>
            <div class="form-hint text-danger">No available rooms. <a href="<?= url("/rooms") ?>">Manage rooms</a></div>
          <?php endif; ?>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="move_in_date">Move-in date <span class="req">*</span></label>
            <input type="date" id="move_in_date" name="move_in_date" class="form-control"
                   value="<?= htmlspecialchars($_POST['move_in_date'] ?? date('Y-m-d')) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="rent_due_day">Monthly due day <span class="req">*</span></label>
            <select id="rent_due_day" name="rent_due_day" class="form-control">
              <?php for ($d = 1; $d <= 28; $d++): ?>
                <option value="<?= $d ?>" <?= ($_POST['rent_due_day'] ?? 5) == $d ? 'selected' : '' ?>>
                  <?= $d ?><?= match(true) { $d===1=>'st',$d===2=>'nd',$d===3=>'rd',default=>'th' } ?> of month
                </option>
              <?php endfor; ?>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="agreed_rent">Agreed monthly rent (₹) <span class="req">*</span></label>
            <input type="number" id="agreed_rent" name="agreed_rent" class="form-control"
                   value="<?= htmlspecialchars($_POST['agreed_rent'] ?? '') ?>" min="0" step="100" required>
            <div class="form-hint" id="proRataHint"></div>
          </div>
          <div class="form-group">
            <label class="form-label" for="security_deposit">Security deposit (₹) <span class="req">*</span></label>
            <input type="number" id="security_deposit" name="security_deposit" class="form-control"
                   value="<?= htmlspecialchars($_POST['security_deposit'] ?? '') ?>" min="0" step="100" required>
          </div>
        </div>

        <div class="d-flex gap-12 mt-8">
          <button type="submit" class="btn btn-primary">Confirm move-in</button>
          <a href="<?= url("/tenants/" . htmlspecialchars($tenant['id'])) ?>" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setBaseRent(sel) {
  const opt  = sel.options[sel.selectedIndex];
  const rent = opt.dataset.rent || '';
  const ar   = document.getElementById('agreed_rent');
  if (!ar.value && rent) ar.value = rent;
}

// Pro-rata preview
function updateProRata() {
  const rent = parseFloat(document.getElementById('agreed_rent').value) || 0;
  const date = document.getElementById('move_in_date').value;
  const hint = document.getElementById('proRataHint');
  if (!rent || !date) { hint.textContent = ''; return; }

  const d    = new Date(date);
  const day  = d.getDate();
  const days = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();

  if (day === 1) {
    hint.textContent = 'Full month — no pro-rata.';
  } else {
    const proRata = Math.round((rent / days) * (days - day + 1));
    hint.textContent = `First invoice: ₹${proRata.toLocaleString('en-IN')} (${days - day + 1} of ${days} days)`;
  }
}

document.getElementById('agreed_rent').addEventListener('input', updateProRata);
document.getElementById('move_in_date').addEventListener('change', updateProRata);
updateProRata();
</script>
