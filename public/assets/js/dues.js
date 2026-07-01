/* RentOps dues.js */
(function () {
  'use strict';

  const root = document.getElementById('duesRoot');
  const CSRF = root?.dataset.csrf || window.CSRF || '';
  const BASE = window.BASE || '';

  // ─── Bulk select ──────────────────────────────────────────────────────────
  const selectAll = document.getElementById('selectAll');
  const bulkBar   = document.getElementById('bulkBar');
  const bulkCount = document.getElementById('bulkCount');
  const selLabel  = root?.dataset.i18nSelected || 'selected';

  function getChecks() { return document.querySelectorAll('.row-select'); }

  function updateBulk() {
    const sel = [...getChecks()].filter(c => c.checked);
    if (sel.length > 0) {
      bulkCount.textContent = sel.length + ' ' + selLabel;
      bulkBar.style.display = 'flex';
      const remind = document.getElementById('bulkReminder');
      if (remind) remind.href = BASE + '/reminders?ids=' + sel.map(c => c.value).join(',');
    } else {
      bulkBar.style.display = 'none';
    }
  }

  selectAll?.addEventListener('change', function () {
    getChecks().forEach(c => c.checked = this.checked);
    updateBulk();
  });

  document.addEventListener('change', e => {
    if (e.target.classList.contains('row-select')) updateBulk();
  });

  // ─── Generate invoices ────────────────────────────────────────────────────
  const genBtn = document.getElementById('generateBtn');
  if (genBtn) {
    const genLabel   = root?.dataset.i18nGenerating  || 'Generating…';
    const doneLabel  = root?.dataset.i18nGenInvoices || 'Generate invoices';
    const alreadyMsg = root?.dataset.i18nAlreadyExist || 'Invoices already exist — nothing new to generate.';

    genBtn.addEventListener('click', async function () {
      const month = document.getElementById('monthPicker').value;
      this.disabled = true;
      this.textContent = genLabel;
      try {
        const r = await fetch(BASE + '/api/invoices/generate', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
          body: `_csrf=${encodeURIComponent(CSRF)}&month=${encodeURIComponent(month)}`
        });
        const d = await r.json().catch(() => null);
        if (!r.ok || !d?.ok) {
          alert('Error: ' + (d?.error || 'Server error ' + r.status));
          return;
        }
        if (d.created > 0) { alert(`Generated ${d.created} invoice(s) for ${month}.`); location.reload(); }
        else alert(alreadyMsg);
      } catch (e) { alert('Request failed: ' + e.message); }
      this.disabled = false;
      this.textContent = doneLabel;
    });
  }
})();
