@php
    $showTargets = $showTargets ?? true;
    $compact = $compact ?? false;
    $sectionTitle = $sectionTitle ?? 'Performance OS';
    $sectionSubtitle = $sectionSubtitle ?? null;
    $sectionMeta = $sectionMeta ?? null;
    $grandTotalLabel = $grandTotalLabel ?? null;
    $emptyMessage = $emptyMessage ?? 'Silakan pilih parameter filter yang berbeda.';
    $tableClass = 'kinerja-konsumer-table' . ($compact ? ' kinerja-konsumer-table--compact' : '');
    $emptyColspan = $showTargets ? 14 : 10;
    $segmentLabels = [
        'CONSUMER' => 'RM',
        'SMALL' => 'SMALL RM',
        'MICRO' => 'MICRO RM',
    ];
    $segLabel = $segmentLabels[$selectedSegmen] ?? 'RM';
    $grandTotalLabel = $grandTotalLabel ?? ('GRAND TOTAL ' . ($selectedProductLabel === 'Semua Produk' ? $segLabel : strtoupper($selectedProductLabel)));
@endphp

<div class="kinerja-report-card">
    <div class="kinerja-report-card__header">
        <div class="kinerja-report-card__title-wrap">
            <h2 class="kinerja-report-card__title">
                <i class="fas fa-table" style="font-size: 0.78rem; color: var(--loan-blue);"></i>
                {{ $sectionTitle }}
            </h2>
            @if(!empty($sectionSubtitle))
                <p class="kinerja-report-card__subtitle">{{ $sectionSubtitle }}</p>
            @endif
        </div>
        @if(!empty($sectionMeta))
            <div class="kinerja-report-card__meta">
                <i class="fas fa-info-circle" style="color: var(--loan-blue);"></i>
                <span>{{ $sectionMeta }}</span>
            </div>
        @endif
    </div>

    <div class="kinerja-table-container shadow-sm">
        <table class="{{ $tableClass }}">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 36px;">No.</th>
                    <th rowspan="2" style="width: 120px;">Kantor Cabang</th>
                    <th rowspan="2" style="width: 160px;">Nama RM / Pengelola</th>
                    <th rowspan="2" style="width: 110px;">Produk</th>
                    <th colspan="3" class="sub-head">PERFORMANCE PER RM</th>
                    <th colspan="3" class="accent-head">DELTA PERIODE PER {{ $selectedPeriodShortLabel }}</th>
                    @if($showTargets)
                        <th colspan="2" class="sub-head">TARGET REALISASI JG</th>
                        <th colspan="2" class="accent-head">PENCAPAIAN REALISASI JG</th>
                    @endif
                </tr>
                <tr>
                    <th class="sub-head" style="width: 82px;">31 DES {{ Carbon\Carbon::parse($ytdPeriod)->format('Y') }}</th>
                    <th class="sub-head" style="width: 82px;">{{ $mtdLabel }}</th>
                    <th class="sub-head" style="width: 88px;">{{ $selectedPeriodLabel }}</th>

                    <th class="accent-head" style="width: 70px;">YtD</th>
                    <th class="accent-head" style="width: 70px;">MtD</th>
                    <th class="accent-head" style="width: 70px;">DtD</th>

                    @if($showTargets)
                        <th class="sub-head" style="width: 50px;">Deb</th>
                        <th class="sub-head" style="width: 80px;">Rp</th>

                        <th class="accent-head" style="width: 60px;">Deb</th>
                        <th class="accent-head" style="width: 85px;">Rp</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @php $no = 1; @endphp
                @forelse($rows as $branch)
                    <tr class="loan-branch-subtotal">
                        <td rowspan="{{ $branch['branch_rowspan'] }}" class="text-center-important" style="font-weight: 800; border-right: 1px solid var(--loan-border-strong); background: #ffffff !important; color: var(--loan-blue-ink) !important;">{{ $no++ }}</td>
                        <td rowspan="{{ $branch['branch_rowspan'] }}" class="merged-branch-cell">{{ $branch['cabang'] }}</td>
                        <td colspan="2" class="text-center-important" style="font-size: 0.7rem; letter-spacing: 0.08em; font-weight: 900; color: var(--loan-cyan);">
                            <i class="fas fa-layer-group me-1" style="opacity: 0.8;"></i> TOTAL {{ $branch['cabang'] }}
                        </td>
                        <td>{{ $fmt($branch['subtotal']['ytd']) }}</td>
                        <td>{{ $fmt($branch['subtotal']['mtd']) }}</td>
                        <td class="highlight-curr">{{ $fmt($branch['subtotal']['curr']) }}</td>
                        <td>{!! $formatSigned($branch['subtotal']['delta_ytd']) !!}</td>
                        <td>{!! $formatSigned($branch['subtotal']['delta_mtd']) !!}</td>
                        <td>{!! $formatSigned($branch['subtotal']['delta_dtd']) !!}</td>
                        @if($showTargets)
                            <td class="text-center-important" style="font-size: 0.72rem;">{{ $branch['subtotal']['target_jg_deb'] ?: '-' }}</td>
                            <td>{{ $branch['subtotal']['target_jg_os'] > 0 ? $fmt($branch['subtotal']['target_jg_os']) : '-' }}</td>
                            <td class="text-center-important">{{ ($branch['subtotal']['ach_deb'] ?? 0) > 0 ? number_format((float) $branch['subtotal']['ach_deb'], 0, ',', '.') : '-' }}</td>
                            <td>{{ ($branch['subtotal']['ach_os'] ?? 0) > 0 ? $fmt($branch['subtotal']['ach_os']) : '-' }}</td>
                        @endif
                    </tr>

                    @foreach($branch['rms'] as $rmName => $rmData)
                        @php
                            if (trim((string) $rmName) === '00385844 -') {
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

                                <td class="text-start-important" style="font-size: 0.7rem; font-weight: 700; color: var(--loan-muted); padding-left: 0.75rem;">
                                    {{ $item['product'] }}
                                </td>
                                <td>{{ $fmt($item['ytd']) }}</td>
                                <td>{{ $fmt($item['mtd']) }}</td>
                                <td class="highlight-curr">{{ $fmt($item['curr']) }}</td>
                                <td>{!! $formatSigned($item['delta_ytd']) !!}</td>
                                <td>{!! $formatSigned($item['delta_mtd']) !!}</td>
                                <td>{!! $formatSigned($item['delta_dtd']) !!}</td>
                                @if($showTargets)
                                    <td class="text-center-important" style="background: rgba(8, 87, 195, 0.02); font-size: 0.7rem;">{{ $item['target_jg_deb'] ?: '' }}</td>
                                    <td style="background: rgba(8, 87, 195, 0.02);">{{ $item['target_jg_os'] > 0 ? $fmt($item['target_jg_os']) : '' }}</td>
                                    <td class="text-center-important">{{ ($item['ach_deb'] ?? 0) > 0 ? number_format((float) $item['ach_deb'], 0, ',', '.') : '' }}</td>
                                    <td>{{ ($item['ach_os'] ?? 0) > 0 ? $fmt($item['ach_os']) : '' }}</td>
                                @endif
                            </tr>
                        @endforeach
                    @endforeach
                @empty
                    <tr>
                        <td colspan="{{ $emptyColspan }}" class="py-5 text-center text-muted">
                            <i class="fas fa-folder-open fa-3x mb-3 d-block" style="opacity: 0.3;"></i>
                            <span style="font-weight: 700; font-size: 0.95rem;">DATA TIDAK DITEMUKAN</span>
                            <p class="small mt-1">{{ $emptyMessage }}</p>
                        </td>
                    </tr>
                @endforelse

                @if(!empty($rows))
                    <tr class="row-grand-total">
                        <td colspan="4" class="text-center-important" style="letter-spacing: 2px;">
                            <i class="fas fa-chart-line me-2"></i> {{ $grandTotalLabel }}
                        </td>
                        <td>{{ $fmt($total['ytd']) }}</td>
                        <td>{{ $fmt($total['mtd']) }}</td>
                        <td>{{ $fmt($total['curr']) }}</td>
                        <td>{!! $formatSigned($total['delta_ytd'], false) !!}</td>
                        <td>{!! $formatSigned($total['delta_mtd'], false) !!}</td>
                        <td>{!! $formatSigned($total['delta_dtd'], false) !!}</td>
                        @if($showTargets)
                            <td class="text-center-important">{{ $total['target_jg_deb'] ?: '-' }}</td>
                            <td>{{ $total['target_jg_os'] > 0 ? $fmt($total['target_jg_os']) : '-' }}</td>
                            <td class="text-center-important">{{ ($total['ach_deb'] ?? 0) > 0 ? number_format((float) $total['ach_deb'], 0, ',', '.') : '-' }}</td>
                            <td>{{ ($total['ach_os'] ?? 0) > 0 ? $fmt($total['ach_os']) : '-' }}</td>
                        @endif
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
