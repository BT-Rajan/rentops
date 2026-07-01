/* RentOps tenant-show.js — rent adjustment + ID proof upload.
 * SECURITY: replaces 4 inline handlers (onclick on rent toggle/save,
 * onclick+ondragover+ondragleave+ondrop on the upload zone, onchange on
 * the file input) and an inline <script> body with PHP-interpolated IDs.
 */
(function () {
  'use strict';

  const data = document.getElementById('tenantShowData');
  if (!data) return;

  const TENANT_ID  = data.dataset.tenantId;
  const TENANCY_ID = data.dataset.tenancyId;
  const CSRF       = data.dataset.csrf;
  const BASE       = window.BASE || '';

  // ─── Rent adjustment ───────────────────────────────────────────────────

  document.querySelector('[data-action="toggle-rent-panel"]')?.addEventListener('click', function () {
    document.getElementById('rentChangePanel').style.display = 'block';
    this.style.display = 'none';
  });

  async function submitRentChange() {
    const newRent = parseFloat(document.getElementById('newRentInput').value);
    const effDate = document.getElementById('rentEffective').value;
    const note    = document.getElementById('rentNote').value;
    if (!newRent || newRent <= 0) { alert('Enter a valid rent amount.'); return; }

    const r = await fetch(`${BASE}/tenancies/${TENANCY_ID}/rent-change`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `_csrf=${encodeURIComponent(CSRF)}&new_rent=${newRent}&effective_from=${encodeURIComponent(effDate)}&note=${encodeURIComponent(note)}`
    });
    const d = await r.json();
    if (d.success) { alert(`Rent updated to ₹${d.new_rent.toLocaleString('en-IN')}`); location.reload(); }
    else alert('Error: ' + (d.error || 'Failed'));
  }

  document.querySelector('[data-action="submit-rent-change"]')?.addEventListener('click', submitRentChange);

  // ─── ID proof upload ───────────────────────────────────────────────────

  async function uploadProof(file) {
    if (!file) return;
    document.getElementById('proofProgress').style.display = 'block';
    document.getElementById('proofLabel').textContent = file.name;
    document.getElementById('proofBar').style.width = '30%';
    const fd = new FormData();
    fd.append('id_proof', file);
    fd.append('_csrf', CSRF);
    try {
      const r = await fetch(`${BASE}/tenants/${TENANT_ID}/upload-proof`, { method: 'POST', body: fd });
      const d = await r.json();
      document.getElementById('proofBar').style.width = '100%';
      if (d.success) {
        document.getElementById('proofStatus').textContent = '✓ Uploaded';
        setTimeout(() => location.reload(), 800);
      } else {
        document.getElementById('proofStatus').textContent = '✕ ' + (d.error || 'Upload failed');
      }
    } catch (e) {
      document.getElementById('proofStatus').textContent = '✕ Network error';
    }
  }

  const dropZone  = document.getElementById('proofDropZone');
  const proofInput = document.getElementById('proofInput');

  dropZone?.addEventListener('click', () => proofInput.click());

  dropZone?.addEventListener('dragover', e => {
    e.preventDefault();
    dropZone.style.borderColor = 'var(--c-primary)';
  });

  dropZone?.addEventListener('dragleave', () => {
    dropZone.style.borderColor = 'var(--border-md)';
  });

  dropZone?.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.style.borderColor = 'var(--border-md)';
    const file = e.dataTransfer.files[0];
    if (file) uploadProof(file);
  });

  proofInput?.addEventListener('change', () => uploadProof(proofInput.files[0]));

  document.getElementById('deleteProofBtn')?.addEventListener('click', async function () {
    if (!confirm('Remove this ID proof file?')) return;
    const r = await fetch(`${BASE}/tenants/${TENANT_ID}/delete-proof`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `_csrf=${encodeURIComponent(CSRF)}`
    });
    const d = await r.json();
    if (d.success) location.reload();
    else alert(d.error || 'Failed to delete.');
  });
})();
