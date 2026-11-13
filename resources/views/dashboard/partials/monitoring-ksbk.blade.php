@php
    $ksbk = (array) ($monitoringKsbk ?? []);
    $ksbkContext = array_replace((array) ($microReportContext ?? []), (array) data_get($ksbk, 'view_context', []));
    $ksbkBranchKey = (string) data_get($ksbkContext, 'branch_key', 'area6');
    $ksbkShowUnits = $ksbkBranchKey !== 'area6' && $ksbkBranchKey !== '';
    $ksbkGroups = (array) data_get($ksbk, $ksbkShowUnits ? 'unit_groups' : 'groups', []);
    $ksbkNominativeUrl = \Illuminate\Support\Facades\Route::has('dashboard.micro-report-nominatives') ? route('dashboard.micro-report-nominatives') : url('/dashboard/micro-report-nominatives');
    $ksbkCountMetrics = ['small_count' => 'small', 'large_count' => 'large', 'total_count' => 'all', 'ksbk_count' => 'ksbk', 'non_ksbk_count' => 'non_ksbk', 'realized_count' => 'realized', 'unrealized_count' => 'unrealized'];
    $ksbkSummary = (array) data_get($ksbk, 'summary', []);
    if (data_get($ksbkSummary, 'realized_count') === null && ! empty($ksbkGroups)) {
        $ksbkHasAnyRealized = false;
        $ksbkAgg = [
            'small_count' => 0, 'large_count' => 0, 'total_count' => 0,
            'ksbk_count' => 0, 'ksbk_amount' => 0,
            'non_ksbk_count' => 0, 'non_ksbk_amount' => 0,
            'realized_count' => 0, 'realized_amount' => 0,
        ];
        foreach ($ksbkGroups as $ksbkG) {
            $ksbkAgg['small_count'] += (int) data_get($ksbkG, 'small_count', 0);
            $ksbkAgg['large_count'] += (int) data_get($ksbkG, 'large_count', 0);
            $ksbkAgg['total_count'] += (int) data_get($ksbkG, 'total_count', 0);
            if (is_numeric(data_get($ksbkG, 'realized_count'))) {
                $ksbkHasAnyRealized = true;
                $ksbkAgg['realized_count'] += (int) data_get($ksbkG, 'realized_count', 0);
                $ksbkAgg['realized_amount'] += (float) data_get($ksbkG, 'realized_amount', 0);
                $ksbkAgg['ksbk_count'] += (int) data_get($ksbkG, 'ksbk_count', 0);
                $ksbkAgg['ksbk_amount'] += (float) data_get($ksbkG, 'ksbk_amount', 0);
                $ksbkAgg['non_ksbk_count'] += (int) data_get($ksbkG, 'non_ksbk_count', 0);
                $ksbkAgg['non_ksbk_amount'] += (float) data_get($ksbkG, 'non_ksbk_amount', 0);
            }
        }
        if ($ksbkHasAnyRealized) {
            $ksbkTotalCount = (int) data_get($ksbkSummary, 'total_count', $ksbkAgg['total_count']);
            $ksbkUnrealizedCount = max(0, $ksbkTotalCount - $ksbkAgg['realized_count']);
            $ksbkSummary = array_merge($ksbkSummary, $ksbkAgg, [
                'total_count' => $ksbkTotalCount,
                'unrealized_count' => $ksbkUnrealizedCount,
                'unrealized_share' => $ksbkTotalCount > 0 ? 100 * $ksbkUnrealizedCount / $ksbkTotalCount : 0,
            ]);
        }
    }
    $ksbkMetrics = [
        'small_count', 'large_count', 'total_count',
        'ksbk_count', 'ksbk_amount', 'non_ksbk_count', 'non_ksbk_amount',
        'realized_count', 'realized_amount', 'unrealized_count', 'unrealized_share',
    ];
    $ksbkNumber = static function ($value, string $metric): string {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return '—';
        }
        if (str_ends_with($metric, '_share')) {
            return number_format((float) $value, 1, ',', '.').'%';
        }
        if (str_ends_with($metric, '_amount')) {
            $millions = (float) $value / 1000000;

            return number_format($millions, abs($millions - round($millions)) < 0.0000001 ? 0 : 2, ',', '.');
        }

        return number_format((float) $value, 0, ',', '.');
    };
@endphp

@once
    <style>
        .micro-ksbk{min-width:0}
        .micro-ksbk .micro-ops-section__head{margin-bottom:1rem}
        .micro-ksbk__meta,.micro-ksbk__notice{margin:.5rem 0;color:#40566c;font-size:.8rem;line-height:1.5}
        .micro-ksbk__scroll{max-width:100%;overflow-x:auto;overscroll-behavior-x:contain;border:1px solid #c7d9e9}
        .micro-ksbk__scroll:focus-visible{outline:3px solid #d97706;outline-offset:3px}
        .micro-ksbk .micro-ksbk__table{width:100%;min-width:1120px;border-collapse:collapse;font-size:.75rem;line-height:1.35;font-variant-numeric:tabular-nums}
        .micro-ksbk .micro-ksbk__table th,.micro-ksbk .micro-ksbk__table td{padding:.5rem .55rem;vertical-align:middle}
        .micro-ksbk .micro-ksbk__table thead th{border:1px solid #fff;background:#0070c0;color:#fff;text-align:center;font-weight:700;white-space:normal}
        .micro-ksbk .micro-ksbk__table thead .micro-ksbk__pending{background:#737373}
        .micro-ksbk .micro-ksbk__table thead .micro-ksbk__units{font-size:.7rem;font-weight:400;font-style:italic}
        .micro-ksbk .micro-ksbk__table tbody td,.micro-ksbk .micro-ksbk__table tbody th{border-bottom:1px solid #e1ebf3;background:#fff;color:#174c78;text-align:right;font-weight:400}
        .micro-ksbk .micro-ksbk__table tbody td:first-child,.micro-ksbk .micro-ksbk__table tbody th[scope=row]{text-align:left;min-width:130px;overflow-wrap:anywhere}
        .micro-ksbk .micro-ksbk__table tbody tr:hover td,.micro-ksbk .micro-ksbk__table tbody tr:hover th{background:#f0f7fc}
        .micro-ksbk .micro-ksbk__table tbody .micro-ksbk__share{background:#ececec;color:#263749}
        .micro-ksbk .micro-ksbk__table tfoot th,.micro-ksbk .micro-ksbk__table tfoot td{border-top:2px solid #fff;background:#0070c0;color:#fff;text-align:right;font-weight:700}
        .micro-ksbk .micro-ksbk__table tfoot th{text-align:center}
        .micro-ksbk .micro-ksbk__table tfoot .micro-ksbk__share{background:#dedede;color:#172b40}
        @media(max-width:640px){.micro-ksbk .micro-ksbk__table{font-size:.72rem}.micro-ksbk__meta{font-size:.75rem}}
    </style>
@endonce

<section class="micro-ops-section micro-ksbk" aria-labelledby="micro-ksbk-title">
    <div class="micro-ops-section__head">
        <div>
            <h3 id="micro-ksbk-title">Monitoring Realisasi KSBK</h3>
            @if(data_get($ksbk, 'loan_period'))
                <p class="micro-ksbk__meta">Posisi pinjaman {{ \Carbon\Carbon::parse($ksbk['loan_period'])->translatedFormat('d F Y') }} · {{ data_get($ksbk, 'loan_source') === 'daily_loan_dinamis' ? 'Daily Loan Dinamis' : 'LW321PN' }}</p>
            @endif
        </div>
    </div>

    @if(empty($ksbk['available']) || $ksbkGroups === [])
        <div class="micro-ops-empty micro-ops-empty--compact" role="status">
            <span>{{ data_get($ksbk, 'message') ?: 'Impor laporan Monitoring Realisasi KSBK untuk menampilkan pipeline dan realisasi.' }}</span>
        </div>
    @else
        @if(data_get($ksbk, 'message'))
            <p class="micro-ksbk__notice" role="status">{{ $ksbk['message'] }}</p>
        @endif
        <div class="micro-ksbk__scroll" role="region" tabindex="0" aria-labelledby="micro-ksbk-title" aria-describedby="micro-ksbk-note">
            <table class="micro-ksbk__table">
                <caption class="sr-only">Pipeline usulan lolos dan realisasi KSBK per Regional Office dan Branch Office</caption>
                <thead>
                    <tr>
                        <th scope="col" rowspan="4">Regional Office</th>
                        <th scope="col" rowspan="4">Branch Office</th>
                        @if($ksbkShowUnits)<th scope="col" rowspan="4">Unit Kerja</th>@endif
                        <th scope="colgroup" colspan="3">Pipeline Usulan - Lolos</th>
                        <th scope="colgroup" colspan="8">Realisasi</th>
                    </tr>
                    <tr>
                        <th scope="col" rowspan="2">SBK ≤ 150juta</th>
                        <th scope="col" rowspan="2">SBK &gt; 150juta</th>
                        <th scope="col" rowspan="2">Total</th>
                        <th scope="colgroup" colspan="6">Sudah Real</th>
                        <th scope="colgroup" colspan="2" class="micro-ksbk__pending">Belum Real</th>
                    </tr>
                    <tr>
                        <th scope="colgroup" colspan="2">Real KSBK</th>
                        <th scope="colgroup" colspan="2">Real Non KSBK</th>
                        <th scope="colgroup" colspan="2">Total</th>
                        <th scope="colgroup" colspan="2" class="micro-ksbk__pending">Total</th>
                    </tr>
                    <tr>
                        <th scope="col" class="micro-ksbk__units">(Deb)</th>
                        <th scope="col" class="micro-ksbk__units">(Deb)</th>
                        <th scope="col" class="micro-ksbk__units">(Deb)</th>
                        <th scope="col" class="micro-ksbk__units">(Deb)</th>
                        <th scope="col" class="micro-ksbk__units">(Plafond Rp juta)</th>
                        <th scope="col" class="micro-ksbk__units">(Deb)</th>
                        <th scope="col" class="micro-ksbk__units">(Plafond Rp juta)</th>
                        <th scope="col" class="micro-ksbk__units">(Deb)</th>
                        <th scope="col" class="micro-ksbk__units">(Plafond Rp juta)</th>
                        <th scope="col" class="micro-ksbk__units micro-ksbk__pending">(Deb)</th>
                        <th scope="col" class="micro-ksbk__units micro-ksbk__pending">%</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ksbkGroups as $ksbkRow)
                        <tr data-micro-report-row data-report="ksbk" data-level="{{ $ksbkShowUnits ? 'unit' : 'bo' }}"
                            data-nominative-url="{{ $ksbkNominativeUrl }}" data-branch="{{ data_get($ksbkRow, 'branch_code', data_get($ksbkRow, 'branch_name', '')) }}"
                            data-unit="{{ data_get($ksbkRow, 'unit_code', '') }}" data-mbm=""
                            data-periode="{{ data_get($ksbkContext, 'periode', data_get($ksbkContext, 'period', '')) }}" data-cabang="{{ $ksbkBranchKey }}"
                            data-group-label="{{ data_get($ksbkRow, $ksbkShowUnits ? 'unit_name' : 'branch_name', '') }}">
                            <td>{{ data_get($ksbkRow, 'regional_office', '—') }}</td>
                            @if($ksbkShowUnits)
                                <td>{{ data_get($ksbkRow, 'branch_name', '—') }}</td>
                                <th scope="row">{{ data_get($ksbkRow, 'unit_name', 'Unit belum terpetakan') }}</th>
                            @else
                                <th scope="row">{{ data_get($ksbkRow, 'branch_name', '—') }}</th>
                            @endif
                            @foreach($ksbkMetrics as $ksbkMetric)
                                <td data-ksbk-metric="{{ $ksbkMetric }}" @class(['micro-ksbk__share' => $ksbkMetric === 'unrealized_share'])>@if(isset($ksbkCountMetrics[$ksbkMetric]) && is_numeric(data_get($ksbkRow, $ksbkMetric)))<button type="button" class="micro-report-count" data-micro-nominative-open data-metric="{{ $ksbkCountMetrics[$ksbkMetric] }}" aria-label="Lihat nominatif {{ str_replace('_', ' ', $ksbkCountMetrics[$ksbkMetric]) }}">{{ $ksbkNumber(data_get($ksbkRow, $ksbkMetric), $ksbkMetric) }}</button>@else{{ $ksbkNumber(data_get($ksbkRow, $ksbkMetric), $ksbkMetric) }}@endif</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="{{ $ksbkShowUnits ? 3 : 2 }}">Total</th>
                        @foreach($ksbkMetrics as $ksbkMetric)
                            <td data-ksbk-metric="{{ $ksbkMetric }}" @class(['micro-ksbk__share' => $ksbkMetric === 'unrealized_share'])>{{ $ksbkNumber(data_get($ksbkSummary, $ksbkMetric), $ksbkMetric) }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
        <p id="micro-ksbk-note" class="micro-ksbk__meta">Deb = debitur. Plafond dalam Rp juta. Tanda — berarti nilai belum dapat diverifikasi. Klik jumlah debitur atau klik dua kali baris untuk melihat nominatif.</p>
    @endif
</section>
