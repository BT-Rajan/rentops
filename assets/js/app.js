/* RentOps app.js — shared UI behaviours */
(function () {
  'use strict';

  // ─── Sidebar mobile toggle ──────────────────────────────────────────────────
  const toggle  = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');

  function openSidebar()  {
    sidebar?.classList.add('open');
    overlay.style.display = 'block';
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar?.classList.remove('open');
    overlay.style.display = 'none';
    document.body.style.overflow = '';
  }

  toggle?.addEventListener('click', () =>
    sidebar?.classList.contains('open') ? closeSidebar() : openSidebar()
  );
  overlay?.addEventListener('click', closeSidebar);

  // Close sidebar on nav click (mobile)
  sidebar?.querySelectorAll('.nav-item').forEach(el =>
    el.addEventListener('click', () => window.innerWidth < 769 && closeSidebar())
  );

  // ─── Flash auto-dismiss ─────────────────────────────────────────────────────
  document.querySelectorAll('.flash').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .4s';
      el.style.opacity    = '0';
      setTimeout(() => el.remove(), 400);
    }, 4000);
  });

  // ─── Active nav highlight (dynamic) ────────────────────────────────────────
  const path = location.pathname;
  document.querySelectorAll('.nav-item').forEach(el => {
    const href = el.getAttribute('href');
    if (!href) return;
    const isHome = href === '/' && (path === '/' || path === '/dashboard');
    const isMatch = href !== '/' && path.startsWith(href);
    if (isHome || isMatch) el.classList.add('active');
    else el.classList.remove('active');
  });

  // ─── Table sort ─────────────────────────────────────────────────────────────
  document.querySelectorAll('th[data-sort]').forEach(th => {
    th.style.cursor = 'pointer';
    th.addEventListener('click', function () {
      const table = this.closest('table');
      const tbody = table.querySelector('tbody');
      const col   = [...this.parentElement.children].indexOf(this);
      const dir   = this.dataset.dir === 'asc' ? -1 : 1;
      this.dataset.dir = dir === 1 ? 'asc' : 'desc';

      const rows = [...tbody.querySelectorAll('tr')];
      rows.sort((a, b) => {
        const av = a.children[col]?.textContent.trim() ?? '';
        const bv = b.children[col]?.textContent.trim() ?? '';
        const an = parseFloat(av.replace(/[₹,]/g, ''));
        const bn = parseFloat(bv.replace(/[₹,]/g, ''));
        return isNaN(an) || isNaN(bn)
          ? av.localeCompare(bv) * dir
          : (an - bn) * dir;
      });

      rows.forEach(r => tbody.appendChild(r));
    });
  });

  // ─── Confirm destructive forms ──────────────────────────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(el =>
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    })
  );
})();
