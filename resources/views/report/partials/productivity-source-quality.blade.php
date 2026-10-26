{{--
    Notice kualitas sumber realisasi untuk laporan produktivitas.
    Input: $sourceQuality (hasil App\Support\ProductivitySourceQuality::summarize) atau null.
--}}
@php
    $psq = $sourceQuality ?? null;
    $psqVisible = is_array($psq) && ($psq['has_issues'] || $psq['from_lw321']);
@endphp
@if ($psqVisible)
    @php
        $psqDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->locale('id')->translatedFormat('d M Y') : '-';
        $psqJuta = fn ($value) => number_format(((float) $value) / 1000000, 0, ',', '.');
        $psqCount = fn ($value) => number_format((int) $value, 0, ',', '.');
        $psqMonth = \Carbon\Carbon::parse($psq['period'])->locale('id')->translatedFormat('M Y');
        $psqTone = $psq['has_issues'] ? 'warn' : 'info';
    @endphp
    <style>
        .psq { display: flex; gap: .75rem; align-items: flex-start; margin: 0 0 1rem; padding: .85rem 1rem; border-radius: 12px; border: 1px solid; font-size: .84rem; line-height: 1.5; }
        .psq.warn { background: var(--psq-warn-bg, #fff8e6); border-color: var(--psq-warn-line, #f5d27a); color: var(--psq-warn-ink, #6b3f06); }
        .psq.info { background: var(--psq-info-bg, #eef4fd); border-color: var(--psq-info-line, #c4d7f4); color: var(--psq-info-ink, #0b3a7a); }
        .psq__icon { margin-top: .15rem; flex: none; }
        .psq__body { min-width: 0; flex: 1; }
        .psq__head { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem .6rem; font-weight: 700; }
        .psq__tag { display: inline-flex; align-items: center; padding: .05rem .5rem; border-radius: 999px; font-size: .72rem; font-weight: 700; letter-spacing: .02em; background: rgba(255, 255, 255, .7); border: 1px solid currentColor; }
        .psq__list { margin: .4rem 0 0; padding-left: 1.1rem; }
        .psq__list li + li { margin-top: .2rem; }
        .psq__num { font-variant-numeric: tabular-nums; font-weight: 700; }
        .psq details { margin-top: .5rem; }
        .psq summary { cursor: pointer; font-weight: 600; width: fit-content; border-radius: 6px; }
        .psq summary:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
        .psq__table-wrap { margin-top: .5rem; overflow-x: auto; border-radius: 8px; border: 1px solid var(--psq-table-line, rgba(0, 0, 0, .1)); background: var(--psq-table-bg, #fff); color: var(--psq-table-ink, #1f2937); }
        .psq__table { width: 100%; border-collapse: collapse; font-size: .78rem; white-space: nowrap; }
        .psq__table th, .psq__table td { padding: .4rem .6rem; border-bottom: 1px solid var(--psq-table-line, rgba(0, 0, 0, .08)); text-align: left; }
        .psq .psq__table th, .psq .psq__table td { background: var(--psq-table-bg, #fff); }
        .psq__table th { font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: var(--psq-table-muted, #6b7280); }
        .psq__table td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .psq__table tr:last-child td { border-bottom: 0; }
        :root[data-theme="dark"] .psq.warn { --psq-warn-bg: var(--app-warning-soft, rgba(214, 145, 30, .16)); --psq-warn-line: rgba(242, 184, 92, .45); --psq-warn-ink: var(--app-warning, #f2b85c); }
        :root[data-theme="dark"] .psq.info { --psq-info-bg: var(--app-info-soft, rgba(61, 132, 232, .16)); --psq-info-line: rgba(125, 176, 245, .4); --psq-info-ink: var(--theme-link, #7db0f5); }
        :root[data-theme="dark"] .psq__tag { background: rgba(255, 255, 255, .06); }
        :root[data-theme="dark"] .psq__table-wrap { --psq-table-bg: var(--app-surface, #142238); --psq-table-ink: var(--app-ink, #e2ecf7); --psq-table-line: var(--app-line, #2d3c53); --psq-table-muted: var(--app-muted, #a4b6ca); }
    </style>
    <div class="psq {{ $psqTone }}" role="status">
        <i class="fas {{ $psq['has_issues'] ? 'fa-exclamation-triangle' : 'fa-info-circle' }} psq__icon" aria-hidden="true"></i>
        <div class="psq__body">
            <div class="psq__head">
                <span>Kualitas sumber realisasi posisi {{ $psqDate($psq['period']) }}</span>
                <span class="psq__tag">{{ $psq['from_lw321'] ? 'LW321PN · H-1' : 'Daily Loan Dinamis' }}</span>
            </div>
            @if ($psq['from_lw321'])
                <div>
                    Posisi ini bersumber LW321PN yang dinormalisasi ke format Daily Loan Dinamis.
                    @if ($psq['reference_period'])
                        Klasifikasi rekening lama mengikuti Daily Loan {{ $psqDate($psq['reference_period']) }}; kolektibilitas mengikuti saldo per kolek LW321PN.
                    @endif
                    Data akan digantikan otomatis saat Daily Loan Dinamis posisi yang sama diimport.
                </div>
            @endif
            @if ($psq['has_issues'])
                <ul class="psq__list">
                    @if ($psq['blank_pn_accounts'] > 0)
                        <li>
                            <span class="psq__num">{{ $psqCount($psq['blank_pn_accounts']) }}</span> dari {{ $psqCount($psq['realized_accounts']) }} rekening realisasi {{ $psqMonth }}
                            (<span class="psq__num">Rp {{ $psqJuta($psq['blank_pn_plafon']) }} Jt</span>) belum memiliki PN pengelola/pemrakarsa{{ $psq['blank_pn_latest_accounts'] > 0 ? ', '.$psqCount($psq['blank_pn_latest_accounts']).' di antaranya realisasi H-0/H-1' : '' }}.
                            Produktivitas rekening ini belum teratribusi ke RM/Mantri sampai sumber posisi berikutnya melengkapi PN.
                        </li>
                    @endif
                    @if ($psq['kolek_issue_accounts'] > 0)
                        <li>
                            <span class="psq__num">{{ $psqCount($psq['kolek_issue_accounts']) }}</span> rekening realisasi {{ $psqMonth }}
                            (<span class="psq__num">Rp {{ $psqJuta($psq['kolek_issue_plafon']) }} Jt</span>) tercatat non-lancar{{ $psq['kolek_age_mismatch_accounts'] > 0 ? '; '.$psqCount($psq['kolek_age_mismatch_accounts']).' di antaranya belum menunggak sehingga tidak sejalan dengan umur tunggakan' : '' }}.
                            Verifikasi kolektibilitas ke core banking sebelum dipakai sebagai dasar penilaian.
                        </li>
                    @endif
                </ul>
                @if (! empty($psq['samples']))
                    <details>
                        <summary>Lihat {{ count($psq['samples']) }} rekening terbaru yang perlu dicek</summary>
                        <div class="psq__table-wrap">
                            <table class="psq__table">
                                <thead>
                                    <tr>
                                        <th scope="col">Rekening</th>
                                        <th scope="col">Debitur</th>
                                        <th scope="col">Unit</th>
                                        <th scope="col">Produk</th>
                                        <th scope="col">Tgl Realisasi</th>
                                        <th scope="col" class="text-right">Plafon (Jt)</th>
                                        <th scope="col">Kolek</th>
                                        <th scope="col" class="text-right">Umur</th>
                                        <th scope="col">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($psq['samples'] as $sample)
                                        <tr>
                                            <td>{{ $sample['account'] ?? '-' }}</td>
                                            <td>{{ $sample['debtor'] ?? '-' }}</td>
                                            <td>{{ $sample['unit'] ?? '-' }}</td>
                                            <td>{{ $sample['product'] ?: '-' }}</td>
                                            <td>{{ $psqDate($sample['realization_date']) }}</td>
                                            <td class="num">{{ $psqJuta($sample['plafon']) }}</td>
                                            <td>{{ $sample['kolek'] ?? '-' }}</td>
                                            <td class="num">{{ $sample['arrears_age'] ?? '-' }}</td>
                                            <td>{{ implode(' · ', $sample['issues']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            @endif
        </div>
    </div>
@endif
