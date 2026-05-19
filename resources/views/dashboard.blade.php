@extends('layouts.admin')
@section('title', 'A-SIX | Dashboard Area 6')
@section('content')
@php
$hero = data_get($dashboard ?? [], 'hero', []);
$metrics = data_get($dashboard ?? [], 'metrics', []);
$liveReports = is_array(data_get($dashboard ?? [], 'live_reports')) ? data_get($dashboard ?? [], 'live_reports') : [];
$digitalCards = is_array(data_get($dashboard ?? [], 'digital_performance.cards')) ? data_get($dashboard ?? [], 'digital_performance.cards') : [];
$timeseries = data_get($dashboard ?? [], 'timeseries', ['labels'=>[],'simpanan'=>[],'pinjaman'=>[]]);
$digitalUpdatedAt = data_get($dashboard ?? [], 'digital_performance.updated_at');
$simpananReport = collect($liveReports)->firstWhere('key', 'simpanan') ?? [];
$pinjamanReport = collect($liveReports)->firstWhere('key', 'pinjaman') ?? [];
$portfolioReport = collect($liveReports)->firstWhere('key', 'portfolio') ?? [];
$tsLabels = json_encode(data_get($timeseries,'labels',[]));
$tsSimpanan = json_encode(data_get($timeseries,'simpanan',[]));
$tsPinjaman = json_encode(data_get($timeseries,'pinjaman',[]));
@endphp

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900&display=swap');

:root {
  --c-blue: #0857c3; --c-blue-d: #053b82; --c-blue-l: #307fe2;
  --c-teal: #0f766e; --c-red: #dc2626; --c-amber: #d97706;
  --c-green: #059669; --c-purple: #7c3aed; --c-pink: #db2777;
  --c-surf: #f1f6ff; --c-border: rgba(8,87,195,.12);
  --shadow: 0 8px 24px -12px rgba(4,42,95,.28);
  --r: 14px;
}
.db-shell { font-family:'Inter',sans-serif; padding:0 0 1rem; }

/* ── KPI STRIP ── */
.kpi-strip { display:grid; grid-template-columns:repeat(3,1fr) 1.6fr repeat(2,1fr); gap:.65rem; margin-bottom:.65rem; }
.kpi-card { border-radius:var(--r); padding:.7rem .85rem .6rem; position:relative; overflow:hidden; border:1px solid var(--c-border); background:#fff; transition:transform .18s,box-shadow .18s; }
.kpi-card:hover { transform:translateY(-2px); box-shadow:var(--shadow); }
.kpi-card .kc-label { font-size:.6rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#64748b; margin-bottom:.18rem; }
.kpi-card .kc-val { font-size:1.32rem; font-weight:800; line-height:1.05; color:#0f172a; }
.kpi-card .kc-sub { font-size:.62rem; color:#64748b; margin-top:.15rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.kpi-card .kc-delta { display:inline-flex; align-items:center; gap:.25rem; font-size:.65rem; font-weight:700; padding:.18rem .5rem; border-radius:999px; margin-top:.28rem; }
.kpi-card.simpanan { background:linear-gradient(145deg,#053b82,#0857c3); border:none; }
.kpi-card.simpanan .kc-label,.kpi-card.simpanan .kc-sub { color:rgba(255,255,255,.7); }
.kpi-card.simpanan .kc-val { color:#fff; font-size:1.6rem; }
.kpi-card.pinjaman { background:linear-gradient(145deg,#0a4f68,#1177a3); border:none; }
.kpi-card.pinjaman .kc-label,.kpi-card.pinjaman .kc-sub { color:rgba(255,255,255,.7); }
.kpi-card.pinjaman .kc-val { color:#fff; font-size:1.6rem; }
.kpi-card.portfolio { background:linear-gradient(145deg,#134e4a,#0f766e); border:none; }
.kpi-card.portfolio .kc-label,.kpi-card.portfolio .kc-sub { color:rgba(255,255,255,.7); }
.kpi-card.portfolio .kc-val { color:#fff; font-size:1.6rem; }
.kc-live { position:absolute; top:.55rem; right:.65rem; width:7px; height:7px; border-radius:999px; background:#4ade80; box-shadow:0 0 0 0 rgba(74,222,128,.45); animation:pulse-live 1.8s infinite; }
@keyframes pulse-live { 0%,100%{box-shadow:0 0 0 0 rgba(74,222,128,.45)} 70%{box-shadow:0 0 0 8px rgba(74,222,128,0)} }
.kpi-card .kc-link { position:absolute; bottom:.5rem; right:.7rem; font-size:.6rem; font-weight:700; color:rgba(255,255,255,.8); text-decoration:none; display:inline-flex; align-items:center; gap:.25rem; }
.kpi-card .kc-link:hover { color:#fff; }
.kpi-card button.kc-link { border:0; background:transparent; padding:0; cursor:pointer; }

/* ── CHART + DIGITAL GRID ── */
.main-grid { display:grid; grid-template-columns:1fr 2.4fr; gap:.65rem; }
.chart-panel { border-radius:var(--r); background:#fff; border:1px solid var(--c-border); padding:.85rem; }
.chart-panel .cp-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:.6rem; }
.chart-panel .cp-title { font-size:.75rem; font-weight:800; color:#0f172a; }
.chart-panel .cp-legend { display:flex; gap:.85rem; }
.chart-panel .cp-leg-item { display:flex; align-items:center; gap:.35rem; font-size:.6rem; font-weight:600; color:#64748b; }
.chart-panel .cp-leg-dot { width:8px; height:8px; border-radius:999px; }
.chart-panel canvas { width:100% !important; height:160px !important; }

/* ── DIGITAL GRID ── */
.digital-panel { border-radius:var(--r); background:#fff; border:1px solid var(--c-border); padding:.7rem; }
.dp-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:.55rem; }
.dp-title { font-size:.72rem; font-weight:800; color:#0f172a; }
.dp-updated { font-size:.58rem; color:#94a3b8; }
.dp-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:.5rem; }
.dc { border-radius:12px; padding:.65rem .6rem .55rem; color:#fff; position:relative; overflow:hidden; cursor:pointer; text-decoration:none; display:flex; flex-direction:column; transition:transform .16s,box-shadow .16s; border:0; text-align:left; width:100%; min-height:0; font:inherit; appearance:none; }
.dc:hover { transform:translateY(-2px); box-shadow:0 12px 28px -16px rgba(4,42,95,.5); color:#fff; }
.dc::before { content:''; position:absolute; inset:-40% -30% auto auto; width:120px; height:120px; border-radius:999px; background:rgba(255,255,255,.1); pointer-events:none; }
.dc-edc { background:linear-gradient(145deg,#0a3ea1,#1d87ff); }
.dc-qris { background:linear-gradient(145deg,#08506c,#12a5c3); }
.dc-qlola { background:linear-gradient(145deg,#7c3aed,#a855f7); }
.dc-brimo { background:linear-gradient(145deg,#272e8f,#3b82f6); }
.dc-brilink { background:linear-gradient(145deg,#0d6b4d,#22c55e); }
.dc-casa { background:linear-gradient(145deg,#b45309,#f59e0b); }
.dc-dormant { background:linear-gradient(145deg,#991b1b,#ef4444); }
.dc-payroll { background:linear-gradient(145deg,#374151,#6b7280); }
.dc-badge { display:inline-flex; align-items:center; gap:.28rem; padding:.2rem .45rem; border-radius:999px; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.18); font-size:.55rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; margin-bottom:.35rem; width:fit-content; }
.dc-val { font-size:1.05rem; font-weight:800; line-height:1.05; }
.dc-label { font-size:.58rem; color:rgba(255,255,255,.72); margin-bottom:.2rem; }
.dc-sub { font-size:.58rem; color:rgba(255,255,255,.65); }
.dc-trend { display:inline-flex; align-items:center; gap:.22rem; font-size:.58rem; font-weight:700; padding:.16rem .4rem; border-radius:999px; background:rgba(255,255,255,.14); margin-top:auto; margin-bottom:.1rem; }
.dc-foot { display:flex; justify-content:space-between; align-items:center; margin-top:.3rem; }
.dc-link { font-size:.58rem; font-weight:700; color:rgba(255,255,255,.85); display:inline-flex; align-items:center; gap:.22rem; }
.dc-link:hover { color:#fff; }
.dc:focus-visible, .kpi-card button.kc-link:focus-visible { outline:2px solid rgba(255,255,255,.8); outline-offset:2px; }
.dc-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:.25rem; margin-top:.4rem; }
.dc-stat { background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.1); border-radius:7px; padding:.25rem .3rem; }
.dc-stat-lbl { font-size:.52rem; color:rgba(255,255,255,.65); }
.dc-stat-val { font-size:.65rem; font-weight:700; }
.dc-stub { opacity:.72; filter:grayscale(.3); }
.dc-stub::after { content:'–'; }

/* ── HEADER ── */
.db-header { display:flex; align-items:center; justify-content:space-between; padding:.5rem .25rem .55rem; margin-bottom:.55rem; border-bottom:1px solid var(--c-border); }
.db-brand { display:flex; align-items:center; gap:.65rem; }
.db-logo { width:34px; height:34px; border-radius:10px; background:linear-gradient(135deg,#053b82,#307fe2); display:flex; align-items:center; justify-content:center; }
.db-logo img { width:26px; height:26px; object-fit:contain; }
.db-title { font-size:.88rem; font-weight:800; color:#0f172a; letter-spacing:-.01em; }
.db-subtitle { font-size:.6rem; color:#64748b; margin-top:.05rem; }
.db-meta { display:flex; align-items:center; gap:.75rem; }
.db-meta-chip { display:inline-flex; align-items:center; gap:.35rem; padding:.28rem .65rem; border-radius:999px; background:#f1f6ff; border:1px solid var(--c-border); font-size:.62rem; font-weight:700; color:#2563eb; }
.db-now { font-size:.6rem; color:#94a3b8; }

/* delta colors */
.pos { color:#059669; background:#ecfdf5; }
.neg { color:#dc2626; background:#fef2f2; }
.neu { color:#64748b; background:#f1f5f9; }
.source-modal-meta { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.55rem; margin-bottom:.75rem; }
.source-modal-chip { border:1px solid var(--c-border); border-radius:10px; padding:.55rem .65rem; background:#f8fbff; }
.source-modal-chip span { display:block; font-size:.58rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:.06em; }
.source-modal-chip strong { display:block; margin-top:.15rem; font-size:.74rem; color:#0f172a; word-break:break-word; }
.source-modal-note { font-size:.72rem; color:#475569; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:.6rem .7rem; margin-bottom:.7rem; }
.source-modal-table th { font-size:.68rem; text-transform:uppercase; color:#64748b; letter-spacing:.04em; border-top:0; }
.source-modal-table td { font-size:.78rem; vertical-align:middle; }
@media (max-width: 575.98px) { .source-modal-meta { grid-template-columns:1fr; } }
</style>

<div class="db-shell pt-2">
  {{-- HEADER --}}
  <div class="db-header">
    <div class="db-brand">
      <div class="db-logo">
        <img src="{{ asset('images/a-six-logo.svg') }}" alt="A-SIX">
      </div>
      <div>
        <div class="db-title">A-SIX Area 6 — Dashboard Realtime</div>
        <div class="db-subtitle">Ringkasan posisi keuangan, portofolio, dan 8 strategi digital</div>
      </div>
    </div>
    <div class="db-meta">
      <span class="db-meta-chip"><i class="fas fa-circle" style="color:#4ade80;font-size:.5rem;"></i> Live Snapshot</span>
      @if($digitalUpdatedAt)
      <span class="db-now">Updated: {{ $digitalUpdatedAt }} WIB</span>
      @endif
      <span class="db-now" id="db-clock"></span>
    </div>
  </div>

  {{-- KPI STRIP: Simpanan | Pinjaman | Portfolio | Growth Simp | Growth Pinj | Coverage --}}
  <div class="kpi-strip">
    {{-- SIMPANAN --}}
    <div class="kpi-card simpanan">
      <div class="kc-live"></div>
      <div class="kc-label"><i class="fas fa-piggy-bank mr-1"></i>Dana Simpanan</div>
      <div class="kc-val">{{ data_get($simpananReport,'value','–') }}</div>
      <div class="kc-sub">{{ data_get($simpananReport,'meta','–') }}</div>
      @php $sm = (float)str_replace(['+','%',','],['','','.'],data_get($simpananReport,'trend','0')); @endphp
      <span class="kc-delta {{ $sm>=0?'pos':'neg' }}">
        <i class="fas {{ $sm>=0?'fa-arrow-up':'fa-arrow-down' }}"></i>
        {{ data_get($simpananReport,'trend','0%') }} MoM
      </span>
      <button type="button" class="kc-link dashboard-detail-trigger" data-detail='@json(data_get($simpananReport,"detail_payload",[]))' data-link="{{ data_get($simpananReport,'link','#') }}" data-link-label="{{ data_get($simpananReport,'link_label','Buka report') }}">Detail <i class="fas fa-table"></i></button>
    </div>

    {{-- PINJAMAN --}}
    <div class="kpi-card pinjaman">
      <div class="kc-live"></div>
      <div class="kc-label"><i class="fas fa-hand-holding-usd mr-1"></i>Kredit Pinjaman</div>
      <div class="kc-val">{{ data_get($pinjamanReport,'value','–') }}</div>
      <div class="kc-sub">{{ data_get($pinjamanReport,'meta','–') }}</div>
      @php $pm = (float)str_replace(['+','%',','],['','','.'],data_get($pinjamanReport,'trend','0')); @endphp
      <span class="kc-delta {{ $pm>=0?'pos':'neg' }}">
        <i class="fas {{ $pm>=0?'fa-arrow-up':'fa-arrow-down' }}"></i>
        {{ data_get($pinjamanReport,'trend','0%') }} MoM
      </span>
      <button type="button" class="kc-link dashboard-detail-trigger" data-detail='@json(data_get($pinjamanReport,"detail_payload",[]))' data-link="{{ data_get($pinjamanReport,'link','#') }}" data-link-label="{{ data_get($pinjamanReport,'link_label','Buka report') }}">Detail <i class="fas fa-table"></i></button>
    </div>

    {{-- PORTFOLIO --}}
    <div class="kpi-card portfolio">
      <div class="kc-label"><i class="fas fa-layer-group mr-1"></i>LDR (Loan to Deposit Ratio)</div>
      <div class="kc-val">{{ data_get($portfolioReport,'value','–') }}</div>
      <div class="kc-sub" style="max-width:150px;white-space:normal;font-size:.58rem;">{{ data_get($portfolioReport,'meta','–') }}</div>
      <button type="button" class="kc-link dashboard-detail-trigger" data-detail='@json(data_get($portfolioReport,"detail_payload",[]))' data-link="{{ data_get($portfolioReport,'link','#') }}" data-link-label="{{ data_get($portfolioReport,'link_label','Lihat report') }}">Detail <i class="fas fa-table"></i></button>
    </div>

    {{-- 3 METRIC CARDS --}}
    @foreach(array_slice($metrics,0,3) as $m)
    <div class="kpi-card">
      <div class="kc-label">{{ data_get($m,'label','–') }}</div>
      <div class="kc-val" style="font-size:1.05rem;">{{ data_get($m,'value','–') }}</div>
      <div class="kc-sub {{ data_get($m,'delta_class','text-muted') }}">{{ data_get($m,'delta','–') }}</div>
    </div>
    @endforeach
  </div>

  {{-- MAIN GRID: Chart + Digital --}}
  <div class="main-grid">
    {{-- TIMESERIES CHART --}}
    <div class="chart-panel">
      <div class="cp-header">
        <div>
          <div class="cp-title">Tren Posisi 6 Periode</div>
          <div style="font-size:.58rem;color:#94a3b8;margin-top:.1rem;">Simpanan vs Pinjaman (dalam Rp Triliun)</div>
        </div>
        <div class="cp-legend">
          <div class="cp-leg-item"><div class="cp-leg-dot" style="background:#3b82f6;"></div>Simpanan</div>
          <div class="cp-leg-item"><div class="cp-leg-dot" style="background:#ef4444;"></div>Pinjaman</div>
        </div>
      </div>
      <canvas id="timeseriesChart"></canvas>
    </div>

    {{-- 8 DIGITAL CARDS --}}
    <div class="digital-panel">
      <div class="dp-header">
        <div class="dp-title"><i class="fas fa-bolt mr-1" style="color:#f59e0b;"></i>8 Strategi Performance Digital Area 6</div>
        @if($digitalUpdatedAt)
        <div class="dp-updated"><i class="fas fa-sync-alt mr-1"></i>{{ $digitalUpdatedAt }} WIB</div>
        @endif
      </div>
      <div class="dp-grid">
        @php
        $toneMap = [
          'edc'=>'dc-edc','qris'=>'dc-qris','qlola'=>'dc-qlola','brimo'=>'dc-brimo',
          'brilink'=>'dc-brilink','casa'=>'dc-casa','dormant'=>'dc-dormant','payroll'=>'dc-payroll',
        ];
        $iconMap = [
          'edc'=>'fa-credit-card','qris'=>'fa-qrcode','qlola'=>'fa-university','brimo'=>'fa-mobile-alt',
          'brilink'=>'fa-network-wired','casa'=>'fa-percentage','dormant'=>'fa-bed','payroll'=>'fa-briefcase',
        ];
        @endphp
        @forelse($digitalCards as $dc)
        @php
          $key = data_get($dc,'key','edc');
          $tone = $toneMap[$key] ?? 'dc-edc';
          $icon = $iconMap[$key] ?? 'fa-chart-bar';
          $isStub = data_get($dc,'is_stub',false);
          $tv = (float)data_get($dc,'trend_value',0);
        @endphp
        <button type="button" class="dc dashboard-detail-trigger {{ $tone }} {{ $isStub?'dc-stub':'' }}" data-detail='@json(data_get($dc,"detail_payload",[]))' data-link="{{ data_get($dc,'link','#') }}" data-link-label="{{ data_get($dc,'link_label','Buka report') }}">
          <div class="dc-badge"><i class="fas {{ $icon }}"></i> {{ data_get($dc,'badge','–') }}</div>
          <div class="dc-label">{{ data_get($dc,'current_label','–') }}</div>
          <div class="dc-val">{{ data_get($dc,'current_value','–') }}</div>
          <div class="dc-sub">{{ data_get($dc,'secondary_label','–') }}: {{ data_get($dc,'secondary_value','–') }}</div>
          <div class="dc-stats">
            @foreach(array_slice(data_get($dc,'stats',[]),0,3) as $st)
            <div class="dc-stat">
              <div class="dc-stat-lbl">{{ data_get($st,'label','–') }}</div>
              <div class="dc-stat-val">{{ data_get($st,'value','–') }}</div>
            </div>
            @endforeach
          </div>
          <div class="dc-foot">
            <span class="dc-trend">
              <i class="fas {{ $tv>=0?'fa-arrow-up':'fa-arrow-down' }}"></i>
              {{ data_get($dc,'trend','0%') }}
            </span>
            <span class="dc-link">{{ data_get($dc,'link_label','Detail') }} <i class="fas fa-arrow-right"></i></span>
          </div>
        </button>
        @empty
        <div class="col-12 text-muted small">Data performance digital belum tersedia.</div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="modal fade" id="dashboardSourceModal" tabindex="-1" role="dialog" aria-labelledby="dashboardSourceModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-1" id="dashboardSourceModalTitle">Detail sumber data</h5>
            <div class="text-muted small">Angka ditampilkan apa adanya dari payload landing page.</div>
          </div>
          <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="source-modal-meta">
            <div class="source-modal-chip"><span>Periode</span><strong id="sourceModalPeriod">-</strong></div>
            <div class="source-modal-chip"><span>Tabel sumber</span><strong id="sourceModalTable">-</strong></div>
            <div class="source-modal-chip"><span>Report</span><strong id="sourceModalReport">-</strong></div>
          </div>
          <div class="source-modal-note" id="sourceModalNote">-</div>
          <div class="table-responsive">
            <table class="table table-sm source-modal-table mb-0">
              <thead>
                <tr>
                  <th>Metrik</th>
                  <th>Nilai tampil</th>
                  <th>Sumber</th>
                </tr>
              </thead>
              <tbody id="sourceModalRows">
                <tr><td colspan="3" class="text-muted">Detail belum tersedia.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <a href="#" class="btn btn-sm btn-primary" id="sourceModalLink">Buka report</a>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">Tutup</button>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Clock
  const clock = document.getElementById('db-clock');
  if (clock) {
    const tick = () => { clock.textContent = new Date().toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit',second:'2-digit'})+' WIB'; };
    tick(); setInterval(tick, 1000);
  }

  // Timeseries Chart
  const labels = @json(data_get($timeseries,'labels',[]));
  const simp   = @json(data_get($timeseries,'simpanan',[]));
  const pinj   = @json(data_get($timeseries,'pinjaman',[]));

  const ctx = document.getElementById('timeseriesChart');
  if (ctx && labels.length) {
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Simpanan (Rp T)',
            data: simp,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59,130,246,.12)',
            borderWidth: 2.5,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#3b82f6',
            pointBorderWidth: 2,
            fill: true,
            tension: .38,
            yAxisID: 'y',
          },
          {
            label: 'Pinjaman (Rp T)',
            data: pinj,
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239,68,68,.08)',
            borderWidth: 2.5,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#ef4444',
            pointBorderWidth: 2,
            fill: true,
            tension: .38,
            yAxisID: 'y2',
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode:'index', intersect:false },
        plugins: {
          legend: { display:false },
          tooltip: {
            backgroundColor:'rgba(15,23,42,.92)',
            titleFont:{ size:11, weight:'700' },
            bodyFont:{ size:10.5 },
            padding:10,
            cornerRadius:10,
            callbacks: {
              label: ctx => {
                const v = ctx.parsed.y;
                return ' ' + ctx.dataset.label + ': Rp' + (v ? v.toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:3}) : '0') + ' T';
              }
            }
          }
        },
        scales: {
          x: {
            grid:{ color:'rgba(148,163,184,.12)' },
            ticks:{ font:{size:10,weight:'600'}, color:'#64748b' }
          },
          y: {
            position:'left',
            grid:{ color:'rgba(148,163,184,.12)' },
            ticks:{ font:{size:10}, color:'#3b82f6', callback: v => 'Rp'+v.toFixed(1)+'T' }
          },
          y2: {
            position:'right',
            grid:{ drawOnChartArea:false },
            ticks:{ font:{size:10}, color:'#ef4444', callback: v => 'Rp'+v.toFixed(1)+'T' }
          }
        }
      }
    });
  }

  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[char]));

  document.querySelectorAll('.dashboard-detail-trigger').forEach(trigger => {
    trigger.addEventListener('click', function(event) {
      event.preventDefault();

      let detail = {};
      try {
        detail = JSON.parse(this.getAttribute('data-detail') || '{}') || {};
      } catch (error) {
        detail = {};
      }

      const title = detail.title || 'Detail sumber data';
      const rows = Array.isArray(detail.rows) ? detail.rows : [];
      document.getElementById('dashboardSourceModalTitle').textContent = title;
      document.getElementById('sourceModalReport').textContent = title;
      document.getElementById('sourceModalPeriod').textContent = detail.period || '-';
      document.getElementById('sourceModalTable').textContent = detail.source_table || '-';
      document.getElementById('sourceModalNote').textContent = detail.note || 'Detail sumber belum tersedia.';

      const tbody = document.getElementById('sourceModalRows');
      tbody.innerHTML = rows.length
        ? rows.map(row => `
            <tr>
              <td>${escapeHtml(row.label || '-')}</td>
              <td class="font-weight-bold">${escapeHtml(row.value || '-')}</td>
              <td><code>${escapeHtml(row.source || detail.source_table || '-')}</code></td>
            </tr>
          `).join('')
        : '<tr><td colspan="3" class="text-muted">Detail belum tersedia.</td></tr>';

      const link = document.getElementById('sourceModalLink');
      link.href = this.getAttribute('data-link') || '#';
      link.textContent = this.getAttribute('data-link-label') || 'Buka report';

      if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
        window.jQuery('#dashboardSourceModal').modal('show');
      }
    });
  });
});
</script>
@endsection
