@extends('layouts.admin')

@section('title', 'Rekening Dormant')

@section('content')
<style>
    .dormant-dashboard{padding-bottom:1.5rem}
    .dormant-shell,.dormant-table-shell{border:1px solid #dbe5ef;border-radius:18px;background:#fff;box-shadow:0 14px 30px -24px rgba(15,23,42,.22)}
    .dormant-shell .card-body,.dormant-table-shell .card-body{background:#fff}
    .dormant-page-title{font-size:clamp(1.7rem,2.7vw,2.4rem);font-weight:800;color:#0f172a;margin-bottom:.45rem}
    .dormant-filter-grid .form-group{margin-bottom:1rem}
    .dormant-filter-label{font-size:.86rem;font-weight:700;color:#0f172a;margin-bottom:.45rem}
    .dormant-filter-control,.dormant-filter-control.select2-selection{border-radius:14px!important;min-height:44px!important;height:44px!important;border-color:#cfdae6!important;background:#fff!important;font-size:.94rem;display:flex;align-items:center}
    .dormant-filter-control:disabled{background:#edf2f7!important;color:#64748b!important}
    .select2-container--bootstrap4 .select2-selection--multiple.dormant-filter-control{min-height:44px!important;height:44px!important;padding:0 2rem 0 .75rem!important;display:flex!important;align-items:center!important;overflow:hidden!important}
    .select2-container--bootstrap4 .select2-selection--multiple.dormant-filter-control .select2-selection__choice{display:none!important}
    .select2-container--bootstrap4 .select2-selection--multiple.dormant-filter-control .select2-selection__rendered{display:block!important;width:100%!important;padding:0!important;margin:0!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;line-height:44px!important;color:#475569!important;font-size:.94rem!important}
    .select2-container--bootstrap4 .select2-selection--multiple.dormant-filter-control .select2-search--inline{position:absolute!important;inset:0!important;width:100%!important;height:100%!important;margin:0!important}
    .select2-container--bootstrap4 .select2-selection--multiple.dormant-filter-control .select2-search__field{width:100%!important;height:100%!important;margin:0!important;opacity:0!important;cursor:pointer!important}
    .select2-container--bootstrap4 .select2-selection--multiple.dormant-filter-control .select2-selection__clear{position:absolute!important;right:.75rem!important;top:50%!important;margin:0!important;transform:translateY(-50%)!important;line-height:1!important}
    .select2-container--bootstrap4 .select2-selection--single.dormant-filter-control{height:44px!important;padding:0 2rem 0 .75rem!important;display:flex!important;align-items:center!important}
    .dormant-select2-option{display:flex;align-items:center;gap:.5rem}.dormant-select2-option input{pointer-events:none}
    .dormant-filter-summary-empty{color:#64748b!important}
    .dormant-filter-meta{display:flex;flex-wrap:wrap;gap:1rem;color:#64748b;font-size:.84rem;margin-top:.25rem}
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
    <div class="mb-4"><h2 class="dormant-page-title">Rekening Dormant</h2></div>

    <div class="card dormant-shell mb-4">
        <div class="card-body p-4">
            <form id="dormantFilterForm" method="GET" action="{{ route('report.rekening-dormant') }}">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3">
                    <div>
                        <h5 class="mb-1 font-weight-bold text-dark">Filter Report</h5>
                        <div class="dormant-filter-meta">
                            <span>Periode aktif: <strong id="dormantActivePeriodMeta">-</strong></span>
                            <span>Pembanding M-1: <strong id="dormantComparisonPeriodMeta">-</strong></span>
                            <span>Sumber: <strong>Status 9 - Simpanan Multi PN</strong></span>
                        </div>
                    </div>
                </div>

                <div class="row dormant-filter-grid">
                    <div class="col-xl-2 col-lg-4 col-md-6"><div class="form-group"><label class="dormant-filter-label">Nama Report</label><input type="text" class="form-control dormant-filter-control" value="Rekening Dormant" disabled></div></div>
                    <div class="col-xl-2 col-lg-4 col-md-6"><div class="form-group"><label class="dormant-filter-label">Branch Office</label><select id="dormantBranchSelect" name="kantor_cabang[]" class="form-control select2 dormant-filter-control" multiple data-placeholder="Semua Branch Office" data-selected='@json($selectedBranches ?? [])'><option value="">Pilih periode dulu</option></select></div></div>
                    <div class="col-xl-2 col-lg-4 col-md-6"><div class="form-group"><label class="dormant-filter-label">Nama Uker</label><select id="dormantUnitSelect" name="unit_kerja[]" class="form-control select2 dormant-filter-control" multiple data-placeholder="ALL UKER" data-selected='@json($selectedUnits ?? [])'><option value="">Pilih branch office dulu</option></select></div></div>
                    <div class="col-xl-2 col-lg-4 col-md-6"><div class="form-group"><label class="dormant-filter-label">Posisi RKA</label><input type="text" class="form-control dormant-filter-control" value="-" disabled></div></div>
                    <div class="col-xl-2 col-lg-4 col-md-6"><div class="form-group"><label class="dormant-filter-label">Periode Terakhir</label><input id="dormantPeriodInput" type="date" name="posisi" class="form-control dormant-filter-control" value="{{ $defaultPeriod }}" max="{{ $defaultPeriod }}"></div></div>
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
                <div><h5>Tabel Rekening Dormant</h5><div class="dormant-table-unit">Satuan: Jumlah Rekening</div></div>
                <div class="dormant-table-badge"><i class="fas fa-table"></i><span id="dormantPeriodBadge">-</span></div>
            </div>

            <div class="dormant-table-stage">
                <div id="dormantLoadingOverlay" class="dormant-loading-overlay">
                    <div class="dormant-loading-title">Siap Memuat Data</div>
                    <p class="dormant-loading-copy">Pilih periode dan cabang terlebih dulu, lalu klik Tampilkan untuk memproses rekening dormant.</p>
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
                            <tr><th rowspan="2" class="head-branch">Branch Office</th><th colspan="4" class="head-group">Rekening Dormant</th></tr>
                            <tr><th id="dormantHeaderCurrent" class="head-sub">Periode Terakhir</th><th id="dormantHeaderMtd" class="head-sub">MtD</th><th id="dormantHeaderYtd" class="head-sub">YtD</th><th id="dormantHeaderYoy" class="head-sub">YoY</th></tr>
                        </thead>
                        <tbody id="dormantTableBody"><tr><td colspan="5" class="dormant-empty-state"><strong>Filter belum dijalankan</strong>Pilih periode atau branch office lalu klik <strong>Tampilkan</strong>.</td></tr></tbody>
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
    const form=document.getElementById('dormantFilterForm'), periodInput=document.getElementById('dormantPeriodInput'), branchSelect=document.getElementById('dormantBranchSelect'), unitSelect=document.getElementById('dormantUnitSelect'), submitButton=document.getElementById('dormantSubmitButton'), resetButton=document.getElementById('dormantResetButton'), chip=document.getElementById('dormantLoadingChip'), overlay=document.getElementById('dormantLoadingOverlay'), tableBody=document.getElementById('dormantTableBody'), tableFoot=document.getElementById('dormantTableFoot'), activeMeta=document.getElementById('dormantActivePeriodMeta'), comparisonMeta=document.getElementById('dormantComparisonPeriodMeta'), badge=document.getElementById('dormantPeriodBadge'), currentHeader=document.getElementById('dormantHeaderCurrent'), mtdHeader=document.getElementById('dormantHeaderMtd'), ytdHeader=document.getElementById('dormantHeaderYtd'), yoyHeader=document.getElementById('dormantHeaderYoy');
    const filtersUrl=@json(route('report.rekening-dormant.filters')), dataUrl=@json(route('report.data.rekening-dormant')), csrfToken=@json(csrf_token()), defaultPeriod=@json($defaultPeriod);
    let activeController=null, activeFilterController=null;

    function parseSelectedDataset(select){try{const parsed=JSON.parse(select.dataset.selected||'[]');return Array.isArray(parsed)?parsed.map(String):[]}catch(error){return[]}}
    function syncSelectedDataset(select){select.dataset.selected=JSON.stringify(window.jQuery(select).val()||[])}
    function buildOptionTemplate(option){if(!option.id)return option.text;const isChecked=option.element?option.element.selected:false, wrapper=document.createElement('span'), checkbox=document.createElement('input'), label=document.createElement('span');wrapper.className='dormant-select2-option';checkbox.type='checkbox';checkbox.checked=isChecked;label.textContent=option.text;wrapper.appendChild(checkbox);wrapper.appendChild(label);return wrapper}
    function initMultiSelect(select, placeholder){if(!(window.jQuery&&window.jQuery.fn&&window.jQuery.fn.select2))return;const $select=window.jQuery(select);if($select.data('select2'))$select.select2('destroy');$select.select2({theme:'bootstrap4',width:'100%',placeholder,closeOnSelect:false,allowClear:true,templateResult:buildOptionTemplate,templateSelection:data=>data.text,escapeMarkup:markup=>markup})}
    function refreshSelectUi(select){if(!(window.jQuery&&window.jQuery.fn&&window.jQuery.fn.select2))return;initMultiSelect(select,select.dataset.placeholder||'');const selectedValues=parseSelectedDataset(select);window.jQuery(select).val(selectedValues).trigger('change.select2');updateSelectSummary(select)}
    function updateSelectSummary(select){if(!(window.jQuery&&window.jQuery.fn&&window.jQuery.fn.select2))return;const $select=window.jQuery(select), select2=$select.data('select2');if(!select2||!select2.$container)return;const items=($select.select2('data')||[]).filter(item=>item&&item.id).map(item=>String(item.text||'').trim()).filter(Boolean);const summary=items.length===0?(select.dataset.placeholder||''):items.length<=2?items.join(', '):`${items.slice(0,2).join(', ')}, ...`;select2.$container.find('.select2-selection__rendered').text(summary).attr('title',items.length?items.join(', '):(select.dataset.placeholder||'')).toggleClass('dormant-filter-summary-empty',items.length===0)}
    function normalizeOptionItem(item){if(item&&typeof item==='object'){return{value:String(item.value??''),label:String(item.label??item.value??'')}}return{value:String(item??''),label:String(item??'')}}
    function setSelectOptions(select, items, selectedValues=[], disabled=false){const normalizedItems=(items||[]).map(normalizeOptionItem).filter(item=>item.value);select.innerHTML='';normalizedItems.forEach(item=>{const option=document.createElement('option');option.value=item.value;option.textContent=item.label;option.selected=selectedValues.includes(item.value);select.appendChild(option)});select.disabled=disabled;select.dataset.selected=JSON.stringify(selectedValues.filter(value=>normalizedItems.some(item=>item.value===String(value))));refreshSelectUi(select)}
    function collectSelectedValues(select){return(window.jQuery(select).val()||[]).filter(Boolean)}
    function appendArrayParams(params,key,values){values.forEach(value=>{if(value)params.append(`${key}[]`,value)})}
    function formatDate(value){if(!value)return'-';return new Intl.DateTimeFormat('id-ID').format(new Date(value+'T00:00:00'))}
    function formatNumber(value){if(value===null||value===undefined||value==='')return'-';const number=Number(value);return Number.isNaN(number)?'-':new Intl.NumberFormat('id-ID').format(number)}
    function deltaText(value){const n=Number(value||0);return n>0?`+${formatNumber(n)}`:formatNumber(n)}
    function cellClass(value,isCurrent=false){if(isCurrent)return'metric-current';const n=Number(value||0);if(n>0)return'metric-positive';if(n<0)return'metric-negative';return'metric-neutral'}
    function renderRows(rows){if(!rows||rows.length===0){tableBody.innerHTML=`<tr><td colspan="5" class="dormant-empty-state"><strong>Data tidak ditemukan</strong>Coba ubah periode atau filter branch office agar hasil report tersedia.</td></tr>`;return}tableBody.innerHTML=rows.map(row=>`<tr><th>${row.branch||'-'}</th><td class="${cellClass(row.current,true)}">${formatNumber(row.current)}</td><td class="${cellClass(row.mtd)}">${deltaText(row.mtd)}</td><td class="${cellClass(row.ytd)}">${deltaText(row.ytd)}</td><td class="${cellClass(row.yoy)}">${deltaText(row.yoy)}</td></tr>`).join('')}
    function renderFoot(total={}){tableFoot.innerHTML=`<th>Grand Total</th><td>${formatNumber(total.current??null)}</td><td>${deltaText(total.mtd??null)}</td><td>${deltaText(total.ytd??null)}</td><td>${deltaText(total.yoy??null)}</td>`}
    function updateHeaders(labels={}){currentHeader.textContent=labels.curr&&labels.curr!=='-'?labels.curr:'Periode Terakhir';mtdHeader.textContent=labels.mtd&&labels.mtd!=='-'?`MtD vs ${labels.mtd}`:'MtD';ytdHeader.textContent=labels.ytd&&labels.ytd!=='-'?`YtD vs ${labels.ytd}`:'YtD';yoyHeader.textContent=labels.yoy&&labels.yoy!=='-'?`YoY vs ${labels.yoy}`:'YoY'}
    function setOverlay(title, copy){overlay.classList.remove('is-hidden');overlay.querySelector('.dormant-loading-title').textContent=title;overlay.querySelector('.dormant-loading-copy').textContent=copy}
    function hideOverlay(){overlay.classList.add('is-hidden')}
    function resetTableState(){tableBody.innerHTML=`<tr><td colspan="5" class="dormant-empty-state"><strong>Filter belum dijalankan</strong>Pilih periode atau branch office lalu klik <strong>Tampilkan</strong>.</td></tr>`;renderFoot({});activeMeta.textContent='-';comparisonMeta.textContent='-';badge.textContent='-';updateHeaders({});setOverlay('Siap Memuat Data','Pilih periode dan cabang terlebih dulu, lalu klik Tampilkan untuk memproses rekening dormant.')}

    async function loadFilterOptions(){if(activeFilterController)activeFilterController.abort();if(!periodInput.value){setSelectOptions(branchSelect,[],[],true);setSelectOptions(unitSelect,[],[],true);activeMeta.textContent='-';comparisonMeta.textContent='-';return}activeFilterController=new AbortController();branchSelect.disabled=true;unitSelect.disabled=true;const params=new URLSearchParams();params.set('posisi',periodInput.value);appendArrayParams(params,'kantor_cabang',collectSelectedValues(branchSelect));appendArrayParams(params,'unit_kerja',collectSelectedValues(unitSelect));try{const response=await fetch(`${filtersUrl}?${params.toString()}`,{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},signal:activeFilterController.signal});if(!response.ok)throw new Error('Gagal memuat opsi filter');const payload=await response.json();setSelectOptions(branchSelect,payload.branch_options||[],payload.selected_branches||[],!periodInput.value);setSelectOptions(unitSelect,payload.unit_options||[],payload.selected_units||[],!periodInput.value);activeMeta.textContent=formatDate(payload.selected_period);comparisonMeta.textContent=formatDate(payload.comparison_period)}catch(error){if(error.name!=='AbortError'){setSelectOptions(branchSelect,[],[],true);setSelectOptions(unitSelect,[],[],true);activeMeta.textContent='-';comparisonMeta.textContent='-'}}finally{if(!activeFilterController?.signal.aborted){branchSelect.disabled=!periodInput.value;unitSelect.disabled=!periodInput.value}}}

    async function loadReport(pushHistory=false){if(activeController)activeController.abort();activeController=new AbortController();const formData=new FormData(form), params=new URLSearchParams();for(const [key,value] of formData.entries()){if(value)params.append(key,value)}chip.classList.remove('d-none');submitButton.disabled=true;setOverlay('Sedang Mengolah','Sistem sedang menghitung rekening dormant dan pembanding MtD, YtD, serta YoY untuk filter yang dipilih.');try{const response=await fetch(dataUrl,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN':csrfToken,'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:params.toString(),signal:activeController.signal});if(!response.ok)throw new Error('Gagal memuat report');const payload=await response.json();renderRows(payload.data||[]);renderFoot(payload.total||{});updateHeaders(payload.labels||{});activeMeta.textContent=formatDate(payload.effective_dates?.curr);comparisonMeta.textContent=formatDate(payload.effective_dates?.mtd);badge.textContent=`${formatDate(payload.effective_dates?.curr)} | ${payload.branches?.length||0} branch | ${payload.units?.length||0} uker`;hideOverlay();if(pushHistory){const pageUrl=new URL(@json(route('report.rekening-dormant')),window.location.origin);params.forEach((value,key)=>pageUrl.searchParams.append(key,value));window.history.replaceState({},'',pageUrl.toString())}}catch(error){if(error.name!=='AbortError'){tableBody.innerHTML=`<tr><td colspan="5" class="dormant-empty-state"><strong>Gagal memuat report</strong>Silakan coba lagi. Jika masih berat, kecilkan pilihan branch office atau nama uker agar data lebih spesifik.</td></tr>`;renderFoot({});badge.textContent='-';setOverlay('Siap Memuat Data','Pilih periode dan cabang terlebih dulu, lalu klik Tampilkan untuk memproses rekening dormant.')}}finally{chip.classList.add('d-none');submitButton.disabled=false}}

    form.addEventListener('submit',function(event){event.preventDefault();loadReport(true)});
    periodInput.addEventListener('change',function(){branchSelect.dataset.selected='[]';unitSelect.dataset.selected='[]';resetTableState();loadFilterOptions()});
    refreshSelectUi(branchSelect);
    refreshSelectUi(unitSelect);
    window.jQuery(branchSelect).on('change',function(){syncSelectedDataset(branchSelect);updateSelectSummary(branchSelect);unitSelect.dataset.selected='[]';loadFilterOptions()});
    window.jQuery(unitSelect).on('change',function(){syncSelectedDataset(unitSelect);updateSelectSummary(unitSelect)});
    resetButton.addEventListener('click',function(){periodInput.value=defaultPeriod;branchSelect.dataset.selected='[]';unitSelect.dataset.selected='[]';setSelectOptions(branchSelect,[],[],true);setSelectOptions(unitSelect,[],[],true);resetTableState();loadFilterOptions()});
    resetTableState();loadFilterOptions();
});
</script>
@endsection
