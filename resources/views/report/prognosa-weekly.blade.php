@extends('layouts.admin')

@section('title', 'Weekly Prognosa')

@php
    $selectedSheetLabel = (string) data_get($selectedSheet ?? [], 'label', 'Area 6');
    $availableWeeks = collect($availableForecastWeeks ?? [])
        ->map(static fn (mixed $week): int => (int) $week)
        ->filter(static fn (int $week): bool => $week >= 1 && $week <= 5)
        ->unique()
        ->sort()
        ->values()
        ->all();
    $availableWeeks = $availableWeeks !== [] ? $availableWeeks : [1];
    $selectedWeek = (int) ($activeForecastWeek ?? $availableWeeks[0]);
    if (!in_array($selectedWeek, $availableWeeks, true)) {
        $selectedWeek = $availableWeeks[0];
    }

    $periodQuery = $periodQuery ?? [];
    $periodOptions = $periodOptions ?? [];
    $customPeriods = $customPeriods ?? [];
    $periodHiddenInputs = static fn (array $except = []): array => array_diff_key($periodQuery, array_flip($except));
    $positionMin = $periodOptions !== [] ? (string) end($periodOptions)['value'] : '';
    $positionMax = $periodOptions !== [] ? (string) reset($periodOptions)['value'] : '';
    $exportQuery = array_merge(['sheet' => $selectedSheetKey, 'week' => $selectedWeek], $periodQuery);
    $groupStartColumns = array_values(array_filter(array_map(
        static fn (array $group): int => (int) ($group['start'] ?? 0),
        $headerGroups ?? []
    ), static fn (int $column): bool => $column > 0));
@endphp

@section('styles')
<style>
    .prognosa-page {
        --pw-canvas: #f4f7fb;
        --pw-surface: #ffffff;
        --pw-surface-soft: #f8fafd;
        --pw-surface-muted: #f1f5fa;
        --prognosa-border: #dde5ef;
        --prognosa-border-strong: #c7d4e3;
        --prognosa-ink: #0f233c;
        --prognosa-muted: #5b6e84;
        --prognosa-primary: #1d4f8a;
        --prognosa-primary-dark: #143c6b;
        --prognosa-primary-soft: #edf4fc;
        --prognosa-no-width: 66px;
        --prognosa-indicator-width: 224px;
        --pw-radius-lg: 14px;
        --pw-radius-md: 10px;
        --pw-radius-sm: 8px;
        --pw-shadow-shell: 0 1px 2px rgba(15, 35, 60, 0.04), 0 12px 32px -22px rgba(15, 35, 60, 0.24);
        --pw-shadow-pop: 0 4px 12px rgba(15, 35, 60, 0.08), 0 20px 44px -16px rgba(15, 35, 60, 0.22);
        --pw-ease: cubic-bezier(0.22, 1, 0.36, 1);
        width: 100%;
        min-width: 0;
        padding: 0.65rem 0 1.25rem;
        color: var(--prognosa-ink);
        -webkit-tap-highlight-color: rgba(29, 79, 138, 0.12);
    }

    .prognosa-page,
    .prognosa-page * {
        box-sizing: border-box;
    }

    .prognosa-page :is(summary, button, a, select, input):focus-visible {
        outline: 2.5px solid #2b6cb0;
        outline-offset: 2px;
    }

    /* ---------- Institutional Branded Header (Danantara | Title | BRI) ---------- */
    .prognosa-page-header {
        display: grid;
        grid-template-columns: minmax(120px, 1fr) auto minmax(120px, 1fr);
        align-items: center;
        gap: 1rem 1.5rem;
        margin-bottom: 0.85rem;
        padding: 0.85rem 1.4rem;
        border: 1px solid var(--prognosa-border);
        border-radius: var(--pw-radius-lg);
        background: var(--pw-surface);
        box-shadow: var(--pw-shadow-shell);
    }

    .prognosa-brand {
        display: flex;
        align-items: center;
        min-width: 0;
    }

    .prognosa-brand--left {
        justify-content: flex-start;
    }

    .prognosa-brand--right {
        justify-content: flex-end;
    }

    .prognosa-brand__logo {
        display: block;
        width: auto;
        max-width: 100%;
        object-fit: contain;
        user-select: none;
    }

    .prognosa-brand__logo--danantara {
        height: 38px;
    }

    .prognosa-brand__logo--bri {
        height: 38px;
    }

    .prognosa-heading {
        min-width: 0;
        text-align: center;
    }

    .prognosa-heading h1 {
        margin: 0;
        color: var(--prognosa-ink);
        font-size: clamp(1.06rem, 1.45vw, 1.32rem);
        font-weight: 800;
        letter-spacing: -0.02em;
        line-height: 1.25;
        text-wrap: balance;
    }

    /* ---------- Workspace Shell ---------- */
    .prognosa-workspace {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow: visible;
        border: 1px solid var(--prognosa-border);
        border-radius: var(--pw-radius-lg);
        background: var(--pw-surface);
        box-shadow: var(--pw-shadow-shell);
    }

    /* ---------- Command Toolbar & Custom Filter Drawer ---------- */
    .prognosa-toolbar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem 1rem;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid var(--prognosa-border);
        border-radius: var(--pw-radius-lg) var(--pw-radius-lg) 0 0;
        background: var(--pw-surface-soft);
    }

    .prognosa-filters {
        position: relative;
        flex: 1 1 auto;
        min-width: 0;
        border: 1px solid var(--prognosa-border);
        border-radius: var(--pw-radius-md);
        background: var(--pw-surface);
        box-shadow: 0 1px 2px rgba(15, 35, 60, 0.03);
        transition: border-color 180ms var(--pw-ease), box-shadow 180ms var(--pw-ease);
    }

    .prognosa-filters:hover {
        border-color: var(--prognosa-border-strong);
    }

    .prognosa-filters[open] {
        border-color: #aac3de;
        box-shadow: 0 6px 20px -14px rgba(15, 35, 60, 0.24);
    }

    .prognosa-filters > summary {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        min-height: 42px;
        padding: 0.5rem 0.75rem;
        color: var(--prognosa-ink);
        cursor: pointer;
        list-style: none;
        user-select: none;
        touch-action: manipulation;
    }

    .prognosa-filters > summary::-webkit-details-marker {
        display: none;
    }

    .prognosa-filters__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        flex: 0 0 auto;
        border-radius: 7px;
        background: var(--prognosa-primary-soft);
        color: var(--prognosa-primary);
        font-size: 0.74rem;
    }

    .prognosa-filters__label {
        flex: 0 0 auto;
        color: var(--prognosa-ink);
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: -0.01em;
    }

    .prognosa-filters__value {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.35rem;
        min-width: 0;
        overflow-wrap: anywhere;
        font-size: 0.74rem;
        color: var(--prognosa-muted);
    }

    .prognosa-filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.32rem;
        padding: 0.18rem 0.52rem;
        border: 1px solid #d9e4f0;
        border-radius: 999px;
        background: #f5f8fc;
        color: #294566;
        font-size: 0.71rem;
        font-weight: 650;
        line-height: 1.25;
        white-space: nowrap;
    }

    .prognosa-filter-chip--week {
        border-color: #e4d5be;
        background: #faf5ec;
        color: #7c4d19;
    }

    .prognosa-filters__chevron-wrap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        margin-left: auto;
        flex: 0 0 auto;
        border-radius: 999px;
        background: #f2f6fb;
        color: var(--prognosa-muted);
        transition: background-color 180ms var(--pw-ease), color 180ms var(--pw-ease);
    }

    .prognosa-filters > summary:hover .prognosa-filters__chevron-wrap {
        background: #e6eff9;
        color: var(--prognosa-primary);
    }

    .prognosa-filters__chevron {
        font-size: 0.64rem;
        transition: transform 220ms var(--pw-ease);
    }

    .prognosa-filters[open] .prognosa-filters__chevron {
        transform: rotate(180deg);
    }

    .prognosa-selector {
        display: flex;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 0.85rem 1rem;
        min-width: 0;
        margin: 0;
        padding: 0.9rem 0.95rem 0.95rem;
        border-top: 1px solid #e6edf5;
        background: #fbfcfe;
        border-radius: 0 0 var(--pw-radius-md) var(--pw-radius-md);
    }

    .prognosa-filters .prognosa-field {
        flex: 1 1 220px;
        width: auto;
    }

    .prognosa-filters .prognosa-field--date {
        flex: 1 1 235px;
    }

    .prognosa-filters .prognosa-field--weeks {
        flex: 1 1 215px;
    }

    .prognosa-field {
        position: relative;
        width: 260px;
        max-width: 100%;
    }

    .prognosa-field > label,
    .prognosa-field__label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.4rem;
        margin: 0 0 0.36rem;
        color: #42556b;
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .prognosa-field__badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.1rem 0.42rem;
        border-radius: 999px;
        background: #edf2f7;
        color: #55697e;
        font-size: 0.65rem;
        font-weight: 650;
    }

    /* Native controls kept accessible & synced with custom dropdown UI */
    .prognosa-native-select {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    /* ---------- Custom Combobox Dropdown (Wilayah & Snapshot Picker) ---------- */
    .pw-combobox {
        position: relative;
        width: 100%;
    }

    .pw-combobox__trigger {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        width: 100%;
        min-height: 40px;
        padding: 0.45rem 0.72rem;
        border: 1px solid var(--prognosa-border-strong);
        border-radius: var(--pw-radius-sm);
        background: var(--pw-surface);
        color: var(--prognosa-ink);
        cursor: pointer;
        font-size: 0.8rem;
        font-weight: 650;
        line-height: 1.25;
        text-align: left;
        box-shadow: 0 1px 2px rgba(15, 35, 60, 0.03);
        transition: border-color 160ms var(--pw-ease), box-shadow 160ms var(--pw-ease), background-color 160ms var(--pw-ease);
        touch-action: manipulation;
    }

    .pw-combobox__trigger:hover:not(:disabled) {
        border-color: #8fb1d6;
        background: #fdfefe;
    }

    .pw-combobox__trigger[aria-expanded="true"] {
        border-color: var(--prognosa-primary);
        box-shadow: 0 0 0 3px rgba(29, 79, 138, 0.12);
    }

    .pw-combobox__trigger:disabled {
        cursor: not-allowed;
        background: #f3f6fa;
        color: #5b6e84;
        border-color: #d9e2ec;
    }

    .pw-combobox__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        flex: 0 0 auto;
        border-radius: 6px;
        background: #eef4fb;
        color: var(--prognosa-primary);
        font-size: 0.72rem;
    }

    .pw-combobox__text {
        flex: 1 1 auto;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pw-combobox__caret {
        flex: 0 0 auto;
        color: #6b7f96;
        font-size: 0.64rem;
        transition: transform 200ms var(--pw-ease);
    }

    .pw-combobox__trigger[aria-expanded="true"] .pw-combobox__caret {
        transform: rotate(180deg);
        color: var(--prognosa-primary);
    }

    .pw-combobox__popover {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        z-index: 95;
        min-width: 230px;
        padding: 0.35rem;
        border: 1px solid #cbd7e6;
        border-radius: 10px;
        background: var(--pw-surface);
        box-shadow: var(--pw-shadow-pop);
        transform-origin: top center;
        animation: pwPopoverIn 150ms var(--pw-ease);
    }

    .pw-combobox__popover[hidden] {
        display: none;
    }

    @keyframes pwPopoverIn {
        from {
            opacity: 0;
            transform: translateY(-4px) scale(0.985);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .pw-combobox__search-wrap {
        position: relative;
        margin-bottom: 0.3rem;
        padding: 0.15rem;
    }

    .pw-combobox__search-wrap i {
        position: absolute;
        top: 50%;
        left: 0.7rem;
        transform: translateY(-50%);
        color: #7c90a6;
        font-size: 0.72rem;
        pointer-events: none;
    }

    .pw-combobox__search {
        width: 100%;
        height: 34px;
        padding: 0.3rem 0.6rem 0.3rem 1.85rem;
        border: 1px solid #d5e0ec;
        border-radius: 7px;
        background: #f8fafd;
        color: var(--prognosa-ink);
        font-size: 0.76rem;
        font-weight: 500;
    }

    .pw-combobox__search:focus {
        border-color: var(--prognosa-primary);
        background: #ffffff;
        outline: 0;
    }

    .pw-combobox__list {
        max-height: 280px;
        overflow-y: auto;
        margin: 0;
        padding: 0;
        list-style: none;
        overscroll-behavior: contain;
    }

    .pw-combobox__option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.55rem;
        width: 100%;
        min-height: 36px;
        padding: 0.38rem 0.6rem;
        border: 0;
        border-radius: 7px;
        background: transparent;
        color: #24374d;
        cursor: pointer;
        font-size: 0.78rem;
        font-weight: 600;
        text-align: left;
        transition: background-color 130ms ease, color 130ms ease;
    }

    .pw-combobox__option:hover,
    .pw-combobox__option.is-highlighted {
        background: #eef4fb;
        color: var(--prognosa-primary-dark);
    }

    .pw-combobox__option.is-selected {
        background: #e6f0fb;
        color: var(--prognosa-primary);
        font-weight: 700;
    }

    .pw-combobox__option-meta {
        display: block;
        color: #6b7f96;
        font-size: 0.67rem;
        font-weight: 500;
    }

    .pw-combobox__check {
        flex: 0 0 auto;
        color: var(--prognosa-primary);
        font-size: 0.72rem;
        opacity: 0;
    }

    .pw-combobox__option.is-selected .pw-combobox__check {
        opacity: 1;
    }

    .pw-combobox__empty {
        padding: 0.75rem 0.6rem;
        color: var(--prognosa-muted);
        font-size: 0.74rem;
        text-align: center;
    }

    /* ---------- Date Field Combo (Native Input + Snapshot Dropdown) ---------- */
    .pw-date-group {
        display: flex;
        align-items: stretch;
        gap: 0.4rem;
    }

    .prognosa-select-shell {
        position: relative;
        flex: 1 1 auto;
        min-width: 0;
    }

    .prognosa-select-icon {
        position: absolute;
        top: 50%;
        left: 0.68rem;
        z-index: 1;
        pointer-events: none;
        transform: translateY(-50%);
        color: var(--prognosa-primary);
        font-size: 0.76rem;
    }

    .prognosa-selector input[type="date"] {
        width: 100%;
        height: 40px;
        padding: 0.4rem 0.6rem 0.4rem 2rem;
        border: 1px solid var(--prognosa-border-strong);
        border-radius: var(--pw-radius-sm);
        background: var(--pw-surface);
        color: var(--prognosa-ink);
        font-size: 0.8rem;
        font-weight: 650;
        line-height: 1.2;
        box-shadow: 0 1px 2px rgba(15, 35, 60, 0.03);
        transition: border-color 160ms var(--pw-ease), box-shadow 160ms var(--pw-ease);
    }

    .prognosa-selector input[type="date"]:hover {
        border-color: #8fb1d6;
    }

    .prognosa-selector input[type="date"]:focus {
        border-color: var(--prognosa-primary);
        outline: 0;
        box-shadow: 0 0 0 3px rgba(29, 79, 138, 0.12);
    }

    .pw-snapshot-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.32rem;
        min-width: 40px;
        height: 40px;
        padding: 0 0.62rem;
        border: 1px solid var(--prognosa-border-strong);
        border-radius: var(--pw-radius-sm);
        background: var(--pw-surface);
        color: #36506e;
        cursor: pointer;
        font-size: 0.74rem;
        font-weight: 650;
        white-space: nowrap;
        transition: border-color 160ms var(--pw-ease), background-color 160ms var(--pw-ease), color 160ms var(--pw-ease);
        touch-action: manipulation;
    }

    .pw-snapshot-btn:hover {
        border-color: #8fb1d6;
        background: #f0f6fc;
        color: var(--prognosa-primary);
    }

    .pw-week-pills {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.32rem;
        min-height: 40px;
    }

    .pw-week-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 44px;
        height: 36px;
        padding: 0 0.62rem;
        border: 1px solid var(--prognosa-border-strong);
        border-radius: var(--pw-radius-sm);
        background: var(--pw-surface);
        color: #3a5068;
        font-size: 0.74rem;
        font-weight: 700;
        text-decoration: none;
        transition: border-color 150ms ease, background-color 150ms ease, color 150ms ease, transform 150ms ease;
    }

    .pw-week-pill:hover {
        border-color: #8fb1d6;
        background: #eef4fb;
        color: var(--prognosa-primary);
        text-decoration: none;
        transform: translateY(-1px);
    }

    .pw-week-pill.is-active {
        border-color: var(--prognosa-primary);
        background: var(--prognosa-primary);
        color: #ffffff;
        box-shadow: 0 4px 10px -6px rgba(20, 60, 107, 0.6);
    }

    .prognosa-lock {
        display: inline-flex;
        align-items: center;
        gap: 0.36rem;
        min-height: 36px;
        padding: 0.3rem 0.65rem;
        border: 1px solid #dbe4ee;
        border-radius: var(--pw-radius-sm);
        background: #f3f6fa;
        color: var(--prognosa-muted);
        font-size: 0.72rem;
        font-weight: 650;
        white-space: nowrap;
    }

    /* ---------- Redesigned Action Buttons (Excel, Export A4, Spreadsheet) ---------- */
    .prognosa-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 0.5rem;
        flex: 0 0 auto;
        min-width: 0;
    }

    .prognosa-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        min-height: 42px;
        padding: 0.42rem 0.82rem 0.42rem 0.5rem;
        border: 1px solid var(--prognosa-border-strong);
        border-radius: var(--pw-radius-md);
        background: var(--pw-surface);
        color: #22354c;
        cursor: pointer;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: -0.005em;
        line-height: 1.2;
        text-decoration: none;
        white-space: nowrap;
        overflow-wrap: anywhere;
        box-shadow: 0 1px 2px rgba(15, 35, 60, 0.04);
        transition: transform 180ms var(--pw-ease), box-shadow 180ms var(--pw-ease), border-color 180ms var(--pw-ease), background-color 180ms var(--pw-ease), color 180ms var(--pw-ease);
        touch-action: manipulation;
    }

    .prognosa-button__badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        flex: 0 0 auto;
        border-radius: 7px;
        background: #edf2f8;
        color: #36506e;
        font-size: 0.78rem;
        transition: transform 200ms var(--pw-ease), background-color 180ms var(--pw-ease), color 180ms var(--pw-ease);
    }

    .prognosa-button:hover,
    .prognosa-button:focus-visible {
        border-color: #9ab0c7;
        background: #f6f9fd;
        color: var(--prognosa-ink);
        text-decoration: none;
        transform: translateY(-1.5px);
        box-shadow: 0 8px 18px -12px rgba(15, 35, 60, 0.34);
    }

    .prognosa-button:active:not([disabled]) {
        transform: translateY(0) scale(0.985);
        box-shadow: 0 1px 2px rgba(15, 35, 60, 0.08);
    }

    .prognosa-button--excel {
        border-color: #bedfce;
        background: #f1faf5;
        color: #145c3f;
    }

    .prognosa-button--excel .prognosa-button__badge {
        background: #daf1e5;
        color: #156e4b;
    }

    .prognosa-button--excel:hover,
    .prognosa-button--excel:focus-visible {
        border-color: #7ec0a0;
        background: #e5f5ec;
        color: #0e4a32;
        box-shadow: 0 10px 20px -14px rgba(20, 92, 63, 0.45);
    }

    .prognosa-button--excel:hover .prognosa-button__badge {
        background: #cbead9;
        transform: scale(1.06);
    }

    .prognosa-button--a4 {
        border-color: #c0d6ee;
        background: #f0f6fd;
        color: #16497d;
    }

    .prognosa-button--a4 .prognosa-button__badge {
        background: #dbeafa;
        color: #185594;
    }

    .prognosa-button--a4:hover,
    .prognosa-button--a4:focus-visible {
        border-color: #82afde;
        background: #e4effb;
        color: #103a66;
        box-shadow: 0 10px 20px -14px rgba(22, 73, 125, 0.45);
    }

    .prognosa-button--a4:hover .prognosa-button__badge {
        background: #cde2f8;
        transform: scale(1.06);
    }

    .prognosa-button--sheet {
        border-color: #cfdbe8;
        background: #f7f9fc;
        color: #24384f;
    }

    .prognosa-button--sheet .prognosa-button__badge {
        background: #e8eef5;
        color: #36506e;
    }

    .prognosa-button--sheet:hover,
    .prognosa-button--sheet:focus-visible {
        border-color: #97aec7;
        background: #edf2f8;
        color: #10243d;
        box-shadow: 0 10px 20px -14px rgba(36, 56, 79, 0.4);
    }

    .prognosa-button--sheet:hover .prognosa-button__badge i {
        transform: translate(1.5px, -1.5px);
    }

    .prognosa-button--sheet .prognosa-button__badge i {
        transition: transform 200ms var(--pw-ease);
    }

    .prognosa-button--reset {
        padding: 0.42rem 0.78rem;
        border-color: #e4d4b7;
        background: #fcf8f0;
        color: #7c4d19;
    }

    .prognosa-button--reset:hover,
    .prognosa-button--reset:focus-visible {
        border-color: #cda96d;
        background: #f7eedc;
        color: #633c11;
    }

    .prognosa-button--primary {
        padding: 0.48rem 0.9rem;
        border-color: var(--prognosa-primary);
        background: var(--prognosa-primary);
        color: #ffffff;
    }

    .prognosa-button--primary:hover,
    .prognosa-button--primary:focus-visible {
        border-color: var(--prognosa-primary-dark);
        background: var(--prognosa-primary-dark);
        color: #ffffff;
    }

    .prognosa-button--plain {
        padding: 0.48rem 0.85rem;
    }

    .prognosa-button[disabled] {
        cursor: progress;
        opacity: 0.75;
        transform: none;
    }

    .prognosa-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.38rem;
        min-height: 36px;
        padding: 0.32rem 0.74rem;
        border: 1px solid #e6d1ae;
        border-radius: 999px;
        background: #fbf6ec;
        color: #7c4d19;
        cursor: pointer;
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1.2;
        white-space: nowrap;
        transition: border-color 160ms ease, background-color 160ms ease, transform 160ms ease;
    }

    .prognosa-pill[hidden] {
        display: none;
    }

    .prognosa-pill:hover,
    .prognosa-pill:focus-visible {
        border-color: #cba25e;
        background: #f6ebd5;
        transform: translateY(-1px);
    }

    /* ---------- Summary Highlights Strip ---------- */
    .prognosa-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin: 0;
        border-bottom: 1px solid var(--prognosa-border);
        background: var(--pw-surface);
    }

    .prognosa-summary__item {
        display: flex;
        align-items: center;
        gap: 0.72rem;
        min-width: 0;
        padding: 0.8rem 1rem;
    }

    .prognosa-summary__item > div {
        min-width: 0;
    }

    .prognosa-summary__item + .prognosa-summary__item {
        border-left: 1px solid #e7edf5;
    }

    .prognosa-summary__icon,
    .prognosa-state__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        border: 1px solid #d5e1ee;
        border-radius: 9px;
        background: #f3f7fc;
        color: var(--prognosa-primary);
    }

    .prognosa-summary__icon {
        width: 36px;
        height: 36px;
        font-size: 0.78rem;
    }

    .prognosa-summary__icon--slate {
        border-color: #d7e0ea;
        background: #f4f7fa;
        color: #465b73;
    }

    .prognosa-summary__icon--amber {
        border-color: #e9d9be;
        background: #fbf7ef;
        color: #8b5a1b;
    }

    .prognosa-summary__icon--red {
        border-color: #ebd0d4;
        background: #fcf4f5;
        color: #9f3542;
    }

    .prognosa-summary dt {
        display: block;
        margin: 0 0 0.16rem;
        color: var(--prognosa-muted);
        font-size: 0.68rem;
        font-weight: 700;
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    .prognosa-summary dd {
        margin: 0;
        color: var(--prognosa-ink);
        font-size: 0.96rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        line-height: 1.25;
        overflow-wrap: anywhere;
    }

    /* ---------- Report Sub-Header Bar + Quick Indicator Search ---------- */
    .prognosa-report-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.65rem 1rem;
        padding: 0.72rem 1rem;
        border-bottom: 1px solid var(--prognosa-border);
        background: var(--pw-surface);
    }

    .prognosa-report-title {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.55rem;
        min-width: 0;
    }

    .prognosa-report-title strong {
        display: inline-block;
        color: var(--prognosa-ink);
        font-size: 0.88rem;
        font-weight: 800;
        letter-spacing: -0.01em;
        line-height: 1.3;
    }

    .prognosa-unit-note {
        color: var(--prognosa-muted);
        font-size: 0.72rem;
        font-weight: 600;
    }

    .prognosa-view-controls {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 0.55rem;
        min-width: 0;
    }

    .pw-search {
        position: relative;
        width: 240px;
        max-width: 100%;
    }

    .pw-search__icon {
        position: absolute;
        top: 50%;
        left: 0.72rem;
        transform: translateY(-50%);
        color: #7c90a6;
        font-size: 0.72rem;
        pointer-events: none;
    }

    .pw-search__input {
        width: 100%;
        height: 34px;
        padding: 0.3rem 2.1rem 0.3rem 1.9rem;
        border: 1px solid #d5e0ec;
        border-radius: 999px;
        background: #f8fafd;
        color: var(--prognosa-ink);
        font-size: 0.76rem;
        font-weight: 500;
        transition: border-color 160ms var(--pw-ease), background-color 160ms var(--pw-ease), box-shadow 160ms var(--pw-ease);
    }

    .pw-search__input::placeholder {
        color: #8295aa;
    }

    .pw-search__input:hover {
        border-color: #b7cadf;
    }

    .pw-search__input:focus {
        border-color: var(--prognosa-primary);
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(29, 79, 138, 0.1);
        outline: 0;
    }

    .pw-search__clear {
        position: absolute;
        top: 50%;
        right: 0.4rem;
        transform: translateY(-50%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        padding: 0;
        border: 0;
        border-radius: 999px;
        background: #e8eef5;
        color: #465b73;
        cursor: pointer;
        font-size: 0.65rem;
    }

    .pw-search__clear[hidden] {
        display: none;
    }

    .pw-search__clear:hover {
        background: #d9e4f0;
        color: var(--prognosa-ink);
    }

    .pw-search__count {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--prognosa-primary);
        background: var(--prognosa-primary-soft);
        padding: 0.22rem 0.56rem;
        border-radius: 999px;
        white-space: nowrap;
    }

    .pw-search__count[hidden] {
        display: none;
    }

    .pw-hint-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.24rem 0.58rem;
        border: 1px solid #e4ebf3;
        border-radius: 999px;
        background: #f8fafd;
        color: #62768c;
        font-size: 0.69rem;
        font-weight: 600;
        white-space: nowrap;
    }

    /* ---------- Week Trigger & Modal ---------- */
    .prognosa-forecast-head {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.32rem;
        max-width: 100%;
        vertical-align: middle;
    }

    .prognosa-forecast-head__label {
        flex: 0 0 auto;
        white-space: nowrap;
    }

    .prognosa-week-trigger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 22px;
        height: 22px;
        margin-left: 0;
        padding: 0;
        border: 1px solid rgba(124, 77, 25, 0.28);
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.78);
        color: #7c4d19;
        cursor: pointer;
        font-size: 0.64rem;
        vertical-align: middle;
        transition: background-color 150ms ease, border-color 150ms ease, transform 150ms ease;
    }

    .prognosa-week-trigger:hover,
    .prognosa-week-trigger:focus-visible {
        border-color: #7c4d19;
        background: #ffffff;
        transform: scale(1.06);
        outline: 2px solid #7c4d19;
        outline-offset: 1px;
    }

    @media (pointer: coarse) {
        .prognosa-week-trigger {
            width: 44px;
            height: 44px;
            min-width: 44px;
            min-height: 44px;
        }

        .prognosa-table col[data-group="forecast"],
        .prognosa-table th[data-group="forecast"],
        .prognosa-table td[data-group="forecast"] {
            width: 156px;
            min-width: 156px;
        }
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
        z-index: 2000;
        display: grid;
        place-items: center;
        padding: 1rem;
        background: rgba(15, 35, 60, 0.48);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        overscroll-behavior: contain;
        overflow-y: auto;
    }

    .prognosa-week-dialog {
        width: min(100%, 450px);
        max-height: calc(100dvh - 2rem);
        overflow-y: auto;
        border: 1px solid var(--prognosa-border);
        border-radius: 16px;
        background: #ffffff;
        box-shadow: 0 28px 72px -18px rgba(15, 35, 60, 0.36);
        animation: pwPopoverIn 170ms var(--pw-ease);
    }

    .prognosa-week-dialog__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 1rem 1.15rem;
        border-bottom: 1px solid #e6edf5;
        background: #f7fafe;
    }

    .prognosa-week-dialog__header h2 {
        margin: 0;
        color: var(--prognosa-ink);
        font-size: 0.96rem;
        font-weight: 800;
        letter-spacing: -0.01em;
    }

    .prognosa-week-dialog__close {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        padding: 0;
        border: 1px solid #d5e0ec;
        border-radius: 8px;
        background: #ffffff;
        color: #5b6e84;
        cursor: pointer;
        transition: border-color 150ms ease, color 150ms ease, background-color 150ms ease;
    }

    .prognosa-week-dialog__close:hover,
    .prognosa-week-dialog__close:focus-visible {
        border-color: var(--prognosa-primary);
        background: #f0f6fc;
        color: var(--prognosa-primary);
    }

    .prognosa-week-options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.6rem;
        margin: 0;
        padding: 1rem 1.1rem;
    }

    .prognosa-week-option {
        min-height: 50px;
        padding: 0.65rem;
        border: 1px solid var(--prognosa-border-strong);
        border-radius: 10px;
        background: #ffffff;
        color: #2c4159;
        cursor: pointer;
        font-size: 0.84rem;
        font-weight: 750;
        transition: border-color 160ms ease, background-color 160ms ease, color 160ms ease, transform 160ms ease;
    }

    .prognosa-week-option:hover,
    .prognosa-week-option:focus-visible {
        border-color: var(--prognosa-primary);
        background: #eff5fc;
        color: var(--prognosa-primary);
        transform: translateY(-1px);
    }

    .prognosa-week-option.is-active {
        border-color: var(--prognosa-primary);
        background: var(--prognosa-primary);
        color: #ffffff;
        box-shadow: 0 8px 18px -12px rgba(20, 60, 107, 0.7);
    }

    body.prognosa-modal-open {
        overflow: hidden;
    }

    /* ---------- Calm Institutional Financial Table ---------- */
    .prognosa-table-wrap {
        position: relative;
        width: 100%;
        max-width: 100%;
        height: clamp(440px, calc(100dvh - 255px), 780px);
        min-height: 440px;
        overflow: auto;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
        border-radius: 0 0 var(--pw-radius-lg) var(--pw-radius-lg);
        background: var(--pw-surface);
        isolation: isolate;
    }

    .prognosa-table {
        width: 100%;
        min-width: 1140px;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
        table-layout: fixed;
        color: var(--prognosa-ink);
        font-variant-numeric: tabular-nums;
    }

    .prognosa-table col[data-column-index="0"] {
        width: var(--prognosa-no-width);
    }

    .prognosa-table col[data-column-index="1"] {
        width: var(--prognosa-indicator-width);
    }

    .prognosa-table col:not([data-column-index="0"]):not([data-column-index="1"]) {
        width: 108px;
    }

    .prognosa-table col[data-group="forecast"] {
        width: 136px;
    }

    .prognosa-table col[data-column-index="13"],
    .prognosa-table col[data-column-index="16"] {
        width: 118px;
    }

    .prognosa-table [hidden] {
        display: none !important;
    }

    .prognosa-table th,
    .prognosa-table td {
        height: 38px;
        padding: 0.46rem 0.62rem;
        border-right: 1px solid #e7edf4;
        border-bottom: 1px solid #eaeff5;
        text-align: right;
        white-space: nowrap;
        transition: background-color 110ms ease;
    }

    .prognosa-table th:not(.prognosa-col-no):not(.prognosa-col-indicator),
    .prognosa-table td:not(.prognosa-col-no):not(.prognosa-col-indicator) {
        width: 108px;
        min-width: 108px;
    }

    .prognosa-table th[data-group="forecast"],
    .prognosa-table td[data-group="forecast"] {
        width: 136px;
        min-width: 136px;
    }

    .prognosa-table th[data-column-index="13"],
    .prognosa-table td[data-column-index="13"],
    .prognosa-table th[data-column-index="16"],
    .prognosa-table td[data-column-index="16"] {
        width: 118px;
        min-width: 118px;
    }

    .prognosa-table thead th {
        position: sticky;
        z-index: 20;
        padding: 0.36rem 0.48rem;
        border-color: #d6e0eb;
        font-size: 0.66rem;
        font-weight: 750;
        letter-spacing: 0.02em;
        line-height: 1.2;
        text-align: center;
        text-transform: uppercase;
    }

    .prognosa-table thead th[data-group-header="forecast"],
    .content-wrapper .abah-table-managed.prognosa-table thead th[data-group-header="forecast"] {
        padding-right: 0.45rem;
        padding-left: 0.45rem;
        overflow: visible !important;
        text-overflow: clip !important;
    }

    .prognosa-table thead tr:first-child th {
        top: 0;
        height: 35px;
    }

    .prognosa-table thead tr:nth-child(2) th {
        top: var(--abah-table-head-top, 35px);
        height: 48px;
        color: #364b63;
        background: #f3f6fa;
        border-bottom: 1.5px solid #cbd7e4;
    }

    .prognosa-column-label,
    .prognosa-column-detail {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .prognosa-column-label {
        font-weight: 750;
    }

    .prognosa-column-detail {
        margin-top: 0.16rem;
        font-size: 0.61rem;
        font-weight: 600;
        line-height: 1.15;
        opacity: 0.78;
        text-transform: none;
        letter-spacing: 0;
    }

    /* Calm Group Header Palette (muted mineral tones + thin top accent rule) */
    .prognosa-table thead th[data-group="identity"] {
        background: #1d3552;
        color: #f4f7fb;
        border-color: #2b4668;
    }

    .prognosa-table thead th[data-group="position"],
    .prognosa-table thead th[data-group="current"] {
        background: #274263;
        color: #eef4fa;
        border-color: #365378;
    }

    .prognosa-table thead th[data-group="forecast"] {
        background: #f4eee4;
        color: #784b18;
        box-shadow: inset 0 2.5px 0 #b8813c;
    }

    .prognosa-table thead th[data-group="forecast"].prognosa-week-surface {
        cursor: pointer;
    }

    .prognosa-table thead th[data-group="delta"] {
        background: #e8f3ef;
        color: #185c4f;
        box-shadow: inset 0 2.5px 0 #358373;
    }

    .prognosa-table thead th[data-group="rka"],
    .prognosa-table thead th[data-group="rka_current"] {
        background: #e9f1f8;
        color: #1c4c7a;
        box-shadow: inset 0 2.5px 0 #4278ad;
    }

    .prognosa-table thead th[data-group="rka_december"] {
        background: #eceff8;
        color: #384675;
        box-shadow: inset 0 2.5px 0 #6473a6;
    }

    .prognosa-table thead th[data-group="runoff"] {
        background: #f0eef7;
        color: #4b4372;
        box-shadow: inset 0 2.5px 0 #766aa6;
    }

    .prognosa-table thead th[data-group="gap"] {
        background: #f7eded;
        color: #7c3f44;
        box-shadow: inset 0 2.5px 0 #a8646a;
    }

    .prognosa-table thead th[data-group="achievement"] {
        background: #eef1f5;
        color: #3e4d5e;
        box-shadow: inset 0 2.5px 0 #6e7f93;
    }

    /* Row 2 Subheaders: airy, calm, legible */
    .prognosa-table thead tr:nth-child(2) th[data-group="identity"] {
        background: #254061;
        color: #f4f7fb;
        border-color: #345276;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="position"],
    .prognosa-table thead tr:nth-child(2) th[data-group="current"] {
        background: #f1f5fa;
        color: #243e5c;
    }

    .prognosa-table thead tr:nth-child(2) th:is([data-group="identity"], [data-group="position"]) .prognosa-column-label {
        font-size: 0.72rem;
        letter-spacing: 0;
        text-transform: none;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="forecast"] {
        background: #faf6ef;
        color: #734818;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="delta"] {
        background: #f2f9f6;
        color: #185c4f;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="rka"],
    .prognosa-table thead tr:nth-child(2) th[data-group="rka_current"] {
        background: #f3f8fc;
        color: #1c4c7a;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="rka_december"] {
        background: #f5f6fb;
        color: #384675;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="runoff"] {
        background: #f7f6fb;
        color: #4b4372;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="gap"] {
        background: #fbf4f4;
        color: #7c3f44;
    }

    .prognosa-table thead tr:nth-child(2) th[data-group="achievement"] {
        background: #f4f6f9;
        color: #3e4d5e;
    }

    /* Active latest position column (column 8): calm spotlight */
    .prognosa-table thead tr:nth-child(2) th[data-column-index="8"] {
        background: #e1eefb !important;
        color: #124377 !important;
        border-bottom: 2px solid #2b6cb0 !important;
    }

    .prognosa-table thead tr:nth-child(2) th.is-col-hover {
        filter: brightness(0.96);
    }

    /* ---------- Table Body Cells ---------- */
    .prognosa-table tbody td {
        background: #ffffff;
        color: #2f4257;
        font-size: 0.78rem;
        font-weight: 500;
        line-height: 1.3;
    }

    .prognosa-table tbody td[data-group="position"],
    .prognosa-table tbody td[data-group="current"] {
        background: #fcfdff;
    }

    .prognosa-table tbody td[data-column-index="8"] {
        background: #f4f9fe;
        color: #124377;
        font-weight: 750;
    }

    .prognosa-table tbody td[data-group="forecast"] {
        background: #fefdfa;
        color: #3f3220;
    }

    .prognosa-table tbody td[data-group="delta"] {
        background: #f7fbf9;
        font-weight: 700;
    }

    .prognosa-table tbody td[data-group="rka"],
    .prognosa-table tbody td[data-group="rka_current"] {
        background: #f8fbfe;
    }

    .prognosa-table tbody td[data-group="rka_december"] {
        background: #fafafe;
    }

    .prognosa-table tbody td[data-group="runoff"] {
        background: #fcfbfe;
    }

    .prognosa-table tbody td[data-group="gap"] {
        background: #fefafa;
    }

    .prognosa-table tbody td[data-group="achievement"] {
        background: #fafbfd;
    }

    .prognosa-table tbody tr:hover td {
        background: #eef5fc !important;
    }

    .prognosa-table tbody tr.is-row-selected td {
        background: #e8f1fb !important;
    }

    .prognosa-table tbody tr.is-row-selected .prognosa-col-no {
        box-shadow: inset 3px 0 0 var(--prognosa-primary);
    }

    /* ---------- Sticky Left Identity Columns (No & Keterangan) ---------- */
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
        padding-right: 0.35rem !important;
        padding-left: 0.35rem !important;
        text-align: center !important;
    }

    .prognosa-col-indicator {
        left: var(--prognosa-no-width);
        width: var(--prognosa-indicator-width) !important;
        min-width: var(--prognosa-indicator-width) !important;
        max-width: var(--prognosa-indicator-width) !important;
        white-space: normal !important;
        transition: box-shadow 180ms ease, background-color 110ms ease;
    }

    .prognosa-table-wrap.is-scrolled-x .prognosa-col-indicator {
        box-shadow: 8px 0 16px -12px rgba(15, 35, 60, 0.36);
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
        border-left: 1.5px solid #cbd7e5;
    }

    /* ---------- Clean Hierarchical Row Styling (1 -> 1.A. -> Detail) ---------- */
    .prognosa-no-token {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-variant-numeric: tabular-nums;
        line-height: 1.15;
    }

    .prognosa-row--section td {
        border-top: 1.5px solid #c3d4e6;
        border-bottom-color: #d2dfec;
        background: #eaf1f9 !important;
        color: #0f2948;
        font-weight: 750;
    }

    .prognosa-row--section .prognosa-col-no,
    .prognosa-row--section .prognosa-col-indicator {
        background: #e2ecf7 !important;
    }

    .prognosa-row--section .prognosa-no-token {
        min-width: 24px;
        height: 22px;
        padding: 0 0.35rem;
        border-radius: 6px;
        background: #d1e2f4;
        color: #123a66;
        font-size: 0.72rem;
        font-weight: 800;
    }

    .prognosa-row--category td {
        border-top: 1px solid #dfe7f0;
        background: #f5f8fc !important;
        color: #1c3550;
        font-weight: 700;
    }

    .prognosa-row--category .prognosa-col-no,
    .prognosa-row--category .prognosa-col-indicator {
        background: #eff4fa !important;
    }

    .prognosa-row--category .prognosa-no-token {
        color: #365678;
        font-size: 0.72rem;
        font-weight: 750;
        letter-spacing: -0.01em;
    }

    .prognosa-row--subcategory td {
        border-top: 1px solid #e8eef5;
        background: #fafcfe !important;
        color: #223a54;
        font-weight: 650;
    }

    .prognosa-row--subcategory .prognosa-col-no,
    .prognosa-row--subcategory .prognosa-col-indicator {
        background: #f5f8fc !important;
    }

    .prognosa-row--subcategory .prognosa-no-token {
        color: #4b6584;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: -0.015em;
    }

    .prognosa-row--subcategory .prognosa-col-indicator span {
        display: block;
        padding-left: 0.35rem;
    }

    .prognosa-row--subtotal td {
        border-top: 1px dashed #c8d5e3;
        background: #f4f7fb !important;
        color: #1c3550;
        font-weight: 700;
    }

    .prognosa-row--subtotal .prognosa-col-no,
    .prognosa-row--subtotal .prognosa-col-indicator {
        background: #eef3f9 !important;
    }

    .prognosa-row--metric td {
        border-top: 1px solid #e6d8be;
        background: #faf6ee !important;
        color: #6a4518;
        font-weight: 750;
    }

    .prognosa-row--metric .prognosa-col-no,
    .prognosa-row--metric .prognosa-col-indicator {
        background: #f6efe2 !important;
    }

    .prognosa-row--detail .prognosa-col-indicator span {
        position: relative;
        display: block;
        padding-left: 1.05rem;
        color: #3d5168;
    }

    .prognosa-row--detail .prognosa-col-indicator span::before {
        content: "";
        position: absolute;
        top: 50%;
        left: 0.3rem;
        width: 5px;
        height: 1.5px;
        background: #c5d3e2;
        transform: translateY(-50%);
    }

    /* ---------- Calm Semantic Number Tones (APCA-balanced) ---------- */
    .prognosa-value--negative {
        color: #a62c3b !important;
        font-weight: 650;
    }

    .prognosa-value--good {
        color: #146348 !important;
        font-weight: 750;
    }

    .prognosa-value--bad {
        color: #a62c3b !important;
        font-weight: 750;
    }

    .prognosa-table tbody td:is([data-group="delta"], [data-group="rka_current"], [data-group="rka_december"]).prognosa-value--good {
        background: #eaf6f0 !important;
    }

    .prognosa-table tbody td:is([data-group="delta"], [data-group="rka_current"], [data-group="rka_december"]).prognosa-value--bad {
        background: #fdf0f2 !important;
    }

    .prognosa-value--neutral {
        color: #63768c !important;
        font-weight: 650;
    }

    .prognosa-value--percent {
        font-weight: 700;
    }

    .prognosa-empty-value {
        color: #a5b4c4 !important;
    }

    .prognosa-table thead tr:nth-child(2) th,
    .prognosa-table tbody td {
        -webkit-touch-callout: none;
    }

    .prognosa-table thead tr:nth-child(2) th.prognosa-period-col[data-custom-period="1"] {
        box-shadow: inset 0 -2.5px 0 #c78b36;
    }

    .prognosa-table thead tr:nth-child(2) th.is-menu-target,
    .prognosa-table tbody tr.is-menu-target td {
        outline: 2px solid rgba(29, 79, 138, 0.48);
        outline-offset: -2px;
    }

    /* ---------- Context Menus & Date Modal ---------- */
    .prognosa-menu {
        position: fixed;
        z-index: 1200;
        min-width: 242px;
        max-width: calc(100vw - 16px);
        padding: 0.35rem;
        border: 1px solid #cbd7e6;
        border-radius: 10px;
        background: #ffffff;
        box-shadow: var(--pw-shadow-pop);
    }

    .prognosa-menu[hidden] {
        display: none;
    }

    .prognosa-menu__title {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        margin: 0 0 0.28rem;
        padding: 0.42rem 0.58rem;
        border-bottom: 1px solid #e7edf5;
        color: var(--prognosa-muted);
        font-size: 0.69rem;
        font-weight: 750;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .prognosa-menu__title span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .prognosa-menu__item {
        display: flex;
        align-items: center;
        gap: 0.58rem;
        width: 100%;
        min-height: 38px;
        padding: 0.42rem 0.62rem;
        border: 0;
        border-radius: 7px;
        background: transparent;
        color: #293c52;
        cursor: pointer;
        font-size: 0.78rem;
        font-weight: 650;
        text-align: left;
        transition: background-color 120ms ease, color 120ms ease;
    }

    .prognosa-menu__item[hidden] {
        display: none;
    }

    .prognosa-menu__item:hover,
    .prognosa-menu__item:focus-visible {
        background: #eef4fb;
        color: var(--prognosa-primary);
        outline: 0;
    }

    .prognosa-menu__item i {
        width: 1rem;
        text-align: center;
    }

    .prognosa-menu__item--success {
        color: #146348;
    }

    .prognosa-date-body {
        display: grid;
        gap: 0.78rem;
        padding: 1rem 1.15rem;
    }

    .prognosa-date-body p {
        margin: 0;
        color: var(--prognosa-muted);
        font-size: 0.78rem;
        line-height: 1.45;
    }

    .prognosa-date-body label {
        display: block;
        margin: 0 0 0.34rem;
        color: #3f5268;
        font-size: 0.72rem;
        font-weight: 700;
    }

    .prognosa-date-body select,
    .prognosa-date-body input[type="date"] {
        width: 100%;
        height: 40px;
        padding: 0.42rem 0.68rem;
        border: 1px solid var(--prognosa-border-strong);
        border-radius: 8px;
        background-color: #ffffff;
        color: var(--prognosa-ink);
        font-size: 0.82rem;
        font-weight: 650;
    }

    .prognosa-date-body select:focus,
    .prognosa-date-body input[type="date"]:focus {
        border-color: var(--prognosa-primary);
        outline: 0;
        box-shadow: 0 0 0 3px rgba(29, 79, 138, 0.12);
    }

    .prognosa-date-info {
        padding: 0.52rem 0.7rem;
        border: 1px solid #e2eaf3;
        border-radius: 8px;
        background: #f5f8fc;
    }

    .prognosa-date-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.78rem 1.15rem;
        border-top: 1px solid #e6edf5;
        background: #f8fafd;
    }

    .prognosa-date-footer > div {
        display: flex;
        gap: 0.45rem;
    }

    /* ---------- Empty / Error States ---------- */
    .prognosa-state {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.85rem;
        min-height: 300px;
        padding: 2rem;
        color: var(--prognosa-muted);
        text-align: left;
    }

    .prognosa-state__icon {
        width: 44px;
        height: 44px;
    }

    .prognosa-state strong {
        display: block;
        margin-bottom: 0.2rem;
        color: var(--prognosa-ink);
        font-size: 0.9rem;
    }

    .prognosa-state span {
        display: block;
        max-width: 520px;
        font-size: 0.78rem;
        line-height: 1.5;
    }

    .prognosa-state--error .prognosa-state__icon {
        border-color: #ebd0d4;
        background: #fcf4f5;
        color: #9f3542;
    }

    /* ---------- Subtle Custom Scrollbar ---------- */
    .prognosa-table-wrap::-webkit-scrollbar {
        width: 9px;
        height: 9px;
    }

    .prognosa-table-wrap::-webkit-scrollbar-track {
        background: #f1f5f9;
    }

    .prognosa-table-wrap::-webkit-scrollbar-thumb {
        border: 2px solid #f1f5f9;
        border-radius: 999px;
        background: #b0c0d2;
    }

    .prognosa-table-wrap::-webkit-scrollbar-thumb:hover {
        background: #8ea4bc;
    }

    /* ---------- A4 Capture Stage ---------- */
    .prognosa-a4-stage {
        position: absolute;
        top: 0;
        left: -99999px;
        width: auto;
        padding: 0 !important;
        background: #ffffff;
    }

    .prognosa-a4-stage .prognosa-table,
    .prognosa-a4-stage .prognosa-table th,
    .prognosa-a4-stage .prognosa-table td {
        position: static !important;
        box-shadow: none !important;
    }

    .prognosa-a4-stage .prognosa-table {
        width: auto;
        min-width: 0;
        table-layout: fixed;
    }

    .prognosa-a4-stage .prognosa-table th:not(.prognosa-col-no):not(.prognosa-col-indicator),
    .prognosa-a4-stage .prognosa-table td:not(.prognosa-col-no):not(.prognosa-col-indicator) {
        min-width: 0;
    }

    /* ---------- Responsive Breakpoints ---------- */
    @media (max-width: 1199.98px) {
        .prognosa-report-bar {
            align-items: flex-start;
            flex-direction: column;
        }

        .prognosa-view-controls {
            justify-content: flex-start;
            width: 100%;
        }

        .pw-hint-badge {
            display: none;
        }
    }

    @media (max-width: 991.98px) {
        .prognosa-page {
            --prognosa-no-width: 60px;
            --prognosa-indicator-width: 200px;
        }

        .prognosa-page-header {
            padding: 0.78rem 1rem;
            gap: 0.75rem 1rem;
        }

        .prognosa-brand__logo--danantara,
        .prognosa-brand__logo--bri {
            height: 32px;
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
            border-top: 1px solid #e7edf5;
            border-left: 0;
        }

        .prognosa-summary__item:nth-child(4) {
            border-top: 1px solid #e7edf5;
        }

        .prognosa-table-wrap {
            height: clamp(420px, calc(100dvh - 335px), 700px);
        }
    }

    @media (max-width: 767.98px) {
        .prognosa-page {
            --prognosa-no-width: 56px;
            --prognosa-indicator-width: 174px;
            padding-top: 0.45rem;
        }

        .prognosa-page-header {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            grid-template-rows: auto auto;
            gap: 0.65rem 0.75rem;
            padding: 0.75rem 0.85rem;
        }

        .prognosa-brand--left {
            grid-column: 1;
            grid-row: 1;
        }

        .prognosa-brand--right {
            grid-column: 2;
            grid-row: 1;
        }

        .prognosa-heading {
            grid-column: 1 / -1;
            grid-row: 2;
            padding-top: 0.55rem;
            border-top: 1px solid #edf2f7;
        }

        .prognosa-heading h1 {
            font-size: 1.04rem;
        }

        .prognosa-brand__logo--danantara,
        .prognosa-brand__logo--bri {
            height: auto;
            max-height: 26px;
            max-width: 100%;
        }

        .prognosa-selector {
            align-items: stretch;
            flex-direction: column;
        }

        .prognosa-filters {
            max-width: 100%;
        }

        .prognosa-filters .prognosa-field {
            flex: 0 0 auto;
            width: 100%;
        }

        .prognosa-filters > summary {
            flex-wrap: wrap;
            min-height: 44px;
        }

        .prognosa-filters__value {
            flex: 1 1 120px;
        }

        .prognosa-selector :is(input, select),
        .prognosa-date-body :is(input, select),
        .pw-search__input {
            font-size: 1rem;
            min-height: 44px;
        }

        .pw-combobox__trigger,
        .pw-snapshot-btn {
            min-height: 44px;
        }

        .prognosa-week-dialog__close {
            min-width: 44px;
            min-height: 44px;
        }

        .prognosa-date-footer {
            flex-wrap: wrap;
        }

        .prognosa-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            width: 100%;
        }

        .prognosa-button {
            width: 100%;
            min-width: 0;
            padding: 0.44rem 0.56rem 0.44rem 0.44rem;
            white-space: normal;
        }

        .prognosa-summary__item {
            padding: 0.68rem 0.75rem;
        }

        .prognosa-summary__icon {
            width: 32px;
            height: 32px;
        }

        .prognosa-summary dd {
            font-size: 0.84rem;
        }

        .prognosa-report-bar {
            padding: 0.68rem 0.75rem;
        }

        .pw-search {
            width: 100%;
        }

        .prognosa-table-wrap {
            height: clamp(390px, calc(100dvh - 395px), 660px);
            min-height: 390px;
        }

        .prognosa-table col:not([data-column-index="0"]):not([data-column-index="1"]) {
            width: 100px;
        }

        .prognosa-table col[data-group="forecast"] {
            width: 128px;
        }

        .prognosa-table col[data-column-index="13"],
        .prognosa-table col[data-column-index="16"] {
            width: 110px;
        }

        .prognosa-table th:not(.prognosa-col-no):not(.prognosa-col-indicator),
        .prognosa-table td:not(.prognosa-col-no):not(.prognosa-col-indicator) {
            width: 100px;
            min-width: 100px;
        }

        .prognosa-table th[data-group="forecast"],
        .prognosa-table td[data-group="forecast"] {
            width: 128px;
            min-width: 128px;
        }

        .prognosa-table th[data-column-index="13"],
        .prognosa-table td[data-column-index="13"],
        .prognosa-table th[data-column-index="16"],
        .prognosa-table td[data-column-index="16"] {
            width: 110px;
            min-width: 110px;
        }

        .prognosa-table tbody td {
            font-size: 0.73rem;
        }
    }

    @media (max-width: 420px) {
        .prognosa-page {
            --prognosa-indicator-width: 156px;
        }

        .prognosa-toolbar {
            padding: 0.7rem;
        }

        .prognosa-actions {
            gap: 0.4rem;
        }

        .prognosa-button {
            padding: 0.42rem 0.48rem;
            font-size: 0.7rem;
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

    @media (prefers-reduced-motion: reduce) {
        .prognosa-page *,
        .prognosa-page *::before,
        .prognosa-page *::after {
            animation-duration: 0.01ms !important;
            transition-duration: 0.01ms !important;
        }
    }
</style>
@endsection

@section('content')
<div class="prognosa-page">
    <header class="prognosa-page-header" aria-label="Header Weekly Prognosa">
        <div class="prognosa-brand prognosa-brand--left">
            <img src="{{ asset('images/danantara-logo-template.png') }}"
                 alt="Danantara Indonesia"
                 class="prognosa-brand__logo prognosa-brand__logo--danantara"
                 width="160"
                 height="38"
                 decoding="async">
        </div>

        <div class="prognosa-heading">
            <h1>Weekly Prognosa - {{ $selectedSheetLabel }}</h1>
        </div>

        <div class="prognosa-brand prognosa-brand--right">
            <img src="{{ asset('images/bri-logo-template.png') }}"
                 alt="Bank Rakyat Indonesia"
                 class="prognosa-brand__logo prognosa-brand__logo--bri"
                 width="136"
                 height="38"
                 decoding="async">
        </div>
    </header>

    <section class="prognosa-workspace" aria-labelledby="prognosa-report-title">
        <div class="prognosa-toolbar">
            <details class="prognosa-filters" id="prognosa-filters">
                <summary>
                    <span class="prognosa-filters__icon" aria-hidden="true">
                        <i class="fas fa-sliders-h"></i>
                    </span>
                    <span class="prognosa-filters__label">Filter laporan</span>
                    <span class="prognosa-filters__value">
                        <span class="prognosa-filter-chip">
                            <i class="fas fa-building" aria-hidden="true"></i>
                            {{ $selectedSheetLabel }}
                        </span>
                        <span class="prognosa-filter-chip">
                            <i class="far fa-calendar-alt" aria-hidden="true"></i>
                            {{ $latestDate ?: 'Belum tersedia' }}
                        </span>
                        @if(!empty($latestForecastLabel))
                            <span class="prognosa-filter-chip prognosa-filter-chip--week">
                                {{ $latestForecastLabel }}
                            </span>
                        @endif
                    </span>
                    <span class="prognosa-filters__chevron-wrap" aria-hidden="true">
                        <i class="fas fa-chevron-down prognosa-filters__chevron"></i>
                    </span>
                </summary>

                <form class="prognosa-selector" id="prognosa-filter-form" method="GET" action="{{ route('prognosa.weekly') }}">
                    <input type="hidden" name="week" value="{{ $selectedWeek }}">
                    @foreach($periodHiddenInputs(['posisi_terakhir']) as $periodName => $periodValue)
                        <input type="hidden" name="{{ $periodName }}" value="{{ $periodValue }}">
                    @endforeach

                    <div class="prognosa-field">
                        <label for="prognosa-sheet">
                            <span>Wilayah Laporan</span>
                            @if($isLocked)
                                <span class="prognosa-field__badge">
                                    <i class="fas fa-lock" aria-hidden="true"></i> Terkunci
                                </span>
                            @endif
                        </label>

                        <select id="prognosa-sheet"
                                name="sheet"
                                class="prognosa-native-select"
                                aria-label="Pilih wilayah laporan"
                                @disabled($isLocked)
                                @if(!$isLocked) onchange="this.form.submit()" @endif>
                            @foreach($sheetOptions as $key => $option)
                                <option value="{{ $key }}" @selected($selectedSheetKey === $key)>{{ $option['label'] }}</option>
                            @endforeach
                        </select>

                        <div class="pw-combobox" data-pw-select="prognosa-sheet">
                            <button type="button"
                                    class="pw-combobox__trigger"
                                    aria-haspopup="listbox"
                                    aria-expanded="false"
                                    aria-controls="pw-sheet-listbox"
                                    @disabled($isLocked)>
                                <span class="pw-combobox__icon" aria-hidden="true">
                                    <i class="fas {{ $isLocked ? 'fa-lock' : 'fa-building' }}"></i>
                                </span>
                                <span class="pw-combobox__text" data-pw-select-label>{{ $selectedSheetLabel }}</span>
                                <i class="fas fa-chevron-down pw-combobox__caret" aria-hidden="true"></i>
                            </button>
                            <div class="pw-combobox__popover" id="pw-sheet-listbox" hidden>
                                <ul class="pw-combobox__list" role="listbox" aria-label="Daftar wilayah laporan">
                                    @foreach($sheetOptions as $key => $option)
                                        <li role="presentation">
                                            <button type="button"
                                                    class="pw-combobox__option {{ $selectedSheetKey === $key ? 'is-selected' : '' }}"
                                                    role="option"
                                                    aria-selected="{{ $selectedSheetKey === $key ? 'true' : 'false' }}"
                                                    data-value="{{ $key }}">
                                                <span>
                                                    {{ $option['label'] }}
                                                    <span class="pw-combobox__option-meta">{{ $key === 'area' ? 'Konsolidasi Area 6' : 'Kantor Cabang' }}</span>
                                                </span>
                                                <i class="fas fa-check pw-combobox__check" aria-hidden="true"></i>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="prognosa-field prognosa-field--date">
                        <label for="prognosa-posisi">
                            <span>Posisi Terakhir</span>
                            @if($periodOptions !== [])
                                <span class="prognosa-field__badge">{{ count($periodOptions) }} snapshot</span>
                            @endif
                        </label>
                        <div class="pw-date-group">
                            <div class="prognosa-select-shell">
                                <i class="fas fa-calendar-day prognosa-select-icon" aria-hidden="true"></i>
                                <input type="date"
                                       id="prognosa-posisi"
                                       name="posisi_terakhir"
                                       value="{{ $selectedPeriod }}"
                                       @if($positionMin !== '') min="{{ $positionMin }}" @endif
                                       @if($positionMax !== '') max="{{ $positionMax }}" @endif
                                       aria-label="Pilih tanggal posisi terakhir"
                                       onchange="this.form.submit()">
                            </div>

                            @if($periodOptions !== [])
                                <div class="pw-combobox" style="width: auto;" data-pw-snapshot>
                                    <button type="button"
                                            class="pw-snapshot-btn"
                                            aria-haspopup="listbox"
                                            aria-expanded="false"
                                            aria-controls="pw-snapshot-popover"
                                            title="Pilih cepat dari daftar tanggal snapshot">
                                        <i class="fas fa-history" aria-hidden="true"></i>
                                        <span>Snapshot</span>
                                        <i class="fas fa-chevron-down pw-combobox__caret" aria-hidden="true"></i>
                                    </button>
                                    <div class="pw-combobox__popover" id="pw-snapshot-popover" style="right: 0; left: auto; min-width: 248px;" hidden>
                                        <div class="pw-combobox__search-wrap">
                                            <i class="fas fa-search" aria-hidden="true"></i>
                                            <input type="search"
                                                   class="pw-combobox__search"
                                                   placeholder="Cari tanggal…"
                                                   aria-label="Cari tanggal snapshot"
                                                   data-pw-snapshot-search>
                                        </div>
                                        <ul class="pw-combobox__list" role="listbox" aria-label="Tanggal snapshot tersedia">
                                            @foreach($periodOptions as $opt)
                                                <li role="presentation">
                                                    <button type="button"
                                                            class="pw-combobox__option {{ $selectedPeriod === $opt['value'] ? 'is-selected' : '' }}"
                                                            role="option"
                                                            aria-selected="{{ $selectedPeriod === $opt['value'] ? 'true' : 'false' }}"
                                                            data-snapshot-value="{{ $opt['value'] }}"
                                                            data-snapshot-label="{{ strtolower($opt['label'] . ' ' . $opt['value']) }}">
                                                        <span>{{ $opt['label'] }}</span>
                                                        <i class="fas fa-check pw-combobox__check" aria-hidden="true"></i>
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <div class="pw-combobox__empty" data-pw-snapshot-empty hidden>Tanggal tidak ditemukan.</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if(count($availableWeeks) > 1)
                        <div class="prognosa-field prognosa-field--weeks">
                            <span class="prognosa-field__label">Target Prognosa</span>
                            <div class="pw-week-pills" role="group" aria-label="Pilih target week prognosa">
                                @foreach($availableWeeks as $weekOption)
                                    <a href="{{ route('prognosa.weekly', array_merge(['sheet' => $selectedSheetKey, 'week' => $weekOption], $periodQuery)) }}"
                                       class="pw-week-pill {{ $selectedWeek === $weekOption ? 'is-active' : '' }}"
                                       aria-current="{{ $selectedWeek === $weekOption ? 'page' : 'false' }}">
                                        W{{ $weekOption }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($isLocked)
                        <input type="hidden" name="sheet" value="{{ $selectedSheetKey }}">
                        <span class="prognosa-lock">
                            <i class="fas fa-lock" aria-hidden="true"></i>
                            Sesuai akses akun
                        </span>
                    @endif
                </form>
            </details>

            <div class="prognosa-actions">
                @if(!$error && $rows !== [] && $headerColumns !== [])
                    <button type="button" id="prognosa-unhide-columns" class="prognosa-pill" hidden
                            title="Tampilkan kembali semua kolom yang disembunyikan">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                        <span id="prognosa-hidden-columns-count">0</span> Kolom Tersembunyi
                    </button>
                    <button type="button" id="prognosa-unhide-rows" class="prognosa-pill" hidden
                            title="Tampilkan kembali semua baris yang disembunyikan">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                        <span id="prognosa-hidden-rows-count">0</span> Baris Tersembunyi
                    </button>
                    @if($periodQuery !== [])
                        <a class="prognosa-button prognosa-button--reset"
                           href="{{ route('prognosa.weekly', ['sheet' => $selectedSheetKey, 'week' => $selectedWeek]) }}"
                           title="Kembalikan semua tanggal ke default">
                            <i class="fas fa-undo-alt" aria-hidden="true"></i>
                            <span>Tanggal Default</span>
                        </a>
                    @endif
                    <a id="prognosa-export-excel"
                       class="prognosa-button prognosa-button--excel"
                       href="{{ route('prognosa.weekly.export', $exportQuery) }}"
                       data-export-url="{{ route('prognosa.weekly.export') }}">
                        <span class="prognosa-button__badge" aria-hidden="true">
                            <i class="fas fa-file-excel"></i>
                        </span>
                        <span>Export Excel</span>
                    </a>
                    <button type="button" id="prognosa-export-a4" class="prognosa-button prognosa-button--a4">
                        <span class="prognosa-button__badge" aria-hidden="true">
                            <i class="fas fa-file-image"></i>
                        </span>
                        <span>Export A4</span>
                    </button>
                @endif
                <a class="prognosa-button prognosa-button--sheet"
                   href="{{ $spreadsheetUrl }}"
                   target="_blank"
                   rel="noopener">
                    <span class="prognosa-button__badge" aria-hidden="true">
                        <i class="fas fa-external-link-alt"></i>
                    </span>
                    <span>Spreadsheet</span>
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
                @if(!$error && $rows !== [] && $headerColumns !== [])
                    <span class="prognosa-unit-note">Satuan nominal dalam Rp Juta</span>
                @endif
            </div>

            @if(!$error && $rows !== [] && $headerColumns !== [])
                <div class="prognosa-view-controls">
                    <span class="pw-search__count" id="prognosa-search-count" role="status" aria-live="polite" hidden></span>
                    <div class="pw-search">
                        <i class="fas fa-search pw-search__icon" aria-hidden="true"></i>
                        <input type="search"
                               id="prognosa-search"
                               class="pw-search__input"
                               placeholder="Cari indikator…"
                               aria-label="Cari indikator dalam tabel"
                               autocomplete="off">
                        <button type="button"
                                id="prognosa-search-clear"
                                class="pw-search__clear"
                                aria-label="Hapus pencarian indikator"
                                hidden>
                            <i class="fas fa-times" aria-hidden="true"></i>
                        </button>
                    </div>
                    <span class="pw-hint-badge" title="Klik kanan pada header kolom atau baris untuk menyembunyikan atau mengubah tanggal">
                        <i class="far fa-lightbulb" aria-hidden="true"></i>
                        Klik kanan: opsi kolom/baris
                    </span>
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
                 data-sheet="{{ $selectedSheetKey }}"
                 data-sheet-label="{{ $selectedSheetLabel }}"
                 data-week="{{ $selectedWeek }}"
                 data-week-label="{{ $latestForecastLabel }}"
                 data-latest-date="{{ $latestDate }}"
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
                                    class="{{ $group['key'] === 'identity' ? 'prognosa-group-identity' : '' }} {{ $group['key'] === 'forecast' ? 'prognosa-week-surface text-break' : '' }} {{ in_array($group['start'], $groupStartColumns, true) ? 'prognosa-group-start' : '' }}">
                                    @if($group['key'] === 'forecast')
                                        <span class="prognosa-forecast-head">
                                            <span class="prognosa-forecast-head__label">{{ $group['label'] }}</span>
                                            <button type="button"
                                                    class="prognosa-week-trigger"
                                                    data-week-selector-trigger
                                                    aria-haspopup="dialog"
                                                    aria-controls="prognosa-week-modal"
                                                    aria-label="Pilih week prognosa">
                                                <i class="fas fa-pen" aria-hidden="true"></i>
                                            </button>
                                        </span>
                                    @else
                                        <span>{{ $group['label'] }}</span>
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
                                        title="Klik dua kali untuk mengganti week. Klik kanan untuk menyembunyikan kolom."
                                    @elseif(!empty($column['period_key']))
                                        tabindex="0"
                                        aria-haspopup="dialog"
                                        aria-controls="prognosa-date-modal"
                                        data-period-key="{{ $column['period_key'] }}"
                                        data-period="{{ $column['period'] }}"
                                        data-custom-period="{{ isset($customPeriods[$column['period_key']]) || ($column['period_key'] === 'current' && isset($periodQuery['posisi_terakhir'])) ? '1' : '0' }}"
                                        title="Tekan Enter untuk mengubah tanggal. Klik kanan untuk opsi kolom."
                                    @elseif($columnIndex > 1)
                                        title="Klik kanan untuk menyembunyikan kolom"
                                    @endif
                                    class="{{ implode(' ', array_filter([...$classes, $column['group'] === 'forecast' ? 'prognosa-week-surface' : '', !empty($column['period_key']) ? 'prognosa-period-col' : ''])) }}">
                                    <span class="prognosa-column-label">{{ !empty($column['period_key']) ? ($column['detail'] ?: '—') : $column['label'] }}</span>
                                    @if(empty($column['period_key']) && !empty($column['detail']))
                                        <small class="prognosa-column-detail">{{ $column['detail'] }}</small>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr class="prognosa-row--{{ $row['type'] }}"
                                data-row-type="{{ $row['type'] }}"
                                data-row-section="{{ $row['section'] }}"
                                data-row-key="{{ $row['key'] ?? '' }}">
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
                                        @elseif($columnIndex === 0)
                                            @if($value !== '')
                                                <span class="prognosa-no-token">{{ $value }}</span>
                                            @endif
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
                    @foreach($periodQuery as $periodName => $periodValue)
                        <input type="hidden" name="{{ $periodName }}" value="{{ $periodValue }}">
                    @endforeach
                    @foreach($availableWeeks as $week)
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

        <div id="prognosa-column-menu" class="prognosa-menu" role="menu" hidden>
            <div class="prognosa-menu__title">
                <i class="fas fa-columns" aria-hidden="true"></i>
                <span id="prognosa-column-menu-title">Kolom</span>
            </div>
            <button type="button" class="prognosa-menu__item" role="menuitem" data-menu-action="hide-column">
                <i class="fas fa-eye-slash text-danger" aria-hidden="true"></i>
                <span>Sembunyikan Kolom</span>
            </button>
            <button type="button" class="prognosa-menu__item" role="menuitem" data-menu-action="change-date">
                <i class="fas fa-calendar-alt text-primary" aria-hidden="true"></i>
                <span>Ubah Tanggal Kolom…</span>
            </button>
            <button type="button" class="prognosa-menu__item" role="menuitem" data-menu-action="reset-date">
                <i class="fas fa-undo-alt text-warning" aria-hidden="true"></i>
                <span>Kembalikan Tanggal Default</span>
            </button>
            <button type="button" class="prognosa-menu__item prognosa-menu__item--success" role="menuitem" data-menu-action="unhide-columns">
                <i class="fas fa-eye" aria-hidden="true"></i>
                <span>Tampilkan Semua Kolom (<span data-menu-count="columns">0</span>)</span>
            </button>
        </div>

        <div id="prognosa-row-menu" class="prognosa-menu" role="menu" hidden>
            <div class="prognosa-menu__title">
                <i class="fas fa-bars" aria-hidden="true"></i>
                <span id="prognosa-row-menu-title">Baris</span>
            </div>
            <button type="button" class="prognosa-menu__item" role="menuitem" data-menu-action="hide-row">
                <i class="fas fa-eye-slash text-danger" aria-hidden="true"></i>
                <span>Sembunyikan Baris</span>
            </button>
            <button type="button" class="prognosa-menu__item prognosa-menu__item--success" role="menuitem" data-menu-action="unhide-rows">
                <i class="fas fa-eye" aria-hidden="true"></i>
                <span>Tampilkan Semua Baris (<span data-menu-count="rows">0</span>)</span>
            </button>
        </div>

        <div id="prognosa-date-modal"
             class="prognosa-week-modal"
             role="dialog"
             aria-modal="true"
             aria-labelledby="prognosa-date-title"
             aria-describedby="prognosa-date-description"
             hidden>
            <div class="prognosa-week-dialog">
                <div class="prognosa-week-dialog__header">
                    <h2 id="prognosa-date-title">Ubah Tanggal Kolom</h2>
                    <button type="button"
                            class="prognosa-week-dialog__close"
                            data-date-close
                            aria-label="Tutup pengaturan tanggal">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="prognosa-date-body">
                    <p id="prognosa-date-description">Pilih tanggal untuk kolom <strong id="prognosa-date-badge">-</strong>.</p>
                    <div>
                        <label for="prognosa-date-preset">Pilih dari tanggal snapshot</label>
                        <select id="prognosa-date-preset"></select>
                    </div>
                    <div>
                        <label for="prognosa-date-custom">Atau masukkan tanggal tertentu</label>
                        <input type="date" id="prognosa-date-custom">
                    </div>
                    <p class="prognosa-date-info" id="prognosa-date-info" role="status" aria-live="polite"></p>
                </div>
                <div class="prognosa-date-footer">
                    <button type="button" class="prognosa-button prognosa-button--plain" id="prognosa-date-reset">
                        <i class="fas fa-undo-alt" aria-hidden="true"></i> Default
                    </button>
                    <div>
                        <button type="button" class="prognosa-button prognosa-button--plain" data-date-close>Batal</button>
                        <button type="button" class="prognosa-button prognosa-button--primary" id="prognosa-date-apply">
                            <i class="fas fa-check" aria-hidden="true"></i> Terapkan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script src="{{ asset('vendor/html2canvas/html2canvas.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ---------- Custom Filter Combobox & Snapshot Dropdown ----------
        (function initFilterControls() {
            const filterForm = document.getElementById('prognosa-filter-form');
            const openPopovers = new Set();

            function closeAllPopovers(except) {
                openPopovers.forEach(function (entry) {
                    if (entry !== except) {
                        entry.close();
                    }
                });
            }

            // 1) Custom Combobox Wilayah Laporan
            document.querySelectorAll('[data-pw-select]').forEach(function (wrapper) {
                const selectId = wrapper.dataset.pwSelect;
                const nativeSelect = document.getElementById(selectId);
                const trigger = wrapper.querySelector('.pw-combobox__trigger');
                const popover = wrapper.querySelector('.pw-combobox__popover');
                const options = Array.from(wrapper.querySelectorAll('.pw-combobox__option'));
                const labelEl = wrapper.querySelector('[data-pw-select-label]');
                if (!nativeSelect || !trigger || !popover || trigger.disabled) {
                    return;
                }

                let activeIndex = Math.max(0, options.findIndex(function (o) { return o.classList.contains('is-selected'); }));

                function highlight(index) {
                    options.forEach(function (opt, idx) {
                        opt.classList.toggle('is-highlighted', idx === index);
                    });
                    if (options[index]) {
                        options[index].scrollIntoView({ block: 'nearest' });
                    }
                }

                function open() {
                    closeAllPopovers(controller);
                    popover.hidden = false;
                    trigger.setAttribute('aria-expanded', 'true');
                    highlight(activeIndex);
                    openPopovers.add(controller);
                }

                function close(returnFocus) {
                    if (popover.hidden) {
                        return;
                    }
                    popover.hidden = true;
                    trigger.setAttribute('aria-expanded', 'false');
                    openPopovers.delete(controller);
                    if (returnFocus) {
                        trigger.focus();
                    }
                }

                function selectOption(option) {
                    const value = option.dataset.value || '';
                    if (nativeSelect.value === value) {
                        close(true);
                        return;
                    }
                    nativeSelect.value = value;
                    if (labelEl) {
                        labelEl.textContent = option.childNodes[0] ? option.childNodes[0].textContent.trim() : value;
                    }
                    close(false);
                    if (filterForm) {
                        filterForm.submit();
                    }
                }

                const controller = { close: close };

                trigger.addEventListener('click', function () {
                    if (popover.hidden) {
                        open();
                    } else {
                        close(false);
                    }
                });

                trigger.addEventListener('keydown', function (event) {
                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        if (popover.hidden) {
                            open();
                        } else {
                            activeIndex = (activeIndex + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
                            highlight(activeIndex);
                        }
                    } else if (event.key === 'Enter' && !popover.hidden) {
                        event.preventDefault();
                        if (options[activeIndex]) {
                            selectOption(options[activeIndex]);
                        }
                    } else if (event.key === 'Escape') {
                        close(true);
                    }
                });

                options.forEach(function (option, idx) {
                    option.addEventListener('mouseenter', function () {
                        activeIndex = idx;
                        highlight(activeIndex);
                    });
                    option.addEventListener('click', function () {
                        selectOption(option);
                    });
                });
            });

            // 2) Custom Snapshot Date Picker Popover
            const snapshotWrap = document.querySelector('[data-pw-snapshot]');
            const posisiInput = document.getElementById('prognosa-posisi');
            if (snapshotWrap && posisiInput) {
                const trigger = snapshotWrap.querySelector('.pw-snapshot-btn');
                const popover = snapshotWrap.querySelector('.pw-combobox__popover');
                const searchInput = snapshotWrap.querySelector('[data-pw-snapshot-search]');
                const emptyState = snapshotWrap.querySelector('[data-pw-snapshot-empty]');
                const items = Array.from(snapshotWrap.querySelectorAll('[data-snapshot-value]'));

                function open() {
                    closeAllPopovers(controller);
                    popover.hidden = false;
                    trigger.setAttribute('aria-expanded', 'true');
                    openPopovers.add(controller);
                    if (searchInput) {
                        searchInput.value = '';
                        filterSnapshots('');
                        window.requestAnimationFrame(function () {
                            searchInput.focus();
                        });
                    }
                }

                function close(returnFocus) {
                    if (popover.hidden) {
                        return;
                    }
                    popover.hidden = true;
                    trigger.setAttribute('aria-expanded', 'false');
                    openPopovers.delete(controller);
                    if (returnFocus) {
                        trigger.focus();
                    }
                }

                function filterSnapshots(query) {
                    const q = String(query || '').trim().toLowerCase();
                    let visible = 0;
                    items.forEach(function (btn) {
                        const match = q === '' || (btn.dataset.snapshotLabel || '').includes(q);
                        btn.parentElement.hidden = !match;
                        if (match) {
                            visible += 1;
                        }
                    });
                    if (emptyState) {
                        emptyState.hidden = visible > 0;
                    }
                }

                const controller = { close: close };

                trigger.addEventListener('click', function () {
                    if (popover.hidden) {
                        open();
                    } else {
                        close(false);
                    }
                });

                if (searchInput) {
                    searchInput.addEventListener('input', function () {
                        filterSnapshots(searchInput.value);
                    });
                    searchInput.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape') {
                            event.preventDefault();
                            close(true);
                        }
                    });
                }

                items.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        posisiInput.value = btn.dataset.snapshotValue || '';
                        close(false);
                        if (filterForm) {
                            filterForm.submit();
                        }
                    });
                });
            }

            document.addEventListener('click', function (event) {
                if (!event.target.closest('.pw-combobox')) {
                    closeAllPopovers(null);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && openPopovers.size > 0) {
                    closeAllPopovers(null);
                }
            });
        })();

        // ---------- Main Table Controller ----------
        const tableWrap = document.querySelector('.prognosa-table-wrap');
        const table = tableWrap ? tableWrap.querySelector('.prognosa-table') : null;

        if (!tableWrap || !table) {
            return;
        }

        // Keep fixed dialogs outside transformed/scrolling admin layout containers.
        ['prognosa-week-modal', 'prognosa-date-modal'].forEach(function (id) {
            const dialog = document.getElementById(id);
            if (dialog) document.body.appendChild(dialog);
        });

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

        // Dynamic sticky scroll shadow on horizontal scroll
        function syncHorizontalScrollState() {
            tableWrap.classList.toggle('is-scrolled-x', tableWrap.scrollLeft > 4);
        }
        tableWrap.addEventListener('scroll', syncHorizontalScrollState, { passive: true });
        syncHorizontalScrollState();

        // Subtle column header crosshair hover + click-to-highlight row
        const headerByIndex = new Map();
        columnHeaders.forEach(function (th) {
            headerByIndex.set(th.dataset.columnIndex, th);
        });
        let hoveredHeader = null;
        const tbodyEl = table.querySelector('tbody');
        if (tbodyEl) {
            tbodyEl.addEventListener('mouseover', function (event) {
                const cell = event.target.closest('td[data-column-index]');
                const nextHeader = cell ? headerByIndex.get(cell.dataset.columnIndex) : null;
                if (hoveredHeader !== nextHeader) {
                    if (hoveredHeader) {
                        hoveredHeader.classList.remove('is-col-hover');
                    }
                    hoveredHeader = nextHeader || null;
                    if (hoveredHeader) {
                        hoveredHeader.classList.add('is-col-hover');
                    }
                }
            });
            tbodyEl.addEventListener('mouseleave', function () {
                if (hoveredHeader) {
                    hoveredHeader.classList.remove('is-col-hover');
                    hoveredHeader = null;
                }
            });
            tbodyEl.addEventListener('click', function (event) {
                const row = event.target.closest('tr[data-row-key]');
                if (!row || event.button !== 0) {
                    return;
                }
                const wasSelected = row.classList.contains('is-row-selected');
                reportRows.forEach(function (item) {
                    item.classList.remove('is-row-selected');
                });
                if (!wasSelected) {
                    row.classList.add('is-row-selected');
                }
            });
        }

        // Baris/kolom yang disembunyikan pengguna (klik kanan), diingat per wilayah selama sesi.
        const hiddenColumns = new Set();
        const hiddenRows = new Set();
        const hiddenStorageKey = 'weekly-prognosa-hidden:' + (tableWrap.dataset.sheet || 'area');
        try {
            const stored = JSON.parse(sessionStorage.getItem(hiddenStorageKey) || 'null');
            if (stored) {
                (stored.cols || []).forEach(function (value) { hiddenColumns.add(String(value)); });
                (stored.rows || []).forEach(function (value) { hiddenRows.add(String(value)); });
            }
        } catch (error) {
        }

        const unhideColumnsButton = document.getElementById('prognosa-unhide-columns');
        const unhideRowsButton = document.getElementById('prognosa-unhide-rows');
        const hiddenColumnsCount = document.getElementById('prognosa-hidden-columns-count');
        const hiddenRowsCount = document.getElementById('prognosa-hidden-rows-count');

        // Instant indicator search state (independent of right-click hiddenRows)
        const searchInput = document.getElementById('prognosa-search');
        const searchClear = document.getElementById('prognosa-search-clear');
        const searchCount = document.getElementById('prognosa-search-count');
        let searchQuery = '';

        function persistHidden() {
            try {
                sessionStorage.setItem(hiddenStorageKey, JSON.stringify({
                    cols: Array.from(hiddenColumns),
                    rows: Array.from(hiddenRows),
                }));
            } catch (error) {
            }
        }

        function updateHiddenBadges() {
            if (unhideColumnsButton) {
                unhideColumnsButton.hidden = hiddenColumns.size === 0;
                hiddenColumnsCount.textContent = String(hiddenColumns.size);
            }
            if (unhideRowsButton) {
                unhideRowsButton.hidden = hiddenRows.size === 0;
                hiddenRowsCount.textContent = String(hiddenRows.size);
            }
        }

        function openWeekModal(source) {
            if (!weekModal) {
                return;
            }

            weekTriggerSource = source || document.activeElement;
            weekModal.hidden = false;
            tableWrap.closest('.prognosa-workspace').inert = true;
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
            tableWrap.closest('.prognosa-workspace').inert = false;
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
                const visible = columnIsVisible(column, mode) && !hiddenColumns.has(index);
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

            const isMobile = window.matchMedia('(max-width: 767.98px)').matches;
            const isTablet = window.matchMedia('(max-width: 991.98px)').matches;
            const isCoarse = window.matchMedia('(pointer: coarse)').matches;
            const stickyWidth = isMobile ? 230 : (isTablet ? 260 : 290);
            let numericWidthSum = 0;
            columnHeaders.forEach(function (column) {
                const idx = column.dataset.columnIndex;
                if (!visibleColumns.has(idx) || idx === '0' || idx === '1') {
                    return;
                }
                if (column.dataset.group === 'forecast') {
                    numericWidthSum += isCoarse ? 156 : (isMobile ? 128 : 136);
                } else if (idx === '13' || idx === '16') {
                    numericWidthSum += isMobile ? 110 : 118;
                } else {
                    numericWidthSum += isMobile ? 100 : 108;
                }
            });
            const scrollViewportWidth = Math.max(0, tableWrap.clientWidth - 16);
            table.style.width = Math.max(scrollViewportWidth, stickyWidth + numericWidthSum) + 'px';
            table.style.minWidth = table.style.width;
            tableWrap.dataset.viewMode = mode;
            updateHiddenBadges();

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

        function applyRowVisibility() {
            const section = tableWrap.dataset.reportSection || 'all';
            const query = searchQuery.trim().toLowerCase();

            if (query === '') {
                reportRows.forEach(function (row) {
                    row.hidden = (section !== 'all' && row.dataset.rowSection !== section)
                        || hiddenRows.has(row.dataset.rowKey || '');
                });
                if (searchCount) {
                    searchCount.hidden = true;
                }
                if (searchClear) {
                    searchClear.hidden = true;
                }
                updateHiddenBadges();
                return;
            }

            // Keep matching rows plus their parent section/category/subcategory headers visible for context.
            const kept = new Set();
            let currentSectionRow = null;
            let currentCategoryRow = null;
            let currentSubCategoryRow = null;
            let matchTotal = 0;

            reportRows.forEach(function (row) {
                const type = row.dataset.rowType || 'detail';
                if (type === 'section') {
                    currentSectionRow = row;
                    currentCategoryRow = null;
                    currentSubCategoryRow = null;
                } else if (type === 'category') {
                    currentCategoryRow = row;
                    currentSubCategoryRow = null;
                } else if (type === 'subcategory') {
                    currentSubCategoryRow = row;
                }

                const baseHidden = (section !== 'all' && row.dataset.rowSection !== section)
                    || hiddenRows.has(row.dataset.rowKey || '');
                if (baseHidden) {
                    return;
                }

                const noText = (row.querySelector('[data-column-index="0"]')?.textContent || '').trim().toLowerCase();
                const labelText = (row.querySelector('[data-column-index="1"]')?.textContent || '').trim().toLowerCase();
                if ((noText + ' ' + labelText).includes(query)) {
                    kept.add(row);
                    matchTotal += 1;
                    if (currentSectionRow && !hiddenRows.has(currentSectionRow.dataset.rowKey || '')) {
                        kept.add(currentSectionRow);
                    }
                    if (currentCategoryRow && !hiddenRows.has(currentCategoryRow.dataset.rowKey || '')) {
                        kept.add(currentCategoryRow);
                    }
                    if (currentSubCategoryRow && !hiddenRows.has(currentSubCategoryRow.dataset.rowKey || '')) {
                        kept.add(currentSubCategoryRow);
                    }
                }
            });

            reportRows.forEach(function (row) {
                row.hidden = !kept.has(row);
            });

            if (searchCount) {
                searchCount.hidden = false;
                searchCount.textContent = matchTotal > 0 ? matchTotal + ' cocok' : '0 cocok';
            }
            if (searchClear) {
                searchClear.hidden = false;
            }
            updateHiddenBadges();
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                searchQuery = searchInput.value;
                applyRowVisibility();
            });
            searchInput.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && searchInput.value !== '') {
                    event.preventDefault();
                    searchInput.value = '';
                    searchQuery = '';
                    applyRowVisibility();
                }
            });
        }

        if (searchClear) {
            searchClear.addEventListener('click', function () {
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
                searchQuery = '';
                applyRowVisibility();
            });
        }

        function applyReportSection(section) {
            tableWrap.dataset.reportSection = section;
            applyRowVisibility();

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

        // Removed view-mode controls must not leave an old stored mode hiding columns.
        applyColumnMode('all');
        applyReportSection('all');

        // ---------- Sembunyikan baris/kolom, menu klik kanan ----------
        const columnMenu = document.getElementById('prognosa-column-menu');
        const rowMenu = document.getElementById('prognosa-row-menu');
        const columnHeaderRow = table.querySelector('thead tr:nth-child(2)');
        let menuColumnIndex = null;
        let menuRowKey = null;
        let menuTarget = null;

        function hideMenus() {
            [columnMenu, rowMenu].forEach(function (menu) {
                if (menu) {
                    menu.hidden = true;
                }
            });
            if (menuTarget) {
                menuTarget.classList.remove('is-menu-target');
                menuTarget = null;
            }
        }

        function placeMenu(menu, x, y) {
            menu.hidden = false;
            const rect = menu.getBoundingClientRect();
            const left = Math.max(8, Math.min(x, window.innerWidth - rect.width - 8));
            const top = Math.max(8, Math.min(y, window.innerHeight - rect.height - 8));
            menu.style.left = left + 'px';
            menu.style.top = top + 'px';
        }

        function openColumnMenu(header, x, y) {
            if (!columnMenu) {
                return;
            }
            hideMenus();
            menuColumnIndex = header.dataset.columnIndex;
            menuTarget = header;
            header.classList.add('is-menu-target');
            const label = header.querySelector('.prognosa-column-label');
            const detail = header.querySelector('.prognosa-column-detail');
            document.getElementById('prognosa-column-menu-title').textContent =
                (label ? label.textContent.trim() : 'Kolom') + (detail && header.dataset.periodKey ? ' - ' + detail.textContent.trim() : '');

            const isIdentity = menuColumnIndex === '0' || menuColumnIndex === '1';
            const hasPeriod = Boolean(header.dataset.periodKey);
            columnMenu.querySelector('[data-menu-action="hide-column"]').hidden = isIdentity;
            columnMenu.querySelector('[data-menu-action="change-date"]').hidden = !hasPeriod;
            columnMenu.querySelector('[data-menu-action="reset-date"]').hidden = !(hasPeriod && header.dataset.customPeriod === '1');
            columnMenu.querySelector('[data-menu-action="unhide-columns"]').hidden = hiddenColumns.size === 0;
            columnMenu.querySelector('[data-menu-count="columns"]').textContent = String(hiddenColumns.size);
            placeMenu(columnMenu, x, y);
        }

        function openRowMenu(row, x, y) {
            if (!rowMenu) {
                return;
            }
            hideMenus();
            menuRowKey = row.dataset.rowKey || '';
            menuTarget = row;
            row.classList.add('is-menu-target');
            const noEl = row.querySelector('[data-column-index="0"]');
            const indicator = row.querySelector('[data-column-index="1"]');
            const noText = noEl ? noEl.textContent.trim() : '';
            const indicatorText = indicator ? indicator.textContent.trim() : 'Baris';
            document.getElementById('prognosa-row-menu-title').textContent =
                (noText ? noText + ' ' : '') + indicatorText;
            rowMenu.querySelector('[data-menu-action="hide-row"]').hidden = menuRowKey === '';
            rowMenu.querySelector('[data-menu-action="unhide-rows"]').hidden = hiddenRows.size === 0;
            rowMenu.querySelector('[data-menu-count="rows"]').textContent = String(hiddenRows.size);
            placeMenu(rowMenu, x, y);
        }

        function hideColumn(index) {
            if (!index || index === '0' || index === '1') {
                return;
            }
            hiddenColumns.add(index);
            persistHidden();
            applyColumnMode(tableWrap.dataset.viewMode || 'all');
        }

        function unhideAllColumns() {
            hiddenColumns.clear();
            persistHidden();
            applyColumnMode(tableWrap.dataset.viewMode || 'all');
        }

        function hideRow(key) {
            if (!key) {
                return;
            }
            hiddenRows.add(key);
            persistHidden();
            applyRowVisibility();
        }

        function unhideAllRows() {
            hiddenRows.clear();
            persistHidden();
            applyRowVisibility();
        }

        if (columnHeaderRow) {
            columnHeaderRow.addEventListener('contextmenu', function (event) {
                const header = event.target.closest('th[data-column-index]');
                if (!header) {
                    return;
                }
                event.preventDefault();
                openColumnMenu(header, event.clientX, event.clientY);
            });
        }

        table.querySelector('tbody').addEventListener('contextmenu', function (event) {
            const row = event.target.closest('tr[data-row-key]');
            if (!row) {
                return;
            }
            event.preventDefault();
            openRowMenu(row, event.clientX, event.clientY);
        });

        // Sentuh lama (layar sentuh tidak punya klik kanan).
        let pressTimer = null;
        function cancelPress() {
            if (pressTimer !== null) {
                window.clearTimeout(pressTimer);
                pressTimer = null;
            }
        }
        table.addEventListener('touchstart', function (event) {
            if (event.touches.length !== 1) {
                return;
            }
            const touch = event.touches[0];
            const header = event.target.closest('thead tr:nth-child(2) th[data-column-index]');
            const row = header ? null : event.target.closest('tbody tr[data-row-key]');
            if (!header && !row) {
                return;
            }
            cancelPress();
            pressTimer = window.setTimeout(function () {
                pressTimer = null;
                if (header) {
                    openColumnMenu(header, touch.clientX, touch.clientY);
                } else {
                    openRowMenu(row, touch.clientX, touch.clientY);
                }
            }, 550);
        }, { passive: true });
        ['touchmove', 'touchend', 'touchcancel'].forEach(function (name) {
            table.addEventListener(name, cancelPress, { passive: true });
        });

        [columnMenu, rowMenu].forEach(function (menu) {
            if (!menu) {
                return;
            }
            menu.addEventListener('click', function (event) {
                const button = event.target.closest('[data-menu-action]');
                if (!button) {
                    return;
                }
                const action = button.dataset.menuAction;
                const columnIndex = menuColumnIndex;
                const rowKey = menuRowKey;
                hideMenus();
                if (action === 'hide-column') {
                    hideColumn(columnIndex);
                } else if (action === 'unhide-columns') {
                    unhideAllColumns();
                } else if (action === 'hide-row') {
                    hideRow(rowKey);
                } else if (action === 'unhide-rows') {
                    unhideAllRows();
                } else if (action === 'change-date') {
                    const header = columnHeaderRow.querySelector('[data-column-index="' + columnIndex + '"]');
                    if (header) {
                        openDateModal(header);
                    }
                } else if (action === 'reset-date') {
                    const header = columnHeaderRow.querySelector('[data-column-index="' + columnIndex + '"]');
                    if (header) {
                        applyPeriod(header.dataset.periodKey, '');
                    }
                }
            });
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('.prognosa-menu')) {
                hideMenus();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hideMenus();
            }
        });
        window.addEventListener('scroll', hideMenus, { passive: true, capture: true });
        window.addEventListener('blur', hideMenus);

        if (unhideColumnsButton) {
            unhideColumnsButton.addEventListener('click', unhideAllColumns);
        }
        if (unhideRowsButton) {
            unhideRowsButton.addEventListener('click', unhideAllRows);
        }

        // ---------- Ubah tanggal ----------
        const periodOptions = @json($periodOptions);
        const dateModal = document.getElementById('prognosa-date-modal');
        const datePreset = document.getElementById('prognosa-date-preset');
        const dateCustom = document.getElementById('prognosa-date-custom');
        const dateInfo = document.getElementById('prognosa-date-info');
        const dateBadge = document.getElementById('prognosa-date-badge');
        let dateModalHeader = null;
        let dateModalSource = null;

        function periodParam(key) {
            return key === 'current' ? 'posisi_terakhir' : key + '_period';
        }

        function applyPeriod(key, value) {
            const url = new URL(window.location.href);
            url.searchParams.delete('refresh');
            if (value) {
                url.searchParams.set(periodParam(key), value);
            } else {
                url.searchParams.delete(periodParam(key));
            }
            window.location.href = url.toString();
        }

        function formatDateLabel(value) {
            const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || '');
            if (!match) {
                return '-';
            }
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

            return match[3] + ' ' + months[parseInt(match[2], 10) - 1] + ' ' + match[1].slice(2);
        }

        function openDateModal(header) {
            if (!dateModal || !header.dataset.periodKey) {
                return;
            }
            dateModalHeader = header;
            dateModalSource = header;
            const current = header.dataset.period || '';
            const label = header.querySelector('.prognosa-column-label');
            dateBadge.textContent = (label ? label.textContent.trim() : header.dataset.periodKey)
                + (header.dataset.periodKey === 'current' ? ' (Posisi Terakhir)' : '');

            datePreset.innerHTML = '';
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = '-- Pilih tanggal snapshot --';
            datePreset.appendChild(defaultOption);
            periodOptions.forEach(function (option) {
                const element = document.createElement('option');
                element.value = option.value;
                element.textContent = option.label;
                datePreset.appendChild(element);
            });
            datePreset.value = periodOptions.some(function (option) { return option.value === current; }) ? current : '';
            dateCustom.value = current;
            dateInfo.textContent = 'Tanggal aktif: ' + formatDateLabel(current)
                + (header.dataset.customPeriod === '1' ? ' (custom)' : ' (default)');

            dateModal.hidden = false;
            tableWrap.closest('.prognosa-workspace').inert = true;
            document.body.classList.add('prognosa-modal-open');
            window.requestAnimationFrame(function () {
                datePreset.focus();
            });
        }

        function closeDateModal() {
            if (!dateModal || dateModal.hidden) {
                return;
            }
            dateModal.hidden = true;
            tableWrap.closest('.prognosa-workspace').inert = false;
            document.body.classList.remove('prognosa-modal-open');
            if (dateModalSource && typeof dateModalSource.focus === 'function') {
                dateModalSource.focus();
            }
        }

        if (dateModal) {
            table.querySelectorAll('th[data-period-key]').forEach(function (header) {
                header.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        openDateModal(header);
                    }
                });
            });
            datePreset.addEventListener('change', function () {
                if (datePreset.value) {
                    dateCustom.value = datePreset.value;
                }
            });
            dateCustom.addEventListener('input', function () {
                datePreset.value = periodOptions.some(function (option) { return option.value === dateCustom.value; })
                    ? dateCustom.value
                    : '';
            });
            dateModal.querySelectorAll('[data-date-close]').forEach(function (button) {
                button.addEventListener('click', closeDateModal);
            });
            dateModal.addEventListener('click', function (event) {
                if (event.target === dateModal) {
                    closeDateModal();
                }
            });
            document.getElementById('prognosa-date-apply').addEventListener('click', function () {
                const chosen = dateCustom.value || datePreset.value;
                if (!chosen || !dateModalHeader) {
                    dateCustom.focus();

                    return;
                }
                applyPeriod(dateModalHeader.dataset.periodKey, chosen);
            });
            document.getElementById('prognosa-date-reset').addEventListener('click', function () {
                if (dateModalHeader) {
                    applyPeriod(dateModalHeader.dataset.periodKey, '');
                }
            });
            document.addEventListener('keydown', function (event) {
                if (dateModal.hidden) {
                    return;
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeDateModal();
                    return;
                }
                if (event.key === 'Tab') {
                    const controls = Array.from(dateModal.querySelectorAll('button:not([disabled]), select:not([disabled]), input:not([disabled])'));
                    const first = controls[0];
                    const last = controls[controls.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                }
            });
        }

        // ---------- Export Excel ----------
        const exportExcelLink = document.getElementById('prognosa-export-excel');
        if (exportExcelLink) {
            exportExcelLink.addEventListener('click', function (event) {
                event.preventDefault();
                const url = new URL(exportExcelLink.dataset.exportUrl, window.location.origin);
                const current = new URLSearchParams(window.location.search);
                ['posisi_terakhir', 'yoy_period', 'ytd_period', 'm2_period', 'mtm_period', 'mtd_period', 'h1_period']
                    .forEach(function (name) {
                        if (current.get(name)) {
                            url.searchParams.set(name, current.get(name));
                        }
                    });
                url.searchParams.set('sheet', tableWrap.dataset.sheet || 'area');
                url.searchParams.set('week', tableWrap.dataset.week || '');
                if (hiddenColumns.size > 0) {
                    url.searchParams.set('hidden_columns', Array.from(hiddenColumns).join(','));
                }
                if (hiddenRows.size > 0) {
                    url.searchParams.set('hidden_rows', Array.from(hiddenRows).join(','));
                }
                window.location.href = url.toString();
            });
        }

        // ---------- Export A4 (PNG, portrait) ----------
        const exportA4Button = document.getElementById('prognosa-export-a4');
        const A4_EXPORT = { width: 4960, height: 7016, marginX: 150, marginTop: 118, headerHeight: 290, footerHeight: 130 };

        function waitFrame() {
            return new Promise(function (resolve) { window.requestAnimationFrame(function () { resolve(); }); });
        }

        function drawEllipsis(ctx, text, x, y, maxWidth) {
            let value = String(text || '');
            if (ctx.measureText(value).width <= maxWidth) {
                ctx.fillText(value, x, y);

                return;
            }
            while (value.length > 0 && ctx.measureText(value + '...').width > maxWidth) {
                value = value.slice(0, -1);
            }
            ctx.fillText(value + '...', x, y);
        }

        function drawA4Frame(ctx, meta, page, pages) {
            const width = A4_EXPORT.width;
            const marginX = A4_EXPORT.marginX;
            const marginTop = A4_EXPORT.marginTop;
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, width, A4_EXPORT.height);
            ctx.fillStyle = '#1d4f8a';
            ctx.fillRect(0, 0, width, 32);

            ctx.fillStyle = '#0f233c';
            ctx.font = 'bold 84px "Inter", "Segoe UI", Arial, sans-serif';
            ctx.fillText('Weekly Prognosa - ' + meta.sheet, marginX, marginTop + 60);

            ctx.fillStyle = '#5b6e84';
            ctx.font = '600 42px "Inter", "Segoe UI", Arial, sans-serif';
            drawEllipsis(ctx, 'Wilayah: ' + meta.sheet + '   |   Posisi: ' + meta.latestDate + '   |   ' + meta.week, marginX, marginTop + 135, width - marginX * 2);

            ctx.strokeStyle = '#dde5ef';
            ctx.lineWidth = 5;
            ctx.beginPath();
            ctx.moveTo(marginX, marginTop + 190);
            ctx.lineTo(width - marginX, marginTop + 190);
            ctx.stroke();

            ctx.strokeStyle = '#dde5ef';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(marginX, A4_EXPORT.height - 120);
            ctx.lineTo(width - marginX, A4_EXPORT.height - 120);
            ctx.stroke();

            ctx.fillStyle = '#94a3b8';
            ctx.font = '600 32px "Inter", "Segoe UI", Arial, sans-serif';
            const stamp = new Date().toLocaleString('id-ID', {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit',
            }).replace(/\//g, '-');
            ctx.fillText('Weekly Prognosa A-Six Generated: ' + stamp, marginX, A4_EXPORT.height - 60);
            ctx.textAlign = 'right';
            ctx.font = 'bold 36px "Inter", "Segoe UI", Arial, sans-serif';
            ctx.fillStyle = '#5b6e84';
            ctx.fillText('Halaman ' + page + ' dari ' + pages, width - marginX, A4_EXPORT.height - 60);
            ctx.textAlign = 'left';
        }

        async function exportA4() {
            if (typeof window.html2canvas !== 'function') {
                window.alert('Pustaka export belum termuat. Muat ulang halaman lalu coba lagi.');

                return;
            }

            const original = exportA4Button.innerHTML;
            exportA4Button.disabled = true;
            exportA4Button.innerHTML = '<span class="prognosa-button__badge" aria-hidden="true"><i class="fas fa-spinner fa-spin"></i></span><span>Menyusun A4…</span>';
            const stage = document.createElement('div');
            stage.className = 'prognosa-page prognosa-a4-stage';

            try {
                // Salin tabel apa adanya (kolom/baris tersembunyi dibuang), lepas sticky agar utuh.
                const shownHeaders = columnHeaders.filter(function (column) { return !column.hidden; });
                const widths = shownHeaders.map(function (column) { return Math.round(column.getBoundingClientRect().width); });
                const tableWidth = widths.reduce(function (sum, value) { return sum + value; }, 0);
                if (shownHeaders.length === 0 || tableWidth <= 0) {
                    throw new Error('Tidak ada kolom yang ditampilkan.');
                }

                const clone = table.cloneNode(true);
                clone.querySelectorAll('[hidden]').forEach(function (element) { element.remove(); });
                clone.querySelectorAll('caption').forEach(function (element) { element.remove(); });
                clone.querySelectorAll('[title]').forEach(function (element) { element.removeAttribute('title'); });
                clone.querySelectorAll('.prognosa-week-trigger').forEach(function (element) { element.remove(); });
                clone.querySelectorAll('.is-row-selected').forEach(function (element) { element.classList.remove('is-row-selected'); });
                clone.style.width = tableWidth + 'px';
                clone.style.minWidth = tableWidth + 'px';
                const colgroup = clone.querySelector('colgroup');
                colgroup.innerHTML = widths.map(function (width) { return '<col style="width:' + width + 'px">'; }).join('');
                stage.appendChild(clone);
                document.body.appendChild(stage);
                await waitFrame();

                const bodyRows = Array.from(clone.querySelectorAll('tbody tr'));
                if (bodyRows.length === 0) {
                    throw new Error('Tidak ada baris yang ditampilkan.');
                }
                const headHeight = clone.querySelector('thead').getBoundingClientRect().height;
                const rowHeights = bodyRows.map(function (row) { return row.getBoundingClientRect().height; });

                const drawWidthMax = A4_EXPORT.width - A4_EXPORT.marginX * 2;
                const scale = Math.min(4, drawWidthMax / tableWidth);
                const availableHeight = (A4_EXPORT.height - A4_EXPORT.marginTop - A4_EXPORT.headerHeight - A4_EXPORT.footerHeight) / scale;

                // Bagi baris ke beberapa halaman tanpa meninggalkan judul seksi di dasar halaman.
                const pages = [];
                let current = { rows: [], height: headHeight };
                bodyRows.forEach(function (row, index) {
                    if (current.rows.length > 0 && current.height + rowHeights[index] > availableHeight) {
                        const carry = [];
                        while (current.rows.length > 1 && /prognosa-row--(section|category|subcategory)/.test(current.rows[current.rows.length - 1].className)) {
                            carry.unshift(current.rows.pop());
                        }
                        pages.push(current);
                        current = { rows: carry, height: headHeight + carry.reduce(function (sum, item) { return sum + item.getBoundingClientRect().height; }, 0) };
                    }
                    current.rows.push(row);
                    current.height += rowHeights[index];
                });
                if (current.rows.length > 0) {
                    pages.push(current);
                }

                const meta = {
                    sheet: tableWrap.dataset.sheetLabel || 'Area 6',
                    latestDate: tableWrap.dataset.latestDate || '-',
                    week: tableWrap.dataset.weekLabel || '',
                };
                const stamp = new Date().toISOString().slice(0, 10);
                const slug = (tableWrap.dataset.sheet || 'area');

                for (let index = 0; index < pages.length; index += 1) {
                    const pageTable = clone.cloneNode(false);
                    pageTable.appendChild(clone.querySelector('colgroup').cloneNode(true));
                    pageTable.appendChild(clone.querySelector('thead').cloneNode(true));
                    const tbody = document.createElement('tbody');
                    pages[index].rows.forEach(function (row) { tbody.appendChild(row.cloneNode(true)); });
                    pageTable.appendChild(tbody);

                    const holder = document.createElement('div');
                    holder.style.cssText = 'display:inline-block;background:#ffffff;width:' + tableWidth + 'px;';
                    holder.appendChild(pageTable);
                    stage.appendChild(holder);
                    await waitFrame();

                    const captured = await window.html2canvas(holder, {
                        scale: scale,
                        useCORS: true,
                        backgroundColor: '#ffffff',
                        logging: false,
                    });
                    stage.removeChild(holder);

                    const canvas = document.createElement('canvas');
                    canvas.width = A4_EXPORT.width;
                    canvas.height = A4_EXPORT.height;
                    const ctx = canvas.getContext('2d');
                    drawA4Frame(ctx, meta, index + 1, pages.length);
                    const drawWidth = captured.width;
                    const x = A4_EXPORT.marginX + Math.max(0, (drawWidthMax - drawWidth) / 2);
                    ctx.drawImage(captured, x, A4_EXPORT.marginTop + A4_EXPORT.headerHeight);

                    const link = document.createElement('a');
                    link.download = 'Weekly-Prognosa_' + slug + '_' + (meta.week || 'W').replace(/\s+/g, '') + '_' + stamp
                        + (pages.length > 1 ? '_Hal-' + (index + 1) : '') + '.png';
                    link.href = canvas.toDataURL('image/png');
                    link.click();
                    await waitFrame();
                }
            } catch (error) {
                console.error('Export A4 Weekly Prognosa gagal.', error);
                window.alert('Gagal menyusun export A4. Silakan coba lagi.');
            } finally {
                if (stage.parentNode) {
                    stage.parentNode.removeChild(stage);
                }
                exportA4Button.disabled = false;
                exportA4Button.innerHTML = original;
            }
        }

        if (exportA4Button) {
            exportA4Button.addEventListener('click', exportA4);
        }

        let resizeFrame = null;
        window.addEventListener('resize', function () {
            if (resizeFrame !== null) {
                window.cancelAnimationFrame(resizeFrame);
            }
            resizeFrame = window.requestAnimationFrame(function () {
                applyColumnMode(tableWrap.dataset.viewMode || 'all');
                syncHorizontalScrollState();
                resizeFrame = null;
            });
        }, { passive: true });
    });
</script>
@endsection
