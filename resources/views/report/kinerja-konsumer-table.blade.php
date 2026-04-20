@php
    $normalize = fn($v) => (float) $v / 1000000;
    $fmt = fn($v) => number_format($normalize($v), 1, ',', '.');

    $formatSigned = function ($v, $showArrow = true) use ($normalize) {
        $val = $normalize($v);
        $cls = $val > 0 ? 'pos' : ($val < 0 ? 'neg' : '');
        $icon = '';

        if ($showArrow) {
            if ($val > 0) {
                $icon = '<i class="fas fa-caret-up me-1"></i>';
            } elseif ($val < 0) {
                $icon = '<i class="fas fa-caret-down me-1"></i>';
            }
        }

        $prefix = ($val > 0 && !$showArrow) ? '+' : '';
        $display = number_format(abs($val), 1, ',', '.');
        if ($val < 0 && !$showArrow) {
            $display = '-' . $display;
        }

        return "<span class='delta-indicator {$cls}'>{$icon}{$prefix}{$display}</span>";
    };
@endphp

<div id="kinerjaContentArea" class="animate-reveal">
    <div class="px-4 pb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 style="font-size: 0.9rem; font-weight: 800; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 0.6rem;">
                <i class="fas fa-table text-primary" style="font-size: 0.8rem;"></i>
                TABEL RINCIAN KINERJA RM
            </h2>
            <div class="legend-box">
                <i class="fas fa-info-circle text-primary"></i>
                <span>Satuan Akuntansi: <strong>Rp, Juta</strong></span>
            </div>
        </div>

        <div class="kinerja-table-container shadow-sm">
            <table class="kinerja-konsumer-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 36px;">No.</th>
                        <th rowspan="2" style="width: 120px;">Kantor Cabang</th>
                        <th rowspan="2" style="width: 160px;">Nama RM / Pengelola</th>
                        <th rowspan="2" style="width: 110px;">Produk</th>
                        <th colspan="3" class="sub-head">OUTSTANDING KONSUMER</th>
                        <th colspan="3" class="accent-head">Δ PERIODE PER {{ $selectedPeriodShortLabel }}</th>
                        <th colspan="2" class="sub-head">TARGET REALISASI JG</th>
                        <th colspan="2" class="accent-head">PENCAPAIAN REALISASI JG</th>
                    </tr>
                    <tr>
                        <th class="sub-head" style="width: 82px;">31 DES {{ Carbon\Carbon::parse($ytdPeriod)->format('Y') }}</th>
                        <th class="sub-head" style="width: 82px;">{{ $mtdLabel }}</th>
                        <th class="sub-head" style="width: 88px;">{{ $selectedPeriodLabel }}</th>

                        <th class="accent-head" style="width: 70px;">YtD</th>
                        <th class="accent-head" style="width: 70px;">MtD</th>
                        <th class="accent-head" style="width: 70px;">DtD</th>

                        <th class="sub-head" style="width: 50px;">Deb</th>
                        <th class="sub-head" style="width: 80px;">Rp</th>

                        <th class="accent-head" style="width: 60px;">Deb</th>
                        <th class="accent-head" style="width: 85px;">Rp</th>
                    </tr>
                </thead>
                <tbody>
                    @php $no = 1; @endphp
                    @forelse($rows as $branch)
                        <!-- Branch Subtotal Row at TOP -->
                        <tr class="loan-branch-subtotal">
                            <td rowspan="{{ $branch['branch_rowspan'] }}" class="text-center-important" style="font-weight: 800; border-right: 1px solid #cbd5e1; background: #ffffff !important; color: #0f172a !important;">{{ $no++ }}</td>
                            <td rowspan="{{ $branch['branch_rowspan'] }}" class="merged-branch-cell">{{ $branch['cabang'] }}</td>
                            <td colspan="2" class="text-center-important" style="font-size: 0.65rem; letter-spacing: 0.8px; font-weight: 900; color: #93c5fd;">
                                <i class="fas fa-layer-group me-1"></i> TOTAL {{ $branch['cabang'] }}
                            </td>
                            <td>{{ $fmt($branch['subtotal']['ytd']) }}</td>
                            <td>{{ $fmt($branch['subtotal']['mtd']) }}</td>
                            <td class="highlight-curr">{{ $fmt($branch['subtotal']['curr']) }}</td>
                            <td>{!! $formatSigned($branch['subtotal']['delta_ytd']) !!}</td>
                            <td>{!! $formatSigned($branch['subtotal']['delta_mtd']) !!}</td>
                            <td>{!! $formatSigned($branch['subtotal']['delta_dtd']) !!}</td>
                            <td class="text-center-important" style="font-size: 0.72rem;">{{ $branch['subtotal']['target_jg_deb'] ?: '-' }}</td>
                            <td>{{ $branch['subtotal']['target_jg_os'] > 0 ? $fmt($branch['subtotal']['target_jg_os']) : '-' }}</td>
                            <td class="text-center-important">{{ ($branch['subtotal']['ach_deb'] ?? 0) > 0 ? number_format((float) $branch['subtotal']['ach_deb'], 0, ',', '.') : '-' }}</td>
                            <td>{{ ($branch['subtotal']['ach_os'] ?? 0) > 0 ? $fmt($branch['subtotal']['ach_os']) : '-' }}</td>
                        </tr>

                        @foreach($branch['rms'] as $rmName => $rmData)
                            @php 
                                if (trim((string)$rmName) === '00385844 -') {
                                    $rmName = '00385844 - Glagah Mahestya Yahya';
                                }
                                $isFirstRmRow = true; 
                            @endphp
                            @foreach($rmData['items'] as $item)
                                <tr>
                                    @if($isFirstRmRow)
                                        <td rowspan="{{ $rmData['rm_rowspan'] }}" class="merged-rm-cell">{{ $rmName }}</td>
                                        @php $isFirstRmRow = false; @endphp
                                    @endif

                                    <td class="text-start-important" style="font-size: 0.7rem; font-weight: 700; color: #475569; padding-left: 0.75rem;">
                                        {{ $item['product'] }}
                                    </td>
                                    <td>{{ $fmt($item['ytd']) }}</td>
                                    <td>{{ $fmt($item['mtd']) }}</td>
                                    <td class="highlight-curr">{{ $fmt($item['curr']) }}</td>
                                    <td>{!! $formatSigned($item['delta_ytd']) !!}</td>
                                    <td>{!! $formatSigned($item['delta_mtd']) !!}</td>
                                    <td>{!! $formatSigned($item['delta_dtd']) !!}</td>
                                    <td class="text-center-important" style="background: #fffefb; font-size: 0.7rem;">{{ $item['target_jg_deb'] ?: '' }}</td>
                                    <td style="background: #fffefb;">{{ $item['target_jg_os'] > 0 ? $fmt($item['target_jg_os']) : '' }}</td>
                                    <td class="text-center-important">{{ ($item['ach_deb'] ?? 0) > 0 ? number_format((float) $item['ach_deb'], 0, ',', '.') : '' }}</td>
                                    <td>{{ ($item['ach_os'] ?? 0) > 0 ? $fmt($item['ach_os']) : '' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="14" class="py-5 text-center text-muted">
                                <i class="fas fa-folder-open fa-3x mb-3 d-block" style="opacity: 0.3;"></i>
                                <span style="font-weight: 700; font-size: 0.95rem;">DATA TIDAK DITEMUKAN</span>
                                <p class="small mt-1">Silakan pilih parameter filter yang berbeda.</p>
                            </td>
                        </tr>
                    @endforelse

                    <!-- Grand Total Row -->
                    @if(!empty($rows))
                    <tr class="row-grand-total">
                        <td colspan="4" class="text-center-important" style="letter-spacing: 2px;">
                            <i class="fas fa-chart-line me-2"></i> GRAND TOTAL {{ $selectedProductLabel === 'Semua Produk' ? 'KONSUMER' : strtoupper($selectedProductLabel) }}
                        </td>
                        <td>{{ $fmt($total['ytd']) }}</td>
                        <td>{{ $fmt($total['mtd']) }}</td>
                        <td>{{ $fmt($total['curr']) }}</td>
                        <td>{!! $formatSigned($total['delta_ytd'], false) !!}</td>
                        <td>{!! $formatSigned($total['delta_mtd'], false) !!}</td>
                        <td>{!! $formatSigned($total['delta_dtd'], false) !!}</td>
                        <td class="text-center-important">{{ $total['target_jg_deb'] ?: '-' }}</td>
                        <td>{{ $total['target_jg_os'] > 0 ? $fmt($total['target_jg_os']) : '-' }}</td>
                        <td class="text-center-important">{{ ($total['ach_deb'] ?? 0) > 0 ? number_format((float) $total['ach_deb'], 0, ',', '.') : '-' }}</td>
                        <td>{{ ($total['ach_os'] ?? 0) > 0 ? $fmt($total['ach_os']) : '-' }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
