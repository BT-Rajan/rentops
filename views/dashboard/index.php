<h2 class="sr-only">Dashboard — collection overview</h2>

<!-- Month picker -->
<div class="d-flex align-center justify-between mb-24">
  <div class="d-flex align-center gap-12">
    <label for="monthPicker" class="text-sm text-muted">Period</label>
    <input type="month" id="monthPicker" class="form-control" style="width:160px"
           value="<?= date('Y-m') ?>" max="<?= date('Y-m') ?>">
  </div>
  <button class="btn btn-secondary btn-sm" id="generateBtn">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
    Generate Invoices
  </button>
</div>

<!-- KPI Cards -->
<div class="stat-grid mb-24" id="kpiGrid">
  <div class="stat-card accent-green">
    <div class="stat-label">Collection %</div>
    <div class="stat-value" id="kpiPct">—</div>
    <div class="stat-sub">of total due</div>
  </div>
  <div class="stat-card accent-blue">
    <div class="stat-label">Total Due</div>
    <div class="stat-value" id="kpiDue">—</div>
    <div class="stat-sub">this month</div>
  </div>
  <div class="stat-card accent-green">
    <div class="stat-label">Collected</div>
    <div class="stat-value" id="kpiPaid">—</div>
    <div class="stat-sub" id="kpiPaidCount">—</div>
  </div>
  <div class="stat-card accent-amber">
    <div class="stat-label">Outstanding</div>
    <div class="stat-value" id="kpiOutstanding">—</div>
    <div class="stat-sub" id="kpiOverdueCount">—</div>
  </div>
</div>

<!-- Rooms + Chart row -->
<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;margin-bottom:20px" class="dashboard-grid">

  <!-- Room occupancy -->
  <div class="card">
    <div class="card-header"><span class="card-title">Rooms</span> <a href="/rooms" class="btn btn-ghost btn-sm">View all</a></div>
    <div class="card-body" id="roomStats">
      <div class="text-muted text-sm">Loading…</div>
    </div>
  </div>

  <!-- Collection trend -->
  <div class="card">
    <div class="card-header"><span class="card-title">Collection trend</span></div>
    <div class="card-body">
      <canvas id="trendChart" height="120"></canvas>
    </div>
  </div>
</div>

<!-- Recent payments -->
<div class="card">
  <div class="card-header"><span class="card-title">Recent payments</span> <a href="/dues" class="btn btn-ghost btn-sm">All dues</a></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Tenant</th><th>Room</th><th>Amount</th><th>Mode</th><th>Date</th></tr></thead>
      <tbody id="recentPayments"><tr><td colspan="5" class="text-muted text-sm" style="padding:20px">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<script>
(function () {
  const fmt  = v => '₹' + Number(v).toLocaleString('en-IN', {maximumFractionDigits: 0});
  const fmtPct = v => v + '%';
  const csrf = document.querySelector('input[name="_csrf"]')?.value || '';

  function statusBadge(s) {
    const map = { paid:'success', partial:'warning', overdue:'danger', unpaid:'muted', vacant:'success', occupied:'primary', maintenance:'danger', partially_occupied:'warning' };
    return `<span class="badge badge-${map[s]||'muted'}">${s.replace('_',' ')}</span>`;
  }

  async function load(month) {
    try {
      const r = await fetch(`/api/dashboard?month=${month}`);
      const d = await r.json();
      const pct = d.collection_pct;

      document.getElementById('kpiPct').textContent         = fmtPct(pct);
      document.getElementById('kpiDue').textContent         = fmt(d.total_due);
      document.getElementById('kpiPaid').textContent        = fmt(d.total_paid);
      document.getElementById('kpiPaidCount').textContent   = `${d.invoices.paid_count} invoices paid`;
      document.getElementById('kpiOutstanding').textContent = fmt(d.outstanding);
      document.getElementById('kpiOverdueCount').textContent= `${d.invoices.overdue_count} overdue`;

      // Room stats
      const rm = d.rooms;
      const roomHtml = rm ? `
        <div style="display:flex;flex-direction:column;gap:12px">
          <div class="d-flex justify-between align-center">
            <span class="text-sm">Occupied</span>
            <span class="fw-600">${rm.occupied}</span>
          </div>
          <div class="progress"><div class="progress-bar" style="width:${rm.total_rooms>0?Math.round(rm.occupied/rm.total_rooms*100):0}%"></div></div>
          <div class="d-flex justify-between align-center mt-4">
            <span class="text-sm text-muted">Vacant</span><span>${rm.vacant}</span>
          </div>
          <div class="d-flex justify-between align-center">
            <span class="text-sm text-muted">Partial</span><span>${rm.partially_occupied}</span>
          </div>
          <div class="d-flex justify-between align-center">
            <span class="text-sm text-muted">Maintenance</span><span>${rm.maintenance}</span>
          </div>
          <div style="border-top:1px solid var(--border);padding-top:12px;margin-top:4px" class="d-flex justify-between align-center">
            <span class="text-sm fw-600">Total</span><span class="fw-600">${rm.total_rooms}</span>
          </div>
        </div>` : '<div class="text-muted text-sm">No data</div>';
      document.getElementById('roomStats').innerHTML = roomHtml;

      // Trend chart
      renderChart(d.trend || []);

      // Recent payments
      const tbody = document.getElementById('recentPayments');
      if (d.recent_payments?.length) {
        tbody.innerHTML = d.recent_payments.map(p => `
          <tr>
            <td>${escHtml(p.full_name)}</td>
            <td>${escHtml(p.room_number)}</td>
            <td class="fw-600 text-primary-color">${fmt(p.amount)}</td>
            <td>${statusBadge(p.mode)}</td>
            <td class="text-muted text-sm">${p.payment_date}</td>
          </tr>`).join('');
      } else {
        tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-sm" style="padding:20px;text-align:center">No payments recorded for this month.</td></tr>';
      }
    } catch(e) {
      console.error(e);
    }
  }

  let chart;
  function renderChart(trend) {
    const canvas = document.getElementById('trendChart');
    const ctx = canvas.getContext('2d');
    if (chart) chart.destroy();

    const labels = trend.map(t => t.label);
    const due    = trend.map(t => +t.due);
    const paid   = trend.map(t => +t.paid);

    chart = {
      destroy() {},
      _render() {
        const W = canvas.parentElement.clientWidth - 40;
        const H = 120;
        canvas.width  = W;
        canvas.height = H;

        if (!labels.length) { ctx.fillStyle = '#ccc'; ctx.fillText('No data', 10, H/2); return; }

        const maxVal = Math.max(...due, 1);
        const pad    = { left: 60, right: 10, top: 10, bottom: 28 };
        const cW     = W - pad.left - pad.right;
        const cH     = H - pad.top  - pad.bottom;
        const step   = cW / (labels.length - 1 || 1);

        ctx.clearRect(0, 0, W, H);

        // Grid lines
        ctx.strokeStyle = 'rgba(0,0,0,.06)';
        ctx.lineWidth   = 1;
        [0, .5, 1].forEach(f => {
          const y = pad.top + cH * (1 - f);
          ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(W - pad.right, y); ctx.stroke();
          ctx.fillStyle = '#9B9A96';
          ctx.font      = '10px sans-serif';
          ctx.textAlign = 'right';
          ctx.fillText(fmt(maxVal * f), pad.left - 6, y + 4);
        });

        // Due line (dashed)
        ctx.setLineDash([4, 3]);
        ctx.strokeStyle = '#9B9A96';
        ctx.lineWidth   = 1.5;
        ctx.beginPath();
        due.forEach((v, i) => {
          const x = pad.left + i * step;
          const y = pad.top + cH * (1 - v / maxVal);
          i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.stroke();
        ctx.setLineDash([]);

        // Paid line
        ctx.strokeStyle = '#1D9E75';
        ctx.lineWidth   = 2.5;
        ctx.beginPath();
        paid.forEach((v, i) => {
          const x = pad.left + i * step;
          const y = pad.top + cH * (1 - v / maxVal);
          i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.stroke();

        // Dots + labels
        paid.forEach((v, i) => {
          const x = pad.left + i * step;
          const y = pad.top + cH * (1 - v / maxVal);
          ctx.beginPath(); ctx.arc(x, y, 4, 0, Math.PI * 2);
          ctx.fillStyle = '#1D9E75'; ctx.fill();

          ctx.fillStyle = '#5F5E5A'; ctx.font = '10px sans-serif'; ctx.textAlign = 'center';
          ctx.fillText(labels[i], x, H - 6);
        });
      }
    };
    chart._render();
  }

  function escHtml(s) { const d = document.createElement('div'); d.textContent = s||''; return d.innerHTML; }

  // Month picker
  const picker = document.getElementById('monthPicker');
  picker.addEventListener('change', () => load(picker.value));
  load(picker.value);

  // Generate invoices
  document.getElementById('generateBtn').addEventListener('click', async function () {
    this.disabled = true;
    this.textContent = 'Generating…';
    try {
      const r = await fetch('/api/invoices/generate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `_csrf=${encodeURIComponent(document.querySelector('[name="_csrf"]')?.value||'')}&month=${picker.value}`
      });
      const d = await r.json();
      alert(`Generated ${d.created} invoice(s) for ${d.month}.`);
      load(picker.value);
    } catch(e) { alert('Failed to generate invoices.'); }
    this.disabled = false;
    this.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg> Generate Invoices`;
  });

  // Mobile sidebar handled in app.js
})();
</script>

<!-- Hidden CSRF for JS use -->
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
