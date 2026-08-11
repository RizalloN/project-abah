@extends('layouts.admin')
@section('title', 'Bank Pipeline')
@section('content')
<style>
:root{
  --d-blue:#0b5d7a;--d-blue-dk:#073b53;--d-blue-lt:#e7f5f8;
  --d-surface:#ffffff;--d-bg:#f8fafc;--d-border:#e2e8f0;
  --d-text:#1e293b;--d-muted:#64748b;--d-hover:#f1f5f9;
  --d-radius:12px;
  --d-shadow:0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.04);
  --d-shadow-md:0 4px 20px rgba(0,0,0,.10);
  --d-shadow-lg:0 16px 48px rgba(0,0,0,.14);
}
*,*::before,*::after{box-sizing:border-box;}
.dv-wrap{background:var(--d-bg);min-height:calc(100vh - 60px);display:flex;flex-direction:column;}

/* ── TOP BAR ── */
.dv-topbar{
  display:flex;align-items:center;justify-content:space-between;
  padding:.85rem 1.25rem;background:var(--d-surface);
  border-bottom:1px solid var(--d-border);gap:1rem;flex-wrap:wrap;
  position:sticky;top:0;z-index:100;
}
.dv-brand{display:flex;align-items:center;gap:.75rem;}
.dv-brand-logo{
  width:38px;height:38px;border-radius:10px;flex-shrink:0;
  background:linear-gradient(135deg,#0b5d7a,#0891b2);
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:1.1rem;box-shadow:0 3px 10px rgba(26,115,232,.3);
}
.dv-brand-name{font-size:1.15rem;font-weight:800;color:var(--d-text);margin:0;letter-spacing:-.01em;}
.dv-brand-sub{font-size:.7rem;color:var(--d-muted);margin:0;}
.dv-topbar-right{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;}

/* ── BUTTONS ── */
.btn-dv{
  display:inline-flex;align-items:center;gap:.4rem;
  padding:.46rem .95rem;border-radius:20px;border:none;
  min-height:34px;
  font-size:.8rem;font-weight:700;cursor:pointer;
  transition:all .15s;white-space:nowrap;text-decoration:none;line-height:1.2;
}
.btn-dv-primary{background:var(--d-blue);color:#fff;box-shadow:0 2px 8px rgba(26,115,232,.25);}
.btn-dv-primary:hover{background:var(--d-blue-dk);box-shadow:0 4px 14px rgba(26,115,232,.35);color:#fff;}
.btn-dv-secondary{background:var(--d-surface);color:var(--d-blue);border:1.5px solid var(--d-blue);}
.btn-dv-secondary:hover{background:var(--d-blue-lt);color:var(--d-blue);}
.btn-dv-danger{background:#fff1f2;color:#dc2626;border:1.5px solid #fca5a5;}
.btn-dv-danger:hover{background:#fee2e2;}
.btn-dv-ghost{background:transparent;color:var(--d-muted);border:none;}
.btn-dv-ghost:hover{background:var(--d-hover);color:var(--d-text);}
.btn-dv-icon{width:36px;height:36px;min-height:36px;padding:0!important;border-radius:50%;justify-content:center;}
.btn-dv-icon.on{background:var(--d-blue-lt);color:var(--d-blue);}
.btn-dv:disabled{cursor:not-allowed;opacity:.58;box-shadow:none;transform:none;pointer-events:none;}

/* ── BREADCRUMB ── */
.dv-breadcrumb{
  display:flex;align-items:center;gap:.2rem;
  padding:.6rem 1.25rem;background:var(--d-surface);
  border-bottom:1px solid var(--d-border);font-size:.8rem;flex-wrap:wrap;
}
.dv-breadcrumb a{color:var(--d-muted);text-decoration:none;font-weight:600;padding:.2rem .42rem;border-radius:6px;transition:all .12s;white-space:nowrap;min-width:0;max-width:min(24rem,70vw);overflow:hidden;text-overflow:ellipsis;}
.dv-breadcrumb a:hover{background:var(--d-hover);color:var(--d-text);}
.dv-breadcrumb .sep{color:#cbd5e1;display:flex;align-items:center;}
.dv-breadcrumb .current{color:var(--d-text);font-weight:700;padding:.2rem .42rem;white-space:nowrap;min-width:0;max-width:min(24rem,70vw);overflow:hidden;text-overflow:ellipsis;}

/* ── TOOLBAR ── */
.dv-toolbar{
  display:flex;align-items:center;gap:.6rem;
  padding:.6rem 1.25rem;background:var(--d-surface);
  border-bottom:1px solid var(--d-border);flex-wrap:wrap;
}
.dv-toolbar-div{width:1px;height:20px;background:var(--d-border);flex-shrink:0;}
.dv-toolbar-summary{display:flex;align-items:center;gap:.45rem;min-width:0;color:var(--d-muted);font-size:.75rem;font-weight:600;}
.dv-selection-actions{display:flex;align-items:center;gap:.4rem;margin-left:auto;}
.dv-selection-status{font-size:.75rem;font-weight:700;color:var(--d-blue);white-space:nowrap;}

/* ── DROP ZONE ── */
.dv-dropzone{
  margin:.85rem 1.25rem 0;border:2px dashed var(--d-blue);
  border-radius:var(--d-radius);background:var(--d-blue-lt);
  padding:1.75rem;text-align:center;cursor:pointer;
  transition:all .15s;display:none;
}
.dv-dropzone.is-open{display:block;}
.dv-dropzone.drag-over{background:#d2e3fc;border-color:var(--d-blue-dk);}
.dv-dropzone.is-busy{cursor:wait;opacity:.72;pointer-events:none;}
.dv-dz-icon{font-size:2.2rem;color:var(--d-blue);margin-bottom:.5rem;}
.dv-dz-text{font-size:.88rem;font-weight:700;color:var(--d-blue);margin:0 0 .2rem;}
.dv-dz-hint{font-size:.74rem;color:var(--d-muted);margin:0;}
#dvFileInput{display:none;}

.dv-progress{margin:.5rem 1.25rem;display:none;}
.dv-progress-bar{height:5px;border-radius:99px;background:var(--d-border);overflow:hidden;}
.dv-progress-fill{height:100%;background:var(--d-blue);border-radius:99px;transition:width .2s;}
.dv-progress-lbl{font-size:.74rem;font-weight:700;color:var(--d-blue);margin-bottom:.3rem;}
.dv-progress.is-validating .dv-progress-fill{background:#d97706;animation:dvProgressPulse 1s ease-in-out infinite alternate;}
.dv-progress.is-validating .dv-progress-lbl{color:#a16207;}
.dv-progress.is-error .dv-progress-fill{background:#dc2626;animation:none;}
.dv-progress.is-error .dv-progress-lbl{color:#b91c1c;}
@keyframes dvProgressPulse{from{opacity:.62;}to{opacity:1;}}

/* ── FLASH ── */
.dv-flash{display:flex;align-items:center;gap:.5rem;margin:.75rem 1.25rem;padding:.6rem 1rem;border-radius:var(--d-radius);font-size:.82rem;font-weight:600;}
.dv-flash.ok{background:#f0fdf4;border:1px solid #86efac;color:#166534;}
.dv-flash.err{background:#fff1f2;border:1px solid #fca5a5;color:#991b1b;}

/* Executive pipeline summary */
.dv-summary{margin:.85rem 1.25rem 0;padding:1rem;background:#fff;border:1px solid var(--d-border);border-left:4px solid #0f5eb8;border-radius:8px;box-shadow:var(--d-shadow);}
.dv-summary-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:.8rem;}
.dv-summary-title{margin:0;color:#0b2d52;font-size:1rem;font-weight:800;}
.dv-summary-copy{margin:.18rem 0 0;color:var(--d-muted);font-size:.75rem;line-height:1.45;}
.dv-summary-updated{display:flex;align-items:center;gap:.45rem;color:var(--d-muted);font-size:.7rem;font-weight:700;white-space:nowrap;}
.dv-summary-refresh{width:32px;height:32px;border:1px solid #cbd8e6;background:#fff;color:#0f5eb8;border-radius:6px;cursor:pointer;}
.dv-summary-refresh:disabled{opacity:.55;cursor:wait;}
.dv-summary-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem;margin-bottom:.8rem;}
.dv-summary-metric{min-width:0;padding:.7rem .8rem;border:1px solid #dce5ee;border-top:3px solid #0f5eb8;background:#f8fbff;border-radius:6px;}
.dv-summary-metric.is-good{border-top-color:#0f9f75;}.dv-summary-metric.is-progress{border-top-color:#16a0bd;}
.dv-summary-metric span{display:block;color:#64748b;font-size:.66rem;font-weight:800;text-transform:uppercase;}
.dv-summary-metric strong{display:block;margin-top:.22rem;color:#10233d;font-size:1.25rem;line-height:1;font-weight:850;font-variant-numeric:tabular-nums;}
.dv-summary-panel{min-width:0;border:1px solid #dce5ee;border-radius:6px;overflow:hidden;background:#fff;}
.dv-summary-panel-title{display:flex;align-items:center;justify-content:space-between;gap:.6rem;margin:0;padding:.58rem .72rem;background:#edf4fb;color:#163a61;font-size:.72rem;font-weight:800;}
.dv-summary-table-wrap{width:100%;overflow-x:auto;}
.dv-summary-table{width:100%;min-width:460px;border-collapse:collapse;font-size:.73rem;}
.dv-summary-table th,.dv-summary-table td{padding:.52rem .65rem;border-bottom:1px solid #e5ebf2;text-align:right;white-space:nowrap;}
.dv-summary-table th{background:#f8fafc;color:#5b6d82;font-size:.65rem;text-transform:uppercase;}
.dv-summary-table th:first-child,.dv-summary-table td:first-child{text-align:left;font-weight:750;color:#18334f;}
.dv-summary-table tr:last-child td{border-bottom:0;}
.dv-progress-cell{display:flex;align-items:center;justify-content:flex-end;gap:.45rem;}
.dv-progress-track{width:70px;height:6px;background:#dfe8f1;border-radius:99px;overflow:hidden;}
.dv-progress-track i{display:block;height:100%;background:#0f9f75;border-radius:inherit;}
.dv-summary-note{margin:.7rem 0 0;color:#64748b;font-size:.67rem;line-height:1.45;}
.dv-summary-error{padding:1rem;color:#9f1239;background:#fff1f2;font-size:.75rem;font-weight:700;}
.dv-card.is-dragging,.dv-list-row.is-dragging{opacity:.4;}
.dv-card.is-drop-target,.dv-list-row.is-drop-target,.dv-breadcrumb.is-drop-target{outline:3px solid rgba(15,94,184,.28);outline-offset:-3px;background:#eaf4ff;}

/* ── CONTENT ── */
.dv-content{padding:1rem 1.25rem 2rem;flex:1;}
.dv-sec-lbl{font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--d-muted);margin:.1rem 0 .55rem .1rem;}

/* ── GRID ── */
.dv-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:.7rem;margin-bottom:1.5rem;}
.dv-card{
  background:var(--d-surface);border:1.5px solid var(--d-border);
  border-radius:var(--d-radius);padding:1rem .85rem .8rem;
  cursor:default;position:relative;display:flex;flex-direction:column;
  align-items:center;gap:.45rem;text-align:center;
  transition:box-shadow .15s,border-color .15s;user-select:none;min-height:118px;
}
.dv-card:hover{box-shadow:var(--d-shadow-md);border-color:#c5cde4;}
.dv-card.selected{border-color:var(--d-blue);background:var(--d-blue-lt);box-shadow:0 0 0 3px rgba(26,115,232,.12);}
.dv-item-check{display:inline-flex;align-items:center;justify-content:center;margin:0;cursor:pointer;}
.dv-item-check input{width:17px;height:17px;margin:0;accent-color:var(--d-blue);cursor:pointer;}
.dv-card-check{position:absolute;top:.62rem;left:.62rem;z-index:2;}
.dv-card-icon{font-size:2.5rem;line-height:1;}
.dv-card-name{font-size:.76rem;font-weight:700;color:var(--d-text);word-break:break-word;line-height:1.3;max-width:100%;}
.dv-card-meta{font-size:.67rem;color:var(--d-muted);}
.dv-card-more{position:absolute;top:.35rem;right:.35rem;opacity:0;transition:opacity .15s;}
.dv-card:hover .dv-card-more,.dv-card.ctx-open .dv-card-more{opacity:1;}
@media (hover:none), (pointer:coarse){
  .dv-card-more{opacity:1;}
}

/* ── LIST ── */
.dv-list{border:1px solid var(--d-border);border-radius:var(--d-radius);overflow:hidden;margin-bottom:1.5rem;}
.dv-list-head,.dv-list-row{display:grid;grid-template-columns:2rem 1fr 7rem 9rem 5rem;gap:.75rem;padding:.55rem 1rem;align-items:center;}
.dv-list-head{background:#f8fafc;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--d-muted);border-bottom:1px solid var(--d-border);}
.dv-list-row{background:var(--d-surface);border-bottom:1px solid var(--d-border);transition:background .1s;cursor:default;}
.dv-list-row:last-child{border-bottom:none;}
.dv-list-row:hover{background:var(--d-hover);}
.dv-list-row.selected{background:var(--d-blue-lt);}
.dv-list-leading{display:flex;align-items:center;justify-content:center;min-width:0;}
.dv-list-name{font-size:.8rem;font-weight:600;color:var(--d-text);display:flex;align-items:center;gap:.45rem;overflow:hidden;}
.dv-list-name span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.dv-list-cell{font-size:.75rem;color:var(--d-muted);}
.dv-list-acts{display:flex;justify-content:flex-end;gap:.25rem;}

/* ── EMPTY ── */
.dv-empty{text-align:center;padding:4rem 2rem;}
.dv-empty-icon{font-size:3.5rem;color:#dde3ec;margin-bottom:1rem;}
.dv-empty h3{font-size:1rem;font-weight:700;color:#94a3b8;margin-bottom:.35rem;}
.dv-empty p{font-size:.83rem;color:var(--d-muted);}

/* ── CONTEXT MENU ── */
.dv-ctx{
  position:fixed;z-index:9000;min-width:215px;
  background:var(--d-surface);border:1px solid var(--d-border);
  border-radius:var(--d-radius);box-shadow:var(--d-shadow-lg);
  padding:.35rem;display:none;
}
.dv-ctx.is-open{display:block;animation:ctxIn .12s ease;}
@keyframes ctxIn{from{opacity:0;transform:scale(.96) translateY(-4px);}to{opacity:1;transform:none;}}
.dv-ctx-item{display:flex;align-items:center;gap:.65rem;padding:.52rem .8rem;border-radius:8px;font-size:.82rem;font-weight:600;color:var(--d-text);cursor:pointer;transition:background .1s;user-select:none;}
.dv-ctx-item:hover{background:var(--d-hover);}
.dv-ctx-item i{width:1.1rem;text-align:center;color:var(--d-muted);font-size:.82rem;}
.dv-ctx-item.danger{color:#dc2626;}
.dv-ctx-item.danger i{color:#dc2626;}
.dv-ctx-sep{height:1px;background:var(--d-border);margin:.3rem 0;}

/* ── MODALS ── */
.dv-overlay{
  position:fixed;inset:0;background:rgba(15,23,42,.55);
  z-index:9100;display:none;align-items:center;justify-content:center;
  backdrop-filter:blur(3px);padding:1rem;
}
.dv-overlay.is-open{display:flex;}
.dv-modal{background:var(--d-surface);border-radius:16px;box-shadow:var(--d-shadow-lg);width:100%;}
.dv-overlay.is-open .dv-modal{animation:modalIn .18s ease;}
@keyframes modalIn{from{opacity:0;transform:translateY(12px) scale(.97);}to{opacity:1;transform:none;}}
.dv-modal-sm{max-width:420px;}.dv-modal-md{max-width:560px;}.dv-modal-lg{max-width:1100px;}
.dv-modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--d-border);}
.dv-modal-title{font-size:.95rem;font-weight:800;color:var(--d-text);margin:0;display:flex;align-items:center;gap:.5rem;}
.dv-modal-body{padding:1.2rem;}
.dv-modal-footer{display:flex;justify-content:flex-end;gap:.5rem;padding:.85rem 1.25rem;border-top:1px solid var(--d-border);}
.dv-preview-body{min-height:300px;background:#f1f3f4;display:flex;align-items:center;justify-content:center;max-height:72vh;overflow:auto;}
.dv-preview-body iframe{width:100%;height:68vh;border:none;display:block;}
.dv-preview-body img{max-width:100%;max-height:68vh;object-fit:contain;border-radius:6px;display:block;margin:auto;}

/* ── FORM ── */
.dv-lbl{display:block;font-size:.78rem;font-weight:700;color:var(--d-text);margin-bottom:.35rem;}
.dv-inp,.dv-sel{width:100%;padding:.55rem .85rem;border:1.5px solid var(--d-border);border-radius:8px;font-size:.88rem;color:var(--d-text);outline:none;transition:border-color .15s,box-shadow .15s;background:#fff;}
.dv-inp:focus,.dv-sel:focus{border-color:var(--d-blue);box-shadow:0 0 0 3px rgba(26,115,232,.12);}
.dv-inp-grp{margin-bottom:.9rem;}

/* ── FOLDER TREE ── */
.dv-tree{border:1px solid var(--d-border);border-radius:8px;max-height:260px;overflow-y:auto;}
.dv-tree-item{display:flex;align-items:center;gap:.5rem;padding:.5rem .85rem;cursor:pointer;font-size:.82rem;font-weight:600;color:var(--d-text);border-bottom:1px solid var(--d-border);transition:background .1s;}
.dv-tree-item:last-child{border-bottom:none;}
.dv-tree-item:hover{background:var(--d-hover);}
.dv-tree-item.sel{background:var(--d-blue-lt);color:var(--d-blue);}
.dv-tree-item i.fa-folder{color:#f6c341;}

.dv-trash-badge{display:inline-flex;align-items:center;justify-content:center;background:#dc2626;color:#fff;border-radius:99px;font-size:.65rem;font-weight:800;min-width:18px;height:18px;padding:0 5px;margin-left:.2rem;}

.drive-swal-popup{border:1px solid var(--d-border);border-radius:10px!important;box-shadow:var(--d-shadow-lg)!important;padding:1.1rem!important;}
.drive-swal-title{color:var(--d-text)!important;font-size:1.2rem!important;font-weight:800!important;letter-spacing:0!important;}
.drive-swal-html{color:var(--d-muted)!important;font-size:.88rem!important;line-height:1.55!important;}
.drive-swal-confirm,.drive-swal-cancel{min-height:38px;border:0;border-radius:6px;padding:.55rem .95rem;font-size:.82rem;font-weight:700;}
.drive-swal-confirm{background:var(--d-blue);color:#fff;}
.drive-swal-confirm-danger{background:#b91c1c;color:#fff;}
.drive-swal-cancel{background:#e2e8f0;color:#334155;}

@media(max-width:640px){
  .dv-summary{margin:.7rem .7rem 0;padding:.75rem;}
  .dv-summary-head{align-items:center;}
  .dv-summary-updated span{display:none;}
  .dv-summary-metrics{grid-template-columns:1fr;}
  .dv-grid{grid-template-columns:repeat(auto-fill,minmax(128px,1fr));}
  .dv-list-head,.dv-list-row{grid-template-columns:2rem 1fr 5rem;}
  .dv-list-head>:nth-child(n+4),.dv-list-row>:nth-child(n+4){display:none;}
  .dv-selection-actions{width:100%;margin-left:0;}
  .dv-selection-actions .btn-dv{flex:1;justify-content:center;}
}
</style>

<div class="dv-wrap">

{{-- ══ TOP BAR ══ --}}
<div class="dv-topbar">
  <div class="dv-brand">
    <div class="dv-brand-logo"><i class="fas fa-hdd"></i></div>
    <div>
      <p class="dv-brand-name">Bank Pipeline</p>
      <p class="dv-brand-sub">Workspace tindak lanjut pipeline Area 6</p>
    </div>
  </div>
  <div class="dv-topbar-right">
    @if(auth()->user()->isAdmin())
      <button class="btn-dv btn-dv-primary" id="btnNewFolder"><i class="fas fa-folder-plus"></i> Folder Baru</button>
      <button class="btn-dv btn-dv-primary" id="btnUpload"><i class="fas fa-upload"></i> Upload</button>
      <button class="btn-dv {{ $trashedCount > 0 ? 'btn-dv-danger' : 'btn-dv-ghost' }}" id="btnTrash">
        <i class="fas fa-trash-alt"></i> Sampah
        @if($trashedCount > 0)<span class="dv-trash-badge">{{ $trashedCount }}</span>@endif
      </button>
    @endif
    <button class="btn-dv btn-dv-ghost btn-dv-icon" id="btnGrid" title="Grid view (G)"><i class="fas fa-th"></i></button>
    <button class="btn-dv btn-dv-ghost btn-dv-icon" id="btnList" title="List view (L)"><i class="fas fa-list"></i></button>
  </div>
</div>

{{-- ══ BREADCRUMB ══ --}}
<div class="dv-breadcrumb" data-drop-folder-id="{{ $folderId ?? '' }}">
  <a href="{{ route('drive.index') }}"><i class="fas fa-stream" style="margin-right:.3rem;font-size:.8rem;"></i>Bank Pipeline</a>
  @foreach($breadcrumbs as $crumb)
    <span class="sep"><i class="fas fa-chevron-right" style="font-size:.6rem;color:#cbd5e1;margin:0 .15rem;"></i></span>
    @if(!$loop->last)
      <a href="{{ route('drive.index', $crumb->id) }}">{{ $crumb->name }}</a>
    @else
      <span class="current">{{ $crumb->name }}</span>
    @endif
  @endforeach
</div>

{{-- ══ TOOLBAR ══ --}}
<div class="dv-toolbar">
  <div class="dv-toolbar-summary">
    <i class="fas fa-folder-open" aria-hidden="true"></i>
    <span>{{ $folders->count() }} folder &middot; {{ $files->count() }} file</span>
  </div>
  @if(auth()->user()->isAdmin() && ($folders->isNotEmpty() || $files->isNotEmpty()))
    <div class="dv-selection-actions">
      <span class="dv-selection-status" id="dvSelectionStatus" aria-live="polite">0 dipilih</span>
      <button type="button" class="btn-dv btn-dv-secondary" id="btnSelectAll">
        <i class="fas fa-check-double" aria-hidden="true"></i>
        <span id="dvSelectAllLabel">Pilih semua</span>
      </button>
      <button type="button" class="btn-dv btn-dv-danger" id="btnDeleteSelected" disabled>
        <i class="fas fa-trash-alt" aria-hidden="true"></i>
        <span>Hapus</span>
      </button>
    </div>
  @endif
</div>

{{-- ══ FLASH ══ --}}
@if(session('success'))
  <div class="dv-flash ok" id="dvFlash"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
  <div class="dv-flash err" id="dvFlash2"><i class="fas fa-times-circle"></i> {{ session('error') }}</div>
@endif
@if($errors->any())
  <div class="dv-flash err" id="dvValidationErrors">
    <i class="fas fa-exclamation-circle"></i>
    <span>{{ $errors->first() }}</span>
  </div>
@endif

{{-- ══ UPLOAD ZONE ══ --}}
<section class="dv-summary" id="bankPipelineSummary"
  data-summary-url="{{ route('drive.pipeline-summary') }}" aria-labelledby="bankPipelineSummaryTitle">
  <div class="dv-summary-head">
    <div>
      <h2 class="dv-summary-title" id="bankPipelineSummaryTitle">Summary Pipeline Area 6</h2>
      <p class="dv-summary-copy">KC Madiun, KC Magetan, KC Ngawi, dan KC Ponorogo.</p>
    </div>
    <div class="dv-summary-updated">
      <span id="pipelineSummaryUpdated">Memuat data...</span>
      <button type="button" class="dv-summary-refresh" id="pipelineSummaryRefresh"
        title="Muat ulang ringkasan" aria-label="Muat ulang ringkasan">
        <i class="fas fa-sync-alt" aria-hidden="true"></i>
      </button>
    </div>
  </div>
  <div class="dv-summary-metrics" aria-live="polite">
    <div class="dv-summary-metric"><span>Jumlah pipeline</span><strong id="pipelineTotal">--</strong></div>
    <div class="dv-summary-metric is-good"><span>Pipeline sudah TL</span><strong id="pipelineFollowed">--</strong></div>
    <div class="dv-summary-metric is-progress"><span>Persentase TL</span><strong id="pipelineFollowUpPercentage">--</strong></div>
  </div>
  <div class="dv-summary-panel">
    <h3 class="dv-summary-panel-title"><span>Rekap per kantor cabang</span><span id="pipelineBranchCoverage">4 cabang</span></h3>
    <div class="dv-summary-table-wrap">
      <table class="dv-summary-table">
        <thead><tr><th>Cabang</th><th>Jumlah pipeline</th><th>Sudah TL</th><th>Persentase TL</th></tr></thead>
        <tbody id="pipelineBranchRows">
          <tr><td colspan="4">Membaca workbook pipeline...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <p class="dv-summary-note" id="pipelineSummaryNote">
    Persentase TL dihitung dari pipeline sudah TL dibagi seluruh jumlah pipeline Area 6.
  </p>
</section>

@if(auth()->user()->isAdmin())
  <div class="dv-dropzone" id="dvDropzone">
    <div class="dv-dz-icon"><i class="fas fa-upload"></i></div>
    <p class="dv-dz-text">Seret & lepas file di sini, atau <u>klik untuk memilih</u></p>
    <p class="dv-dz-hint">Semua format didukung &middot; Maks 50 MB per file</p>
  </div>
  <div class="dv-progress" id="dvProgress" role="status" aria-live="polite">
    <p class="dv-progress-lbl" id="dvProgressLbl">Mengunggah...</p>
    <div class="dv-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
      <div class="dv-progress-fill" id="dvProgressFill" style="width:0%"></div>
    </div>
  </div>
  <form id="dvUploadForm" method="POST" action="{{ route('drive.upload') }}" enctype="multipart/form-data" style="display:none;">
    @csrf
    <input type="hidden" name="folder_id" value="{{ $folderId }}">
    <input type="file" id="dvFileInput" name="files[]" multiple
      accept=".xlsx,.xls,.csv,.pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.docx,.doc,.word,.pptx,.ppt">
  </form>
@endif

{{-- ══ CONTENT ══ --}}
<div class="dv-content">
  @if($folders->isEmpty() && $files->isEmpty())
    <div class="dv-empty">
      <div class="dv-empty-icon"><i class="fas fa-folder-open"></i></div>
      <h3>Folder ini masih kosong</h3>
      <p>{{ auth()->user()->isAdmin() ? 'Buat folder baru atau upload file untuk memulai.' : 'Belum ada file di sini.' }}</p>
    </div>
  @else

    {{-- GRID VIEW --}}
    <div id="viewGrid">
      @if($folders->isNotEmpty())
        <div class="dv-sec-lbl">Folder</div>
        <div class="dv-grid" id="gridFolders">
          @foreach($folders as $folder)
          <div class="dv-card" data-type="folder" data-id="{{ $folder->id }}" data-name="{{ $folder->name }}"
            @if(auth()->user()->isAdmin()) draggable="true" data-drop-folder-id="{{ $folder->id }}" @endif
            data-select-key="folder:{{ $folder->id }}"
            data-open-url="{{ route('drive.index', $folder->id) }}"
            data-rename-url="{{ route('drive.folder.rename', $folder) }}"
            data-move-url="{{ route('drive.folder.move', $folder) }}"
            data-delete-url="{{ route('drive.folder.delete', $folder) }}"
            role="button" tabindex="0" aria-label="Buka folder {{ $folder->name }}"
            ondblclick="openItem(this)"
            onclick="selectCard(this,event)"
            @if(auth()->user()->isAdmin()) oncontextmenu="openCtx(event,this)" @endif>
            @if(auth()->user()->isAdmin())
            <label class="dv-item-check dv-card-check" onclick="event.stopPropagation()">
              <input type="checkbox" class="dv-select-checkbox"
                aria-label="Pilih folder {{ $folder->name }}"
                onchange="toggleSelectionFromCheckbox(this)">
            </label>
            <button class="btn-dv btn-dv-ghost btn-dv-icon dv-card-more"
              onclick="event.stopPropagation();openCtx(event,this.closest('.dv-card'))"
              title="Opsi lainnya"><i class="fas fa-ellipsis-v"></i></button>
            @endif
            <div class="dv-card-icon" style="color:#f6c341;"><i class="fas fa-folder"></i></div>
            <div class="dv-card-name">{{ $folder->name }}</div>
            <div class="dv-card-meta">Folder</div>
          </div>
          @endforeach
        </div>
      @endif
      @if($files->isNotEmpty())
        <div class="dv-sec-lbl">File</div>
        <div class="dv-grid" id="gridFiles">
          @foreach($files as $file)
          @php $ic = $file->iconInfo(); @endphp
          <div class="dv-card" data-type="file" data-id="{{ $file->id }}" data-name="{{ $file->original_name }}"
            @if(auth()->user()->isAdmin()) draggable="true" @endif
            data-select-key="file:{{ $file->id }}"
            data-mode="{{ $file->openMode() }}"
            data-office-url="{{ route('drive.file.office-editor', $file) }}"
            data-editor-url="{{ route('drive.file.editor', $file) }}"
            data-preview-url="{{ route('drive.file.preview', $file) }}"
            data-document-url="{{ route('drive.file.document-preview', $file) }}"
            data-download-url="{{ route('drive.file.download', $file) }}"
            data-rename-url="{{ route('drive.file.rename', $file) }}"
            data-move-url="{{ route('drive.file.move', $file) }}"
            data-copy-url="{{ route('drive.file.copy', $file) }}"
            data-delete-url="{{ route('drive.file.delete', $file) }}"
            role="button" tabindex="0" aria-label="Buka file {{ $file->original_name }}"
            ondblclick="openItem(this)"
            onclick="selectCard(this,event)"
            @if(auth()->user()->isAdmin()) oncontextmenu="openCtx(event,this)" @endif>
            @if(auth()->user()->isAdmin())
            <label class="dv-item-check dv-card-check" onclick="event.stopPropagation()">
              <input type="checkbox" class="dv-select-checkbox"
                aria-label="Pilih file {{ $file->original_name }}"
                onchange="toggleSelectionFromCheckbox(this)">
            </label>
            <button class="btn-dv btn-dv-ghost btn-dv-icon dv-card-more"
              onclick="event.stopPropagation();openCtx(event,this.closest('.dv-card'))"
              title="Opsi lainnya"><i class="fas fa-ellipsis-v"></i></button>
            @endif
            <div class="dv-card-icon" style="color:{{ $ic['color'] }};"><i class="{{ $ic['icon'] }}"></i></div>
            <div class="dv-card-name">{{ $file->original_name }}</div>
            <div class="dv-card-meta">{{ $file->humanSize() }}</div>
          </div>
          @endforeach
        </div>
      @endif
    </div>

    {{-- LIST VIEW --}}
    <div id="viewList" style="display:none;">
      <div class="dv-list">
        <div class="dv-list-head">
          <div></div><div>Nama</div><div>Ukuran</div><div>Diunggah oleh</div><div>Aksi</div>
        </div>
        @foreach($folders as $folder)
        <div class="dv-list-row" data-type="folder" data-id="{{ $folder->id }}" data-name="{{ $folder->name }}"
          @if(auth()->user()->isAdmin()) draggable="true" data-drop-folder-id="{{ $folder->id }}" @endif
          data-select-key="folder:{{ $folder->id }}"
          data-open-url="{{ route('drive.index', $folder->id) }}"
          data-rename-url="{{ route('drive.folder.rename', $folder) }}"
          data-move-url="{{ route('drive.folder.move', $folder) }}"
          data-delete-url="{{ route('drive.folder.delete', $folder) }}"
          role="button" tabindex="0" aria-label="Buka folder {{ $folder->name }}"
          ondblclick="openItem(this)"
          onclick="selectRow(this,event)"
          @if(auth()->user()->isAdmin()) oncontextmenu="openCtx(event,this)" @endif>
          <div class="dv-list-leading">
            @if(auth()->user()->isAdmin())
              <label class="dv-item-check" onclick="event.stopPropagation()">
                <input type="checkbox" class="dv-select-checkbox"
                  aria-label="Pilih folder {{ $folder->name }}"
                  onchange="toggleSelectionFromCheckbox(this)">
              </label>
            @else
              <i class="fas fa-folder" style="color:#f6c341;" aria-hidden="true"></i>
            @endif
          </div>
          <div class="dv-list-name">
            @if(auth()->user()->isAdmin())<i class="fas fa-folder" style="color:#f6c341;" aria-hidden="true"></i>@endif
            <span>{{ $folder->name }}</span>
          </div>
          <div class="dv-list-cell">—</div>
          <div class="dv-list-cell">{{ optional($folder->creator)->name ?? '—' }}</div>
          <div class="dv-list-acts" onclick="event.stopPropagation()">
            @if(auth()->user()->isAdmin())
              <button class="btn-dv btn-dv-ghost btn-dv-icon" onclick="openCtx(event,this.closest('.dv-list-row'))" title="Opsi"><i class="fas fa-ellipsis-v"></i></button>
            @endif
          </div>
        </div>
        @endforeach
        @foreach($files as $file)
        @php $ic = $file->iconInfo(); @endphp
        <div class="dv-list-row" data-type="file" data-id="{{ $file->id }}" data-name="{{ $file->original_name }}"
          @if(auth()->user()->isAdmin()) draggable="true" @endif
          data-select-key="file:{{ $file->id }}"
          data-mode="{{ $file->openMode() }}"
          data-office-url="{{ route('drive.file.office-editor', $file) }}"
          data-editor-url="{{ route('drive.file.editor', $file) }}"
          data-preview-url="{{ route('drive.file.preview', $file) }}"
          data-document-url="{{ route('drive.file.document-preview', $file) }}"
          data-download-url="{{ route('drive.file.download', $file) }}"
          data-rename-url="{{ route('drive.file.rename', $file) }}"
          data-move-url="{{ route('drive.file.move', $file) }}"
          data-copy-url="{{ route('drive.file.copy', $file) }}"
          data-delete-url="{{ route('drive.file.delete', $file) }}"
          role="button" tabindex="0" aria-label="Buka file {{ $file->original_name }}"
          ondblclick="openItem(this)"
          onclick="selectRow(this,event)"
          @if(auth()->user()->isAdmin()) oncontextmenu="openCtx(event,this)" @endif>
          <div class="dv-list-leading">
            @if(auth()->user()->isAdmin())
              <label class="dv-item-check" onclick="event.stopPropagation()">
                <input type="checkbox" class="dv-select-checkbox"
                  aria-label="Pilih file {{ $file->original_name }}"
                  onchange="toggleSelectionFromCheckbox(this)">
              </label>
            @else
              <i class="{{ $ic['icon'] }}" style="color:{{ $ic['color'] }};" aria-hidden="true"></i>
            @endif
          </div>
          <div class="dv-list-name">
            @if(auth()->user()->isAdmin())<i class="{{ $ic['icon'] }}" style="color:{{ $ic['color'] }};" aria-hidden="true"></i>@endif
            <span>{{ $file->original_name }}</span>
          </div>
          <div class="dv-list-cell">{{ $file->humanSize() }}</div>
          <div class="dv-list-cell">{{ optional($file->uploader)->name ?? '—' }}</div>
          <div class="dv-list-acts" onclick="event.stopPropagation()">
            @if(auth()->user()->isAdmin())
              <button class="btn-dv btn-dv-ghost btn-dv-icon" onclick="openCtx(event,this.closest('.dv-list-row'))" title="Opsi"><i class="fas fa-ellipsis-v"></i></button>
            @endif
          </div>
        </div>
        @endforeach
      </div>
    </div>

  @endif
</div>
</div>

{{-- ══ CONTEXT MENU ══ --}}
<div class="dv-ctx" id="dvCtx">
  <div class="dv-ctx-item" id="ctxOpen"><i class="fas fa-external-link-alt"></i> Buka</div>
  <div class="dv-ctx-item" id="ctxPreview"><i class="fas fa-eye"></i> Preview</div>
  <div class="dv-ctx-item" id="ctxDownload"><i class="fas fa-download"></i> Download</div>
  <div class="dv-ctx-sep" id="ctxAdminSep"></div>
  <div class="dv-ctx-item" id="ctxRename"><i class="fas fa-pen"></i> Rename</div>
  <div class="dv-ctx-item" id="ctxMove"><i class="fas fa-folder-open"></i> Pindahkan ke...</div>
  <div class="dv-ctx-item" id="ctxCopy"><i class="fas fa-copy"></i> Salin ke...</div>
  <div class="dv-ctx-sep" id="ctxDangerSep"></div>
  <div class="dv-ctx-item danger" id="ctxDelete"><i class="fas fa-trash-alt"></i> Hapus ke Sampah</div>
</div>

{{-- ══ MODAL: New Folder ══ --}}
<div class="dv-overlay" id="modalFolder">
  <div class="dv-modal dv-modal-sm">
    <div class="dv-modal-hdr">
      <h3 class="dv-modal-title"><i class="fas fa-folder-plus" style="color:#f6c341;"></i> Folder Baru</h3>
      <button class="btn-dv btn-dv-ghost btn-dv-icon" onclick="closeModal('modalFolder')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="{{ route('drive.folder.store') }}">
      @csrf
      <input type="hidden" name="parent_id" value="{{ $folderId }}">
      <div class="dv-modal-body">
        <label class="dv-lbl" for="newFolderName">Nama Folder</label>
        <input class="dv-inp" type="text" id="newFolderName" name="name" placeholder="Contoh: Laporan Q3 2026" required maxlength="255">
      </div>
      <div class="dv-modal-footer">
        <button type="button" class="btn-dv btn-dv-ghost" onclick="closeModal('modalFolder')">Batal</button>
        <button type="submit" class="btn-dv btn-dv-primary"><i class="fas fa-check"></i> Buat</button>
      </div>
    </form>
  </div>
</div>

{{-- ══ MODAL: Rename ══ --}}
<div class="dv-overlay" id="modalRename">
  <div class="dv-modal dv-modal-sm">
    <div class="dv-modal-hdr">
      <h3 class="dv-modal-title" id="renameMTitle"><i class="fas fa-pen" style="color:var(--d-blue);"></i> Rename</h3>
      <button class="btn-dv btn-dv-ghost btn-dv-icon" onclick="closeModal('modalRename')"><i class="fas fa-times"></i></button>
    </div>
    <form id="renameForm" method="POST" action="">
      @csrf @method('PATCH')
      <div class="dv-modal-body">
        <label class="dv-lbl">Nama Baru</label>
        <input class="dv-inp" type="text" id="renameInp" name="name" required maxlength="255">
      </div>
      <div class="dv-modal-footer">
        <button type="button" class="btn-dv btn-dv-ghost" onclick="closeModal('modalRename')">Batal</button>
        <button type="submit" class="btn-dv btn-dv-primary"><i class="fas fa-check"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- ══ MODAL: Move/Copy ══ --}}
<div class="dv-overlay" id="modalMoveCopy">
  <div class="dv-modal dv-modal-md">
    <div class="dv-modal-hdr">
      <h3 class="dv-modal-title" id="mcTitle"><i class="fas fa-folder-open" style="color:#f6c341;"></i> Pindahkan ke</h3>
      <button class="btn-dv btn-dv-ghost btn-dv-icon" onclick="closeModal('modalMoveCopy')"><i class="fas fa-times"></i></button>
    </div>
    <form id="mcForm" method="POST" action="">
      @csrf @method('PATCH')
      <input type="hidden" name="action" id="mcAction" value="move">
      <input type="hidden" name="destination_folder_id" id="mcDestId" value="">
      <div class="dv-modal-body">
        <p style="font-size:.8rem;color:var(--d-muted);margin:0 0 .75rem;">Pilih folder tujuan. Klik <strong>Root Bank Pipeline</strong> untuk memindahkan ke halaman utama.</p>
        <div class="dv-tree" id="folderTree">
          <div class="dv-tree-item sel" data-id="" onclick="selectTree(this,'')">
            <i class="fas fa-hdd" style="color:var(--d-blue);font-size:.9rem;"></i> Root Bank Pipeline
          </div>
          @foreach($allFolders as $f)
          <div class="dv-tree-item" data-id="{{ $f->id }}" onclick="selectTree(this,{{ $f->id }})">
            <span style="display:inline-block;width:{{ $f->depth * 1.1 }}rem;"></span>
            <i class="fas fa-folder"></i> {{ $f->name }}
          </div>
          @endforeach
        </div>
      </div>
      <div class="dv-modal-footer">
        <button type="button" class="btn-dv btn-dv-ghost" onclick="closeModal('modalMoveCopy')">Batal</button>
        <button type="submit" class="btn-dv btn-dv-primary" id="mcSubmitBtn"><i class="fas fa-check"></i> Pindahkan</button>
      </div>
    </form>
  </div>
</div>

{{-- ══ MODAL: Preview ══ --}}
<div class="dv-overlay" id="modalPreview">
  <div class="dv-modal dv-modal-lg">
    <div class="dv-modal-hdr">
      <h3 class="dv-modal-title" id="previewTitle" style="max-width:78%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.85rem;"></h3>
      <div style="display:flex;gap:.4rem;">
        <a class="btn-dv btn-dv-secondary" id="previewDlBtn" href="#" style="font-size:.76rem;padding:.38rem .7rem;"><i class="fas fa-download"></i> Download</a>
        <button class="btn-dv btn-dv-ghost btn-dv-icon" onclick="closeModal('modalPreview')"><i class="fas fa-times"></i></button>
      </div>
    </div>
    <div class="dv-preview-body" id="previewBody"></div>
  </div>
</div>

{{-- ══ MODAL: Trash ══ --}}
<div class="dv-overlay" id="modalTrash">
  <div class="dv-modal dv-modal-md">
    <div class="dv-modal-hdr">
      <h3 class="dv-modal-title"><i class="fas fa-trash-alt" style="color:#dc2626;"></i> Sampah</h3>
      <button class="btn-dv btn-dv-ghost btn-dv-icon" onclick="closeModal('modalTrash')"><i class="fas fa-times"></i></button>
    </div>
    <div class="dv-modal-body" style="padding:0;">
      @if($trashedFiles->isEmpty())
        <div style="text-align:center;padding:2.5rem;color:var(--d-muted);">
          <i class="fas fa-check-circle" style="font-size:2rem;color:#86efac;margin-bottom:.75rem;display:block;"></i>
          Tidak ada file di sampah.
        </div>
      @else
        @foreach($trashedFiles as $tf)
          @php $tic = $tf->iconInfo(); @endphp
          <div style="display:flex;align-items:center;gap:.75rem;padding:.7rem 1.25rem;border-bottom:1px solid var(--d-border);">
            <span style="color:{{ $tic['color'] }};font-size:1.3rem;flex-shrink:0;"><i class="{{ $tic['icon'] }}"></i></span>
            <div style="flex:1;min-width:0;">
              <div style="font-size:.82rem;font-weight:700;color:var(--d-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $tf->original_name }}</div>
              <div style="font-size:.7rem;color:var(--d-muted);">{{ $tf->humanSize() }} &middot; Dihapus {{ $tf->deleted_at->diffForHumans() }}</div>
            </div>
            <div style="display:flex;gap:.3rem;flex-shrink:0;">
              <form method="POST" action="{{ route('drive.file.restore', $tf->id) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn-dv btn-dv-secondary" style="font-size:.72rem;padding:.3rem .6rem;" title="Pulihkan"><i class="fas fa-undo"></i> Pulihkan</button>
              </form>
              <form method="POST" action="{{ route('drive.file.purge', $tf->id) }}"
                class="js-drive-confirm"
                data-confirm-title="Hapus permanen?"
                data-confirm-text="File {{ $tf->original_name }} akan dihapus permanen dan tidak dapat dipulihkan."
                data-confirm-button="Hapus permanen">
                @csrf @method('DELETE')
                <button type="submit" class="btn-dv btn-dv-danger" style="font-size:.72rem;padding:.3rem .6rem;" title="Hapus permanen"><i class="fas fa-times"></i></button>
              </form>
            </div>
          </div>
        @endforeach
        <div style="padding:.85rem 1.25rem;display:flex;justify-content:flex-end;border-top:1px solid var(--d-border);">
          <form method="POST" action="{{ route('drive.file.purge-all') }}"
            class="js-drive-confirm"
            data-confirm-title="Kosongkan Sampah?"
            data-confirm-text="Semua file di Sampah akan dihapus permanen dan tidak dapat dipulihkan."
            data-confirm-button="Kosongkan Sampah">
            @csrf @method('DELETE')
            <button type="submit" class="btn-dv btn-dv-danger"><i class="fas fa-fire-alt"></i> Kosongkan Sampah</button>
          </form>
        </div>
      @endif
    </div>
  </div>
</div>

{{-- ══ JAVASCRIPT ══ --}}
<script>
const IS_ADMIN = {{ auth()->user()->isAdmin() ? 'true' : 'false' }};
const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';

const pipelineNumber = new Intl.NumberFormat('id-ID');

function pipelineCell(row, value) {
  const cell = document.createElement('td');
  cell.textContent = value;
  row.appendChild(cell);
  return cell;
}

function renderPipelineSummary(payload) {
  const totals = payload?.totals || {};
  const followUpPercentage = totals.follow_up_percentage ?? totals.progress ?? 0;
  document.getElementById('pipelineTotal').textContent = pipelineNumber.format(totals.total || 0);
  document.getElementById('pipelineFollowed').textContent = pipelineNumber.format(totals.followed_up || 0);
  document.getElementById('pipelineFollowUpPercentage').textContent = `${Number(followUpPercentage).toFixed(1).replace('.', ',')}%`;
  document.getElementById('pipelineBranchCoverage').textContent = `${(payload.branches || []).length} cabang`;

  const generatedAt = payload?.generated_at ? new Date(payload.generated_at) : null;
  document.getElementById('pipelineSummaryUpdated').textContent = generatedAt && !Number.isNaN(generatedAt.getTime())
    ? `Diperbarui ${generatedAt.toLocaleString('id-ID', {dateStyle:'medium', timeStyle:'short'})}`
    : 'Ringkasan terbaru';

  const branchRows = document.getElementById('pipelineBranchRows');
  branchRows.replaceChildren();
  (payload.branches || []).forEach(branch => {
    const row = document.createElement('tr');
    pipelineCell(row, branch.name || branch.key || '-');
    pipelineCell(row, pipelineNumber.format(branch.total || 0));
    pipelineCell(row, pipelineNumber.format(branch.followed_up || 0));
    const branchFollowUpPercentage = branch.follow_up_percentage ?? branch.progress ?? 0;
    const progressCell = pipelineCell(row, '');
    progressCell.className = 'dv-progress-cell';
    const track = document.createElement('span');
    track.className = 'dv-progress-track';
    const fill = document.createElement('i');
    fill.style.width = `${Math.max(0, Math.min(100, Number(branchFollowUpPercentage)))}%`;
    track.appendChild(fill);
    const label = document.createElement('strong');
    label.textContent = `${Number(branchFollowUpPercentage).toFixed(1).replace('.', ',')}%`;
    progressCell.append(track, label);
    branchRows.appendChild(row);
  });
  if (!branchRows.children.length) {
    const row = document.createElement('tr');
    const cell = pipelineCell(row, 'Belum ada entri pipeline Area 6 yang dapat dipetakan.');
    cell.colSpan = 4;
    branchRows.appendChild(row);
  }

  const notes = [
    'Jumlah pipeline menghitung seluruh entri valid di empat KC Area 6; angka bukan nominal rupiah.',
    'Persentase TL = pipeline sudah TL / jumlah pipeline. Entri tanpa isi TL tetap masuk sebagai pipeline, tetapi tidak dihitung sudah TL.',
    totals.outside_scope ? `${pipelineNumber.format(totals.outside_scope)} entri cabang di luar Area 6 diabaikan.` : '',
    totals.unmapped ? `${pipelineNumber.format(totals.unmapped)} entri tanpa identitas cabang tidak dimasukkan.` : '',
    payload.warnings?.length ? `${payload.warnings.length} file perlu pemeriksaan struktur.` : ''
  ].filter(Boolean);
  document.getElementById('pipelineSummaryNote').textContent = notes.join(' ');
}

async function loadPipelineSummary() {
  const section = document.getElementById('bankPipelineSummary');
  const refresh = document.getElementById('pipelineSummaryRefresh');
  if (!section?.dataset.summaryUrl || refresh?.disabled) return;
  if (refresh) refresh.disabled = true;
  try {
    const response = await fetch(section.dataset.summaryUrl, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'Ringkasan pipeline gagal dimuat.');
    renderPipelineSummary(payload);
  } catch (error) {
    console.error(error);
    document.getElementById('pipelineSummaryUpdated').textContent = 'Gagal diperbarui';
    const rows = document.getElementById('pipelineBranchRows');
    const row = document.createElement('tr');
    const cell = pipelineCell(row, error.message || 'Ringkasan pipeline gagal dimuat.');
    cell.colSpan = 4;
    rows.replaceChildren(row);
  } finally {
    if (refresh) refresh.disabled = false;
  }
}

document.getElementById('pipelineSummaryRefresh')?.addEventListener('click', loadPipelineSummary);
loadPipelineSummary();

/* ── View Toggle ── */
function setView(v) {
  const grid = document.getElementById('viewGrid');
  const list = document.getElementById('viewList');
  if (!grid || !list) return;
  localStorage.setItem('drive_view', v);
  grid.style.display = v === 'grid' ? '' : 'none';
  list.style.display = v === 'list' ? '' : 'none';
  document.getElementById('btnGrid')?.classList.toggle('on', v === 'grid');
  document.getElementById('btnList')?.classList.toggle('on', v === 'list');
}
setView(localStorage.getItem('drive_view') || 'grid');
document.getElementById('btnGrid')?.addEventListener('click', () => setView('grid'));
document.getElementById('btnList')?.addEventListener('click', () => setView('list'));

/* ── Selection ── */
const driveSwalTheme = {
  customClass: {
    popup: 'drive-swal-popup',
    title: 'drive-swal-title',
    htmlContainer: 'drive-swal-html',
    confirmButton: 'drive-swal-confirm',
    cancelButton: 'drive-swal-cancel'
  },
  buttonsStyling: false
};

function driveSwal(options) {
  if (!window.Swal) {
    return Promise.reject(new Error('Komponen konfirmasi belum siap. Muat ulang halaman lalu coba kembali.'));
  }

  const configuredOptions = Object.assign({}, driveSwalTheme, options);
  configuredOptions.customClass = Object.assign(
    {},
    driveSwalTheme.customClass,
    options?.customClass || {}
  );

  return window.Swal.fire(configuredOptions);
}

const selectedDriveItems = new Map();
let driveClickSuppressedUntil = 0;

function isCoarsePointer() {
  return window.matchMedia?.('(hover: none), (pointer: coarse)').matches ?? false;
}

function driveItemFromElement(element) {
  const key = element?.dataset?.selectKey;
  if (!key) return null;

  return {
    key,
    type: element.dataset.type || '',
    id: element.dataset.id || '',
    name: element.dataset.name || '',
    deleteUrl: element.dataset.deleteUrl || ''
  };
}

function selectableDriveItems() {
  const items = new Map();
  document.querySelectorAll('[data-select-key]').forEach(element => {
    const item = driveItemFromElement(element);
    if (item && !items.has(item.key)) items.set(item.key, item);
  });
  return Array.from(items.values());
}

function elementsForSelectionKey(key) {
  return Array.from(document.querySelectorAll('[data-select-key]'))
    .filter(element => element.dataset.selectKey === key);
}

function syncSelectionKey(key) {
  const selected = selectedDriveItems.has(key);
  elementsForSelectionKey(key).forEach(element => {
    element.classList.toggle('selected', selected);
    element.querySelectorAll('.dv-select-checkbox').forEach(checkbox => {
      checkbox.checked = selected;
    });
  });
}

function updateSelectionToolbar() {
  const count = selectedDriveItems.size;
  const availableCount = selectableDriveItems().length;
  const allSelected = availableCount > 0 && count === availableCount;
  const status = document.getElementById('dvSelectionStatus');
  const deleteButton = document.getElementById('btnDeleteSelected');
  const selectAllButton = document.getElementById('btnSelectAll');
  const selectAllLabel = document.getElementById('dvSelectAllLabel');

  if (status) status.textContent = `${count} dipilih`;
  if (deleteButton) deleteButton.disabled = count === 0;
  if (selectAllButton) selectAllButton.setAttribute('aria-pressed', allSelected ? 'true' : 'false');
  if (selectAllLabel) selectAllLabel.textContent = allSelected ? 'Batalkan semua' : 'Pilih semua';
}

function setDriveItemSelected(element, selected) {
  const item = driveItemFromElement(element);
  if (!item || !IS_ADMIN) return;

  if (selected) selectedDriveItems.set(item.key, item);
  else selectedDriveItems.delete(item.key);

  syncSelectionKey(item.key);
  updateSelectionToolbar();
}

function clearDriveSelection() {
  const keys = Array.from(selectedDriveItems.keys());
  selectedDriveItems.clear();
  keys.forEach(syncSelectionKey);
  updateSelectionToolbar();
}

function toggleSelectionFromCheckbox(checkbox) {
  const element = checkbox.closest('[data-select-key]');
  setDriveItemSelected(element, checkbox.checked);
}

function selectDriveItem(element, event) {
  if (Date.now() < driveClickSuppressedUntil) return;
  if (event?.target?.closest('button,a,input,label')) return;

  if (!IS_ADMIN) {
    if (isCoarsePointer()) openItem(element);
    return;
  }

  const item = driveItemFromElement(element);
  if (!item) return;

  if (isCoarsePointer() && selectedDriveItems.size === 1 && selectedDriveItems.has(item.key)) {
    openItem(element);
    return;
  }

  if (event?.ctrlKey || event?.metaKey || event?.shiftKey) {
    setDriveItemSelected(element, !selectedDriveItems.has(item.key));
    return;
  }

  if (selectedDriveItems.size !== 1 || !selectedDriveItems.has(item.key)) {
    clearDriveSelection();
    setDriveItemSelected(element, true);
  }
}

function selectCard(element, event) {
  selectDriveItem(element, event);
}

function selectRow(element, event) {
  selectDriveItem(element, event);
}

document.querySelectorAll('.dv-card[role="button"],.dv-list-row[role="button"]').forEach(item => {
  item.addEventListener('keydown', event => {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    event.preventDefault();

    if (IS_ADMIN && event.key === ' ') {
      const driveItem = driveItemFromElement(item);
      setDriveItemSelected(item, !selectedDriveItems.has(driveItem?.key));
      return;
    }

    openItem(item);
  });
});

document.getElementById('btnSelectAll')?.addEventListener('click', () => {
  const availableItems = selectableDriveItems();
  if (availableItems.length > 0 && selectedDriveItems.size === availableItems.length) {
    clearDriveSelection();
    return;
  }

  availableItems.forEach(item => {
    selectedDriveItems.set(item.key, item);
    syncSelectionKey(item.key);
  });
  updateSelectionToolbar();
});

updateSelectionToolbar();

/* Drag-and-drop item movement */
let draggedDriveItem = null;

function clearDriveDropTargets() {
  document.querySelectorAll('.is-drop-target').forEach(element => element.classList.remove('is-drop-target'));
}

async function moveDraggedDriveItem(destinationId) {
  if (!draggedDriveItem?.moveUrl) return;
  const response = await fetch(draggedDriveItem.moveUrl, {
    method: 'PATCH',
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': CSRF,
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: JSON.stringify({destination_folder_id: destinationId || null})
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    const validationMessage = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
    throw new Error(validationMessage || payload.message || 'Item gagal dipindahkan.');
  }
}

if (IS_ADMIN) {
  document.querySelectorAll('[data-select-key][draggable="true"]').forEach(element => {
    element.addEventListener('dragstart', event => {
      if (event.target.closest('button,input,label,a')) {
        event.preventDefault();
        return;
      }
      draggedDriveItem = {
        type: element.dataset.type,
        id: element.dataset.id,
        name: element.dataset.name,
        moveUrl: element.dataset.moveUrl
      };
      driveClickSuppressedUntil = Date.now() + 700;
      element.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', draggedDriveItem.name || 'Bank Pipeline');
    });
    element.addEventListener('dragend', () => {
      element.classList.remove('is-dragging');
      clearDriveDropTargets();
      window.setTimeout(() => draggedDriveItem = null, 0);
    });
  });

  document.querySelectorAll('[data-drop-folder-id]').forEach(target => {
    target.addEventListener('dragover', event => {
      if (!draggedDriveItem || Array.from(event.dataTransfer.types || []).includes('Files')) return;
      const destinationId = target.dataset.dropFolderId || '';
      if (draggedDriveItem.type === 'folder' && String(draggedDriveItem.id) === String(destinationId)) return;
      event.preventDefault();
      event.dataTransfer.dropEffect = 'move';
      clearDriveDropTargets();
      target.classList.add('is-drop-target');
    });
    target.addEventListener('dragleave', event => {
      if (!target.contains(event.relatedTarget)) target.classList.remove('is-drop-target');
    });
    target.addEventListener('drop', async event => {
      if (!draggedDriveItem || Array.from(event.dataTransfer.types || []).includes('Files')) return;
      event.preventDefault();
      const destinationId = target.dataset.dropFolderId || null;
      clearDriveDropTargets();
      try {
        await moveDraggedDriveItem(destinationId);
        window.location.assign(window.location.href);
      } catch (error) {
        await driveSwal({icon:'error', title:'Gagal memindahkan item', text:error.message, confirmButtonText:'Tutup'}).catch(() => {});
      }
    });
  });
}

/* ── Modals ── */
async function deleteDriveItem(item) {
  if (!item?.deleteUrl) throw new Error('Alamat penghapusan item tidak tersedia.');

  const response = await fetch(item.deleteUrl, {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: {
      'X-CSRF-TOKEN': CSRF,
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  });
  const payload = await response.json().catch(() => null);

  if (!response.ok || payload?.status !== 'success') {
    throw new Error(payload?.message || `Server menolak penghapusan "${item.name}".`);
  }

  return payload;
}

async function deleteSelectedDriveItems() {
  const selectedItems = Array.from(selectedDriveItems.values())
    .sort((left, right) => Number(left.type === 'folder') - Number(right.type === 'folder'));
  if (!selectedItems.length) return;

  const folderCount = selectedItems.filter(item => item.type === 'folder').length;
  const fileCount = selectedItems.length - folderCount;
  const details = [
    fileCount > 0 ? `${fileCount} file akan dipindahkan ke Sampah.` : '',
    folderCount > 0 ? `${folderCount} folder beserta isinya akan dihapus permanen.` : ''
  ].filter(Boolean).join(' ');

  let confirmation;
  try {
    confirmation = await driveSwal({
      icon: 'warning',
      title: `Hapus ${selectedItems.length} item?`,
      text: details,
      showCancelButton: true,
      confirmButtonText: 'Hapus item',
      cancelButtonText: 'Batal',
      reverseButtons: true,
      focusCancel: true,
      customClass: {
        confirmButton: 'drive-swal-confirm drive-swal-confirm-danger'
      }
    });
  } catch (error) {
    console.error(error);
    return;
  }
  if (!confirmation.isConfirmed) return;

  const deleteButton = document.getElementById('btnDeleteSelected');
  if (deleteButton) deleteButton.disabled = true;

  driveSwal({
    title: 'Menghapus item',
    text: `Memproses 0 dari ${selectedItems.length} item...`,
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => window.Swal.showLoading()
  }).catch(() => {});

  const failures = [];
  let deletedCount = 0;

  for (const [index, item] of selectedItems.entries()) {
    window.Swal?.update({
      text: `Memproses ${index + 1} dari ${selectedItems.length}: ${item.name}`
    });

    try {
      await deleteDriveItem(item);
      deletedCount++;
      selectedDriveItems.delete(item.key);
    } catch (error) {
      failures.push({
        name: item.name,
        message: error?.message || 'Penghapusan gagal.'
      });
    }
  }

  window.Swal?.close();
  updateSelectionToolbar();

  if (failures.length === 0) {
    await driveSwal({
      icon: 'success',
      title: 'Penghapusan selesai',
      text: `${deletedCount} item berhasil diproses.`,
      showConfirmButton: false,
      timer: 1100,
      timerProgressBar: true
    }).catch(() => {});
    window.location.assign(window.location.href);
    return;
  }

  const firstFailure = failures[0];
  await driveSwal({
    icon: deletedCount > 0 ? 'warning' : 'error',
    title: deletedCount > 0 ? 'Sebagian item gagal dihapus' : 'Item gagal dihapus',
    text: `${deletedCount} berhasil, ${failures.length} gagal. ${firstFailure.name}: ${firstFailure.message}`,
    confirmButtonText: 'Tutup'
  }).catch(() => {});

  if (deletedCount > 0) {
    window.location.assign(window.location.href);
  } else if (deleteButton) {
    deleteButton.disabled = false;
  }
}

document.getElementById('btnDeleteSelected')?.addEventListener('click', deleteSelectedDriveItems);

document.querySelectorAll('.js-drive-confirm').forEach(form => {
  form.addEventListener('submit', async event => {
    if (form.dataset.driveConfirmed === 'true') return;
    event.preventDefault();

    let confirmation;
    try {
      confirmation = await driveSwal({
        icon: 'warning',
        title: form.dataset.confirmTitle || 'Konfirmasi tindakan',
        text: form.dataset.confirmText || 'Tindakan ini tidak dapat dibatalkan.',
        showCancelButton: true,
        confirmButtonText: form.dataset.confirmButton || 'Lanjutkan',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        focusCancel: true,
        customClass: {
          confirmButton: 'drive-swal-confirm drive-swal-confirm-danger'
        }
      });
    } catch (error) {
      console.error(error);
      return;
    }

    if (confirmation.isConfirmed) {
      form.dataset.driveConfirmed = 'true';
      form.requestSubmit();
    }
  });
});

function openModal(id) { document.getElementById(id).classList.add('is-open'); document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('is-open'); if (!document.querySelector('.dv-overlay.is-open')) document.body.style.overflow = ''; }
document.querySelectorAll('.dv-overlay').forEach(o => o.addEventListener('click', e => { if (e.target === o) closeModal(o.id); }));

/* ── New Folder ── */
document.getElementById('btnNewFolder')?.addEventListener('click', () => { document.getElementById('newFolderName').value = ''; openModal('modalFolder'); setTimeout(() => document.getElementById('newFolderName').focus(), 120); });

/* ── Upload ── */
const $dz = document.getElementById('dvDropzone');
const $fi = document.getElementById('dvFileInput');
const $uf = document.getElementById('dvUploadForm');
const $pp = document.getElementById('dvProgress');
const $pf = document.getElementById('dvProgressFill');
const $pl = document.getElementById('dvProgressLbl');
const $uploadButton = document.getElementById('btnUpload');
const UPLOAD_BATCH_SIZE = 1;
const UPLOAD_TIMEOUT_MS = 15 * 60 * 1000;
const MAX_CONSECUTIVE_UPLOAD_FAILURES = 3;
let uploadInFlight = false;

function setUploadBusy(busy) {
  uploadInFlight = busy;
  if ($uploadButton) $uploadButton.disabled = busy;
  if ($fi) $fi.disabled = busy;
  $dz?.classList.toggle('is-busy', busy);
  $dz?.setAttribute('aria-busy', busy ? 'true' : 'false');
}
function setUploadProgress(width, label, state = '') {
  const boundedWidth = Math.max(0, Math.min(100, Number(width) || 0));
  if ($pp) {
    $pp.style.display = 'block';
    $pp.classList.toggle('is-validating', state === 'validating');
    $pp.classList.toggle('is-error', state === 'error');
    $pp.querySelector('[role="progressbar"]')?.setAttribute('aria-valuenow', String(Math.round(boundedWidth)));
  }
  if ($pf) $pf.style.width = `${boundedWidth}%`;
  if ($pl) $pl.textContent = label;
}
function setUploadError(message) {
  const currentWidth = Number.parseFloat($pf?.style.width || '0');
  setUploadProgress(
    Number.isFinite(currentWidth) ? Math.min(currentWidth, 96) : 0,
    message,
    'error'
  );
  if ($fi) $fi.value = '';
  setUploadBusy(false);
}
function parseUploadJson(xhr) {
  const contentType = xhr.getResponseHeader('Content-Type') || '';
  if (!/\bapplication\/(?:[a-z0-9.+-]*\+)?json\b/i.test(contentType)) return null;
  try {
    const payload = JSON.parse(xhr.responseText);
    return payload && typeof payload === 'object' && !Array.isArray(payload) ? payload : null;
  } catch (_) {
    return null;
  }
}
function uploadErrorMessage(payload, batchFiles, fallback) {
  if (payload?.errors && typeof payload.errors === 'object') {
    for (const [key, messages] of Object.entries(payload.errors)) {
      const message = Array.isArray(messages) ? messages[0] : messages;
      if (!message) continue;
      const match = key.match(/^files\.(\d+)(?:\.|$)/);
      const file = match ? batchFiles[Number(match[1])] : null;
      return file?.name ? `${file.name}: ${message}` : String(message);
    }
  }
  return payload?.message || fallback;
}
function createUploadError(message, transportFailure = false, fatalQueue = false) {
  const error = new Error(message);
  error.transportFailure = transportFailure;
  error.fatalQueue = fatalQueue;
  return error;
}
function sameOriginUploadRedirect(value) {
  if (typeof value !== 'string' || !value.trim()) return null;
  try {
    const url = new URL(value, window.location.href);
    if (url.origin !== window.location.origin || url.username || url.password) return null;
    return url;
  } catch (_) {
    return null;
  }
}
function sendUploadBatch(batchFiles, batchIndex, batchCount) {
  return new Promise((resolve, reject) => {
    const fd = new FormData($uf);
    fd.delete('files[]');
    batchFiles.forEach(file => fd.append('files[]', file));

    const xhr = new XMLHttpRequest();
    xhr.open('POST', $uf.action);
    xhr.timeout = UPLOAD_TIMEOUT_MS;
    xhr.setRequestHeader('X-CSRF-TOKEN', CSRF);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.upload.addEventListener('progress', event => {
      if (!event.lengthComputable) return;
      const batchFraction = Math.max(0, Math.min(1, event.loaded / event.total));
      const aggregatePercent = Math.min(
        99,
        Math.round(((batchIndex + batchFraction) / batchCount) * 100)
      );
      setUploadProgress(
        Math.min(aggregatePercent, 96),
        `Mengunggah file ${batchIndex + 1}/${batchCount}... ${aggregatePercent}%`
      );
    });
    xhr.upload.addEventListener('load', () => {
      const validationWidth = Math.min(
        96,
        Math.round(((batchIndex + 1) / batchCount) * 100)
      );
      setUploadProgress(
        validationWidth,
        `Memvalidasi dan menyimpan file ${batchIndex + 1}/${batchCount}...`,
        'validating'
      );
    });
    xhr.addEventListener('load', () => {
      const payload = parseUploadJson(xhr);
      const redirect = sameOriginUploadRedirect(payload?.redirect_url);
      const validSuccess = xhr.status === 201
        && payload !== null
        && Number.isInteger(payload.uploaded_count)
        && payload.uploaded_count === batchFiles.length
        && redirect !== null;

      if (validSuccess) {
        resolve({
          uploadedCount: payload.uploaded_count,
          redirect,
        });
        return;
      }

      const transportFailure = xhr.status === 408
        || xhr.status === 425
        || xhr.status === 429
        || xhr.status >= 500;
      const fatalQueue = [401, 403, 419].includes(xhr.status);
      reject(createUploadError(
        uploadErrorMessage(
          payload,
          batchFiles,
          xhr.status === 201
            ? 'Respons sukses server tidak lengkap atau tidak konsisten.'
            : 'Upload gagal. Periksa format dan ukuran file.'
        ),
        transportFailure,
        fatalQueue
      ));
    });
    xhr.addEventListener('error', () => reject(createUploadError('Koneksi ke server terputus.', true)));
    xhr.addEventListener('abort', () => reject(createUploadError('Upload dibatalkan.')));
    xhr.addEventListener('timeout', () => reject(createUploadError('Upload melewati batas waktu.', true)));

    try {
      xhr.send(fd);
    } catch (_) {
      reject(createUploadError('Upload tidak dapat dimulai.', true));
    }
  });
}

$uploadButton?.addEventListener('click', () => {
  if (uploadInFlight) return;
  $dz.classList.toggle('is-open');
  if ($dz.classList.contains('is-open')) $dz.scrollIntoView({behavior:'smooth',block:'nearest'});
});
$dz?.addEventListener('click', () => { if (!uploadInFlight) $fi.click(); });
$dz?.addEventListener('dragover', e => {
  e.preventDefault();
  if (!uploadInFlight) $dz.classList.add('drag-over');
});
$dz?.addEventListener('dragleave', () => $dz.classList.remove('drag-over'));
$dz?.addEventListener('drop', e => {
  e.preventDefault();
  $dz.classList.remove('drag-over');
  if (!uploadInFlight) doUpload(e.dataTransfer.files);
});
document.addEventListener('dragover', event => {
  if (!IS_ADMIN || uploadInFlight || !Array.from(event.dataTransfer?.types || []).includes('Files')) return;
  event.preventDefault();
  $dz?.classList.add('is-open', 'drag-over');
});
document.addEventListener('drop', event => {
  if (!IS_ADMIN || uploadInFlight || !Array.from(event.dataTransfer?.types || []).includes('Files')) return;
  event.preventDefault();
  $dz?.classList.remove('drag-over');
  if (!event.target.closest('#dvDropzone')) doUpload(event.dataTransfer.files);
});
$fi?.addEventListener('change', () => doUpload($fi.files));
async function doUpload(files) {
  if (uploadInFlight || !files?.length || !$uf) return;

  const selectedFiles = Array.from(files);
  const batches = [];
  for (let offset = 0; offset < selectedFiles.length; offset += UPLOAD_BATCH_SIZE) {
    batches.push(selectedFiles.slice(offset, offset + UPLOAD_BATCH_SIZE));
  }

  if ($fi) $fi.value = '';
  setUploadBusy(true);
  setUploadProgress(0, `Mengunggah 0/${selectedFiles.length} file... 0%`);

  let uploadedTotal = 0;
  let finalRedirect = null;
  let consecutiveTransportFailures = 0;
  let skippedCount = 0;
  let queueStopReason = '';
  const failedUploads = [];

  for (let activeBatch = 0; activeBatch < batches.length; activeBatch++) {
    try {
      const result = await sendUploadBatch(
        batches[activeBatch],
        activeBatch,
        batches.length
      );
      uploadedTotal += result.uploadedCount;
      finalRedirect = result.redirect;
      consecutiveTransportFailures = 0;
    } catch (error) {
      const fileName = batches[activeBatch][0]?.name || `File ${activeBatch + 1}`;
      failedUploads.push({
        name: fileName,
        message: error?.message || 'Upload gagal.'
      });
      consecutiveTransportFailures = error?.transportFailure
        ? consecutiveTransportFailures + 1
        : 0;

      if (error?.fatalQueue) {
        skippedCount = batches.length - activeBatch - 1;
        queueStopReason = 'karena sesi atau izin akses perlu diperbarui';
        break;
      }

      if (consecutiveTransportFailures >= MAX_CONSECUTIVE_UPLOAD_FAILURES) {
        skippedCount = batches.length - activeBatch - 1;
        queueStopReason = 'karena koneksi tidak stabil';
        break;
      }
    }
  }

  if (uploadedTotal === selectedFiles.length && finalRedirect && failedUploads.length === 0) {
    setUploadProgress(
      100,
      `${uploadedTotal} file berhasil disimpan. Membuka folder...`
    );
    if ($fi) $fi.value = '';
    setUploadBusy(false);
    window.location.assign(finalRedirect.href);
    return;
  }

  const firstFailure = failedUploads[0];
  const progressMessage = [
    `${uploadedTotal} berhasil`,
    `${failedUploads.length} gagal`,
    skippedCount > 0 ? `${skippedCount} belum diproses ${queueStopReason}` : ''
  ].filter(Boolean).join(' · ');
  setUploadError(progressMessage);

  await driveSwal({
    icon: uploadedTotal > 0 ? 'warning' : 'error',
    title: uploadedTotal > 0 ? 'Upload selesai dengan kendala' : 'Upload belum berhasil',
    text: `${progressMessage}.${firstFailure ? ` ${firstFailure.name}: ${firstFailure.message}` : ''}`,
    confirmButtonText: 'Tutup'
  }).catch(() => {});

  if (uploadedTotal > 0 && finalRedirect) {
    window.location.assign(finalRedirect.href);
  }
}

/* ── Trash ── */
document.getElementById('btnTrash')?.addEventListener('click', () => openModal('modalTrash'));

/* ── Context Menu ── */
const $ctx = document.getElementById('dvCtx');
let ctxType=null, ctxId=null, ctxName=null, ctxElement=null;
function openCtx(e, item) {
  e.preventDefault(); e.stopPropagation();
  ctxElement=item;
  ctxType=item?.dataset.type ?? null;
  ctxId=item?.dataset.id ?? null;
  ctxName=item?.dataset.name ?? '';
  const isFile = ctxType === 'file';
  document.getElementById('ctxOpen').style.display    = '';
  document.getElementById('ctxPreview').style.display = isFile  ? '' : 'none';
  document.getElementById('ctxDownload').style.display= isFile  ? '' : 'none';
  document.getElementById('ctxAdminSep').style.display = IS_ADMIN ? '' : 'none';
  document.getElementById('ctxRename').style.display   = IS_ADMIN ? '' : 'none';
  document.getElementById('ctxMove').style.display     = IS_ADMIN ? '' : 'none';
  document.getElementById('ctxCopy').style.display     = IS_ADMIN && isFile ? '' : 'none';
  document.getElementById('ctxDangerSep').style.display= IS_ADMIN ? '' : 'none';
  document.getElementById('ctxDelete').style.display   = IS_ADMIN ? '' : 'none';
  $ctx.style.left='0'; $ctx.style.top='0';
  $ctx.classList.add('is-open');
  const vw=window.innerWidth, vh=window.innerHeight;
  const w=$ctx.offsetWidth, h=$ctx.offsetHeight;
  $ctx.style.left = Math.min(e.clientX, vw-w-8) + 'px';
  $ctx.style.top  = Math.min(e.clientY, vh-h-8) + 'px';
}
document.addEventListener('click',      () => $ctx.classList.remove('is-open'));
document.addEventListener('keydown',    e  => { if (e.key==='Escape') $ctx.classList.remove('is-open'); });

document.getElementById('ctxOpen').addEventListener('click',     () => openItem(ctxElement));
document.getElementById('ctxPreview').addEventListener('click',  () => openItem(ctxElement));
document.getElementById('ctxDownload').addEventListener('click', () => {
  if (ctxElement?.dataset.downloadUrl) window.location.assign(ctxElement.dataset.downloadUrl);
});

document.getElementById('ctxRename').addEventListener('click', () => {
  document.getElementById('renameMTitle').innerHTML = `<i class="fas fa-pen" style="color:var(--d-blue);"></i> Rename ${ctxType==='folder'?'Folder':'File'}`;
  document.getElementById('renameInp').value = ctxName;
  document.getElementById('renameForm').action = ctxElement?.dataset.renameUrl ?? '';
  openModal('modalRename');
  setTimeout(() => { const i=document.getElementById('renameInp'); i.focus(); i.select(); }, 120);
});

document.getElementById('ctxMove').addEventListener('click', () => {
  document.getElementById('mcForm').action = ctxElement?.dataset.moveUrl ?? '';
  document.getElementById('mcAction').value = 'move';
  document.getElementById('mcTitle').innerHTML = '<i class="fas fa-folder-open" style="color:#f6c341;"></i> Pindahkan ke';
  document.getElementById('mcSubmitBtn').innerHTML = '<i class="fas fa-check"></i> Pindahkan';
  openModal('modalMoveCopy');
});

document.getElementById('ctxCopy').addEventListener('click', () => {
  document.getElementById('mcForm').action = ctxElement?.dataset.copyUrl ?? '';
  document.getElementById('mcAction').value = 'copy';
  document.getElementById('mcTitle').innerHTML = '<i class="fas fa-copy" style="color:var(--d-blue);"></i> Salin ke';
  document.getElementById('mcSubmitBtn').innerHTML = '<i class="fas fa-check"></i> Salin Ke Sini';
  openModal('modalMoveCopy');
});

document.getElementById('ctxDelete').addEventListener('click', async () => {
  const consequence = ctxType === 'folder'
    ? 'Folder beserta seluruh isinya akan dihapus permanen.'
    : 'File akan masuk ke Sampah dan masih dapat dipulihkan.';

  let confirmation;
  try {
    confirmation = await driveSwal({
      icon: 'warning',
      title: `Hapus ${ctxType === 'folder' ? 'folder' : 'file'}?`,
      text: `"${ctxName}". ${consequence}`,
      showCancelButton: true,
      confirmButtonText: 'Hapus',
      cancelButtonText: 'Batal',
      reverseButtons: true,
      focusCancel: true,
      customClass: {
        confirmButton: 'drive-swal-confirm drive-swal-confirm-danger'
      }
    });
  } catch (error) {
    console.error(error);
    return;
  }

  if (!confirmation.isConfirmed) return;
  if (ctxElement?.dataset.deleteUrl) postForm(ctxElement.dataset.deleteUrl, 'DELETE');
});

/* ── Folder Tree ── */
function selectTree(el, id) {
  document.querySelectorAll('.dv-tree-item.sel').forEach(i => i.classList.remove('sel'));
  el.classList.add('sel');
  document.getElementById('mcDestId').value = id;
}

/* ── Preview ── */
function openItem(item) {
  if (!item) return;
  if (item.dataset.type === 'folder') {
    if (item.dataset.openUrl) window.location.assign(item.dataset.openUrl);
    return;
  }

  switch (item.dataset.mode) {
    case 'office':
      window.location.assign(item.dataset.officeUrl);
      break;
    case 'spreadsheet':
      window.location.assign(item.dataset.editorUrl);
      break;
    case 'document':
      window.location.assign(item.dataset.documentUrl);
      break;
    case 'pdf':
      window.open(item.dataset.previewUrl, '_blank', 'noopener,noreferrer');
      break;
    case 'image':
      openImagePreview(item);
      break;
    default:
      window.location.assign(item.dataset.downloadUrl);
  }
}

function openImagePreview(item) {
  document.getElementById('previewTitle').textContent = item.dataset.name ?? 'Preview gambar';
  document.getElementById('previewDlBtn').href = item.dataset.downloadUrl;
  const body = document.getElementById('previewBody');
  const image = document.createElement('img');
  image.src = item.dataset.previewUrl;
  image.alt = item.dataset.name ?? 'Gambar';
  image.loading = 'eager';
  body.replaceChildren(image);
  openModal('modalPreview');
}

/* ── Helper ── */
function postForm(url, method) {
  const f=document.createElement('form'); f.method='POST'; f.action=url;
  const c=document.createElement('input'); c.type='hidden'; c.name='_token'; c.value=CSRF;
  const m=document.createElement('input'); m.type='hidden'; m.name='_method'; m.value=method;
  f.appendChild(c); f.appendChild(m); document.body.appendChild(f); f.submit();
}

/* ── Auto-dismiss flash ── */
setTimeout(() => { document.getElementById('dvFlash')?.remove(); document.getElementById('dvFlash2')?.remove(); }, 4000);

/* ── Keyboard shortcuts ── */
document.addEventListener('keydown', e => {
  if (e.target.tagName==='INPUT'||e.target.tagName==='TEXTAREA') return;
  if (e.key==='g') setView('grid');
  if (e.key==='l') setView('list');
  if (e.key==='n' && IS_ADMIN) { e.preventDefault(); document.getElementById('btnNewFolder')?.click(); }
  if (e.key==='u' && IS_ADMIN) { e.preventDefault(); document.getElementById('btnUpload')?.click(); }
});
</script>
@endsection
