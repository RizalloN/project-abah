@extends('layouts.admin')

@section('title', 'Dashboard Dana')

@section('content')
<style>
    :root {
        --dana-bg: #f8fafc;
        --dana-primary: #0f4c81;
        --dana-accent: #3b82f6;
        --dana-success: #10b981;
        --dana-danger: #ef4444;
        --dana-border: #e2e8f0;
        --dana-card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
    }

    .dana-dashboard {
        background-color: var(--dana-bg);
        min-height: 100vh;
        padding-bottom: 3rem;
    }

    .dana-hero {
        background: linear-gradient(135deg, #0f4c81 0%, #1e40af 100%);
        padding: 3rem 2rem 5rem;
        color: white;
        margin-bottom: -3rem;
        border-radius: 0 0 2.5rem 2.5rem;
    }

    .dana-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 1.5rem;
    }

    .dana-card {
        background: white;
        border-radius: 1.25rem;
        border: 1px solid var(--dana-border);
        box-shadow: var(--dana-card-shadow);
        overflow: hidden;
    }

    .dana-filter-bar {
        background: white;
        padding: 1.25rem;
        border-radius: 1rem;
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        align-items: flex-end;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
        border: 1px solid var(--dana-border);
    }

    .filter-item {
        flex: 1;
        min-width: 200px;
    }

    .filter-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        margin-bottom: 0.5rem;
        display: block;
    }

    .filter-select {
        width: 100%;
        height: 42px;
        border-radius: 0.75rem;
        border: 1px solid #cbd5e1;
        padding: 0 1rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: #1e293b;
        background-color: #f8fafc;
        transition: all 0.2s;
    }

    .filter-select:focus {
        border-color: var(--dana-accent);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }

    .btn-dana-refresh {
        background: var(--dana-accent);
        color: white;
        border: none;
        padding: 0.625rem 1.5rem;
        border-radius: 0.75rem;
        font-weight: 700;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s;
        height: 42px;
    }

    .btn-dana-refresh:hover {
        background: #2563eb;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .dana-table-container {
        margin-top: 1rem;
    }

    .dana-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .dana-table thead th {
        background: #0f4c81;
        color: #ffffff;
        font-weight: 800;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        padding: 0.35rem 0.3rem;
        border-bottom: 2px solid #0a3d6b;
        border-right: 1px solid #145994;
        position: sticky;
        top: 0;
        z-index: 10;
        text-align: center;
    }

    .dana-table tbody td {
        padding: 0.15rem 0.4rem;
        font-size: 0.725rem;
        border-bottom: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
        vertical-align: middle;
        line-height: 1.1;
        white-space: nowrap;
    }

    .dana-table .branch-cell {
        font-weight: 800;
        color: #0f172a;
        text-align: center;
        background: #ffffff !important;
        font-size: 0.75rem;
        border-right: 2px solid #cbd5e1;
    }

    .dana-table .cat-cell {
        font-weight: 600;
        color: #475569;
    }

    .dana-table .val-cell {
        font-family: 'JetBrains Mono', monospace;
        font-weight: 600;
        text-align: right;
    }

    .dana-table .delta-cell {
        text-align: right;
        font-weight: 700;
    }

    .dana-table .perf-cell {
        text-align: center;
    }

    .text-pos { color: var(--dana-success); }
    .text-neg { color: var(--dana-danger); }

    .perf-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 0.5rem;
        font-weight: 800;
        font-size: 0.75rem;
    }

    .perf-badge.bg-pos { background: #d1fae5; color: #065f46; }
    .perf-badge.bg-neg { background: #fee2e2; color: #991b1b; }

    .sticky-col {
        position: sticky;
        left: 0;
        background: white;
        z-index: 5;
    }

    .subtotal-row {
        background-color: #f8fafc !important;
        font-weight: 800;
        color: var(--dana-primary);
    }

    .grandtotal-row {
        background-color: #0f4c81 !important;
        color: white !important;
        font-weight: 800;
    }

    .grandtotal-row td {
        color: white !important;
    }

    /* Number formatting classes */
    .neg-val::before { content: '('; }
    .neg-val::after { content: ')'; }

    .loading-overlay {
        position: absolute;
        inset: 0;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 100;
        backdrop-filter: blur(2px);
    }

    /* Capture Status Modal */
    .capture-status-modal .modal-content {
        border-radius: 24px;
        border: none;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.2);
    }
    .capture-status-modal-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2.5rem;
    }
    .icon-loading { background: rgba(15, 76, 129, 0.1); color: #0f4c81; }
    .icon-success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .icon-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

    .btn-capture-all {
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.75rem;
        font-weight: 700;
        font-size: 0.75rem;
        transition: all 0.2s;
    }
    .btn-capture-all:hover {
        background: rgba(255, 255, 255, 0.25);
        color: white;
        border-color: white;
    }
</style>

<div class="dana-dashboard">
    <div class="dana-hero">
        <div class="dana-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-4 font-weight-bold mb-2">Dashboard Dana</h1>
                    <p class="h5 opacity-75">Monitoring Keragaan Dana Simpanan (SSA) Area 6</p>
                    <div class="mt-3">
                        <button id="captureAllBtn" class="btn-capture-all">
                            <i class="fas fa-camera mr-2"></i> CAPTURE ALL A4
                        </button>
                    </div>
                </div>
                <div class="text-right">
                    <div class="h2 mb-0 font-weight-bold" id="headerTotalDana">-</div>
                    <div class="small opacity-75 text-uppercase font-weight-bold">Total Dana Kelolaan</div>
                </div>
            </div>
        </div>
    </div>

    <div class="dana-container">
        <div class="dana-filter-bar">
            <div class="filter-item">
                <label class="filter-label">Periode Data</label>
                <select id="filterPeriode" class="filter-select select2">
                    @foreach($periods as $p)
                        <option value="{{ $p }}" {{ $selectedPeriod == $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-item">
                <label class="filter-label">Periode RKA</label>
                <select id="filterRka" class="filter-select">
                    @foreach($periods as $p)
                        <option value="{{ $p }}" {{ $selectedRka == $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-item">
                <label class="filter-label">Segmentasi</label>
                <select id="filterKategori" class="filter-select">
                    <option value="all">SEMUA SEGMEN</option>
                    @foreach($categories as $c)
                        <option value="{{ $c }}" {{ $selectedCategory == $c ? 'selected' : '' }}>{{ strtoupper($c) }}</option>
                    @endforeach
                </select>
            </div>
            <button id="btnRefresh" class="btn-dana-refresh">
                <i class="fas fa-sync-alt"></i> Tampilkan
            </button>
        </div>

        <div class="dana-card">
            <div id="loader" class="loading-overlay" style="display: none;">
                <div class="text-center">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="font-weight-bold text-primary">Mengolah Data...</div>
                </div>
            </div>

            <div class="dana-table-container">
                <div class="table-responsive">
                    <table class="dana-table">
                        <thead>
                            <tr>
                                <th rowspan="2" class="text-center" width="50">No</th>
                                <th rowspan="2" class="sticky-col">Kantor Cabang</th>
                                <th rowspan="2" class="text-center">Kategori</th>
                                <th colspan="3" class="text-center border-bottom">Posisi Saldo (Rp)</th>
                                <th colspan="2" class="text-center border-bottom border-left">Delta Posisi</th>
                                <th colspan="2" class="text-center border-bottom border-left" style="background: #ecfdf5;">Performa RKA</th>
                            </tr>
                            <tr>
                                <th class="text-right">YTD</th>
                                <th class="text-right">MTD</th>
                                <th class="text-right" id="headerSelectedDate">Posisi</th>
                                <th class="text-right border-left">YTD</th>
                                <th class="text-right">MTD</th>
                                <th class="text-right border-left" style="background: #f0fdf4;">Rp</th>
                                <th class="text-center" style="background: #f0fdf4;">%</th>
                            </tr>
                        </thead>
                        <tbody id="danaContent">
                            <!-- JS Driven -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
    $(document).ready(function() {
        if ($.fn.select2) {
            $('.select2').select2({ theme: 'bootstrap4', width: '100%' });
        }

        const formatMoney = (val) => {
            const num = parseFloat(val) || 0;
            const formatted = new Intl.NumberFormat('id-ID', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(Math.abs(num));
            
            return num < 0 ? `(${formatted})` : formatted;
        };

        const formatPercent = (val) => {
            return (parseFloat(val) || 0).toFixed(2) + '%';
        };

        const getColorClass = (val) => {
            const num = parseFloat(val) || 0;
            return num > 0 ? 'text-pos' : (num < 0 ? 'text-neg' : '');
        };

        const getKategoriBadge = (kat) => {
            const slug = kat.toLowerCase();
            return `<span class="kategori-badge badge-${slug}">${kat}</span>`;
        };

        const loadData = () => {
            $('#loader').fadeIn(200);
            
            const params = {
                periode: $('#filterPeriode').val(),
                rka_periode: $('#filterRka').val(),
                kategori: $('#filterKategori').val()
            };

            $.get("{{ route('report.dashboard-dana.data') }}", params, function(res) {
                $('#loader').fadeOut(200);
                $('#headerSelectedDate').text(res.header_dates.selected);
                
                let html = '';
                
                res.rows.forEach((row, index) => {
                    const isTotal = row.is_total;
                    const isStartOfBranch = isTotal;
                    
                    html += `
                        <tr class="${isTotal ? 'subtotal-row' : ''}">
                            <td class="text-center text-muted xsmall">${row.no || ''}</td>
                            ${isStartOfBranch ? `
                            <td class="sticky-col branch-cell" rowspan="5">
                                ${row.nama_cabang}
                            </td>
                            ` : ''}
                            <td class="text-left cat-cell ${isTotal ? 'font-weight-bold' : 'pl-3 text-muted'}">${row.kategori}</td>
                            <td class="val-cell ${isTotal ? 'font-weight-bold' : 'text-muted'}">${formatMoney(row.ytd)}</td>
                            <td class="val-cell ${isTotal ? 'font-weight-bold' : 'text-muted'}">${formatMoney(row.mtd)}</td>
                            <td class="val-cell ${isTotal ? 'font-weight-bold' : ''}">${formatMoney(row.selected)}</td>
                            <td class="delta-cell border-left ${getColorClass(row.delta_ytd)} ${isTotal ? 'font-weight-bold' : ''}">${formatMoney(row.delta_ytd)}</td>
                            <td class="delta-cell ${getColorClass(row.delta_mtd)} ${isTotal ? 'font-weight-bold' : ''}">${formatMoney(row.delta_mtd)}</td>
                            <td class="val-cell border-left ${getColorClass(row.rka_rp)} ${isTotal ? 'font-weight-bold' : ''}" style="background: #f0fdf4;">${formatMoney(row.rka_rp)}</td>
                            <td class="perf-cell" style="background: #f0fdf4;">
                                <span class="perf-badge ${parseFloat(row.rka_pct) >= 100 ? 'bg-pos' : 'bg-neg'}">
                                    ${formatPercent(row.rka_pct)}
                                </span>
                            </td>
                        </tr>
                    `;
                });

                // Grand Total
                const gt = res.total;
                html += `
                    <tr class="grandtotal-row">
                        <td colspan="2" class="text-center font-weight-bold">TOTAL AREA 6</td>
                        <td class="text-center">SEMUA</td>
                        <td class="val-cell">${formatMoney(gt.ytd)}</td>
                        <td class="val-cell">${formatMoney(gt.mtd)}</td>
                        <td class="val-cell">${formatMoney(gt.selected)}</td>
                        <td class="delta-cell border-left ${getColorClass(gt.delta_ytd)}">${formatMoney(gt.delta_ytd)}</td>
                        <td class="delta-cell ${getColorClass(gt.delta_mtd)}">${formatMoney(gt.delta_mtd)}</td>
                        <td class="val-cell border-left ${getColorClass(gt.rka_rp)}" style="background: #f0fdf4;">${formatMoney(gt.rka_rp)}</td>
                        <td class="perf-cell" style="background: #f0fdf4;">
                            <span class="perf-badge ${parseFloat(gt.rka_pct) >= 100 ? 'bg-pos' : 'bg-neg'}">
                                ${formatPercent(gt.rka_pct)}
                            </span>
                        </td>
                    </tr>
                `;

                $('#danaContent').html(html);
                $('#headerTotalDana').text(formatMoney(res.total.selected));
            }).fail(function() {
                $('#loader').fadeOut(200);
                alert('Terjadi kesalahan saat memuat data.');
            });
        };

        $('#btnRefresh').click(loadData);
        loadData();

        // --- Capture All Logic (A4 Portrait) ---
        const captureBtn = document.getElementById('captureAllBtn');
        const captureModal = document.getElementById('captureStatusModal');
        
        const A4_EXPORT = {
            width: 2480,
            height: 3508,
            marginX: 140,
            marginY: 120,
            headerHeight: 280,
            footerHeight: 100,
        };

        async function captureAllDanaDashboard() {
            $(captureModal).modal('show');
            $('#captureProgressUI').removeClass('d-none');
            $('#captureErrorUI, #captureSuccessUI').addClass('d-none');
            
            const originalBtnHtml = captureBtn.innerHTML;
            captureBtn.disabled = true;
            captureBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> CAPTURING...';

            try {
                const canvas = document.createElement('canvas');
                canvas.width = A4_EXPORT.width;
                canvas.height = A4_EXPORT.height;
                const ctx = canvas.getContext('2d');

                // Fill background
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                // 1. Draw Header
                ctx.fillStyle = '#0f4c81';
                ctx.fillRect(0, 0, A4_EXPORT.width, 30);
                
                ctx.fillStyle = '#0f172a';
                ctx.font = 'bold 64px "Inter", sans-serif';
                ctx.fillText('Dashboard Dana Simpanan (SSA)', A4_EXPORT.marginX, A4_EXPORT.marginY + 60);
                
                ctx.fillStyle = '#64748b';
                ctx.font = '600 32px "Inter", sans-serif';
                const periode = $('#filterPeriode').val();
                const kategori = $('#filterKategori option:selected').text();
                ctx.fillText(`Periode: ${periode} | Segmen: ${kategori}`, A4_EXPORT.marginX, A4_EXPORT.marginY + 120);

                ctx.strokeStyle = '#e2e8f0';
                ctx.lineWidth = 4;
                ctx.beginPath();
                ctx.moveTo(A4_EXPORT.marginX, A4_EXPORT.marginY + 180);
                ctx.lineTo(A4_EXPORT.width - A4_EXPORT.marginX, A4_EXPORT.marginY + 180);
                ctx.stroke();

                // 2. Capture Table
                const tableWrap = document.querySelector('.table-responsive');
                const capturedTable = await html2canvas(tableWrap, {
                    scale: 2.5,
                    useCORS: true,
                    backgroundColor: '#ffffff'
                });

                // Calculate aspect ratio to fit width
                const targetWidth = A4_EXPORT.width - (A4_EXPORT.marginX * 2);
                const targetHeight = (capturedTable.height * targetWidth) / capturedTable.width;
                
                ctx.drawImage(capturedTable, A4_EXPORT.marginX, A4_EXPORT.marginY + 250, targetWidth, targetHeight);

                // 3. Draw Footer
                ctx.fillStyle = '#94a3b8';
                ctx.font = '600 24px "Inter", sans-serif';
                const now = new Date().toLocaleString('id-ID');
                ctx.fillText(`Generated by A-Six Dashboard: ${now}`, A4_EXPORT.marginX, A4_EXPORT.height - 60);

                // 4. Download
                const link = document.createElement('a');
                link.download = `Dashboard-Dana-${periode.replace(/-/g, '')}.jpg`;
                link.href = canvas.toDataURL('image/jpeg', 0.95);
                link.click();

                $('#captureProgressUI').addClass('d-none');
                $('#captureSuccessUI').removeClass('d-none');
            } catch (err) {
                console.error(err);
                $('#captureProgressUI').addClass('d-none');
                $('#captureErrorUI').removeClass('d-none');
                $('#captureErrorMessage').text(err.message);
            } finally {
                captureBtn.disabled = false;
                captureBtn.innerHTML = originalBtnHtml;
            }
        }

        captureBtn.addEventListener('click', captureAllDanaDashboard);
    });
</script>

<!-- Modal Capture Status -->
<div class="modal fade capture-status-modal" id="captureStatusModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body text-center">
                <!-- Progress State -->
                <div id="captureProgressUI">
                    <div class="capture-status-modal-icon icon-loading">
                        <i class="fas fa-camera fa-spin"></i>
                    </div>
                    <h4 class="font-weight-bold mb-2">Memproses Gambar...</h4>
                    <p class="text-muted mb-0">Sedang menyusun layout A4 Portrait kualitas tinggi. Mohon tunggu sejenak.</p>
                </div>

                <!-- Success State -->
                <div id="captureSuccessUI" class="d-none">
                    <div class="capture-status-modal-icon icon-success">
                        <i class="fas fa-check"></i>
                    </div>
                    <h4 class="font-weight-bold mb-2">Berhasil!</h4>
                    <p class="text-muted mb-4">Gambar dashboard telah berhasil diunduh.</p>
                    <button type="button" class="btn btn-primary px-5" data-dismiss="modal">Tutup</button>
                </div>

                <!-- Error State -->
                <div id="captureErrorUI" class="d-none">
                    <div class="capture-status-modal-icon icon-error">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4 class="font-weight-bold mb-2">Oops! Gagal</h4>
                    <p id="captureErrorMessage" class="text-muted mb-4">Terjadi kesalahan teknis saat memproses gambar.</p>
                    <button type="button" class="btn btn-secondary px-5" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
