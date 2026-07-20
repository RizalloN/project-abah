@extends('layouts.admin')

@section('title', 'Hourly DPK')

@php
    $formatJuta = static function ($value): string {
        $number = (float) $value / 1000000;
        if (abs($number) >= 1000) {
            return number_format($number, 0, ',', '.');
        }

        return number_format($number, 1, ',', '.');
    };

    $periodKeys = ['yoy', 'ytd', 'mtm', 'mtd', 'h2', 'h1'];
    $deltaLabels = ['ytd' => 'YTD', 'yoy' => 'YoY', 'mtm' => 'MtM', 'mtd' => 'MtD', 'dtd' => 'DtD'];
    $deltaClass = static function ($value): string {
        $number = (float) $value;

        if ($number > 0) {
            return 'hourly-delta-positive';
        }

        if ($number < 0) {
            return 'hourly-delta-negative';
        }

        return 'hourly-delta-flat';
    };
    $formatDeltaJuta = static function ($value) use ($formatJuta): string {
        $number = (float) $value;
        if (abs($number) < 0.5) {
            return '0,0';
        }

        return ($number > 0 ? '+' : '') . $formatJuta($number);
    };
@endphp

@section('content')
<style>
    :root {
        --hourly-blue: #00529c;
        --hourly-blue-deep: #003b75;
        --hourly-blue-soft: #eaf2ff;
        --hourly-cyan: #31b7e9;
        --hourly-border: #dbe8f6;
        --hourly-muted: #64748b;
        --hourly-text: #0f172a;
    }

    .hourly-dpk-page {
        min-height: 100vh;
        background: linear-gradient(180deg, #eef7ff 0%, #f8fbff 48%, #ffffff 100%);
        padding-bottom: 2.5rem;
        font-family: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif;
    }

    .hourly-dpk-hero {
        background: linear-gradient(135deg, var(--hourly-blue-deep), #086ed1);
        color: #fff;
        padding: 1.35rem 1.75rem;
        border-radius: 0 0 22px 22px;
        box-shadow: 0 16px 30px -24px rgba(0, 58, 117, 0.4);
    }

    .hourly-dpk-hero h1 {
        margin: 0;
        font-size: 1.9rem;
        font-weight: 900;
        letter-spacing: 0;
    }

    .hourly-dpk-hero p {
        margin: 0.35rem 0 0;
        color: rgba(255, 255, 255, 0.82);
        font-weight: 650;
    }

    .hourly-dpk-container {
        max-width: 1660px;
        margin: 0 auto;
        padding: 1.35rem 1.45rem 0;
    }

    .hourly-filter-card,
    .hourly-table-card {
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid var(--hourly-border);
        border-radius: 18px;
        box-shadow: 0 18px 34px -28px rgba(15, 23, 42, 0.38);
    }

    .hourly-filter-card {
        position: relative;
        z-index: 1000;
        overflow: visible;
        padding: 1rem;
        margin-bottom: 1.15rem;
    }

    .hourly-filter-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr) auto;
        gap: 0.9rem;
        align-items: end;
    }

    @media (max-width: 1200px) {
        .hourly-filter-grid {
            grid-template-columns: repeat(3, 1fr);
        }
        .hourly-filter-actions {
            grid-column: span 3;
            justify-content: flex-end;
            margin-top: 0.5rem;
        }
    }

    .hourly-filter-field {
        position: relative;
        min-width: 0;
    }

    .hourly-filter-label {
        display: block;
        margin-bottom: 0.42rem;
        color: #516b91;
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .hourly-native-select {
        display: none;
    }

    .hourly-select {
        position: relative;
    }

    .hourly-select.is-open {
        z-index: 1010;
    }

    .hourly-select-toggle {
        width: 100%;
        min-height: 46px;
        border: 1px solid #cddcf0;
        border-radius: 14px;
        background: linear-gradient(180deg, #ffffff 0%, #f4f9ff 100%);
        color: var(--hourly-text);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0 1rem;
        font-weight: 800;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9), 0 10px 20px -18px rgba(0, 82, 156, 0.45);
    }

    .hourly-select-toggle i {
        color: var(--hourly-blue);
    }

    .hourly-select-menu {
        position: absolute;
        top: calc(100% + 0.5rem);
        left: 0;
        right: 0;
        z-index: 1020;
        display: none;
        max-height: 260px;
        overflow-y: auto;
        padding: 0.45rem;
        border: 1px solid var(--hourly-border);
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 22px 34px -24px rgba(15, 23, 42, 0.45);
    }

    .hourly-select.is-open .hourly-select-menu {
        display: block;
    }

    .hourly-select-option {
        width: 100%;
        border: 0;
        background: transparent;
        padding: 0.68rem 0.75rem;
        border-radius: 11px;
        text-align: left;
        font-weight: 750;
        color: #334155;
    }

    .hourly-select-option:hover,
    .hourly-select-option.is-active {
        background: var(--hourly-blue-soft);
        color: var(--hourly-blue);
    }

    .hourly-submit {
        min-height: 46px;
        border: 0;
        border-radius: 14px;
        padding: 0 1.25rem;
        background: linear-gradient(135deg, var(--hourly-blue), #0b72d9);
        color: #fff;
        font-weight: 900;
        box-shadow: 0 16px 24px -18px rgba(0, 82, 156, 0.8);
    }

    .hourly-filter-actions {
        display: flex;
        gap: 0.65rem;
        align-items: center;
        align-self: end;
        white-space: nowrap;
    }

    .hourly-export-pdf {
        min-height: 46px;
        border: 1px solid #cddcf0;
        border-radius: 14px;
        padding: 0 1rem;
        background: #ffffff;
        color: var(--hourly-blue);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        white-space: nowrap;
        cursor: pointer;
        box-shadow: 0 14px 22px -20px rgba(0, 82, 156, 0.55);
    }

    .hourly-export-pdf:hover {
        color: #ffffff;
        background: var(--hourly-blue);
        text-decoration: none;
    }

    .hourly-meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        margin-top: 0.95rem;
    }

    .hourly-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.52rem 0.78rem;
        border: 1px solid #cfe1f6;
        border-radius: 999px;
        background: #f8fbff;
        color: #37516f;
        font-size: 0.78rem;
        font-weight: 850;
    }

    .hourly-table-card {
        position: relative;
        z-index: 1;
        overflow: hidden;
    }

    .hourly-table-title {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: center;
        padding: 1rem 1.15rem;
        border-bottom: 1px solid var(--hourly-border);
    }

    .hourly-table-title h2 {
        margin: 0;
        color: var(--hourly-text);
        font-size: 1.15rem;
        font-weight: 900;
    }

    .hourly-table-shell {
        position: relative;
        z-index: 1;
        max-height: calc(100vh - 295px);
        min-height: 430px;
        overflow: auto;
        scrollbar-width: thin;
        scrollbar-color: #9fb2ca #edf4fb;
    }

    .hourly-table {
        width: max-content;
        min-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: #fff;
    }

    .hourly-table th,
    .hourly-table td {
        border-right: 1px solid #dce7f3;
        border-bottom: 1px solid #dce7f3;
        padding: 0.72rem 0.7rem;
        white-space: nowrap;
        vertical-align: middle;
    }

    .hourly-table th:not(.hourly-sticky),
    .hourly-table td:not(.hourly-sticky) {
        min-width: 112px;
    }

    .hourly-table thead th {
        position: sticky;
        z-index: 3;
        top: 0;
        background: #073b78;
        color: #fff;
        text-align: center;
        font-size: 0.72rem;
        font-weight: 900;
        text-transform: uppercase;
    }

    .hourly-table thead tr:nth-child(2) th {
        top: 42px;
        background: #0b519d;
    }

    .hourly-table tbody td {
        color: #233044;
        font-size: 0.82rem;
        font-weight: 650;
        text-align: right;
        background: #fff;
    }

    .hourly-table tbody tr:nth-child(even) td {
        background: #f8fbff;
    }

    .hourly-table tbody tr:hover td {
        background: #eaf4ff !important;
    }

    .hourly-sticky {
        position: sticky;
        z-index: 4;
        left: 0;
        background: #ffffff !important;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .hourly-sticky-no {
        min-width: 58px;
        width: 58px;
        max-width: 58px;
    }

    .hourly-sticky-branch {
        left: 58px;
        min-width: 160px;
        width: 160px;
        max-width: 160px;
        text-align: left !important;
    }

    .hourly-sticky-unit {
        left: 218px;
        min-width: 300px;
        width: 300px;
        max-width: 300px;
        text-align: left !important;
        border-right: 2px solid #b8cce4 !important;
        box-shadow: 12px 0 18px -18px rgba(15, 23, 42, 0.75);
    }

    .hourly-table tbody tr:nth-child(even) td.hourly-sticky {
        background: #f8fbff !important;
    }

    .hourly-table tbody tr:hover td.hourly-sticky {
        background: #eaf4ff !important;
    }

    .hourly-table thead .hourly-sticky {
        z-index: 5;
        background: #073b78 !important;
    }

    .hourly-total td {
        background: #ffeb3b !important;
        color: #10213a !important;
        font-weight: 950 !important;
    }

    .hourly-delta-positive {
        background: #dcfce7 !important;
        color: #078246 !important;
        font-weight: 900 !important;
    }

    .hourly-delta-negative {
        background: #fee2e2 !important;
        color: #dc2626 !important;
        font-weight: 900 !important;
    }

    .hourly-delta-flat {
        background: #fef3c7 !important;
        color: #a16207 !important;
        font-weight: 900 !important;
    }

    .hourly-empty {
        padding: 2rem;
        color: var(--hourly-muted);
        font-weight: 800;
        text-align: center;
    }

    @media (max-width: 900px) {
        .hourly-filter-grid {
            grid-template-columns: minmax(0, 1fr);
        }
        .hourly-filter-actions {
            grid-column: span 1;
            justify-content: stretch;
            width: 100%;
            margin-top: 0.5rem;
        }
        .hourly-filter-actions .hourly-submit,
        .hourly-filter-actions .hourly-export-pdf {
            flex: 1;
            text-align: center;
            justify-content: center;
        }

        .hourly-table-shell {
            max-height: calc(100vh - 360px);
        }
    }

    @media (max-width: 575.98px) {
        .hourly-dpk-container {
            padding-right: 0.5rem;
            padding-left: 0.5rem;
        }

        .hourly-filter-grid,
        .hourly-filter-field,
        .hourly-select,
        .hourly-select-toggle,
        .hourly-filter-actions {
            width: 100%;
            min-width: 0;
            max-width: 100%;
        }

        .hourly-filter-actions {
            flex-direction: column;
            white-space: normal;
        }

        .hourly-filter-actions .hourly-submit,
        .hourly-filter-actions .hourly-export-pdf {
            width: 100%;
            flex: 0 0 auto;
        }
    }
</style>

<div class="hourly-dpk-page">
    <div class="hourly-dpk-hero">
        <h1>Hourly DPK</h1>
        <p>Monitoring posisi simpanan harian berbasis Hourly DPK dan pembanding historis SSA Simpanan.</p>
    </div>

    <div class="hourly-dpk-container">
        <form method="GET" action="{{ route('report.dashboard-dana.hourly-dpk') }}" class="hourly-filter-card">
            <div class="hourly-filter-grid">
                <div class="hourly-filter-field">
                    <label class="hourly-filter-label" for="cabang">Cabang</label>
                    <select id="cabang" name="cabang" class="hourly-native-select" data-hourly-native-select>
                        <option value="all" {{ ($selectedBranch ?? 'all') === 'all' ? 'selected' : '' }}>Area 6</option>
                        @foreach (($filters['branches'] ?? []) as $branch)
                            <option value="{{ $branch }}" {{ ($selectedBranch ?? 'all') === $branch ? 'selected' : '' }}>{{ $branch }}</option>
                        @endforeach
                    </select>
                    <div class="hourly-select" data-hourly-select="cabang"></div>
                </div>

                <div class="hourly-filter-field">
                    <label class="hourly-filter-label" for="jenis">Jenis Simpanan</label>
                    <select id="jenis" name="jenis" class="hourly-native-select" data-hourly-native-select>
                        @foreach (($filters['products'] ?? []) as $value => $label)
                            <option value="{{ $value }}" {{ ($selectedProduct ?? 'all') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="hourly-select" data-hourly-select="jenis"></div>
                </div>

                <div class="hourly-filter-field">
                    <label class="hourly-filter-label" for="segmen">Segmen</label>
                    <select id="segmen" name="segmen" class="hourly-native-select" data-hourly-native-select>
                        @foreach (($filters['segments'] ?? ['all' => 'Semua Segmen']) as $value => $label)
                            <option value="{{ $value }}" {{ ($selectedSegment ?? 'all') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="hourly-select" data-hourly-select="segmen"></div>
                </div>

                <div class="hourly-filter-actions">
                    <button type="submit" class="hourly-submit">
                        <i class="fas fa-filter mr-2"></i>Tampilkan
                    </button>
                    <a
                        href="{{ route('report.dashboard-dana.hourly-dpk.export-pdf', ['cabang' => $selectedBranch ?? 'all', 'segmen' => $selectedSegment ?? 'all']) }}"
                        target="_blank"
                        rel="noopener"
                        data-hourly-export-pdf
                        data-export-url="{{ route('report.dashboard-dana.hourly-dpk.export-pdf') }}"
                        class="hourly-export-pdf"
                    >
                        <i class="fas fa-file-pdf mr-2"></i>Export PDF
                    </a>
                </div>
            </div>

            <div class="hourly-meta-row">
                <span class="hourly-pill"><i class="fas fa-calendar-day"></i>Hari ini: {{ $payload['selectedDateLabel'] ?? '-' }}</span>
                <span class="hourly-pill"><i class="fas fa-map-marker-alt"></i>Scope: {{ $payload['scopeLabel'] ?? 'Area 6' }}</span>
                <span class="hourly-pill"><i class="fas fa-layer-group"></i>Segmen: {{ ($filters['segments'][$selectedSegment ?? 'all'] ?? 'Semua Segmen') }}</span>
                <span class="hourly-pill"><i class="fas fa-coins"></i>Satuan: Rp Juta</span>
            </div>
        </form>

        <div class="hourly-table-card">
            <div class="hourly-table-title">
                <h2>Hourly DPK</h2>
                <span class="hourly-pill"><i class="fas fa-table"></i>{{ number_format(count($payload['rows'] ?? []), 0, ',', '.') }} baris</span>
            </div>

            @if (!($payload['ready'] ?? false))
                <div class="hourly-empty">{{ $payload['message'] ?? 'Data belum tersedia.' }}</div>
            @else
                <div class="hourly-table-shell">
                    <table class="hourly-table">
                        <thead>
                            <tr>
                                <th rowspan="2" class="hourly-sticky hourly-sticky-no">No</th>
                                <th rowspan="2" class="hourly-sticky hourly-sticky-branch">Cabang</th>
                                <th rowspan="2" class="hourly-sticky hourly-sticky-unit">Unit Kerja</th>
                                <th colspan="6">Posisi Historis SSA Simpanan</th>
                                <th colspan="{{ max(1, count($payload['hours'] ?? [])) }}">Posisi Hari Ini {{ $payload['selectedDateLabel'] ?? '-' }}</th>
                                <th colspan="{{ count($deltaLabels) }}">Delta thd {{ $payload['hours'] ? (($payload['hours'][count($payload['hours']) - 1]['label'] ?? 'Jam Terbaru')) : 'Jam Terbaru' }}</th>
                            </tr>
                            <tr>
                                @foreach ($periodKeys as $key)
                                    <th>{{ $dateFormatter(($payload['periods'][$key] ?? null)) }}</th>
                                @endforeach
                                @forelse (($payload['hours'] ?? []) as $hour)
                                    <th>{{ $hour['label'] }}</th>
                                @empty
                                    <th>-</th>
                                @endforelse
                                @foreach ($deltaLabels as $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (($payload['rows'] ?? []) as $row)
                                <tr>
                                    <td class="hourly-sticky hourly-sticky-no text-center">{{ $row['no'] }}</td>
                                    <td class="hourly-sticky hourly-sticky-branch">{{ $row['branch'] }}</td>
                                    <td class="hourly-sticky hourly-sticky-unit">{{ $row['unit'] }}</td>
                                    @foreach ($periodKeys as $key)
                                        <td>{{ $formatJuta($row['period_values'][$key] ?? 0) }}</td>
                                    @endforeach
                                    @forelse (($payload['hours'] ?? []) as $hour)
                                        <td>{{ $formatJuta($row['hour_values'][$hour['key']] ?? 0) }}</td>
                                    @empty
                                        <td>0,0</td>
                                    @endforelse
                                    @foreach ($deltaLabels as $key => $label)
                                        @php $deltaValue = $row['delta_values'][$key] ?? 0; @endphp
                                        <td class="{{ $deltaClass($deltaValue) }}">{{ $formatDeltaJuta($deltaValue) }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 9 + max(1, count($payload['hours'] ?? [])) + count($deltaLabels) }}" class="hourly-empty">Data tidak ditemukan untuk filter ini.</td>
                                </tr>
                            @endforelse

                            @if (!empty($payload['rows']))
                                <tr class="hourly-total">
                                    <td class="hourly-sticky hourly-sticky-no text-center">#</td>
                                    <td class="hourly-sticky hourly-sticky-branch">{{ $payload['scopeLabel'] ?? 'AREA 6' }}</td>
                                    <td class="hourly-sticky hourly-sticky-unit">GRAND TOTAL</td>
                                    @foreach ($periodKeys as $key)
                                        <td>{{ $formatJuta($payload['total']['period_values'][$key] ?? 0) }}</td>
                                    @endforeach
                                    @forelse (($payload['hours'] ?? []) as $hour)
                                        <td>{{ $formatJuta($payload['total']['hour_values'][$hour['key']] ?? 0) }}</td>
                                    @empty
                                        <td>0,0</td>
                                    @endforelse
                                    @foreach ($deltaLabels as $key => $label)
                                        @php $deltaValue = $payload['total']['delta_values'][$key] ?? 0; @endphp
                                        <td class="{{ $deltaClass($deltaValue) }}">{{ $formatDeltaJuta($deltaValue) }}</td>
                                    @endforeach
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const exportLink = document.querySelector('[data-hourly-export-pdf]');
    const updateExportLink = function () {
        if (!exportLink) return;

        const form = exportLink.closest('form');
        const params = new URLSearchParams();
        params.set('cabang', form && form.elements.cabang ? form.elements.cabang.value : 'all');
        params.set('segmen', form && form.elements.segmen ? form.elements.segmen.value : 'all');
        exportLink.href = exportLink.dataset.exportUrl + '?' + params.toString();
    };
    const escapeHtml = function (value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    document.querySelectorAll('[data-hourly-native-select]').forEach(function (select) {
        const host = document.querySelector('[data-hourly-select="' + select.id + '"]');
        if (!host) return;

        const render = function () {
            const active = select.options[select.selectedIndex];
            let iconClass = 'fa-chevron-down';
            if (select.id === 'cabang') iconClass = 'fa-map-marker-alt';
            else if (select.id === 'jenis') iconClass = 'fa-wallet';
            else if (select.id === 'segmen') iconClass = 'fa-layer-group';

            host.innerHTML = [
                '<button type="button" class="hourly-select-toggle">',
                    '<span><i class="fas ' + iconClass + ' mr-2"></i>' + escapeHtml(active ? active.text : 'Pilih') + '</span>',
                    '<i class="fas fa-angle-down"></i>',
                '</button>',
                '<div class="hourly-select-menu">',
                    Array.from(select.options).map(function (option) {
                        return '<button type="button" class="hourly-select-option ' + (option.selected ? 'is-active' : '') + '" data-value="' + escapeHtml(option.value) + '">' + escapeHtml(option.text) + '</button>';
                    }).join(''),
                '</div>'
            ].join('');
        };

        render();

        host.addEventListener('click', function (event) {
            const toggle = event.target.closest('.hourly-select-toggle');
            const option = event.target.closest('.hourly-select-option');

            if (toggle) {
                event.preventDefault();
                document.querySelectorAll('.hourly-select.is-open').forEach(function (openSelect) {
                    if (openSelect !== host) openSelect.classList.remove('is-open');
                });
                host.classList.toggle('is-open');
            }

            if (option) {
                event.preventDefault();
                select.value = option.dataset.value;
                host.classList.remove('is-open');
                render();
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        select.addEventListener('change', updateExportLink);
    });

    document.addEventListener('click', function (event) {
        if (event.target.closest('.hourly-select')) return;
        document.querySelectorAll('.hourly-select.is-open').forEach(function (host) {
            host.classList.remove('is-open');
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;

        document.querySelectorAll('.hourly-select.is-open').forEach(function (host) {
            host.classList.remove('is-open');
        });
    });

    if (exportLink) {
        updateExportLink();
        exportLink.addEventListener('click', function () {
            updateExportLink();
            document.querySelectorAll('.hourly-select.is-open').forEach(function (host) {
                host.classList.remove('is-open');
            });
        });
    }
});
</script>
@endsection
