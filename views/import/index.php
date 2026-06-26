<div style="max-width:680px">

  <!-- Instructions -->
  <div class="card mb-20">
    <div class="card-header">
      <span class="card-title">Bulk tenant import</span>
      <a href="<?= url("/import/template") ?>" class="btn btn-secondary btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Download CSV template
      </a>
    </div>
    <div class="card-body">
      <div class="text-sm text-muted mb-16">
        Upload a CSV with your existing tenant data. The importer will:
        create tenants, assign rooms, generate backfilled invoices from move-in date to today,
        and skip any rows that already exist (safe to re-run).
      </div>

      <!-- Required columns -->
      <div style="background:var(--surface-2);border-radius:var(--radius);padding:14px 16px;margin-bottom:20px">
        <div class="text-sm fw-600 mb-8">Required columns</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
          <?php foreach (['full_name','phone','room_number','move_in_date','agreed_rent','security_deposit'] as $col): ?>
            <code style="background:var(--c-primary-lt);color:var(--c-primary-dk);padding:2px 8px;border-radius:4px;font-size:12px"><?= $col ?></code>
          <?php endforeach; ?>
        </div>
        <div class="text-xs text-muted mt-8">Optional: <code>email, id_proof_type, id_proof_number, emergency_contact, rent_due_day</code></div>
        <div class="text-xs text-muted mt-4"><strong>move_in_date</strong> format: YYYY-MM-DD &nbsp;·&nbsp; <strong>room_number</strong> must match existing rooms (e.g. 101, G01)</div>
      </div>

      <form action="<?= url("/import/preview") ?>" method="POST" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <!-- Drop zone -->
        <div id="dropZone" style="border:2px dashed var(--border-md);border-radius:var(--radius-lg);padding:40px 20px;text-align:center;cursor:pointer;transition:all var(--transition);margin-bottom:16px"
             onclick="document.getElementById('csvFile').click()"
             ondragover="event.preventDefault();this.style.borderColor='var(--c-primary)';this.style.background='var(--c-primary-lt)'"
             ondragleave="this.style.borderColor='var(--border-md)';this.style.background=''"
             ondrop="handleDrop(event)">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--text-hint)" stroke-width="1.5" style="margin:0 auto 12px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <div class="fw-600" style="margin-bottom:4px">Drop CSV here or click to browse</div>
          <div id="fileLabel" class="text-sm text-muted">No file selected — max 2 MB</div>
        </div>
        <input type="file" id="csvFile" name="csv" accept=".csv,text/csv" style="display:none" onchange="showFile(this)">

        <button type="submit" id="uploadBtn" class="btn btn-primary" disabled>
          Preview import →
        </button>
      </form>
    </div>
  </div>

  <!-- Sample data hint -->
  <div class="card">
    <div class="card-header"><span class="card-title">CSV sample</span></div>
    <div class="card-body" style="padding:0">
      <div style="overflow-x:auto">
        <table style="font-size:12px;font-family:var(--font-mono)">
          <thead>
            <tr>
              <th>full_name</th><th>phone</th><th>room_number</th><th>move_in_date</th><th>agreed_rent</th><th>security_deposit</th><th>rent_due_day</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Ravi Kumar</td><td>9876543210</td><td>101</td><td>2024-01-01</td><td>9000</td><td>18000</td><td>5</td>
            </tr>
            <tr>
              <td>Priya Singh</td><td>9123456789</td><td>102</td><td>2024-02-15</td><td>9000</td><td>18000</td><td>5</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
function showFile(input) {
  const file = input.files[0];
  if (file) {
    document.getElementById('fileLabel').textContent = file.name + ' — ' + (file.size/1024).toFixed(1) + ' KB';
    document.getElementById('uploadBtn').disabled = false;
    document.getElementById('dropZone').style.borderColor = 'var(--c-primary)';
  }
}

function handleDrop(e) {
  e.preventDefault();
  document.getElementById('dropZone').style.borderColor = 'var(--border-md)';
  document.getElementById('dropZone').style.background = '';
  const file = e.dataTransfer.files[0];
  if (file && file.name.endsWith('.csv')) {
    const dt   = new DataTransfer();
    dt.items.add(file);
    const input = document.getElementById('csvFile');
    input.files = dt.files;
    showFile(input);
  } else {
    alert('Please drop a .csv file.');
  }
}
</script>
