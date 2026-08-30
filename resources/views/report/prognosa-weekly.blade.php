@extends('layouts.admin')

@section('title', 'Weekly Prognosa')

@php
    $selectedSheetLabel = (string) data_get($selectedSheet ?? [], 'label', 'Area 6');
    $selectedWeek = max(1, min(4, (int) ($activeForecastWeek ?? 1)));

    $columnModes = [
        'all' => 'Semua',
        'position' => 'Posisi',
        'forecast' => 'Prognosa',
        'rka' => 'RKA',
        'runoff' => 'Run Off',
    ];
    $reportSections = [
        'all' => 'Semua',
        'pinjaman' => 'Pinjaman',
        'dpk' => 'DPK',
        'recovery' => 'Recovery',
    ];
    $groupStartColumns = array_values(array_filter(array_map(
        static fn (array $group): int => (int) ($group['start'] ?? 0),
        $headerGroups ?? []
    ), static fn (int $column): bool => $column > 0));
@endphp

@section('styles')
<style>
    .prognosa-page {
        --prognosa-border: #d9e2ec;
        --prognosa-border-strong: #c4d0dd;
        --prognosa-ink: #172033;
        --prognosa-muted: #64748b;
        --prognosa-primary: #0b5cab;
        --prognosa-primary-dark: #084a88;
        --prognosa-no-width: 40px;
        --prognosa-indicator-width: 205px;
        width: 100%;
        min-width: 0;
        padding: 0.85rem 0 1rem;
        color: var(--prognosa-ink);
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
        margin-bottom: 0.75rem;
        padding: 0 0.1rem;
    }

    .prognosa-heading {
        min-width: 0;
    }

    .prognosa-heading h1 {
        margin: 0;
        color: var(--prognosa-ink);
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: 0;
        line-height: 1.25;
    }

    .prognosa-position {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex: 0 0 auto;
        min-width: 176px;
        padding-left: 1rem;
        border-left: 1px solid var(--prognosa-border);
    }

    .prognosa-position__icon,
    .prognosa-summary__icon,
    .prognosa-state__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        border: 1px solid #c9d8e7;
        border-radius: 6px;
        background: #f4f7fa;
        color: var(--prognosa-primary);
    }

    .prognosa-position__icon {
        width: 34px;
        height: 34px;
    }

    .prognosa-position span,
    .prognosa-summary dt {
        display: block;
        margin: 0 0 0.14rem;
        color: var(--prognosa-muted);
        font-size: 0.64rem;
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
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--prognosa-border);
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 10px 24px -22px rgba(15, 23, 42, 0.5);
    }

    .prognosa-toolbar {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 0.75rem 1rem;
        padding: 0.8rem 0.95rem;
        border-bottom: 1px solid var(--prognosa-border);
        background: #ffffff;
    }

    .prognosa-selector {
        display: flex;
        align-items: flex-end;
        gap: 0.6rem;
        min-width: 0;
        margin: 0;
    }

    .prognosa-field {
        width: 270px;
        max-width: 100%;
    }

    .prognosa-field > label {
        display: block;
        margin: 0 0 0.3rem;
        color: #475569;
        font-size: 0.7rem;
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
        pointer-events: none;
        transform: translateY(-50%);
    }

    .prognosa-select-icon {
        left: 0.7rem;
        color: var(--prognosa-primary);
        font-size: 0.75rem;
    }

    .prognosa-select-chevron {
        right: 0.7rem;
        color: #64748b;
        font-size: 0.64rem;
    }

    .prognosa-selector select {
        width: 100%;
        height: 38px;
        appearance: none;
        padding: 0.4rem 2rem 0.4rem 1.9rem;
        border: 1px solid var(--prognosa-border-strong);
        border-radius: 6px;
        background: #ffffff;
        color: var(--prognosa-ink);
        font-size: 0.8rem;
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
        background: #f1f5f9;
        color: #475569;
        opacity: 1;
    }

    .prognosa-lock {
        display: inline-flex;
        align-items: center;
        gap: 0.32rem;
        min-height: 38px;
        color: var(--prognosa-muted);
        font-size: 0.7rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .prognosa-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.45rem;
        flex: 0 0 auto;
        min-width: 0;
    }

    .prognosa-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        min-height: 38px;
        padding: 0.48rem 0.72rem;
        border: 1px solid var(--prognosa-border-strong);
        border-radius: 6px;
        background: #ffffff;
        color: #334155;
        font-size: 0.76rem;
        font-weight: 750;
        line-height: 1.2;
        text-decoration: none;
        white-space: nowrap;
        overflow-wrap: anywhere;
    }

    .prognosa-button:hover,
    .prognosa-button:focus {
        border-color: #94a3b8;
        background: #f8fafc;
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
        background: #ffffff;
    }

    .prognosa-summary__item {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        min-width: 0;
        padding: 0.75rem 0.9rem;
    }

    .prognosa-summary__item > div {
        min-width: 0;
    }

    .prognosa-summary dt {
        overflow-wrap: anywhere;
    }

    .prognosa-summary__item + .prognosa-summary__item {
        border-left: 1px solid var(--prognosa-border);
    }

    .prognosa-summary__icon {
        width: 34px;
        height: 34px;
        font-size: 0.76rem;
    }

    .prognosa-summary__icon--slate {
        border-color: #d3dce6;
        background: #f1f5f9;
        color: #475569;
    }

    .prognosa-summary__icon--amber {
        border-color: #f2d7a8;
        background: #fff9ed;
        color: #9a5b0b;
    }

    .prognosa-summary__icon--red {
        border-color: #efc7c7;
        background: #fff5f5;
        color: #b23b3b;
    }

    .prognosa-summary dd {
        margin: 0;
        color: var(--prognosa-ink);
        font-size: 0.94rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        line-height: 1.25;
        overflow-wrap: anywhere;
    }

    .prognosa-report-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem 1rem;
        padding: 0.68rem 0.9rem;
        border-bottom: 1px solid var(--prognosa-border);
        background: #ffffff;
    }

    .prognosa-report-title {
        min-width: 0;
    }

    .prognosa-report-title strong {
        display: block;
        color: var(--prognosa-ink);
        font-size: 0.82rem;
        font-weight: 800;
        line-height: 1.3;
    }

    .prognosa-view-controls {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.45rem;
        min-width: 0;
    }

    .prognosa-segmented {
        display: inline-flex;
        align-items: center;
        min-width: 0;
        padding: 2px;
        border: 1px solid var(--prognosa-border);
        border-radius: 6px;
        background: #f1f5f9;
    }

    .prognosa-segmented button {
        min-height: 30px;
        padding: 0.34rem 0.58rem;
        border: 0;
        border-radius: 4px;
        background: transparent;
        color: #526174;
        font-size: 0.68rem;
        font-weight: 750;
        line-height: 1.15;
        white-space: nowrap;
    }

    .prognosa-segmented button:hover,
    .prognosa-segmented button:focus {
        color: var(--prognosa-ink);
        outline: 0;
    }

    .prognosa-segmented button.is-active {
        background: #ffffff;
        color: var(--prognosa-primary);
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
    }

    .prognosa-week-trigger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        margin-left: 0.25rem;
        padding: 0;
        border: 1px solid rgba(255, 255, 255, 0.44);
        border-radius: 5px;
        background: rgba(255, 255, 255, 0.12);
        color: inherit;
        cursor: pointer;
        vertical-align: middle;
    }

    .prognosa-week-trigger:hover,
    .prognosa-week-trigger:focus-visible {
        border-color: #ffffff;
        background: rgba(255, 255, 255, 0.25);
        outline: 2px solid #ffffff;
        outline-offset: 1px;
    }

    .prognosa-week-surface {
        cursor: default;
        user-select: none;
    }

    .prognosa-week-modal[hidden] {
        display: none;
    }

    .prognosa-week-modal {
        position: fixed;
        inset: 0;
        z-index: 1100;
        display: grid;
        place-items: center;
        padding: 1rem;
        background: rgba(15, 23, 42, 0.58);
    }

    .prognosa-week-dialog {
        width: min(100%, 410px);
        overflow: hidden;
        border: 1px solid var(--prognosa-border);
        border-radius: 10px;
        background: #ffffff;
        box-shadow: 0 24px 70px rgba(15, 23, 42, 0.3);
    }

    .prognosa-week-dialog__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.85rem 0.95rem;
        border-bottom: 1px solid var(--prognosa-border);
    }

    .prognosa-week-dialog__header h2 {
        margin: 0;
        color: var(--prognosa-ink);
        font-size: 0.98rem;
        font-weight: 800;
    }

    .prognosa-week-dialog__close {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        padding: 0;
        border: 1px solid var(--prognosa-border);
        border-radius: 6px;
        background: #ffffff;
        color: #475569;
        cursor: pointer;
    }

    .prognosa-week-dialog__close:hover,
    .prognosa-week-dialog__close:focus-visible {
        border-color: var(--prognosa-primary);
        color: var(--prognosa-primary);
        outline: 3px solid rgba(11, 92, 171, 0.14);
    }

    .prognosa-week-options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.6rem;
        margin: 0;
        padding: 0.95rem;
    }

    .prognosa-week-option {
        min-height: 52px;
        padding: 0.65rem;
        border: 1px solid var(--prognosa-border-strong);
        border-radius: 7px;
        background: #ffffff;
        color: #334155;
        cursor: pointer;
        font-size: 0.82rem;
        font-weight: 800;
    }

    .prognosa-week-option:hover,
    .prognosa-week-option:focus-visible {
        border-color: var(--prognosa-primary);
        background: #f0f7fc;
        color: var(--prognosa-primary);
        outline: 3px solid rgba(11, 92, 171, 0.14);
    }

    .prognosa-week-option.is-active {
        border-color: var(--prognosa-primary);
        background: var(--prognosa-primary);
        color: #ffffff;
    }

    body.prognosa-modal-open {
        overflow: hidden;
    }

    .prognosa-table-wrap {
        position: relative;
        height: clamp(430px, calc(100dvh - 280px), 760px);
        min-height: 430px;
        overflow: auto;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
        background: #ffffff;
        isolation: isolate;
    }

    .prognosa-table {
        width: 100%;
        min-width: 1108px;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
        table-layout: fixed;
        color: var(--prognosa-ink);
        font-variant-numeric: tabular-nums;
    }

    .prognosa-table [hidden] {
        display: none !important;
    }

    .prognosa-table th,
    .prognosa-table td {
        height: 36px;
        padding: 0.44rem 0.55rem;
        border-right: 1px solid #e1e8ef;
        border-bottom: 1px solid #e5ebf1;
        text-align: right;
        white-space: nowrap;
    }

    .prognosa-table th:not(.prognosa-col-no):not(.prognosa-col-indicator),
    .prognosa-table td:not(.prognosa-col-no):not(.prognosa-col-indicator) {
        width: 96px;
        min-width: 96px;
    }

    .prognosa-table thead th {
        position: sticky;
        z-index: 20;
        border-color: #cfd9e4;
        color: #ffffff;
        font-size: 0.64rem;
        font-weight: 800;
        letter-spacing: 0;
        line-height: 1.2;
        text-align: center;
        text-transform: uppercase;
    }

    .prognosa-table thead tr:first-child th {
        top: 0;
        height: 34px;
    }

    .prognosa-table thead tr:nth-child(2) th {
        top: 34px;
        height: 48px;
        color: #334155;
        background: #edf2f7;
    }

    .prognosa-column-label,
    .prognosa-column-detail {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .prognosa-column-label {
        font-weight: 800;
    }

    .prognosa-column-detail {
        margin-top: 0.16rem;
        font-size: 0.58rem;
        font-weight: 650;
        line-height: 1.15;
        opacity: 0.82;
        text-transform: none;
    }

    .prognosa-table thead th[data-group="identity"] {
        background: #12375f;
    }

    .prognosa-table thead th[data-group="position"],
    .prognosa-table thead th[data-group="current"] {
        background: #155b9d;
    }

    .prognosa-table thead th[data-group="forecast"] {
        background: #a65b10;
    }

    .prognosa-table thead th[data-group="forecast"].prognosa-week-surface {
        cursor: pointer;
    }

    .prognosa-table thead th[data-group="delta"] {
        background: #176a67;
    }

    .prognosa-table thead th[data-group="rka"],
    .prognosa-table thead th[data-group="rka_current"],
    .prognosa-table thead th[data-group="rka_december"] {
        background: #35644a;
    }

    .prognosa-table thead th[data-group="runoff"] {
        background: #5b4b8a;
    }

    .prognosa-table thead th[data-group="gap"] {
        background: #8c4b4b;
    }

    .prognosa-table thead th[data-group="achievement"] {
        background: #4c5868;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="position"],
    .prognosa-table thead tr:nth-child(2) th[data-group="current"] {
        background: #e9f2fa;
        color: #174b78;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="forecast"] {
        background: #fff5df;
        color: #8a4a0b;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="delta"] {
        background: #e9f5f3;
        color: #145c59;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="rka"],
    .prognosa-table thead tr:nth-child(2) th[data-group="rka_current"],
    .prognosa-table thead tr:nth-child(2) th[data-group="rka_december"] {
        background: #edf5ef;
        color: #2e5a41;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="runoff"] {
        background: #f1eef9;
        color: #51427d;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="gap"] {
        background: #fbefef;
        color: #7c3f3f;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="achievement"] {
        background: #eef1f4;
        color: #3f4a58;
    }

    .prognosa-table tbody td {
        background: #ffffff;
        color: #334155;
        font-size: 0.76rem;
        line-height: 1.25;
    }

    .prognosa-table tbody td[data-group="position"],
    .prognosa-table tbody td[data-group="current"] {
        background: #fbfdff;
    }

    .prognosa-table thead th[data-column-index="8"] {
        background: #dcecf9 !important;
        color: #0b477b !important;
    }

    .prognosa-table tbody td[data-column-index="8"] {
        background: #f0f7fc;
        color: #0b5cab;
        font-weight: 800;
    }

    .prognosa-table tbody td[data-group="forecast"] {
        background: #fffdf8;
    }

    .prognosa-table tbody td[data-group="delta"] {
        background: #f8fcfb;
    }

    .prognosa-table tbody td[data-group="rka"],
    .prognosa-table tbody td[data-group="rka_current"],
    .prognosa-table tbody td[data-group="rka_december"] {
        background: #fbfdfb;
    }

    .prognosa-table tbody td[data-group="runoff"] {
        background: #fcfbff;
    }

    .prognosa-table tbody td[data-group="gap"] {
        background: #fffafa;
    }

    .prognosa-table tbody td[data-group="achievement"] {
        background: #fafbfc;
    }

    .prognosa-table tbody tr:hover td {
        background: #edf5fc !important;
    }

    .prognosa-col-no,
    .prognosa-col-indicator {
        position: sticky !important;
        text-align: left !important;
    }

    .prognosa-col-no {
        left: 0;
        width: var(--prognosa-no-width) !important;
        min-width: var(--prognosa-no-width) !important;
        max-width: var(--prognosa-no-width) !important;
        text-align: center !important;
    }

    .prognosa-col-indicator {
        left: var(--prognosa-no-width);
        width: var(--prognosa-indicator-width) !important;
        min-width: var(--prognosa-indicator-width) !important;
        max-width: var(--prognosa-indicator-width) !important;
        white-space: normal !important;
        box-shadow: 6px 0 12px -12px rgba(15, 23, 42, 0.7);
    }

    .prognosa-table thead .prognosa-col-no,
    .prognosa-table thead .prognosa-col-indicator,
    .prognosa-table thead .prognosa-group-identity {
        z-index: 45;
    }

    .prognosa-table thead .prognosa-group-identity {
        position: sticky;
        left: 0;
    }

    .prognosa-table tbody .prognosa-col-no,
    .prognosa-table tbody .prognosa-col-indicator {
        z-index: 8;
        background: #ffffff;
    }

    .prognosa-table .prognosa-group-start {
        border-left: 2px solid #aebdcd;
    }

    .prognosa-row--section td {
        border-top: 1px solid #aac4dd;
        border-bottom-color: #c7d8e8;
        background: #e8f1fa !important;
        color: #173e65;
        font-weight: 800;
    }

    .prognosa-row--section .prognosa-col-no,
    .prognosa-row--section .prognosa-col-indicator {
        background: #dae9f7 !important;
    }

    .prognosa-row--category td {
        background: #f3f6f9 !important;
        color: #24364f;
        font-weight: 800;
    }

    .prognosa-row--category .prognosa-col-no,
    .prognosa-row--category .prognosa-col-indicator {
        background: #e9eef4 !important;
    }

    .prognosa-row--subtotal td {
        border-top: 1px solid #ced9e4;
        background: #f7f9fb !important;
        font-weight: 750;
    }

    .prognosa-row--metric td {
        border-top: 1px solid #ead7b2;
        background: #fff9ed !important;
        color: #694314;
        font-weight: 800;
    }

    .prognosa-row--detail .prognosa-col-indicator span {
        display: block;
        padding-left: 0.65rem;
    }

    .prognosa-value--negative {
        color: #b4232f !important;
        font-weight: 700;
    }

    .prognosa-value--good {
        color: #087a55 !important;
        font-weight: 800;
    }

    .prognosa-value--bad {
        color: #b4232f !important;
        font-weight: 800;
    }

    .prognosa-value--neutral {
        color: #64748b !important;
        font-weight: 700;
    }

    .prognosa-value--percent {
        font-weight: 750;
    }

    .prognosa-empty-value {
        color: #9aa8b8 !important;
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
        width: 42px;
        height: 42px;
    }

    .prognosa-state strong {
        display: block;
        margin-bottom: 0.16rem;
        color: var(--prognosa-ink);
        font-size: 0.86rem;
    }

    .prognosa-state span {
        display: block;
        max-width: 520px;
        font-size: 0.76rem;
        line-height: 1.45;
    }

    .prognosa-state--error .prognosa-state__icon {
        border-color: #efc7c7;
        background: #fff5f5;
        color: #b23b3b;
    }

    .prognosa-table-wrap::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }

    .prognosa-table-wrap::-webkit-scrollbar-track {
        background: #eef2f6;
    }

    .prognosa-table-wrap::-webkit-scrollbar-thumb {
        border: 2px solid #eef2f6;
        border-radius: 6px;
        background: #9eacbc;
    }

    @media (max-width: 1199.98px) {
        .prognosa-report-bar {
            align-items: flex-start;
            flex-direction: column;
        }

        .prognosa-view-controls {
            justify-content: flex-start;
            width: 100%;
            overflow-x: auto;
            padding-bottom: 0.15rem;
        }
    }

    @media (max-width: 991.98px) {
        .prognosa-page {
            --prognosa-indicator-width: 190px;
        }

        .prognosa-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        .prognosa-selector,
        .prognosa-field {
            width: 100%;
        }

        .prognosa-actions {
            justify-content: flex-start;
        }

        .prognosa-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .prognosa-summary__item:nth-child(3) {
            border-top: 1px solid var(--prognosa-border);
            border-left: 0;
        }

        .prognosa-summary__item:nth-child(4) {
            border-top: 1px solid var(--prognosa-border);
        }

        .prognosa-table-wrap {
            height: clamp(420px, calc(100dvh - 355px), 700px);
        }
    }

    @media (max-width: 767.98px) {
        .prognosa-page {
            --prognosa-no-width: 38px;
            --prognosa-indicator-width: 166px;
            padding-top: 0.55rem;
        }

        .prognosa-page-header {
            display: block;
        }

        .prognosa-heading h1 {
            font-size: 1.14rem;
        }

        .prognosa-position {
            margin-top: 0.6rem;
            padding: 0.58rem 0 0;
            border-top: 1px solid var(--prognosa-border);
            border-left: 0;
        }

        .prognosa-selector {
            align-items: stretch;
            flex-direction: column;
        }

        .prognosa-lock {
            min-height: 0;
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

        .prognosa-summary__item {
            padding: 0.65rem 0.7rem;
        }

        .prognosa-summary__icon {
            width: 30px;
            height: 30px;
        }

        .prognosa-summary dd {
            font-size: 0.82rem;
        }

        .prognosa-report-bar {
            padding: 0.65rem 0.7rem;
        }

        .prognosa-view-controls {
            align-items: flex-start;
            flex-direction: column;
        }

        .prognosa-segmented {
            max-width: 100%;
            overflow-x: auto;
        }

        .prognosa-table-wrap {
            height: clamp(390px, calc(100dvh - 405px), 660px);
            min-height: 390px;
        }

        .prognosa-table th:not(.prognosa-col-no):not(.prognosa-col-indicator),
        .prognosa-table td:not(.prognosa-col-no):not(.prognosa-col-indicator) {
            width: 96px;
            min-width: 96px;
        }

        .prognosa-table tbody td {
            font-size: 0.72rem;
        }
    }

    @media (max-width: 420px) {
        .prognosa-page {
            --prognosa-indicator-width: 154px;
        }

        .prognosa-toolbar {
            padding: 0.7rem;
        }

        .prognosa-actions {
            gap: 0.36rem;
        }

        .prognosa-button {
            padding: 0.44rem 0.38rem;
            font-size: 0.68rem;
        }

        .prognosa-summary__item {
            gap: 0.45rem;
        }

        .prognosa-segmented button {
            padding-right: 0.48rem;
            padding-left: 0.48rem;
            font-size: 0.64rem;
        }

        .prognosa-week-modal {
            align-items: end;
            padding: 0.6rem;
        }

        .prognosa-week-dialog {
            width: 100%;
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
            <h1>Weekly Prognosa</h1>
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

    <section class="prognosa-workspace" aria-labelledby="prognosa-report-title">
        <div class="prognosa-toolbar">
            <form class="prognosa-selector" method="GET" action="{{ route('prognosa.weekly') }}">
                <input type="hidden" name="week" value="{{ $selectedWeek }}">
                <div class="prognosa-field">
                    <label for="prognosa-sheet">Wilayah Laporan</label>
                    <div class="prognosa-select-shell">
                        <i class="fas fa-building prognosa-select-icon" aria-hidden="true"></i>
                        <select id="prognosa-sheet"
                                name="sheet"
                                aria-label="Pilih wilayah laporan"
                                @disabled($isLocked)
                                @if(!$isLocked) onchange="this.form.submit()" @endif>
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
                   href="{{ route('prognosa.weekly', ['sheet' => $selectedSheetKey, 'week' => $selectedWeek, 'refresh' => 1]) }}">
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

        @if(!$error && !empty($highlights))
            <dl class="prognosa-summary">
                @foreach($highlights as $highlight)
                    <div class="prognosa-summary__item">
                        <span class="prognosa-summary__icon prognosa-summary__icon--{{ $highlight['tone'] }}" aria-hidden="true">
                            <i class="fas {{ $highlight['icon'] }}"></i>
                        </span>
                        <div>
                            <dt>{{ $highlight['label'] }}</dt>
                            <dd>{{ $highlight['value'] }}</dd>
                        </div>
                    </div>
                @endforeach
            </dl>
        @endif

        <div class="prognosa-report-bar">
            <div class="prognosa-report-title">
                <strong id="prognosa-report-title">Detail {{ $selectedSheetLabel }}</strong>
            </div>

            @if(!$error && $rows !== [] && $headerColumns !== [])
                <div class="prognosa-view-controls">
                    <div class="prognosa-segmented" role="group" aria-label="Bagian laporan">
                        @foreach($reportSections as $key => $label)
                            <button type="button"
                                    data-report-section="{{ $key }}"
                                    class="{{ $key === 'all' ? 'is-active' : '' }}"
                                    aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                    <div class="prognosa-segmented" role="group" aria-label="Kelompok kolom">
                        @foreach($columnModes as $key => $label)
                            <button type="button"
                                    data-column-mode="{{ $key }}"
                                    class="{{ $key === 'all' ? 'is-active' : '' }}"
                                    aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        @if($error)
            <div class="prognosa-state prognosa-state--error" role="alert">
                <span class="prognosa-state__icon" aria-hidden="true"><i class="fas fa-exclamation-triangle"></i></span>
                <div>
                    <strong>Data belum dapat dimuat</strong>
                    <span>{{ $error }}</span>
                </div>
            </div>
        @elseif($rows === [] || $headerColumns === [])
            <div class="prognosa-state">
                <span class="prognosa-state__icon" aria-hidden="true"><i class="fas fa-table"></i></span>
                <div>
                    <strong>Belum ada data</strong>
                    <span>Data pada wilayah ini belum tersedia.</span>
                </div>
            </div>
        @else
            <div class="prognosa-table-wrap"
                 data-view-mode="all"
                 data-report-section="all"
                 tabindex="0"
                 aria-label="Tabel Weekly Prognosa {{ $selectedSheetLabel }}">
                <table class="prognosa-table">
                    <caption class="sr-only">Weekly Prognosa {{ $selectedSheetLabel }}</caption>
                    <colgroup>
                        @foreach($headerColumns as $column)
                            <col data-column-index="{{ $column['index'] }}" data-group="{{ $column['group'] }}" data-summary="{{ $column['summary'] ? '1' : '0' }}">
                        @endforeach
                    </colgroup>
                    <thead>
                        <tr>
                            @foreach($headerGroups as $group)
                                <th scope="colgroup"
                                    colspan="{{ $group['colspan'] }}"
                                    data-group-header="{{ $group['key'] }}"
                                    data-original-colspan="{{ $group['colspan'] }}"
                                    data-group="{{ $group['key'] }}"
                                    @if($group['key'] === 'forecast')
                                        data-week-selector-surface
                                        title="Klik dua kali untuk mengganti week"
                                    @endif
                                    class="{{ $group['key'] === 'identity' ? 'prognosa-group-identity' : '' }} {{ $group['key'] === 'forecast' ? 'prognosa-week-surface' : '' }} {{ in_array($group['start'], $groupStartColumns, true) ? 'prognosa-group-start' : '' }}">
                                    <span>{{ $group['label'] }}</span>
                                    @if($group['key'] === 'forecast')
                                        <button type="button"
                                                class="prognosa-week-trigger"
                                                data-week-selector-trigger
                                                aria-haspopup="dialog"
                                                aria-controls="prognosa-week-modal"
                                                aria-label="Pilih week prognosa">
                                            <i class="fas fa-pen" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach($headerColumns as $column)
                                @php
                                    $columnIndex = (int) $column['index'];
                                    $classes = [
                                        $columnIndex === 0 ? 'prognosa-col-no' : '',
                                        $columnIndex === 1 ? 'prognosa-col-indicator' : '',
                                        in_array($columnIndex, $groupStartColumns, true) ? 'prognosa-group-start' : '',
                                    ];
                                @endphp
                                <th scope="col"
                                    data-column-index="{{ $columnIndex }}"
                                    data-group="{{ $column['group'] }}"
                                    data-summary="{{ $column['summary'] ? '1' : '0' }}"
                                    @if($column['group'] === 'forecast')
                                        data-week-selector-surface
                                        title="Klik dua kali untuk mengganti week"
                                    @endif
                                    class="{{ implode(' ', array_filter([...$classes, $column['group'] === 'forecast' ? 'prognosa-week-surface' : ''])) }}">
                                    <span class="prognosa-column-label">{{ $column['label'] }}</span>
                                    @if(!empty($column['detail']))
                                        <small class="prognosa-column-detail">{{ $column['detail'] }}</small>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr class="prognosa-row--{{ $row['type'] }}" data-row-section="{{ $row['section'] }}">
                                @foreach($headerColumns as $column)
                                    @php
                                        $columnIndex = (int) $column['index'];
                                        $cell = $row['cells'][$columnIndex] ?? ['value' => '', 'negative' => false, 'percent' => false, 'tone' => ''];
                                        $value = trim((string) ($cell['value'] ?? ''));
                                        $tone = trim((string) ($cell['tone'] ?? ''));
                                        $classes = [
                                            $columnIndex === 0 ? 'prognosa-col-no' : '',
                                            $columnIndex === 1 ? 'prognosa-col-indicator' : '',
                                            in_array($columnIndex, $groupStartColumns, true) ? 'prognosa-group-start' : '',
                                            !empty($cell['negative']) ? 'prognosa-value--negative' : '',
                                            !empty($cell['percent']) ? 'prognosa-value--percent' : '',
                                            $tone !== '' ? 'prognosa-value--' . $tone : '',
                                            $value === '' ? 'prognosa-empty-value' : '',
                                        ];
                                    @endphp
                                    <td data-column-index="{{ $columnIndex }}"
                                        data-group="{{ $column['group'] }}"
                                        data-summary="{{ $column['summary'] ? '1' : '0' }}"
                                        class="{{ implode(' ', array_filter($classes)) }}"
                                        @if($value !== '') title="{{ $value }}" @endif>
                                        @if($columnIndex === 1)
                                            <span>{{ $value === '' ? '-' : $value }}</span>
                                        @else
                                            {{ $value === '' ? '-' : $value }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if(!$error && $rows !== [] && $headerColumns !== [])
        <div id="prognosa-week-modal"
             class="prognosa-week-modal"
             role="dialog"
             aria-modal="true"
             aria-labelledby="prognosa-week-modal-title"
             hidden>
            <div class="prognosa-week-dialog">
                <div class="prognosa-week-dialog__header">
                    <h2 id="prognosa-week-modal-title">Pilih Week Prognosa</h2>
                    <button type="button"
                            class="prognosa-week-dialog__close"
                            data-week-selector-close
                            aria-label="Tutup pilihan week">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>
                <form class="prognosa-week-options" method="GET" action="{{ route('prognosa.weekly') }}">
                    <input type="hidden" name="sheet" value="{{ $selectedSheetKey }}">
                    @foreach(range(1, 4) as $week)
                        <button type="submit"
                                name="week"
                                value="{{ $week }}"
                                class="prognosa-week-option {{ $selectedWeek === $week ? 'is-active' : '' }}"
                                aria-current="{{ $selectedWeek === $week ? 'true' : 'false' }}">
                            Week {{ $week }}
                        </button>
                    @endforeach
                </form>
            </div>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tableWrap = document.querySelector('.prognosa-table-wrap');
        const table = tableWrap ? tableWrap.querySelector('.prognosa-table') : null;

        if (!tableWrap || !table) {
            return;
        }

        const columnHeaders = Array.from(table.querySelectorAll('thead tr:nth-child(2) [data-column-index]'));
        const columnElements = Array.from(table.querySelectorAll('[data-column-index]'));
        const groupHeaders = Array.from(table.querySelectorAll('[data-group-header]'));
        const modeButtons = Array.from(document.querySelectorAll('[data-column-mode]'));
        const sectionButtons = Array.from(document.querySelectorAll('[data-report-section]'));
        const reportRows = Array.from(table.querySelectorAll('tbody [data-row-section]'));
        const weekModal = document.getElementById('prognosa-week-modal');
        const weekTriggers = Array.from(document.querySelectorAll('[data-week-selector-trigger]'));
        const weekSurfaces = Array.from(document.querySelectorAll('[data-week-selector-surface]'));
        const weekClose = weekModal ? weekModal.querySelector('[data-week-selector-close]') : null;
        let weekTriggerSource = null;

        function openWeekModal(source) {
            if (!weekModal) {
                return;
            }

            weekTriggerSource = source || document.activeElement;
            weekModal.hidden = false;
            document.body.classList.add('prognosa-modal-open');
            const activeOption = weekModal.querySelector('.prognosa-week-option.is-active');
            const firstFocusable = activeOption || weekClose;
            if (firstFocusable) {
                window.requestAnimationFrame(function () {
                    firstFocusable.focus();
                });
            }
        }

        function closeWeekModal() {
            if (!weekModal || weekModal.hidden) {
                return;
            }

            weekModal.hidden = true;
            document.body.classList.remove('prognosa-modal-open');
            if (weekTriggerSource && typeof weekTriggerSource.focus === 'function') {
                weekTriggerSource.focus();
            }
        }

        weekTriggers.forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.stopPropagation();
                openWeekModal(trigger);
            });
        });

        weekSurfaces.forEach(function (surface) {
            surface.addEventListener('dblclick', function () {
                openWeekModal(surface.querySelector('[data-week-selector-trigger]') || surface);
            });
        });

        if (weekClose) {
            weekClose.addEventListener('click', closeWeekModal);
        }

        if (weekModal) {
            weekModal.addEventListener('click', function (event) {
                if (event.target === weekModal) {
                    closeWeekModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (weekModal.hidden) {
                    return;
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeWeekModal();
                    return;
                }

                if (event.key !== 'Tab') {
                    return;
                }

                const focusable = Array.from(weekModal.querySelectorAll('button:not([disabled])'));
                if (focusable.length === 0) {
                    return;
                }
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });
        }

        function columnIsVisible(column, mode) {
            const group = column.dataset.group || 'identity';
            if (mode === 'all') {
                return true;
            }
            if (mode === 'position') {
                return ['identity', 'position'].includes(group);
            }
            if (mode === 'forecast') {
                return group === 'identity' || group === 'forecast' || group === 'delta'
                    || column.dataset.columnIndex === '8';
            }
            if (mode === 'rka') {
                return group === 'identity' || group === 'rka_current' || group === 'rka_december'
                    || column.dataset.columnIndex === '8';
            }
            if (mode === 'runoff') {
                return group === 'identity' || group === 'runoff'
                    || column.dataset.columnIndex === '8';
            }

            return true;
        }

        function applyColumnMode(mode) {
            const visibleColumns = new Set();
            columnHeaders.forEach(function (column) {
                const index = column.dataset.columnIndex;
                const visible = columnIsVisible(column, mode);
                column.hidden = !visible;
                if (visible) {
                    visibleColumns.add(index);
                }
            });

            columnElements.forEach(function (element) {
                if (element.tagName === 'TH' && element.parentElement && element.parentElement.rowIndex === 1) {
                    return;
                }
                const visible = visibleColumns.has(element.dataset.columnIndex);
                element.hidden = !visible;
                if (element.tagName === 'COL') {
                    element.style.display = visible ? '' : 'none';
                }
            });

            groupHeaders.forEach(function (groupHeader) {
                const group = groupHeader.dataset.groupHeader;
                const visibleCount = columnHeaders.filter(function (column) {
                    return column.dataset.group === group && visibleColumns.has(column.dataset.columnIndex);
                }).length;
                groupHeader.hidden = visibleCount === 0;
                groupHeader.colSpan = Math.max(visibleCount, 1);
            });

            const numericColumns = Math.max(0, visibleColumns.size - 2);
            const isMobile = window.matchMedia('(max-width: 767.98px)').matches;
            const numericColumnWidth = 96;
            const stickyWidth = isMobile ? 192 : 245;
            const scrollViewportWidth = Math.max(0, tableWrap.clientWidth - 16);
            table.style.minWidth = Math.max(scrollViewportWidth, stickyWidth + (numericColumns * numericColumnWidth)) + 'px';
            tableWrap.dataset.viewMode = mode;

            modeButtons.forEach(function (button) {
                const active = button.dataset.columnMode === mode;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            try {
                sessionStorage.setItem('weekly-prognosa-column-mode', mode);
            } catch (error) {
            }
        }

        function applyReportSection(section) {
            reportRows.forEach(function (row) {
                row.hidden = section !== 'all' && row.dataset.rowSection !== section;
            });
            tableWrap.dataset.reportSection = section;

            sectionButtons.forEach(function (button) {
                const active = button.dataset.reportSection === section;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }

        modeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                applyColumnMode(button.dataset.columnMode || 'all');
            });
        });

        sectionButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                applyReportSection(button.dataset.reportSection || 'all');
            });
        });

        let initialMode = 'all';
        try {
            const storedMode = sessionStorage.getItem('weekly-prognosa-column-mode');
            if (['position', 'forecast', 'rka', 'runoff', 'all'].includes(storedMode)) {
                initialMode = storedMode;
            }
        } catch (error) {
        }

        applyColumnMode(initialMode);
        applyReportSection('all');

        let resizeFrame = null;
        window.addEventListener('resize', function () {
            if (resizeFrame !== null) {
                window.cancelAnimationFrame(resizeFrame);
            }
            resizeFrame = window.requestAnimationFrame(function () {
                applyColumnMode(tableWrap.dataset.viewMode || 'all');
                resizeFrame = null;
            });
        }, { passive: true });
    });
</script>
@endsection
