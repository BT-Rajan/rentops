/* RentOps invoice-show.js — Razorpay link + copy.
 * SECURITY: replaces 2 inline onclick handlers (getRazorpayLink, copyLink)
 * and a <script> body with PHP-interpolated IDs/URLs. The previous version
 * also dynamically injected onclick="navigator.clipboard..." into innerHTML,
 * which is blocked by CSP regardless of when it's injected — fixed here by
 * using event delegation on the container instead.
 */
(function () {
  'use strict';

  const CSRF   = document.querySelector('[name="_csrf"]')?.value || '';
  const INV_ID = document.getElementById('invoiceId')?.value || '';
  const BASE   = window.BASE || '';

  // Container for the razorpay action area — used for event delegation so
  // dynamically-rendered "Copy Link" buttons work without inline onclick.
  const actionsContainer = document.querySelector('.card');

  async function getRazorpayLink() {
    const btn = document.getElementById('rzBtn');
    if (!btn) return;
    btn.disabled = true;
    const origInner = btn.innerHTML;
    btn.textContent = 'Generating link…';

    try {
      const res  = await fetch(`${BASE}/invoices/${INV_ID}/razorpay-link`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
        body: '_csrf=' + encodeURIComponent(CSRF)
      });
      const data = await res.json().catch(() => null);

      if (!data?.ok) {
        alert('Error: ' + (data?.error || 'Failed to create payment link'));
        btn.disabled = false;
        btn.innerHTML = origInner;
        return;
      }

      const reuseNote = data.reused
        ? '<div class="text-hint text-sm mt-4">Reusing existing link (balance unchanged).</div>'
        : '';

      // Replace button area — use data-action on the copy button, NOT onclick,
      // so CSP (which blocks inline event handlers) doesn't break it.
      btn.parentElement.innerHTML = `
        <a href="${escHtml(data.url)}" target="_blank" rel="noopener"
           class="btn" style="width:100%;justify-content:center;background:#072654;color:#fff;border-color:#072654">
          Open Payment Link
        </a>
        <button type="button" class="btn btn-ghost btn-sm"
                style="width:100%;margin-top:6px;justify-content:center"
                data-action="copy-link"
                data-url="${escAttr(data.url)}">
          Copy Link
        </button>
        <div class="text-hint text-sm mt-6" style="word-break:break-all">${escHtml(data.url)}</div>
        ${reuseNote}`;
    } catch (e) {
      alert('Network error: ' + e.message);
      btn.disabled = false;
      btn.innerHTML = origInner;
    }
  }

  function copyLink(url) {
    if (!url) return;
    navigator.clipboard.writeText(url)
      .then(() => alert('Payment link copied!'))
      .catch(() => prompt('Copy this link:', url));
  }

  // ─── Delegated event handling — covers both server-rendered and dynamically
  //     injected buttons without ever writing an inline event attribute ────────
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;

    switch (btn.dataset.action) {
      case 'get-razorpay-link':
        getRazorpayLink();
        break;
      case 'copy-link':
        copyLink(btn.dataset.url);
        break;
    }
  });

  function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function escAttr(s) {
    return (s || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
})();
