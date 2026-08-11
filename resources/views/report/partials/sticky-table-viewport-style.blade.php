@php
    $wrapperSelector = $wrapperSelector ?? '.table-container';
    $tableSelector = $tableSelector ?? '.table-report';
@endphp

{{ $wrapperSelector }} {
    --table-sticky-top: 0px;
    --table-scrollbar-space: 0px;
    height: auto !important;
    max-height: none !important;
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
    overscroll-behavior-y: auto;
    position: relative;
    top: auto;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 10px 24px -20px rgba(15, 23, 42, 0.28);
    z-index: 1;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
    scrollbar-gutter: auto;
    box-sizing: border-box;
    padding-bottom: var(--table-scrollbar-space);
}

{{ $wrapperSelector }}::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

{{ $wrapperSelector }}::-webkit-scrollbar-track {
    background: transparent;
}

{{ $wrapperSelector }}::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 999px;
    border: 2px solid transparent;
    background-clip: content-box;
}

{{ $wrapperSelector }} {{ $tableSelector }} thead.sticky-top {
    position: static;
    top: auto;
    z-index: auto;
}

{{ $wrapperSelector }} {{ $tableSelector }} thead th {
    position: sticky;
    top: var(--table-head-top, 0px) !important;
    background-color: inherit;
    background-clip: padding-box;
    line-height: 1.35;
    box-shadow: inset 0 -1px 0 rgba(148, 163, 184, 0.24), 0 10px 16px -18px rgba(15, 23, 42, 0.45);
    z-index: 9;
}

{{ $wrapperSelector }} {{ $tableSelector }} tbody td,
{{ $wrapperSelector }} {{ $tableSelector }} tbody th {
    line-height: 1.45;
}

{{ $wrapperSelector }} {{ $tableSelector }} tbody tr:nth-child(even):not(.row-total):not(.row-total-blue) td,
{{ $wrapperSelector }} {{ $tableSelector }} tbody tr:nth-child(even):not(.row-total):not(.row-total-blue) th {
    background-color: rgba(248, 250, 252, 0.96);
}

@media (max-width: 1180px), (max-height: 760px) {
    {{ $wrapperSelector }} {
        position: relative;
        top: auto;
        height: auto !important;
        max-height: none !important;
        overflow-x: auto;
        overflow-y: visible;
        overscroll-behavior-x: contain;
        overscroll-behavior-y: auto;
    }
}
