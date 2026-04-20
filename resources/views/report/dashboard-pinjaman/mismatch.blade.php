@extends('layouts.admin')

@section('title', 'Kolek Tidak Sesuai')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

<div class="loan-dashboard pt-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h1 class="loan-page-title">Audit Kolek Tidak Sesuai</h1>
            <p class="text-muted mb-0">Verifikasi konsistensi bucket kualitas pinjaman berdasarkan rule audit.</p>
        </div>
    </div>

    <div id="loanMismatchPanel">
        <div class="card loan-shell mb-4 animate-reveal">
            <div class="card-body p-4">
                <form id="loanMismatchForm" method="GET" action="{{ route('report.dashboard-pinjaman.kolek-tidak-sesuai') }}">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3">
                        <div>
                            <h5 class="mb-1 font-weight-bold text-dark">Filter Audit</h5>
                        </div>
                    </div>

                    <div class="row loan-filter-grid">
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="form-group">
                                <label class="loan-filter-label">Periode</label>
                                <input id="loanMismatchPeriodeInput" type="date" name="mismatch_periode" class="form-control loan-filter-control" value="{{ $mismatchRequestedPeriod ?: $mismatchSelectedPeriod }}" max="{{ $periods->first() }}">
                            </div>
                        </div>
                        <div class="col-xl-4 col-lg-5 col-md-6">
                            <div class="form-group">
                                <label class="loan-filter-label">Kantor Cabang</label>
                                <select id="loanMismatchCabangSelect" name="mismatch_cabang1" class="form-control loan-filter-control" data-selected="{{ $mismatchSelectedBranch }}">
                                    <option value="">Pilih periode dulu</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-xl-5 col-lg-3 col-md-12 loan-mismatch-action-col">
                            <div class="loan-mismatch-actions w-100">
                                <button id="loanMismatchSubmitButton" type="submit" class="btn btn-primary">
                                    <i class="fas fa-search mr-1"></i> Proses
                                </button>
                                <a href="{{ route('report.dashboard-pinjaman.kolek-tidak-sesuai') }}" class="btn btn-light">Reset</a>
                                <div id="loanMismatchLoadingChip" class="loan-loading-chip d-none">
                                    <span class="loan-loading-dot"></span> Audit Sedang Jalan
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card loan-table-shell loan-mismatch-table-shell animate-reveal">
            <div class="card-body p-4">
                <div class="loan-table-heading">
                    <div><h5>Hasil Audit Mismatch</h5></div>
                    <div class="loan-table-badge">
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span id="loanMismatchPeriodBadge">
                            {{ $mismatchSelectedPeriod ? \Carbon\Carbon::parse($mismatchSelectedPeriod)->format('d/m/Y') : '-' }} | {{ $mismatchSelectedBranch ?: 'Belum pilih cabang' }}
                        </span>
                    </div>
                </div>

                <div class="loan-mismatch-summary">
                    <div class="loan-mismatch-card">
                        <div class="loan-audit-label">Baris Discanning</div>
                        <div id="loanMismatchScanned" class="loan-audit-value">0</div>
                    </div>
                    <div class="loan-mismatch-card">
                        <div class="loan-audit-label">Mismatch</div>
                        <div id="loanMismatchTotal" class="loan-audit-value text-danger">0</div>
                    </div>
                    <div class="loan-mismatch-card">
                        <div class="loan-audit-label">Sesuai</div>
                        <div id="loanMismatchMatched" class="loan-audit-value text-success">0</div>
                    </div>
                    <div class="loan-mismatch-card">
                        <div class="loan-audit-label">Unit Bermasalah</div>
                        <div id="loanMismatchUnits" class="loan-audit-value">0</div>
                    </div>
                </div>

                <div class="table-responsive mt-4">
                    <div class="loan-mismatch-table-wrap">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 72px;">No</th>
                                    <th>Unit Kerja</th>
                                    <th style="width: 220px;">Jumlah Kolek Tidak Sesuai</th>
                                    <th style="width: 180px;">Export Detail</th>
                                </tr>
                            </thead>
                            <tbody id="loanMismatchBody">
                                <tr>
                                    <td colspan="4" class="loan-empty-state">
                                        <strong>Audit belum dijalankan</strong>
                                        Pilih periode dan cabang lalu klik <strong>Proses</strong>.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
@include('report.dashboard-pinjaman._partials._scripts_shared')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('loanMismatchForm');
        const periodInput = document.getElementById('loanMismatchPeriodeInput');
        const branchSelect = document.getElementById('loanMismatchCabangSelect');
        const body = document.getElementById('loanMismatchBody');
        const chip = document.getElementById('loanMismatchLoadingChip');
        const submitButton = document.getElementById('loanMismatchSubmitButton');
        const badge = document.getElementById('loanMismatchPeriodBadge');
        
        const filterUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai.filters'));
        const dataUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai.data'));
        const exportUrl = @json(route('report.dashboard-pinjaman.kolek-tidak-sesuai.export'));

        async function loadBranches() {
            if (!periodInput.value) return;
            const res = await fetch(`${filterUrl}?periode=${periodInput.value}`);
            const payload = await res.json();
            branchSelect.innerHTML = '<option value="">Pilih kantor cabang</option>';
            payload.branches.forEach(b => {
                branchSelect.add(new Option(b, b, false, b === branchSelect.dataset.selected));
            });
            branchSelect.disabled = false;
        }

        async function loadData() {
            if (!periodInput.value || !branchSelect.value) return;
            chip.classList.remove('d-none');
            submitButton.disabled = true;
            
            try {
                const res = await fetch(`${dataUrl}?periode=${periodInput.value}&cabang1=${branchSelect.value}`);
                const payload = await res.json();
                renderTable(payload.summary_rows, payload.selected_period, payload.selected_branch);
                document.getElementById('loanMismatchScanned').textContent = formatNumber(payload.audit.scanned_rows);
                document.getElementById('loanMismatchTotal').textContent = formatNumber(payload.audit.mismatch_rows);
                document.getElementById('loanMismatchMatched').textContent = formatNumber(payload.audit.matched_rows);
                document.getElementById('loanMismatchUnits').textContent = formatNumber(payload.audit.units_with_mismatch);
            } finally {
                chip.classList.add('d-none');
                submitButton.disabled = false;
            }
        }

        function renderTable(rows, period, branch) {
            body.innerHTML = rows.map((row, i) => `
                <tr>
                    <td>${i+1}</td>
                    <td>${row.unit}</td>
                    <td class="text-danger font-weight-bold">${formatNumber(row.mismatch_count)}</td>
                    <td><a href="${exportUrl}?periode=${period}&cabang1=${branch}&unit1=${row.unit}" class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel mr-1"></i> Excel</a></td>
                </tr>
            `).join('');
            badge.textContent = `${formatDate(period)} | ${branch}`;
        }

        form.addEventListener('submit', e => { e.preventDefault(); loadData(); });
        periodInput.addEventListener('change', loadBranches);
        if (periodInput.value) loadBranches();
    });
</script>
@endpush

@endsection
