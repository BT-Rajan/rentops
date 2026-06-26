/* RentOps app.js — shared UI behaviours */
(function () {
  'use strict';

  // ─── Sidebar mobile toggle ──────────────────────────────────────────────────
  const toggle  = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');

  function openSidebar() {
    sidebar?.classList.add('open');
    if (overlay) overlay.style.display = 'block';
    document.body.style.overflow = 'hidden';
    toggle?.setAttribute('aria-expanded', 'true');
  }

  function closeSidebar() {
    sidebar?.classList.remove('open');
    if (overlay) overlay.style.display = 'none';
    document.body.style.overflow = '';
    toggle?.setAttribute('aria-expanded', 'false');
  }

  toggle?.addEventListener('click', () =>
    sidebar?.classList.contains('open') ? closeSidebar() : openSidebar()
  );
  overlay?.addEventListener('click', closeSidebar);

  // Close on nav click (mobile) and on Escape key
  sidebar?.querySelectorAll('.nav-item').forEach(el =>
    el.addEventListener('click', () => window.innerWidth < 769 && closeSidebar())
  );
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && sidebar?.classList.contains('open')) closeSidebar();
  });

  // ─── Active nav highlight ───────────────────────────────────────────────────
  const path = location.pathname;
  document.querySelectorAll('.nav-item').forEach(el => {
    const href    = el.getAttribute('href');
    if (!href) return;
    const isHome  = href === '/' && (path === '/' || path === '/dashboard');
    const isMatch = href !== '/' && path.startsWith(href);
    el.classList.toggle('active', isHome || isMatch);
  });

  // ─── Flash auto-dismiss (4 s) ───────────────────────────────────────────────
  document.querySelectorAll('.flash').forEach(el => {
    const dismiss = () => {
      el.style.transition = 'opacity .35s ease';
      el.style.opacity    = '0';
      setTimeout(() => el.remove(), 360);
    };
    setTimeout(dismiss, 4000);
    el.addEventListener('click', dismiss); // tap to dismiss early
  });

  // ─── Client-side table sort (data-sort on <th>) ─────────────────────────────
  document.querySelectorAll('th[data-sort]').forEach(th => {
    th.style.cursor = 'pointer';
    th.title        = 'Click to sort';

    th.addEventListener('click', function () {
      const table = this.closest('table');
      const tbody = table.querySelector('tbody');
      const col   = [...this.parentElement.children].indexOf(this);
      const asc   = this.dataset.dir !== 'asc';
      this.dataset.dir = asc ? 'asc' : 'desc';

      // Reset other headers
      table.querySelectorAll('th[data-sort]').forEach(h => {
        if (h !== this) delete h.dataset.dir;
      });

      const rows = [...tbody.querySelectorAll('tr')];
      rows.sort((a, b) => {
        const av = a.children[col]?.textContent.trim() ?? '';
        const bv = b.children[col]?.textContent.trim() ?? '';
        const an = parseFloat(av.replace(/[₹,\s]/g, ''));
        const bn = parseFloat(bv.replace(/[₹,\s]/g, ''));
        const cmp = isNaN(an) || isNaN(bn)
          ? av.localeCompare(bv, 'en-IN')
          : an - bn;
        return asc ? cmp : -cmp;
      });

      rows.forEach(r => tbody.appendChild(r));
    });
  });

  // ─── Confirm destructive actions ────────────────────────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(el =>
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    })
  );

  // ─── AJAX form submission feedback ─────────────────────────────────────────
  // Prevents double-submit on slow connections
  document.querySelectorAll('form:not([data-no-lock])').forEach(form => {
    form.addEventListener('submit', function () {
      const btn = this.querySelector('button[type="submit"]');
      if (!btn || btn.dataset.locked) return;
      btn.dataset.locked    = '1';
      btn.dataset.origText  = btn.textContent;
      btn.disabled          = true;
      btn.style.opacity     = '.7';
      // Re-enable after 8s in case navigation fails
      setTimeout(() => {
        btn.disabled = false;
        btn.style.opacity = '';
        delete btn.dataset.locked;
      }, 8000);
    });
  });

  // ─── Touch-friendly: increase small btn tap area on mobile ─────────────────
  if ('ontouchstart' in window) {
    document.querySelectorAll('.btn-sm').forEach(btn => {
      btn.style.minHeight = '36px';
    });
  }

  // ─── Number formatting helper (exposed globally for inline scripts) ─────────
  window.fmtINR = v =>
    '₹' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 });

  // ─── Escape HTML helper ─────────────────────────────────────────────────────
  window.escHtml = s => {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
  };

})();
