<?php
$channelIcon = [
    'sms'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 10.81a19.79 19.79 0 01-3.07-8.63A2 2 0 012 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 14.92z"/></svg>',
    'email'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
    'whatsapp'  => '<svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;vertical-align:-2px;color:#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>',
];
$statusBadge = [
    'sent'      => 'badge-success',
    'scheduled' => 'badge-warning',
    'queued'    => 'badge-muted',
    'failed'    => 'badge-danger',
];
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?> mb-16">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Tabs -->
<div class="d-flex gap-0 mb-24" style="border-bottom:1px solid var(--border)">
  <button class="tab-btn active" data-tab="compose" onclick="switchTab('compose',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
    Compose
  </button>
  <button class="tab-btn" data-tab="history" onclick="switchTab('history',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
    History <span class="badge badge-muted" style="margin-left:4px"><?= count($history) ?></span>
  </button>
</div>

<!-- ─── COMPOSE TAB ──────────────────────────────────────────────────────── -->
<div id="tab-compose">

  <!-- Channel selector -->
  <div class="card mb-20">
    <div class="d-flex gap-12 align-center flex-wrap">
      <span class="text-sm fw-600" style="min-width:70px">Channel</span>
      <?php foreach (['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'Email'] as $ch => $label): ?>
      <label class="channel-pill <?= $ch === 'whatsapp' ? 'active' : '' ?>" id="pill-<?= $ch ?>">
        <input type="radio" name="channel" value="<?= $ch ?>" style="display:none" <?= $ch === 'whatsapp' ? 'checked' : '' ?>>
        <?= $channelIcon[$ch] ?> <?= $label ?>
      </label>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="d-flex gap-16 align-start" style="flex-wrap:wrap">

    <!-- Left: Recipients + Message -->
    <div style="flex:1;min-width:320px">

      <!-- Recipients -->
      <div class="card mb-16">
        <div class="d-flex justify-between align-center mb-12">
          <span class="fw-600 text-sm">Recipients</span>
          <div class="d-flex gap-8">
            <button class="btn btn-ghost btn-sm" onclick="setRecipientType('all')">All Active</button>
            <button class="btn btn-ghost btn-sm" onclick="setRecipientType('overdue')">Overdue Only</button>
            <button class="btn btn-ghost btn-sm" onclick="clearAll()">Clear</button>
          </div>
        </div>
        <input type="text" id="tenantSearch" class="form-control mb-10" placeholder="Search tenant or room…" oninput="filterTenants(this.value)">
        <div id="tenantList" style="max-height:280px;overflow-y:auto;display:flex;flex-direction:column;gap:4px">
          <?php foreach ($tenants as $t): ?>
          <label class="tenant-row" data-name="<?= htmlspecialchars(strtolower($t['full_name'])) ?>"
                 data-room="<?= htmlspecialchars(strtolower($t['room_number'])) ?>"
                 data-balance="<?= (float)$t['balance'] ?>">
            <input type="checkbox" class="tenant-chk" value="<?= htmlspecialchars($t['id']) ?>"
                   data-phone="<?= htmlspecialchars($t['phone']) ?>"
                   data-email="<?= htmlspecialchars($t['email'] ?? '') ?>"
                   data-name="<?= htmlspecialchars($t['full_name']) ?>">
            <div style="flex:1">
              <span class="fw-600 text-sm"><?= htmlspecialchars($t['full_name']) ?></span>
              <span class="text-hint text-sm"> · Room <?= htmlspecialchars($t['room_number']) ?></span>
            </div>
            <?php if ((float)$t['balance'] > 0): ?>
            <span class="badge badge-danger text-sm">₹<?= number_format((float)$t['balance']) ?></span>
            <?php endif; ?>
          </label>
          <?php endforeach; ?>
        </div>
        <div class="mt-10 text-sm text-muted"><span id="selCount">0</span> selected</div>
      </div>

      <!-- Email subject (shown only for email channel) -->
      <div id="subjectRow" class="card mb-16" style="display:none">
        <label class="fw-600 text-sm d-block mb-8">Subject</label>
        <input type="text" id="subjectField" class="form-control" value="Rent Payment Reminder">
      </div>

      <!-- Message -->
      <div class="card mb-16">
        <div class="d-flex justify-between align-center mb-8">
          <span class="fw-600 text-sm">Message</span>
          <button class="btn btn-ghost btn-sm" onclick="loadTemplate()">Load template</button>
        </div>
        <textarea id="msgBody" class="form-control" rows="8" placeholder="Type your message…" oninput="updateCount()"></textarea>
        <div class="d-flex justify-between mt-6">
          <span class="text-hint text-sm" id="charCount">0 chars</span>
          <span class="text-hint text-sm" id="smsSegments"></span>
        </div>
      </div>

      <!-- Attachment -->
      <div class="card mb-16">
        <div class="d-flex justify-between align-center mb-8">
          <span class="fw-600 text-sm">Attachment <span class="text-hint">(optional · PDF/JPG/PNG · max 5 MB)</span></span>
          <button class="btn btn-ghost btn-sm" id="clearFileBtn" onclick="clearFile()" style="display:none">Remove</button>
        </div>
        <input type="file" id="attachFile" accept=".pdf,.jpg,.jpeg,.png" class="form-control" onchange="onFileChange(this)">
        <div id="fileInfo" class="text-sm text-muted mt-6" style="display:none"></div>
      </div>

    </div>

    <!-- Right: Schedule + Send -->
    <div style="width:260px;min-width:220px;flex-shrink:0">
      <div class="card mb-16">
        <span class="fw-600 text-sm d-block mb-12">Send options</span>

        <label class="text-sm fw-600 d-block mb-4">Schedule (optional)</label>
        <input type="datetime-local" id="scheduleAt" class="form-control mb-16"
               min="<?= date('Y-m-d\TH:i') ?>">

        <button class="btn btn-primary" style="width:100%;margin-bottom:8px" onclick="doSend()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          <span id="sendBtnLabel">Send Now</span>
        </button>

        <button class="btn btn-secondary" style="width:100%" onclick="doPreview()">
          Preview message
        </button>
      </div>

      <div class="card" id="summaryCard" style="display:none">
        <span class="fw-600 text-sm d-block mb-8">Summary</span>
        <div id="summaryBody" class="text-sm" style="line-height:1.8"></div>
      </div>
    </div>

  </div>
</div>

<!-- ─── HISTORY TAB ──────────────────────────────────────────────────────── -->
<div id="tab-history" style="display:none">
  <?php if ($history): ?>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Date</th><th>Channel</th><th>Recipients</th>
            <th>Status</th><th>Sent/Failed</th><th>Scheduled</th><th style="width:120px"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h):
            $recs = json_decode($h['recipients'], true) ?? [];
          ?>
          <tr>
            <td class="text-sm"><?= date('d M Y H:i', strtotime($h['created_at'])) ?></td>
            <td><?= ($channelIcon[$h['channel']] ?? '') . ' ' . ucfirst($h['channel']) ?></td>
            <td class="text-sm">
              <?php if (count($recs) <= 2): ?>
                <?= htmlspecialchars(implode(', ', array_column($recs, 'name'))) ?>
              <?php else: ?>
                <?= htmlspecialchars($recs[0]['name']) ?> +<?= count($recs) - 1 ?> more
              <?php endif; ?>
            </td>
            <td><span class="badge <?= $statusBadge[$h['status']] ?? 'badge-muted' ?>"><?= ucfirst($h['status']) ?></span></td>
            <td class="text-sm">
              <?php if ($h['sent_count'] || $h['fail_count']): ?>
                <span class="text-success"><?= (int)$h['sent_count'] ?> sent</span>
                <?php if ($h['fail_count']): ?> · <span class="text-danger"><?= (int)$h['fail_count'] ?> failed</span><?php endif; ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="text-sm text-muted">
              <?= $h['scheduled_at'] ? date('d M H:i', strtotime($h['scheduled_at'])) : '—' ?>
            </td>
            <td>
              <div class="d-flex gap-6">
                <button class="btn btn-ghost btn-sm" onclick="viewLog('<?= htmlspecialchars($h['id']) ?>')">View</button>
                <button class="btn btn-secondary btn-sm" onclick="openResend('<?= htmlspecialchars($h['id']) ?>', <?= htmlspecialchars(json_encode($h['message'])) ?>, <?= htmlspecialchars(json_encode($h['subject'])) ?>, '<?= htmlspecialchars($h['attachment_path'] ?? '') ?>')">Resend</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php else: ?>
  <div class="card">
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:48px;height:48px"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
      <p>No reminders sent yet.</p>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ─── Preview Modal ────────────────────────────────────────────────────── -->
<div id="previewModal" class="modal-backdrop" style="display:none">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <span class="fw-600">Message Preview</span>
      <button onclick="closeModal('previewModal')" class="btn btn-ghost btn-sm">✕</button>
    </div>
    <div id="previewBody" class="modal-body" style="max-height:60vh;overflow-y:auto"></div>
  </div>
</div>

<!-- ─── Log Detail Modal ─────────────────────────────────────────────────── -->
<div id="logModal" class="modal-backdrop" style="display:none">
  <div class="modal" style="max-width:640px">
    <div class="modal-header">
      <span class="fw-600">Reminder Detail</span>
      <button onclick="closeModal('logModal')" class="btn btn-ghost btn-sm">✕</button>
    </div>
    <div id="logBody" class="modal-body" style="max-height:70vh;overflow-y:auto"></div>
  </div>
</div>

<!-- ─── Resend Modal ──────────────────────────────────────────────────────── -->
<div id="resendModal" class="modal-backdrop" style="display:none">
  <div class="modal" style="max-width:540px">
    <div class="modal-header">
      <span class="fw-600">Resend Reminder</span>
      <button onclick="closeModal('resendModal')" class="btn btn-ghost btn-sm">✕</button>
    </div>
    <div class="modal-body">
      <label class="fw-600 text-sm d-block mb-4">Subject (email only)</label>
      <input type="text" id="resendSubject" class="form-control mb-12">
      <label class="fw-600 text-sm d-block mb-4">Message</label>
      <textarea id="resendMsg" class="form-control mb-12" rows="7"></textarea>
      <label class="fw-600 text-sm d-block mb-4">Attachment (replaces original if uploaded)</label>
      <input type="file" id="resendFile" class="form-control mb-4" accept=".pdf,.jpg,.jpeg,.png">
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:16px">
        <input type="checkbox" id="removeAttachment"> Remove existing attachment
      </label>
      <input type="hidden" id="resendLogId">
      <button class="btn btn-primary" style="width:100%" onclick="doResend()">Resend Now</button>
    </div>
  </div>
</div>

<!-- ─── WhatsApp Links Modal ──────────────────────────────────────────────── -->
<div id="waModal" class="modal-backdrop" style="display:none">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <span class="fw-600">Open WhatsApp for each recipient</span>
      <button onclick="closeModal('waModal')" class="btn btn-ghost btn-sm">✕</button>
    </div>
    <div id="waBody" class="modal-body" style="max-height:60vh;overflow-y:auto"></div>
  </div>
</div>

<!-- ─── Result Toast ─────────────────────────────────────────────────────── -->
<div id="toast" style="display:none;position:fixed;bottom:24px;right:24px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px 20px;box-shadow:var(--shadow);z-index:500;max-width:360px;font-size:14px"></div>

<style>
.tab-btn{background:none;border:none;padding:10px 20px;font-size:14px;font-weight:600;cursor:pointer;border-bottom:2px solid transparent;color:var(--text-muted)}
.tab-btn.active{color:var(--primary);border-bottom-color:var(--primary)}
.channel-pill{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:20px;border:1.5px solid var(--border);cursor:pointer;font-size:13px;font-weight:600;transition:.15s;user-select:none}
.channel-pill.active{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary)}
.tenant-row{display:flex;align-items:center;gap:10px;padding:7px 10px;border-radius:var(--radius);cursor:pointer;font-size:13px;transition:.1s}
.tenant-row:hover{background:var(--surface-2)}
.tenant-row.selected{background:color-mix(in srgb,var(--primary) 8%,transparent)}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:300;overflow-y:auto;padding:40px 16px;display:flex;align-items:flex-start;justify-content:center}
.modal{background:var(--surface);border-radius:var(--radius-xl);width:100%;box-shadow:var(--shadow);overflow:hidden}
.modal-header{display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid var(--border);font-size:15px}
.modal-body{padding:20px 24px}
.wa-link-btn{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-radius:var(--radius);background:var(--surface-2);margin-bottom:8px;font-size:13px}
</style>

<script>
const CSRF = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
const BASE  = window.BASE || '';

// ─── Tabs ─────────────────────────────────────────────────────────────────

function switchTab(name, el) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
  ['compose','history'].forEach(t => {
    document.getElementById('tab-' + t).style.display = t === name ? '' : 'none';
  });
}

// ─── Channel pills ────────────────────────────────────────────────────────

document.querySelectorAll('.channel-pill').forEach(pill => {
  pill.addEventListener('click', () => {
    document.querySelectorAll('.channel-pill').forEach(p => p.classList.remove('active'));
    pill.classList.add('active');
    pill.querySelector('input').checked = true;
    const ch = pill.querySelector('input').value;
    document.getElementById('subjectRow').style.display = ch === 'email' ? '' : 'none';
    updateSendBtn();
    updateCount();
  });
});

function currentChannel() {
  return document.querySelector('input[name="channel"]:checked')?.value ?? 'whatsapp';
}

// ─── Recipients ───────────────────────────────────────────────────────────

function setRecipientType(type) {
  const rows = document.querySelectorAll('.tenant-row');
  if (type === 'all') {
    rows.forEach(r => { r.querySelector('.tenant-chk').checked = true; r.classList.add('selected'); });
  } else if (type === 'overdue') {
    rows.forEach(r => {
      const bal = parseFloat(r.dataset.balance) || 0;
      const chk = r.querySelector('.tenant-chk');
      chk.checked = bal > 0;
      r.classList.toggle('selected', bal > 0);
    });
  }
  updateSelCount();
}

function clearAll() {
  document.querySelectorAll('.tenant-chk').forEach(c => { c.checked = false; c.closest('.tenant-row').classList.remove('selected'); });
  updateSelCount();
}

function filterTenants(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.tenant-row').forEach(r => {
    const match = !q || r.dataset.name.includes(q) || r.dataset.room.includes(q);
    r.style.display = match ? '' : 'none';
  });
}

document.addEventListener('change', e => {
  if (e.target.classList.contains('tenant-chk')) {
    e.target.closest('.tenant-row').classList.toggle('selected', e.target.checked);
    updateSelCount();
  }
});

function updateSelCount() {
  const n = document.querySelectorAll('.tenant-chk:checked').length;
  document.getElementById('selCount').textContent = n;
  updateSendBtn();
}

function updateSendBtn() {
  const n  = document.querySelectorAll('.tenant-chk:checked').length;
  const sch = document.getElementById('scheduleAt').value;
  document.getElementById('sendBtnLabel').textContent = sch ? `Schedule for ${n} recipient${n!==1?'s':''}` : `Send to ${n} recipient${n!==1?'s':''}`;
}

document.getElementById('scheduleAt').addEventListener('change', updateSendBtn);

// ─── Message ──────────────────────────────────────────────────────────────

function updateCount() {
  const len = document.getElementById('msgBody').value.length;
  document.getElementById('charCount').textContent = len + ' chars';
  const ch = currentChannel();
  if (ch === 'sms') {
    const segs = Math.ceil(len / 160) || 1;
    document.getElementById('smsSegments').textContent = segs + ' SMS segment' + (segs > 1 ? 's' : '');
  } else {
    document.getElementById('smsSegments').textContent = '';
  }
}

function loadTemplate() {
  const checked = [...document.querySelectorAll('.tenant-chk:checked')];
  const firstName = checked.length === 1 ? checked[0].dataset.name.split(' ')[0] : '[Name]';
  const ch = currentChannel();
  const greeting = ch === 'email' ? `Dear ${firstName},\n\n` : `Dear ${firstName}, `;
  document.getElementById('msgBody').value =
    greeting +
    'This is a friendly reminder that your rent payment is due. Kindly arrange the payment at the earliest to avoid any late fees.\n\n' +
    'Please contact us if you have any questions.\n\nThank you,\nRentOps';
  updateCount();
}

// ─── Attachment ───────────────────────────────────────────────────────────

function onFileChange(inp) {
  const f = inp.files[0];
  if (!f) { clearFile(); return; }
  document.getElementById('fileInfo').style.display = '';
  document.getElementById('fileInfo').textContent = f.name + ' (' + (f.size/1024).toFixed(1) + ' KB)';
  document.getElementById('clearFileBtn').style.display = '';
}
function clearFile() {
  document.getElementById('attachFile').value = '';
  document.getElementById('fileInfo').style.display = 'none';
  document.getElementById('clearFileBtn').style.display = 'none';
}

// ─── Preview ──────────────────────────────────────────────────────────────

function doPreview() {
  const msg  = document.getElementById('msgBody').value.trim();
  const ch   = currentChannel();
  const checked = [...document.querySelectorAll('.tenant-chk:checked')];
  if (!msg)          { showToast('Write a message first.', 'error'); return; }
  if (!checked.length) { showToast('Select at least one recipient.', 'error'); return; }

  const previews = checked.map(c => {
    const personal = msg.replace(/\[Name\]/g, c.dataset.name.split(' ')[0]);
    return `<div style="background:var(--surface-2);border-radius:var(--radius);padding:14px;margin-bottom:12px">
      <div class="d-flex justify-between align-center mb-8">
        <div><div class="fw-600 text-sm">${escHtml(c.dataset.name)}</div>
             <div class="text-hint text-sm">${ch === 'email' ? escHtml(c.dataset.email||'—') : escHtml(c.dataset.phone)}</div></div>
        ${ch === 'whatsapp' ? `<a href="https://wa.me/91${c.dataset.phone.replace(/\D/g,'')}?text=${encodeURIComponent(personal)}" target="_blank" class="btn btn-primary btn-sm">Open WA</a>` : ''}
      </div>
      <pre style="font-family:var(--font);font-size:13px;white-space:pre-wrap;line-height:1.6;color:var(--text-primary)">${escHtml(personal)}</pre>
    </div>`;
  });
  document.getElementById('previewBody').innerHTML = previews.join('');
  document.getElementById('previewModal').style.display = 'flex';
}

// ─── Send ─────────────────────────────────────────────────────────────────

async function doSend() {
  const msg      = document.getElementById('msgBody').value.trim();
  const ch       = currentChannel();
  const checked  = [...document.querySelectorAll('.tenant-chk:checked')];
  const schedAt  = document.getElementById('scheduleAt').value;
  const subject  = document.getElementById('subjectField').value.trim();
  const fileInp  = document.getElementById('attachFile');

  if (!msg)          { showToast('Message is required.', 'error'); return; }
  if (!checked.length) { showToast('Select at least one recipient.', 'error'); return; }

  const fd = new FormData();
  fd.append('_csrf',          CSRF);
  fd.append('channel',        ch);
  fd.append('recipient_type', checked.length === document.querySelectorAll('.tenant-chk').length ? 'all' : 'selected');
  fd.append('message',        msg);
  fd.append('subject',        subject);
  if (schedAt) fd.append('schedule_at', schedAt);
  checked.forEach(c => fd.append('tenant_ids[]', c.value));
  if (fileInp.files[0]) fd.append('attachment', fileInp.files[0]);

  const btn = document.querySelector('[onclick="doSend()"]');
  btn.disabled = true; btn.textContent = 'Sending…';

  try {
    const res  = await fetch(BASE + '/reminders/send', { method: 'POST', body: fd });
    const data = await res.json();

    if (!data.ok) { showToast(data.error || 'Failed', 'error'); return; }

    if (data.scheduled) {
      showToast(`Scheduled for ${schedAt} — ${checked.length} recipient(s).`, 'success');
    } else if (data.waLinks && data.waLinks.length) {
      // WhatsApp without API — show link list
      showWaLinks(data.waLinks);
    } else {
      showToast(`Sent to ${data.sent} · Failed: ${data.failed}`, data.failed ? 'warn' : 'success');
    }

    if (!data.scheduled) setTimeout(() => location.reload(), 2000);
  } catch(e) {
    showToast('Network error: ' + e.message, 'error');
  }
  btn.disabled = false;
  updateSendBtn();
}

// ─── WhatsApp manual links ────────────────────────────────────────────────

function showWaLinks(links) {
  document.getElementById('waBody').innerHTML = `
    <p class="text-sm text-muted mb-12">Twilio/WABA not configured. Click each link to open WhatsApp Web:</p>
    ${links.map(l => `<div class="wa-link-btn"><span class="fw-600 text-sm">${escHtml(l.name)}</span>
      <a href="${l.url}" target="_blank" class="btn btn-primary btn-sm" style="background:#25D366;border-color:#25D366">
        Open WhatsApp
      </a></div>`).join('')}`;
  document.getElementById('waModal').style.display = 'flex';
}

// ─── Resend ───────────────────────────────────────────────────────────────

function openResend(id, msg, subject, attachPath) {
  document.getElementById('resendLogId').value    = id;
  document.getElementById('resendMsg').value      = msg;
  document.getElementById('resendSubject').value  = subject || '';
  document.getElementById('removeAttachment').checked = false;
  document.getElementById('resendModal').style.display = 'flex';
}

async function doResend() {
  const id      = document.getElementById('resendLogId').value;
  const msg     = document.getElementById('resendMsg').value.trim();
  const subject = document.getElementById('resendSubject').value.trim();
  const fileInp = document.getElementById('resendFile');
  const removeA = document.getElementById('removeAttachment').checked;

  if (!msg) { showToast('Message is required.', 'error'); return; }

  const fd = new FormData();
  fd.append('_csrf',    CSRF);
  fd.append('message',  msg);
  fd.append('subject',  subject);
  if (removeA) fd.append('remove_attachment', '1');
  if (fileInp.files[0]) fd.append('attachment', fileInp.files[0]);

  const btn = document.querySelector('[onclick="doResend()"]');
  btn.disabled = true; btn.textContent = 'Sending…';

  try {
    const res  = await fetch(BASE + `/reminders/${id}/resend`, { method: 'POST', body: fd });
    const data = await res.json();
    closeModal('resendModal');
    if (data.waLinks && data.waLinks.length) {
      showWaLinks(data.waLinks);
    } else {
      showToast(data.ok ? `Resent — ${data.sent} sent, ${data.failed} failed` : (data.error || 'Failed'),
                data.ok && !data.failed ? 'success' : 'warn');
    }
    if (data.ok) setTimeout(() => location.reload(), 2000);
  } catch(e) { showToast('Error: ' + e.message, 'error'); }
  btn.disabled = false; btn.textContent = 'Resend Now';
}

// ─── Log detail ───────────────────────────────────────────────────────────

async function viewLog(id) {
  document.getElementById('logBody').innerHTML = '<div class="text-muted text-sm">Loading…</div>';
  document.getElementById('logModal').style.display = 'flex';
  try {
    const res  = await fetch(BASE + `/reminders/${id}/detail`);
    const data = await res.json();
    const l    = data.log;
    const recs = l.recipients || [];
    document.getElementById('logBody').innerHTML = `
      <div class="d-flex gap-20 flex-wrap mb-16">
        <div><div class="text-hint text-sm">Channel</div><div class="fw-600">${ucfirst(l.channel)}</div></div>
        <div><div class="text-hint text-sm">Status</div><div class="fw-600">${ucfirst(l.status)}</div></div>
        <div><div class="text-hint text-sm">Sent</div><div class="fw-600">${l.sent_at ? fmtDate(l.sent_at) : '—'}</div></div>
        <div><div class="text-hint text-sm">Scheduled</div><div class="fw-600">${l.scheduled_at ? fmtDate(l.scheduled_at) : '—'}</div></div>
        <div><div class="text-hint text-sm">Created by</div><div class="fw-600">${escHtml(l.created_by || '—')}</div></div>
      </div>
      ${l.subject ? `<div class="mb-12"><span class="text-hint text-sm">Subject: </span><span class="fw-600">${escHtml(l.subject)}</span></div>` : ''}
      <div class="mb-12" style="background:var(--surface-2);border-radius:var(--radius);padding:14px">
        <pre style="font-size:13px;white-space:pre-wrap;line-height:1.6;color:var(--text-primary);margin:0">${escHtml(l.message)}</pre>
      </div>
      ${l.attachment_path ? `<div class="mb-12 text-sm">📎 Attachment: ${escHtml(l.attachment_path.split('/').pop())}</div>` : ''}
      <div class="fw-600 text-sm mb-8">Recipients (${recs.length})</div>
      <div style="display:flex;flex-direction:column;gap:4px;max-height:200px;overflow-y:auto">
        ${recs.map(r => `<div style="display:flex;gap:12px;font-size:13px;padding:6px 10px;background:var(--surface-2);border-radius:6px">
          <span class="fw-600">${escHtml(r.name)}</span>
          <span class="text-muted">${escHtml(r.phone || '')}</span>
          <span class="text-muted">${escHtml(r.email || '')}</span>
        </div>`).join('')}
      </div>
      ${l.error_log ? `<div class="mt-12 text-sm text-danger" style="background:color-mix(in srgb,var(--danger,#e55) 10%,transparent);border-radius:var(--radius);padding:10px">${escHtml(l.error_log)}</div>` : ''}
    `;
  } catch(e) { document.getElementById('logBody').innerHTML = '<div class="text-danger text-sm">Failed to load.</div>'; }
}

// ─── Utilities ────────────────────────────────────────────────────────────

function closeModal(id) { document.getElementById(id).style.display = 'none'; }
function escHtml(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
function ucfirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function fmtDate(s) { return new Date(s).toLocaleString('en-IN', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}); }

function showToast(msg, type) {
  const t = document.getElementById('toast');
  t.style.display = 'block';
  t.style.borderLeft = `4px solid ${type==='success'?'var(--success,#22c55e)':type==='warn'?'var(--warning,#f59e0b)':'var(--danger,#ef4444)'}`;
  t.innerHTML = escHtml(msg);
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.style.display = 'none', 5000);
}

// Close modals on backdrop click
document.querySelectorAll('.modal-backdrop').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; });
});

// Init
updateSelCount();
</script>
