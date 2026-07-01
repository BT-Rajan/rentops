/* RentOps movein.js — pro-rata preview + room rent prefill */
(function () {
  'use strict';

  function setBaseRent(sel) {
    const opt  = sel.options[sel.selectedIndex];
    const rent = opt.dataset.rent || '';
    const ar   = document.getElementById('agreed_rent');
    if (ar && !ar.value && rent) ar.value = rent;
    updateProRata();
  }

  function updateProRata() {
    const rent = parseFloat(document.getElementById('agreed_rent')?.value) || 0;
    const date = document.getElementById('move_in_date')?.value;
    const hint = document.getElementById('proRataHint');
    if (!hint) return;
    if (!rent || !date) { hint.textContent = ''; return; }

    const d    = new Date(date + 'T00:00:00');
    const day  = d.getDate();
    const days = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();

    if (day === 1) {
      hint.textContent = 'Full month — no pro-rata.';
    } else {
      const proRata = Math.round((rent / days) * (days - day + 1));
      hint.textContent = `First invoice: ₹${proRata.toLocaleString('en-IN')} (${days - day + 1} of ${days} days)`;
    }
  }

  document.getElementById('room_id')?.addEventListener('change', function () { setBaseRent(this); });
  document.getElementById('agreed_rent')?.addEventListener('input', updateProRata);
  document.getElementById('move_in_date')?.addEventListener('change', updateProRata);
  updateProRata();
})();
