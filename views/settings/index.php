<div style="max-width:520px">
  <div class="card">
    <div class="card-header"><span class="card-title">Property settings</span></div>
    <div class="card-body">
      <form action="/settings" method="POST" novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="form-group">
          <label class="form-label" for="name">Property name</label>
          <input type="text" id="name" name="name" class="form-control"
                 value="<?= htmlspecialchars($property['name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="address">Address</label>
          <textarea id="address" name="address" class="form-control" rows="3"><?= htmlspecialchars($property['address'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="default_due_day">Default rent due day</label>
          <select id="default_due_day" name="default_due_day" class="form-control" style="max-width:200px">
            <?php for ($d = 1; $d <= 28; $d++): ?>
              <option value="<?= $d ?>" <?= ($property['default_due_day'] ?? 5) == $d ? 'selected' : '' ?>>
                <?= $d ?><?= match(true) { $d===1=>'st',$d===2=>'nd',$d===3=>'rd',default=>'th' } ?> of month
              </option>
            <?php endfor; ?>
          </select>
          <div class="form-hint">Applied when creating new tenancies (can be overridden per tenant).</div>
        </div>

        <button type="submit" class="btn btn-primary">Save settings</button>
      </form>
    </div>
  </div>

  <!-- Change password -->
  <div class="card mt-16">
    <div class="card-header"><span class="card-title">Security</span></div>
    <div class="card-body">
      <div class="text-sm text-muted mb-16">
        To change your password, edit the database directly or add a password reset flow in a future update.
      </div>
      <div class="d-flex justify-between align-center">
        <span class="text-sm">Logged in as</span>
        <span class="fw-600"><?= htmlspecialchars($user['name']) ?></span>
      </div>
    </div>
  </div>
</div>
