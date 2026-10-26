@php
    $extremeLowView = $selectedExtremeLowView ?? ($payload['view'] ?? 'per_unit_kerja');
    $isBranchView = $extremeLowView === 'per_cabang';
    $baseFilters = [
        'periode' => $selectedPeriod ?? request('periode'),
        'kategori_rm' => 'mantri',
        'kategori_report' => 'extreme_low_mantri',
    ];
    $perUnitKerjaUrl = route('report.dashboard-pinjaman.kinerjarmmikro', array_merge($baseFilters, ['extreme_low_view' => 'per_unit_kerja']));
    $perCabangUrl = route('report.dashboard-pinjaman.kinerjarmmikro', array_merge($baseFilters, ['extreme_low_view' => 'per_cabang']));
    $identityColumnCount = $isBranchView ? 3 : 4;
    $tableColumnCount = $identityColumnCount + 22;
    $extremeLowDetails = $payload['details'] ?? [];
    $bucketLabels = collect($total['buckets'] ?? [])->map(fn ($bucket) => $bucket['label'] ?? '-')->all();
    $extremeLowGroups = [
        'extreme_low' => ['el_0_100', 'el_100_200', 'el_200_400'],
        'under_800' => ['none', 'el_0_100', 'el_100_200', 'el_200_400', 'low_400_600', 'low_600_800'],
        'all' => array_keys($bucketLabels),
    ];
    $groupPrefixes = ['extreme' => 'Extreme Low ', 'low' => 'Low ', 'mid' => 'Mid ', 'high' => 'High '];
    $drillAttrs = function (?string $branch, ?string $unit, array|string $buckets, ?string $label = null) use ($extremeLowGroups, $bucketLabels, $total, $groupPrefixes): string {
        if (is_string($buckets)) {
            $label ??= ($groupPrefixes[$total['buckets'][$buckets]['group'] ?? ''] ?? '').($bucketLabels[$buckets] ?? '');
            $buckets = [$buckets];
        }

        return 'data-el-drill tabindex="0" title="Klik 2x untuk melihat daftar Mantri"'
            .' data-el-branch="'.e($branch ?? '').'"'
            .' data-el-unit="'.e($unit ?? '').'"'
            .' data-el-buckets="'.e(implode(',', $buckets)).'"'
            .' data-el-label="'.e($label ?? '').'"';
    };
@endphp

<div class="mantri-extreme-viewbar">
    <div>
        <div class="mantri-extreme-viewbar__title">Ringkasan Mantri Extreme Low</div>
        <div class="mantri-extreme-viewbar__copy">
            {{ $isBranchView
                ? 'Jumlah Mantri pada setiap kategori realisasi, diringkas per cabang.'
                : 'Jumlah Mantri pada setiap kategori realisasi, diringkas per unit kerja.' }}
        </div>
    </div>
    <div class="mantri-extreme-viewbar__switch" role="group" aria-label="Pilih ringkasan Extreme Low Mantri">
        <a href="{{ $perUnitKerjaUrl }}" class="{{ !$isBranchView ? 'active' : '' }}">
            <i class="fas fa-sitemap mr-1"></i> Unit Kerja
        </a>
        <a href="{{ $perCabangUrl }}" class="{{ $isBranchView ? 'active' : '' }}">
            <i class="fas fa-building mr-1"></i> Cabang
        </a>
    </div>
</div>

<div class="rm-mikro-table-wrap table-container">
    <table class="table table-sm rm-mikro-table mantri-monitoring-table">
        <thead>
            <tr>
                <th class="head-base" rowspan="3">No</th>
                <th class="head-base" rowspan="3">Branch Office</th>
                @if (!$isBranchView)
                    <th class="head-base" rowspan="3">Unit Kerja</th>
                @endif
                <th class="head-base" rowspan="3">Total Mantri</th>
                <th class="head-base" colspan="2" rowspan="2">Belum Realisasi</th>
                <th class="head-extreme" colspan="8">Extreme Low</th>
                <th class="head-low" colspan="4">Low</th>
                <th class="head-under" colspan="2">Total Under 800 Juta</th>
                <th class="head-mid" colspan="4">Mid</th>
                <th class="head-high" colspan="2">High</th>
            </tr>
            <tr>
                @foreach (['el_0_100', 'el_100_200', 'el_200_400'] as $bucketKey)
                    <th class="head-extreme" colspan="2">{{ $total['buckets'][$bucketKey]['label'] ?? '-' }}</th>
                @endforeach
                <th class="head-extreme" colspan="2">{{ $total['extreme_low']['label'] ?? 'Total Mantri Extreme Low' }}</th>
                @foreach (['low_400_600', 'low_600_800'] as $bucketKey)
                    <th class="head-low" colspan="2">{{ $total['buckets'][$bucketKey]['label'] ?? '-' }}</th>
                @endforeach
                <th class="head-under" colspan="2">{{ $total['under_800']['label'] ?? 'Total Under 800 Juta' }}</th>
                @foreach (['mid_800_1000', 'mid_1000_1200'] as $bucketKey)
                    <th class="head-mid" colspan="2">{{ $total['buckets'][$bucketKey]['label'] ?? '-' }}</th>
                @endforeach
                <th class="head-high" colspan="2">{{ $total['buckets']['high_1200']['label'] ?? '-' }}</th>
            </tr>
            <tr>
                @for ($i = 0; $i < 11; $i++)
                    <th>Org</th>
                    <th>%</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="text-center">{{ $row['no'] ?? $loop->iteration }}</td>
                    <td class="strong">{{ $row['branch_office'] ?? '-' }}</td>
                    @if (!$isBranchView)
                        <td>{{ $row['nama_uker'] ?? '-' }}</td>
                    @endif
                    @php
                        $drillBranch = $row['branch_office'] ?? null;
                        $drillUnit = $isBranchView ? null : ($row['nama_uker'] ?? null);
                    @endphp
                    <td class="text-right strong" {!! $drillAttrs($drillBranch, $drillUnit, $extremeLowGroups['all'], 'Total Mantri') !!}>{{ $formatAmount($row['total_mantri'] ?? 0) }}</td>
                    <td class="text-right heat-muted" {!! $drillAttrs($drillBranch, $drillUnit, 'none') !!}>{{ $formatAmount($row['buckets']['none']['deb'] ?? 0) }}</td>
                    <td class="text-right heat-muted" {!! $drillAttrs($drillBranch, $drillUnit, 'none') !!}>{{ $formatPercent($row['buckets']['none']['pct'] ?? 0, 2) }}</td>

                    @foreach (['el_0_100', 'el_100_200', 'el_200_400'] as $bucketKey)
                        <td class="text-right cell-extreme" {!! $drillAttrs($drillBranch, $drillUnit, $bucketKey) !!}>{{ $formatAmount($row['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                        <td class="text-right cell-extreme" {!! $drillAttrs($drillBranch, $drillUnit, $bucketKey) !!}>{{ $formatPercent($row['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="text-right cell-extreme" {!! $drillAttrs($drillBranch, $drillUnit, $extremeLowGroups['extreme_low'], 'Total Mantri Extreme Low') !!}>{{ $formatAmount($row['extreme_low']['deb'] ?? 0) }}</td>
                    <td class="text-right cell-extreme" {!! $drillAttrs($drillBranch, $drillUnit, $extremeLowGroups['extreme_low'], 'Total Mantri Extreme Low') !!}>{{ $formatPercent($row['extreme_low']['pct'] ?? 0, 2) }}</td>
                    @foreach (['low_400_600', 'low_600_800'] as $bucketKey)
                        <td class="text-right cell-low" {!! $drillAttrs($drillBranch, $drillUnit, $bucketKey) !!}>{{ $formatAmount($row['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                        <td class="text-right cell-low" {!! $drillAttrs($drillBranch, $drillUnit, $bucketKey) !!}>{{ $formatPercent($row['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="text-right cell-under" {!! $drillAttrs($drillBranch, $drillUnit, $extremeLowGroups['under_800'], 'Total Under 800 Juta') !!}>{{ $formatAmount($row['under_800']['deb'] ?? 0) }}</td>
                    <td class="text-right cell-under" {!! $drillAttrs($drillBranch, $drillUnit, $extremeLowGroups['under_800'], 'Total Under 800 Juta') !!}>{{ $formatPercent($row['under_800']['pct'] ?? 0, 2) }}</td>
                    @foreach (['mid_800_1000', 'mid_1000_1200'] as $bucketKey)
                        <td class="text-right cell-mid" {!! $drillAttrs($drillBranch, $drillUnit, $bucketKey) !!}>{{ $formatAmount($row['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                        <td class="text-right cell-mid" {!! $drillAttrs($drillBranch, $drillUnit, $bucketKey) !!}>{{ $formatPercent($row['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="text-right cell-high" {!! $drillAttrs($drillBranch, $drillUnit, 'high_1200') !!}>{{ $formatAmount($row['buckets']['high_1200']['deb'] ?? 0) }}</td>
                    <td class="text-right cell-high" {!! $drillAttrs($drillBranch, $drillUnit, 'high_1200') !!}>{{ $formatPercent($row['buckets']['high_1200']['pct'] ?? 0, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $tableColumnCount }}">
                        <div class="rm-mikro-empty">Data Extreme Low Mantri belum tersedia untuk periode ini.</div>
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="rm-mikro-total">
                <td class="text-center" colspan="{{ $isBranchView ? 2 : 3 }}">{{ $total['branch_office'] ?? ($userBranchScope['upper_label'] ?? 'AREA 6') }}</td>
                <td class="text-right" {!! $drillAttrs(null, null, $extremeLowGroups['all'], 'Total Mantri') !!}>{{ $formatAmount($total['total_mantri'] ?? 0) }}</td>
                <td class="text-right heat-muted" {!! $drillAttrs(null, null, 'none') !!}>{{ $formatAmount($total['buckets']['none']['deb'] ?? 0) }}</td>
                <td class="text-right heat-muted" {!! $drillAttrs(null, null, 'none') !!}>{{ $formatPercent($total['buckets']['none']['pct'] ?? 0, 2) }}</td>
                @foreach (['el_0_100', 'el_100_200', 'el_200_400'] as $bucketKey)
                    <td class="text-right cell-extreme" {!! $drillAttrs(null, null, $bucketKey) !!}>{{ $formatAmount($total['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                    <td class="text-right cell-extreme" {!! $drillAttrs(null, null, $bucketKey) !!}>{{ $formatPercent($total['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                @endforeach
                <td class="text-right cell-extreme" {!! $drillAttrs(null, null, $extremeLowGroups['extreme_low'], 'Total Mantri Extreme Low') !!}>{{ $formatAmount($total['extreme_low']['deb'] ?? 0) }}</td>
                <td class="text-right cell-extreme" {!! $drillAttrs(null, null, $extremeLowGroups['extreme_low'], 'Total Mantri Extreme Low') !!}>{{ $formatPercent($total['extreme_low']['pct'] ?? 0, 2) }}</td>
                @foreach (['low_400_600', 'low_600_800'] as $bucketKey)
                    <td class="text-right cell-low" {!! $drillAttrs(null, null, $bucketKey) !!}>{{ $formatAmount($total['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                    <td class="text-right cell-low" {!! $drillAttrs(null, null, $bucketKey) !!}>{{ $formatPercent($total['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                @endforeach
                <td class="text-right cell-under" {!! $drillAttrs(null, null, $extremeLowGroups['under_800'], 'Total Under 800 Juta') !!}>{{ $formatAmount($total['under_800']['deb'] ?? 0) }}</td>
                <td class="text-right cell-under" {!! $drillAttrs(null, null, $extremeLowGroups['under_800'], 'Total Under 800 Juta') !!}>{{ $formatPercent($total['under_800']['pct'] ?? 0, 2) }}</td>
                @foreach (['mid_800_1000', 'mid_1000_1200'] as $bucketKey)
                    <td class="text-right cell-mid" {!! $drillAttrs(null, null, $bucketKey) !!}>{{ $formatAmount($total['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                    <td class="text-right cell-mid" {!! $drillAttrs(null, null, $bucketKey) !!}>{{ $formatPercent($total['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                @endforeach
                <td class="text-right cell-high" {!! $drillAttrs(null, null, 'high_1200') !!}>{{ $formatAmount($total['buckets']['high_1200']['deb'] ?? 0) }}</td>
                <td class="text-right cell-high" {!! $drillAttrs(null, null, 'high_1200') !!}>{{ $formatPercent($total['buckets']['high_1200']['pct'] ?? 0, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

<script type="application/json" id="extremeLowMantriDetails">@json(['details' => $extremeLowDetails, 'labels' => $bucketLabels])</script>

<div class="modal fade" id="extremeLowMantriModal" tabindex="-1" role="dialog" aria-labelledby="extremeLowMantriModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="extremeLowMantriModalTitle">Daftar Mantri</h5>
                    <div class="extreme-low-modal__scope" data-el-modal-scope></div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="extreme-low-modal__toolbar">
                    <span class="extreme-low-modal__count" data-el-modal-count></span>
                    <input type="search" class="form-control form-control-sm" placeholder="Cari PN / nama / unit..." data-el-modal-search>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped extreme-low-modal__table mb-0">
                        <thead>
                            <tr>
                                <th class="text-center">No</th>
                                <th>PN</th>
                                <th>Nama Mantri</th>
                                <th>Branch Office</th>
                                <th>Unit Kerja</th>
                                <th>Kategori</th>
                                <th class="text-right">Realisasi (Jt)</th>
                            </tr>
                        </thead>
                        <tbody data-el-modal-body></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const source = document.getElementById('extremeLowMantriDetails');
        const modal = document.getElementById('extremeLowMantriModal');
        if (!source || !modal) {
            return;
        }

        let data = { details: [], labels: {} };
        try {
            data = JSON.parse(source.textContent || '{}') || data;
        } catch (error) {
            // Keep the table usable even when the embedded detail payload is malformed.
        }

        const body = modal.querySelector('[data-el-modal-body]');
        const count = modal.querySelector('[data-el-modal-count]');
        const scope = modal.querySelector('[data-el-modal-scope]');
        const search = modal.querySelector('[data-el-modal-search]');
        const title = modal.querySelector('.modal-title');
        let activeRows = [];

        const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[char]);
        const formatJuta = value => Number(value || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const render = function () {
            const keyword = (search.value || '').trim().toLowerCase();
            const rows = keyword === ''
                ? activeRows
                : activeRows.filter(row => [row.pn, row.nama, row.branch_office, row.nama_uker]
                    .some(value => String(value || '').toLowerCase().includes(keyword)));

            count.textContent = keyword === ''
                ? `${activeRows.length} Mantri`
                : `${rows.length} dari ${activeRows.length} Mantri`;
            body.innerHTML = rows.length
                ? rows.map((row, index) => `
                    <tr>
                        <td class="text-center">${index + 1}</td>
                        <td>${escapeHtml(row.pn)}</td>
                        <td class="strong">${escapeHtml(row.nama)}</td>
                        <td>${escapeHtml(row.branch_office)}</td>
                        <td>${escapeHtml(row.nama_uker)}</td>
                        <td>${escapeHtml(data.labels[row.bucket_key] || row.bucket_key)}</td>
                        <td class="text-right">${formatJuta(row.realisasi_juta)}</td>
                    </tr>`).join('')
                : '<tr><td colspan="7" class="text-center text-muted py-3">Tidak ada Mantri pada kategori ini.</td></tr>';
        };

        const open = function (cell) {
            const branch = cell.dataset.elBranch || '';
            const unit = cell.dataset.elUnit || '';
            const buckets = (cell.dataset.elBuckets || '').split(',').filter(Boolean);

            activeRows = (data.details || []).filter(row => buckets.includes(row.bucket_key)
                && (branch === '' || row.branch_office === branch)
                && (unit === '' || row.nama_uker === unit));

            title.textContent = cell.dataset.elLabel || 'Daftar Mantri';
            scope.textContent = [branch || 'Seluruh Cabang', unit].filter(Boolean).join(' • ');
            search.value = '';
            render();

            if (window.jQuery && window.jQuery.fn.modal) {
                window.jQuery(modal).modal('show');
            }
        };

        search.addEventListener('input', render);
        document.addEventListener('dblclick', function (event) {
            const cell = event.target.closest('[data-el-drill]');
            if (!cell) {
                return;
            }

            event.preventDefault();
            window.getSelection()?.removeAllRanges();
            open(cell);
        });
        document.addEventListener('keydown', function (event) {
            const cell = event.target.closest?.('[data-el-drill]');
            if (!cell || event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            open(cell);
        });
    })();
</script>

@once
    <style>
        .mantri-extreme-viewbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: .85rem;
            padding: .75rem .85rem;
            border: 1px solid #dbe3ec;
            border-radius: 8px;
            background: #f8fafc;
        }

        .mantri-extreme-viewbar__title {
            color: #334155;
            font-size: .78rem;
            font-weight: 800;
        }

        .mantri-extreme-viewbar__copy {
            margin-top: .12rem;
            color: #64748b;
            font-size: .75rem;
        }

        .mantri-extreme-viewbar__switch {
            display: inline-flex;
            padding: 3px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #ffffff;
            flex: 0 0 auto;
        }

        .mantri-extreme-viewbar__switch a {
            padding: .38rem .6rem;
            border-radius: 4px;
            color: #475569;
            font-size: .75rem;
            font-weight: 700;
            text-decoration: none;
        }

        .mantri-extreme-viewbar__switch a.active {
            background: #0b5cab;
            color: #ffffff;
        }

        [data-el-drill] {
            cursor: pointer;
            user-select: none;
        }

        [data-el-drill]:hover,
        [data-el-drill]:focus {
            outline: 2px solid #0b5cab;
            outline-offset: -2px;
        }

        .extreme-low-modal__scope {
            margin-top: .15rem;
            color: #64748b;
            font-size: .78rem;
            font-weight: 700;
        }

        .extreme-low-modal__toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .65rem;
        }

        .extreme-low-modal__toolbar input {
            max-width: 260px;
        }

        .extreme-low-modal__count {
            color: #334155;
            font-size: .8rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .extreme-low-modal__table {
            font-size: .78rem;
        }

        .extreme-low-modal__table thead th {
            position: sticky;
            top: 0;
            background: #f1f5f9;
            white-space: nowrap;
        }

        @media (max-width: 767.98px) {
            .mantri-extreme-viewbar {
                align-items: stretch;
                flex-direction: column;
            }

            .mantri-extreme-viewbar__switch {
                width: 100%;
            }

            .mantri-extreme-viewbar__switch a {
                flex: 1 1 50%;
                text-align: center;
            }
        }
    </style>
@endonce
