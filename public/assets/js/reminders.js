/* RentOps reminders.js — compose, schedule, resend, history.
 * SECURITY: this file replaces 21 inline onclick/onchange handlers and an
 * inline <script> body that previously lived in views/reminders/index.php.
 * All wiring goes through addEventListener + event delegation on
 * data-action attributes; nothing here is reachable via the page's CSP
 * without this external file being loaded from /assets/js/.
 */
(function () {
  'use strict';

  const root = document.getElementById('remindersRoot');
  if (!root) return;

  const CSRF = root.dataset.csrf || '';
  const BASE = window.BASE || '';
  const I18N = {
    resendBtn: root.dataset.i18nResendBtn || 'Resend Now',
    waNoApi:   root.dataset.i18nWaNoApi   || 'Twilio/WABA not configured. Click each link to open WhatsApp Web:',
    openWa:    root.dataset.i18nOpenWa    || 'Open WhatsApp',
    channel:   root.dataset.i18nChannel   || 'Channel',
  };

  // ─── Tabs ─────────────────────────────────────────────────────────────────

  function switchTab(name, el) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    ['compose', 'history'].forEach(t => {
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

  function clearAllRecipients() {
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

  document.getElementById('tenantSearch')?.addEventListener('input', e => filterTenants(e.target.value));

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
    const n   = document.querySelectorAll('.tenant-chk:checked').length;
    const sch = document.getElementById('scheduleAt').value;
    document.getElementById('sendBtnLabel').textContent = sch
      ? `Schedule for ${n} recipient${n !== 1 ? 's' : ''}`
      : `Send to ${n} recipient${n !== 1 ? 's' : ''}`;
  }

  document.getElementById('scheduleAt')?.addEventListener('change', updateSendBtn);

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

  document.getElementById('msgBody')?.addEventListener('input', updateCount);

  function loadTemplate() {
    const checked   = [...document.querySelectorAll('.tenant-chk:checked')];
    const firstName = checked.length === 1 ? checked[0].dataset.name.split(' ')[0] : '[Name]';
    const ch        = currentChannel();
    const greeting  = ch === 'email' ? `Dear ${firstName},\n\n` : `Dear ${firstName}, `;
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
    document.getElementById('fileInfo').textContent = f.name + ' (' + (f.size / 1024).toFixed(1) + ' KB)';
    document.getElementById('clearFileBtn').style.display = '';
  }

  function clearFile() {
    document.getElementById('attachFile').value = '';
    document.getElementById('fileInfo').style.display = 'none';
    document.getElementById('clearFileBtn').style.display = 'none';
  }

  document.getElementById('attachFile')?.addEventListener('change', e => onFileChange(e.target));

  // ─── Preview ──────────────────────────────────────────────────────────────

  function doPreview() {
    const msg     = document.getElementById('msgBody').value.trim();
    const ch      = currentChannel();
    const checked = [...document.querySelectorAll('.tenant-chk:checked')];
    if (!msg)            { showToast('Write a message first.', 'error'); return; }
    if (!checked.length) { showToast('Select at least one recipient.', 'error'); return; }

    const previews = checked.map(c => {
      const personal = msg.replace(/\[Name\]/g, c.dataset.name.split(' ')[0]);
      return `<div style="background:var(--surface-2);border-radius:var(--radius);padding:14px;margin-bottom:12px">
        <div class="d-flex justify-between align-center mb-8">
          <div><div class="fw-600 text-sm">${escHtml(c.dataset.name)}</div>
               <div class="text-hint text-sm">${ch === 'email' ? escHtml(c.dataset.email || '—') : escHtml(c.dataset.phone)}</div></div>
          ${ch === 'whatsapp' ? `<a href="https://wa.me/91${c.dataset.phone.replace(/\D/g, '')}?text=${encodeURIComponent(personal)}" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Open WA</a>` : ''}
        </div>
        <pre style="font-family:var(--font);font-size:13px;white-space:pre-wrap;line-height:1.6;color:var(--text-primary)">${escHtml(personal)}</pre>
      </div>`;
    });
    document.getElementById('previewBody').innerHTML = previews.join('');
    document.getElementById('previewModal').style.display = 'flex';
  }

  // ─── Send ─────────────────────────────────────────────────────────────────

  async function doSend() {
    const msg     = document.getElementById('msgBody').value.trim();
    const ch      = currentChannel();
    const checked = [...document.querySelectorAll('.tenant-chk:checked')];
    const schedAt = document.getElementById('scheduleAt').value;
    const subject = document.getElementById('subjectField').value.trim();
    const fileInp = document.getElementById('attachFile');

    if (!msg)            { showToast('Message is required.', 'error'); return; }
    if (!checked.length) { showToast('Select at least one recipient.', 'error'); return; }

    const fd = new FormData();
    fd.append('_csrf', CSRF);
    fd.append('channel', ch);
    fd.append('recipient_type', checked.length === document.querySelectorAll('.tenant-chk').length ? 'all' : 'selected');
    fd.append('message', msg);
    fd.append('subject', subject);
    if (schedAt) fd.append('schedule_at', schedAt);
    checked.forEach(c => fd.append('tenant_ids[]', c.value));
    if (fileInp.files[0]) fd.append('attachment', fileInp.files[0]);

    const btn = document.getElementById('sendBtn');
    btn.disabled = true;
    const label = document.getElementById('sendBtnLabel');
    const prevLabel = label.textContent;
    label.textContent = 'Sending…';

    try {
      const res  = await fetch(BASE + '/reminders/send', { method: 'POST', body: fd });
      const data = await res.json();

      if (!data.ok) { showToast(data.error || 'Failed', 'error'); }
      else if (data.scheduled) {
        showToast(`Scheduled for ${schedAt} — ${checked.length} recipient(s).`, 'success');
      } else if (data.waLinks && data.waLinks.length) {
        showWaLinks(data.waLinks);
      } else {
        showToast(`Sent to ${data.sent} · Failed: ${data.failed}`, data.failed ? 'warn' : 'success');
      }

      if (data.ok && !data.scheduled) setTimeout(() => location.reload(), 2000);
    } catch (e) {
      showToast('Network error: ' + e.message, 'error');
    }
    btn.disabled = false;
    updateSendBtn();
  }

  // ─── WhatsApp manual links ────────────────────────────────────────────────

  function showWaLinks(links) {
    document.getElementById('waBody').innerHTML = `
      <p class="text-sm text-muted mb-12">${escHtml(I18N.waNoApi)}</p>
      ${links.map(l => `<div class="wa-link-btn"><span class="fw-600 text-sm">${escHtml(l.name)}</span>
        <a href="${l.url}" target="_blank" rel="noopener" class="btn btn-primary btn-sm" style="background:#25D366;border-color:#25D366">
          ${escHtml(I18N.openWa)}
        </a></div>`).join('')}`;
    document.getElementById('waModal').style.display = 'flex';
  }

  // ─── Resend ───────────────────────────────────────────────────────────────

  function openResend(id, msg, subject) {
    document.getElementById('resendLogId').value         = id;
    document.getElementById('resendMsg').value            = msg;
    document.getElementById('resendSubject').value        = subject || '';
    document.getElementById('removeAttachment').checked   = false;
    document.getElementById('resendModal').style.display  = 'flex';
  }

  async function doResend() {
    const id      = document.getElementById('resendLogId').value;
    const msg     = document.getElementById('resendMsg').value.trim();
    const subject = document.getElementById('resendSubject').value.trim();
    const fileInp = document.getElementById('resendFile');
    const removeA = document.getElementById('removeAttachment').checked;

    if (!msg) { showToast('Message is required.', 'error'); return; }

    const fd = new FormData();
    fd.append('_csrf', CSRF);
    fd.append('message', msg);
    fd.append('subject', subject);
    if (removeA) fd.append('remove_attachment', '1');
    if (fileInp.files[0]) fd.append('attachment', fileInp.files[0]);

    const btn = document.getElementById('resendBtn');
    btn.disabled = true;
    btn.textContent = 'Sending…';

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
    } catch (e) {
      showToast('Error: ' + e.message, 'error');
    }
    btn.disabled = false;
    btn.textContent = I18N.resendBtn;
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
          <div><div class="text-hint text-sm">${escHtml(I18N.channel)}</div><div class="fw-600">${ucfirst(l.channel)}</div></div>
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
    } catch (e) {
      document.getElementById('logBody').innerHTML = '<div class="text-danger text-sm">Failed to load.</div>';
    }
  }

  // ─── Utilities ────────────────────────────────────────────────────────────

  function closeModal(id) { document.getElementById(id).style.display = 'none'; }
  function escHtml(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
  function ucfirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
  function fmtDate(s) { return new Date(s).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }

  function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.style.display = 'block';
    t.style.borderLeft = `4px solid ${type === 'success' ? 'var(--success,#22c55e)' : type === 'warn' ? 'var(--warning,#f59e0b)' : 'var(--danger,#ef4444)'}`;
    t.innerHTML = escHtml(msg);
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.style.display = 'none', 5000);
  }

  // Close modals on backdrop click
  document.querySelectorAll('.modal-backdrop').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; });
  });

  // ─── Single delegated click handler for every data-action button ──────────

  root.addEventListener('click', e => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;

    switch (btn.dataset.action) {
      case 'switch-tab':
        switchTab(btn.dataset.tab, btn);
        break;
      case 'recipient-type':
        setRecipientType(btn.dataset.type);
        break;
      case 'clear-recipients':
        clearAllRecipients();
        break;
      case 'load-template':
        loadTemplate();
        break;
      case 'clear-file':
        clearFile();
        break;
      case 'send':
        doSend();
        break;
      case 'preview':
        doPreview();
        break;
      case 'view-log':
        viewLog(btn.dataset.id);
        break;
      case 'open-resend':
        openResend(btn.dataset.id, btn.dataset.message, btn.dataset.subject);
        break;
      case 'resend':
        doResend();
        break;
      case 'close-modal':
        closeModal(btn.dataset.modal);
        break;
    }
  });

  // Init
  updateSelCount();
})();
