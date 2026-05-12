@php
    $formatAmount = $formatAmount ?? fn ($value, int $decimals = 1) => number_format(((float) $value) / 1000000, $decimals, ',', '.');
    $formatPercent = $formatPercent ?? fn ($value, int $decimals = 2) => number_format((float) $value, $decimals, ',', '.');
    $visibleDetails = collect($details ?? [])->filter(function ($detail) {
        $realisasiOs = (float) ($detail['realisasi_os'] ?? 0);
        $larValue = (float) ($detail['lar_value'] ?? 0);
        $pctLar = (float) ($detail['pct_lar'] ?? 0);

        return abs($realisasiOs) > 0 || abs($larValue) > 0 || abs($pctLar) > 0;
    })->values();
    $totalLoanOs = (float) $visibleDetails->sum(fn ($detail) => (float) ($detail['loan_os'] ?? 0));
    $totalLarValue = (float) $visibleDetails->sum(fn ($detail) => (float) ($detail['lar_value'] ?? 0));
    $totalRealisasiOs = (float) $visibleDetails->sum(fn ($detail) => (float) ($detail['realisasi_os'] ?? 0));
    $totalPctLar = $totalLoanOs > 0 ? ($totalLarValue / $totalLoanOs) * 100 : 0;
    $totalPencRealisasi = ($totalRealisasiOs / 1000000) >= 1600 ? 'A' : 'B';
    $totalPencLar = $totalPctLar < 17.5 ? 'A' : 'B';
@endphp

<div class="modal-header kinerja-rm-modal__header">
    <div>
        <p class="kinerja-rm-modal__eyebrow">{{ $segmen ?? 'RM' }} Detail</p>
        <h5 class="modal-title" id="rmDetailModalLabel">{{ $rm }}</h5>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body kinerja-rm-modal__body">
    <div class="kinerja-rm-modal__summary">
        <div>
            <span>Realisasi OS</span>
            <strong>{{ $formatAmount($totalRealisasiOs) }}</strong>
        </div>
        <div>
            <span>Penc. Realisasi</span>
            <strong class="{{ $totalPencRealisasi === 'A' ? 'text-success' : 'text-danger' }}">{{ $totalPencRealisasi }}</strong>
        </div>
        <div>
            <span>% LAR</span>
            <strong>{{ $formatPercent($totalPctLar, 2) }}%</strong>
        </div>
        <div>
            <span>Penc. LAR</span>
            <strong class="{{ $totalPencLar === 'A' ? 'text-success' : 'text-danger' }}">{{ $totalPencLar }}</strong>
        </div>
    </div>

    <div class="table-responsive kinerja-rm-modal__table-wrap">
        <table class="table table-sm mb-0 kinerja-rm-modal__table">
            <thead>
                <tr>
                    <th class="text-center">Periode</th>
                    <th class="text-start">Unit Kerja / Cabang</th>
                    <th class="text-end">Realisasi OS (Rp Juta)</th>
                    <th class="text-center">Penc. Realisasi</th>
                    <th class="text-end">% LAR</th>
                    <th class="text-center">Penc. LAR</th>
                </tr>
            </thead>
            <tbody>
                @forelse($visibleDetails as $detail)
                    <tr>
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
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">Data historis tidak tersedia untuk tahun ini.</td>
                    </tr>
                @endforelse
            </tbody>
            @if($visibleDetails->isNotEmpty())
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-end">TOTAL</td>
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
            @endif
        </table>
    </div>
</div>
<div class="modal-footer kinerja-rm-modal__footer">
    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Tutup</button>
</div>
