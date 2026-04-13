@extends('layouts.admin')

@section('title', 'Rekening Dormant')

@section('content')
<style>
    .dormant-dashboard{padding-bottom:1.5rem}
    .dormant-shell,.dormant-table-shell{border:1px solid #dbe5ef;border-radius:18px;background:#fff;box-shadow:0 14px 30px -24px rgba(15,23,42,.22)}
    .dormant-shell{overflow:visible}
    .dormant-shell .card-body,.dormant-table-shell .card-body{background:#fff}
    .dormant-shell .card-body{overflow:visible}
    .dormant-page-title{font-size:clamp(1.45rem,2.4vw,2rem);font-weight:800;color:#0f172a;margin-bottom:.2rem}
    .dormant-filter-grid .form-group{margin-bottom:1rem}
    .dormant-filter-label{font-size:.86rem;font-weight:700;color:#0f172a;margin-bottom:.45rem}
    .dormant-filter-control,.dormant-filter-control.select2-selection{border-radius:14px!important;min-height:44px!important;height:44px!important;border-color:#cfdae6!important;background:#fff!important;font-size:.94rem;display:flex;align-items:center}
    .dormant-filter-control:disabled{background:#edf2f7!important;color:#64748b!important}
    .dormant-filter-dropdown{position:relative}
    .dormant-dropdown-toggle{display:flex;align-items:center;justify-content:space-between;text-align:left;background:#fff}
    .dormant-dropdown-toggle:disabled{background:#edf2f7;cursor:not-allowed;opacity:1}
    .dormant-dropdown-label{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .dormant-dropdown-menu{position:absolute;top:calc(100% + 6px);left:0;right:0;z-index:1080;display:none;width:100%;max-height:260px;overflow-y:auto;background:#fff;border:1px solid #dee2e6;border-radius:12px;box-shadow:0 10px 30px rgba(15,23,42,.12);padding:8px 0}
    .dormant-dropdown-menu.show{display:block}
    .dormant-dropdown-menu .dropdown-item{padding:.45rem 1rem;cursor:pointer;margin-bottom:0}
    .dormant-dropdown-menu .form-check{display:flex;align-items:center;gap:8px}
    .dormant-dropdown-menu .form-check-input{position:static;margin:0}
    .dormant-dropdown-menu .form-check-label{margin:0;font-weight:500;cursor:pointer}
    .dormant-filter-meta{display:flex;flex-wrap:wrap;gap:.8rem;color:#64748b;font-size:.84rem;margin-top:.15rem}
    .dormant-loading-chip{display:inline-flex;align-items:center;gap:.55rem;border-radius:999px;padding:.55rem .9rem;background:linear-gradient(135deg,#eff6ff,#ecfeff);color:#0f766e;font-size:.8rem;font-weight:800}
    .dormant-loading-dot{width:10px;height:10px;border-radius:999px;background:#14b8a6;animation:dormantPulse 1.6s infinite}
    @keyframes dormantPulse{0%{transform:scale(.95)}70%{transform:scale(1)}100%{transform:scale(.95)}}
    .dormant-table-heading{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:1rem}
    .dormant-table-heading h5{margin:0;font-size:1.05rem;font-weight:800;color:#0f172a}
    .dormant-table-unit{margin-top:.35rem;color:#64748b;font-size:.82rem;font-weight:700}
    .dormant-table-badge{display:inline-flex;align-items:center;gap:.4rem;border-radius:999px;padding:.45rem .7rem;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;font-size:.79rem;font-weight:700}
    .dormant-table-stage{position:relative;min-height:420px}
    .dormant-loading-overlay{position:absolute;inset:0;z-index:5;display:flex;flex-direction:column;gap:1rem;justify-content:center;align-items:center;border-radius:18px;background:linear-gradient(180deg,rgba(255,255,255,.92),rgba(248,250,252,.96));backdrop-filter:blur(4px);transition:opacity .28s ease,visibility .28s ease}
    .dormant-loading-overlay.is-hidden{opacity:0;visibility:hidden;pointer-events:none}
    .dormant-loading-title{font-size:1rem;font-weight:800;color:#0f172a}
    .dormant-loading-copy{max-width:480px;text-align:center;color:#64748b;font-size:.9rem;margin:0}
    .dormant-skeleton-grid{width:min(760px,100%);display:grid;grid-template-columns:220px repeat(4,1fr);gap:.75rem}
    .dormant-skeleton-cell{height:16px;border-radius:999px;background:linear-gradient(90deg,#e2e8f0 25%,#f8fafc 50%,#e2e8f0 75%);background-size:220% 100%;animation:dormantShimmer 1.3s infinite linear}
    .dormant-skeleton-cell.is-wide{height:18px}
    @keyframes dormantShimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
    .dormant-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
    .dormant-table{width:100%;min-width:860px;border-collapse:separate;border-spacing:0}
    .dormant-table th,.dormant-table td{padding:14px 12px;border-right:1px solid rgba(255,255,255,.3);border-bottom:1px solid rgba(255,255,255,.3);text-align:right;vertical-align:middle}
    .dormant-table thead th{color:#fff;font-size:.86rem;font-weight:800;text-align:center}
    .dormant-table .head-branch{background:#f59e0b;text-align:left;min-width:210px}
    .dormant-table .head-group,.dormant-table .head-sub{background:#1d4ed8}
    .dormant-table tbody th{background:#fb923c;color:#fff;text-align:left;font-size:.9rem;font-weight:800}
    .dormant-table tbody td{background:#e8f1fb;color:#334155;font-weight:700;white-space:nowrap}
    .dormant-table tbody td.metric-current{background:#fff;color:#0f172a}
    .dormant-table tbody td.metric-positive{background:#dcfce7;color:#166534}
    .dormant-table tbody td.metric-negative{background:#fee2e2;color:#b91c1c}
    .dormant-table tbody td.metric-neutral{background:#f8fafc;color:#64748b}
    .dormant-table tfoot th,.dormant-table tfoot td{background:#0f172a;color:#fff;font-weight:800}
    .dormant-empty-state{padding:3rem 1rem;text-align:center;color:#64748b}
    .dormant-empty-state strong{display:block;margin-bottom:.4rem;color:#0f172a}
</style>

<div class="dormant-dashboard">

    <div class="card dormant-shell mb-4">
        <div class="card-body p-4">
            <form id="dormantFilterForm" method="GET" action="{{ route('report.rekening-dormant') }}">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3">
                    <div>
                        <h2 class="dormant-page-title">Rekening Dormant</h2>
                        <div class="dormant-filter-meta">
                            <span>Periode aktif: <strong id="dormantActivePeriodMeta">-</strong></span>
                            <span>M-1: <strong id="dormantComparisonPeriodMeta">-</strong></span>
                        </div>
                    </div>
                </div>

                <div class="row dormant-filter-grid">
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <div class="form-group">
                            <label class="dormant-filter-label">Branch Office (Kanca)</label>
                            <div class="dormant-filter-dropdown">
                                <button type="button" id="dormantBranchDropdown" class="form-control dormant-filter-control dormant-dropdown-toggle" aria-haspopup="true" aria-expanded="false">
                                    <span id="dormantBranchLabel" class="dormant-dropdown-label">Area 6 - All</span>
                                    <i class="fas fa-chevron-down text-muted"></i>
                                </button>
                                <div id="dormantBranchMenu" class="dormant-dropdown-menu" aria-labelledby="dormantBranchDropdown">
                                    <div class="dropdown-item text-muted small">Memuat opsi...</div>
                                </div>
                            </div>
                            <div id="dormantBranchInputs"></div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <div class="form-group">
                            <label class="dormant-filter-label">Nama Uker</label>
                            <div class="dormant-filter-dropdown">
                                <button type="button" id="dormantUnitDropdown" class="form-control dormant-filter-control dormant-dropdown-toggle" aria-haspopup="true" aria-expanded="false" disabled>
                                    <span id="dormantUnitLabel" class="dormant-dropdown-label">ALL UKER</span>
                                    <i class="fas fa-chevron-down text-muted"></i>
                                </button>
                                <div id="dormantUnitMenu" class="dormant-dropdown-menu" aria-labelledby="dormantUnitDropdown">
                                    <div class="dropdown-item text-muted small">Pilih branch office</div>
                                </div>
                            </div>
                            <div id="dormantUnitInputs"></div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-4 col-md-6"><div class="form-group"><label class="dormant-filter-label">Periode</label><input id="dormantPeriodInput" type="date" name="posisi" class="form-control dormant-filter-control" value="{{ $defaultPeriod }}" max="{{ $defaultPeriod }}"></div></div>
                </div>

                <div class="d-flex flex-wrap align-items-center" style="gap:.75rem;">
                    <button id="dormantSubmitButton" type="submit" class="btn btn-primary"><i class="fas fa-filter mr-1"></i>Tampilkan</button>
                    <button id="dormantResetButton" type="button" class="btn btn-light">Reset</button>
                    <div id="dormantLoadingChip" class="dormant-loading-chip d-none"><span class="dormant-loading-dot"></span>Sedang Mengolah</div>
                </div>
            </form>
        </div>
    </div>

    <div class="card dormant-table-shell">
        <div class="card-body p-4">
            <div class="dormant-table-heading">
                <div><h5>Rekening Dormant</h5><div class="dormant-table-unit">Satuan: Jumlah Rekening</div></div>
                <div class="dormant-table-badge"><i class="fas fa-table"></i><span id="dormantPeriodBadge">-</span></div>
            </div>

            <div class="dormant-table-stage">
                <div id="dormantLoadingOverlay" class="dormant-loading-overlay">
                    <div class="dormant-loading-title">Siap Memuat Data</div>
                    <p class="dormant-loading-copy">Pilih filter lalu klik Tampilkan.</p>
                    <div class="dormant-skeleton-grid" aria-hidden="true">
                        @for ($row = 0; $row < 5; $row++)
                            <div class="dormant-skeleton-cell is-wide"></div>
                            @for ($col = 0; $col < 4; $col++)
                                <div class="dormant-skeleton-cell"></div>
                            @endfor
                        @endfor
                    </div>
                </div>

                <div class="dormant-table-wrap">
                    <table class="dormant-table">
                        <thead>
                            <tr><th rowspan="2" class="head-branch dormant-group-label" data-default-label="Branch Office" data-filtered-label="UKER">Branch Office</th><th colspan="4" class="head-group">Rekening Dormant</th></tr>
                            <tr><th id="dormantHeaderCurrent" class="head-sub">Periode Terakhir</th><th id="dormantHeaderMtd" class="head-sub">MtD</th><th id="dormantHeaderYtd" class="head-sub">YtD</th><th id="dormantHeaderYoy" class="head-sub">YoY</th></tr>
                        </thead>
                        <tbody id="dormantTableBody"><tr><td colspan="5" class="dormant-empty-state"><strong>Belum ada data</strong>Klik <strong>Tampilkan</strong>.</td></tr></tbody>
                        <tfoot><tr id="dormantTableFoot"><th>Grand Total</th><td>-</td><td>-</td><td>-</td><td>-</td></tr></tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form=document.getElementById('dormantFilterForm'), periodInput=document.getElementById('dormantPeriodInput'), branchDropdown=document.getElementById('dormantBranchDropdown'), branchMenu=document.getElementById('dormantBranchMenu'), branchLabel=document.getElementById('dormantBranchLabel'), unitDropdown=document.getElementById('dormantUnitDropdown'), unitMenu=document.getElementById('dormantUnitMenu'), unitLabel=document.getElementById('dormantUnitLabel'), branchInputs=document.getElementById('dormantBranchInputs'), unitInputs=document.getElementById('dormantUnitInputs'), submitButton=document.getElementById('dormantSubmitButton'), resetButton=document.getElementById('dormantResetButton'), chip=document.getElementById('dormantLoadingChip'), overlay=document.getElementById('dormantLoadingOverlay'), tableBody=document.getElementById('dormantTableBody'), tableFoot=document.getElementById('dormantTableFoot'), activeMeta=document.getElementById('dormantActivePeriodMeta'), comparisonMeta=document.getElementById('dormantComparisonPeriodMeta'), badge=document.getElementById('dormantPeriodBadge'), currentHeader=document.getElementById('dormantHeaderCurrent'), mtdHeader=document.getElementById('dormantHeaderMtd'), ytdHeader=document.getElementById('dormantHeaderYtd'), yoyHeader=document.getElementById('dormantHeaderYoy');
    const filtersUrl=@json(route('report.rekening-dormant.filters')), dataUrl=@json(route('report.data.rekening-dormant')), csrfToken=@json(csrf_token()), defaultPeriod=@json($defaultPeriod), initialBranches=@json($selectedBranches ?? []), initialUnits=@json($selectedUnits ?? []);
    let activeController=null, activeFilterController=null, branchOptions=[], unitOptions=[], selectedBranches=Array.isArray(initialBranches)?initialBranches:[], selectedUnits=Array.isArray(initialUnits)?initialUnits:[];

    function appendArrayParams(params,key,values){values.forEach(value=>{if(value)params.append(`${key}[]`,value)})}
    function formatDate(value){if(!value)return'-';return new Intl.DateTimeFormat('id-ID').format(new Date(value+'T00:00:00'))}
    function formatNumber(value){if(value===null||value===undefined||value==='')return'-';const number=Number(value);return Number.isNaN(number)?'-':new Intl.NumberFormat('id-ID').format(number)}
    function deltaText(value){const n=Number(value||0);return n>0?`+${formatNumber(n)}`:formatNumber(n)}
    function cellClass(value,isCurrent=false){if(isCurrent)return'metric-current';const n=Number(value||0);if(n>0)return'metric-positive';if(n<0)return'metric-negative';return'metric-neutral'}
    function renderRows(rows){if(!rows||rows.length===0){tableBody.innerHTML=`<tr><td colspan="5" class="dormant-empty-state"><strong>Data tidak ditemukan</strong>Coba ubah periode atau filter branch office agar hasil report tersedia.</td></tr>`;return}tableBody.innerHTML=rows.map(row=>`<tr><th>${row.branch||'-'}</th><td class="${cellClass(row.current,true)}">${formatNumber(row.current)}</td><td class="${cellClass(row.mtd)}">${deltaText(row.mtd)}</td><td class="${cellClass(row.ytd)}">${deltaText(row.ytd)}</td><td class="${cellClass(row.yoy)}">${deltaText(row.yoy)}</td></tr>`).join('')}
    function renderFoot(total={}){tableFoot.innerHTML=`<th>Grand Total</th><td>${formatNumber(total.current??null)}</td><td>${deltaText(total.mtd??null)}</td><td>${deltaText(total.ytd??null)}</td><td>${deltaText(total.yoy??null)}</td>`}
    function updateHeaders(labels={}){currentHeader.textContent=labels.curr&&labels.curr!=='-'?labels.curr:'Periode Terakhir';mtdHeader.textContent=labels.mtd&&labels.mtd!=='-'?`MtD vs ${labels.mtd}`:'MtD';ytdHeader.textContent=labels.ytd&&labels.ytd!=='-'?`YtD vs ${labels.ytd}`:'YtD';yoyHeader.textContent=labels.yoy&&labels.yoy!=='-'?`YoY vs ${labels.yoy}`:'YoY'}
    function updateGroupLabel(groupLabel){document.querySelectorAll('.dormant-group-label').forEach(el=>{el.textContent=groupLabel==='UKER'?(el.dataset.filteredLabel||'UKER'):(el.dataset.defaultLabel||'Branch Office')})}
    function setOverlay(title, copy){overlay.classList.remove('is-hidden');overlay.querySelector('.dormant-loading-title').textContent=title;overlay.querySelector('.dormant-loading-copy').textContent=copy}
    function hideOverlay(){overlay.classList.add('is-hidden')}
    function resetTableState(){updateGroupLabel('BRANCH OFFICE');tableBody.innerHTML=`<tr><td colspan="5" class="dormant-empty-state"><strong>Belum ada data</strong>Klik <strong>Tampilkan</strong>.</td></tr>`;renderFoot({});activeMeta.textContent='-';comparisonMeta.textContent='-';badge.textContent='-';updateHeaders({});setOverlay('Siap Memuat Data','Pilih filter lalu klik Tampilkan.')}
    function renderHiddenInputs(container,name,values){container.innerHTML=values.map(value=>`<input type="hidden" name="${name}[]" value="${String(value).replace(/"/g,'&quot;')}">`).join('')}
    function updateBranchLabel(){branchLabel.textContent=selectedBranches.length>0?selectedBranches.join(', '):'Area 6 - All'}
    function updateUnitLabel(){unitLabel.textContent=selectedUnits.length>0?selectedUnits.join(', '):'ALL UKER'}
    function closeMenus(except=null){if(except!=='branch'){branchMenu.classList.remove('show');branchDropdown.setAttribute('aria-expanded','false')}if(except!=='unit'){unitMenu.classList.remove('show');unitDropdown.setAttribute('aria-expanded','false')}}
    function getCheckedValues(selector){return Array.from(document.querySelectorAll(selector)).filter(el=>el.checked).map(el=>String(el.value)).filter(Boolean)}
    function renderBranchMenu(){if(branchOptions.length===0){branchMenu.innerHTML='<div class="dropdown-item text-muted small">Tidak ada opsi</div>';return}branchMenu.innerHTML=branchOptions.map((item,index)=>{const value=String(item.value??item), label=String(item.label??item), checked=selectedBranches.includes(value)?'checked':'';return `<label class="dropdown-item" for="dormant_branch_${index}"><div class="form-check"><input class="form-check-input dormant-branch-checkbox" type="checkbox" value="${value}" id="dormant_branch_${index}" ${checked}><span class="form-check-label">${label}</span></div></label>`}).join('');document.querySelectorAll('.dormant-branch-checkbox').forEach(checkbox=>{checkbox.addEventListener('change',function(){selectedBranches=getCheckedValues('.dormant-branch-checkbox');selectedUnits=[];renderHiddenInputs(branchInputs,'kantor_cabang',selectedBranches);renderHiddenInputs(unitInputs,'unit_kerja',selectedUnits);updateBranchLabel();updateUnitLabel();loadFilterOptions()})})}
    function renderUnitMenu(){if(selectedBranches.length===0){unitDropdown.disabled=true;unitMenu.innerHTML='<div class="dropdown-item text-muted small">Pilih branch office</div>';selectedUnits=[];renderHiddenInputs(unitInputs,'unit_kerja',selectedUnits);updateUnitLabel();return}unitDropdown.disabled=false;if(unitOptions.length===0){unitMenu.innerHTML='<div class="dropdown-item text-muted small">Tidak ada opsi</div>';selectedUnits=[];renderHiddenInputs(unitInputs,'unit_kerja',selectedUnits);updateUnitLabel();return}unitMenu.innerHTML=unitOptions.map((item,index)=>{const value=String(item.value??item), label=String(item.label??item), checked=selectedUnits.includes(value)?'checked':'';return `<label class="dropdown-item" for="dormant_unit_${index}"><div class="form-check"><input class="form-check-input dormant-unit-checkbox" type="checkbox" value="${value}" id="dormant_unit_${index}" ${checked}><span class="form-check-label">${label}</span></div></label>`}).join('');document.querySelectorAll('.dormant-unit-checkbox').forEach(checkbox=>{checkbox.addEventListener('change',function(){selectedUnits=getCheckedValues('.dormant-unit-checkbox');renderHiddenInputs(unitInputs,'unit_kerja',selectedUnits);updateUnitLabel()})})}
    function applyFilterPayload(payload){branchOptions=payload.branch_options||[];unitOptions=payload.unit_options||[];selectedBranches=payload.selected_branches||[];selectedUnits=payload.selected_units||[];renderHiddenInputs(branchInputs,'kantor_cabang',selectedBranches);renderHiddenInputs(unitInputs,'unit_kerja',selectedUnits);renderBranchMenu();renderUnitMenu();updateBranchLabel();updateUnitLabel();activeMeta.textContent=formatDate(payload.selected_period);comparisonMeta.textContent=formatDate(payload.comparison_period)}
    async function loadFilterOptions(){if(activeFilterController)activeFilterController.abort();if(!periodInput.value){branchOptions=[];unitOptions=[];selectedBranches=[];selectedUnits=[];renderHiddenInputs(branchInputs,'kantor_cabang',selectedBranches);renderHiddenInputs(unitInputs,'unit_kerja',selectedUnits);renderBranchMenu();renderUnitMenu();activeMeta.textContent='-';comparisonMeta.textContent='-';return}activeFilterController=new AbortController();const timeoutId=window.setTimeout(()=>activeFilterController?.abort('timeout'),15000);branchDropdown.disabled=true;unitDropdown.disabled=true;branchMenu.innerHTML='<div class="dropdown-item text-muted small">Memuat opsi...</div>';unitMenu.innerHTML='<div class="dropdown-item text-muted small">Memuat opsi...</div>';const params=new URLSearchParams();params.set('posisi',periodInput.value);appendArrayParams(params,'kantor_cabang',selectedBranches);appendArrayParams(params,'unit_kerja',selectedUnits);try{const response=await fetch(`${filtersUrl}?${params.toString()}`,{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},signal:activeFilterController.signal});if(!response.ok)throw new Error('Gagal memuat opsi filter');const payload=await response.json();applyFilterPayload(payload)}catch(error){if(error.name!=='AbortError'){branchOptions=[];unitOptions=[];selectedBranches=[];selectedUnits=[];renderHiddenInputs(branchInputs,'kantor_cabang',selectedBranches);renderHiddenInputs(unitInputs,'unit_kerja',selectedUnits);renderBranchMenu();renderUnitMenu();activeMeta.textContent='-';comparisonMeta.textContent='-'}}finally{window.clearTimeout(timeoutId);branchDropdown.disabled=!periodInput.value;if(selectedBranches.length===0){unitDropdown.disabled=true}}}

    async function loadReport(pushHistory=false){if(activeController)activeController.abort();activeController=new AbortController();const formData=new FormData(form), params=new URLSearchParams();for(const [key,value] of formData.entries()){if(value)params.append(key,value)}chip.classList.remove('d-none');submitButton.disabled=true;setOverlay('Sedang Mengolah','Memproses data rekening dormant.');try{const response=await fetch(dataUrl,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN':csrfToken,'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:params.toString(),signal:activeController.signal});if(!response.ok)throw new Error('Gagal memuat report');const payload=await response.json();updateGroupLabel(payload.group_label||'BRANCH OFFICE');renderRows(payload.data||[]);renderFoot(payload.total||{});updateHeaders(payload.labels||{});activeMeta.textContent=formatDate(payload.effective_dates?.curr);comparisonMeta.textContent=formatDate(payload.effective_dates?.mtd);badge.textContent=`${formatDate(payload.effective_dates?.curr)} | ${(payload.data||[]).length||0} row`;hideOverlay();if(pushHistory){const pageUrl=new URL(@json(route('report.rekening-dormant')),window.location.origin);params.forEach((value,key)=>pageUrl.searchParams.append(key,value));window.history.replaceState({},'',pageUrl.toString())}}catch(error){if(error.name!=='AbortError'){updateGroupLabel('BRANCH OFFICE');tableBody.innerHTML=`<tr><td colspan="5" class="dormant-empty-state"><strong>Gagal memuat report</strong>Coba ulangi.</td></tr>`;renderFoot({});badge.textContent='-';setOverlay('Siap Memuat Data','Silakan coba lagi.')}}finally{chip.classList.add('d-none');submitButton.disabled=false}}

    form.addEventListener('submit',function(event){event.preventDefault();loadReport(true)});
    branchDropdown.addEventListener('click',function(event){event.preventDefault();if(branchDropdown.disabled)return;const willShow=!branchMenu.classList.contains('show');closeMenus();branchMenu.classList.toggle('show',willShow);branchDropdown.setAttribute('aria-expanded',willShow?'true':'false')});
    unitDropdown.addEventListener('click',function(event){event.preventDefault();if(unitDropdown.disabled)return;const willShow=!unitMenu.classList.contains('show');closeMenus();unitMenu.classList.toggle('show',willShow);unitDropdown.setAttribute('aria-expanded',willShow?'true':'false')});
    document.addEventListener('click',function(event){if(!event.target.closest('.dormant-filter-dropdown'))closeMenus()});
    periodInput.addEventListener('change',function(){selectedBranches=[];selectedUnits=[];renderHiddenInputs(branchInputs,'kantor_cabang',selectedBranches);renderHiddenInputs(unitInputs,'unit_kerja',selectedUnits);updateBranchLabel();updateUnitLabel();resetTableState();loadFilterOptions()});
    resetButton.addEventListener('click',function(){periodInput.value=defaultPeriod;selectedBranches=[];selectedUnits=[];renderHiddenInputs(branchInputs,'kantor_cabang',selectedBranches);renderHiddenInputs(unitInputs,'unit_kerja',selectedUnits);updateBranchLabel();updateUnitLabel();branchOptions=[];unitOptions=[];renderBranchMenu();renderUnitMenu();resetTableState();loadFilterOptions()});
    resetTableState();loadFilterOptions();
});
</script>
@endsection
