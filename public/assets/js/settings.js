/* RentOps settings.js — password strength + match + toggle */
(function () {
  'use strict';

  const root = document.getElementById('settingsRoot');

  const labels = {
    veryWeak:  root?.dataset.i18nVeryWeak  || 'Very weak',
    weak:      root?.dataset.i18nWeak      || 'Weak',
    fair:      root?.dataset.i18nFair      || 'Fair',
    strong:    root?.dataset.i18nStrong    || 'Strong',
    veryStrong:root?.dataset.i18nVeryStrong|| 'Very strong',
  };

  document.querySelectorAll('.pw-toggle').forEach(btn => {
    btn.addEventListener('click', function () {
      const input = document.getElementById(this.dataset.target);
      if (input) input.type = input.type === 'password' ? 'text' : 'password';
    });
  });

  function checkStrength(pw) {
    const bar   = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');
    if (!bar) return;
    let score = 0;
    if (pw.length >= 8)           score++;
    if (pw.length >= 12)          score++;
    if (/[A-Z]/.test(pw))         score++;
    if (/[0-9]/.test(pw))         score++;
    if (/[^A-Za-z0-9]/.test(pw))  score++;

    const levels = [
      { pct: '0%',   color: 'var(--c-danger)',  text: '' },
      { pct: '20%',  color: '#E24B4A',           text: labels.veryWeak },
      { pct: '40%',  color: '#EF9F27',           text: labels.weak },
      { pct: '60%',  color: '#D4BF00',           text: labels.fair },
      { pct: '80%',  color: 'var(--c-primary)',  text: labels.strong },
      { pct: '100%', color: '#0F6E56',           text: labels.veryStrong },
    ];
    const l = levels[score] || levels[0];
    bar.style.width      = l.pct;
    bar.style.background = l.color;
    label.textContent    = l.text;
    checkSubmit();
  }

  function checkMatch() {
    const nw   = document.getElementById('new_password')?.value     || '';
    const cn   = document.getElementById('confirm_password')?.value || '';
    const hint = document.getElementById('matchHint');
    if (!hint) return;
    if (!cn) { hint.textContent = ''; return; }
    if (nw === cn) {
      hint.textContent = '✓ Passwords match';
      hint.style.color = 'var(--c-success)';
    } else {
      hint.textContent = 'Passwords do not match';
      hint.style.color = 'var(--c-danger)';
    }
    checkSubmit();
  }

  function checkSubmit() {
    const cur = document.getElementById('current_password')?.value  || '';
    const nw  = document.getElementById('new_password')?.value      || '';
    const cn  = document.getElementById('confirm_password')?.value  || '';
    const btn = document.getElementById('pwSubmit');
    if (btn) btn.disabled = !(cur.length > 0 && nw.length >= 8 && nw === cn);
  }

  document.getElementById('new_password')?.addEventListener('input', e => checkStrength(e.target.value));
  document.getElementById('confirm_password')?.addEventListener('input', checkMatch);
  document.getElementById('current_password')?.addEventListener('input', checkSubmit);
})();
