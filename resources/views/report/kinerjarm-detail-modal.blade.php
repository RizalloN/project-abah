@php
    $formatAmountFormatter = $formatAmount ?? fn ($value, int $decimals = 0) => number_format(((float) $value) / 1000000, 0, ',', '.');
    $formatPercentFormatter = $formatPercent ?? fn ($value, int $decimals = 2) => number_format((float) $value, 2, ',', '.');
    $formatAmount = fn ($value, int $decimals = 0) => $formatAmountFormatter($value, 0);
    $formatPercent = fn ($value, int $decimals = 2) => $formatPercentFormatter($value, 2);
    $detailMode = $detailMode ?? 'default';
    $historyRangeLabel = $historyRangeLabel ?? null;
    $smallSummariesByYear = $smallSummariesByYear ?? [];
    $selectedHistoryYear = isset($selectedHistoryYear) ? (string) $selectedHistoryYear : null;
    $modalUid = 'rmh-' . substr(md5((string) ($rm ?? '') . '|' . (string) ($segmen ?? '') . '|' . $detailMode), 0, 10);
@endphp

@if($detailMode === 'consumer_surplus')
@php
    $visibleDetails = collect($details ?? [])->map(function ($detail) {
        if (empty($detail['year']) && !empty($detail['periode_raw'])) {
            $detail['year'] = \Carbon\Carbon::parse($detail['periode_raw'])->year;
        }

        return $detail;
    })->values();
    $detailsByYear = $visibleDetails
        ->groupBy(fn ($detail) => (string) ($detail['year'] ?? 'Tidak Diketahui'))
        ->sortKeysDesc();
    $activeHistoryYear = $selectedHistoryYear !== null && $detailsByYear->has($selectedHistoryYear)
        ? $selectedHistoryYear
        : (string) $detailsByYear->keys()->first();
@endphp

<div class="modal-header kinerja-rm-modal__header">
    <div>
        <p class="kinerja-rm-modal__eyebrow">
            CONSUMER{{ !empty($selectedProduct) ? ' · ' . $selectedProduct : '' }} Delta OS{{ $historyRangeLabel ? ' - ' . $historyRangeLabel : '' }}
        </p>
        <h5 class="modal-title" id="rmDetailModalLabel">{{ $rm }}</h5>
    </div>
    <button type="button" class="kinerja-rm-modal__close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Tutup">&times;</button>
</div>
<div class="modal-body kinerja-rm-modal__body">
    @if($detailsByYear->isEmpty())
        <div class="table-responsive kinerja-rm-modal__table-wrap">
            <table class="table table-sm mb-0 kinerja-rm-modal__table">
                <tbody>
                    <tr>
                        <td colspan="16" class="text-center is-center py-4 text-muted">Tidak ada data pembanding bulan sebelumnya untuk RM ini pada rentang periode terpilih.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @else
        <div class="kinerja-rm-modal__toolbar">
            <ul class="nav nav-pills gap-2 mb-0" role="tablist" aria-label="Pilih tahun history RM">
                @foreach($detailsByYear as $year => $yearDetails)
                    @php $isActiveYear = (string) $year === $activeHistoryYear; @endphp
                    <li class="nav-item" role="presentation">
                        <button
                            class="nav-link {{ $isActiveYear ? 'active' : '' }}"
                            id="{{ $modalUid }}-consumer-year-{{ $year }}-tab"
                            data-bs-toggle="pill"
                            data-bs-target="#{{ $modalUid }}-consumer-year-{{ $year }}"
                            type="button"
                            role="tab"
                            aria-controls="{{ $modalUid }}-consumer-year-{{ $year }}"
                            aria-selected="{{ $isActiveYear ? 'true' : 'false' }}"
                        >
                            {{ $year }}{{ (string) $year === $selectedHistoryYear ? ' (tahun berjalan)' : ' (tahun lalu)' }}
                        </button>
                    </li>
                @endforeach
            </ul>
            <div class="kinerja-rm-modal__legend" aria-label="Keterangan breakdown realisasi">
                <span class="kinerja-rm-modal__unit-chip">Satuan: Rp Juta</span>
                <span class="kinerja-rm-modal__legend-item is-baru"><i class="fas fa-circle" aria-hidden="true"></i> Baru</span>
                <span class="kinerja-rm-modal__legend-item is-suplesi"><i class="fas fa-circle" aria-hidden="true"></i> Suplesi</span>
            </div>
        </div>

        <div class="tab-content">
            @foreach($detailsByYear as $year => $yearDetails)
                @php
                    $isActiveYear = (string) $year === $activeHistoryYear;
                    $monthlyDetails = $yearDetails
                        ->groupBy(fn ($detail) => \Carbon\Carbon::parse($detail['periode_raw'])->format('Y-m'))
                        ->sortKeys()
                        ->map(function ($monthDetails) {
                            $summaryDetails = $monthDetails->filter(fn ($detail) => (bool) ($detail['is_summary'] ?? false));
                            $sourceDetails = $summaryDetails->isNotEmpty() ? $summaryDetails : $monthDetails;
                            $first = $sourceDetails->first();
                            $totalRealization = (float) $sourceDetails->sum(fn ($detail) => (float) ($detail['delta_os'] ?? $detail['surplus_plafon'] ?? 0));
                            $targetJgOs = (float) ($first['target_jg_os'] ?? 0.0);

                            return [
                                'month_label' => \Carbon\Carbon::parse($first['periode_raw'])->translatedFormat('M Y'),
                                'period_label' => $first['periode'] ?? '-',
                                'previous_period' => $first['previous_period'] ?? '-',
                                'target_jg_deb' => (int) ($first['target_jg_deb'] ?? 0),
                                'target_jg_os' => $targetJgOs,
                                'current_debitur' => (int) $sourceDetails->sum(fn ($detail) => (int) ($detail['current_debitur'] ?? 0)),
                                'current_os' => (float) $sourceDetails->sum(fn ($detail) => (float) ($detail['current_os'] ?? $detail['current_plafon'] ?? 0)),
                                'previous_debitur' => (int) $sourceDetails->sum(fn ($detail) => (int) ($detail['previous_debitur'] ?? 0)),
                                'previous_os' => (float) $sourceDetails->sum(fn ($detail) => (float) ($detail['previous_os'] ?? $detail['previous_plafon'] ?? 0)),
                                'baru_debitur' => (int) $sourceDetails->sum(fn ($detail) => (int) ($detail['baru_debitur'] ?? 0)),
                                'baru_os' => (float) $sourceDetails->sum(fn ($detail) => (float) ($detail['baru_os'] ?? 0)),
                                'suplesi_debitur' => (int) $sourceDetails->sum(fn ($detail) => (int) ($detail['suplesi_debitur'] ?? 0)),
                                'suplesi_os' => (float) $sourceDetails->sum(fn ($detail) => (float) ($detail['suplesi_os'] ?? 0)),
                                'delta_debitur' => (int) $sourceDetails->sum(fn ($detail) => (int) ($detail['debitur'] ?? 0)),
                                'delta_os' => $totalRealization,
                                'pg_pct' => $targetJgOs > 0 ? ($totalRealization / $targetJgOs) * 100 : null,
                            ];
                        })
                        ->values();
                    $yearSummaryDetails = $yearDetails->filter(fn ($detail) => (bool) ($detail['is_summary'] ?? false));
                    $yearTotalSource = $yearSummaryDetails->isNotEmpty() ? $yearSummaryDetails : $yearDetails;
                    $totalSurplus = (float) $yearTotalSource->sum(fn ($detail) => (float) ($detail['delta_os'] ?? $detail['surplus_plafon'] ?? 0));
                    $totalDebitur = (int) $yearTotalSource->sum(fn ($detail) => (int) ($detail['debitur'] ?? 0));
                    $totalBaruDebitur = (int) $monthlyDetails->sum(fn ($detail) => (int) ($detail['baru_debitur'] ?? 0));
                    $totalBaruOs = (float) $monthlyDetails->sum(fn ($detail) => (float) ($detail['baru_os'] ?? 0));
                    $totalSuplesiDebitur = (int) $monthlyDetails->sum(fn ($detail) => (int) ($detail['suplesi_debitur'] ?? 0));
                    $totalSuplesiOs = (float) $monthlyDetails->sum(fn ($detail) => (float) ($detail['suplesi_os'] ?? 0));
                    $baruSharePct = $totalSurplus > 0 ? ($totalBaruOs / $totalSurplus) * 100 : null;
                    $suplesiSharePct = $totalSurplus > 0 ? ($totalSuplesiOs / $totalSurplus) * 100 : null;
                    $totalTargetDebitur = (int) $monthlyDetails->sum(fn ($detail) => (int) ($detail['target_jg_deb'] ?? 0));
                    $totalTargetOs = (float) $monthlyDetails->sum(fn ($detail) => (float) ($detail['target_jg_os'] ?? 0));
                    $totalCurrentDebitur = (int) $monthlyDetails->sum(fn ($detail) => (int) ($detail['current_debitur'] ?? 0));
                    $totalCurrentOs = (float) $monthlyDetails->sum(fn ($detail) => (float) ($detail['current_os'] ?? 0));
                    $totalPreviousDebitur = (int) $monthlyDetails->sum(fn ($detail) => (int) ($detail['previous_debitur'] ?? 0));
                    $totalPreviousOs = (float) $monthlyDetails->sum(fn ($detail) => (float) ($detail['previous_os'] ?? 0));
                    $totalPgPct = $totalTargetOs > 0 ? ($totalSurplus / $totalTargetOs) * 100 : null;
                @endphp
                <div
                    class="tab-pane fade {{ $isActiveYear ? 'show active' : '' }}"
                    id="{{ $modalUid }}-consumer-year-{{ $year }}"
                    role="tabpanel"
                    aria-labelledby="{{ $modalUid }}-consumer-year-{{ $year }}-tab"
                >
                    <div class="kinerja-rm-modal__summary kinerja-rm-modal__summary--consumer">
                        <div>
                            <span>Debitur Naik {{ $year }}</span>
                            <strong>{{ number_format($totalDebitur, 0, ',', '.') }}</strong>
                            <small class="kinerja-rm-modal__summary-sub">Baru {{ number_format($totalBaruDebitur, 0, ',', '.') }} · Suplesi {{ number_format($totalSuplesiDebitur, 0, ',', '.') }}</small>
                        </div>
                        <div>
                            <span>Delta OS {{ $year }}</span>
                            <strong class="{{ $totalSurplus < 0 ? 'text-danger' : 'text-success' }}">{{ $formatAmount($totalSurplus) }}</strong>
                            <small class="kinerja-rm-modal__summary-sub">Rp Juta</small>
                        </div>
                        <div class="is-baru">
                            <span>Realisasi Baru {{ $year }}</span>
                            <strong>{{ $formatAmount($totalBaruOs) }}</strong>
                            <small class="kinerja-rm-modal__summary-sub">{{ number_format($totalBaruDebitur, 0, ',', '.') }} deb{{ $baruSharePct !== null ? ' · ' . $formatPercent($baruSharePct) . '%' : '' }}</small>
                        </div>
                        <div class="is-suplesi">
                            <span>Realisasi Suplesi {{ $year }}</span>
                            <strong>{{ $formatAmount($totalSuplesiOs) }}</strong>
                            <small class="kinerja-rm-modal__summary-sub">{{ number_format($totalSuplesiDebitur, 0, ',', '.') }} deb{{ $suplesiSharePct !== null ? ' · ' . $formatPercent($suplesiSharePct) . '%' : '' }}</small>
                        </div>
                        <div>
                            <span>Target JG {{ $year }}</span>
                            <strong>
                                {{ $totalTargetDebitur > 0 ? number_format($totalTargetDebitur, 0, ',', '.') . ' deb / ' : '' }}{{ $totalTargetOs > 0 ? $formatAmount($totalTargetOs) : '-' }}
                            </strong>
                            <small class="kinerja-rm-modal__summary-sub">Akumulasi Target {{ $monthlyDetails->count() }} Bulan</small>
                        </div>
                        <div>
                            <span>% PG {{ $year }}</span>
                            <strong class="{{ $totalPgPct !== null && $totalPgPct >= 100 ? 'text-success' : ($totalPgPct !== null && $totalPgPct < 50 ? 'text-danger' : '') }}">{{ $totalPgPct === null ? '-' : $formatPercent($totalPgPct) . '%' }}</strong>
                            <small class="kinerja-rm-modal__summary-sub">Pencapaian Growth</small>
                        </div>
                        <div>
                            <span>OS Bulan Ini {{ $year }}</span>
                            <strong>{{ $formatAmount($totalCurrentOs) }}</strong>
                            <small class="kinerja-rm-modal__summary-sub">{{ number_format($totalCurrentDebitur, 0, ',', '.') }} deb</small>
                        </div>
                        <div>
                            <span>OS Bulan Lalu {{ $year }}</span>
                            <strong>{{ $formatAmount($totalPreviousOs) }}</strong>
                            <small class="kinerja-rm-modal__summary-sub">{{ number_format($totalPreviousDebitur, 0, ',', '.') }} deb</small>
                        </div>
                    </div>

                    <div class="table-responsive kinerja-rm-modal__table-wrap">
                        <table class="table table-sm mb-0 kinerja-rm-modal__table">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="text-start is-left">Bulan</th>
                                    <th rowspan="2" class="text-center is-center">Posisi</th>
                                    <th rowspan="2" class="text-center is-center">Pembanding</th>
                                    <th colspan="2" class="text-center is-center">Target JG</th>
                                    <th colspan="4" class="text-center is-center">Posisi OS</th>
                                    <th colspan="2" class="text-center is-center modal-head-baru">Baru</th>
                                    <th colspan="2" class="text-center is-center modal-head-suplesi">Suplesi</th>
                                    <th colspan="3" class="text-center is-center modal-head-total">Total Realisasi</th>
                                </tr>
                                <tr>
                                    <th class="text-end is-num">Target Deb</th>
                                    <th class="text-end is-num">Target Rp</th>
                                    <th class="text-end is-num">Deb Ini</th>
                                    <th class="text-end is-num">OS Ini</th>
                                    <th class="text-end is-num">Deb Lalu</th>
                                    <th class="text-end is-num">OS Lalu</th>
                                    <th class="text-end is-num modal-subhead-baru">Deb</th>
                                    <th class="text-end is-num modal-subhead-baru">Rp</th>
                                    <th class="text-end is-num modal-subhead-suplesi">Deb</th>
                                    <th class="text-end is-num modal-subhead-suplesi">Rp</th>
                                    <th class="text-end is-num modal-subhead-total">Deb Naik</th>
                                    <th class="text-end is-num modal-subhead-total">Delta OS</th>
                                    <th class="text-end is-num modal-subhead-total">% PG</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($monthlyDetails as $detail)
                                    <tr>
                                        <td class="text-start is-left is-bold">{{ $detail['month_label'] }}</td>
                                        <td class="text-center is-center">{{ $detail['period_label'] }}</td>
                                        <td class="text-center is-center">{{ $detail['previous_period'] }}</td>
                                        <td class="text-end is-num">{{ $detail['target_jg_deb'] > 0 ? number_format((int) $detail['target_jg_deb'], 0, ',', '.') : '-' }}</td>
                                        <td class="text-end is-num is-semibold">{{ $detail['target_jg_os'] > 0 ? $formatAmount($detail['target_jg_os']) : '-' }}</td>
                                        <td class="text-end is-num">{{ number_format((int) $detail['current_debitur'], 0, ',', '.') }}</td>
                                        <td class="text-end is-num is-semibold">{{ $formatAmount($detail['current_os']) }}</td>
                                        <td class="text-end is-num">{{ number_format((int) $detail['previous_debitur'], 0, ',', '.') }}</td>
                                        <td class="text-end is-num is-semibold">{{ $formatAmount($detail['previous_os']) }}</td>
                                        <td class="text-end is-num modal-col-baru">{{ number_format((int) $detail['baru_debitur'], 0, ',', '.') }}</td>
                                        <td class="text-end is-num is-semibold modal-col-baru">{{ $formatAmount($detail['baru_os']) }}</td>
                                        <td class="text-end is-num modal-col-suplesi">{{ number_format((int) $detail['suplesi_debitur'], 0, ',', '.') }}</td>
                                        <td class="text-end is-num is-semibold modal-col-suplesi">{{ $formatAmount($detail['suplesi_os']) }}</td>
                                        <td class="text-end is-num is-semibold">{{ number_format((int) $detail['delta_debitur'], 0, ',', '.') }}</td>
                                        <td class="text-end is-num is-bold {{ (float) $detail['delta_os'] < 0 ? 'text-danger' : 'text-success' }}">{{ $formatAmount($detail['delta_os']) }}</td>
                                        <td class="text-end is-num is-bold">{{ $detail['pg_pct'] === null ? '-' : $formatPercent($detail['pg_pct']) . '%' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end is-num">TOTAL {{ $year }}</td>
                                    <td class="text-end is-num">{{ $totalTargetDebitur > 0 ? number_format($totalTargetDebitur, 0, ',', '.') : '-' }}</td>
                                    <td class="text-end is-num">{{ $totalTargetOs > 0 ? $formatAmount($totalTargetOs) : '-' }}</td>
                                    <td class="text-end is-num">{{ number_format($totalCurrentDebitur, 0, ',', '.') }}</td>
                                    <td class="text-end is-num">{{ $formatAmount($totalCurrentOs) }}</td>
                                    <td class="text-end is-num">{{ number_format($totalPreviousDebitur, 0, ',', '.') }}</td>
                                    <td class="text-end is-num">{{ $formatAmount($totalPreviousOs) }}</td>
                                    <td class="text-end is-num">{{ number_format($totalBaruDebitur, 0, ',', '.') }}</td>
                                    <td class="text-end is-num">{{ $formatAmount($totalBaruOs) }}</td>
                                    <td class="text-end is-num">{{ number_format($totalSuplesiDebitur, 0, ',', '.') }}</td>
                                    <td class="text-end is-num">{{ $formatAmount($totalSuplesiOs) }}</td>
                                    <td class="text-end is-num">{{ number_format($totalDebitur, 0, ',', '.') }}</td>
                                    <td class="text-end is-num {{ (float) $totalSurplus < 0 ? 'text-danger' : 'text-success' }}">{{ $formatAmount($totalSurplus) }}</td>
                                    <td class="text-end is-num">{{ $totalPgPct === null ? '-' : $formatPercent($totalPgPct) . '%' }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
<div class="modal-footer kinerja-rm-modal__footer">
    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal" data-dismiss="modal">Tutup</button>
</div>
@else
@php
    $visibleDetails = collect($details ?? [])->map(function ($detail) {
        if (empty($detail['year']) && !empty($detail['periode_raw'])) {
            $detail['year'] = \Carbon\Carbon::parse($detail['periode_raw'])->year;
        }

        return $detail;
    })->filter(function ($detail) {
        $realisasiOs = (float) ($detail['realisasi_os'] ?? 0);
        $larValue = (float) ($detail['lar_value'] ?? 0);
        $pctLar = (float) ($detail['pct_lar'] ?? 0);

        return abs($realisasiOs) > 0 || abs($larValue) > 0 || abs($pctLar) > 0;
    })->values();
    $detailsByYear = $visibleDetails
        ->groupBy(fn ($detail) => (string) ($detail['year'] ?? 'Tidak Diketahui'))
        ->sortKeysDesc();
    $activeHistoryYear = $selectedHistoryYear !== null && $detailsByYear->has($selectedHistoryYear)
        ? $selectedHistoryYear
        : (string) $detailsByYear->keys()->first();
@endphp

<div class="modal-header kinerja-rm-modal__header">
    <div>
        <p class="kinerja-rm-modal__eyebrow">
            {{ $segmen ?? 'RM' }} Detail{{ $historyRangeLabel ? ' - ' . $historyRangeLabel : '' }}
        </p>
        <h5 class="modal-title" id="rmDetailModalLabel">{{ $rm }}</h5>
    </div>
    <button type="button" class="kinerja-rm-modal__close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Tutup">&times;</button>
</div>
<div class="modal-body kinerja-rm-modal__body">
    @if($detailsByYear->isEmpty())
        <div class="table-responsive kinerja-rm-modal__table-wrap">
            <table class="table table-sm mb-0 kinerja-rm-modal__table">
                <tbody>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Data historis tidak tersedia untuk rentang periode terpilih.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @else
        <ul class="nav nav-pills gap-2 mb-3" role="tablist" aria-label="Pilih tahun history RM">
            @foreach($detailsByYear as $year => $yearDetails)
                @php $isActiveYear = (string) $year === $activeHistoryYear; @endphp
                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link {{ $isActiveYear ? 'active' : '' }}"
                        id="{{ $modalUid }}-year-{{ $year }}-tab"
                        data-bs-toggle="pill"
                        data-bs-target="#{{ $modalUid }}-year-{{ $year }}"
                        type="button"
                        role="tab"
                        aria-controls="{{ $modalUid }}-year-{{ $year }}"
                        aria-selected="{{ $isActiveYear ? 'true' : 'false' }}"
                    >
                        {{ $year }}{{ (string) $year === $selectedHistoryYear ? ' (tahun berjalan)' : ' (tahun lalu)' }}
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content">
            @foreach($detailsByYear as $year => $yearDetails)
                @php
                    $isActiveYear = (string) $year === $activeHistoryYear;
                    $smallSummary = $smallSummariesByYear[(string) $year] ?? null;
                    $totalLoanOs = (float) $yearDetails->sum(fn ($detail) => (float) ($detail['loan_os'] ?? 0));
                    $totalLarValue = (float) $yearDetails->sum(fn ($detail) => (float) ($detail['lar_value'] ?? 0));
                    $totalRealisasiOs = $smallSummary !== null
                        ? (float) $smallSummary['realisasi_os']
                        : (float) $yearDetails->sum(fn ($detail) => (float) ($detail['realisasi_os'] ?? 0));
                    $totalPctLar = $smallSummary !== null
                        ? (float) $smallSummary['pct_lar']
                        : ($totalLoanOs > 0 ? ($totalLarValue / $totalLoanOs) * 100 : 0);
                    $totalPencRealisasi = $smallSummary['penc_realisasi'] ?? (($totalRealisasiOs / 1000000) >= 1600 ? 'A' : 'B');
                    $totalPencLar = $smallSummary['penc_lar'] ?? ($totalPctLar < (($segmen ?? '') === 'SMALL' ? 15.0 : 17.5) ? 'A' : 'B');
                    $realisasiLabel = $smallSummary !== null ? 'Ratas Realisasi OS' : 'Realisasi OS';
                    $larPeriodLabel = $smallSummary['closed_period_label'] ?? $year;
                    $footerLabel = $smallSummary !== null ? 'RATAS' : 'TOTAL';
                @endphp
                <div
                    class="tab-pane fade {{ $isActiveYear ? 'show active' : '' }}"
                    id="{{ $modalUid }}-year-{{ $year }}"
                    role="tabpanel"
                    aria-labelledby="{{ $modalUid }}-year-{{ $year }}-tab"
                >
                    <div class="kinerja-rm-modal__summary">
                        <div>
                            <span>{{ $realisasiLabel }} {{ $year }}</span>
                            <strong>{{ $formatAmount($totalRealisasiOs) }}</strong>
                        </div>
                        <div>
                            <span>Penc. Realisasi {{ $year }}</span>
                            <strong class="{{ $totalPencRealisasi === 'A' ? 'text-success' : 'text-danger' }}">{{ $totalPencRealisasi }}</strong>
                        </div>
                        <div>
                            <span>% LAR {{ $larPeriodLabel }}</span>
                            <strong>{{ $formatPercent($totalPctLar) }}%</strong>
                        </div>
                        <div>
                            <span>Penc. LAR {{ $year }}</span>
                            <strong class="{{ $totalPencLar === 'A' ? 'text-success' : 'text-danger' }}">{{ $totalPencLar }}</strong>
                        </div>
                    </div>

                    <div class="table-responsive kinerja-rm-modal__table-wrap">
                        <table class="table table-sm mb-0 kinerja-rm-modal__table">
                            <thead>
                                <tr>
                                    <th class="text-center is-center">Tahun</th>
                                    <th class="text-center is-center">Periode</th>
                                    <th class="text-start is-left">Unit Kerja / Cabang</th>
                                    <th class="text-end is-num">Realisasi OS (Rp Juta)</th>
                                    <th class="text-center is-center">Penc. Realisasi</th>
                                    <th class="text-end is-num">% LAR</th>
                                    <th class="text-center is-center">Penc. LAR</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($yearDetails as $detail)
                                    <tr>
                                        <td class="text-center is-center">
                                            <span class="badge bg-light text-dark">{{ $detail['year'] ?? '-' }}</span>
                                        </td>
                                        <td class="text-center is-center is-bold">{{ $detail['periode'] }}</td>
                                        <td class="text-start is-left">{{ $detail['cabang'] }}</td>
                                        <td class="text-end is-num">{{ $formatAmount($detail['realisasi_os']) }}</td>
                                        <td class="text-center is-center">
                                            <span class="kinerja-rm-modal__grade {{ $detail['penc_realisasi'] === 'A' ? 'is-good' : 'is-bad' }}">
                                                {{ $detail['penc_realisasi'] }}
                                            </span>
                                        </td>
                                        <td class="text-end is-num">{{ $formatPercent($detail['pct_lar']) }}%</td>
                                        <td class="text-center is-center">
                                            <span class="kinerja-rm-modal__grade {{ $detail['penc_lar'] === 'A' ? 'is-good' : 'is-bad' }}">
                                                {{ $detail['penc_lar'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end is-num">{{ $footerLabel }} {{ $year }}</td>
                                    <td class="text-end is-num">{{ $formatAmount($totalRealisasiOs) }}</td>
                                    <td class="text-center is-center">
                                        <span class="kinerja-rm-modal__grade {{ $totalPencRealisasi === 'A' ? 'is-good' : 'is-bad' }}">{{ $totalPencRealisasi }}</span>
                                    </td>
                                    <td class="text-end is-num">{{ $formatPercent($totalPctLar) }}%</td>
                                    <td class="text-center is-center">
                                        <span class="kinerja-rm-modal__grade {{ $totalPencLar === 'A' ? 'is-good' : 'is-bad' }}">{{ $totalPencLar }}</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
<div class="modal-footer kinerja-rm-modal__footer">
    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal" data-dismiss="modal">Tutup</button>
</div>
@endif
