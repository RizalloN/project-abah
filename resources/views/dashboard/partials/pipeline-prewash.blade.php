@php
    $prewash = (array) ($pipelinePrewash ?? []);
    $prewashAvailable = (bool) data_get($prewash, 'available', false);
    $prewashSummary = (array) data_get($prewash, 'summary', []);
    $prewashMissingCif = (int) data_get($prewash, 'quality.missing_cif', 0);
    $prewashUnmappedMbm = (int) data_get($prewash, 'quality.unmapped_mbm', 0);
    $prewashBranchPipeline = (int) data_get($prewash, 'quality.branch_pipeline', 0);
    $prewashUnverifiedRealization = (int) data_get($prewash, 'quality.unverified_realization', 0);
    $prewashYear = data_get($prewash, 'year');
    $prewashPrefix = $prewashPrefix ?? 'pipeline-prewash';
    $prewashTitle = $prewashTitle ?? 'Pipeline Prewash';
    $prewashLabel = $prewashLabel ?? 'Pipeline pre-washed SLIK Hijau';
    $prewashVariant = $prewashVariant ?? null;
    $prewashContext = array_replace((array) ($microReportContext ?? []), (array) data_get($prewash, 'view_context', []));
    $prewashBranchKey = (string) data_get($prewashContext, 'branch_key', 'area6');
    $prewashInitialView = $prewashBranchKey !== 'area6' && $prewashBranchKey !== '' ? 'unit' : 'bo';
    $prewashReport = data_get($prewash, 'source_key') ?: match ($prewashPrefix) { 'pipeline-prewash-boh' => 'boh', 'pipeline-prewash-mbm' => 'mbm', default => 'prewash' };
    $prewashNominativeUrl = \Illuminate\Support\Facades\Route::has('dashboard.micro-report-nominatives') ? route('dashboard.micro-report-nominatives') : url('/dashboard/micro-report-nominatives');
    $prewashViews = $prewashViews ?? [
        'bo' => ['label' => 'BO', 'description' => 'Branch Office', 'identity_columns' => 3],
        'mbm' => ['label' => 'MBM', 'description' => 'Manajer Bisnis Mikro', 'identity_columns' => 5],
        'unit' => ['label' => 'Unit', 'description' => 'Unit Kerja', 'identity_columns' => 3],
    ];
    $prewashNumber = static fn ($value): string => is_numeric($value) ? number_format((float) $value, 0, ',', '.') : '—';
    $prewashAmount = static fn ($value): string => is_numeric($value) ? number_format((float) $value / 1_000_000, 2, ',', '.') : '—';
    $prewashShare = static fn ($value): string => is_numeric($value) ? number_format((float) $value, 2, ',', '.').'%' : '—';
    $prewashIdentity = static fn ($value): string => trim((string) $value) !== '' ? (string) $value : '—';
    $prewashDate = static function ($value): string {
        if (! $value) {
            return '—';
        }

        try {
            return \Carbon\Carbon::parse($value)->locale('id')->translatedFormat('d F Y');
        } catch (\Throwable) {
            return '—';
        }
    };
    $prewashLoanSource = match (strtolower((string) data_get($prewash, 'loan_source', ''))) {
        'daily_loan_dinamis', 'daily loan dinamis', 'daily' => 'Daily Loan Dinamis',
        'lw321pn' => 'LW321PN',
        default => null,
    };
@endphp

@once
    <style>
        .pipeline-prewash{--prewash-blue:#0754bd;--prewash-ink:#173f66;--prewash-line:#d9e5f0;min-width:0;max-width:100%;margin-top:1.25rem;border:1px solid var(--prewash-line);border-radius:14px;background:#fff;color:var(--prewash-ink);overflow:hidden}
        .pipeline-prewash *{box-sizing:border-box}
        .micro-report-count{display:inline-flex;align-items:center;justify-content:center;min-height:28px;min-width:30px;padding:3px 7px;border:1px solid transparent;border-radius:5px;background:transparent;color:inherit;font:inherit;font-weight:700;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:4px;cursor:pointer;touch-action:manipulation}
        .micro-report-count:hover{background:#e5f0ff;border-color:#a7c9f7;text-decoration-style:solid}
        .micro-report-count:focus-visible{outline:3px solid #d97706;outline-offset:2px}
        [data-micro-report-row]:hover{cursor:pointer}
        @media(pointer:coarse){.micro-report-count{min-height:44px;min-width:44px}}
        .pipeline-prewash__head{display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding:1.15rem 1.25rem;border-bottom:1px solid var(--prewash-line)}
        .pipeline-prewash__head>div{min-width:0}
        .pipeline-prewash__head h3{margin:0 0 .35rem;color:var(--prewash-blue);font-size:1.05rem;font-weight:800;line-height:1.35}
        .pipeline-prewash__head p{margin:0;color:#526c84;font-size:.75rem;line-height:1.6;max-width:75ch}
        .pipeline-prewash__dates{display:flex;flex-direction:column;gap:.3rem;align-items:flex-start;font-size:.7rem;line-height:1.5;color:#526c84}
        .pipeline-prewash__dates strong{color:var(--prewash-ink);font-weight:700}
        .pipeline-prewash__note{margin:0;padding:.8rem 1.25rem;background:#f4f8fd;color:#526c84;font-size:.71rem;line-height:1.65}
        .pipeline-prewash__message{margin:.9rem 1.25rem;padding:.75rem .9rem;border-left:3px solid var(--prewash-blue);background:#f4f8fd;font-size:.75rem;line-height:1.6;overflow-wrap:anywhere}
        .pipeline-prewash__quality ul{margin:.4rem 0;padding-left:1.1rem}
        .pipeline-prewash__quality p{margin:.5rem 0 0}
        .pipeline-prewash__view{border-top:1px solid var(--prewash-line)}
        .pipeline-prewash__view:first-of-type{border-top:0}
        .pipeline-prewash__view>summary{display:flex;align-items:center;gap:.65rem;min-height:52px;padding:.75rem 1.25rem;cursor:pointer;list-style:none;touch-action:manipulation;background:#fff}
        .pipeline-prewash__view>summary::-webkit-details-marker{display:none}
        .pipeline-prewash__view>summary::before{content:'+';display:grid;place-items:center;flex:0 0 24px;height:24px;border:1px solid #b9cee3;border-radius:5px;color:var(--prewash-blue);font-size:1rem;font-weight:700;line-height:1}
        .pipeline-prewash__view[open]>summary::before{content:'−'}
        .pipeline-prewash__view>summary:hover{background:#f4f8fd}
        .pipeline-prewash__view[open]>summary{background:#edf4fd;border-bottom:1px solid var(--prewash-line)}
        .pipeline-prewash__view-label{color:var(--prewash-blue);font-size:.8rem;font-weight:800}
        .pipeline-prewash__view-description{color:#526c84;font-size:.71rem}
        .pipeline-prewash__view-count{margin-left:auto;color:#526c84;font-size:.7rem;white-space:nowrap;font-variant-numeric:tabular-nums}
        .pipeline-prewash__scroll{isolation:isolate;max-width:100%;max-height:34rem;overflow:auto;overscroll-behavior-x:contain;scroll-padding:1rem}
        .pipeline-prewash__view>summary:focus-visible,.pipeline-prewash__scroll:focus-visible{outline:3px solid #d97706;outline-offset:-3px}
        .pipeline-prewash__table{width:100%;min-width:1040px;margin:0;border-collapse:separate;border-spacing:0;font-size:.71rem;line-height:1.45;font-variant-numeric:tabular-nums}
        .pipeline-prewash__table--mbm{min-width:1240px}
        .pipeline-prewash--mbm{--prewash-blue:#0070c0}
        .pipeline-prewash--mbm .pipeline-prewash__table--unit{min-width:1380px}
        .pipeline-prewash--mbm .pipeline-prewash__table thead .pipeline-prewash__visited{background:#666}
        .pipeline-prewash__table caption{padding:.65rem 1rem;text-align:left;color:#526c84;font-size:.69rem;caption-side:top;background:#fff}
        .pipeline-prewash__table th,.pipeline-prewash__table td{padding:.6rem .7rem;vertical-align:middle;border-right:1px solid #e4ecf4;border-bottom:1px solid #e4ecf4;white-space:nowrap;text-align:right}
        .pipeline-prewash__table thead th{background:var(--prewash-blue);color:#fff;text-align:center;font-weight:700;border-color:#4480cd}
        .pipeline-prewash__table thead .pipeline-prewash__visited{background:#006b63}
        .pipeline-prewash__table .pipeline-prewash__identity{text-align:left;white-space:normal;overflow-wrap:anywhere;min-width:150px;max-width:230px}
        .pipeline-prewash__table .pipeline-prewash__code{text-align:left;min-width:65px}
        .pipeline-prewash__table .pipeline-prewash__index{width:42px;text-align:center}
        .pipeline-prewash__table tbody tr{--prewash-row:#fff}
        .pipeline-prewash__table tbody tr:nth-child(even){--prewash-row:#f5f8fc}
        .pipeline-prewash__table tbody th,.pipeline-prewash__table tbody td{background:var(--prewash-row)}
        .pipeline-prewash__table tbody th{font-weight:700}
        .pipeline-prewash__table .pipeline-prewash__sticky{position:sticky;left:0;z-index:1;box-shadow:3px 0 0 rgba(7,84,189,.07)}
        .pipeline-prewash__table thead .pipeline-prewash__sticky{z-index:2}
        .pipeline-prewash__table tbody .pipeline-prewash__visited{color:#006b63;font-weight:700;background:#edf7f5}
        .pipeline-prewash__table tbody .pipeline-prewash__realized{font-weight:700;color:var(--prewash-blue)}
        .pipeline-prewash__table tfoot th,.pipeline-prewash__table tfoot td{background:#e8f1fd;color:#153e70;font-weight:800;border-top:2px solid var(--prewash-blue);border-bottom:0}
        .pipeline-prewash__table tfoot th{text-align:left}
        .pipeline-prewash__empty{padding:1.35rem 1.25rem;font-size:.78rem;line-height:1.7;color:#526c84}
        .pipeline-prewash__empty strong{display:block;margin-bottom:.2rem;font-size:.85rem;color:var(--prewash-ink)}
        .pipeline-prewash__empty p{margin:0;max-width:75ch;overflow-wrap:anywhere}
        .pipeline-prewash__import{display:inline-flex;align-items:center;justify-content:center;min-height:44px;margin-top:1rem;padding:.6rem .9rem;border:1px solid #0754bd;border-radius:7px;background:#0754bd;color:#fff;font-size:.75rem;font-weight:700;text-decoration:none;touch-action:manipulation}
        .pipeline-prewash__import:hover{background:#06479f;color:#fff;text-decoration:none}
        .pipeline-prewash__import:focus-visible{outline:3px solid #d97706;outline-offset:3px}
        @media(max-width:640px){.pipeline-prewash__head{padding:1rem;gap:.75rem}.pipeline-prewash__note{padding:.75rem 1rem}.pipeline-prewash__view>summary{padding:.75rem 1rem;flex-wrap:wrap;column-gap:.5rem}.pipeline-prewash__view-description{flex-basis:calc(100% - 34px);order:3;margin-left:34px}.pipeline-prewash__table{font-size:.69rem}.pipeline-prewash__table .pipeline-prewash__identity{min-width:130px;max-width:170px}.pipeline-prewash__message{margin:.75rem 1rem}.pipeline-prewash__empty{padding:1rem}}
        @media(prefers-reduced-motion:reduce){.pipeline-prewash,.pipeline-prewash *{scroll-behavior:auto}}
    </style>
@endonce

<section class="pipeline-prewash{{ $prewashVariant === 'mbm' ? ' pipeline-prewash--mbm' : '' }}" aria-labelledby="{{ $prewashPrefix }}-title">
    <header class="pipeline-prewash__head">
        <div>
            <h3 id="{{ $prewashPrefix }}-title">{{ $prewashTitle }}</h3>
            <p>{{ $prewashLabel }}{{ $prewashYear ? ' tahun '.$prewashYear : '' }}: kunjungan dan progres realisasi.</p>
        </div>
        @if($prewashAvailable)
            <div class="pipeline-prewash__dates" aria-label="Periode laporan Pipeline Prewash">
                <span>Laporan kunjungan <strong>{{ $prewashDate(data_get($prewash, 'visit_period')) }}</strong></span>
                <span>Realisasi s.d. <strong>{{ $prewashDate(data_get($prewash, 'loan_period')) }}</strong>@if($prewashLoanSource) ({{ $prewashLoanSource }})@endif</span>
            </div>
        @endif
    </header>

    @if(! $prewashAvailable)
        <div class="pipeline-prewash__empty" role="status">
            <strong>{{ data_get($prewash, 'empty_title') ?: ('Data Pipeline Prewash'.($prewashVariant === 'mbm' ? ' PIC MBM' : '').' belum tersedia') }}</strong>
            <p>{{ data_get($prewash, 'message') ?: ($prewashVariant === 'mbm' ? 'Impor laporan Monitoring TL Pipeline Prewash PIC MBM untuk menampilkan evaluasi kunjungan dan realisasi pada cabang yang dipilih.' : 'Impor laporan Pipeline Prewash untuk menampilkan evaluasi kunjungan dan realisasi pada cabang yang dipilih.') }}</p>
            @if(data_get($prewash, 'empty_reason') === 'not_imported')
                <a class="pipeline-prewash__import" href="{{ route('import.index') }}" aria-label="Buka menu Import untuk {{ data_get($prewash, 'import_report', 'Pipeline Prewash') }}">Buka menu Import</a>
            @endif
        </div>
    @else
        @if(data_get($prewash, 'message'))
            <p class="pipeline-prewash__message" role="status">{{ data_get($prewash, 'message') }}</p>
        @endif
        @if($prewashMissingCif > 0 || $prewashUnmappedMbm > 0 || $prewashUnverifiedRealization > 0)
            <div class="pipeline-prewash__message pipeline-prewash__quality" role="note" aria-labelledby="{{ $prewashPrefix }}-quality-title">
                <strong id="{{ $prewashPrefix }}-quality-title">Catatan kelengkapan data</strong>
                <ul>
                    @if($prewashMissingCif > 0)
                        <li>{{ $prewashNumber($prewashMissingCif) }} pipeline tanpa CIF tetap masuk total pipeline, tetapi belum masuk jumlah dan subtotal realisasi terverifikasi.</li>
                    @endif
                    @if($prewashUnverifiedRealization > 0)
                        <li>{{ $prewashNumber($prewashUnverifiedRealization) }} pipeline memiliki data realisasi yang perlu diverifikasi dan belum masuk jumlah maupun subtotal realisasi terverifikasi.</li>
                    @endif
                    @if($prewashBranchPipeline > 0)
                        <li>{{ $prewashNumber($prewashBranchPipeline) }} pipeline berada pada level BO dan belum memiliki PIC MBM. Baris ini tetap masuk total pipeline, terpisah dari MBM yang sudah terpetakan.</li>
                    @endif
                    @if($prewashUnmappedMbm > $prewashBranchPipeline)
                        <li>{{ $prewashNumber($prewashUnmappedMbm - $prewashBranchPipeline) }} pipeline belum terhubung ke MBM dan tetap ditampilkan pada kelompok “Belum dipetakan”.</li>
                    @endif
                </ul>
                @if($prewashMissingCif > 0 || $prewashUnverifiedRealization > 0)
                    <p>Jumlah dan nominal realisasi yang tampil hanya mencakup data yang sudah terverifikasi. Data yang belum tersedia atau berbeda antarcatatan tidak dianggap bernilai nol.</p>
                @endif
            </div>
        @endif
        <p class="pipeline-prewash__note" id="{{ $prewashPrefix }}-note">Nominal dalam Rp juta. Persentase kunjungan memakai total pipeline; persentase realisasi memakai pipeline yang sudah dikunjungi. Tanda — berarti data belum tersedia. Geser tabel untuk melihat seluruh kolom. Klik jumlah debitur atau klik dua kali baris untuk melihat nominatif.</p>

        @foreach($prewashViews as $prewashView => $prewashViewMeta)
            @php
                $prewashRows = array_values((array) data_get($prewash, 'groups.'.$prewashView, []));
                $prewashIdentityColumns = $prewashViewMeta['identity_columns'];
                $prewashUnitMbm = $prewashView === 'unit' && ! empty($prewashViewMeta['show_mbm']);
                $prewashUnitCode = $prewashView === 'unit' && ! empty($prewashViewMeta['show_unit_code']);
            @endphp
            <details class="pipeline-prewash__view" id="{{ $prewashPrefix }}-{{ $prewashView }}" @if($prewashView === $prewashInitialView || (! array_key_exists($prewashInitialView, $prewashViews) && $loop->first)) open @endif>
                <summary>
                    <span class="pipeline-prewash__view-label">{{ $prewashViewMeta['label'] }}</span>
                    <span class="pipeline-prewash__view-description">{{ $prewashViewMeta['description'] }}</span>
                    <span class="pipeline-prewash__view-count">{{ $prewashNumber(count($prewashRows)) }} kelompok</span>
                </summary>
                @if($prewashRows === [])
                    <div class="pipeline-prewash__empty" role="status"><p>Belum ada pipeline {{ $prewashViewMeta['label'] }} pada cabang yang dipilih.</p></div>
                @else
                    <div class="pipeline-prewash__scroll" tabindex="0" role="region" aria-label="Tabel Pipeline Prewash {{ $prewashViewMeta['label'] }}" aria-describedby="{{ $prewashPrefix }}-note">
                        <table class="pipeline-prewash__table pipeline-prewash__table--{{ $prewashView }}">
                            <caption>Evaluasi Pipeline Prewash {{ $prewashViewMeta['description'] }}{{ $prewashYear ? ' tahun '.$prewashYear : '' }}</caption>
                            <thead>
                                <tr>
                                    <th rowspan="3" scope="col" class="pipeline-prewash__index">No.</th>
                                    @if($prewashView === 'bo' || $prewashView === 'mbm' || $prewashUnitMbm)
                                        <th rowspan="3" scope="col" class="pipeline-prewash__code">Kode BO</th>
                                    @endif
                                    <th rowspan="3" scope="col" class="pipeline-prewash__identity {{ $prewashView !== 'mbm' && $prewashView !== 'unit' ? 'pipeline-prewash__sticky' : '' }}">Branch Office</th>
                                    @if($prewashView === 'mbm')
                                        <th rowspan="3" scope="col" class="pipeline-prewash__code">PN MBM</th>
                                        <th rowspan="3" scope="col" class="pipeline-prewash__identity pipeline-prewash__sticky">MBM</th>
                                    @elseif($prewashView === 'unit')
                                        @if($prewashUnitMbm)
                                            <th rowspan="3" scope="col" class="pipeline-prewash__identity">MBM</th>
                                        @endif
                                        @if($prewashUnitCode)
                                            <th rowspan="3" scope="col" class="pipeline-prewash__code">Kode Unit</th>
                                        @endif
                                        <th rowspan="3" scope="col" class="pipeline-prewash__identity pipeline-prewash__sticky">Unit Kerja</th>
                                    @endif
                                    <th colspan="6" scope="colgroup">{{ $prewashLabel }}</th>
                                    <th colspan="3" rowspan="2" scope="colgroup">Progres realisasi
                                        @if($prewashVariant === 'mbm')<br>pipeline<br>s.d. {{ $prewashDate(data_get($prewash, 'loan_period')) }}@endif</th>
                                </tr>
                                <tr>
                                    <th colspan="2" scope="colgroup">Total pipeline</th>
                                    <th colspan="2" scope="colgroup" class="pipeline-prewash__visited">Sudah dikunjungi</th>
                                    <th colspan="2" scope="colgroup">Belum dikunjungi</th>
                                </tr>
                                <tr>
                                    <th scope="col">Deb</th><th scope="col">Rp juta</th>
                                    <th scope="col" class="pipeline-prewash__visited">Deb</th><th scope="col" class="pipeline-prewash__visited">%</th>
                                    <th scope="col">Deb</th><th scope="col">%</th>
                                    <th scope="col">Deb</th><th scope="col">%</th><th scope="col">Rp juta</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($prewashRows as $prewashRow)
                                    <tr data-micro-report-row data-report="{{ $prewashReport }}" data-level="{{ $prewashView }}"
                                        data-nominative-url="{{ $prewashNominativeUrl }}" data-branch="{{ data_get($prewashRow, 'branch_code', data_get($prewashRow, 'branch_name', '')) }}"
                                        data-unit="{{ data_get($prewashRow, 'unit_code', '') }}" data-mbm="{{ data_get($prewashRow, 'mbm_pn') ?: data_get($prewashRow, 'mbm_name', '') }}"
                                        data-periode="{{ data_get($prewashContext, 'periode', data_get($prewashContext, 'period', '')) }}" data-cabang="{{ $prewashBranchKey }}"
                                        data-group-label="{{ data_get($prewashRow, $prewashView === 'unit' ? 'unit_name' : ($prewashView === 'mbm' ? 'mbm_name' : 'branch_name'), '') }}">
                                        <td class="pipeline-prewash__index">{{ $loop->iteration }}</td>
                                        @if($prewashView === 'bo' || $prewashView === 'mbm' || $prewashUnitMbm)
                                            <td class="pipeline-prewash__code">{{ $prewashIdentity(data_get($prewashRow, 'branch_code')) }}</td>
                                        @endif
                                        @if($prewashView === 'bo')
                                            <th scope="row" class="pipeline-prewash__identity pipeline-prewash__sticky">{{ $prewashIdentity(data_get($prewashRow, 'branch_name')) }}</th>
                                        @else
                                            <td class="pipeline-prewash__identity">{{ $prewashIdentity(data_get($prewashRow, 'branch_name')) }}</td>
                                        @endif
                                        @if($prewashView === 'mbm')
                                            <td class="pipeline-prewash__code">{{ $prewashIdentity(data_get($prewashRow, 'mbm_pn')) }}</td>
                                            <th scope="row" class="pipeline-prewash__identity pipeline-prewash__sticky">{{ $prewashIdentity(data_get($prewashRow, 'mbm_name')) }}</th>
                                        @elseif($prewashView === 'unit')
                                            @if($prewashUnitMbm)
                                                <td class="pipeline-prewash__identity">{{ $prewashIdentity(data_get($prewashRow, 'mbm_name')) }}</td>
                                            @endif
                                            @if($prewashUnitCode)
                                                <td class="pipeline-prewash__code">{{ $prewashIdentity(data_get($prewashRow, 'unit_code')) }}</td>
                                            @endif
                                            <th scope="row" class="pipeline-prewash__identity pipeline-prewash__sticky">{{ $prewashIdentity(data_get($prewashRow, 'unit_name')) }}</th>
                                        @endif
                                        <td>@if(is_numeric(data_get($prewashRow, 'total_count')))<button type="button" class="micro-report-count" data-micro-nominative-open data-metric="all" aria-label="Lihat nominatif total pipeline">{{ $prewashNumber(data_get($prewashRow, 'total_count')) }}</button>@else{{ $prewashNumber(data_get($prewashRow, 'total_count')) }}@endif</td>
                                        <td>{{ $prewashAmount(data_get($prewashRow, 'total_amount')) }}</td>
                                        <td class="pipeline-prewash__visited">@if(is_numeric(data_get($prewashRow, 'visited_count')))<button type="button" class="micro-report-count" data-micro-nominative-open data-metric="visited" aria-label="Lihat nominatif sudah dikunjungi">{{ $prewashNumber(data_get($prewashRow, 'visited_count')) }}</button>@else{{ $prewashNumber(data_get($prewashRow, 'visited_count')) }}@endif</td>
                                        <td class="pipeline-prewash__visited">{{ $prewashShare(data_get($prewashRow, 'visited_share')) }}</td>
                                        <td>@if(is_numeric(data_get($prewashRow, 'unvisited_count')))<button type="button" class="micro-report-count" data-micro-nominative-open data-metric="unvisited" aria-label="Lihat nominatif belum dikunjungi">{{ $prewashNumber(data_get($prewashRow, 'unvisited_count')) }}</button>@else{{ $prewashNumber(data_get($prewashRow, 'unvisited_count')) }}@endif</td>
                                        <td>{{ $prewashShare(data_get($prewashRow, 'unvisited_share')) }}</td>
                                        <td class="pipeline-prewash__realized">@if(is_numeric(data_get($prewashRow, 'realized_count')))<button type="button" class="micro-report-count" data-micro-nominative-open data-metric="realized" aria-label="Lihat nominatif sudah realisasi">{{ $prewashNumber(data_get($prewashRow, 'realized_count')) }}</button>@else{{ $prewashNumber(data_get($prewashRow, 'realized_count')) }}@endif</td>
                                        <td class="pipeline-prewash__realized">{{ $prewashShare(data_get($prewashRow, 'realized_share')) }}</td>
                                        <td class="pipeline-prewash__realized">{{ $prewashAmount(data_get($prewashRow, 'realized_amount')) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            @if($prewashSummary !== [])
                                <tfoot>
                                    <tr>
                                        <th colspan="{{ $prewashIdentityColumns }}" scope="row">Total cabang yang dipilih</th>
                                        <td>{{ $prewashNumber(data_get($prewashSummary, 'total_count')) }}</td>
                                        <td>{{ $prewashAmount(data_get($prewashSummary, 'total_amount')) }}</td>
                                        <td>{{ $prewashNumber(data_get($prewashSummary, 'visited_count')) }}</td>
                                        <td>{{ $prewashShare(data_get($prewashSummary, 'visited_share')) }}</td>
                                        <td>{{ $prewashNumber(data_get($prewashSummary, 'unvisited_count')) }}</td>
                                        <td>{{ $prewashShare(data_get($prewashSummary, 'unvisited_share')) }}</td>
                                        <td>{{ $prewashNumber(data_get($prewashSummary, 'realized_count')) }}</td>
                                        <td>{{ $prewashShare(data_get($prewashSummary, 'realized_share')) }}</td>
                                        <td>{{ $prewashAmount(data_get($prewashSummary, 'realized_amount')) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                @endif
            </details>
        @endforeach
    @endif
</section>
