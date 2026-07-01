/* RentOps payment-create.js — prefill amount from invoice balance */
(function () {
  'use strict';

  document.getElementById('invoice_id')?.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const bal = opt.dataset.balance || '';
    if (bal) {
      const amtInput = document.getElementById('amount');
      const hint     = document.getElementById('amountHint');
      if (amtInput) amtInput.value = bal;
      if (hint) hint.textContent = `Balance due: ₹${parseFloat(bal).toLocaleString('en-IN')}`;
    }
  });
})();
