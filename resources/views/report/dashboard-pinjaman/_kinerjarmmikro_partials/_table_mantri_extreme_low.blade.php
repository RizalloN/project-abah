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
    $tableColumnCount = $identityColumnCount + 20;
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
                @for ($i = 0; $i < 10; $i++)
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
                    <td class="text-right strong">{{ $formatAmount($row['total_mantri'] ?? 0) }}</td>

                    @foreach (['el_0_100', 'el_100_200', 'el_200_400'] as $bucketKey)
                        <td class="text-right cell-extreme">{{ $formatAmount($row['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                        <td class="text-right cell-extreme">{{ $formatPercent($row['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="text-right cell-extreme">{{ $formatAmount($row['extreme_low']['deb'] ?? 0) }}</td>
                    <td class="text-right cell-extreme">{{ $formatPercent($row['extreme_low']['pct'] ?? 0, 2) }}</td>
                    @foreach (['low_400_600', 'low_600_800'] as $bucketKey)
                        <td class="text-right cell-low">{{ $formatAmount($row['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                        <td class="text-right cell-low">{{ $formatPercent($row['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="text-right cell-under">{{ $formatAmount($row['under_800']['deb'] ?? 0) }}</td>
                    <td class="text-right cell-under">{{ $formatPercent($row['under_800']['pct'] ?? 0, 2) }}</td>
                    @foreach (['mid_800_1000', 'mid_1000_1200'] as $bucketKey)
                        <td class="text-right cell-mid">{{ $formatAmount($row['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                        <td class="text-right cell-mid">{{ $formatPercent($row['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="text-right cell-high">{{ $formatAmount($row['buckets']['high_1200']['deb'] ?? 0) }}</td>
                    <td class="text-right cell-high">{{ $formatPercent($row['buckets']['high_1200']['pct'] ?? 0, 2) }}</td>
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
                <td class="text-right">{{ $formatAmount($total['total_mantri'] ?? 0) }}</td>
                @foreach (['el_0_100', 'el_100_200', 'el_200_400'] as $bucketKey)
                    <td class="text-right cell-extreme">{{ $formatAmount($total['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                    <td class="text-right cell-extreme">{{ $formatPercent($total['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                @endforeach
                <td class="text-right cell-extreme">{{ $formatAmount($total['extreme_low']['deb'] ?? 0) }}</td>
                <td class="text-right cell-extreme">{{ $formatPercent($total['extreme_low']['pct'] ?? 0, 2) }}</td>
                @foreach (['low_400_600', 'low_600_800'] as $bucketKey)
                    <td class="text-right cell-low">{{ $formatAmount($total['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                    <td class="text-right cell-low">{{ $formatPercent($total['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                @endforeach
                <td class="text-right cell-under">{{ $formatAmount($total['under_800']['deb'] ?? 0) }}</td>
                <td class="text-right cell-under">{{ $formatPercent($total['under_800']['pct'] ?? 0, 2) }}</td>
                @foreach (['mid_800_1000', 'mid_1000_1200'] as $bucketKey)
                    <td class="text-right cell-mid">{{ $formatAmount($total['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                    <td class="text-right cell-mid">{{ $formatPercent($total['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                @endforeach
                <td class="text-right cell-high">{{ $formatAmount($total['buckets']['high_1200']['deb'] ?? 0) }}</td>
                <td class="text-right cell-high">{{ $formatPercent($total['buckets']['high_1200']['pct'] ?? 0, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

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
