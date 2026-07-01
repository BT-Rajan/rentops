/* RentOps moveout.js — deposit refund calc + pro-rata preview */
(function () {
  'use strict';

  const root = document.getElementById('moveoutRoot');
  const deposit    = parseFloat(root?.dataset.deposit  || '0');
  const rent       = parseFloat(root?.dataset.rent     || '0');
  const moveInDate = root?.dataset.moveInDate || '';

  function updateRefund() {
    const ded = Math.min(parseFloat(document.getElementById('deposit_deduction')?.value) || 0, deposit);
    const deductionDisplay = document.getElementById('deductionDisplay');
    const refundDisplay    = document.getElementById('refundDisplay');
    if (deductionDisplay) deductionDisplay.textContent = '₹' + ded.toLocaleString('en-IN');
    if (refundDisplay)    refundDisplay.textContent    = '₹' + Math.max(0, deposit - ded).toLocaleString('en-IN');
  }

  function updateProRata() {
    const date = document.getElementById('move_out_date')?.value;
    const hint = document.getElementById('proRataHint');
    const warn = document.getElementById('sameMonthWarn');
    if (!hint) return;
    if (!date) { hint.textContent = ''; return; }

    const outDate = new Date(date + 'T00:00:00');
    const inDate  = new Date(moveInDate + 'T00:00:00');
    const day     = outDate.getDate();
    const days    = new Date(outDate.getFullYear(), outDate.getMonth() + 1, 0).getDate();

    const sameMonth = inDate.getFullYear() === outDate.getFullYear() &&
                      inDate.getMonth()     === outDate.getMonth();

    if (sameMonth) {
      const occupied = day - inDate.getDate() + 1;
      const pro = Math.round((rent / days) * Math.max(1, occupied));
      hint.textContent = `Same-month exit: ₹${pro.toLocaleString('en-IN')} (${occupied} day(s) occupied)`;
      if (warn) warn.style.display = 'block';
    } else if (day >= days) {
      hint.textContent = 'Full month — no pro-rata.';
      if (warn) warn.style.display = 'none';
    } else {
      const pro = Math.round((rent / days) * day);
      hint.textContent = `Final invoice: ₹${pro.toLocaleString('en-IN')} (${day} of ${days} days)`;
      if (warn) warn.style.display = 'none';
    }
  }

  document.getElementById('deposit_deduction')?.addEventListener('input', updateRefund);
  document.getElementById('move_out_date')?.addEventListener('change', updateProRata);
  updateRefund();
  updateProRata();
})();
