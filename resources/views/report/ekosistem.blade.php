@extends('layouts.admin')
@section('title', 'Peta Prospek Ekosistem')
@php
    $s = $monitorSummary;
    $money = static function ($value): string {
        if ($value === null || $value === '') return '—';
        $value = (float) $value;
        if (abs($value) >= 1_000_000) return 'Rp'.number_format($value / 1_000_000, 2, ',', '.').' T';
        if (abs($value) >= 1_000) return 'Rp'.number_format($value / 1_000, 1, ',', '.').' M';
        return 'Rp'.number_format($value, $value == floor($value) ? 0 : 1, ',', '.').' jt';
    };
    $range = static function ($min, $max) use ($money): string {
        if ($min === null && $max === null) return '—';
        $min = $min ?? $max;
        $max = $max ?? $min;
        return $min == $max ? $money($max) : $money($min).' – '.$money($max);
    };
    $num = static fn ($n): string => number_format((float) $n, 0, ',', '.');
    $date = static fn ($v): string => $v ? \Carbon\Carbon::parse($v)->locale('id')->translatedFormat('d M Y') : 'Belum diisi';
    $url = static function (array $patch = []) use ($filters, $activeView): string {
        $params = array_merge($filters, ['view' => $activeView], $patch);
        return route('ekosistem.index', array_filter($params, static fn ($v): bool => $v !== '' && $v !== null));
    };
    $detailUrl = static fn (array $patch = []): string => $url(array_merge(['view' => 'prospects', 'page' => null], $patch));
    $statusTone = ['Belum mulai' => 'muted', 'Berjalan' => 'info', 'Tertunda' => 'warning', 'Selesai' => 'success', 'Batal' => 'danger'];
    $filterLabels = ['jalur'=>'Jalur RM', 'condition'=>'Kondisi', 'q'=>'Pencarian', 'jenis'=>'Jenis potensi', 'status'=>'Status', 'pic'=>'PIC', 'ring'=>'Ring', 'arah'=>'Arah', 'dasar'=>'Dasar angka', 'eco'=>'Ekosistem', 'peran'=>'Peran PIC'];
    $extraOpen = count(array_filter(array_intersect_key($filters, array_flip(['jenis','status','pic','ring','arah','dasar','eco','peran'])))) > 0;
    $attention = array_sum(array_intersect_key($s['conditions'], array_flip(['late','stale','due','hold','nopic'])));
    $detailRecords = array_column($paginator->items(), null, 'ID_Prospek');
    $ecoNames = array_column($allEcosystems, 'Nama_Ekosistem', 'ID_Ekosistem');
@endphp

@section('styles')
<style>
    .em { --em-blue:#0857c3; --em-navy:#053b82; --em-ink:#172c46; --em-muted:#536a83; --em-line:#dbe5f0; --em-soft:#f3f7fc; --em-green:#15724d; --em-amber:#88560b; --em-red:#b43436; color:var(--em-ink); padding:.5rem 0 2rem; font-size:.85rem; line-height:1.5; font-variant-numeric:tabular-nums; }
    .em *, .em *::before, .em *::after { box-sizing:border-box; }
    .em [hidden] { display:none !important; }
    .em ::selection { color:#053b82; background:#cee5ff; }
    .em :is(a,button,input,select,summary):focus-visible { outline:3px solid #307fe2; outline-offset:3px; }
    .em :is(a,button,input,select) { touch-action:manipulation; }
    .em a { text-underline-offset:3px; }
    .em h1,.em h2,.em h3 { color:var(--em-ink); margin:0; font-weight:750; }
    .em h1 { font-size:1.45rem; letter-spacing:-.025em; }
    .em h2 { font-size:1rem; letter-spacing:-.015em; }
    .em h3 { font-size:.9rem; }
    .em-head { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
    .em-brand { display:flex; align-items:center; gap:.85rem; }
    .em-brand-icon { display:grid; place-items:center; width:45px; height:45px; border-radius:12px; background:var(--em-navy); color:#fff; font-size:1.2rem; }
    .em-head p { color:var(--em-muted); margin:.2rem 0 0; font-size:.8rem; }
    .em-actions { display:flex; align-items:center; flex-wrap:wrap; gap:.5rem; }
    .em-btn { display:inline-flex; align-items:center; justify-content:center; gap:.5rem; min-height:38px; padding:.45rem .8rem; background:#fff; border:1px solid #bdcfe3; border-radius:7px; color:var(--em-navy); font:inherit; font-weight:650; cursor:pointer; text-decoration:none; }
    .em-btn:hover { color:var(--em-blue); background:#edf4ff; text-decoration:none; }
    .em-btn--primary { background:var(--em-blue); border-color:var(--em-blue); color:#fff; }
    .em-btn--primary:hover { background:var(--em-navy); color:#fff; }
    .em-btn[aria-busy=true] { cursor:progress; }
    .em-tabs { display:flex; gap:1.5rem; border-bottom:1px solid #bccfe5; overflow-x:auto; margin-bottom:1rem; }
    .em-tabs a { display:inline-flex; align-items:center; gap:.5rem; padding:.7rem .1rem; white-space:nowrap; color:var(--em-muted); border-bottom:3px solid transparent; text-decoration:none; font-weight:650; }
    .em-tabs a[aria-current=page] { color:var(--em-navy); border-bottom-color:var(--em-blue); }
    .em-tabs a:hover { color:var(--em-blue); }
    .em-quick-stats { display:none; }
    .em-controls { background:#fff; border:1px solid var(--em-line); border-radius:10px; margin-bottom:1rem; }
    .em-controls > summary { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem; min-height:48px; padding:.7rem 1rem; list-style:none; cursor:pointer; color:var(--em-navy); font-weight:600; }
    .em-controls > summary::-webkit-details-marker { display:none; }
    .em-controls > summary .em-subtle { margin-left:.65rem; font-weight:400; }
    .em-controls > .em-filter { border:0; border-top:1px solid var(--em-line); margin:0; border-radius:0 0 10px 10px; }
    .em-filter { border:1px solid var(--em-line); background:#fff; border-radius:12px; padding:.9rem 1rem; margin-bottom:1rem; }
    .em-filter-main { display:grid; grid-template-columns:minmax(135px,.9fr) minmax(190px,1.7fr) minmax(140px,1fr) minmax(160px,1.15fr) auto; gap:.75rem; align-items:end; }
    .em-field { display:flex; flex-direction:column; gap:.3rem; min-width:0; }
    .em label { font-size:.75rem; font-weight:600; color:var(--em-muted); margin:0; }
    .em :is(input,select) { width:100%; min-width:0; height:38px; background:#fff; color:var(--em-ink); border:1px solid #bdcfe3; border-radius:6px; padding:0 .6rem; font:inherit; caret-color:var(--em-blue); }
    .em input::placeholder { color:var(--em-muted); }
    .em select:disabled { background:var(--em-soft); color:var(--em-muted); }
    .em-filter-bottom { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.5rem; margin-top:.6rem; color:var(--em-muted); font-size:.74rem; }
    .em-filter details { margin-top:.55rem; }
    .em-filter summary { color:var(--em-blue); cursor:pointer; width:fit-content; padding:.2rem 0; font-size:.78rem; font-weight:600; }
    .em-filter-extra { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; padding:.8rem 0 .25rem; }
    .em-filter-active { display:flex; flex-wrap:wrap; gap:.3rem; margin-top:.65rem; }
    .em-filter-active a { padding:.2rem .45rem; background:#eaf2ff; color:#053b82; border-radius:5px; font-size:.73rem; overflow-wrap:anywhere; }
    .em-finance { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); background:var(--em-navy); color:#fff; border-radius:12px; padding:1rem .3rem; margin-bottom:1rem; }
    .em-finance > a { padding:.1rem 1.1rem; color:#fff; border-right:1px solid #ffffff36; text-decoration:none; min-width:0; }
    .em-finance > a:last-child { border:0; }
    .em-finance > a:hover { background:#ffffff10; }
    .em-finance span { display:flex; gap:.5rem; align-items:center; font-size:.76rem; color:#d8eaff; }
    .em-finance strong { display:block; font-size:1.3rem; font-weight:750; margin:.35rem 0 .2rem; letter-spacing:-.02em; overflow-wrap:anywhere; }
    .em-finance .em-finance-note { color:#d8eaff; font-size:.7rem; display:block; }
    .em-title-row { display:flex; align-items:baseline; justify-content:space-between; gap:.65rem; flex-wrap:wrap; margin-bottom:.8rem; }
    .em-title-row p { margin:.15rem 0 0; color:var(--em-muted); font-size:.77rem; }
    .em-subtle { color:var(--em-muted); font-size:.75rem; }
    .em-overview { display:grid; grid-template-columns:minmax(0,1.35fr) minmax(0,1fr); gap:1rem; margin-bottom:1rem; }
    .em-panel { background:#fff; border:1px solid var(--em-line); border-radius:12px; padding:1.1rem; min-width:0; }
    .em-map-panel { display:flex; flex-direction:column; }
    .em-map { position:relative; background:#eef4fa; border-radius:9px; flex:1; min-height:340px; display:grid; place-items:center; overflow:hidden; }
    .em-map svg { display:block; width:100%; max-height:390px; height:auto; }
    .em-region path { fill:var(--region-color); stroke:#fff; stroke-width:.7; vector-effect:non-scaling-stroke; transition:fill-opacity .15s ease; }
    .em-region:hover path,.em-region:focus path { fill-opacity:.75; stroke:var(--em-navy); stroke-width:1; }
    .em-region[data-selected=true] path { stroke:#102d52; stroke-width:1.6; }
    .em-region rect { fill:#fff; stroke:#d5e1ef; stroke-width:1; }
    .em-region text { fill:#12385f; font-size:14px; font-weight:700; text-anchor:middle; pointer-events:none; }
    .em-region .em-map-count { font-size:10px; font-weight:500; fill:#536a83; }
    .em-map-empty { padding:2rem; color:var(--em-muted); text-align:center; }
    .em-map-legend { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.5rem; margin-top:.7rem; }
    .em-map-legend a { padding:.4rem; border-radius:5px; color:var(--em-ink); text-decoration:none; font-size:.73rem; }
    .em-map-legend a:hover { background:#eef4ff; }
    .em-map-legend i { display:inline-block; width:8px; height:8px; border-radius:2px; margin-right:.35rem; background:var(--region-color); }
    .em-map-legend strong { display:block; margin:.15rem 0 0 .85rem; font-size:.85rem; }
    .em-map-note { margin:.6rem 0 0; color:var(--em-muted); font-size:.68rem; }
    .em-total { display:flex; justify-content:space-between; align-items:end; gap:.7rem; padding-bottom:.85rem; border-bottom:1px solid var(--em-line); }
    .em-total strong { font-size:2.25rem; line-height:1; font-weight:800; color:var(--em-navy); letter-spacing:-.035em; }
    .em-total span { display:block; margin-top:.45rem; color:var(--em-muted); font-size:.8rem; }
    .em-execution { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); margin:1rem 0; gap:.75rem; }
    .em-execution a { text-decoration:none; color:var(--em-ink); }
    .em-execution b { display:block; font-size:1.4rem; font-weight:750; }
    .em-execution span { display:block; color:var(--em-muted); font-size:.73rem; }
    .em-stack { display:flex; height:10px; gap:2px; overflow:hidden; border-radius:4px; background:#edf2f7; margin:1rem 0 .75rem; }
    .em-stack span { min-width:0; }
    .em-statuses { display:grid; grid-template-columns:1fr 1fr; gap:.4rem .85rem; margin-bottom:1rem; }
    .em-statuses a { display:flex; align-items:center; gap:.4rem; color:var(--em-muted); text-decoration:none; font-size:.76rem; }
    .em-statuses b { margin-left:auto; color:var(--em-ink); }
    .em-dot { width:7px; height:7px; display:inline-block; border-radius:50%; flex-shrink:0; }
    .em-real { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:.5rem; padding:.85rem 0; border-top:1px solid var(--em-line); border-bottom:1px solid var(--em-line); }
    .em-real span { color:var(--em-muted); font-size:.75rem; }
    .em-real strong { font-size:1.05rem; color:var(--em-green); }
    .em-attention { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.5rem; margin-top:.8rem; }
    .em-attention a { display:block; padding:.55rem; border-radius:7px; background:#fff7ec; color:#88560b; text-decoration:none; }
    .em-attention b { display:block; font-size:1.05rem; }
    .em-attention span { font-size:.68rem; }
    .em-section { margin-bottom:1rem; }
    .em-scroll { overflow-x:auto; scrollbar-width:thin; scrollbar-color:#91accb #eef4fa; }
    .em-table { width:100%; border-collapse:collapse; font-size:.79rem; }
    .em-table th { background:#edf3fb; color:#3d5979; font-size:.71rem; font-weight:650; padding:.65rem .6rem; text-align:left; white-space:nowrap; border-bottom:1px solid #ccdaeb; }
    .em-table td { padding:.8rem .6rem; border-bottom:1px solid #e5edf5; vertical-align:top; }
    .em-table tr:last-child td { border-bottom:0; }
    .em-table tbody tr:hover { background:#f8fbff; }
    .em-table .em-right { text-align:right; white-space:nowrap; }
    .em-table a { color:var(--em-navy); font-weight:650; }
    .em-table td > span.em-subtle { display:block; margin-top:.2rem; }
    .em-table .em-kc { white-space:nowrap; font-weight:700; }
    .em-coverage { display:flex; align-items:center; gap:.45rem; white-space:nowrap; }
    .em-coverage > span { height:4px; width:50px; background:#e5edf6; overflow:hidden; border-radius:2px; }
    .em-coverage i { display:block; height:100%; background:var(--em-blue); }
    .em-bottom-grid { display:grid; grid-template-columns:minmax(0,1.3fr) minmax(0,1fr); gap:1rem; margin-bottom:1rem; }
    .em-priority { margin:0; padding:0; list-style:none; }
    .em-priority li + li { border-top:1px solid var(--em-line); }
    .em-priority a { display:flex; justify-content:space-between; align-items:center; gap:.75rem; padding:.75rem 0; text-decoration:none; color:var(--em-ink); }
    .em-priority strong { display:block; font-size:.8rem; overflow-wrap:anywhere; }
    .em-priority .em-subtle { display:block; margin-top:.2rem; }
    .em-badge { display:inline-flex; align-items:center; white-space:normal; width:fit-content; padding:.15rem .45rem; font-size:.7rem; border-radius:5px; color:#4d627c; background:#edf2f7; font-weight:600; }
    .em-badge--info { color:#0857c3; background:#e7f0ff; }
    .em-badge--success { color:#146747; background:#e7f5ee; }
    .em-badge--warning { color:#80510b; background:#fff2dc; }
    .em-badge--danger { color:#a62c30; background:#fcebed; }
    .em-bar-list { display:grid; gap:.75rem; }
    .em-bar-list a { display:grid; grid-template-columns:105px minmax(0,1fr) 32px; gap:.6rem; align-items:center; font-size:.77rem; color:var(--em-muted); text-decoration:none; }
    .em-bar-list .em-bar { height:7px; background:#eaf1fa; border-radius:4px; overflow:hidden; }
    .em-bar i { display:block; height:100%; background:#307fe2; border-radius:inherit; }
    .em-bar-list b { text-align:right; color:var(--em-ink); }
    .em-empty { color:var(--em-muted); text-align:center; padding:2rem 1rem; }
    .em-empty strong { display:block; color:var(--em-ink); margin-bottom:.4rem; }
    .em-notice { margin-bottom:1rem; background:#fffaef; border:1px solid #ecd7ad; border-radius:8px; color:#85520c; font-size:.77rem; }
    .em-notice summary { cursor:pointer; padding:.65rem .9rem; }
    .em-notice > div { padding:0 .9rem .75rem; }
    .em-alert { padding:1rem; border:1px solid #ecd7ad; background:#fffaef; color:#85520c; border-radius:10px; }
    .em-method { color:var(--em-muted); font-size:.72rem; line-height:1.7; padding:.2rem .1rem; }
    .em-log { margin:0; padding:0; list-style:none; }
    .em-log li { display:flex; flex-wrap:wrap; align-items:baseline; gap:.35rem .75rem; padding:.6rem 0; border-top:1px solid var(--em-line); font-size:.75rem; }
    .em-log time { color:var(--em-muted); min-width:115px; }
    .em-matrix td { min-width:125px; }
    .em-matrix a { display:block; text-decoration:none; padding:.55rem; background:#eaf4f0; border-radius:7px; color:#1f694d; }
    .em-matrix a.em-cell-attention { background:#fff3e1; color:#895611; }
    .em-matrix b { font-size:1rem; }
    .em-ring-cell a { display:block; text-decoration:none; font-size:.76rem; }
    .em-ring-cell span { display:block; color:var(--em-muted); margin-top:.2rem; font-size:.68rem; }
    .em-eco-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:1rem; }
    .em-eco { background:#fff; border:1px solid var(--em-line); border-radius:12px; padding:1rem; min-width:0; }
    .em-eco h3 { margin:.7rem 0 .4rem; color:var(--em-navy); overflow-wrap:anywhere; }
    .em-eco p { font-size:.76rem; color:var(--em-muted); margin:.4rem 0; }
    .em-eco-rings { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.4rem; margin-top:1rem; }
    .em-eco-rings a { padding:.55rem .4rem; background:#edf4ff; color:var(--em-navy); border-radius:6px; text-decoration:none; text-align:center; font-size:.73rem; }
    .em-eco-rings b { display:block; font-size:1.1rem; }
    .em-pagination { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.6rem; margin-top:1rem; }
    .em-prospect { min-width:250px; max-width:330px; }
    .em-prospect button { background:none; border:0; padding:0; color:var(--em-navy); font:inherit; font-weight:700; text-align:left; cursor:pointer; overflow-wrap:anywhere; }
    .em-prospect button:hover { text-decoration:underline; }
    .em-prospect .em-subtle { font-size:.71rem; }
    .em-dialog { color:var(--em-ink); border:0; border-radius:14px; padding:0; width:min(860px,calc(100vw - 32px)); max-height:calc(100dvh - 32px); box-shadow:0 24px 64px -20px #042a5f80; }
    .em-dialog::backdrop { background:#0a244c99; }
    .em-dialog-head { padding:1.2rem 1.4rem; background:#edf4ff; display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; border-bottom:1px solid var(--em-line); }
    .em-dialog-head h2 { font-size:1.05rem; line-height:1.5; }
    .em-dialog-body { padding:1.2rem 1.4rem; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1.5rem; }
    .em-dialog h3 { font-size:.9rem; margin-bottom:.8rem; color:var(--em-navy); }
    .em-dialog dl { display:grid; grid-template-columns:115px minmax(0,1fr); gap:.5rem .75rem; margin:0; font-size:.79rem; }
    .em-dialog dt { color:var(--em-muted); font-weight:500; }
    .em-dialog dd { margin:0; white-space:pre-wrap; overflow-wrap:anywhere; }
    .em-dialog-wide { grid-column:1 / -1; }
    @media(max-width:1199.98px) { .em-filter-main { grid-template-columns:repeat(4,minmax(0,1fr)); } .em-filter-main .em-field:nth-child(2) { grid-column:span 2; } .em-filter-main .em-btn { grid-column:4; } .em-overview { grid-template-columns:minmax(0,1.2fr) minmax(0,1fr); } .em-finance strong { font-size:1.1rem; } .em-eco-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media(max-width:991.98px) {
        .em-quick-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); padding:.75rem .25rem; margin-bottom:1rem; border:1px solid var(--em-line); border-radius:10px; background:#fff; }
        .em-quick-stats a { display:block; text-decoration:none; padding:0 .5rem; border-right:1px solid var(--em-line); color:var(--em-navy); }
        .em-quick-stats a:last-child { border:0; }
        .em-quick-stats b { display:block; font-size:1.35rem; font-weight:750; }
        .em-quick-stats span { display:block; font-size:.68rem; color:var(--em-muted); }
        .em-overview { grid-template-columns:1fr; } .em-map { min-height:300px; } .em-bottom-grid { grid-template-columns:1fr; } .em-finance { grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem 0; } .em-finance>a:nth-child(2) { border:0; }
    }
    @media(max-width:575.98px) {
        .em h1 { font-size:1.15rem; } .em-head { gap:.8rem; } .em-brand { align-items:flex-start; gap:.6rem; } .em-brand-icon { width:38px; height:38px; flex-shrink:0; }
        .em-head .em-actions { width:100%; } .em-head .em-actions .em-btn { flex:1; } .em-tabs { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.15rem .75rem; overflow:visible; } .em-tabs a { font-size:.78rem; min-height:44px; }
        .em-filter { padding:.8rem; } .em-filter-main { grid-template-columns:repeat(2,minmax(0,1fr)); gap:.65rem; } .em-filter-main .em-field:nth-child(2) { grid-column:auto; } .em-filter-main .em-btn { grid-column:1 / -1; }
        .em :is(input,select) { font-size:1rem; height:44px; } .em-btn { min-height:44px; } .em-filter-extra { grid-template-columns:repeat(2,minmax(0,1fr)); } .em-filter summary { min-height:32px; }
        .em-finance>a { padding:0 .8rem; } .em-finance span { font-size:.7rem; } .em-finance strong { font-size:1.05rem; } .em-finance .em-finance-note { font-size:.65rem; }
        .em-panel { padding:.85rem; } .em-map { min-height:235px; } .em-map-legend { grid-template-columns:repeat(2,minmax(0,1fr)); } .em-map-legend a { min-height:44px; }
        .em-execution b { font-size:1.3rem; } .em-priority a { flex-wrap:wrap; } .em-priority .em-badge { margin-left:0; } .em-eco-grid { grid-template-columns:1fr; }
        .em-prospect-table { display:block; } .em-prospect-table thead { position:absolute; width:1px; height:1px; overflow:hidden; clip-path:inset(50%); } .em-prospect-table tbody { display:grid; gap:.8rem; }
        .em-prospect-table tr { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); padding:.8rem; gap:.6rem; border:1px solid var(--em-line); border-radius:8px; }
        .em-prospect-table td { border:0; min-width:0; padding:0; overflow-wrap:anywhere; } .em-prospect-table td::before { content:attr(data-label); display:block; color:var(--em-muted); font-size:.68rem; margin-bottom:.2rem; }
        .em-prospect-table .em-prospect { grid-column:1 / -1; min-width:0; max-width:none; } .em-prospect::before { display:none !important; } .em-prospect-table .em-right { text-align:left; white-space:normal; }
        .em-dialog-body { grid-template-columns:1fr; } .em-dialog dl { grid-template-columns:95px minmax(0,1fr); } .em-dialog-head,.em-dialog-body { padding:1rem; }
    }
    @media(prefers-reduced-motion:reduce) { .em *, .em *::before, .em *::after { animation:none !important; transition:none !important; scroll-behavior:auto !important; } }
</style>
@endsection

@section('content')
<div class="em" id="eco-workspace">
    <header class="em-head">
        <div class="em-brand"><span class="em-brand-icon"><i class="fas fa-project-diagram" aria-hidden="true"></i></span><div><h1>Peta Prospek Ekosistem</h1><p>Monitoring peluang, eksekusi, dan cakupan BRI Area 6.</p></div></div>
        <div class="em-actions"><span class="em-subtle"><i class="fas fa-eye" aria-hidden="true"></i> Monitoring</span><a class="em-btn" href="{{ $spreadsheetUrl }}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt" aria-hidden="true"></i> Sumber</a><a class="em-btn em-btn--primary" data-em-refresh href="{{ $url(['refresh'=>1]) }}"><i class="fas fa-sync-alt" aria-hidden="true"></i> Perbarui</a></div>
    </header>
    <nav class="em-tabs" aria-label="Tampilan monitoring">
        @foreach(['overview'=>['Ringkasan','chart-pie'],'monitor'=>['KC & PIC','users'],'ecosystems'=>['Ekosistem & ring','project-diagram'],'prospects'=>['Detail prospek','list']] as $key=>$tab)
            <a href="{{ $url(['view'=>$key]) }}" @if($activeView===$key) aria-current="page" 
@endif
><i class="fas fa-{{ $tab[1] }}" aria-hidden="true"></i>{{ $tab[0] }}</a>
        
@endforeach

    </nav>
    @if(! $error && $activeView==='overview')
        <div class="em-quick-stats" aria-label="Ringkasan eksekusi sesuai filter">
            <a href="{{ $detailUrl() }}"><b>{{ $num($s['total']) }}</b><span>Prospek</span></a>
            <a href="{{ $detailUrl(['status'=>'Berjalan']) }}"><b>{{ $num($s['statuses']['Berjalan']) }}</b><span>Berjalan</span></a>
            <a href="{{ $detailUrl(['status'=>'Selesai']) }}"><b>{{ $num($s['statuses']['Selesai']) }}</b><span>Selesai</span></a>
            <a href="{{ $detailUrl(['pic'=>'__assigned']) }}"><b>{{ $s['coverage'] }}%</b><span>PIC terisi</span></a>
        </div>
    @endif
    <details class="em-controls" @if(count(array_filter(array_diff_key($filters,['kc'=>true])))>0) open @endif>
        <summary><span><i class="fas fa-filter" aria-hidden="true"></i> Filter monitoring <span class="em-subtle">{{ $kcOptions[$selectedKc] }}</span></span><span class="em-subtle">{{ $fetchedAt ? 'Data '.$fetchedAt : 'Sumber belum tersedia' }} <i class="fas fa-chevron-down" aria-hidden="true"></i></span></summary>
    <form method="GET" action="{{ route('ekosistem.index') }}" class="em-filter" aria-label="Filter seluruh monitoring">
        <input type="hidden" name="view" value="{{ $activeView }}">
        @if($isLocked)<input type="hidden" name="kc" value="{{ $selectedKc }}">
@endif

        @if($filters['peran'] !== '')<input type="hidden" name="peran" value="{{ $filters['peran'] }}">
@endif

        <div class="em-filter-main">
            <div class="em-field"><label for="em-kc">Wilayah{{ $isLocked ? ' · sesuai akses' : '' }}</label><select name="kc" id="em-kc" @disabled($isLocked)>@foreach($kcOptions as $value=>$label)<option value="{{ $value }}" @selected($selectedKc===$value)>{{ $label }}</option>
@endforeach
</select></div>
            <div class="em-field"><label for="em-search">Pencarian</label><input name="q" id="em-search" type="search" value="{{ $filters['q'] }}" placeholder="Prospek, ekosistem, PIC…" maxlength="200"></div>
            <div class="em-field"><label for="em-jalur">Jalur RM</label><select name="jalur" id="em-jalur"><option value="">Semua jalur</option>@foreach($choices['jalur'] as $value)<option @selected($filters['jalur']===$value)>{{ $value }}</option>
@endforeach
</select></div>
            <div class="em-field"><label for="em-condition">Kondisi tindak lanjut</label><select name="condition" id="em-condition"><option value="">Semua kondisi</option>@foreach($conditions as $value=>$label)<option value="{{ $value }}" @selected($filters['condition']===$value)>{{ $label }}</option>
@endforeach
</select></div>
            <button class="em-btn em-btn--primary" type="submit">Terapkan filter</button>
        </div>
        <details @if($extraOpen) open 
@endif
><summary>Filter lainnya</summary><div class="em-filter-extra">
            @foreach(['jenis'=>'Jenis potensi','status'=>'Status','pic'=>'PIC','ring'=>'Ring','arah'=>'Arah garapan','dasar'=>'Dasar angka','eco'=>'Ekosistem'] as $key=>$label)
                <div class="em-field"><label for="em-{{ $key }}">{{ $label }}</label><select name="{{ $key }}" id="em-{{ $key }}"><option value="">Semua</option>@if($key==='pic')<option value="__unassigned" @selected($filters['pic']==='__unassigned')>Belum diisi</option><option value="__assigned" @selected($filters['pic']==='__assigned')>Sudah diisi</option>
@endif
 @if(in_array($key,['ring','jenis']))<option value="__empty" @selected($filters[$key]==='__empty')>Belum diisi</option>
@endif
@foreach($choices[$key] as $value)<option value="{{ $value }}" @selected($filters[$key]===$value)>{{ $key==='eco' ? ($ecoNames[$value] ?? $value) : $value }}</option>
@endforeach
</select></div>
            
@endforeach

        </div></details>
        @if($filters['condition']==='nopic')<p class="em-subtle">Prioritas tanpa PIC mengikuti urutan kondisi pada webapp. Prospek yang menunggu prasyarat berada pada kondisi tersendiri; untuk seluruh PIC kosong pilih PIC ?Belum diisi?.</p>@endif
        <div class="em-filter-bottom"><span>Semua ringkasan mengikuti filter. {{ $fetchedAt ? 'Data diambil '.$fetchedAt : 'Sumber belum tersedia' }}.</span><a href="{{ route('ekosistem.index',['kc'=>$selectedKc,'view'=>$activeView]) }}">Reset filter</a></div>
        @if(count(array_filter(array_diff_key($filters,['kc'=>true])))>0)<div class="em-filter-active">@foreach($filterLabels as $key=>$label)@if($filters[$key]!=='')<a href="{{ $url([$key=>'']) }}" aria-label="Hapus filter {{ $label }}">{{ $label }}: {{ $key==='condition' ? $conditions[$filters[$key]] : ($key==='eco' ? ($ecoNames[$filters[$key]] ?? $filters[$key]) : ($filters[$key]==='__unassigned' ? 'Belum diisi' : ($filters[$key]==='__empty' ? 'Belum diisi' : ($filters[$key]==='__assigned' ? 'Sudah diisi' : $filters[$key])))) }} ×</a>
@endif
 
@endforeach
</div>
@endif

    </form>
    </details>
    @if($error)
        <div class="em-alert" role="alert">{{ $error }} Gunakan tombol Perbarui untuk mencoba kembali.</div>
    
@else

        @if($s['unit_issues']>0)
            <details class="em-notice"><summary><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <strong>{{ $num($s['unit_issues']) }} prospek perlu cek satuan</strong> · nominal yang ditandai tidak masuk rekap.</summary><div>Nilai ≥ Rp10 T per prospek ditandai karena sumber memakai juta rupiah. Nilai asli tetap dapat dilihat pada detail; tidak dikonversi atau diubah di spreadsheet. <a href="{{ $detailUrl(['condition'=>'unit']) }}">Lihat rinciannya</a></div></details>
        
@endif

        <div class="em-finance" aria-label="Potensi dipisahkan menurut jenis, tidak dijumlahkan">
            @foreach(['Simpanan'=>['wallet','Peluang dana simpanan'],'Kredit'=>['hand-holding-usd','Peluang pembiayaan'],'Payroll'=>['users','Target orang, indikatif'],'Arus dana'=>['exchange-alt','Per tahun, bukan saldo']] as $category=>$meta)
                <a href="{{ $detailUrl(['jenis'=>$category]) }}"><span><i class="fas fa-{{ $meta[0] }}" aria-hidden="true"></i>{{ $category==='Arus dana' ? 'Arus dana / tahun' : 'Potensi '.$category }}</span><strong>{{ $category==='Payroll' ? $num($s['payroll']).' orang' : $range($s['categories'][$category]['min'],$s['categories'][$category]['max']) }}</strong><span class="em-finance-note">{{ $meta[1] }}</span></a>
            
@endforeach

        </div>
        @if($activeView==='overview')
            <div class="em-overview">
                <section class="em-panel em-map-panel" aria-labelledby="em-map-title">
                    <div class="em-title-row"><div><h2 id="em-map-title">Peta cakupan wilayah</h2><p>Pilih poligon atau nama KC untuk memfokuskan monitoring.</p></div>@if($selectedKc!=='all' && !$isLocked)<a class="em-subtle" href="{{ $url(['kc'=>'all']) }}">Semua KC</a>
@endif
</div>
                    <div class="em-map">
                        @if($geography['ready'])
                            <svg viewBox="{{ $geography['viewBox'] }}" role="group" aria-labelledby="em-map-access-title"><title id="em-map-access-title">Poligon wilayah KC yang dapat diakses. Rekap berdasarkan kolom KC pada spreadsheet.</title>
                                @foreach($geography['regions'] as $key=>$region)
                                    <a class="em-region" href="{{ $url(['kc'=>$key]) }}" style="--region-color:{{ $region['color'] }}" data-selected="{{ $selectedKc===$key ? 'true':'false' }}" aria-label="KC {{ $region['label'] }}, {{ $branches[$key]['stats']['total'] }} prospek. Pilih wilayah.">
                                        <title>KC {{ $region['label'] }} · {{ $branches[$key]['stats']['total'] }} prospek</title>
                                        @foreach($region['paths'] as $path)<path d="{{ $path }}" fill-rule="evenodd"/>
@endforeach

                                        <rect x="{{ $region['x']-52 }}" y="{{ $region['y']-20 }}" width="104" height="43" rx="7"/>
                                        <text x="{{ $region['x'] }}" y="{{ $region['y']-2 }}">{{ $region['label'] }}</text><text class="em-map-count" x="{{ $region['x'] }}" y="{{ $region['y']+13 }}">{{ $num($branches[$key]['stats']['total']) }} prospek</text>
                                    </a>
                                
@endforeach

                                <g transform="translate({{ $geography['north']['x'] }},{{ $geography['north']['y'] }})"><path d="M0 25V-12M-5-4L0-12L5-4" stroke="#647e99" fill="none"/><text x="0" y="-20" text-anchor="middle" fill="#536a83" font-size="11">U</text></g>
                            </svg>
                        
@else
<div class="em-map-empty">Peta batas wilayah belum tersedia. Pilih wilayah melalui rekap KC di bawah.</div>
@endif

                    </div>
                    <div class="em-map-legend">@foreach($branches as $key=>$branch)<a href="{{ $url(['kc'=>$key]) }}" style="--region-color:{{ $geography['regions'][$key]['color'] ?? '#0857c3' }}"><i aria-hidden="true"></i>{{ $branch['label'] }}<strong>{{ $num($branch['stats']['total']) }} prospek</strong></a>
@endforeach
</div>
                    <p class="em-map-note">{{ $geography['source'] }}. Poligon menunjukkan batas administrasi, bukan lokasi nasabah. Rekap KC mengikuti filter selain wilayah.</p>
                </section>
                <section class="em-panel" aria-labelledby="em-execution-title">
                    <div class="em-title-row"><div><h2 id="em-execution-title">Eksekusi {{ $kcOptions[$selectedKc] }}</h2><p>Prospek yang sesuai seluruh filter.</p></div></div>
                    <div class="em-total"><div><strong>{{ $num($s['total']) }}</strong><span>prospek pada {{ $num($s['ecosystems']) }} ekosistem terdaftar</span>@if($s['unmapped_ecosystem']>0)<a class="em-subtle" href="{{ $detailUrl(['condition'=>'unmapped']) }}">{{ $s['unmapped_ecosystem'] }} prospek belum terhubung ke master</a>@endif</div><a href="{{ $detailUrl() }}" class="em-btn">Lihat detail <i class="fas fa-arrow-right" aria-hidden="true"></i></a></div>
                    <div class="em-execution"><a href="{{ $detailUrl(['status'=>'Berjalan']) }}"><b>{{ $num($s['statuses']['Berjalan']) }}</b><span>Berjalan</span></a><a href="{{ $detailUrl(['status'=>'Selesai']) }}"><b>{{ $num($s['statuses']['Selesai']) }}</b><span>Selesai</span></a><a href="{{ $detailUrl(['pic'=>'__assigned']) }}"><b>{{ $s['coverage'] }}%</b><span>PIC terisi · {{ $s['assigned'] }}/{{ $s['total'] }}</span></a></div>
                    @php $colors=['Belum mulai'=>'#9bacc2','Berjalan'=>'#0857c3','Tertunda'=>'#d08a24','Selesai'=>'#19805b','Batal'=>'#bd4d54']; @endphp
                    <div class="em-stack" role="img" aria-label="Komposisi status; rincian jumlah pada legenda berikut.">@foreach($s['statuses'] as $status=>$total)<span style="width:{{ $s['total']>0 ? $total/$s['total']*100:0 }}%;background:{{ $colors[$status] ?? '#9bacc2' }}"></span>
@endforeach
</div>
                    <div class="em-statuses">@foreach($s['statuses'] as $status=>$total)<a href="{{ $detailUrl(['status'=>$status]) }}"><i class="em-dot" style="background:{{ $colors[$status] ?? '#9bacc2' }}" aria-hidden="true"></i>{{ $status }}<b>{{ $num($total) }}</b></a>
@endforeach
</div>
                    <div class="em-real"><span>Realisasi tercatat<br>{{ $num($s['customers']) }} nasabah terealisasi</span><strong>{{ $money($s['realization']) }}</strong></div>
                    <div class="em-attention">@foreach(['late'=>'Lewat target','stale'=>'Mandek > 14 hari','unassigned'=>'PIC belum diisi'] as $key=>$label)<a href="{{ $detailUrl($key==='unassigned' ? ['pic'=>'__unassigned'] : ['condition'=>$key]) }}"><b>{{ $num($key==='unassigned' ? $s['unassigned'] : $s['conditions'][$key]) }}</b><span>{{ $label }}</span></a>
@endforeach
</div>
                </section>
            </div>
        
@endif


        @if(in_array($activeView,['overview','monitor']))
            <section class="em-panel em-section" aria-labelledby="em-branch-title">
                <div class="em-title-row"><div><h2 id="em-branch-title">Rapor kantor cabang</h2><p>Perbandingan KC yang dapat diakses; filter lain tetap berlaku. Klik angka untuk membuka daftar.</p></div>@if($activeView==='overview')<a href="{{ $url(['view'=>'monitor']) }}">Lihat KC & PIC <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
@endif
</div>
                <div class="em-scroll" tabindex="0" role="region" aria-label="Rapor per KC" data-abah-no-table-guard="1"><table class="em-table" data-abah-no-table-guard="1"><thead><tr><th scope="col">Kantor cabang</th><th scope="col">Prospek</th><th scope="col">PIC terisi</th><th scope="col">Berjalan</th><th scope="col">Selesai</th><th scope="col">Perlu perhatian</th><th scope="col" class="em-right">Est. simpanan</th><th scope="col" class="em-right">Est. kredit</th><th scope="col" class="em-right">Realisasi</th></tr></thead><tbody>
                    @foreach($branches as $key=>$branch) @php $b=$branch['stats']; @endphp
                    <tr><td class="em-kc"><a href="{{ $url(['kc'=>$key]) }}">{{ $branch['label'] }}</a></td><td><a href="{{ $detailUrl(['kc'=>$key]) }}">{{ $num($b['total']) }}</a></td><td><span class="em-coverage">{{ $b['coverage'] }}% <span aria-hidden="true"><i style="width:{{ $b['coverage'] }}%"></i></span></span><span class="em-subtle">{{ $b['assigned'] }}/{{ $b['total'] }} prospek</span></td><td><a href="{{ $detailUrl(['kc'=>$key,'status'=>'Berjalan']) }}">{{ $num($b['statuses']['Berjalan']) }}</a></td><td><a href="{{ $detailUrl(['kc'=>$key,'status'=>'Selesai']) }}">{{ $num($b['statuses']['Selesai']) }}</a></td><td><a href="{{ $detailUrl(['kc'=>$key,'condition'=>'late']) }}">{{ $b['conditions']['late'] }} lewat target</a><span class="em-subtle">{{ $b['conditions']['stale'] }} mandek</span></td><td class="em-right">{{ $range($b['categories']['Simpanan']['min'],$b['categories']['Simpanan']['max']) }}</td><td class="em-right">{{ $range($b['categories']['Kredit']['min'],$b['categories']['Kredit']['max']) }}</td><td class="em-right">{{ $money($b['realization']) }}</td></tr>
                    
@endforeach

                </tbody></table></div>
            </section>
        
@endif


        @if($activeView==='overview')
            <div class="em-bottom-grid">
                <section class="em-panel" aria-labelledby="em-priority-title"><div class="em-title-row"><div><h2 id="em-priority-title">Prioritas tindak lanjut</h2><p>Urutan kondisi mengikuti webapp; prospek menunggu prasyarat dipisahkan dari prioritas tanpa PIC.</p></div><span class="em-badge em-badge--warning">{{ $num($attention) }} prospek</span></div>
                    @if(empty($priority))<div class="em-empty"><strong>Tidak ada prioritas pada filter ini</strong>Ubah filter untuk meninjau cakupan lainnya.</div>
@else
<ul class="em-priority">@foreach(array_slice($priority,0,5) as $row)<li><a href="{{ $detailUrl(['q'=>$row['ID_Prospek']]) }}"><span><strong>{{ $row['Prospek'] }}</strong><span class="em-subtle">{{ $row['ID_Prospek'] }} · KC {{ $row['KC'] }} · {{ $row['Nama_PIC'] ?: ($row['Peran_PIC'] ?? 'PIC belum diisi') }}</span></span><span class="em-badge em-badge--{{ $row['Kondisi']['tone'] }}">{{ $row['Kondisi']['label'] }}</span></a></li>
@endforeach
</ul>
@endif

                </section>
                <section class="em-panel" aria-labelledby="em-types-title"><div class="em-title-row"><div><h2 id="em-types-title">Komposisi peluang</h2><p>Jumlah prospek per jenis potensi, bukan penjumlahan nominal.</p></div></div><div class="em-bar-list">@foreach($s['types'] as $type=>$total)<a href="{{ $detailUrl(['jenis'=>$type==='Belum diklasifikasi' ? '__empty' : $type]) }}"><span>{{ $type }}</span><span class="em-bar" aria-hidden="true"><i style="width:{{ $s['total']>0 ? $total/$s['total']*100:0 }}%"></i></span><b>{{ $num($total) }}</b></a>
@endforeach
</div></section>
            </div>
            <section class="em-panel em-section" aria-labelledby="em-activity-title"><div class="em-title-row"><h2 id="em-activity-title">Aktivitas terakhir</h2><span class="em-subtle">Tercatat pada sumber</span></div>@if(empty($activity))<p class="em-subtle">Belum ada aktivitas untuk prospek pada filter ini.</p>
@else
<ul class="em-log">@foreach(array_slice($activity,0,5) as $log)<li><time>{{ $log['Waktu'] }}</time><a href="{{ $detailUrl(['q'=>$log['ID_Prospek']]) }}">{{ $log['ID_Prospek'] }}</a><span>{{ $log['Kolom'] }}: @if(($log['Nilai_Lama'] ?? '')!=='')<s>{{ $log['Nilai_Lama'] }}</s> → 
@endif
 {{ $log['Nilai_Baru'] }}</span></li>
@endforeach
</ul>
@endif
</section>
        
@endif


        @if($activeView==='monitor')
            <section class="em-panel em-section" aria-labelledby="em-matrix-title"><div class="em-title-row"><div><h2 id="em-matrix-title">Matriks KC × jalur RM</h2><p>Selesai / total prospek. Kuning menandai prospek lewat target atau mandek.</p></div></div><div class="em-scroll" tabindex="0" role="region" aria-label="Matriks KC dan jalur RM" data-abah-no-table-guard="1"><table class="em-table em-matrix" data-abah-no-table-guard="1"><thead><tr><th scope="col">KC</th>@foreach($jalurOptions as $jalur)<th scope="col">{{ $jalur }}</th>
@endforeach
</tr></thead><tbody>@foreach($branches as $key=>$branch)<tr><td class="em-kc">{{ $branch['label'] }}</td>@foreach($jalurOptions as $jalur)@php $cell=$matrix[$key][$jalur]; @endphp<td><a href="{{ $detailUrl(['kc'=>$key,'jalur'=>$jalur]) }}" @class(['em-cell-attention'=>$cell['conditions']['late']+$cell['conditions']['stale']>0])><b>{{ $cell['statuses']['Selesai'] }}</b> / {{ $cell['total'] }}<span class="em-subtle">{{ $cell['conditions']['late'] }} lewat · {{ $cell['conditions']['stale'] }} mandek</span></a></td>
@endforeach
</tr>
@endforeach
</tbody></table></div></section>
            <section class="em-panel em-section" aria-labelledby="em-pic-title"><div class="em-title-row"><div><h2 id="em-pic-title">Beban dan eksekusi PIC</h2><p>Nama mengikuti sumber; PIC kosong dikelompokkan menurut peran dan jalur RM.</p></div><span class="em-subtle">{{ count($picGroups) }} kelompok</span></div><div class="em-scroll" tabindex="0" role="region" aria-label="Rekap PIC" data-abah-no-table-guard="1"><table class="em-table" data-abah-no-table-guard="1"><thead><tr><th scope="col">PIC / peran</th><th scope="col">KC & jalur</th><th scope="col">Prospek</th><th scope="col">Belum mulai</th><th scope="col">Berjalan</th><th scope="col">Selesai</th><th scope="col">Lewat / mandek</th><th scope="col" class="em-right">Realisasi</th></tr></thead><tbody>
            @forelse($picPaginator as $group)@php $p=$group['stats']; $picLink=['kc'=>mb_strtolower($group['kc']),'pic'=>$group['pic'] ?: '__unassigned','jalur'=>$group['jalur'],'peran'=>$group['pic']!=='' ? '' : ($group['peran'] ?: '__empty')]; @endphp<tr><td><a href="{{ $detailUrl($picLink) }}">{{ $group['label'] }}</a></td><td>{{ $group['kc'] }}<span class="em-subtle">{{ $group['jalur'] }}</span></td><td><a href="{{ $detailUrl($picLink) }}">{{ $p['total'] }}</a></td><td>{{ $p['statuses']['Belum mulai'] }}</td><td>{{ $p['statuses']['Berjalan'] }}</td><td>{{ $p['statuses']['Selesai'] }}</td><td>{{ $p['conditions']['late'] }} / {{ $p['conditions']['stale'] }}</td><td class="em-right">{{ $money($p['realization']) }}</td></tr>
@empty
<tr><td colspan="8"><div class="em-empty">Tidak ada PIC sesuai filter.</div></td></tr>
@endforelse

            </tbody></table></div><div class="em-pagination"><span class="em-subtle">{{ $picPaginator->firstItem() ?? 0 }}?{{ $picPaginator->lastItem() ?? 0 }} dari {{ $picPaginator->total() }} kelompok PIC</span><div class="em-actions">@if($picPaginator->previousPageUrl())<a class="em-btn" href="{{ $picPaginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>@endif @if($picPaginator->nextPageUrl())<a class="em-btn" href="{{ $picPaginator->nextPageUrl() }}" rel="next">Berikutnya</a>@endif</div></div></section>
        
@endif


        @if($activeView==='ecosystems')
            @if($s['unmapped_ecosystem']>0)<p class="em-notice" style="padding:.75rem 1rem">{{ $s['unmapped_ecosystem'] }} prospek belum terhubung ke master ekosistem. Data ini tetap dihitung dalam total prospek. <a href="{{ $detailUrl(['condition'=>'unmapped']) }}">Lihat daftar</a></p>@endif
            <section class="em-panel em-section" aria-labelledby="em-rings-title"><div class="em-title-row"><div><h2 id="em-rings-title">Cakupan ring per ekosistem</h2><p>Klik ring untuk melihat prospek. Rincian arah: akuisisi, pendalaman, dan pendataan.</p></div></div><div class="em-scroll" tabindex="0" role="region" aria-label="Cakupan ring ekosistem" data-abah-no-table-guard="1"><table class="em-table" data-abah-no-table-guard="1"><thead><tr><th scope="col">Ekosistem</th><th scope="col">KC</th><th scope="col">L1 · Inti</th><th scope="col">L2 · Pemasok</th><th scope="col">L3 · Turunan</th><th scope="col">Belum dipetakan</th><th scope="col">Selesai</th></tr></thead><tbody>
            @forelse($ecosystems as $eco)@php $ecoRows=array_values(array_filter($prospects,static fn($p)=>$p['ID_Ekosistem']===$eco['ID_Ekosistem'])); @endphp<tr><td><a href="{{ $detailUrl(['eco'=>$eco['ID_Ekosistem']]) }}">{{ $eco['Nama_Ekosistem'] }}</a></td><td>{{ $eco['KC'] }}</td>@foreach(['L1','L2','L3',''] as $ring)@php $rr=array_values(array_filter($ecoRows,static fn($p)=>($p['Ring'] ?? '')===$ring)); $directions=array_count_values(array_column($rr,'Arah')); @endphp<td class="em-ring-cell"><a href="{{ $detailUrl(['eco'=>$eco['ID_Ekosistem'],'ring'=>$ring ?: '__empty']) }}">{{ count($rr) }} prospek</a><span>{{ $directions['Akuisisi'] ?? 0 }} akuisisi · {{ $directions['Pendalaman'] ?? 0 }} pendalaman · {{ $directions['Pendataan'] ?? 0 }} pendataan</span></td>
@endforeach
<td>{{ $eco['status']['Selesai'] }} / {{ $eco['total'] }}</td></tr>
@empty
<tr><td colspan="7"><div class="em-empty">Tidak ada ekosistem sesuai filter.</div></td></tr>
@endforelse

            </tbody></table></div></section>
            <div class="em-eco-grid">@foreach($ecosystems as $eco)<article class="em-eco"><span class="em-badge em-badge--info">KC {{ $eco['KC'] }}</span><h3>{{ $eco['Nama_Ekosistem'] }}</h3><p>{{ $eco['Jenis'] }}{{ ($eco['Lokasi'] ?? '')!=='' ? ' · '.$eco['Lokasi'] : '' }}</p>@if(($eco['Fakta_Kunci'] ?? '')!=='')<p>{{ $eco['Fakta_Kunci'] }}</p>
@endif
<p><strong>Posisi BRI:</strong> {{ ($eco['Posisi_BRI'] ?? '') ?: 'Belum dicatat' }}</p><p><strong>Bank pesaing:</strong> {{ ($eco['Bank_Pesaing'] ?? '') ?: 'Belum dicatat' }}</p><div class="em-eco-rings">@foreach(['L1','L2','L3'] as $ring)<a href="{{ $detailUrl(['eco'=>$eco['ID_Ekosistem'],'ring'=>$ring]) }}" title="{{ $ringOptions[$ring] }}">{{ $ring }}<b>{{ $eco['ring'][$ring] }}</b>prospek</a>
@endforeach
</div></article>
@endforeach
</div>
        
@endif


        @if($activeView==='prospects')
            <section class="em-panel em-section" aria-labelledby="em-prospects-title"><div class="em-title-row"><div><h2 id="em-prospects-title">Detail prospek</h2><p>Klik nama prospek untuk membuka seluruh rincian dalam mode baca.</p></div><span class="em-subtle" id="em-record-count">{{ $num($paginator->total()) }} prospek sesuai filter</span></div>
            @if($paginator->isEmpty())<div class="em-empty"><strong>Tidak ada prospek sesuai filter</strong>Coba perluas pilihan wilayah atau reset filter. <a href="{{ route('ekosistem.index',['view'=>'prospects','kc'=>$selectedKc]) }}">Reset filter</a></div>
@else

            <div class="em-scroll" data-abah-no-table-guard="1"><table class="em-table em-prospect-table" data-abah-no-table-guard="1"><caption class="sr-only">Daftar prospek sesuai filter. Klik nama untuk membuka rincian.</caption><thead><tr><th scope="col">Prospek & ekosistem</th><th scope="col">PIC / jalur RM</th><th scope="col">Status & kondisi</th><th scope="col">Target</th><th scope="col" class="em-right">Estimasi</th><th scope="col" class="em-right">Realisasi</th></tr></thead><tbody>
            @foreach($paginator as $row)<tr data-prospect-row="{{ $row['ID_Prospek'] }}"><td class="em-prospect" data-label="Prospek"><button type="button" data-em-detail="{{ $row['ID_Prospek'] }}" aria-haspopup="dialog">{{ $row['Prospek'] }}</button><span class="em-subtle">{{ $row['ID_Prospek'] }} · KC {{ $row['KC'] }} · {{ $row['Ekosistem'] }}</span><span class="em-subtle">{{ $row['Produk'] }} · {{ $row['Ring'] ?: 'Ring belum diisi' }} · {{ $row['Arah'] }}</span>@if(!empty($row['Cek_Satuan']))<span class="em-badge em-badge--warning">Cek satuan nominal</span>
@endif
</td><td data-label="PIC / jalur">{{ $row['Nama_PIC'] ?: 'Belum diisi' }}<span class="em-subtle">{{ $row['Jalur_RM'] }}</span></td><td data-label="Status"><span class="em-badge em-badge--{{ $statusTone[$row['Status']] ?? 'muted' }}">{{ $row['Status'] }}</span>@if(!in_array($row['Kondisi']['key'],['done','cancel']))<span class="em-subtle"><span class="em-badge em-badge--{{ $row['Kondisi']['tone'] }}">{{ $row['Kondisi']['label'] }}</span></span>
@endif
<span class="em-subtle">{{ $row['Tahap'] }}</span></td><td data-label="Target">{{ $date($row['Target_Selesai']) }}</td><td class="em-right" data-label="Estimasi">{{ $range($row['Est_Min_Juta'] ?? null,$row['Est_Max_Juta'] ?? null) }}<span class="em-subtle">{{ ($row['Jenis_Potensi'] ?? '') ?: 'Belum diklasifikasi' }}@if(($row['Est_Nasabah'] ?? null)!==null) · {{ $num($row['Est_Nasabah']) }} nasabah
@endif
</span></td><td class="em-right" data-label="Realisasi">{{ $money($row['Realisasi_Nominal_Juta'] ?? null) }}<span class="em-subtle">{{ $num($row['Realisasi_Nasabah'] ?? 0) }} nasabah</span></td></tr>
@endforeach

            </tbody></table></div><div class="em-pagination"><span class="em-subtle">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $num($paginator->total()) }} prospek · halaman {{ $paginator->currentPage() }}/{{ $paginator->lastPage() }}</span><div class="em-actions">@if($paginator->previousPageUrl())<a class="em-btn" rel="prev" href="{{ $paginator->previousPageUrl() }}">Sebelumnya</a>
@endif
 @if($paginator->nextPageUrl())<a class="em-btn" rel="next" href="{{ $paginator->nextPageUrl() }}">Berikutnya</a>
@endif
</div></div>
            
@endif
</section>
            <dialog class="em-dialog" id="em-detail-dialog" aria-labelledby="em-detail-title"><div class="em-dialog-head"><div><span class="em-subtle" id="em-detail-id"></span><h2 id="em-detail-title">Rincian prospek</h2></div><button type="button" class="em-btn" id="em-detail-close" aria-label="Tutup rincian prospek">Tutup</button></div><div class="em-dialog-body" id="em-detail-body"></div></dialog>
        
@endif

        <p class="em-method">Nominal sumber menggunakan juta rupiah. Simpanan, kredit, dan arus dana tahunan disajikan terpisah. Estimasi dan target nasabah bersifat indikatif dan dapat tumpang tindih. Monitoring ini hanya membaca sumber; pembaruan tetap mengikuti proses pada spreadsheet/webapp.</p>
    
@endif

</div>
@endsection

@section('scripts')
<script>
(function () {
    const refresh = document.querySelector('[data-em-refresh]');
    if (refresh) refresh.addEventListener('click', function (event) {
        if (this.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
        this.setAttribute('aria-busy', 'true');
        this.textContent = 'Memperbarui…';
    });
    const form = document.querySelector('.em-filter');
    if (form) form.addEventListener('submit', function () {
        const button = this.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Menerapkan…';
    });
    const dialog = document.getElementById('em-detail-dialog');
    if (!dialog) return;
    const records = @json($detailRecords);
    const number = new Intl.NumberFormat('id-ID', {maximumFractionDigits: 1});
    const nominal = value => value === null || value === undefined || value === '' ? 'Belum dicatat' : 'Rp'+number.format(Number(value))+' juta';
    const body = document.getElementById('em-detail-body');
    const close = document.getElementById('em-detail-close');
    document.querySelectorAll('[data-em-detail]').forEach(button => button.addEventListener('click', function () {
        const record = records[this.dataset.emDetail];
        if (!record) return;
        document.getElementById('em-detail-title').textContent = record.Prospek;
        document.getElementById('em-detail-id').textContent = record.ID_Prospek+' · KC '+record.KC+' · Mode baca';
        body.replaceChildren();
        const sections = [
            ['Identitas & cakupan', [['Ekosistem',record.Ekosistem],['Jalur RM',record.Jalur_RM],['Produk',record.Produk],['Sasaran',record.Sasaran],['Ring',record.Ring],['Simpul',record.Simpul],['Arah garapan',record.Arah],['Status di BRI',record.Status_BRI],['Bank lain',record.Bank_Lain]]],
            ['PIC & tindak lanjut', [['PIC',record.Nama_PIC],['Peran',record.Peran_PIC],['Status',record.Status],['Kondisi',record.Kondisi.label],['Tahap',record.Tahap],['Mulai',record.Tgl_Mulai],['Target',record.Target_Selesai],['Selesai',record.Tgl_Selesai],['Prasyarat',record.Prasyarat_ID],['Pembaruan',record.Diubah_Pada]]],
            ['Potensi & realisasi', [['Jenis potensi',record.Jenis_Potensi],['Estimasi minimum',nominal(record.Est_Min_Juta)],['Estimasi maksimum',nominal(record.Est_Max_Juta)],['Target nasabah',record.Est_Nasabah],['Realisasi',nominal(record.Realisasi_Nominal_Juta)],['Nasabah terealisasi',record.Realisasi_Nasabah],['Dasar angka',record.Dasar_Angka],['Pemeriksaan satuan',(record.Cek_Satuan || []).map(issue=>issue.label+': '+number.format(issue.value)+' juta (nilai asli; tidak masuk rekap)').join('\n')]]],
            ['Sumber & catatan', [['Sumber',record.Sumber],['Catatan analis',record.Catatan_Analis],['Kendala',record.Kendala],['Catatan tindak lanjut',record.Catatan]]]
        ];
        sections.forEach(([title, fields]) => {
            const section = document.createElement('section');
            const heading = document.createElement('h3'); heading.textContent = title;
            const list = document.createElement('dl');
            fields.forEach(([label, value]) => {
                const term = document.createElement('dt'); term.textContent = label;
                const definition = document.createElement('dd'); definition.textContent = value === null || value === undefined || value === '' ? 'Belum diisi' : String(value);
                list.append(term, definition);
            });
            section.append(heading, list); body.append(section);
        });
        dialog.showModal();
        close.focus();
    }));
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) {const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom) dialog.close();} });
})();
</script>
@endsection
