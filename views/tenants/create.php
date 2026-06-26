<div style="max-width:640px">
  <a href="<?= url("/tenants") ?>" class="btn btn-ghost btn-sm" style="padding-left:0;margin-bottom:20px">← Back to tenants</a>

  <div class="card">
    <div class="card-header"><span class="card-title">New tenant</span></div>
    <div class="card-body">
      <form action="<?= url("/tenants/new") ?>" method="POST" novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="full_name">Full name <span class="req">*</span></label>
            <input type="text" id="full_name" name="full_name" class="form-control"
                   value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required autofocus>
          </div>
          <div class="form-group">
            <label class="form-label" for="phone">Phone <span class="req">*</span></label>
            <input type="tel" id="phone" name="phone" class="form-control"
                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="emergency_contact">Emergency contact</label>
            <input type="text" id="emergency_contact" name="emergency_contact" class="form-control"
                   value="<?= htmlspecialchars($_POST['emergency_contact'] ?? '') ?>"
                   placeholder="Name — Phone">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="id_proof_type">ID proof type</label>
            <select id="id_proof_type" name="id_proof_type" class="form-control">
              <?php foreach (['Aadhaar','PAN','Passport','Other'] as $t): ?>
                <option value="<?= $t ?>" <?= ($_POST['id_proof_type'] ?? 'Aadhaar') === $t ? 'selected' : '' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="id_proof_number">ID proof number</label>
            <input type="text" id="id_proof_number" name="id_proof_number" class="form-control"
                   value="<?= htmlspecialchars($_POST['id_proof_number'] ?? '') ?>">
          </div>
        </div>

        <div class="d-flex gap-12 mt-8">
          <button type="submit" class="btn btn-primary">Create tenant & assign room →</button>
          <a href="<?= url("/tenants") ?>" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
