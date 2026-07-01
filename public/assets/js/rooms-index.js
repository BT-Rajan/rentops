/* RentOps rooms-index.js — grid/list view toggle with localStorage persistence */
(function () {
  'use strict';

  document.querySelectorAll('.view-toggle').forEach(btn => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.view-toggle').forEach(b => {
        b.classList.remove('active');
        b.setAttribute('aria-pressed', 'false');
      });
      this.classList.add('active');
      this.setAttribute('aria-pressed', 'true');
      const v = this.dataset.view;
      const roomGrid = document.getElementById('roomGrid');
      const roomList = document.getElementById('roomList');
      if (roomGrid) roomGrid.style.display = v === 'grid' ? 'grid' : 'none';
      if (roomList) roomList.style.display = v === 'list' ? 'block' : 'none';
      try { localStorage.setItem('roomView', v); } catch (e) { /* storage blocked */ }
    });
  });

  // Restore preference
  try {
    const saved = localStorage.getItem('roomView');
    if (saved === 'list') {
      const listBtn = document.querySelector('[data-view="list"]');
      if (listBtn) listBtn.click();
    }
  } catch (e) { /* storage blocked */ }
})();
