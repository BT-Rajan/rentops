/* RentOps import.js — CSV file picker + drag-and-drop */
(function () {
  'use strict';

  const dropZone = document.getElementById('csvDropZoneInner') || document.getElementById('csvDropZone');
  const csvFile  = document.getElementById('csvFile');

  function showFile(input) {
    const file = input?.files[0];
    if (!file) return;
    const label = document.getElementById('fileLabel');
    const uploadBtn = document.getElementById('uploadBtn');
    if (label) label.textContent = file.name + ' — ' + (file.size / 1024).toFixed(1) + ' KB';
    if (uploadBtn) uploadBtn.disabled = false;
    if (dropZone) dropZone.style.borderColor = 'var(--c-primary)';
  }

  csvFile?.addEventListener('change', () => showFile(csvFile));

  dropZone?.addEventListener('click', () => csvFile?.click());

  dropZone?.addEventListener('dragover', e => {
    e.preventDefault();
    dropZone.style.borderColor = 'var(--c-primary)';
    dropZone.style.background  = 'var(--c-primary-lt, color-mix(in srgb,var(--primary) 6%,transparent))';
  });

  dropZone?.addEventListener('dragleave', () => {
    dropZone.style.borderColor = 'var(--border-md)';
    dropZone.style.background  = '';
  });

  dropZone?.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.style.borderColor = 'var(--border-md)';
    dropZone.style.background  = '';
    const file = e.dataTransfer.files[0];
    if (file && file.name.endsWith('.csv') && csvFile) {
      const dt = new DataTransfer();
      dt.items.add(file);
      csvFile.files = dt.files;
      showFile(csvFile);
    } else {
      alert('Please drop a .csv file.');
    }
  });
})();
