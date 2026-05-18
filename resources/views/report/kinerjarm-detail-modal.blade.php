@php
    $formatAmount = $formatAmount ?? fn ($value, int $decimals = 1) => number_format(((float) $value) / 1000000, $decimals, ',', '.');
    $formatPercent = $formatPercent ?? fn ($value, int $decimals = 2) => number_format((float) $value, $decimals, ',', '.');
    $detailMode = $detailMode ?? 'default';
    $historyRangeLabel = $historyRangeLabel ?? null;
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
            CONSUMER Surplesi Plafon Net{{ $historyRangeLabel ? ' - ' . $historyRangeLabel : '' }}
        </p>
        <h5 class="modal-title" id="rmDetailModalLabel">{{ $rm }}</h5>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body kinerja-rm-modal__body">
    @if($detailsByYear->isEmpty())
        <div class="table-responsive kinerja-rm-modal__table-wrap">
            <table class="table table-sm mb-0 kinerja-rm-modal__table">
                <tbody>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">Tidak ada surplesi plafon net untuk RM ini pada rentang periode terpilih.</td>
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

        <div class="tab-content">
            @foreach($detailsByYear as $year => $yearDetails)
                @php
                    $isActiveYear = (string) $year === $activeHistoryYear;
                    $totalSurplus = (float) $yearDetails->sum(fn ($detail) => (float) ($detail['surplus_plafon'] ?? 0));
                    $totalDebitur = (int) $yearDetails->sum(fn ($detail) => (int) ($detail['debitur'] ?? 0));
                @endphp
                <div
                    class="tab-pane fade {{ $isActiveYear ? 'show active' : '' }}"
                    id="{{ $modalUid }}-consumer-year-{{ $year }}"
                    role="tabpanel"
                    aria-labelledby="{{ $modalUid }}-consumer-year-{{ $year }}-tab"
                >
                    <div class="kinerja-rm-modal__summary">
                        <div>
                            <span>Debitur Surplesi {{ $year }}</span>
                            <strong>{{ number_format($totalDebitur, 0, ',', '.') }}</strong>
                        </div>
                        <div>
                            <span>Plafon Net {{ $year }}</span>
                            <strong>{{ $formatAmount($totalSurplus) }}</strong>
                        </div>
                        <div>
                            <span>Basis</span>
                            <strong>Scope RM</strong>
                        </div>
                        <div>
                            <span>Periode</span>
                            <strong>{{ $year }}</strong>
                        </div>
                    </div>

                    <div class="table-responsive kinerja-rm-modal__table-wrap">
                        <table class="table table-sm mb-0 kinerja-rm-modal__table">
                            <thead>
                                <tr>
                                    <th class="text-center">Tahun</th>
                                    <th class="text-center">Posisi</th>
                                    <th class="text-center">Pembanding</th>
                                    <th class="text-start">Scope</th>
                                    <th class="text-start">Unit / Produk</th>
                                    <th class="text-end">Plafon Lalu</th>
                                    <th class="text-end">Plafon Kini</th>
                                    <th class="text-end">Net</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($yearDetails as $detail)
                                    <tr>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark">{{ $detail['year'] ?? '-' }}</span>
                                        </td>
                                        <td class="text-center fw-bold">{{ $detail['periode'] }}</td>
                                        <td class="text-center">{{ $detail['previous_period'] }}</td>
                                        <td class="text-start">{{ $detail['account'] ?: '-' }}</td>
                                        <td class="text-start">
                                            <div class="fw-semibold">{{ $detail['unit'] ?: $detail['cabang'] }}</div>
                                            <small class="text-muted">{{ $detail['produk'] }} / {{ number_format((int) ($detail['debitur'] ?? 0), 0, ',', '.') }} deb</small>
                                        </td>
                                        <td class="text-end">{{ $formatAmount($detail['previous_plafon']) }}</td>
                                        <td class="text-end">{{ $formatAmount($detail['current_plafon']) }}</td>
                                        <td class="text-end fw-bold text-success">{{ $formatAmount($detail['surplus_plafon']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="7" class="text-end">TOTAL NET {{ $year }}</td>
                                    <td class="text-end">{{ $formatAmount($totalSurplus) }}</td>
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
    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Tutup</button>
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
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
                    $totalLoanOs = (float) $yearDetails->sum(fn ($detail) => (float) ($detail['loan_os'] ?? 0));
                    $totalLarValue = (float) $yearDetails->sum(fn ($detail) => (float) ($detail['lar_value'] ?? 0));
                    $totalRealisasiOs = (float) $yearDetails->sum(fn ($detail) => (float) ($detail['realisasi_os'] ?? 0));
                    $totalPctLar = $totalLoanOs > 0 ? ($totalLarValue / $totalLoanOs) * 100 : 0;
                    $totalPencRealisasi = ($totalRealisasiOs / 1000000) >= 1600 ? 'A' : 'B';
                    $totalPencLar = $totalPctLar < 17.5 ? 'A' : 'B';
                @endphp
                <div
                    class="tab-pane fade {{ $isActiveYear ? 'show active' : '' }}"
                    id="{{ $modalUid }}-year-{{ $year }}"
                    role="tabpanel"
                    aria-labelledby="{{ $modalUid }}-year-{{ $year }}-tab"
                >
                    <div class="kinerja-rm-modal__summary">
                        <div>
                            <span>Realisasi OS {{ $year }}</span>
                            <strong>{{ $formatAmount($totalRealisasiOs) }}</strong>
                        </div>
                        <div>
                            <span>Penc. Realisasi {{ $year }}</span>
                            <strong class="{{ $totalPencRealisasi === 'A' ? 'text-success' : 'text-danger' }}">{{ $totalPencRealisasi }}</strong>
                        </div>
                        <div>
                            <span>% LAR {{ $year }}</span>
                            <strong>{{ $formatPercent($totalPctLar, 2) }}%</strong>
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
                                    <th class="text-center">Tahun</th>
                                    <th class="text-center">Periode</th>
                                    <th class="text-start">Unit Kerja / Cabang</th>
                                    <th class="text-end">Realisasi OS (Rp Juta)</th>
                                    <th class="text-center">Penc. Realisasi</th>
                                    <th class="text-end">% LAR</th>
                                    <th class="text-center">Penc. LAR</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($yearDetails as $detail)
                                    <tr>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark">{{ $detail['year'] ?? '-' }}</span>
                                        </td>
                                        <td class="text-center fw-bold">{{ $detail['periode'] }}</td>
                                        <td class="text-start">{{ $detail['cabang'] }}</td>
                                        <td class="text-end">{{ $formatAmount($detail['realisasi_os']) }}</td>
                                        <td class="text-center">
                                            <span class="kinerja-rm-modal__grade {{ $detail['penc_realisasi'] === 'A' ? 'is-good' : 'is-bad' }}">
                                                {{ $detail['penc_realisasi'] }}
                                            </span>
                                        </td>
                                        <td class="text-end">{{ $formatPercent($detail['pct_lar'], 2) }}%</td>
                                        <td class="text-center">
                                            <span class="kinerja-rm-modal__grade {{ $detail['penc_lar'] === 'A' ? 'is-good' : 'is-bad' }}">
                                                {{ $detail['penc_lar'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end">TOTAL {{ $year }}</td>
                                    <td class="text-end">{{ $formatAmount($totalRealisasiOs) }}</td>
                                    <td class="text-center">
                                        <span class="kinerja-rm-modal__grade {{ $totalPencRealisasi === 'A' ? 'is-good' : 'is-bad' }}">{{ $totalPencRealisasi }}</span>
                                    </td>
                                    <td class="text-end">{{ $formatPercent($totalPctLar, 2) }}%</td>
                                    <td class="text-center">
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
    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Tutup</button>
</div>
@endif
