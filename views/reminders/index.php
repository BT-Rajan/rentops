<div class="d-flex align-center justify-between mb-24">
  <div class="d-flex align-center gap-8">
    <label class="text-sm text-muted">Period</label>
    <input type="month" id="monthPicker" class="form-control" style="width:150px"
           value="<?= htmlspecialchars($month) ?>"
           onchange="location.href='/reminders?month='+this.value">
  </div>
  <div class="d-flex gap-8">
    <button class="btn btn-secondary btn-sm" id="selectAllBtn">Select all</button>
    <button class="btn btn-primary btn-sm" id="previewBtn" disabled>
      Preview messages (<span id="selCount">0</span>)
    </button>
  </div>
</div>

<?php if ($overdue): ?>
<div class="card mb-20">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:36px"><input type="checkbox" id="masterCheck"></th>
          <th>Tenant</th><th>Room</th>
          <th class="text-right">Balance</th><th>Status</th><th>Overdue</th><th>Phone</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($overdue as $r): ?>
        <tr>
          <td><input type="checkbox" class="inv-check" value="<?= htmlspecialchars($r['invoice_id']) ?>"></td>
          <td><a href="/tenants/<?= htmlspecialchars($r['tenant_id']) ?>" class="fw-600"><?= htmlspecialchars($r['full_name']) ?></a></td>
          <td>Room <?= htmlspecialchars($r['room_number']) ?></td>
          <td class="text-right text-danger fw-600">₹<?= number_format((float)$r['balance']) ?></td>
          <td><span class="badge badge-<?= $r['status']==='overdue'?'danger':($r['status']==='partial'?'warning':'muted') ?>"><?= ucfirst($r['status']) ?></span></td>
          <td class="text-sm <?= (int)$r['days_overdue'] > 0 ? 'text-danger' : 'text-hint' ?>">
            <?= (int)$r['days_overdue'] > 0 ? (int)$r['days_overdue'] . 'd overdue' : 'Not yet due' ?>
          </td>
          <td class="text-sm"><?= htmlspecialchars($r['phone']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <p>No pending dues for <?= htmlspecialchars($month) ?>. All clear!</p>
  </div>
</div>
<?php endif; ?>

<!-- Message preview modal -->
<div id="previewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:300;overflow-y:auto;padding:40px 16px">
  <div style="max-width:640px;margin:0 auto;background:var(--surface);border-radius:var(--radius-xl);overflow:hidden;box-shadow:var(--shadow)">
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <span style="font-size:16px;font-weight:600">WhatsApp message preview</span>
      <button onclick="closeModal()" class="btn btn-ghost btn-sm">✕</button>
    </div>
    <div id="messageList" style="padding:20px 24px;display:flex;flex-direction:column;gap:20px"></div>
  </div>
</div>

<script>
const csrf   = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';
const checks = () => [...document.querySelectorAll('.inv-check:checked')];

function updateCount() {
  const n = checks().length;
  document.getElementById('selCount').textContent = n;
  document.getElementById('previewBtn').disabled  = n === 0;
}

document.getElementById('masterCheck').addEventListener('change', function () {
  document.querySelectorAll('.inv-check').forEach(c => c.checked = this.checked);
  updateCount();
});
document.addEventListener('change', e => {
  if (e.target.classList.contains('inv-check')) updateCount();
});

document.getElementById('selectAllBtn').addEventListener('click', () => {
  const all = document.querySelectorAll('.inv-check');
  const anyUnchecked = [...all].some(c => !c.checked);
  all.forEach(c => c.checked = anyUnchecked);
  document.getElementById('masterCheck').checked = anyUnchecked;
  updateCount();
});

document.getElementById('previewBtn').addEventListener('click', async function () {
  const ids = checks().map(c => c.value);
  this.disabled = true; this.textContent = 'Loading…';
  try {
    const body = `_csrf=${encodeURIComponent(csrf)}&${ids.map(id => `invoice_ids[]=${encodeURIComponent(id)}`).join('&')}`;
    const r    = await fetch('/reminders/preview', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
    const data = await r.json();
    renderMessages(data.messages || []);
    document.getElementById('previewModal').style.display = 'block';
  } catch(e) { alert('Failed to load previews.'); }
  this.disabled = false;
  this.innerHTML = `Preview messages (<span id="selCount">${ids.length}</span>)`;
});

function renderMessages(msgs) {
  document.getElementById('messageList').innerHTML = msgs.map((m, i) => `
    <div style="background:var(--surface-2);border-radius:var(--radius);padding:16px">
      <div class="d-flex justify-between align-center mb-8">
        <div>
          <div class="fw-600">${escHtml(m.name)}</div>
          <div class="text-sm text-muted">${escHtml(m.phone)}</div>
        </div>
        <div class="d-flex gap-8">
          <button onclick="copyMsg(${i})" class="btn btn-secondary btn-sm">Copy</button>
          <a href="https://wa.me/91${m.phone.replace(/\D/g,'')}?text=${encodeURIComponent(m.message)}"
             target="_blank" class="btn btn-primary btn-sm">
            Open WhatsApp
          </a>
        </div>
      </div>
      <pre id="msg-${i}" style="font-family:var(--font);font-size:13px;white-space:pre-wrap;line-height:1.6;color:var(--text-primary)">${escHtml(m.message)}</pre>
    </div>
  `).join('');
  window._messages = msgs;
}

function copyMsg(i) {
  navigator.clipboard?.writeText(window._messages[i].message).then(() => alert('Copied!'));
}

function closeModal() {
  document.getElementById('previewModal').style.display = 'none';
}

function escHtml(s) { const d = document.createElement('div'); d.textContent = s||''; return d.innerHTML; }
</script>
