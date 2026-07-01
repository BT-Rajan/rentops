/* RentOps invoices-index.js — search/filter/export for the invoice listing.
 * SECURITY: replaces 5 inline oninput/onclick/onchange handlers and an
 * inline <script> body that interpolated $_GET values into JS.
 */
(function () {
  'use strict';

  const root = document.getElementById('invoicesRoot');
  if (!root) return;

  let selectedTenantId = root.dataset.initialTenantId || '';

  const searchTenant  = document.getElementById('searchTenant');
  const searchRoom    = document.getElementById('searchRoom');
  const searchMonth   = document.getElementById('searchMonth');
  const searchStatus  = document.getElementById('searchStatus');
  const exactBadge    = document.getElementById('exactMatchBadge');
  const visibleCount  = document.getElementById('visibleCount');
  const xlsxBtn       = document.getElementById('xlsxBtn');

  // FIX B-flow-10: when the typed value exactly matches a datalist option
  // ("Name — Room X"), lock onto that tenant's ID for an unambiguous filter
  // instead of falling back to a substring match on name alone.
  function onTenantInput() {
    const val   = searchTenant.value;
    const opts  = document.querySelectorAll('#tenantDatalist option');
    let matched = null;

    opts.forEach(opt => { if (opt.value === val) matched = opt; });

    if (matched) {
      selectedTenantId = matched.dataset.tenantId;
      searchTenant.value = matched.dataset.name; // collapse to just the name for display
      exactBadge.style.display = '';
    } else {
      selectedTenantId = '';
      exactBadge.style.display = 'none';
    }

    applyFilters();
  }

  function applyFilters() {
    const tenant = searchTenant.value.toLowerCase().trim();
    const room   = searchRoom.value.toLowerCase().trim();
    const month  = searchMonth.value;
    const status = searchStatus.value;
    let visible  = 0;

    document.querySelectorAll('#invoiceTable tbody tr').forEach(row => {
      const tenantMatch = selectedTenantId
        ? row.dataset.tenantId === selectedTenantId
        : (!tenant || row.dataset.tenant.includes(tenant));

      const show = tenantMatch
                && (!room   || row.dataset.room.includes(room))
                && (!month  || row.dataset.month === month)
                && (!status || row.dataset.status === status);
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    visibleCount.textContent = visible;

    // Update XLSX export link to reflect current filters
    const params = new URLSearchParams(window.location.search);
    if (selectedTenantId) { params.set('tenant_id', selectedTenantId); params.delete('tenant'); }
    else if (tenant)      { params.set('tenant', tenant); params.delete('tenant_id'); }
    else                  { params.delete('tenant'); params.delete('tenant_id'); }
    if (room)   params.set('room', room);     else params.delete('room');
    if (month)  params.set('month', month);   else params.delete('month');
    if (status) params.set('status', status); else params.delete('status');
    params.set('download', 'xlsx');
    xlsxBtn.href = '?' + params.toString();
  }

  function clearFilters() {
    searchTenant.value = '';
    searchRoom.value   = '';
    searchMonth.value  = '';
    searchStatus.value = '';
    selectedTenantId    = '';
    exactBadge.style.display = 'none';
    applyFilters();
  }

  searchTenant.addEventListener('input', onTenantInput);
  searchRoom.addEventListener('input', applyFilters);
  searchMonth.addEventListener('input', applyFilters);
  searchStatus.addEventListener('change', applyFilters);

  root.addEventListener('click', e => {
    const btn = e.target.closest('[data-action="clear-filters"]');
    if (btn) clearFilters();
  });

  // If page loaded with ?tenant_id= already set (e.g. from tenant page link),
  // reflect the exact-match badge state immediately.
  if (selectedTenantId) {
    exactBadge.style.display = '';
  }

  // Apply any URL params on load
  applyFilters();
})();
