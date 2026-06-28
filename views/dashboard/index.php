<h2 class="sr-only"><?= __('dashboard.title') ?></h2>

<!-- Month picker -->
<div class="d-flex align-center justify-between mb-24">
  <div class="d-flex align-center gap-12">
    <label for="monthPicker" class="text-sm text-muted"><?= __('common.period') ?></label>
    <input type="month" id="monthPicker" class="form-control" style="width:160px"
           value="<?= date('Y-m') ?>" max="<?= date('Y-m') ?>">
  </div>
  <button class="btn btn-secondary btn-sm" id="generateBtn">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
    <?= __('dashboard.generate_invoices') ?>
  </button>
</div>

<!-- KPI Cards -->
<div class="stat-grid mb-24" id="kpiGrid">
  <div class="stat-card accent-green">
    <div class="stat-label"><?= __('dashboard.collection_pct') ?></div>
    <div class="stat-value" id="kpiPct">—</div>
    <div class="stat-sub"><?= __('dashboard.of_total_due') ?></div>
  </div>
  <div class="stat-card accent-blue">
    <div class="stat-label"><?= __('dashboard.total_due') ?></div>
    <div class="stat-value" id="kpiDue">—</div>
    <div class="stat-sub"><?= __('dashboard.this_month') ?></div>
  </div>
  <div class="stat-card accent-green">
    <div class="stat-label"><?= __('dashboard.collected') ?></div>
    <div class="stat-value" id="kpiPaid">—</div>
    <div class="stat-sub" id="kpiPaidCount">—</div>
  </div>
  <div class="stat-card accent-amber">
    <div class="stat-label"><?= __('dashboard.outstanding') ?></div>
    <div class="stat-value" id="kpiOutstanding">—</div>
    <div class="stat-sub" id="kpiOverdueCount">—</div>
  </div>
</div>

<!-- Rooms + Chart row -->
<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;margin-bottom:20px" class="dashboard-grid">
  <div class="card">
    <div class="card-header"><span class="card-title"><?= __('dashboard.rooms') ?></span> <a href="<?= url("/rooms") ?>" class="btn btn-ghost btn-sm"><?= __('dashboard.view_all') ?></a></div>
    <div class="card-body" id="roomStats">
      <div class="text-muted text-sm"><?= __('common.loading') ?></div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><span class="card-title"><?= __('dashboard.collection_trend') ?></span></div>
    <div class="card-body">
      <canvas id="trendChart" height="120"></canvas>
    </div>
  </div>
</div>

<!-- Recent payments -->
<div class="card">
  <div class="card-header"><span class="card-title"><?= __('dashboard.recent_payments') ?></span> <a href="<?= url("/dues") ?>" class="btn btn-ghost btn-sm"><?= __('dashboard.all_dues') ?></a></div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th><?= __('common.tenant') ?></th>
        <th><?= __('common.room') ?></th>
        <th><?= __('common.amount') ?></th>
        <th><?= __('common.type') ?></th>
        <th><?= __('common.date') ?></th>
      </tr></thead>
      <tbody id="recentPayments"><tr><td colspan="5" class="text-muted text-sm" style="padding:20px"><?= __('common.loading') ?></td></tr></tbody>
    </table>
  </div>
</div>

<script>
(function () {
  const fmt    = v => '₹' + Number(v).toLocaleString('en-IN', {maximumFractionDigits: 0});
  const fmtPct = v => v + '%';
  const L = {
    invoices_paid : <?= json_encode(__('dashboard.invoices_paid')) ?>,
    overdue       : <?= json_encode(__('dashboard.overdue')) ?>,
    no_payments   : <?= json_encode(__('dashboard.no_payments')) ?>,
    occupied      : <?= json_encode(__('dashboard.occupied')) ?>,
    vacant        : <?= json_encode(__('dashboard.vacant')) ?>,
    partial       : <?= json_encode(__('dashboard.partial')) ?>,
    maintenance   : <?= json_encode(__('dashboard.maintenance')) ?>,
    total         : <?= json_encode(__('dashboard.total')) ?>,
    generating    : <?= json_encode(__('dashboard.generating')) ?>,
    gen_invoices  : <?= json_encode(__('dashboard.generate_invoices')) ?>,
  };

  function statusBadge(s) {
    const map = { paid:'success', partial:'warning', overdue:'danger', unpaid:'muted', vacant:'success', occupied:'primary', maintenance:'danger', partially_occupied:'warning' };
    return `<span class="badge badge-${map[s]||'muted'}">${s.replace('_',' ')}</span>`;
  }

  async function load(month) {
    try {
      const r = await fetch(`${BASE}/api/dashboard?month=${month}`);
      const d = await r.json();
      const pct = d.collection_pct;

      document.getElementById('kpiPct').textContent         = fmtPct(pct);
      document.getElementById('kpiDue').textContent         = fmt(d.total_due);
      document.getElementById('kpiPaid').textContent        = fmt(d.total_paid);
      document.getElementById('kpiPaidCount').textContent   = `${d.invoices.paid_count} ${L.invoices_paid}`;
      document.getElementById('kpiOutstanding').textContent = fmt(d.outstanding);
      document.getElementById('kpiOverdueCount').textContent= `${d.invoices.overdue_count} ${L.overdue}`;

      const rm = d.rooms;
      const roomHtml = rm ? `
        <div style="display:flex;flex-direction:column;gap:12px">
          <div class="d-flex justify-between align-center">
            <span class="text-sm">${L.occupied}</span>
            <span class="fw-600">${rm.occupied}</span>
          </div>
          <div class="progress"><div class="progress-bar" style="width:${rm.total_rooms>0?Math.round(rm.occupied/rm.total_rooms*100):0}%"></div></div>
          <div class="d-flex justify-between align-center mt-4">
            <span class="text-sm text-muted">${L.vacant}</span><span>${rm.vacant}</span>
          </div>
          <div class="d-flex justify-between align-center">
            <span class="text-sm text-muted">${L.partial}</span><span>${rm.partially_occupied}</span>
          </div>
          <div class="d-flex justify-between align-center">
            <span class="text-sm text-muted">${L.maintenance}</span><span>${rm.maintenance}</span>
          </div>
          <div style="border-top:1px solid var(--border);padding-top:12px;margin-top:4px" class="d-flex justify-between align-center">
            <span class="text-sm fw-600">${L.total}</span><span class="fw-600">${rm.total_rooms}</span>
          </div>
        </div>` : `<div class="text-muted text-sm">${L.no_data}</div>`;
      document.getElementById('roomStats').innerHTML = roomHtml;

      renderChart(d.trend || []);

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
        tbody.innerHTML = `<tr><td colspan="5" class="text-muted text-sm" style="padding:20px;text-align:center">${L.no_payments}</td></tr>`;
      }
    } catch(e) { console.error(e); }
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
        if (!labels.length) { ctx.fillStyle='#ccc'; ctx.fillText('No data',10,H/2); return; }
        const maxVal = Math.max(...due, 1);
        const pad = {left:60,right:10,top:10,bottom:28};
        const cW = W-pad.left-pad.right, cH = H-pad.top-pad.bottom;
        const step = cW/(labels.length-1||1);
        ctx.clearRect(0,0,W,H);
        ctx.strokeStyle='rgba(0,0,0,.06)'; ctx.lineWidth=1;
        [0,.5,1].forEach(f => {
          const y=pad.top+cH*(1-f);
          ctx.beginPath(); ctx.moveTo(pad.left,y); ctx.lineTo(W-pad.right,y); ctx.stroke();
          ctx.fillStyle='#9B9A96'; ctx.font='10px sans-serif'; ctx.textAlign='right';
          ctx.fillText(fmt(maxVal*f),pad.left-6,y+4);
        });
        ctx.setLineDash([4,3]); ctx.strokeStyle='#9B9A96'; ctx.lineWidth=1.5;
        ctx.beginPath();
        due.forEach((v,i) => { const x=pad.left+i*step,y=pad.top+cH*(1-v/maxVal); i===0?ctx.moveTo(x,y):ctx.lineTo(x,y); });
        ctx.stroke(); ctx.setLineDash([]);
        ctx.strokeStyle='#1D9E75'; ctx.lineWidth=2.5;
        ctx.beginPath();
        paid.forEach((v,i) => { const x=pad.left+i*step,y=pad.top+cH*(1-v/maxVal); i===0?ctx.moveTo(x,y):ctx.lineTo(x,y); });
        ctx.stroke();
        paid.forEach((v,i) => {
          const x=pad.left+i*step,y=pad.top+cH*(1-v/maxVal);
          ctx.beginPath(); ctx.arc(x,y,4,0,Math.PI*2); ctx.fillStyle='#1D9E75'; ctx.fill();
          ctx.fillStyle='#5F5E5A'; ctx.font='10px sans-serif'; ctx.textAlign='center';
          ctx.fillText(labels[i],x,H-6);
        });
      }
    };
    chart._render();
  }

  function escHtml(s) { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

  const picker = document.getElementById('monthPicker');
  picker.addEventListener('change', () => load(picker.value));
  load(picker.value);

  // Generate invoices (uses session CSRF from hidden input)
  document.getElementById('generateBtn').addEventListener('click', async function () {
    this.disabled = true;
    this.textContent = L.generating;
    try {
      const csrf = document.querySelector('[name="_csrf"]')?.value || '';
      const r = await fetch(BASE + '/api/invoices/generate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
        body: `_csrf=${encodeURIComponent(csrf)}&month=${encodeURIComponent(picker.value)}`
      });
      const d = await r.json().catch(() => null);
      if (!r.ok || !d?.ok) { alert('Error: ' + (d?.error || r.status)); }
      else if (d.created > 0) { alert(`${d.created} ${L.gen_invoices}`); load(picker.value); }
      else { alert('0 new invoices — already generated.'); }
    } catch(e) { alert('Failed: ' + e.message); }
    this.disabled = false;
    this.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg> ${L.gen_invoices}`;
  });
})();
</script>

<!-- Hidden CSRF for JS use -->
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? $_SESSION['csrf_token'] ?? '') ?>">
