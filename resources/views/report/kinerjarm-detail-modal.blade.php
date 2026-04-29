<div class="modal-header">
    <h5 class="modal-title" id="rmDetailModalLabel">Detail Kinerja RM: {{ $rm }}</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
@php
    $formatAmount = $formatAmount ?? fn ($value, int $decimals = 1) => number_format(((float) $value) / 1000000, $decimals, ',', '.');
    $formatPercent = $formatPercent ?? fn ($value, int $decimals = 2) => number_format((float) $value, $decimals, ',', '.');
    $visibleDetails = collect($details ?? [])->filter(function ($detail) {
        $realisasiOs = (float) ($detail['realisasi_os'] ?? 0);
        $larValue = (float) ($detail['lar_value'] ?? 0);
        $pctLar = (float) ($detail['pct_lar'] ?? 0);

        return abs($realisasiOs) > 0 || abs($larValue) > 0 || abs($pctLar) > 0;
    })->values();
@endphp
<div class="modal-body p-0">
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0" style="font-size: 0.75rem;">
            <thead class="bg-light">
                <tr>
                    <th class="text-center" style="vertical-align: middle;">Periode</th>
                    <th class="text-center" style="vertical-align: middle;">Unit Kerja / Cabang</th>
                    <th class="text-center">Realisasi OS (Rp Juta)</th>
                    <th class="text-center">Penc. Realisasi (Target 1.600)</th>
                    <th class="text-center">% LAR</th>
                    <th class="text-center">Penc. LAR (Target 17,5%)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($visibleDetails as $detail)
                    <tr>
                        <td class="text-center fw-bold">{{ $detail['periode'] }}</td>
                        <td class="text-center">{{ $detail['cabang'] }}</td>
                        <td class="text-end px-3">{{ $formatAmount($detail['realisasi_os']) }}</td>
                        <td class="text-center">
                            <span class="badge {{ $detail['penc_realisasi'] === 'A' ? 'bg-success' : 'bg-danger' }}">
                                {{ $detail['penc_realisasi'] }}
                            </span>
                        </td>
                        <td class="text-end px-3">{{ $formatPercent($detail['pct_lar'], 2) }}%</td>
                        <td class="text-center">
                            <span class="badge {{ $detail['penc_lar'] === 'A' ? 'bg-success' : 'bg-danger' }}">
                                {{ $detail['penc_lar'] }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-3 text-muted">Data historis tidak tersedia untuk tahun ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
</div>
