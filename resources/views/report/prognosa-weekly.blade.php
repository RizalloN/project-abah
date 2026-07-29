@extends('layouts.admin')

@section('title', 'Weekly Prognosa')

@php
    $rowCount = is_countable($rows ?? null) ? count($rows) : 0;
    $selectedSheetLabel = (string) data_get($selectedSheet ?? [], 'label', 'Area 6');
    $fetchedAtLabel = '-';

    if (!empty($fetchedAt)) {
        try {
            $fetchedAtLabel = \Carbon\Carbon::parse($fetchedAt)->translatedFormat('d M Y, H:i');
        } catch (\Throwable $exception) {
            $fetchedAtLabel = (string) $fetchedAt;
        }
    }
@endphp

@section('styles')
<style>
    .prognosa-page {
        --prognosa-border: #dbe3ec;
        --prognosa-border-strong: #cbd5e1;
        --prognosa-ink: #172033;
        --prognosa-muted: #64748b;
        --prognosa-primary: #0b5cab;
        --prognosa-primary-dark: #084a88;
        --prognosa-indicator: #0b3a6e;
        --prognosa-position: #0b5cab;
        --prognosa-prognosa: #b45309;
        --prognosa-delta: #0f766e;
        --prognosa-rka: #2f6f4e;
        --prognosa-position-soft: #eaf3fb;
        --prognosa-prognosa-soft: #fff7df;
        --prognosa-delta-soft: #e8f5f2;
        --prognosa-rka-soft: #edf6ef;
        --prognosa-surface: #ffffff;
        --prognosa-subtle: #f8fafc;
        --prognosa-indicator-width: 270px;
        width: 100%;
        min-width: 0;
        padding: 1rem 0 0.75rem;
    }

    .prognosa-page,
    .prognosa-page * {
        box-sizing: border-box;
    }

    .prognosa-page-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.85rem;
        padding: 0 0.15rem;
    }

    .prognosa-heading {
        min-width: 0;
    }

    .prognosa-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.38rem;
        margin-bottom: 0.28rem;
        color: var(--prognosa-primary);
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0;
        line-height: 1.2;
        text-transform: uppercase;
    }

    .prognosa-heading h1 {
        margin: 0;
        color: var(--prognosa-ink);
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: 0;
        line-height: 1.25;
    }

    .prognosa-heading p {
        max-width: 760px;
        margin: 0.28rem 0 0;
        color: var(--prognosa-muted);
        font-size: 0.84rem;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    .prognosa-position {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        flex: 0 0 auto;
        min-width: 190px;
        padding-left: 1rem;
        border-left: 1px solid var(--prognosa-border);
    }

    .prognosa-position__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        border: 1px solid #bfdbfe;
        border-radius: 6px;
        background: #eff6ff;
        color: var(--prognosa-primary);
    }

    .prognosa-position span,
    .prognosa-summary dt {
        display: block;
        margin: 0 0 0.16rem;
        color: var(--prognosa-muted);
        font-size: 0.65rem;
        font-weight: 800;
        letter-spacing: 0;
        line-height: 1.2;
        text-transform: uppercase;
    }

    .prognosa-position strong {
        display: block;
        color: var(--prognosa-ink);
        font-size: 0.88rem;
        font-weight: 800;
        line-height: 1.3;
    }

    .prognosa-workspace {
        width: 100%;
        max-width: 100%;
        overflow: hidden;
        border: 1px solid var(--prognosa-border);
        border-radius: 8px;
        background: var(--prognosa-surface);
        box-shadow: 0 8px 22px -20px rgba(15, 23, 42, 0.42);
    }

    .prognosa-toolbar {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 0.85rem 1rem;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid var(--prognosa-border);
        background: var(--prognosa-surface);
    }

    .prognosa-selector {
        display: flex;
        align-items: flex-end;
        gap: 0.65rem;
        min-width: 0;
        margin: 0;
    }

    .prognosa-field {
        min-width: min(280px, 100%);
    }

    .prognosa-field > label {
        display: block;
        margin: 0 0 0.32rem;
        color: #475569;
        font-size: 0.72rem;
        font-weight: 800;
        line-height: 1.2;
    }

    .prognosa-select-shell {
        position: relative;
    }

    .prognosa-select-icon,
    .prognosa-select-chevron {
        position: absolute;
        top: 50%;
        z-index: 1;
        font-size: 0.78rem;
        pointer-events: none;
        transform: translateY(-50%);
    }

    .prognosa-select-icon {
        left: 0.72rem;
        color: var(--prognosa-primary);
    }

    .prognosa-select-chevron {
        right: 0.72rem;
        color: #64748b;
        font-size: 0.68rem;
    }

    .prognosa-selector select {
        width: 100%;
        min-width: 240px;
        height: 38px;
        appearance: none;
        padding: 0.42rem 2.1rem 0.42rem 2rem;
        border: 1px solid var(--prognosa-border-strong);
        border-radius: 6px;
        background-color: #ffffff;
        color: var(--prognosa-ink);
        font-size: 0.82rem;
        font-weight: 700;
        line-height: 1.2;
        box-shadow: none;
    }

    .prognosa-selector select:focus {
        border-color: var(--prognosa-primary);
        outline: 0;
        box-shadow: 0 0 0 3px rgba(11, 92, 171, 0.12);
    }

    .prognosa-selector select:disabled {
        cursor: not-allowed;
        background-color: #f1f5f9;
        color: #475569;
        opacity: 1;
    }

    .prognosa-lock {
        display: inline-flex;
        align-items: center;
        gap: 0.34rem;
        min-height: 38px;
        padding: 0.45rem 0;
        color: var(--prognosa-muted);
        font-size: 0.72rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .prognosa-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.5rem;
        flex: 0 0 auto;
    }

    .prognosa-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.42rem;
        min-height: 38px;
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--prognosa-border-strong);
        border-radius: 6px;
        background: #ffffff;
        color: #334155;
        font-size: 0.78rem;
        font-weight: 750;
        line-height: 1.2;
        text-decoration: none;
        white-space: nowrap;
    }

    .prognosa-button:hover,
    .prognosa-button:focus {
        border-color: #94a3b8;
        background: var(--prognosa-subtle);
        color: var(--prognosa-ink);
        text-decoration: none;
    }

    .prognosa-button--primary {
        border-color: var(--prognosa-primary);
        background: var(--prognosa-primary);
        color: #ffffff;
    }

    .prognosa-button--primary:hover,
    .prognosa-button--primary:focus {
        border-color: var(--prognosa-primary-dark);
        background: var(--prognosa-primary-dark);
        color: #ffffff;
    }

    .prognosa-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin: 0;
        border-bottom: 1px solid var(--prognosa-border);
        background: var(--prognosa-subtle);
    }

    .prognosa-summary__item {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        min-width: 0;
        padding: 0.68rem 1rem;
    }

    .prognosa-summary__item > div {
        min-width: 0;
    }

    .prognosa-summary__item + .prognosa-summary__item {
        border-left: 1px solid var(--prognosa-border);
    }

    .prognosa-summary__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        flex: 0 0 32px;
        border: 1px solid #c9dff2;
        border-radius: 6px;
        background: var(--prognosa-position-soft);
        color: var(--prognosa-position);
        font-size: 0.78rem;
    }

    .prognosa-summary__icon--date {
        border-color: #bfe1da;
        background: var(--prognosa-delta-soft);
        color: var(--prognosa-delta);
    }

    .prognosa-summary__icon--rows {
        border-color: #ccdfd0;
        background: var(--prognosa-rka-soft);
        color: var(--prognosa-rka);
    }

    .prognosa-summary__icon--sync {
        border-color: #d7dee8;
        background: #f1f5f9;
        color: #475569;
    }

    .prognosa-summary dd {
        margin: 0;
        color: var(--prognosa-ink);
        font-size: 0.85rem;
        font-weight: 800;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .prognosa-table-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.65rem 1rem;
        border-bottom: 1px solid var(--prognosa-border);
        background: #ffffff;
    }

    .prognosa-table-heading strong {
        color: var(--prognosa-ink);
        font-size: 0.84rem;
        font-weight: 800;
        line-height: 1.3;
    }

    .prognosa-table-context {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem;
        min-width: 0;
    }

    .prognosa-table-legend {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .prognosa-table-legend li {
        display: inline-flex;
        align-items: center;
        gap: 0.28rem;
        color: #475569;
        font-size: 0.68rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .prognosa-table-legend__swatch {
        width: 9px;
        height: 9px;
        flex: 0 0 9px;
        border-radius: 2px;
        background: var(--prognosa-position);
    }

    .prognosa-table-legend__swatch--delta {
        background: var(--prognosa-delta);
    }

    .prognosa-table-legend__swatch--prognosa {
        background: var(--prognosa-prognosa);
    }

    .prognosa-table-legend__swatch--rka {
        background: var(--prognosa-rka);
    }

    .prognosa-table-source {
        color: var(--prognosa-muted);
        font-size: 0.72rem;
        font-weight: 600;
        text-align: right;
    }

    .prognosa-table-wrap {
        position: relative;
        height: calc(100dvh - 320px);
        min-height: 420px;
        max-height: 760px;
        overflow: auto;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
        background: #ffffff;
        isolation: isolate;
    }

    .prognosa-corner-header {
        position: absolute;
        top: 0;
        left: 0;
        z-index: 60;
        display: grid;
        grid-template-rows: 38px 38px;
        width: var(--prognosa-indicator-width);
        height: 76px;
        color: #ffffff;
        font-size: 0.68rem;
        font-weight: 800;
        line-height: 1.2;
        text-align: left;
        text-transform: uppercase;
        pointer-events: none;
        will-change: transform;
        box-shadow: 5px 0 10px -10px rgba(15, 23, 42, 0.65);
    }

    .prognosa-corner-header span,
    .prognosa-corner-header strong {
        display: flex;
        align-items: center;
        padding: 0.45rem 0.62rem;
        border-right: 1px solid #2d6598;
        border-bottom: 1px solid #376f9f;
    }

    .prognosa-corner-header span {
        background: var(--prognosa-indicator);
    }

    .prognosa-corner-header strong {
        background: #174f86;
        color: #ffffff;
    }

    .prognosa-table {
        width: 100%;
        min-width: 1680px;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
        table-layout: fixed;
        color: var(--prognosa-ink);
        font-variant-numeric: tabular-nums;
    }

    .prognosa-table th,
    .prognosa-table td {
        height: 38px;
        padding: 0.48rem 0.62rem;
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e8edf3;
        text-align: right;
        white-space: nowrap;
    }

    .prognosa-table th:not(.prognosa-col-indicator),
    .prognosa-table td:not(.prognosa-col-indicator) {
        width: 112px;
        min-width: 112px;
    }

    .prognosa-table thead th {
        position: sticky;
        z-index: 5;
        height: 38px;
        padding-top: 0.45rem;
        padding-bottom: 0.45rem;
        border-color: #d7e0ea;
        background: #eef3f8;
        color: #334155;
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0;
        line-height: 1.2;
        text-align: center;
        text-transform: uppercase;
    }

    .prognosa-table thead tr:first-child th {
        top: 0;
        color: #ffffff;
    }

    .prognosa-table thead tr:nth-child(2) th {
        top: 38px;
        color: #475569;
    }

    .prognosa-table thead tr:first-child th[data-group="indicator"] {
        background: var(--prognosa-indicator);
    }

    .prognosa-table thead tr:first-child th[data-group="position"] {
        background: var(--prognosa-position);
    }

    .prognosa-table thead tr:first-child th[data-group="prognosa"] {
        background: var(--prognosa-prognosa);
    }

    .prognosa-table thead tr:first-child th[data-group="delta"] {
        background: var(--prognosa-delta);
    }

    .prognosa-table thead tr:first-child th[data-group="rka"] {
        background: var(--prognosa-rka);
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="indicator"] {
        background: #174f86;
        color: #ffffff;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="position"] {
        background: var(--prognosa-position-soft);
        color: #174b78;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="prognosa"] {
        background: var(--prognosa-prognosa-soft);
        color: #92400e;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="delta"] {
        background: var(--prognosa-delta-soft);
        color: #0d5f58;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="rka"] {
        background: var(--prognosa-rka-soft);
        color: #285d42;
    }

    .prognosa-table tbody td {
        background: #ffffff;
        color: #334155;
        font-size: 0.78rem;
        line-height: 1.25;
    }

    .prognosa-table tbody td[data-group="position"] {
        background: #fcfdff;
    }

    .prognosa-table tbody td[data-group="prognosa"] {
        background: #fffdf8;
    }

    .prognosa-table tbody td[data-group="delta"] {
        background: #f8fcfb;
    }

    .prognosa-table tbody td[data-group="rka"] {
        background: #fbfdfb;
    }

    .prognosa-table tbody tr:nth-child(even):not(.prognosa-section-row) td[data-group="indicator"] {
        background: #f8fafc;
    }

    .prognosa-table tbody tr:nth-child(even):not(.prognosa-section-row) td[data-group="position"] {
        background: #f6f9fd;
    }

    .prognosa-table tbody tr:nth-child(even):not(.prognosa-section-row) td[data-group="prognosa"] {
        background: #fffaf0;
    }

    .prognosa-table tbody tr:nth-child(even):not(.prognosa-section-row) td[data-group="delta"] {
        background: #f2f9f7;
    }

    .prognosa-table tbody tr:nth-child(even):not(.prognosa-section-row) td[data-group="rka"] {
        background: #f5faf6;
    }

    .prognosa-table tbody tr:hover td {
        background: #eef5fc !important;
    }

    .prognosa-col-indicator {
        position: sticky !important;
        left: 0;
        width: var(--prognosa-indicator-width) !important;
        min-width: var(--prognosa-indicator-width) !important;
        max-width: var(--prognosa-indicator-width) !important;
        text-align: left !important;
        white-space: normal !important;
        box-shadow: 5px 0 10px -10px rgba(15, 23, 42, 0.65);
    }

    .prognosa-table thead .prognosa-col-indicator {
        z-index: 9;
        vertical-align: middle;
    }

    .prognosa-table tbody .prognosa-col-indicator {
        z-index: 3;
        background: #ffffff;
        color: #24364f;
        font-weight: 650;
    }

    .prognosa-table tbody tr:nth-child(even):not(.prognosa-section-row) .prognosa-col-indicator {
        background: #f8fafc;
    }

    .prognosa-table .prognosa-group-start {
        border-left: 2px solid #aebdcd;
    }

    .prognosa-section-row td {
        border-top: 1px solid #a9c8e6;
        border-bottom-color: #c9dcef;
        background: #eaf2fb !important;
        color: #183b60;
        font-weight: 800;
    }

    .prognosa-section-row .prognosa-col-indicator {
        background: #dceafb !important;
        color: #0b4f8a;
    }

    .prognosa-empty-value {
        color: #94a3b8 !important;
    }

    .prognosa-state {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.7rem;
        min-height: 300px;
        padding: 2rem;
        color: var(--prognosa-muted);
        text-align: left;
    }

    .prognosa-state__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        border: 1px solid var(--prognosa-border);
        border-radius: 6px;
        background: var(--prognosa-subtle);
        color: #64748b;
    }

    .prognosa-state strong {
        display: block;
        margin-bottom: 0.18rem;
        color: var(--prognosa-ink);
        font-size: 0.88rem;
    }

    .prognosa-state span {
        display: block;
        max-width: 520px;
        font-size: 0.78rem;
        line-height: 1.45;
    }

    .prognosa-state--error .prognosa-state__icon {
        border-color: #fed7aa;
        background: #fff7ed;
        color: #c2410c;
    }

    .prognosa-table-wrap::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }

    .prognosa-table-wrap::-webkit-scrollbar-track {
        background: #f1f5f9;
    }

    .prognosa-table-wrap::-webkit-scrollbar-thumb {
        border: 2px solid #f1f5f9;
        border-radius: 6px;
        background: #a8b5c5;
    }

    @media (max-width: 991.98px) {
        .prognosa-page {
            --prognosa-indicator-width: 220px;
        }

        .prognosa-page-header {
            align-items: flex-start;
        }

        .prognosa-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        .prognosa-selector {
            width: 100%;
        }

        .prognosa-field {
            flex: 1 1 auto;
        }

        .prognosa-selector select {
            width: 100%;
        }

        .prognosa-actions {
            justify-content: flex-start;
        }

        .prognosa-table-wrap {
            height: calc(100dvh - 390px);
        }
    }

    @media (max-width: 767.98px) {
        .prognosa-page {
            --prognosa-indicator-width: 180px;
            padding-top: 0.7rem;
        }

        .prognosa-page-header {
            display: block;
        }

        .prognosa-heading h1 {
            font-size: 1.15rem;
        }

        .prognosa-position {
            margin-top: 0.65rem;
            padding: 0.6rem 0 0;
            border-top: 1px solid var(--prognosa-border);
            border-left: 0;
        }

        .prognosa-selector {
            align-items: stretch;
            flex-direction: column;
        }

        .prognosa-field,
        .prognosa-selector select {
            min-width: 0;
            width: 100%;
        }

        .prognosa-lock {
            min-height: 0;
            padding: 0;
        }

        .prognosa-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            width: 100%;
        }

        .prognosa-button {
            min-width: 0;
            white-space: normal;
        }

        .prognosa-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .prognosa-summary__item:nth-child(3) {
            border-left: 0;
            border-top: 1px solid var(--prognosa-border);
        }

        .prognosa-summary__item:nth-child(4) {
            border-top: 1px solid var(--prognosa-border);
        }

        .prognosa-table-heading {
            align-items: flex-start;
            flex-direction: column;
            gap: 0.15rem;
        }

        .prognosa-table-context {
            align-items: flex-start;
            flex-direction: column;
            gap: 0.28rem;
        }

        .prognosa-table-source {
            text-align: left;
        }

        .prognosa-table-wrap {
            height: calc(100dvh - 455px);
            min-height: 390px;
        }

        .prognosa-table th:not(.prognosa-col-indicator),
        .prognosa-table td:not(.prognosa-col-indicator) {
            width: 104px;
            min-width: 104px;
        }

        .prognosa-table tbody td {
            font-size: 0.75rem;
        }
    }

    @media (max-width: 420px) {
        .prognosa-toolbar {
            padding: 0.75rem;
        }

        .prognosa-actions {
            gap: 0.38rem;
        }

        .prognosa-button {
            gap: 0.3rem;
            padding: 0.46rem 0.38rem;
            font-size: 0.7rem;
        }

        .prognosa-summary__item {
            gap: 0.48rem;
            padding: 0.62rem 0.75rem;
        }

        .prognosa-summary__icon {
            width: 28px;
            height: 28px;
            flex-basis: 28px;
        }

        .prognosa-summary dd {
            font-size: 0.79rem;
        }
    }

    @media (max-height: 720px) and (min-width: 768px) {
        .prognosa-table-wrap {
            height: 390px;
            min-height: 390px;
        }
    }
</style>
@endsection

@section('content')
<div class="prognosa-page">
    <header class="prognosa-page-header">
        <div class="prognosa-heading">
            <span class="prognosa-eyebrow">
                <i class="fas fa-chart-line" aria-hidden="true"></i>
                Monitoring Mingguan
            </span>
            <h1>Weekly Prognosa</h1>
            <p>{{ $title }}</p>
        </div>

        <div class="prognosa-position" aria-label="Posisi data terakhir">
            <span class="prognosa-position__icon" aria-hidden="true">
                <i class="far fa-calendar-check"></i>
            </span>
            <div>
                <span>Posisi Data</span>
                <strong>{{ $latestDate ?: 'Belum tersedia' }}</strong>
            </div>
        </div>
    </header>

    <section class="prognosa-workspace" aria-labelledby="prognosa-table-title">
        <div class="prognosa-toolbar">
            <form class="prognosa-selector" method="GET" action="{{ route('prognosa.weekly') }}">
                <div class="prognosa-field">
                    <label for="prognosa-sheet">Wilayah Laporan</label>
                    <div class="prognosa-select-shell">
                        <i class="fas fa-building prognosa-select-icon" aria-hidden="true"></i>
                        <select id="prognosa-sheet"
                                name="sheet"
                                aria-label="Pilih wilayah laporan"
                                {{ $isLocked ? 'disabled' : '' }}
                                onchange="this.form.submit()">
                            @foreach($sheetOptions as $key => $option)
                                <option value="{{ $key }}" @selected($selectedSheetKey === $key)>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                        <i class="fas fa-chevron-down prognosa-select-chevron" aria-hidden="true"></i>
                    </div>
                </div>

                @if($isLocked)
                    <input type="hidden" name="sheet" value="{{ $selectedSheetKey }}">
                    <span class="prognosa-lock">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        Sesuai akses akun
                    </span>
                @endif
            </form>

            <div class="prognosa-actions">
                <a class="prognosa-button prognosa-button--primary"
                   href="{{ route('prognosa.weekly', ['sheet' => $selectedSheetKey, 'refresh' => 1]) }}">
                    <i class="fas fa-sync-alt" aria-hidden="true"></i>
                    Perbarui Data
                </a>
                <a class="prognosa-button"
                   href="{{ $spreadsheetUrl }}"
                   target="_blank"
                   rel="noopener">
                    <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                    Spreadsheet
                </a>
            </div>
        </div>

        <dl class="prognosa-summary">
            <div class="prognosa-summary__item">
                <span class="prognosa-summary__icon" aria-hidden="true">
                    <i class="fas fa-building"></i>
                </span>
                <div>
                    <dt>Wilayah Aktif</dt>
                    <dd>{{ $selectedSheetLabel }}</dd>
                </div>
            </div>
            <div class="prognosa-summary__item">
                <span class="prognosa-summary__icon prognosa-summary__icon--date" aria-hidden="true">
                    <i class="far fa-calendar-alt"></i>
                </span>
                <div>
                    <dt>Posisi Data</dt>
                    <dd>{{ $latestDate ?: '-' }}</dd>
                </div>
            </div>
            <div class="prognosa-summary__item">
                <span class="prognosa-summary__icon prognosa-summary__icon--rows" aria-hidden="true">
                    <i class="fas fa-list-ol"></i>
                </span>
                <div>
                    <dt>Baris Laporan</dt>
                    <dd>{{ number_format($rowCount, 0, ',', '.') }}</dd>
                </div>
            </div>
            <div class="prognosa-summary__item">
                <span class="prognosa-summary__icon prognosa-summary__icon--sync" aria-hidden="true">
                    <i class="fas fa-sync-alt"></i>
                </span>
                <div>
                    <dt>Sinkronisasi</dt>
                    <dd>{{ $fetchedAtLabel }}</dd>
                </div>
            </div>
        </dl>

        <div class="prognosa-table-heading">
            <strong id="prognosa-table-title">Detail {{ $selectedSheetLabel }}</strong>
            <div class="prognosa-table-context">
                <ul class="prognosa-table-legend" aria-label="Kelompok data">
                    <li><span class="prognosa-table-legend__swatch"></span>Posisi</li>
                    <li><span class="prognosa-table-legend__swatch prognosa-table-legend__swatch--prognosa"></span>Prognosa</li>
                    <li><span class="prognosa-table-legend__swatch prognosa-table-legend__swatch--delta"></span>Delta</li>
                    <li><span class="prognosa-table-legend__swatch prognosa-table-legend__swatch--rka"></span>RKA</li>
                </ul>
                <span class="prognosa-table-source">Sumber Google Spreadsheet</span>
            </div>
        </div>

        @if($error)
            <div class="prognosa-state prognosa-state--error" role="alert">
                <span class="prognosa-state__icon" aria-hidden="true">
                    <i class="fas fa-exclamation-triangle"></i>
                </span>
                <div>
                    <strong>Data belum dapat dimuat</strong>
                    <span>{{ $error }}</span>
                </div>
            </div>
        @elseif($rows === [] || $headerColumns === [])
            <div class="prognosa-state">
                <span class="prognosa-state__icon" aria-hidden="true">
                    <i class="fas fa-table"></i>
                </span>
                <div>
                    <strong>Belum ada data</strong>
                    <span>Data pada wilayah ini belum tersedia.</span>
                </div>
            </div>
        @else
            <div class="prognosa-table-wrap" tabindex="0" aria-label="Tabel Weekly Prognosa {{ $selectedSheetLabel }}">
                <div class="prognosa-corner-header" aria-hidden="true">
                    <span>Indikator</span>
                    <strong>Keterangan</strong>
                </div>
                <table class="prognosa-table">
                    <caption class="sr-only">Weekly Prognosa {{ $selectedSheetLabel }}</caption>
                    <thead>
                        <tr>
                            @foreach($headerGroups as $group)
                                @php
                                    $groupStart = (int) ($group['start'] ?? 0);
                                    $groupKey = (string) ($group['key'] ?? 'indicator');
                                @endphp
                                <th scope="colgroup"
                                    colspan="{{ $group['colspan'] }}"
                                    rowspan="1"
                                    data-group="{{ $groupKey }}"
                                    class="{{ $groupStart === 0 ? 'prognosa-col-indicator' : '' }} {{ in_array($groupStart, [1, 9, 10, 15, 18], true) ? 'prognosa-group-start' : '' }}">
                                    {{ $groupStart === 0 ? 'Indikator' : $group['label'] }}
                                </th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach($headerColumns as $column)
                                @php
                                    $columnIndex = (int) $column['index'];
                                    $columnGroup = $columnIndex === 0
                                        ? 'indicator'
                                        : ($columnIndex <= 8 ? 'position' : ($columnIndex === 9 ? 'prognosa' : ($columnIndex <= 14 ? 'delta' : 'rka')));
                                @endphp
                                @if($columnIndex === 0)
                                    <th scope="col"
                                        data-group="indicator"
                                        class="prognosa-col-indicator">
                                        {{ $column['label'] }}
                                    </th>
                                @else
                                    <th scope="col"
                                        data-group="{{ $columnGroup }}"
                                        class="{{ in_array($columnIndex, [1, 9, 10, 15, 18], true) ? 'prognosa-group-start' : '' }}">
                                        {{ $column['label'] }}
                                    </th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            @php
                                $rowLabel = trim((string) ($row[0] ?? ''));
                                $isSection = preg_match('/^(?:\d+\.|[A-D]\.)/u', $rowLabel) === 1;
                            @endphp
                            <tr class="{{ $isSection ? 'prognosa-section-row' : '' }}">
                                @foreach($headerColumns as $column)
                                    @php
                                        $columnIndex = (int) $column['index'];
                                        $value = trim((string) ($row[$columnIndex] ?? ''));
                                        $cellGroup = $columnIndex === 0
                                            ? 'indicator'
                                            : ($columnIndex <= 8 ? 'position' : ($columnIndex === 9 ? 'prognosa' : ($columnIndex <= 14 ? 'delta' : 'rka')));
                                        $cellClasses = [
                                            $columnIndex === 0 ? 'prognosa-col-indicator' : '',
                                            in_array($columnIndex, [1, 9, 10, 15, 18], true) ? 'prognosa-group-start' : '',
                                            $value === '' ? 'prognosa-empty-value' : '',
                                        ];
                                    @endphp
                                    <td class="{{ implode(' ', array_filter($cellClasses)) }}"
                                        data-group="{{ $cellGroup }}"
                                        title="{{ $value === '' ? '-' : $value }}">
                                        {{ $value === '' ? '-' : $value }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const scrollSurface = document.querySelector('.prognosa-table-wrap');
        const cornerHeader = document.querySelector('.prognosa-corner-header');

        if (!scrollSurface || !cornerHeader) {
            return;
        }

        let animationFrame = null;

        function syncCornerHeader() {
            if (animationFrame !== null) {
                return;
            }

            animationFrame = window.requestAnimationFrame(function () {
                cornerHeader.style.transform = 'translate3d(' +
                    scrollSurface.scrollLeft + 'px, ' +
                    scrollSurface.scrollTop + 'px, 0)';
                animationFrame = null;
            });
        }

        scrollSurface.addEventListener('scroll', syncCornerHeader, { passive: true });
        syncCornerHeader();
    });
</script>
@endsection
