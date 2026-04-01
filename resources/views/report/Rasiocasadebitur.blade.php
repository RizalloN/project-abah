@extends('layouts.admin')

@section('title', 'Rasio CASA Debitur')

@section('content')

<style>
    /* 🔥 KONSISTENSI UI: Tabel elastis dan cerdas menyesuaikan ukuran layar */
    .report-filter-card,
    .report-data-card {
        border: 1px solid #e9ecef;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 0.5rem 1rem rgba(15, 23, 42, 0.08) !important;
    }
    .report-filter-card .card-body,
    .report-data-card .card-header,
    .report-data-card .card-body {
        background-color: #ffffff;
    }
    .report-filter-card .form-control {
        border-radius: 10px;
        min-height: 40px;
    }
    .table-container { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .table-report { 
        border-collapse: collapse; 
        width: 100%; 
        table-layout: auto; 
        white-space: nowrap;
    }
    .table-report th, .table-report td { 
        vertical-align: middle !important; 
        border: 1px solid #dee2e6;
    }
    .table-report th { font-size: 0.65rem; padding: 10px 4px; text-align: center; }
    .table-report td { font-size: 0.70rem; padding: 6px 6px; text-align: right; }
    .table-report td.text-left { text-align: left; }
    
    /* Pewarnaan Header Khas */
    .bg-header-main { background-color: #0056b3 !important; color: #ffffff !important; border-color: #004085 !important; }
    .bg-header-sub { background-color: #a6a6a6 !important; color: #ffffff !important; font-weight: bold; border-color: #808080 !important; }
    .bg-header-sub-light { background-color: #d9d9d9 !important; color: #333 !important; font-weight: bold; border-color: #bfbfbf !important; }

    .table-hover tbody tr:hover { background-color: #f1f7ff; }
    .row-total { background-color: #0056b3 !important; color: white !important; font-weight: bold; }
    .row-total td { color: white !important; }
    
    /* Warna teks khusus untuk Mtd */
    .val-up { color: #28a745; font-weight: bold; }
    .val-down { color: #dc3545; font-weight: bold; }
    
    .nav-tabs.report-tabs { border-bottom: 2px solid #dee2e6; flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; white-space: nowrap; scrollbar-width: thin; }
    .nav-tabs.report-tabs .nav-link { border: none; font-weight: 600; color: #6c757d; padding: 12px 18px; font-size: 0.95rem; background: transparent; }
    .nav-tabs.report-tabs .nav-link.active { border-bottom: 3px solid #007bff; color: #007bff; background: transparent; }
    .nav-tabs.report-tabs .nav-link:hover { border-bottom: 3px solid #9ec5fe; color: #007bff; background: transparent; }
</style>

<!-- CARD HEADER FILTER -->
<div class="card card-outline card-primary shadow-sm mb-3 report-filter-card">
    <div class="card-body py-3">
        <div class="row align-items-end">
            <!-- Selector 1: Periode Akhir -->
            <div class="col-md-4">
                <div class="form-group mb-0">
                    <label class="text-dark text-sm font-weight-bold mb-1">Periode Akhir <i class="fas fa-edit text-primary ml-1"></i></label>
                    <input type="date" id="filter_posisi" class="form-control border-primary shadow-sm filter-trigger" value="{{ date('Y-m-d') }}">
                </div>
            </div>
            <!-- Selector 2: Branch Office -->
            <div class="col-md-4">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Branch Office (Kanca)</label>
                    <input type="text" class="form-control font-weight-bold" value="Area 6 - All" disabled>
                </div>
            </div>
            <!-- Selector 3: Nama Uker -->
            <div class="col-md-4">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Nama Uker</label>
                    <input type="text" class="form-control" value="ALL UKER" disabled>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TABEL DATA PERFORMANCE -->
<div class="card shadow-sm border-0 mb-4 report-data-card">
    <div class="card-header bg-white p-0 border-bottom-0">
        <!-- 🔥 3 TABS HEADER -->
        <ul class="nav nav-tabs report-tabs px-3 pt-2" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#tab-total" role="tab">
                    <i class="fas fa-chart-pie mr-1"></i> Total
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-briguna-kpr" role="tab">
                    <i class="fas fa-home mr-1"></i> BRIGUNA & KPR
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-mikro-smc" role="tab">
                    <i class="fas fa-store mr-1"></i> MIKRO & SMC
                </a>
            </li>
            <li class="nav-item ml-auto d-flex align-items-center pr-2">
                <span id="loadingIndicator" class="text-primary font-weight-bold" style="display: none; font-size: 0.9rem;">
                    <i class="fas fa-spinner fa-spin mr-1"></i> Memuat Data...
                </span>
            </li>
        </ul>
    </div>
    
    <div class="card-body p-0">
        <div class="tab-content">
            
            <!-- 🔥 STRUKTUR LOOPING CERDAS UNTUK 3 TAB BERSUSUN -->
            @php
                $tabGroups = [
                    'tab-total' => [['id' => 'total', 'title' => 'TOTAL']],
                    'tab-briguna-kpr' => [['id' => 'briguna', 'title' => 'BRIGUNA'], ['id' => 'kpr', 'title' => 'KPR']],
                    'tab-mikro-smc' => [['id' => 'mikro', 'title' => 'MIKRO'], ['id' => 'smc', 'title' => 'SMC']]
                ];
            @endphp

            @foreach($tabGroups as $tabId => $tables)
            <div class="tab-pane fade {{ $tabId === 'tab-total' ? 'show active' : '' }}" id="{{ $tabId }}" role="tabpanel">
                @foreach($tables as $t)
                <div class="table-container {{ count($tables) > 1 ? 'mb-4 border-bottom' : '' }}">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="3" class="bg-header-main align-middle" style="min-width: 150px;">BRANCH OFFICE</th>
                                <th colspan="7" class="bg-header-main">{{ strtoupper($t['title']) }}</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th colspan="2">Total OS</th>
                                <th colspan="2">Total CASA</th>
                                <th colspan="3">Rasio CASA/OS</th>
                            </tr>
                            <tr class="bg-header-sub-light">
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th class="lbl-prev-th">-</th>
                                <th class="lbl-curr-th">-</th>
                                <th>MtD</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-{{ $t['id'] }}"></tbody>
                    </table>
                </div>
                @endforeach
            </div>
            @endforeach

        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // 🚀 FORMAT FUNCTIONS (Aman untuk Strict Mode/Null)
    function formatNum(num) { 
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '-';
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(parseFloat(num)); 
    }
    
    function formatPct(num) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '-';
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(parseFloat(num)) + '%';
    }

    function formatMtd(num) {
        if (num === null || num === undefined || isNaN(parseFloat(num))) return '-';
        let val = parseFloat(num);
        let text = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val) + '%';
        
        // Memberi warna hijau jika naik, merah jika turun sesuai request visualisasi
        if (val > 0) return `<span class="val-up">+${text}</span>`;
        if (val < 0) return `<span class="val-down">${text}</span>`;
        return text;
    }

    // 🚀 LOAD DATA FUNCTION - ULTRA DEBUG MODE
    window.loadData = function() {
        $('#loadingIndicator').fadeIn('fast');
        
        let payload = {
            posisi: $('#filter_posisi').val(),
            _token: '{{ csrf_token() }}' 
        };

        console.log('%c[RasioCasa] Sending request with date:', 'color: blue; font-weight: bold', payload.posisi);

        $.ajax({
            url: "{{ route('report.data.rasiocasa') }}",
            type: "POST",
            data: payload,
            dataType: 'json',
            timeout: 120000, // 2 minutes timeout for big data
            success: function(res) {
                console.log('%c[RasioCasa] Response received:', 'color: green; font-weight: bold', res);
                
                if(res.status === 'success') {
                    
                    // Update Label Dinamis Header
                    let prevLabel = res.labels.prev || '-';
                    let currLabel = res.labels.curr || '-';
                    $('.lbl-prev-th').text(prevLabel);
                    $('.lbl-curr-th').text(currLabel);

                    let dataList = res.data || [];
                    let totalData = res.total || {};
                    
                    console.log('[RasioCasa] Data count:', dataList.length);
                    console.log('[RasioCasa] Total data:', totalData);
                    
                    // Check if we have actual data
                    let hasAnyData = false;
                    dataList.forEach(row => {
                        ['total', 'briguna', 'kpr', 'mikro', 'smc'].forEach(seg => {
                            if (row[seg] && (row[seg].os_curr > 0 || row[seg].casa_curr > 0)) {
                                hasAnyData = true;
                            }
                        });
                    });

                    // Logika rendering 5 data tabel
                    let tablesTarget = ['total', 'briguna', 'kpr', 'mikro', 'smc'];

                    tablesTarget.forEach(tableId => {
                        let html = '';
                        let tableHasData = false;
                        
                        // Render per Cabang
                        dataList.forEach(row => {
                            let dt = row[tableId] || {};
                            // Check if has any meaningful data
                            if (dt.os_curr > 0 || dt.casa_curr > 0 || dt.os_prev > 0 || dt.casa_prev > 0) {
                                tableHasData = true;
                                hasAnyData = true;
                            }
                            html += `<tr>
                                <td class="text-left font-weight-bold">${row.branch || '-'}</td>
                                <td>${formatNum(dt.os_prev)}</td>
                                <td style="background-color: #f6f9fc;">${formatNum(dt.os_curr)}</td>
                                <td>${formatNum(dt.casa_prev)}</td>
                                <td style="background-color: #f6f9fc;">${formatNum(dt.casa_curr)}</td>
                                <td>${formatPct(dt.rasio_prev)}</td>
                                <td class="font-weight-bold">${formatPct(dt.rasio_curr)}</td>
                                <td>${formatMtd(dt.mtd)}</td>
                            </tr>`;
                        });

                        // Render Baris Total Area 6
                        let t_dt = totalData[tableId] || {};
                        html += `<tr class="row-total">
                            <td class="text-left">TOTAL AREA 6</td>
                            <td>${formatNum(t_dt.os_prev)}</td>
                            <td>${formatNum(t_dt.os_curr)}</td>
                            <td>${formatNum(t_dt.casa_prev)}</td>
                            <td>${formatNum(t_dt.casa_curr)}</td>
                            <td>${formatPct(t_dt.rasio_prev)}</td>
                            <td>${formatPct(t_dt.rasio_curr)}</td>
                            <td>${formatMtd(t_dt.mtd)}</td>
                        </tr>`;

                        // Tempel HTML ke tbody
                        $(`#tbody-${tableId}`).html(html);
                        
                        console.log(`[RasioCasa] Table ${tableId} rendered, hasData: ${tableHasData}`);
                    });
                    
                    // Show warning if no data found
                    if (!hasAnyData) {
                        console.warn('%c[RasioCasa] WARNING: No data found!', 'color: red; font-size: 16px; font-weight: bold');
                        console.warn('Date:', payload.posisi);
                        console.warn('Possible causes:');
                        console.warn('1. No data in database for this date');
                        console.warn('2. Branch names do not match (expected: MADIUN, MAGETAN, NGAWI, PONOROGO)');
                        console.warn('3. Segment filters too restrictive');
                        console.warn('4. Check Laravel logs: storage/logs/laravel.log');
                        
                        // Show visual warning
                        tablesTarget.forEach(tableId => {
                            $(`#tbody-${tableId}`).prepend(`
                                <tr class="table-warning">
                                    <td colspan="8" class="text-center text-warning font-weight-bold">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        Tidak ada data untuk tanggal ${payload.posisi}. 
                                        Coba pilih tanggal lain atau cek logs.
                                    </td>
                                </tr>
                            `);
                        });
                    } else {
                        console.log('%c[RasioCasa] SUCCESS: Data loaded!', 'color: green; font-size: 16px; font-weight: bold');
                    }
                } else {
                    console.error('[RasioCasa] Response status not success:', res);
                    alert('Error loading data: ' + (res.message || 'Unknown error'));
                }
                $('#loadingIndicator').fadeOut('fast');
            },
            error: function(xhr, status, error) {
                $('#loadingIndicator').fadeOut('fast');
                console.error('%c[RasioCasa] AJAX Error:', 'color: red; font-weight: bold', status, error);
                console.error('[RasioCasa] Response:', xhr.responseText);
                
                // Show user-friendly error
                let errorMsg = 'Gagal memuat data. ';
                if (xhr.status === 500) {
                    errorMsg += 'Server error (500). Check logs di storage/logs/laravel.log';
                } else if (xhr.status === 419) {
                    errorMsg += 'Session expired. Please refresh page.';
                } else if (xhr.status === 0) {
                    errorMsg += 'Connection timeout. Query might be too slow for 2M+ rows.';
                } else {
                    errorMsg += 'Error: ' + error;
                }
                alert(errorMsg);
            }
        });
    }

    // 🔥 FIXED: Trigger update bila tanggal dirubah - dengan multiple event handlers
    $('#filter_posisi').on('change', function() {
        console.log('%c[RasioCasa] Date changed to:', 'color: orange; font-weight: bold', $(this).val());
        loadData();
    });
    
    // Also bind to input event for immediate response
    $('#filter_posisi').on('input', function() {
        console.log('%c[RasioCasa] Date input changed:', 'color: orange', $(this).val());
    });
    
    // Bind to class as well for compatibility
    $('.filter-trigger').on('change', function() {
        console.log('%c[RasioCasa] Filter trigger changed:', 'color: orange', $(this).val());
        loadData();
    });
    
    // Auto Load saat halaman pertama dibuka
    console.log('%c[RasioCasa] Initial load starting...', 'color: blue; font-weight: bold');
    loadData();
});
</script>
@endsection
