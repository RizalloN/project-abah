@extends('layouts.admin')

@section('title', $pageTitle ?? 'Marketshare - Area 6')

@php
    $payload = $marketShareArea6 ?? [];
    $segments = $payload['segments'] ?? [];
    $selected = $payload['selected'] ?? [];
    $headers = $payload['headers'] ?? ['groups' => [], 'columns' => []];
    $rows = $payload['rows'] ?? [];
    $selectedKey = $selected['key'] ?? 'dpk';
    $segmentsByGroup = collect($segments)->groupBy('group');

    $toneClass = static function (string $value): string {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '-' || $trimmed === '0' || $trimmed === '0.00%' || $trimmed === '0.0%' || $trimmed === '0.00') {
            return 'ms-neutral';
        }

        return str_contains($trimmed, '(') || str_starts_with($trimmed, '-')
            ? 'ms-negative'
            : 'ms-positive';
    };
@endphp

@section('content')
<div class="marketshare-area-page">
    <section class="marketshare-area-hero">
        <div>
            <span class="marketshare-area-eyebrow">Market Share Area 6</span>
            <h1>{{ $payload['title'] ?? 'Marketshare - Area 6' }}</h1>
            <p>{{ $payload['subtitle'] ?? '' }}</p>
        </div>
        <div class="marketshare-area-meta">
            <span><i class="fas fa-calendar-alt"></i> {{ $payload['period'] ?? 'April 2026' }}</span>
            <span><i class="fas fa-layer-group"></i> {{ $selected['label'] ?? '-' }}</span>
            <span><i class="fas fa-balance-scale"></i> {{ $payload['unit'] ?? 'Rp dalam Miliar' }}</span>
        </div>
    </section>

    <section class="marketshare-area-toolbar">
        <form method="GET" action="{{ route('report.dashboard-dana.market-share.area6') }}" class="marketshare-area-form">
            <label for="marketshare-segmen">Segmen</label>
            <select id="marketshare-segmen" name="segmen" class="form-control" onchange="this.form.submit()">
                @foreach($segmentsByGroup as $group => $items)
                    <optgroup label="{{ $group }}">
                        @foreach($items as $key => $segment)
                            <option value="{{ $key }}" @selected($selectedKey === $key)>{{ $segment['label'] }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </form>

        <div class="marketshare-area-tabs" aria-label="Pilihan segmen marketshare">
            @foreach($segments as $key => $segment)
                <a href="{{ route('report.dashboard-dana.market-share.area6', ['segmen' => $key]) }}"
                   class="marketshare-area-tab {{ $selectedKey === $key ? 'active' : '' }}">
                    {{ $segment['label'] }}
                </a>
            @endforeach
        </div>
    </section>

    <section class="marketshare-area-panel">
        <div class="marketshare-area-panel-head">
            <div>
                <h2>{{ $selected['label'] ?? 'Total DPK' }}</h2>
                <span>{{ $selected['group'] ?? 'Simpanan' }} | {{ $payload['period'] ?? 'April 2026' }} | {{ $payload['unit'] ?? 'Rp dalam Miliar' }}</span>
            </div>
            <div class="marketshare-area-source">
                <i class="fas fa-file-pdf"></i>
                {{ $payload['source'] ?? 'Kajian Market Share Umum RO Malang April 2026.pdf' }}
            </div>
        </div>

        <div class="marketshare-area-table-wrap">
            <table class="marketshare-area-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="branch-head">Branch Office</th>
                        @foreach($headers['groups'] ?? [] as $group)
                            <th colspan="{{ $group['span'] }}" class="{{ ($group['label'] ?? '') === 'Market Share' ? 'share-head' : 'group-head' }}">
                                {{ $group['label'] }}
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach($headers['columns'] ?? [] as $column)
                            <th class="sub-head">{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr class="{{ !empty($row['total']) ? 'area-total-row' : '' }}">
                            <th class="branch-cell">{{ $row['branch'] ?? '-' }}</th>
                            @foreach(($row['values'] ?? []) as $value)
                                <td class="{{ $toneClass((string) $value) }}">{{ $value }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($headers['columns'] ?? []) + 1 }}" class="empty-cell">Data segmen tidak tersedia.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@section('styles')
<style>
    .marketshare-area-page {
        min-height: calc(100vh - 74px);
        padding: 1rem;
        color: #152238;
        background:
            linear-gradient(180deg, rgba(239, 246, 255, .86) 0%, rgba(248, 250, 252, .96) 42%, #f8fafc 100%);
    }

    .marketshare-area-hero {
        align-items: center;
        background: #ffffff;
        border: 1px solid #dbe7f6;
        border-top: 3px solid #0857c3;
        border-radius: 14px;
        color: #0f172a;
        display: flex;
        gap: 1rem;
        justify-content: space-between;
        margin-bottom: .85rem;
        padding: 1rem 1.15rem;
        box-shadow: 0 14px 30px -28px rgba(15, 23, 42, .45);
    }

    .marketshare-area-eyebrow {
        display: block;
        color: #0857c3;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .08em;
        margin-bottom: .2rem;
        text-transform: uppercase;
    }

    .marketshare-area-hero h1 {
        font-size: clamp(1.4rem, 2.2vw, 1.95rem);
        font-weight: 900;
        margin: 0;
        line-height: 1.12;
    }

    .marketshare-area-hero p {
        color: #475569;
        font-size: .9rem;
        margin: .25rem 0 0;
        max-width: 860px;
    }

    .marketshare-area-meta {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: .45rem;
        max-width: 620px;
    }

    .marketshare-area-meta span {
        background: #f8fbff;
        border: 1px solid #dbe7f6;
        border-radius: 999px;
        color: #334155;
        font-size: .78rem;
        font-weight: 800;
        padding: .42rem .68rem;
        white-space: nowrap;
    }

    .marketshare-area-toolbar {
        align-items: flex-start;
        background: #fff;
        border: 1px solid #d9e7f7;
        border-radius: 14px;
        display: grid;
        gap: .75rem;
        margin-bottom: .85rem;
        padding: .85rem;
        box-shadow: 0 12px 28px -28px rgba(15, 23, 42, .42);
    }

    .marketshare-area-form {
        align-items: center;
        display: flex;
        gap: .75rem;
    }

    .marketshare-area-form label {
        color: #50627c;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .04em;
        margin: 0;
        text-transform: uppercase;
    }

    .marketshare-area-form select {
        height: 40px;
        border-color: #bcd2ee;
        border-radius: 10px;
        font-weight: 800;
        min-width: 280px;
        box-shadow: none;
    }

    .marketshare-area-tabs {
        display: flex;
        overflow-x: auto;
        gap: .45rem;
        padding-bottom: .1rem;
        scrollbar-color: #9ebee8 #edf4ff;
        scrollbar-width: thin;
    }

    .marketshare-area-tabs::-webkit-scrollbar {
        height: 8px;
    }

    .marketshare-area-tabs::-webkit-scrollbar-track {
        background: #edf4ff;
        border-radius: 999px;
    }

    .marketshare-area-tabs::-webkit-scrollbar-thumb {
        background: #9ebee8;
        border-radius: 999px;
    }

    .marketshare-area-tab {
        border: 1px solid #c9dbf2;
        border-radius: 10px;
        color: #16416f;
        flex: 0 0 auto;
        font-size: .78rem;
        font-weight: 800;
        padding: .42rem .7rem;
        text-decoration: none;
    }

    .marketshare-area-tab:hover,
    .marketshare-area-tab.active {
        background: #075fb8;
        border-color: #075fb8;
        color: #fff;
        text-decoration: none;
    }

    .marketshare-area-panel {
        background: #fff;
        border: 1px solid #d9e7f7;
        border-radius: 14px;
        box-shadow: 0 18px 42px -34px rgba(15, 23, 42, .55);
        overflow: hidden;
    }

    .marketshare-area-panel-head {
        align-items: center;
        display: flex;
        gap: 1rem;
        justify-content: space-between;
        padding: .85rem 1rem;
    }

    .marketshare-area-panel-head h2 {
        font-size: 1.15rem;
        font-weight: 900;
        margin: 0;
    }

    .marketshare-area-panel-head span,
    .marketshare-area-source {
        color: #64748b;
        font-size: .8rem;
        font-weight: 700;
    }

    .marketshare-area-table-wrap {
        border-top: 1px solid #d9e7f7;
        max-height: calc(100vh - 360px);
        min-height: 360px;
        overflow: auto;
        scrollbar-color: #9ebee8 #edf4ff;
        scrollbar-width: thin;
    }

    .marketshare-area-table-wrap::-webkit-scrollbar {
        height: 10px;
        width: 10px;
    }

    .marketshare-area-table-wrap::-webkit-scrollbar-track {
        background: #edf4ff;
    }

    .marketshare-area-table-wrap::-webkit-scrollbar-thumb {
        background: #9ebee8;
        border-radius: 999px;
    }

    .marketshare-area-table {
        border-collapse: separate;
        border-spacing: 0;
        font-size: .8rem;
        min-width: 1480px;
        width: 100%;
    }

    .marketshare-area-table th,
    .marketshare-area-table td {
        border-bottom: 1px solid #d7e4f4;
        border-right: 1px solid #d7e4f4;
        padding: .56rem .62rem;
        text-align: right;
        white-space: nowrap;
    }

    .marketshare-area-table thead th {
        color: #fff;
        font-weight: 900;
        text-align: center;
        position: sticky;
        top: 0;
        z-index: 5;
    }

    .marketshare-area-table thead tr:nth-child(2) th {
        top: 35px;
        z-index: 6;
    }

    .marketshare-area-table .branch-head,
    .marketshare-area-table .branch-cell {
        left: 0;
        position: sticky;
        text-align: left;
        z-index: 2;
    }

    .marketshare-area-table .branch-head {
        background: #073f8f;
        min-width: 170px;
        z-index: 8;
    }

    .marketshare-area-table .branch-cell {
        background: #ffffff;
        color: #11335f;
        font-weight: 900;
        box-shadow: 8px 0 16px -16px rgba(15, 23, 42, .35);
    }

    .marketshare-area-table .group-head {
        background: #0857c3;
    }

    .marketshare-area-table .share-head {
        background: #0b74d1;
    }

    .marketshare-area-table .sub-head {
        background: #0a4ea5;
    }

    .marketshare-area-table tbody tr:nth-child(even) td {
        background: #f8fbff;
    }

    .marketshare-area-table tbody tr:hover td,
    .marketshare-area-table tbody tr:hover th {
        background: #eff6ff;
    }

    .marketshare-area-table .area-total-row th,
    .marketshare-area-table .area-total-row td {
        background: #e8f2ff !important;
        color: #073f8f !important;
        border-top: 1px solid #9fc4f4;
        border-bottom: 1px solid #9fc4f4;
        font-weight: 900;
    }

    .marketshare-area-table .area-total-row th {
        background: #dcecff !important;
    }

    .marketshare-area-table .area-total-row .ms-negative {
        color: #b91c1c !important;
    }

    .marketshare-area-table .area-total-row .ms-positive {
        color: #047857 !important;
    }

    .marketshare-area-table .area-total-row:hover th,
    .marketshare-area-table .area-total-row:hover td {
        background: #dcecff !important;
    }

    .marketshare-area-table thead tr:first-child .branch-head {
        top: 0;
    }

    .marketshare-area-table thead tr:first-child th {
        height: 35px;
    }

    .marketshare-area-table thead tr:nth-child(2) .branch-head {
        top: 35px;
    }

    .marketshare-area-table .ms-negative {
        color: #c62828;
        font-weight: 800;
    }

    .marketshare-area-table .ms-positive {
        color: #0b7a44;
        font-weight: 800;
    }

    .marketshare-area-table .ms-neutral {
        color: #475569;
        font-weight: 700;
    }

    .empty-cell {
        padding: 2rem !important;
        text-align: center !important;
    }

    @media (max-width: 768px) {
        .marketshare-area-page {
            padding: .75rem;
        }

        .marketshare-area-hero,
        .marketshare-area-panel-head {
            align-items: stretch;
            flex-direction: column;
        }

        .marketshare-area-meta {
            justify-content: flex-start;
        }

        .marketshare-area-form {
            align-items: stretch;
            flex-direction: column;
        }

        .marketshare-area-form select {
            min-width: 0;
            width: 100%;
        }

        .marketshare-area-table-wrap {
            max-height: calc(100vh - 420px);
        }
    }
</style>
@endsection
