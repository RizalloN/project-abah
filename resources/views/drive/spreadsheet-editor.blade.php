@extends('layouts.admin')

@section('title', 'Editor Spreadsheet - Bank Pipeline')

@section('styles')
<style>
    :root {
        --asix-sheet-navy: #082c52;
        --asix-sheet-navy-2: #0d3d6b;
        --asix-sheet-cyan: #18a7c8;
        --asix-sheet-cyan-soft: #e9faff;
        --asix-sheet-amber: #f3ad32;
        --asix-sheet-ink: #14253b;
        --asix-sheet-muted: #64748b;
        --asix-sheet-line: #d8e1eb;
        --asix-sheet-head: #edf3f8;
        --asix-sheet-selected: rgba(24, 167, 200, .13);
        --asix-sheet-row-head: 52px;
        --asix-sheet-col-head: 30px;
    }

    .asix-sheet-swal {
        width: min(430px, calc(100vw - 24px)) !important;
        border: 1px solid var(--asix-sheet-line);
        border-radius: 10px !important;
        box-shadow: 0 22px 55px -28px rgba(15, 23, 42, .46) !important;
    }

    .asix-sheet-swal-title {
        color: var(--asix-sheet-ink) !important;
        font-size: 1.18rem !important;
        letter-spacing: 0 !important;
    }

    .asix-sheet-swal-confirm,
    .asix-sheet-swal-cancel {
        min-height: 38px;
        border: 0;
        border-radius: 6px;
        padding: .55rem .95rem;
        font-size: .82rem;
        font-weight: 700;
    }

    .asix-sheet-swal-confirm {
        color: #fff;
        background: #b91c1c;
    }

    .asix-sheet-swal-cancel {
        color: #334155;
        background: #e2e8f0;
    }

    body.drive-spreadsheet-active {
        overflow: hidden;
    }

    body.drive-spreadsheet-active .content-wrapper {
        overflow: hidden !important;
    }

    body.drive-spreadsheet-active .content-wrapper > .content {
        padding: 0 !important;
    }

    body.drive-spreadsheet-active .content-wrapper > .content > .container-fluid {
        max-width: none !important;
        padding: 0 !important;
    }

    .asix-sheet-app,
    .asix-sheet-app * {
        box-sizing: border-box;
    }

    .asix-sheet-app {
        height: calc(100dvh - 64px);
        min-height: 420px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #f7fafc;
        color: var(--asix-sheet-ink);
        font-family: "Inter", "Segoe UI", sans-serif;
        isolation: isolate;
    }

    .asix-sheet-topbar {
        min-height: 58px;
        display: flex;
        align-items: center;
        gap: .8rem;
        padding: .55rem .9rem;
        color: #fff;
        background:
            radial-gradient(circle at 82% -30%, rgba(24, 167, 200, .54), transparent 34%),
            linear-gradient(112deg, var(--asix-sheet-navy), var(--asix-sheet-navy-2));
        border-bottom: 3px solid var(--asix-sheet-amber);
        box-shadow: 0 6px 20px rgba(8, 44, 82, .19);
        z-index: 30;
    }

    .asix-sheet-back,
    .asix-sheet-icon-button,
    .asix-sheet-action,
    .asix-sheet-tool,
    .asix-sheet-tab-add {
        border: 0;
        outline: 0;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background-color .16s ease, border-color .16s ease, color .16s ease, transform .16s ease;
    }

    .asix-sheet-back {
        flex: 0 0 38px;
        height: 38px;
        border-radius: 11px;
        color: #dff7ff;
        background: rgba(255, 255, 255, .11);
        border: 1px solid rgba(255, 255, 255, .19);
        text-decoration: none !important;
    }

    .asix-sheet-back:hover {
        color: #fff;
        background: rgba(255, 255, 255, .2);
        transform: translateX(-1px);
    }

    .asix-sheet-file-mark {
        flex: 0 0 38px;
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 11px;
        color: var(--asix-sheet-navy);
        background: linear-gradient(145deg, #fff7d8, var(--asix-sheet-amber));
        box-shadow: 0 5px 14px rgba(0, 0, 0, .15);
    }

    .asix-sheet-file-copy {
        min-width: 0;
        flex: 1 1 auto;
    }

    .asix-sheet-file-name {
        margin: 0;
        overflow: hidden;
        color: #fff;
        font-size: .95rem;
        font-weight: 800;
        line-height: 1.25;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .asix-sheet-file-meta {
        display: flex;
        align-items: center;
        gap: .45rem;
        min-width: 0;
        margin-top: .15rem;
        color: #bdeaf5;
        font-size: .69rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .asix-sheet-status-dot {
        width: 7px;
        height: 7px;
        flex: 0 0 7px;
        border-radius: 50%;
        background: #6ee7b7;
        box-shadow: 0 0 0 3px rgba(110, 231, 183, .14);
    }

    .asix-sheet-status-dot.is-dirty {
        background: var(--asix-sheet-amber);
        box-shadow: 0 0 0 3px rgba(243, 173, 50, .16);
    }

    .asix-sheet-status-dot.is-saving {
        background: #67e8f9;
        animation: asixSheetPulse 1s infinite alternate;
    }

    .asix-sheet-status-dot.is-error {
        background: #fb7185;
        box-shadow: 0 0 0 3px rgba(251, 113, 133, .18);
    }

    @keyframes asixSheetPulse {
        to { opacity: .35; }
    }

    .asix-sheet-top-actions {
        display: flex;
        align-items: center;
        gap: .45rem;
        flex: 0 0 auto;
    }

    .asix-sheet-icon-button {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        color: #e8faff;
        background: rgba(255, 255, 255, .1);
        border: 1px solid rgba(255, 255, 255, .18);
    }

    .asix-sheet-icon-button:hover {
        color: #fff;
        background: rgba(255, 255, 255, .19);
    }

    .asix-sheet-action {
        min-height: 38px;
        gap: .45rem;
        padding: .48rem .85rem;
        border-radius: 10px;
        color: var(--asix-sheet-navy);
        background: linear-gradient(145deg, #ffd978, var(--asix-sheet-amber));
        border: 1px solid rgba(255, 255, 255, .28);
        font-size: .76rem;
        font-weight: 800;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .14);
    }

    .asix-sheet-action:hover {
        color: #031d38;
        transform: translateY(-1px);
    }

    .asix-sheet-action:disabled,
    .asix-sheet-tool:disabled,
    .asix-sheet-icon-button:disabled {
        cursor: not-allowed;
        opacity: .42;
        transform: none;
    }

    .asix-sheet-menubar,
    .asix-sheet-toolbar {
        display: flex;
        align-items: center;
        flex: 0 0 auto;
        overflow-x: auto;
        overflow-y: hidden;
        scrollbar-width: thin;
        scrollbar-color: #b8c7d8 transparent;
    }

    .asix-sheet-menubar {
        min-height: 34px;
        gap: .12rem;
        padding: .2rem .65rem;
        background: #fff;
        border-bottom: 1px solid var(--asix-sheet-line);
    }

    .asix-sheet-menu {
        position: relative;
        flex: 0 0 auto;
    }

    .asix-sheet-menu > summary {
        list-style: none;
        cursor: pointer;
        padding: .35rem .57rem;
        border-radius: 7px;
        color: #34465d;
        font-size: .73rem;
        font-weight: 700;
        user-select: none;
    }

    .asix-sheet-menu > summary::-webkit-details-marker {
        display: none;
    }

    .asix-sheet-menu[open] > summary,
    .asix-sheet-menu > summary:hover {
        color: var(--asix-sheet-navy);
        background: var(--asix-sheet-cyan-soft);
    }

    .asix-sheet-menu-panel {
        position: fixed;
        z-index: 100;
        width: 226px;
        margin-top: .25rem;
        padding: .38rem;
        border-radius: 12px;
        background: #fff;
        border: 1px solid var(--asix-sheet-line);
        box-shadow: 0 18px 42px rgba(8, 44, 82, .19);
    }

    .asix-sheet-menu-item {
        width: 100%;
        min-height: 34px;
        display: flex;
        align-items: center;
        gap: .65rem;
        padding: .4rem .55rem;
        border: 0;
        border-radius: 8px;
        color: #30435a;
        background: transparent;
        font-size: .72rem;
        font-weight: 650;
        text-align: left;
        cursor: pointer;
    }

    .asix-sheet-menu-item i {
        width: 18px;
        color: var(--asix-sheet-cyan);
        text-align: center;
    }

    .asix-sheet-menu-item .shortcut {
        margin-left: auto;
        color: #8a9aae;
        font-size: .65rem;
    }

    .asix-sheet-menu-item:hover {
        color: var(--asix-sheet-navy);
        background: #f0f7fb;
    }

    .asix-sheet-menu-item:disabled {
        cursor: not-allowed;
        opacity: .42;
    }

    .asix-sheet-menu-separator {
        height: 1px;
        margin: .3rem .2rem;
        background: #e7edf3;
    }

    .asix-sheet-toolbar {
        min-height: 43px;
        gap: .3rem;
        padding: .3rem .65rem;
        background: #f9fbfd;
        border-bottom: 1px solid var(--asix-sheet-line);
    }

    .asix-sheet-tool {
        flex: 0 0 auto;
        min-width: 34px;
        height: 34px;
        gap: .32rem;
        padding: 0 .5rem;
        border-radius: 8px;
        color: #34465d;
        background: transparent;
        border: 1px solid transparent;
        font-size: .71rem;
        font-weight: 750;
    }

    .asix-sheet-tool:hover,
    .asix-sheet-tool.is-active {
        color: var(--asix-sheet-navy);
        background: #eaf7fb;
        border-color: #bce9f4;
    }

    .asix-sheet-tool .tool-letter {
        font-family: Georgia, serif;
        font-size: .88rem;
    }

    .asix-sheet-tool .tool-letter.is-italic {
        font-style: italic;
    }

    .asix-sheet-tool .tool-letter.is-underline {
        text-decoration: underline;
    }

    .asix-sheet-toolbar-divider {
        width: 1px;
        height: 23px;
        flex: 0 0 1px;
        margin: 0 .2rem;
        background: #d7e0e9;
    }

    .asix-sheet-select {
        height: 34px;
        flex: 0 0 auto;
        min-width: 110px;
        padding: 0 1.8rem 0 .55rem;
        border-radius: 8px;
        color: #34465d;
        background: #fff;
        border: 1px solid #ccd8e4;
        outline: 0;
        font-size: .7rem;
        font-weight: 650;
    }

    .asix-sheet-select:focus {
        border-color: var(--asix-sheet-cyan);
        box-shadow: 0 0 0 3px rgba(24, 167, 200, .12);
    }

    .asix-sheet-select.is-narrow {
        min-width: 72px;
    }

    .asix-sheet-color-tool {
        position: relative;
        flex: 0 0 auto;
        width: 34px;
        height: 34px;
    }

    .asix-sheet-color-tool label {
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0;
        border-radius: 8px;
        color: #34465d;
        cursor: pointer;
    }

    .asix-sheet-color-tool label:hover {
        background: #eaf7fb;
    }

    .asix-sheet-color-tool input {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }

    .asix-sheet-color-swatch {
        position: absolute;
        right: 7px;
        bottom: 4px;
        left: 7px;
        height: 3px;
        border-radius: 3px;
        background: #14253b;
    }

    .asix-sheet-formula {
        min-height: 38px;
        display: flex;
        align-items: stretch;
        flex: 0 0 auto;
        background: #fff;
        border-bottom: 1px solid #cbd7e3;
    }

    .asix-sheet-name-box {
        width: 92px;
        flex: 0 0 92px;
        padding: 0 .65rem;
        border: 0;
        border-right: 1px solid var(--asix-sheet-line);
        color: var(--asix-sheet-navy);
        background: #f8fbfd;
        outline: 0;
        font-size: .72rem;
        font-weight: 800;
        text-align: center;
        text-transform: uppercase;
    }

    .asix-sheet-fx {
        width: 42px;
        flex: 0 0 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--asix-sheet-cyan);
        font-family: Georgia, serif;
        font-size: .86rem;
        font-style: italic;
        font-weight: 800;
        border-right: 1px solid var(--asix-sheet-line);
    }

    .asix-sheet-formula-input {
        min-width: 0;
        flex: 1 1 auto;
        padding: 0 .75rem;
        border: 0;
        outline: 0;
        color: #1e3148;
        background: #fff;
        font-family: "Cascadia Mono", Consolas, monospace;
        font-size: .75rem;
    }

    .asix-sheet-banner {
        display: none;
        flex: 0 0 auto;
        align-items: center;
        gap: .55rem;
        min-height: 34px;
        padding: .4rem .8rem;
        font-size: .71rem;
        font-weight: 650;
    }

    .asix-sheet-banner.is-visible {
        display: flex;
    }

    .asix-sheet-banner.is-warning {
        color: #7b4b03;
        background: #fff8dc;
        border-bottom: 1px solid #f4d884;
    }

    .asix-sheet-banner.is-error {
        color: #8d2332;
        background: #fff0f2;
        border-bottom: 1px solid #fecdd3;
    }

    .asix-sheet-workspace {
        min-height: 0;
        position: relative;
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        overflow: hidden;
        background: #fff;
    }

    .asix-sheet-grid-shell {
        min-height: 0;
        position: relative;
        display: grid;
        grid-template-columns: var(--asix-sheet-row-head) minmax(0, 1fr);
        grid-template-rows: var(--asix-sheet-col-head) minmax(0, 1fr);
        flex: 1 1 auto;
        overflow: hidden;
        background: #fff;
        border-bottom: 1px solid #bdcbd8;
    }

    .asix-sheet-corner {
        grid-area: 1 / 1;
        z-index: 9;
        position: relative;
        background: linear-gradient(145deg, #e5edf4, #f7fafc);
        border-right: 1px solid #aebdca;
        border-bottom: 1px solid #aebdca;
    }

    .asix-sheet-corner::after {
        content: "";
        position: absolute;
        right: 4px;
        bottom: 4px;
        width: 0;
        height: 0;
        border-bottom: 7px solid #88a0b4;
        border-left: 7px solid transparent;
    }

    .asix-sheet-column-viewport {
        grid-area: 1 / 2;
        z-index: 8;
        position: relative;
        overflow: hidden;
        background: var(--asix-sheet-head);
        border-bottom: 1px solid #aebdca;
    }

    .asix-sheet-row-viewport {
        grid-area: 2 / 1;
        z-index: 8;
        position: relative;
        overflow: hidden;
        background: var(--asix-sheet-head);
        border-right: 1px solid #aebdca;
    }

    .asix-sheet-column-track,
    .asix-sheet-row-track {
        position: relative;
        will-change: transform;
    }

    .asix-sheet-column-track {
        height: var(--asix-sheet-col-head);
    }

    .asix-sheet-row-track {
        width: var(--asix-sheet-row-head);
    }

    .asix-sheet-column-header,
    .asix-sheet-row-header {
        position: absolute;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        color: #53677c;
        background: var(--asix-sheet-head);
        border-right: 1px solid #c3cfda;
        border-bottom: 1px solid #c3cfda;
        font-size: .65rem;
        font-weight: 750;
        user-select: none;
        cursor: default;
    }

    .asix-sheet-column-header {
        top: 0;
        height: var(--asix-sheet-col-head);
    }

    .asix-sheet-row-header {
        left: 0;
        width: var(--asix-sheet-row-head);
    }

    .asix-sheet-column-header.is-selected,
    .asix-sheet-row-header.is-selected {
        color: #fff;
        background: var(--asix-sheet-navy-2);
    }

    .asix-sheet-column-header::after,
    .asix-sheet-row-header::after {
        content: "";
        position: absolute;
        z-index: 2;
        opacity: 0;
        background: var(--asix-sheet-cyan);
        transition: opacity .15s ease;
    }

    .asix-sheet-column-header::after {
        top: 0;
        right: 0;
        width: 4px;
        height: 100%;
        cursor: col-resize;
    }

    .asix-sheet-row-header::after {
        right: 0;
        bottom: 0;
        width: 100%;
        height: 4px;
        cursor: row-resize;
    }

    .asix-sheet-column-header:hover::after,
    .asix-sheet-row-header:hover::after {
        opacity: .75;
    }

    .asix-sheet-body-viewport {
        grid-area: 2 / 2;
        min-width: 0;
        min-height: 0;
        position: relative;
        overflow: auto;
        outline: 0;
        overscroll-behavior: contain;
        scrollbar-color: #9fb3c4 #eef3f7;
        scrollbar-width: auto;
    }

    .asix-sheet-body-canvas {
        position: relative;
        min-width: 100%;
        min-height: 100%;
        background-color: #fff;
        background-image:
            linear-gradient(to right, rgba(216, 225, 235, .72) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(216, 225, 235, .72) 1px, transparent 1px);
        background-size: 104px 28px;
        transform: translateZ(0);
    }

    .asix-sheet-cell {
        position: absolute;
        z-index: 1;
        display: flex;
        align-items: center;
        overflow: hidden;
        padding: 2px 6px;
        color: #1e293b;
        background: #fff;
        border-right: 1px solid var(--asix-sheet-line);
        border-bottom: 1px solid var(--asix-sheet-line);
        font-size: 12px;
        line-height: 1.25;
        white-space: nowrap;
        cursor: cell;
        user-select: none;
    }

    .asix-sheet-cell.is-wrap {
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .asix-sheet-cell.is-selected {
        outline: 1px solid rgba(24, 167, 200, .62);
        outline-offset: -1px;
    }

    .asix-sheet-cell.is-active {
        z-index: 7 !important;
        overflow: visible;
        outline: 2px solid var(--asix-sheet-cyan);
        outline-offset: -2px;
    }

    .asix-sheet-cell.is-active::after {
        content: "";
        position: absolute;
        right: -3px;
        bottom: -3px;
        width: 7px;
        height: 7px;
        background: var(--asix-sheet-cyan);
        border: 1px solid #fff;
    }

    .asix-sheet-cell.is-frozen {
        z-index: 4;
        box-shadow: 1px 1px 0 #93aabe;
    }

    .asix-sheet-cell-editor {
        position: absolute;
        z-index: 20;
        display: none;
        min-width: 50px;
        min-height: 26px;
        padding: 2px 5px;
        color: #14253b;
        background: #fff;
        border: 2px solid var(--asix-sheet-cyan);
        border-radius: 0;
        outline: 0;
        box-shadow: 0 5px 16px rgba(8, 44, 82, .18);
        font: 12px/1.25 "Cascadia Mono", Consolas, monospace;
    }

    .asix-sheet-cell-editor.is-visible {
        display: block;
    }

    .asix-sheet-empty,
    .asix-sheet-loading {
        position: absolute;
        inset: 0;
        z-index: 25;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        background: rgba(247, 250, 252, .94);
    }

    .asix-sheet-empty[hidden],
    .asix-sheet-loading[hidden] {
        display: none;
    }

    .asix-sheet-state-card {
        width: min(420px, 100%);
        padding: 1.4rem;
        border-radius: 18px;
        color: #34465d;
        background: #fff;
        border: 1px solid #dbe5ed;
        box-shadow: 0 18px 45px rgba(8, 44, 82, .12);
        text-align: center;
    }

    .asix-sheet-state-icon {
        width: 48px;
        height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: .75rem;
        border-radius: 15px;
        color: var(--asix-sheet-navy);
        background: linear-gradient(145deg, #dff8ff, #bcecf7);
        font-size: 1.2rem;
    }

    .asix-sheet-state-card h2 {
        margin: 0 0 .35rem;
        color: var(--asix-sheet-navy);
        font-size: .95rem;
        font-weight: 800;
    }

    .asix-sheet-state-card p {
        margin: 0;
        color: #6b7d90;
        font-size: .73rem;
        line-height: 1.6;
    }

    .asix-sheet-spinner {
        width: 21px;
        height: 21px;
        display: inline-block;
        margin-right: .45rem;
        border: 3px solid #d3edf3;
        border-top-color: var(--asix-sheet-cyan);
        border-radius: 50%;
        vertical-align: middle;
        animation: asixSheetSpin .75s linear infinite;
    }

    @keyframes asixSheetSpin {
        to { transform: rotate(360deg); }
    }

    .asix-sheet-bottom {
        min-height: 38px;
        display: flex;
        align-items: stretch;
        flex: 0 0 auto;
        background: #f2f6f9;
        border-top: 1px solid #fff;
    }

    .asix-sheet-tabs {
        min-width: 0;
        display: flex;
        align-items: flex-end;
        gap: 2px;
        flex: 1 1 auto;
        overflow-x: auto;
        padding: 4px 8px 0;
        scrollbar-width: thin;
    }

    .asix-sheet-tab {
        min-width: 90px;
        max-width: 190px;
        height: 33px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        overflow: hidden;
        padding: 0 .8rem;
        border: 1px solid transparent;
        border-bottom: 0;
        border-radius: 9px 9px 0 0;
        color: #53677c;
        background: transparent;
        font-size: .7rem;
        font-weight: 750;
        text-overflow: ellipsis;
        white-space: nowrap;
        cursor: pointer;
    }

    .asix-sheet-tab:hover {
        color: var(--asix-sheet-navy);
        background: #e6f2f7;
    }

    .asix-sheet-tab.is-active {
        position: relative;
        color: var(--asix-sheet-navy);
        background: #fff;
        border-color: #cdd9e3;
    }

    .asix-sheet-tab.is-active::before {
        content: "";
        position: absolute;
        right: 12px;
        bottom: 0;
        left: 12px;
        height: 3px;
        border-radius: 3px 3px 0 0;
        background: var(--asix-sheet-amber);
    }

    .asix-sheet-tab-add {
        width: 32px;
        height: 32px;
        flex: 0 0 32px;
        margin: 3px 3px 3px 0;
        border-radius: 9px;
        color: var(--asix-sheet-cyan);
        background: transparent;
        font-size: .75rem;
    }

    .asix-sheet-tab-add:hover {
        color: var(--asix-sheet-navy);
        background: #dff4f9;
    }

    .asix-sheet-footer-status {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        padding: 0 .8rem;
        color: #65778a;
        border-left: 1px solid #d6e0e8;
        font-size: .66rem;
        font-weight: 650;
        white-space: nowrap;
    }

    .asix-sheet-context {
        position: fixed;
        z-index: 150;
        display: none;
        width: 208px;
        padding: .4rem;
        border-radius: 12px;
        background: #fff;
        border: 1px solid #d4dfe8;
        box-shadow: 0 16px 42px rgba(8, 44, 82, .2);
    }

    .asix-sheet-context.is-visible {
        display: block;
    }

    .asix-sheet-modal {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(3, 24, 45, .52);
        backdrop-filter: blur(3px);
    }

    .asix-sheet-modal.is-visible {
        display: flex;
    }

    .asix-sheet-dialog {
        width: min(470px, 100%);
        overflow: hidden;
        border-radius: 18px;
        background: #fff;
        border: 1px solid rgba(255, 255, 255, .8);
        box-shadow: 0 25px 70px rgba(0, 0, 0, .25);
    }

    .asix-sheet-dialog-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: .9rem 1rem;
        color: #fff;
        background: linear-gradient(120deg, var(--asix-sheet-navy), var(--asix-sheet-navy-2));
        border-bottom: 3px solid var(--asix-sheet-amber);
    }

    .asix-sheet-dialog-head h2 {
        margin: 0;
        font-size: .88rem;
        font-weight: 800;
    }

    .asix-sheet-dialog-close {
        width: 30px;
        height: 30px;
        border: 0;
        border-radius: 8px;
        color: #d9f6fc;
        background: rgba(255, 255, 255, .1);
        cursor: pointer;
    }

    .asix-sheet-dialog-body {
        padding: 1rem;
    }

    .asix-sheet-field {
        margin-bottom: .75rem;
    }

    .asix-sheet-field label {
        display: block;
        margin: 0 0 .3rem;
        color: #465b70;
        font-size: .68rem;
        font-weight: 750;
    }

    .asix-sheet-field input[type="text"] {
        width: 100%;
        height: 38px;
        padding: 0 .7rem;
        border-radius: 9px;
        color: #23374e;
        background: #fff;
        border: 1px solid #cbd7e2;
        outline: 0;
        font-size: .75rem;
    }

    .asix-sheet-field input[type="text"]:focus {
        border-color: var(--asix-sheet-cyan);
        box-shadow: 0 0 0 3px rgba(24, 167, 200, .12);
    }

    .asix-sheet-check {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        color: #53677c;
        font-size: .7rem;
        font-weight: 650;
    }

    .asix-sheet-dialog-note {
        margin: 0;
        color: #64748b;
        font-size: .72rem;
        line-height: 1.6;
    }

    .asix-sheet-dialog-actions {
        display: flex;
        justify-content: flex-end;
        gap: .5rem;
        padding: .8rem 1rem;
        background: #f7fafc;
        border-top: 1px solid #e1e8ef;
    }

    .asix-sheet-dialog-button {
        min-height: 36px;
        padding: .4rem .8rem;
        border-radius: 9px;
        border: 1px solid #cbd7e2;
        color: #465b70;
        background: #fff;
        font-size: .71rem;
        font-weight: 750;
        cursor: pointer;
    }

    .asix-sheet-dialog-button.is-primary {
        color: #fff;
        background: var(--asix-sheet-navy-2);
        border-color: var(--asix-sheet-navy-2);
    }

    .asix-sheet-dialog-button.is-accent {
        color: #19334b;
        background: var(--asix-sheet-amber);
        border-color: var(--asix-sheet-amber);
    }

    .asix-sheet-dialog-button.is-danger {
        color: #fff;
        background: #be123c;
        border-color: #be123c;
    }

    .asix-sheet-toast-stack {
        position: fixed;
        right: max(1rem, env(safe-area-inset-right));
        bottom: max(1rem, env(safe-area-inset-bottom));
        z-index: 2200;
        width: min(360px, calc(100vw - 2rem));
        display: grid;
        gap: .55rem;
        pointer-events: none;
    }

    .asix-sheet-toast {
        display: flex;
        align-items: flex-start;
        gap: .65rem;
        padding: .75rem .85rem;
        border-radius: 12px;
        color: #fff;
        background: var(--asix-sheet-navy);
        border-left: 4px solid var(--asix-sheet-cyan);
        box-shadow: 0 14px 34px rgba(8, 44, 82, .26);
        font-size: .72rem;
        line-height: 1.5;
        animation: asixSheetToastIn .2s ease-out;
    }

    .asix-sheet-toast.is-error {
        border-left-color: #fb7185;
    }

    .asix-sheet-toast.is-success {
        border-left-color: #6ee7b7;
    }

    @keyframes asixSheetToastIn {
        from { opacity: 0; transform: translateY(8px); }
    }

    @media (max-width: 767.98px) {
        .asix-sheet-app {
            height: calc(100dvh - 64px);
        }

        .asix-sheet-topbar {
            gap: .5rem;
            padding-inline: .55rem;
        }

        .asix-sheet-file-mark {
            display: none;
        }

        .asix-sheet-file-meta .revision-label {
            display: none;
        }

        .asix-sheet-action {
            width: 38px;
            padding: 0;
        }

        .asix-sheet-action span {
            display: none;
        }

        .asix-sheet-toolbar {
            padding-inline: .45rem;
        }

        .asix-sheet-footer-status {
            display: none;
        }

        .asix-sheet-name-box {
            width: 72px;
            flex-basis: 72px;
        }

        :root {
            --asix-sheet-row-head: 44px;
        }
    }

    @media (max-height: 540px) and (orientation: landscape) {
        .asix-sheet-topbar {
            min-height: 46px;
            padding-block: .3rem;
        }

        .asix-sheet-file-mark,
        .asix-sheet-back,
        .asix-sheet-icon-button,
        .asix-sheet-action {
            width: 34px;
            height: 34px;
            min-height: 34px;
            flex-basis: 34px;
        }

        .asix-sheet-app {
            height: calc(100dvh - 48px);
        }

        .asix-sheet-menubar {
            min-height: 30px;
        }

        .asix-sheet-toolbar {
            min-height: 37px;
        }

        .asix-sheet-formula {
            min-height: 34px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .asix-sheet-spinner,
        .asix-sheet-status-dot,
        .asix-sheet-toast {
            animation: none !important;
        }
    }
</style>
@endsection

@section('content')
@php
    $driveFileName = $file->original_name ?? $file->name ?? 'Spreadsheet';
    $resolvedBackUrl = $backUrl ?? route('drive.index');
@endphp
<div class="asix-sheet-app" id="asixSheetApp" data-abah-no-table-guard="1">
    <header class="asix-sheet-topbar">
        <a class="asix-sheet-back" href="{{ $resolvedBackUrl }}" data-sheet-exit aria-label="Kembali ke Bank Pipeline" title="Kembali ke Bank Pipeline">
            <i class="fas fa-arrow-left"></i>
        </a>
        <span class="asix-sheet-file-mark" aria-hidden="true"><i class="fas fa-border-all"></i></span>
        <div class="asix-sheet-file-copy">
            <h1 class="asix-sheet-file-name" id="sheetFileName">{{ $driveFileName }}</h1>
            <div class="asix-sheet-file-meta">
                <span class="asix-sheet-status-dot" id="sheetStatusDot"></span>
                <span id="sheetStatusText">Menyiapkan editor...</span>
                <span class="revision-label" id="sheetRevisionLabel"></span>
            </div>
        </div>
        <div class="asix-sheet-top-actions">
            <a class="asix-sheet-icon-button" href="{{ route('drive.file.download', ['file' => $file]) }}" id="sheetDownloadButton" title="Unduh file asli" aria-label="Unduh file asli">
                <i class="fas fa-download"></i>
            </a>
            <button type="button" class="asix-sheet-action" id="sheetSaveButton" disabled>
                <i class="fas fa-save"></i><span>Simpan</span>
            </button>
        </div>
    </header>

    <nav class="asix-sheet-menubar" aria-label="Menu spreadsheet">
        <details class="asix-sheet-menu">
            <summary>File</summary>
            <div class="asix-sheet-menu-panel">
                <button type="button" class="asix-sheet-menu-item" data-command="save"><i class="fas fa-save"></i>Simpan <span class="shortcut">Ctrl+S</span></button>
                <a class="asix-sheet-menu-item" href="{{ route('drive.file.download', ['file' => $file]) }}"><i class="fas fa-download"></i>Unduh file asli</a>
                <div class="asix-sheet-menu-separator"></div>
                <a class="asix-sheet-menu-item" href="{{ $resolvedBackUrl }}" data-sheet-exit><i class="fas fa-arrow-left"></i>Kembali ke Bank Pipeline</a>
            </div>
        </details>
        <details class="asix-sheet-menu">
            <summary>Edit</summary>
            <div class="asix-sheet-menu-panel">
                <button type="button" class="asix-sheet-menu-item" data-command="undo"><i class="fas fa-undo"></i>Urungkan <span class="shortcut">Ctrl+Z</span></button>
                <button type="button" class="asix-sheet-menu-item" data-command="redo"><i class="fas fa-redo"></i>Ulangi <span class="shortcut">Ctrl+Y</span></button>
                <div class="asix-sheet-menu-separator"></div>
                <button type="button" class="asix-sheet-menu-item" data-command="cut"><i class="fas fa-cut"></i>Potong <span class="shortcut">Ctrl+X</span></button>
                <button type="button" class="asix-sheet-menu-item" data-command="copy"><i class="fas fa-copy"></i>Salin <span class="shortcut">Ctrl+C</span></button>
                <button type="button" class="asix-sheet-menu-item" data-command="paste"><i class="fas fa-paste"></i>Tempel <span class="shortcut">Ctrl+V</span></button>
                <button type="button" class="asix-sheet-menu-item" data-command="clear"><i class="fas fa-eraser"></i>Bersihkan rentang <span class="shortcut">Delete</span></button>
                <div class="asix-sheet-menu-separator"></div>
                <button type="button" class="asix-sheet-menu-item" data-command="find"><i class="fas fa-search"></i>Cari / Ganti <span class="shortcut">Ctrl+F</span></button>
            </div>
        </details>
        <details class="asix-sheet-menu">
            <summary>Sisipkan</summary>
            <div class="asix-sheet-menu-panel">
                <button type="button" class="asix-sheet-menu-item" data-command="insert-rows"><i class="fas fa-grip-lines"></i>Baris di atas</button>
                <button type="button" class="asix-sheet-menu-item" data-command="insert-columns"><i class="fas fa-columns"></i>Kolom di kiri</button>
                <div class="asix-sheet-menu-separator"></div>
                <button type="button" class="asix-sheet-menu-item" data-command="add-sheet"><i class="fas fa-plus"></i>Sheet baru</button>
                <button type="button" class="asix-sheet-menu-item" data-command="duplicate-sheet"><i class="fas fa-clone"></i>Duplikat sheet</button>
            </div>
        </details>
        <details class="asix-sheet-menu">
            <summary>Format</summary>
            <div class="asix-sheet-menu-panel">
                <button type="button" class="asix-sheet-menu-item" data-command="bold"><i class="fas fa-bold"></i>Tebal <span class="shortcut">Ctrl+B</span></button>
                <button type="button" class="asix-sheet-menu-item" data-command="italic"><i class="fas fa-italic"></i>Miring <span class="shortcut">Ctrl+I</span></button>
                <button type="button" class="asix-sheet-menu-item" data-command="underline"><i class="fas fa-underline"></i>Garis bawah <span class="shortcut">Ctrl+U</span></button>
                <button type="button" class="asix-sheet-menu-item" data-command="wrap"><i class="fas fa-align-left"></i>Bungkus teks</button>
                <div class="asix-sheet-menu-separator"></div>
                <button type="button" class="asix-sheet-menu-item" data-command="column-width"><i class="fas fa-arrows-alt-h"></i>Lebar kolom</button>
                <button type="button" class="asix-sheet-menu-item" data-command="row-height"><i class="fas fa-arrows-alt-v"></i>Tinggi baris</button>
            </div>
        </details>
        <details class="asix-sheet-menu">
            <summary>Data</summary>
            <div class="asix-sheet-menu-panel">
                <button type="button" class="asix-sheet-menu-item" data-command="toggle-filter"><i class="fas fa-filter"></i>Aktif/nonaktif filter</button>
                <button type="button" class="asix-sheet-menu-item" data-command="sort-asc"><i class="fas fa-sort-alpha-down"></i>Urutkan naik</button>
                <button type="button" class="asix-sheet-menu-item" data-command="sort-desc"><i class="fas fa-sort-alpha-down-alt"></i>Urutkan turun</button>
                <div class="asix-sheet-menu-separator"></div>
                <button type="button" class="asix-sheet-menu-item" data-command="freeze"><i class="fas fa-thumbtack"></i>Bekukan hingga sel aktif</button>
                <button type="button" class="asix-sheet-menu-item" data-command="unfreeze"><i class="fas fa-unlock"></i>Lepas panel beku</button>
            </div>
        </details>
        <details class="asix-sheet-menu">
            <summary>Sheet</summary>
            <div class="asix-sheet-menu-panel">
                <button type="button" class="asix-sheet-menu-item" data-command="rename-sheet"><i class="fas fa-pen"></i>Ubah nama sheet</button>
                <button type="button" class="asix-sheet-menu-item" data-command="duplicate-sheet"><i class="fas fa-clone"></i>Duplikat sheet</button>
                <button type="button" class="asix-sheet-menu-item" data-command="delete-sheet"><i class="fas fa-trash-alt"></i>Hapus sheet</button>
            </div>
        </details>
    </nav>

    <div class="asix-sheet-toolbar" role="toolbar" aria-label="Pemformatan spreadsheet">
        <button type="button" class="asix-sheet-tool" data-command="undo" title="Urungkan"><i class="fas fa-undo"></i></button>
        <button type="button" class="asix-sheet-tool" data-command="redo" title="Ulangi"><i class="fas fa-redo"></i></button>
        <button type="button" class="asix-sheet-tool" data-command="find" title="Cari dan ganti"><i class="fas fa-search"></i></button>
        <span class="asix-sheet-toolbar-divider"></span>
        <button type="button" class="asix-sheet-tool" data-command="bold" id="sheetBoldButton" title="Tebal"><span class="tool-letter">B</span></button>
        <button type="button" class="asix-sheet-tool" data-command="italic" id="sheetItalicButton" title="Miring"><span class="tool-letter is-italic">I</span></button>
        <button type="button" class="asix-sheet-tool" data-command="underline" id="sheetUnderlineButton" title="Garis bawah"><span class="tool-letter is-underline">U</span></button>
        <select class="asix-sheet-select is-narrow" id="sheetFontSize" aria-label="Ukuran font">
            <option value="">Ukuran</option>
            @foreach([8, 9, 10, 11, 12, 14, 16, 18, 20, 24, 28, 32, 36] as $fontSize)
                <option value="{{ $fontSize }}">{{ $fontSize }}</option>
            @endforeach
        </select>
        <div class="asix-sheet-color-tool" title="Warna teks">
            <label for="sheetFontColor"><i class="fas fa-font"></i><span class="asix-sheet-color-swatch" id="sheetFontColorSwatch"></span></label>
            <input type="color" id="sheetFontColor" value="#14253b">
        </div>
        <div class="asix-sheet-color-tool" title="Warna isi">
            <label for="sheetFillColor"><i class="fas fa-fill-drip"></i><span class="asix-sheet-color-swatch" id="sheetFillColorSwatch" style="background:#f3ad32"></span></label>
            <input type="color" id="sheetFillColor" value="#f3ad32">
        </div>
        <span class="asix-sheet-toolbar-divider"></span>
        <select class="asix-sheet-select" id="sheetHorizontalSelect" aria-label="Perataan horizontal">
            <option value="">Perataan</option>
            <option value="left">Kiri</option>
            <option value="center">Tengah</option>
            <option value="right">Kanan</option>
        </select>
        <select class="asix-sheet-select" id="sheetVerticalSelect" aria-label="Perataan vertikal">
            <option value="">Vertikal</option>
            <option value="top">Atas</option>
            <option value="center">Tengah</option>
            <option value="bottom">Bawah</option>
        </select>
        <select class="asix-sheet-select" id="sheetBorderStyle" aria-label="Garis batas sel">
            <option value="">Garis batas</option>
            <option value="none">Tanpa garis</option>
            <option value="thin">Tipis</option>
            <option value="medium">Sedang</option>
            <option value="dashed">Putus-putus</option>
            <option value="dotted">Titik-titik</option>
            <option value="double">Ganda</option>
        </select>
        <select class="asix-sheet-select" id="sheetNumberFormat" aria-label="Format angka">
            <option value="General">Umum</option>
            <option value="0">Angka bulat</option>
            <option value="0.00">2 desimal</option>
            <option value="#,##0">Ribuan</option>
            <option value="#,##0.00">Ribuan + desimal</option>
            <option value="0%">Persen</option>
            <option value="0.00%">Persen + desimal</option>
            <option value="dd mmm yyyy">Tanggal</option>
            <option value="Rp #,##0">Rupiah</option>
        </select>
        <button type="button" class="asix-sheet-tool" data-command="wrap" id="sheetWrapButton" title="Bungkus teks"><i class="fas fa-text-width"></i></button>
        <button type="button" class="asix-sheet-tool" data-command="merge" id="sheetMergeButton" title="Gabungkan sel" aria-label="Gabungkan sel" aria-pressed="false"><i class="fas fa-object-group"></i></button>
        <span class="asix-sheet-toolbar-divider"></span>
        <button type="button" class="asix-sheet-tool" data-command="toggle-filter" title="Filter"><i class="fas fa-filter"></i></button>
        <button type="button" class="asix-sheet-tool" data-command="sort-asc" title="Urut naik"><i class="fas fa-sort-amount-down-alt"></i></button>
        <button type="button" class="asix-sheet-tool" data-command="freeze" title="Bekukan panel"><i class="fas fa-thumbtack"></i></button>
    </div>

    <div class="asix-sheet-formula">
        <input class="asix-sheet-name-box" id="sheetNameBox" value="A1" aria-label="Alamat atau rentang sel" autocomplete="off">
        <span class="asix-sheet-fx" aria-hidden="true">fx</span>
        <input class="asix-sheet-formula-input" id="sheetFormulaInput" aria-label="Formula atau nilai sel aktif" autocomplete="off" spellcheck="false">
    </div>

    <div class="asix-sheet-banner is-warning" id="sheetWarningBanner" role="status"></div>
    <div class="asix-sheet-banner is-error" id="sheetErrorBanner" role="alert"></div>

    <main class="asix-sheet-workspace">
        <div class="asix-sheet-grid-shell" id="sheetGridShell">
            <div class="asix-sheet-corner" id="sheetCorner" title="Pilih semua"></div>
            <div class="asix-sheet-column-viewport"><div class="asix-sheet-column-track" id="sheetColumnTrack"></div></div>
            <div class="asix-sheet-row-viewport"><div class="asix-sheet-row-track" id="sheetRowTrack"></div></div>
            <div class="asix-sheet-body-viewport" id="sheetViewport" tabindex="0" role="grid" aria-label="Spreadsheet">
                <div class="asix-sheet-body-canvas" id="sheetCanvas"></div>
            </div>
            <input class="asix-sheet-cell-editor" id="sheetCellEditor" autocomplete="off" spellcheck="false">
        </div>

        <div class="asix-sheet-loading" id="sheetLoading">
            <div class="asix-sheet-state-card">
                <span class="asix-sheet-state-icon"><span class="asix-sheet-spinner"></span></span>
                <h2>Menyiapkan spreadsheet</h2>
                <p>Bank Pipeline sedang membaca workbook dari penyimpanan lokal.</p>
            </div>
        </div>

        <div class="asix-sheet-empty" id="sheetEmptyState" hidden>
            <div class="asix-sheet-state-card">
                <span class="asix-sheet-state-icon"><i class="fas fa-exclamation-triangle"></i></span>
                <h2>Spreadsheet belum dapat dibuka</h2>
                <p id="sheetEmptyMessage">Coba muat ulang file atau unduh file aslinya.</p>
                <button type="button" class="asix-sheet-dialog-button is-primary mt-3" id="sheetReloadButton">Muat ulang</button>
            </div>
        </div>
    </main>

    <footer class="asix-sheet-bottom">
        <div class="asix-sheet-tabs" id="sheetTabs" aria-label="Daftar sheet"></div>
        <button type="button" class="asix-sheet-tab-add" id="sheetAddTab" title="Tambah sheet" aria-label="Tambah sheet"><i class="fas fa-plus"></i></button>
        <div class="asix-sheet-footer-status" id="sheetFooterStatus">0 baris · 0 kolom</div>
    </footer>
</div>

<div class="asix-sheet-context" id="sheetContextMenu">
    <button type="button" class="asix-sheet-menu-item" data-command="cut"><i class="fas fa-cut"></i>Potong</button>
    <button type="button" class="asix-sheet-menu-item" data-command="copy"><i class="fas fa-copy"></i>Salin</button>
    <button type="button" class="asix-sheet-menu-item" data-command="paste"><i class="fas fa-paste"></i>Tempel</button>
    <div class="asix-sheet-menu-separator"></div>
    <button type="button" class="asix-sheet-menu-item" data-command="insert-rows"><i class="fas fa-grip-lines"></i>Sisipkan baris</button>
    <button type="button" class="asix-sheet-menu-item" data-command="insert-columns"><i class="fas fa-columns"></i>Sisipkan kolom</button>
    <button type="button" class="asix-sheet-menu-item" data-command="delete-rows"><i class="fas fa-minus"></i>Hapus baris</button>
    <button type="button" class="asix-sheet-menu-item" data-command="delete-columns"><i class="fas fa-minus"></i>Hapus kolom</button>
    <div class="asix-sheet-menu-separator"></div>
    <button type="button" class="asix-sheet-menu-item" data-command="clear"><i class="fas fa-eraser"></i>Bersihkan rentang</button>
</div>

<div class="asix-sheet-modal" id="sheetFindModal" role="dialog" aria-modal="true" aria-labelledby="sheetFindTitle">
    <div class="asix-sheet-dialog">
        <div class="asix-sheet-dialog-head">
            <h2 id="sheetFindTitle">Cari dan ganti</h2>
            <button type="button" class="asix-sheet-dialog-close" data-close-modal="sheetFindModal" aria-label="Tutup"><i class="fas fa-times"></i></button>
        </div>
        <div class="asix-sheet-dialog-body">
            <div class="asix-sheet-field">
                <label for="sheetFindInput">Cari</label>
                <input type="text" id="sheetFindInput" autocomplete="off">
            </div>
            <div class="asix-sheet-field">
                <label for="sheetReplaceInput">Ganti dengan</label>
                <input type="text" id="sheetReplaceInput" autocomplete="off">
            </div>
            <label class="asix-sheet-check"><input type="checkbox" id="sheetFindCase"> Cocokkan huruf besar/kecil</label>
            <p class="asix-sheet-dialog-note mt-2" id="sheetFindResult" aria-live="polite"></p>
        </div>
        <div class="asix-sheet-dialog-actions">
            <button type="button" class="asix-sheet-dialog-button" id="sheetReplaceAllButton">Ganti semua</button>
            <button type="button" class="asix-sheet-dialog-button" id="sheetReplaceButton">Ganti</button>
            <button type="button" class="asix-sheet-dialog-button is-primary" id="sheetFindNextButton">Cari berikutnya</button>
        </div>
    </div>
</div>

<div class="asix-sheet-modal" id="sheetConflictModal" role="dialog" aria-modal="true" aria-labelledby="sheetConflictTitle">
    <div class="asix-sheet-dialog">
        <div class="asix-sheet-dialog-head">
            <h2 id="sheetConflictTitle">File berubah di server</h2>
            <button type="button" class="asix-sheet-dialog-close" data-close-modal="sheetConflictModal" aria-label="Tutup"><i class="fas fa-times"></i></button>
        </div>
        <div class="asix-sheet-dialog-body">
            <p class="asix-sheet-dialog-note">Pengguna lain telah menyimpan revisi yang lebih baru. Editor dikunci sementara agar perubahan lokal tidak hilang. Muat revisi terbaru lalu terapkan kembali perubahan lokal untuk melanjutkan.</p>
        </div>
        <div class="asix-sheet-dialog-actions">
            <button type="button" class="asix-sheet-dialog-button" data-close-modal="sheetConflictModal">Tutup, tetap terkunci</button>
            <button type="button" class="asix-sheet-dialog-button is-accent" id="sheetRebaseButton">Muat & terapkan ulang</button>
        </div>
    </div>
</div>

<div class="asix-sheet-modal" id="sheetExitModal" role="dialog" aria-modal="true" aria-labelledby="sheetExitTitle">
    <div class="asix-sheet-dialog">
        <div class="asix-sheet-dialog-head">
            <h2 id="sheetExitTitle">Simpan sebelum keluar?</h2>
            <button type="button" class="asix-sheet-dialog-close" data-close-modal="sheetExitModal" aria-label="Batal keluar"><i class="fas fa-times"></i></button>
        </div>
        <div class="asix-sheet-dialog-body">
            <p class="asix-sheet-dialog-note">Masih ada perubahan lokal yang belum tersimpan. Simpan terlebih dahulu agar pembaruan pipeline tidak hilang.</p>
        </div>
        <div class="asix-sheet-dialog-actions">
            <button type="button" class="asix-sheet-dialog-button" data-close-modal="sheetExitModal">Tetap mengedit</button>
            <button type="button" class="asix-sheet-dialog-button is-danger" id="sheetExitDiscardButton">Keluar tanpa menyimpan</button>
            <button type="button" class="asix-sheet-dialog-button is-primary" id="sheetExitSaveButton"><i class="fas fa-save"></i> Simpan & keluar</button>
        </div>
    </div>
</div>

<div class="asix-sheet-toast-stack" id="sheetToastStack" aria-live="polite"></div>
@endsection

@section('scripts')
<script>
(() => {
    'use strict';

    const CONFIG = {{ Illuminate\Support\Js::from([
        'workbookUrl' => route('drive.file.workbook', ['file' => $file]),
        'cellsUrl' => route('drive.file.workbook.cells', ['file' => $file]),
        'saveUrl' => route('drive.file.workbook.save', ['file' => $file]),
        'downloadUrl' => route('drive.file.download', ['file' => $file]),
        'backUrl' => $resolvedBackUrl,
        'csrfToken' => csrf_token(),
        'fallbackName' => $driveFileName,
    ]) }};

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const elements = {
        app: $('#asixSheetApp'),
        viewport: $('#sheetViewport'),
        canvas: $('#sheetCanvas'),
        gridShell: $('#sheetGridShell'),
        columnTrack: $('#sheetColumnTrack'),
        rowTrack: $('#sheetRowTrack'),
        corner: $('#sheetCorner'),
        cellEditor: $('#sheetCellEditor'),
        nameBox: $('#sheetNameBox'),
        formulaInput: $('#sheetFormulaInput'),
        tabs: $('#sheetTabs'),
        addTab: $('#sheetAddTab'),
        saveButton: $('#sheetSaveButton'),
        downloadButton: $('#sheetDownloadButton'),
        statusDot: $('#sheetStatusDot'),
        statusText: $('#sheetStatusText'),
        revisionLabel: $('#sheetRevisionLabel'),
        footerStatus: $('#sheetFooterStatus'),
        warningBanner: $('#sheetWarningBanner'),
        errorBanner: $('#sheetErrorBanner'),
        loading: $('#sheetLoading'),
        empty: $('#sheetEmptyState'),
        emptyMessage: $('#sheetEmptyMessage'),
        reload: $('#sheetReloadButton'),
        contextMenu: $('#sheetContextMenu'),
        fontColor: $('#sheetFontColor'),
        fillColor: $('#sheetFillColor'),
        fontColorSwatch: $('#sheetFontColorSwatch'),
        fillColorSwatch: $('#sheetFillColorSwatch'),
        fontSize: $('#sheetFontSize'),
        horizontal: $('#sheetHorizontalSelect'),
        vertical: $('#sheetVerticalSelect'),
        borderStyle: $('#sheetBorderStyle'),
        numberFormat: $('#sheetNumberFormat'),
        boldButton: $('#sheetBoldButton'),
        italicButton: $('#sheetItalicButton'),
        underlineButton: $('#sheetUnderlineButton'),
        wrapButton: $('#sheetWrapButton'),
        mergeButton: $('#sheetMergeButton'),
        findModal: $('#sheetFindModal'),
        findInput: $('#sheetFindInput'),
        replaceInput: $('#sheetReplaceInput'),
        findCase: $('#sheetFindCase'),
        findResult: $('#sheetFindResult'),
        conflictModal: $('#sheetConflictModal'),
        exitModal: $('#sheetExitModal'),
        exitSaveButton: $('#sheetExitSaveButton'),
        exitDiscardButton: $('#sheetExitDiscardButton'),
        toastStack: $('#sheetToastStack'),
    };

    const state = {
        file: null,
        workbook: null,
        revision: null,
        activeSheetPosition: 0,
        selection: { anchor: { row: 1, col: 1 }, focus: { row: 1, col: 1 } },
        dragging: false,
        editing: false,
        saving: false,
        conflictLocked: false,
        loading: true,
        canEdit: false,
        canDownload: true,
        undoStack: [],
        redoStack: [],
        internalClipboard: null,
        renderFrame: null,
        metrics: null,
        mergedMap: new Map(),
        findMatches: [],
        findIndex: -1,
        conflictingOperations: null,
        pendingRowLoads: new Map(),
        failedRowLoads: new Map(),
        rowLoadGeneration: 0,
        autosaveTimer: null,
        savePromise: null,
        pendingExitUrl: null,
        allowUnload: false,
    };

    const DEFAULT_COLUMN_WIDTH = 104;
    const DEFAULT_ROW_HEIGHT = 28;
    const MAX_WORKSHEET_ROWS = 1048576;
    const MAX_RENDER_COLUMNS = 100;
    const ROW_VIEWPORT_CHUNK_SIZE = 160;
    const MAX_INTERACTIVE_RANGE_CELLS = 20000;
    const MAX_CLIPBOARD_CELLS = 2400;
    const MAX_FROZEN_ROWS = 100;
    const MAX_FROZEN_COLUMNS = 20;
    const AUTOSAVE_DELAY_MS = 1400;

    function deepClone(value) {
        if (typeof structuredClone === 'function') {
            return structuredClone(value);
        }
        return JSON.parse(JSON.stringify(value));
    }

    function columnLabel(number) {
        let value = Math.max(1, Number(number) || 1);
        let label = '';
        while (value > 0) {
            const remainder = (value - 1) % 26;
            label = String.fromCharCode(65 + remainder) + label;
            value = Math.floor((value - 1) / 26);
        }
        return label;
    }

    function columnNumber(label) {
        return String(label || '').toUpperCase().split('').reduce((total, character) => (
            total * 26 + character.charCodeAt(0) - 64
        ), 0);
    }

    function cellAddress(row, col) {
        return `${columnLabel(col)}${Math.max(1, row)}`;
    }

    function parseAddress(value) {
        const match = String(value || '').trim().toUpperCase().match(/^\$?([A-Z]{1,4})\$?(\d+)$/);
        if (!match) return null;
        return { row: Math.max(1, Number(match[2])), col: columnNumber(match[1]) };
    }

    function parseRange(value) {
        const parts = String(value || '').trim().toUpperCase().split(':');
        const first = parseAddress(parts[0]);
        const second = parseAddress(parts[1] || parts[0]);
        if (!first || !second) return null;
        return {
            startRow: Math.min(first.row, second.row),
            endRow: Math.max(first.row, second.row),
            startCol: Math.min(first.col, second.col),
            endCol: Math.max(first.col, second.col),
        };
    }

    function rangeAddress(range) {
        const first = cellAddress(range.startRow, range.startCol);
        const second = cellAddress(range.endRow, range.endCol);
        return first === second ? first : `${first}:${second}`;
    }

    function selectedRange() {
        const { anchor, focus } = state.selection;
        return {
            startRow: Math.min(anchor.row, focus.row),
            endRow: Math.max(anchor.row, focus.row),
            startCol: Math.min(anchor.col, focus.col),
            endCol: Math.max(anchor.col, focus.col),
        };
    }

    function activePoint() {
        return { ...state.selection.focus };
    }

    function activeSheet() {
        return state.workbook?.sheets?.[state.activeSheetPosition] || null;
    }

    function sheetOperationReference(sheet = activeSheet()) {
        // Nama sheet tetap stabil saat sheet lain disisipkan/dihapus dalam batch yang sama.
        return sheet?.title ?? sheet?.index ?? state.activeSheetPosition;
    }

    function normalizeCell(cell) {
        if (!cell || typeof cell !== 'object') return null;
        return {
            value: cell.value ?? null,
            formula: cell.formula ?? null,
            display: cell.display ?? (cell.formula ?? cell.value ?? ''),
            data_type: cell.data_type ?? null,
            style: { ...(cell.style || {}) },
        };
    }

    function writableStyle(style) {
        const input = style && typeof style === 'object' ? style : {};
        const output = {};
        ['bold', 'italic', 'underline', 'wrap'].forEach(key => {
            if (typeof input[key] === 'boolean') output[key] = input[key];
        });
        if (Number.isFinite(Number(input.font_size))) output.font_size = Number(input.font_size);
        if (input.font_color) output.font_color = input.font_color;
        if (Object.prototype.hasOwnProperty.call(input, 'fill_color')) output.fill_color = input.fill_color;
        if (input.horizontal) output.horizontal = input.horizontal;
        if (input.vertical) output.vertical = input.vertical;
        if (input.number_format) output.number_format = input.number_format;
        if (input.border_style) {
            output.border_style = input.border_style;
            if (input.border_color) output.border_color = input.border_color;
        }
        return output;
    }

    function normalizeSheet(sheet, position) {
        const cells = {};
        Object.entries(sheet?.cells || {}).forEach(([address, cell]) => {
            const normalizedAddress = String(address).toUpperCase();
            if (parseAddress(normalizedAddress)) {
                cells[normalizedAddress] = normalizeCell(cell);
            }
        });
        return {
            index: sheet?.index ?? position,
            title: String(sheet?.title || `Sheet ${position + 1}`),
            max_row: Math.max(1, Number(sheet?.max_row) || 1),
            max_col: Math.max(1, Number(sheet?.max_col) || 1),
            freeze_pane: sheet?.freeze_pane || null,
            auto_filter: sheet?.auto_filter || null,
            merged_cells: Array.isArray(sheet?.merged_cells) ? [...sheet.merged_cells] : [],
            column_widths: { ...(sheet?.column_widths || {}) },
            row_heights: { ...(sheet?.row_heights || {}) },
            cells,
            loaded_row_ranges: Array.isArray(sheet?.loaded_row_ranges)
                ? sheet.loaded_row_ranges
                    .map(range => ({
                        start: Math.max(1, Number(range?.start) || 1),
                        end: Math.max(1, Number(range?.end) || 1),
                    }))
                    .filter(range => range.end >= range.start)
                : [],
        };
    }

    function normalizeWorkbook(workbook) {
        const sheets = Array.isArray(workbook?.sheets)
            ? workbook.sheets.map(normalizeSheet)
            : [];
        if (!sheets.length) {
            sheets.push(normalizeSheet({ title: 'Sheet 1' }, 0));
        }
        return {
            active_sheet: workbook?.active_sheet ?? sheets[0].index,
            sheets,
        };
    }

    function resolveActivePosition(workbook) {
        const target = workbook?.active_sheet;
        const byIndex = workbook.sheets.findIndex(sheet => String(sheet.index) === String(target));
        if (byIndex >= 0) return byIndex;
        const byTitle = workbook.sheets.findIndex(sheet => sheet.title === target);
        return Math.max(0, byTitle);
    }

    function capability(names, fallback) {
        const capabilities = state.file?.capabilities || {};
        for (const name of names) {
            if (Object.prototype.hasOwnProperty.call(capabilities, name)) {
                return Boolean(capabilities[name]);
            }
        }
        return fallback;
    }

    function sanitizeColor(value, fallback = '') {
        const color = String(value || '').trim();
        return /^(#[0-9a-f]{3,8}|rgba?\([\d\s.,%]+\)|[a-z]{3,20})$/i.test(color) ? color : fallback;
    }

    function showToast(message, type = 'info', timeout = 3200) {
        const toast = document.createElement('div');
        toast.className = `asix-sheet-toast is-${type}`;
        const icon = document.createElement('i');
        icon.className = type === 'error' ? 'fas fa-exclamation-circle'
            : type === 'success' ? 'fas fa-check-circle' : 'fas fa-info-circle';
        const copy = document.createElement('span');
        copy.textContent = message;
        toast.append(icon, copy);
        elements.toastStack.appendChild(toast);
        window.setTimeout(() => toast.remove(), timeout);
    }

    function setStatus(type, message) {
        elements.statusDot.className = 'asix-sheet-status-dot';
        if (type !== 'ready') elements.statusDot.classList.add(`is-${type}`);
        elements.statusText.textContent = message;
        const revision = state.revision ?? state.file?.revision;
        const shortRevision = revision === null || revision === undefined
            ? ''
            : String(revision).replace(/^sha256:/, '').slice(0, 12);
        elements.revisionLabel.textContent = shortRevision ? `· revisi ${shortRevision}` : '';
    }

    function setBanner(element, messages, type) {
        const list = Array.isArray(messages) ? messages.filter(Boolean) : [messages].filter(Boolean);
        element.className = `asix-sheet-banner is-${type}`;
        element.replaceChildren();
        if (!list.length) return;
        element.classList.add('is-visible');
        const icon = document.createElement('i');
        icon.className = type === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-exclamation-triangle';
        const text = document.createElement('span');
        text.textContent = list.join(' · ');
        element.append(icon, text);
    }

    function setLoading(isLoading) {
        state.loading = isLoading;
        elements.loading.hidden = !isLoading;
    }

    function setEmpty(message = null) {
        elements.empty.hidden = !message;
        if (message) elements.emptyMessage.textContent = message;
    }

    function pendingOperations() {
        return state.undoStack.flatMap(action => action.operations || []);
    }

    function updateDirtyState() {
        const dirty = state.undoStack.length > 0;
        const mutationBlocked = !state.canEdit || state.saving || state.loading || state.conflictLocked;
        if (state.saving) {
            setStatus('saving', 'Menyimpan perubahan...');
        } else if (state.conflictLocked) {
            setStatus('error', 'Konflik revisi: muat ulang perubahan sebelum melanjutkan');
        } else if (dirty) {
            setStatus('dirty', `${pendingOperations().length} perubahan belum disimpan`);
        } else {
            setStatus('ready', state.canEdit ? 'Semua perubahan tersimpan' : 'Mode baca');
        }
        elements.saveButton.disabled = mutationBlocked || !dirty;
        elements.formulaInput.disabled = mutationBlocked;
        elements.cellEditor.disabled = mutationBlocked;
        elements.addTab.disabled = mutationBlocked;
        [
            elements.fontColor,
            elements.fillColor,
            elements.fontSize,
            elements.horizontal,
            elements.vertical,
            elements.borderStyle,
            elements.numberFormat,
        ].forEach(control => {
            control.disabled = mutationBlocked;
        });
        $$('[data-command="undo"]').forEach(button => {
            button.disabled = mutationBlocked || !state.undoStack.length;
        });
        $$('[data-command="redo"]').forEach(button => {
            button.disabled = mutationBlocked || !state.redoStack.length;
        });
        $$('[data-command]').forEach(button => {
            const readOnlyCommands = ['copy', 'find'];
            const command = button.dataset.command;
            if (['save', 'undo', 'redo'].includes(command)) return;
            button.disabled = readOnlyCommands.includes(command)
                ? state.loading || !state.workbook
                : mutationBlocked;
        });
    }

    function closeMenus() {
        $$('.asix-sheet-menu[open]').forEach(menu => menu.removeAttribute('open'));
        elements.contextMenu.classList.remove('is-visible');
    }

    function showModal(modal) {
        closeMenus();
        modal.classList.add('is-visible');
    }

    function hideModal(modal) {
        modal.classList.remove('is-visible');
    }

    async function readJsonResponse(response) {
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error(`Server mengembalikan respons yang tidak dikenali (${response.status}).`);
        }
        return response.json();
    }

    function rowRangeLoaded(sheet, startRow, endRow) {
        return (sheet?.loaded_row_ranges || []).some(range => (
            range.start <= startRow && range.end >= endRow
        ));
    }

    function markRowRangeLoaded(sheet, startRow, endRow) {
        const ranges = [...(sheet.loaded_row_ranges || []), { start: startRow, end: endRow }]
            .sort((left, right) => left.start - right.start);
        const merged = [];
        ranges.forEach(range => {
            const previous = merged[merged.length - 1];
            if (previous && range.start <= previous.end + 1) {
                previous.end = Math.max(previous.end, range.end);
            } else {
                merged.push({ ...range });
            }
        });
        sheet.loaded_row_ranges = merged;
    }

    function updateFooterStatus() {
        const sheet = activeSheet();
        if (!sheet) return;
        const loadingCopy = state.pendingRowLoads.size
            ? ` \u00b7 memuat ${state.pendingRowLoads.size} blok data...`
            : '';
        elements.footerStatus.textContent = `${sheet.max_row || 1} baris \u00b7 ${sheet.max_col || 1} kolom${loadingCopy}`;
    }

    async function loadRowChunk(sheet, startRow) {
        if (!sheet || state.loading || state.saving || state.conflictLocked) return;
        if (pendingOperations().length) return;

        const endRow = Math.min(sheet.max_row, startRow + ROW_VIEWPORT_CHUNK_SIZE - 1);
        if (endRow < startRow || rowRangeLoaded(sheet, startRow, endRow)) return;

        const endColumn = Math.max(1, Math.min(MAX_RENDER_COLUMNS, sheet.max_col || 1));
        const key = `${sheet.index}:${startRow}:${endRow}:${endColumn}`;
        if (state.pendingRowLoads.has(key)) return state.pendingRowLoads.get(key);
        const lastFailure = state.failedRowLoads.get(key) || 0;
        if (Date.now() - lastFailure < 5000) return;

        const generation = state.rowLoadGeneration;
        const revision = state.revision;
        const request = (async () => {
            const url = new URL(CONFIG.cellsUrl, window.location.origin);
            url.searchParams.set('revision', revision || '');
            url.searchParams.set('sheet', String(sheet.index));
            url.searchParams.set('start_row', String(startRow));
            url.searchParams.set('end_row', String(endRow));
            url.searchParams.set('start_col', '1');
            url.searchParams.set('end_col', String(endColumn));

            const response = await fetch(url.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await readJsonResponse(response);
            if (response.status === 409 || response.status === 412) {
                if (!pendingOperations().length) await loadWorkbook();
                return;
            }
            if (!response.ok) {
                throw new Error(payload.message || 'Baris workbook gagal dimuat.');
            }
            if (generation !== state.rowLoadGeneration || revision !== state.revision) return;
            if (pendingOperations().length) return;

            const target = state.workbook?.sheets?.find(candidate => (
                String(candidate.index) === String(sheet.index)
            ));
            if (!target) return;

            Object.entries(payload.cells || {}).forEach(([address, cell]) => {
                target.cells[String(address).toUpperCase()] = normalizeCell(cell);
            });
            target.row_heights = { ...target.row_heights, ...(payload.row_heights || {}) };
            markRowRangeLoaded(target, startRow, endRow);
            state.failedRowLoads.delete(key);
            if (target === activeSheet()) scheduleRender();
        })().catch(error => {
            console.error(error);
            state.failedRowLoads.set(key, Date.now());
            showToast(error.message || 'Baris workbook gagal dimuat.', 'error', 4200);
        }).finally(() => {
            if (state.pendingRowLoads.get(key) === request) {
                state.pendingRowLoads.delete(key);
            }
            updateFooterStatus();
        });

        state.pendingRowLoads.set(key, request);
        updateFooterStatus();

        return request;
    }

    function requestVisibleRows(rows) {
        const sheet = activeSheet();
        if (!sheet || !Array.isArray(rows) || !rows.length) return;
        const chunks = new Set(rows.map(row => (
            Math.floor((Math.max(1, row) - 1) / ROW_VIEWPORT_CHUNK_SIZE) * ROW_VIEWPORT_CHUNK_SIZE + 1
        )));
        chunks.forEach(startRow => void loadRowChunk(sheet, startRow));
    }

    async function loadWorkbook(options = {}) {
        const operationsToReapply = options.reapplyOperations || null;
        state.rowLoadGeneration++;
        state.pendingRowLoads.clear();
        state.failedRowLoads.clear();
        setLoading(true);
        setEmpty(null);
        setBanner(elements.errorBanner, [], 'error');
        setStatus('saving', 'Membaca workbook...');
        try {
            const response = await fetch(CONFIG.workbookUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await readJsonResponse(response);
            if (!response.ok) {
                throw new Error(payload.message || 'Workbook gagal dibuka.');
            }

            state.file = payload.file || {};
            state.workbook = normalizeWorkbook(payload.workbook);
            state.revision = payload.revision ?? state.file.revision ?? null;
            state.activeSheetPosition = resolveActivePosition(state.workbook);
            state.canEdit = capability(['edit', 'can_edit', 'update', 'can_update'], true);
            state.canDownload = capability(['download', 'can_download'], true);
            state.selection = { anchor: { row: 1, col: 1 }, focus: { row: 1, col: 1 } };
            state.undoStack = [];
            state.redoStack = [];
            elements.downloadButton.hidden = !state.canDownload;
            $('#sheetFileName').textContent = state.file.name || CONFIG.fallbackName;
            setBanner(elements.warningBanner, state.file.warnings || payload.warnings || [], 'warning');

            if (Array.isArray(operationsToReapply) && operationsToReapply.length) {
                const beforeWorkbook = deepClone(state.workbook);
                const beforePosition = state.activeSheetPosition;
                operationsToReapply.forEach(operation => applyRawOperation(operation));
                const afterWorkbook = deepClone(state.workbook);
                const afterPosition = state.activeSheetPosition;
                state.undoStack.push({
                    label: 'Perubahan lokal setelah rebase',
                    operations: operationsToReapply,
                    apply: () => {
                        state.workbook = deepClone(afterWorkbook);
                        state.activeSheetPosition = afterPosition;
                    },
                    revert: () => {
                        state.workbook = deepClone(beforeWorkbook);
                        state.activeSheetPosition = beforePosition;
                    },
                });
                showToast('Revisi terbaru dimuat. Perubahan lokal telah diterapkan ulang dan belum disimpan.', 'info', 5200);
            }

            rebuildMetrics();
            renderTabs();
            syncSelectionUi();
            scheduleRender();
            state.conflictingOperations = null;
            state.conflictLocked = false;
            updateDirtyState();
        } catch (error) {
            console.error(error);
            setStatus('error', 'Workbook gagal dibuka');
            setBanner(elements.errorBanner, error.message, 'error');
            setEmpty(error.message || 'Workbook gagal dibuka.');
        } finally {
            setLoading(false);
            updateDirtyState();
        }
    }

    async function saveWorkbook(options = {}) {
        const settings = options instanceof Event ? {} : options;
        if (!state.canEdit || state.conflictLocked) return false;
        if (state.saving) return state.savePromise || false;
        if (state.editing) commitCellEditor();
        const operations = pendingOperations();
        if (!operations.length) return true;

        if (state.autosaveTimer) {
            window.clearTimeout(state.autosaveTimer);
            state.autosaveTimer = null;
        }

        const request = (async () => {
            state.saving = true;
            updateDirtyState();
            setBanner(elements.errorBanner, [], 'error');
            try {
                const response = await fetch(CONFIG.saveUrl, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CONFIG.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        base_revision: state.revision,
                        operations,
                        active_sheet: activeSheet()?.title ?? null,
                    }),
                });
                const payload = await readJsonResponse(response);
                if (response.status === 409 || response.status === 412) {
                    state.conflictingOperations = operations;
                    state.conflictLocked = true;
                    showModal(elements.conflictModal);
                    throw new Error(payload.message || 'File telah berubah di server.');
                }
                if (!response.ok) {
                    const validationMessage = payload.errors
                        ? Object.values(payload.errors).flat().join(' ')
                        : null;
                    throw new Error(validationMessage || payload.message || 'Perubahan gagal disimpan.');
                }

                state.revision = payload.revision ?? payload.file?.revision ?? state.revision;
                if (payload.file) state.file = { ...state.file, ...payload.file };
                if (payload.workbook) {
                    state.rowLoadGeneration++;
                    state.pendingRowLoads.clear();
                    state.failedRowLoads.clear();
                    state.workbook = normalizeWorkbook(payload.workbook);
                    state.activeSheetPosition = resolveActivePosition(state.workbook);
                }
                state.undoStack = [];
                state.redoStack = [];
                rebuildMetrics();
                renderTabs();
                syncSelectionUi();
                scheduleRender();
                if (!settings.quiet) showToast('Perubahan spreadsheet berhasil disimpan.', 'success');
                return true;
            } catch (error) {
                console.error(error);
                setBanner(elements.errorBanner, error.message, 'error');
                showToast(error.message, 'error', 5000);
                return false;
            } finally {
                state.saving = false;
                updateDirtyState();
            }
        })();

        state.savePromise = request;
        const saved = await request;
        if (state.savePromise === request) state.savePromise = null;
        return saved;
    }

    function actionSheetPosition(action) {
        const reference = action?.originSheet
            ?? action?.operations?.find(operation => operation?.sheet !== null && operation?.sheet !== undefined)?.sheet;
        if (reference !== null && reference !== undefined) {
            const position = state.workbook?.sheets?.findIndex(sheet => (
                String(sheet.index) === String(reference) || sheet.title === reference
            ));
            if (position >= 0) return position;
        }
        return Number.isInteger(action?.originPosition) ? action.originPosition : state.activeSheetPosition;
    }

    function activateActionSheet(action) {
        const position = actionSheetPosition(action);
        if (position >= 0 && position < (state.workbook?.sheets?.length || 0)) {
            state.activeSheetPosition = position;
        }
    }

    function performAction(action) {
        if (!state.canEdit || !action) return false;
        if (state.conflictLocked) {
            showToast('Muat dan terapkan ulang perubahan lokal sebelum mengedit kembali.', 'info', 3200);
            return false;
        }
        if (state.saving) {
            showToast('Tunggu penyimpanan selesai sebelum mengubah workbook.', 'info', 2600);
            return false;
        }
        const futureOperationCount = pendingOperations().length + (action.operations?.length || 0);
        if (futureOperationCount > 5000) {
            showToast('Batas 5.000 perubahan per penyimpanan tercapai. Simpan perubahan saat ini sebelum melanjutkan.', 'error', 5200);
            return false;
        }
        action.originPosition = state.activeSheetPosition;
        action.originSheet = action.originSheet
            ?? action.operations?.find(operation => operation?.sheet !== null && operation?.sheet !== undefined)?.sheet
            ?? activeSheet()?.title
            ?? null;
        activateActionSheet(action);
        action.apply();
        state.undoStack.push(action);
        state.redoStack = [];
        afterLocalMutation();
        return true;
    }

    function undo() {
        if (!state.canEdit || state.saving || state.conflictLocked || !state.undoStack.length) return;
        commitCellEditor();
        const action = state.undoStack.pop();
        activateActionSheet(action);
        action.revert();
        state.redoStack.push(action);
        afterLocalMutation();
        showToast(`Diurungkan: ${action.label}`, 'info', 1800);
    }

    function redo() {
        if (!state.canEdit || state.saving || state.conflictLocked || !state.redoStack.length) return;
        commitCellEditor();
        const action = state.redoStack.pop();
        activateActionSheet(action);
        action.apply();
        state.undoStack.push(action);
        afterLocalMutation();
        showToast(`Diulangi: ${action.label}`, 'info', 1800);
    }

    function scheduleAutosave() {
        if (!state.canEdit || state.conflictLocked || !pendingOperations().length) return;
        if (state.autosaveTimer) window.clearTimeout(state.autosaveTimer);
        state.autosaveTimer = window.setTimeout(() => {
            state.autosaveTimer = null;
            if (!state.editing && !state.saving && pendingOperations().length) {
                void saveWorkbook({quiet: true, source: 'autosave'});
            }
        }, AUTOSAVE_DELAY_MS);
    }

    function leaveEditor(url) {
        state.allowUnload = true;
        window.location.assign(url || CONFIG.backUrl);
    }

    async function requestEditorExit(url) {
        state.pendingExitUrl = url || CONFIG.backUrl;
        if (state.saving && state.savePromise) await state.savePromise;
        if (!pendingOperations().length) {
            leaveEditor(state.pendingExitUrl);
            return;
        }
        showModal(elements.exitModal);
    }

    function afterLocalMutation() {
        rebuildMetrics();
        renderTabs();
        syncSelectionUi();
        scheduleRender();
        updateDirtyState();
        scheduleAutosave();
    }

    function cellChangeOperations(changes, sheetReference) {
        return changes.flatMap(change => {
            const address = cellAddress(change.row, change.col);
            if (!change.cell) {
                return [{
                    type: 'clear_range',
                    sheet: sheetReference,
                    range: address,
                }];
            }
            const cellOperation = {
                type: 'set_cell',
                sheet: sheetReference,
                address,
                value: change.cell.formula ? null : change.cell.value,
                formula: change.cell.formula || null,
            };
            const style = change.copyStyle ? writableStyle(change.cell.style) : {};
            return Object.keys(style).length
                ? [
                    cellOperation,
                    {
                        type: 'set_style',
                        sheet: sheetReference,
                        range: address,
                        style,
                    },
                ]
                : [cellOperation];
        });
    }

    function createCellMutation(changes, label) {
        const sheet = activeSheet();
        if (!sheet || !changes.length) return null;
        const before = {};
        const after = {};
        changes.forEach(change => {
            const address = cellAddress(change.row, change.col);
            const existing = Object.prototype.hasOwnProperty.call(sheet.cells, address)
                ? deepClone(sheet.cells[address])
                : null;
            before[address] = existing;
            after[address] = change.cell
                ? normalizeCell(change.cell)
                : (existing && Object.keys(existing.style || {}).length
                    ? { ...existing, value: null, formula: null, display: '', data_type: 'null' }
                    : null);
        });

        const applySnapshot = snapshot => {
            Object.entries(snapshot).forEach(([address, cell]) => {
                if (cell === null) delete activeSheet().cells[address];
                else activeSheet().cells[address] = deepClone(cell);
            });
            refreshSheetBounds(activeSheet());
        };

        const operations = cellChangeOperations(changes, sheetOperationReference(sheet));

        return {
            label,
            operations,
            apply: () => applySnapshot(after),
            revert: () => applySnapshot(before),
        };
    }

    function setCellInput(row, col, input, label = 'Ubah sel') {
        const raw = String(input ?? '');
        const formula = raw.startsWith('=') ? raw : null;
        const cell = raw === '' ? null : {
            value: formula ? null : raw,
            formula,
            display: raw,
            data_type: formula ? 'f' : 's',
            style: { ...(activeSheet()?.cells?.[cellAddress(row, col)]?.style || {}) },
        };
        performAction(createCellMutation([{ row, col, cell }], label));
    }

    function clearRange(range = selectedRange()) {
        const sheet = activeSheet();
        if (!sheet || !allowInteractiveRange(range)) return;
        const before = {};
        for (let row = range.startRow; row <= range.endRow; row++) {
            for (let col = range.startCol; col <= range.endCol; col++) {
                const address = cellAddress(row, col);
                if (Object.prototype.hasOwnProperty.call(sheet.cells, address)) {
                    before[address] = deepClone(sheet.cells[address]);
                }
            }
        }
        const apply = () => {
            for (let row = range.startRow; row <= range.endRow; row++) {
                for (let col = range.startCol; col <= range.endCol; col++) {
                    const address = cellAddress(row, col);
                    const existing = activeSheet().cells[address];
                    if (existing && Object.keys(existing.style || {}).length) {
                        activeSheet().cells[address] = {
                            ...existing,
                            value: null,
                            formula: null,
                            display: '',
                            data_type: 'null',
                        };
                    } else {
                        delete activeSheet().cells[address];
                    }
                }
            }
            refreshSheetBounds(activeSheet());
        };
        const revert = () => {
            Object.entries(before).forEach(([address, cell]) => activeSheet().cells[address] = deepClone(cell));
            refreshSheetBounds(activeSheet());
        };
        performAction({
            label: 'Bersihkan rentang',
            operations: [{
                type: 'clear_range',
                sheet: sheetOperationReference(sheet),
                range: rangeAddress(range),
            }],
            apply,
            revert,
        });
    }

    function applyStyle(stylePatch, label) {
        const sheet = activeSheet();
        const range = selectedRange();
        if (!sheet || !allowInteractiveRange(range)) return;
        const before = {};
        const after = {};
        for (let row = range.startRow; row <= range.endRow; row++) {
            for (let col = range.startCol; col <= range.endCol; col++) {
                const address = cellAddress(row, col);
                const existing = normalizeCell(sheet.cells[address] || {
                    value: null, display: '', style: {},
                });
                before[address] = Object.prototype.hasOwnProperty.call(sheet.cells, address)
                    ? deepClone(sheet.cells[address])
                    : null;
                after[address] = {
                    ...existing,
                    style: { ...(existing.style || {}), ...stylePatch },
                };
            }
        }
        const applySnapshot = snapshot => {
            Object.entries(snapshot).forEach(([address, cell]) => {
                if (cell === null) delete activeSheet().cells[address];
                else activeSheet().cells[address] = deepClone(cell);
            });
        };
        performAction({
            label,
            operations: [{
                type: 'set_style',
                sheet: sheetOperationReference(sheet),
                range: rangeAddress(range),
                style: stylePatch,
            }],
            apply: () => applySnapshot(after),
            revert: () => applySnapshot(before),
        });
    }

    function toggleStyle(key, label) {
        const point = activePoint();
        const current = activeSheet()?.cells?.[cellAddress(point.row, point.col)]?.style?.[key];
        applyStyle({ [key]: !Boolean(current) }, label);
    }

    function refreshSheetBounds(sheet) {
        if (!sheet) return;
        let maxRow = 1;
        let maxCol = 1;
        Object.keys(sheet.cells || {}).forEach(address => {
            const point = parseAddress(address);
            if (!point) return;
            maxRow = Math.max(maxRow, point.row);
            maxCol = Math.max(maxCol, point.col);
        });
        sheet.max_row = Math.max(sheet.max_row || 1, maxRow);
        sheet.max_col = Math.max(sheet.max_col || 1, maxCol);
    }

    function replaceActiveSheet(snapshot) {
        state.workbook.sheets[state.activeSheetPosition] = deepClone(snapshot);
    }

    function structuralAction(operation, mutate, label) {
        const before = deepClone(activeSheet());
        mutate(activeSheet());
        const after = deepClone(activeSheet());
        replaceActiveSheet(before);
        const applied = performAction({
            label,
            operations: [operation],
            apply: () => replaceActiveSheet(after),
            revert: () => replaceActiveSheet(before),
        });
        if (applied) void saveWorkbook();
    }

    function remapCells(sheet, mapper) {
        const remapped = {};
        Object.entries(sheet.cells || {}).forEach(([address, cell]) => {
            const point = parseAddress(address);
            const target = point ? mapper(point) : null;
            if (target && target.row > 0 && target.col > 0) {
                remapped[cellAddress(target.row, target.col)] = cell;
            }
        });
        sheet.cells = remapped;
        refreshSheetBounds(sheet);
    }

    function insertRows() {
        const range = selectedRange();
        const count = range.endRow - range.startRow + 1;
        const sheet = activeSheet();
        structuralAction({
            type: 'insert_rows',
            sheet: sheetOperationReference(sheet),
            row: range.startRow,
            count,
        }, current => {
            remapCells(current, point => ({
                row: point.row >= range.startRow ? point.row + count : point.row,
                col: point.col,
            }));
            current.max_row += count;
        }, `Sisipkan ${count} baris`);
    }

    function deleteRows() {
        const range = selectedRange();
        const count = range.endRow - range.startRow + 1;
        const last = range.endRow;
        const sheet = activeSheet();
        structuralAction({
            type: 'delete_rows',
            sheet: sheetOperationReference(sheet),
            row: range.startRow,
            count,
        }, current => {
            remapCells(current, point => {
                if (point.row >= range.startRow && point.row <= last) return null;
                return {
                    row: point.row > last ? point.row - count : point.row,
                    col: point.col,
                };
            });
            current.max_row = Math.max(1, current.max_row - count);
        }, `Hapus ${count} baris`);
        selectCell(Math.min(range.startRow, activeSheet().max_row), range.startCol);
    }

    function insertColumns() {
        const range = selectedRange();
        const count = range.endCol - range.startCol + 1;
        const sheet = activeSheet();
        structuralAction({
            type: 'insert_columns',
            sheet: sheetOperationReference(sheet),
            column: range.startCol,
            count,
        }, current => {
            remapCells(current, point => ({
                row: point.row,
                col: point.col >= range.startCol ? point.col + count : point.col,
            }));
            current.max_col += count;
        }, `Sisipkan ${count} kolom`);
    }

    function deleteColumns() {
        const range = selectedRange();
        const count = range.endCol - range.startCol + 1;
        const last = range.endCol;
        const sheet = activeSheet();
        structuralAction({
            type: 'delete_columns',
            sheet: sheetOperationReference(sheet),
            column: range.startCol,
            count,
        }, current => {
            remapCells(current, point => {
                if (point.col >= range.startCol && point.col <= last) return null;
                return {
                    row: point.row,
                    col: point.col > last ? point.col - count : point.col,
                };
            });
            current.max_col = Math.max(1, current.max_col - count);
        }, `Hapus ${count} kolom`);
        selectCell(range.startRow, Math.min(range.startCol, activeSheet().max_col));
    }

    function setColumnWidth() {
        const range = selectedRange();
        const widthKey = columnLabel(range.startCol);
        const current = Number(activeSheet().column_widths?.[widthKey]
            ?? activeSheet().column_widths?.[range.startCol]
            ?? ((DEFAULT_COLUMN_WIDTH - 8) / 7));
        const input = window.prompt('Lebar kolom (2–100 unit):', String(Math.round(current * 10) / 10));
        if (input === null) return;
        const width = Math.min(100, Math.max(2, Number(input) || current));
        const sheet = activeSheet();
        const before = deepClone(sheet.column_widths);
        const after = deepClone(before);
        for (let col = range.startCol; col <= range.endCol; col++) {
            after[columnLabel(col)] = width;
        }
        performAction({
            label: 'Atur lebar kolom',
            operations: Array.from({ length: range.endCol - range.startCol + 1 }, (_, offset) => ({
                type: 'set_column_width',
                sheet: sheetOperationReference(sheet),
                column: range.startCol + offset,
                width,
            })),
            apply: () => activeSheet().column_widths = deepClone(after),
            revert: () => activeSheet().column_widths = deepClone(before),
        });
    }

    function setRowHeight() {
        const range = selectedRange();
        const current = Number(activeSheet().row_heights?.[range.startRow]
            ?? activeSheet().row_heights?.[String(range.startRow)]
            ?? (DEFAULT_ROW_HEIGHT / 1.333));
        const input = window.prompt('Tinggi baris (8–180 poin):', String(Math.round(current * 10) / 10));
        if (input === null) return;
        const height = Math.min(180, Math.max(8, Number(input) || current));
        const sheet = activeSheet();
        const before = deepClone(sheet.row_heights);
        const after = deepClone(before);
        for (let row = range.startRow; row <= range.endRow; row++) after[row] = height;
        performAction({
            label: 'Atur tinggi baris',
            operations: Array.from({ length: range.endRow - range.startRow + 1 }, (_, offset) => ({
                type: 'set_row_height',
                sheet: sheetOperationReference(sheet),
                row: range.startRow + offset,
                height,
            })),
            apply: () => activeSheet().row_heights = deepClone(after),
            revert: () => activeSheet().row_heights = deepClone(before),
        });
    }

    function toggleMerge() {
        const sheet = activeSheet();
        const range = selectedRange();
        if (!sheet || !allowInteractiveRange(range)) return;
        const address = rangeAddress(range);
        const mergedRanges = mergedRangeEntries(sheet);
        const existing = mergedRanges.find(merged => rangesEqual(merged.range, range));

        if (!existing && rangeCellCount(range) < 2) {
            showToast('Pilih sedikitnya dua sel untuk digabungkan.', 'info');
            return;
        }

        const overlapping = !existing
            ? mergedRanges.find(merged => rangesIntersect(merged.range, range))
            : null;
        if (overlapping) {
            showToast(
                `Rentang ${address} bersinggungan dengan ${overlapping.address}. Pisahkan sel tersebut terlebih dahulu.`,
                'error',
                4800
            );
            return;
        }

        const before = [...sheet.merged_cells];
        const after = existing
            ? before.filter(merged => merged !== existing.address)
            : [...before, address];
        const applied = performAction({
            label: existing ? 'Pisahkan sel' : 'Gabungkan sel',
            operations: [{
                type: existing ? 'unmerge' : 'merge',
                sheet: sheetOperationReference(sheet),
                range: existing?.address || address,
            }],
            apply: () => activeSheet().merged_cells = [...after],
            revert: () => activeSheet().merged_cells = [...before],
        });
        if (applied) {
            showToast(existing ? 'Sel berhasil dipisahkan.' : 'Sel berhasil digabungkan.', 'success', 1800);
        }
    }

    function freezePane(unfreeze = false) {
        const sheet = activeSheet();
        const point = activePoint();
        const pane = unfreeze ? null : cellAddress(point.row, point.col);
        const before = sheet.freeze_pane || null;
        performAction({
            label: unfreeze ? 'Lepas panel beku' : 'Bekukan panel',
            operations: [{
                type: 'freeze_pane',
                sheet: sheetOperationReference(sheet),
                pane,
            }],
            apply: () => activeSheet().freeze_pane = pane,
            revert: () => activeSheet().freeze_pane = before,
        });
    }

    function toggleAutoFilter() {
        const sheet = activeSheet();
        const selected = selectedRange();
        if (!sheet || !allowInteractiveRange(selected)) return;
        const range = rangeAddress(selected);
        const before = sheet.auto_filter || null;
        const after = before ? null : range;
        performAction({
            label: after ? 'Aktifkan filter' : 'Nonaktifkan filter',
            operations: [{
                type: 'set_auto_filter',
                sheet: sheetOperationReference(sheet),
                range: after,
            }],
            apply: () => activeSheet().auto_filter = after,
            revert: () => activeSheet().auto_filter = before,
        });
    }

    function sortRange(direction) {
        const sheet = activeSheet();
        const range = selectedRange();
        if (!sheet || !allowInteractiveRange(range)) return;
        if ((sheet.merged_cells || []).some(merged => {
            const parsed = parseRange(merged);
            return parsed && rangesIntersect(parsed, range);
        })) {
            showToast('Pisahkan merged cell sebelum mengurutkan rentang.', 'error', 3600);
            return;
        }
        for (let row = range.startRow; row <= range.endRow; row++) {
            for (let col = range.startCol; col <= range.endCol; col++) {
                if (sheet.cells[cellAddress(row, col)]?.formula) {
                    showToast('Sort lokal hanya tersedia untuk rentang tanpa formula agar referensi tidak berubah.', 'error', 4200);
                    return;
                }
            }
        }
        if (range.endRow <= range.startRow) {
            showToast('Pilih sedikitnya dua baris untuk diurutkan.', 'info');
            return;
        }
        const sortColumn = activePoint().col;
        const before = deepClone(sheet);
        const rows = [];
        for (let row = range.startRow; row <= range.endRow; row++) {
            const cells = [];
            for (let col = range.startCol; col <= range.endCol; col++) {
                const address = cellAddress(row, col);
                cells.push(Object.prototype.hasOwnProperty.call(sheet.cells, address)
                    ? deepClone(sheet.cells[address])
                    : null);
            }
            const keyCell = sheet.cells[cellAddress(row, sortColumn)];
            rows.push({ cells, key: keyCell?.value ?? keyCell?.display ?? '' });
        }
        rows.sort((a, b) => {
            const numericA = Number(a.key);
            const numericB = Number(b.key);
            const comparison = Number.isFinite(numericA) && Number.isFinite(numericB)
                ? numericA - numericB
                : String(a.key).localeCompare(String(b.key), 'id', { numeric: true, sensitivity: 'base' });
            return direction === 'desc' ? -comparison : comparison;
        });
        rows.forEach((rowData, offset) => {
            rowData.cells.forEach((cell, colOffset) => {
                const address = cellAddress(range.startRow + offset, range.startCol + colOffset);
                if (cell === null) delete sheet.cells[address];
                else sheet.cells[address] = cell;
            });
        });
        const after = deepClone(sheet);
        replaceActiveSheet(before);
        performAction({
            label: direction === 'desc' ? 'Urutkan turun' : 'Urutkan naik',
            operations: [{
                type: 'sort_range',
                sheet: sheetOperationReference(sheet),
                range: rangeAddress(range),
                column: sortColumn,
                direction,
            }],
            apply: () => replaceActiveSheet(after),
            revert: () => replaceActiveSheet(before),
        });
    }

    function sheetCollectionAction(operation, mutate, label, nextPosition = null) {
        const beforeSheets = deepClone(state.workbook.sheets);
        const beforePosition = state.activeSheetPosition;
        mutate(state.workbook.sheets);
        const afterSheets = deepClone(state.workbook.sheets);
        const afterPosition = nextPosition === null
            ? Math.min(state.activeSheetPosition, afterSheets.length - 1)
            : Math.min(Math.max(0, nextPosition), afterSheets.length - 1);
        state.workbook.sheets = beforeSheets;
        state.activeSheetPosition = beforePosition;
        const applied = performAction({
            label,
            operations: [operation],
            apply: () => {
                state.workbook.sheets = deepClone(afterSheets);
                state.activeSheetPosition = afterPosition;
            },
            revert: () => {
                state.workbook.sheets = deepClone(beforeSheets);
                state.activeSheetPosition = beforePosition;
            },
        });
        if (applied) void saveWorkbook();
    }

    function uniqueSheetTitle(base = 'Sheet') {
        const titles = new Set(state.workbook.sheets.map(sheet => sheet.title.toLowerCase()));
        let number = 1;
        let candidate = `${base} ${number}`;
        while (titles.has(candidate.toLowerCase())) candidate = `${base} ${++number}`;
        return candidate;
    }

    function addSheet() {
        const title = uniqueSheetTitle();
        const newPosition = state.workbook.sheets.length;
        const newIndex = Math.max(-1, ...state.workbook.sheets.map(sheet => Number(sheet.index) || 0)) + 1;
        sheetCollectionAction({ type: 'add_sheet', title }, sheets => {
            sheets.push(normalizeSheet({ index: newIndex, title }, sheets.length));
        }, 'Tambah sheet', newPosition);
    }

    function renameSheet() {
        const sheet = activeSheet();
        const title = window.prompt('Nama sheet:', sheet.title);
        if (title === null || !title.trim() || title.trim() === sheet.title) return;
        const normalized = title.trim().slice(0, 31);
        if (state.workbook.sheets.some(item => item !== sheet && item.title.toLowerCase() === normalized.toLowerCase())) {
            showToast('Nama sheet sudah digunakan.', 'error');
            return;
        }
        sheetCollectionAction({
            type: 'rename_sheet',
            sheet: sheetOperationReference(sheet),
            title: normalized,
        }, sheets => {
            sheets[state.activeSheetPosition].title = normalized;
        }, 'Ubah nama sheet');
    }

    function duplicateSheet() {
        const sheet = activeSheet();
        const title = uniqueSheetTitle(`${sheet.title} Salinan`);
        const insertAt = state.activeSheetPosition + 1;
        sheetCollectionAction({
            type: 'duplicate_sheet',
            sheet: sheetOperationReference(sheet),
            title,
        }, sheets => {
            const copy = deepClone(sheet);
            copy.index = Math.max(-1, ...sheets.map(item => Number(item.index) || 0)) + 1;
            copy.title = title;
            sheets.splice(insertAt, 0, copy);
        }, 'Duplikat sheet', insertAt);
    }

    async function deleteSheet() {
        if (state.workbook.sheets.length <= 1) {
            showToast('Workbook harus memiliki sedikitnya satu sheet.', 'error');
            return;
        }
        const sheet = activeSheet();
        if (!window.Swal) {
            showToast('Komponen konfirmasi belum siap. Muat ulang halaman lalu coba kembali.', 'error');
            return;
        }

        let confirmation;
        try {
            confirmation = await window.Swal.fire({
                icon: 'warning',
                title: 'Hapus sheet?',
                text: `"${sheet.title}" akan dihapus dari workbook.`,
                showCancelButton: true,
                confirmButtonText: 'Hapus sheet',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                focusCancel: true,
                buttonsStyling: false,
                customClass: {
                    popup: 'asix-sheet-swal',
                    title: 'asix-sheet-swal-title',
                    confirmButton: 'asix-sheet-swal-confirm',
                    cancelButton: 'asix-sheet-swal-cancel',
                },
            });
        } catch (_) {
            showToast('Konfirmasi tidak dapat ditampilkan.', 'error');
            return;
        }

        if (!confirmation.isConfirmed) return;
        const deletingPosition = state.activeSheetPosition;
        sheetCollectionAction({
            type: 'delete_sheet',
            sheet: sheetOperationReference(sheet),
        }, sheets => {
            sheets.splice(deletingPosition, 1);
        }, 'Hapus sheet', Math.max(0, deletingPosition - 1));
    }

    function rangesIntersect(a, b) {
        return a.startRow <= b.endRow && a.endRow >= b.startRow
            && a.startCol <= b.endCol && a.endCol >= b.startCol;
    }

    function rangesEqual(a, b) {
        return a.startRow === b.startRow && a.endRow === b.endRow
            && a.startCol === b.startCol && a.endCol === b.endCol;
    }

    function mergedRangeEntries(sheet = activeSheet()) {
        return (sheet?.merged_cells || []).map(address => ({
            address,
            range: parseRange(address),
        })).filter(entry => entry.range);
    }

    function exactMergedRange(sheet, range) {
        return mergedRangeEntries(sheet).find(merged => rangesEqual(merged.range, range)) || null;
    }

    function rangeCellCount(range) {
        return Math.max(0, range.endRow - range.startRow + 1)
            * Math.max(0, range.endCol - range.startCol + 1);
    }

    function allowInteractiveRange(range, maximum = MAX_INTERACTIVE_RANGE_CELLS) {
        const count = rangeCellCount(range);
        if (count <= maximum) return true;
        showToast(
            `Operasi ini dibatasi ${maximum.toLocaleString('id-ID')} sel agar editor tetap responsif.`,
            'error',
            4200
        );
        return false;
    }

    function copySelection(cut = false) {
        const sheet = activeSheet();
        const range = selectedRange();
        if (!sheet || !allowInteractiveRange(range, MAX_CLIPBOARD_CELLS)) return '';
        const textRows = [];
        const cellRows = [];
        for (let row = range.startRow; row <= range.endRow; row++) {
            const values = [];
            const cells = [];
            for (let col = range.startCol; col <= range.endCol; col++) {
                const cell = sheet.cells[cellAddress(row, col)];
                cells.push(cell ? deepClone(cell) : null);
                values.push(
                    String(cell?.formula ?? cell?.display ?? cell?.value ?? '')
                        .replace(/\t/g, ' ')
                        .replace(/\r?\n/g, ' ')
                );
            }
            textRows.push(values);
            cellRows.push(cells);
        }
        const text = textRows.map(values => values.join('\t')).join('\n');
        state.internalClipboard = {
            text,
            cells: cellRows,
            cut,
            sourceRange: { ...range },
            sourceSheet: sheetOperationReference(sheet),
        };
        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(text).catch(() => {});
        }
        showToast(cut ? 'Rentang siap dipindahkan.' : 'Rentang disalin.', 'info', 1800);
        return text;
    }

    async function pasteSelection(clipboardEvent = null) {
        if (!state.canEdit || state.saving || state.conflictLocked) return;
        let text = clipboardEvent?.clipboardData?.getData('text/plain') || '';
        if (!text && state.internalClipboard?.text) {
            text = state.internalClipboard.text;
        } else if (!text && navigator.clipboard?.readText) {
            try { text = await navigator.clipboard.readText(); } catch (_) {}
        }
        if (!text) return;

        const internal = state.internalClipboard?.text === text ? state.internalClipboard : null;
        const rawRows = text.replace(/\r/g, '').split('\n');
        if (rawRows.length > 1 && rawRows[rawRows.length - 1] === '') rawRows.pop();
        const sourceRows = internal?.cells || rawRows.map(line => (
            line.split('\t').map(value => {
                const formula = value.startsWith('=') ? value : null;
                return value === '' ? null : normalizeCell({
                    value: formula ? null : value,
                    formula,
                    display: value,
                    data_type: formula ? 'f' : 's',
                    style: {},
                });
            })
        ));
        const pastedCellCount = sourceRows.reduce((total, row) => total + row.length, 0);
        if (pastedCellCount > MAX_CLIPBOARD_CELLS) {
            showToast(
                `Paste dibatasi ${MAX_CLIPBOARD_CELLS.toLocaleString('id-ID')} sel per operasi.`,
                'error',
                4200
            );
            return;
        }

        const point = activePoint();
        const changes = [];
        sourceRows.forEach((row, rowOffset) => {
            row.forEach((cell, colOffset) => {
                changes.push({
                    row: point.row + rowOffset,
                    col: point.col + colOffset,
                    cell: cell ? deepClone(cell) : null,
                    copyStyle: Boolean(internal),
                });
            });
        });

        if (internal?.cut) {
            const destinationSheet = activeSheet();
            const destinationReference = sheetOperationReference(destinationSheet);
            const sourcePosition = state.workbook.sheets.findIndex(sheet => (
                String(sheet.index) === String(internal.sourceSheet) || sheet.title === internal.sourceSheet
            ));
            if (sourcePosition < 0) {
                showToast('Sheet sumber cut tidak lagi tersedia.', 'error');
                return;
            }

            const beforeWorkbook = deepClone(state.workbook);
            const beforePosition = state.activeSheetPosition;
            const sourceSheet = state.workbook.sheets[sourcePosition];
            for (let row = internal.sourceRange.startRow; row <= internal.sourceRange.endRow; row++) {
                for (let col = internal.sourceRange.startCol; col <= internal.sourceRange.endCol; col++) {
                    delete sourceSheet.cells[cellAddress(row, col)];
                }
            }
            changes.forEach(change => {
                const address = cellAddress(change.row, change.col);
                if (change.cell === null) delete destinationSheet.cells[address];
                else destinationSheet.cells[address] = deepClone(change.cell);
            });
            refreshSheetBounds(sourceSheet);
            refreshSheetBounds(destinationSheet);
            const afterWorkbook = deepClone(state.workbook);
            state.workbook = beforeWorkbook;
            state.activeSheetPosition = beforePosition;

            const operations = [
                {
                    type: 'clear_range',
                    sheet: internal.sourceSheet,
                    range: rangeAddress(internal.sourceRange),
                },
                ...cellChangeOperations(changes, destinationReference),
            ];
            const applied = performAction({
                label: 'Pindahkan data',
                originSheet: destinationReference,
                operations,
                apply: () => {
                    state.workbook = deepClone(afterWorkbook);
                    state.activeSheetPosition = beforePosition;
                },
                revert: () => {
                    state.workbook = deepClone(beforeWorkbook);
                    state.activeSheetPosition = beforePosition;
                },
            });
            if (applied) state.internalClipboard = null;
        } else {
            performAction(createCellMutation(changes, 'Tempel data'));
        }
        selectRange({
            startRow: point.row,
            startCol: point.col,
            endRow: point.row + Math.max(0, sourceRows.length - 1),
            endCol: point.col + Math.max(0, Math.max(...sourceRows.map(row => row.length)) - 1),
        });
    }

    function findMatches() {
        const query = elements.findInput.value;
        const caseSensitive = elements.findCase.checked;
        const needle = caseSensitive ? query : query.toLowerCase();
        state.findMatches = [];
        state.findIndex = -1;
        if (!query) {
            elements.findResult.textContent = 'Masukkan teks atau angka yang ingin dicari.';
            return;
        }
        Object.entries(activeSheet().cells || {}).forEach(([address, cell]) => {
            const haystackRaw = String(cell.formula ?? cell.display ?? cell.value ?? '');
            const haystack = caseSensitive ? haystackRaw : haystackRaw.toLowerCase();
            if (haystack.includes(needle)) state.findMatches.push({ address, cell: haystackRaw });
        });
        state.findMatches.sort((a, b) => {
            const pointA = parseAddress(a.address);
            const pointB = parseAddress(b.address);
            return pointA.row - pointB.row || pointA.col - pointB.col;
        });
        elements.findResult.textContent = state.findMatches.length
            ? `${state.findMatches.length} kecocokan ditemukan pada sheet ini.`
            : 'Tidak ada kecocokan.';
    }

    function findNext() {
        findMatches();
        if (!state.findMatches.length) return;
        const active = activePoint();
        const currentIndex = state.findMatches.findIndex(match => {
            const point = parseAddress(match.address);
            return point.row > active.row || (point.row === active.row && point.col > active.col);
        });
        state.findIndex = currentIndex >= 0 ? currentIndex : 0;
        const point = parseAddress(state.findMatches[state.findIndex].address);
        selectCell(point.row, point.col);
        scrollSelectionIntoView();
        elements.findResult.textContent = `${state.findIndex + 1} dari ${state.findMatches.length}: ${state.findMatches[state.findIndex].address}`;
    }

    function replaceCurrent(findAll = false) {
        findMatches();
        if (!state.findMatches.length || !state.canEdit || state.saving || state.conflictLocked) return;
        const replacement = elements.replaceInput.value;
        const query = elements.findInput.value;
        const caseSensitive = elements.findCase.checked;
        const candidates = findAll
            ? state.findMatches
            : state.findMatches.filter(match => match.address === cellAddress(activePoint().row, activePoint().col)).slice(0, 1);
        if (!candidates.length) {
            findNext();
            return;
        }
        const changes = candidates.map(match => {
            const point = parseAddress(match.address);
            const source = String(activeSheet().cells[match.address]?.formula
                ?? activeSheet().cells[match.address]?.value
                ?? '');
            const value = caseSensitive
                ? source.split(query).join(replacement)
                : source.replace(new RegExp(escapeRegExp(query), 'gi'), replacement);
            const formula = value.startsWith('=') ? value : null;
            return {
                row: point.row,
                col: point.col,
                cell: {
                    ...normalizeCell(activeSheet().cells[match.address]),
                    value: formula ? null : value,
                    formula,
                    display: value,
                },
            };
        });
        performAction(createCellMutation(changes, findAll ? 'Ganti semua' : 'Ganti nilai'));
        elements.findResult.textContent = `${changes.length} sel diperbarui.`;
    }

    function escapeRegExp(value) {
        return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function selectCell(row, col, extend = false) {
        let point = {
            row: Math.max(1, Math.min(MAX_WORKSHEET_ROWS, Number(row) || 1)),
            col: Math.max(1, Math.min(MAX_RENDER_COLUMNS, Number(col) || 1)),
        };
        const merged = state.mergedMap.get(cellAddress(point.row, point.col));
        if (merged && extend) {
            const anchor = state.selection.anchor;
            point = {
                row: anchor.row <= merged.range.startRow ? merged.range.endRow : merged.range.startRow,
                col: anchor.col <= merged.range.startCol ? merged.range.endCol : merged.range.startCol,
            };
            state.selection.focus = point;
        } else if (merged) {
            point = { row: merged.range.startRow, col: merged.range.startCol };
            state.selection = {
                anchor: { row: merged.range.endRow, col: merged.range.endCol },
                focus: point,
            };
        } else if (extend) {
            state.selection.focus = point;
        } else {
            state.selection = { anchor: point, focus: point };
        }
        if (!state.metrics || point.row > state.metrics.rows || point.col > state.metrics.columns) {
            rebuildMetrics();
        }
        syncSelectionUi();
        scheduleRender();
    }

    function selectRange(range) {
        const normalized = {
            startRow: Math.max(1, Math.min(MAX_WORKSHEET_ROWS, Number(range.startRow) || 1)),
            endRow: Math.max(1, Math.min(MAX_WORKSHEET_ROWS, Number(range.endRow) || 1)),
            startCol: Math.max(1, Math.min(MAX_RENDER_COLUMNS, Number(range.startCol) || 1)),
            endCol: Math.max(1, Math.min(MAX_RENDER_COLUMNS, Number(range.endCol) || 1)),
        };
        if (!allowInteractiveRange(normalized)) return false;
        state.selection = {
            anchor: { row: normalized.startRow, col: normalized.startCol },
            focus: { row: normalized.endRow, col: normalized.endCol },
        };
        if (!state.metrics
            || normalized.endRow > state.metrics.rows
            || normalized.endCol > state.metrics.columns) {
            rebuildMetrics();
        }
        syncSelectionUi();
        scheduleRender();
        return true;
    }

    function syncSelectionUi() {
        const sheet = activeSheet();
        if (!sheet) return;
        const range = selectedRange();
        const point = activePoint();
        const cell = sheet.cells[cellAddress(point.row, point.col)];
        elements.nameBox.value = rangeAddress(range);
        elements.formulaInput.value = String(cell?.formula ?? cell?.value ?? '');
        const style = cell?.style || {};
        elements.boldButton.classList.toggle('is-active', Boolean(style.bold));
        elements.italicButton.classList.toggle('is-active', Boolean(style.italic));
        elements.underlineButton.classList.toggle('is-active', Boolean(style.underline));
        elements.wrapButton.classList.toggle('is-active', Boolean(style.wrap));
        elements.fontSize.value = style.font_size ? String(style.font_size) : '';
        elements.horizontal.value = style.horizontal || '';
        elements.vertical.value = style.vertical || '';
        elements.borderStyle.value = style.border_style || '';
        elements.numberFormat.value = style.number_format || 'General';

        const mergedRange = exactMergedRange(sheet, range);
        const mergeLabel = mergedRange ? 'Pisahkan sel' : 'Gabungkan sel';
        elements.mergeButton.classList.toggle('is-active', Boolean(mergedRange));
        elements.mergeButton.title = mergeLabel;
        elements.mergeButton.setAttribute('aria-label', mergeLabel);
        elements.mergeButton.setAttribute('aria-pressed', mergedRange ? 'true' : 'false');
        const mergeIcon = elements.mergeButton.querySelector('i');
        if (mergeIcon) {
            mergeIcon.className = mergedRange ? 'fas fa-object-ungroup' : 'fas fa-object-group';
        }
    }

    function beginCellEdit(initialValue = null) {
        if (!state.canEdit || state.saving || state.conflictLocked || state.editing) return;
        const point = activePoint();
        scrollSelectionIntoView();
        scheduleRender(true);
        window.requestAnimationFrame(() => {
            const address = cellAddress(point.row, point.col);
            const cellElement = elements.canvas.querySelector(`[data-address="${address}"]`);
            if (!cellElement) return;
            const cellRect = cellElement.getBoundingClientRect();
            const shellRect = elements.gridShell.getBoundingClientRect();
            const existing = activeSheet().cells[address];
            elements.cellEditor.value = initialValue !== null
                ? initialValue
                : String(existing?.formula ?? existing?.value ?? '');
            elements.cellEditor.style.left = `${cellRect.left - shellRect.left}px`;
            elements.cellEditor.style.top = `${cellRect.top - shellRect.top}px`;
            elements.cellEditor.style.width = `${Math.max(cellRect.width, 90)}px`;
            elements.cellEditor.style.height = `${Math.max(cellRect.height, 28)}px`;
            elements.cellEditor.classList.add('is-visible');
            state.editing = true;
            elements.cellEditor.focus();
            if (initialValue === null) elements.cellEditor.select();
        });
    }

    function commitCellEditor(move = null) {
        if (!state.editing) return;
        const point = activePoint();
        const value = elements.cellEditor.value;
        elements.cellEditor.classList.remove('is-visible');
        state.editing = false;
        setCellInput(point.row, point.col, value);
        if (move) moveSelection(move.row, move.col);
        elements.viewport.focus({ preventScroll: true });
    }

    function cancelCellEditor() {
        if (!state.editing) return;
        elements.cellEditor.classList.remove('is-visible');
        state.editing = false;
        elements.viewport.focus({ preventScroll: true });
    }

    function moveSelection(rowDelta, colDelta, extend = false) {
        const point = activePoint();
        selectCell(point.row + rowDelta, point.col + colDelta, extend);
        scrollSelectionIntoView();
    }

    function columnWidth(sheet, col) {
        const raw = sheet.column_widths?.[columnLabel(col)] ?? sheet.column_widths?.[col];
        if (raw === null || raw === undefined || raw === '') return DEFAULT_COLUMN_WIDTH;
        const number = Number(raw);
        if (!Number.isFinite(number)) return DEFAULT_COLUMN_WIDTH;
        const pixels = number * 7 + 8;
        return Math.min(480, Math.max(48, pixels));
    }

    function rowHeight(sheet, row) {
        const raw = sheet.row_heights?.[row] ?? sheet.row_heights?.[String(row)];
        if (raw === null || raw === undefined || raw === '') return DEFAULT_ROW_HEIGHT;
        const number = Number(raw);
        if (!Number.isFinite(number)) return DEFAULT_ROW_HEIGHT;
        const pixels = number * 1.333;
        return Math.min(240, Math.max(20, pixels));
    }

    function rebuildMetrics() {
        const sheet = activeSheet();
        if (!sheet) return;
        const selected = selectedRange();
        const requestedRows = Math.max(200, (sheet.max_row || 1) + 30, selected.endRow + 10);
        const requestedColumns = Math.max(26, (sheet.max_col || 1) + 5, selected.endCol + 5);
        const rows = Math.min(MAX_WORKSHEET_ROWS, requestedRows);
        const columns = Math.min(MAX_RENDER_COLUMNS, requestedColumns);
        const rowOffsets = new Float64Array(rows + 1);
        const columnOffsets = new Float64Array(columns + 1);
        for (let row = 1; row <= rows; row++) {
            rowOffsets[row] = rowOffsets[row - 1] + rowHeight(sheet, row);
        }
        for (let col = 1; col <= columns; col++) {
            columnOffsets[col] = columnOffsets[col - 1] + columnWidth(sheet, col);
        }
        state.metrics = {
            rows,
            columns,
            rowOffsets,
            columnOffsets,
            totalHeight: rowOffsets[rows],
            totalWidth: columnOffsets[columns],
        };
        elements.canvas.style.width = `${state.metrics.totalWidth}px`;
        elements.canvas.style.height = `${state.metrics.totalHeight}px`;
        elements.columnTrack.style.width = `${state.metrics.totalWidth}px`;
        elements.rowTrack.style.height = `${state.metrics.totalHeight}px`;
        updateFooterStatus();
        rebuildMergedMap();
        setBanner(elements.warningBanner, [
            ...(state.file?.warnings || []),
            ...(requestedColumns > MAX_RENDER_COLUMNS
                ? [`Editor kompatibel menampilkan sampai ${MAX_RENDER_COLUMNS.toLocaleString('id-ID')} kolom.`]
                : []),
        ], 'warning');
    }

    function rebuildMergedMap() {
        state.mergedMap = new Map();
        (activeSheet()?.merged_cells || []).forEach(rangeValue => {
            const parsed = parseRange(rangeValue);
            if (!parsed || !state.metrics) return;
            if (parsed.startRow > state.metrics.rows || parsed.startCol > state.metrics.columns) return;
            const range = {
                startRow: parsed.startRow,
                startCol: parsed.startCol,
                endRow: Math.min(parsed.endRow, state.metrics.rows),
                endCol: Math.min(parsed.endCol, state.metrics.columns),
            };
            if (rangeCellCount(range) > MAX_INTERACTIVE_RANGE_CELLS) return;
            for (let row = range.startRow; row <= range.endRow; row++) {
                for (let col = range.startCol; col <= range.endCol; col++) {
                    state.mergedMap.set(cellAddress(row, col), {
                        range,
                        master: row === range.startRow && col === range.startCol,
                    });
                }
            }
        });
    }

    function findIndexAtOffset(offsets, value) {
        let low = 0;
        let high = offsets.length - 1;
        while (low < high) {
            const middle = Math.floor((low + high + 1) / 2);
            if (offsets[middle] <= value) low = middle;
            else high = middle - 1;
        }
        return Math.max(1, Math.min(offsets.length - 1, low + 1));
    }

    function visibleIndices(offsets, scroll, viewportSize, limit, buffer = 2) {
        const start = Math.max(1, findIndexAtOffset(offsets, Math.max(0, scroll)) - buffer);
        const end = Math.min(limit, findIndexAtOffset(offsets, scroll + viewportSize) + buffer);
        const indices = [];
        for (let index = start; index <= end; index++) indices.push(index);
        return indices;
    }

    function freezeCounts() {
        const point = parseAddress(activeSheet()?.freeze_pane);
        return point
            ? {
                rows: Math.min(
                    MAX_FROZEN_ROWS,
                    state.metrics?.rows || MAX_FROZEN_ROWS,
                    Math.max(0, point.row - 1)
                ),
                columns: Math.min(
                    MAX_FROZEN_COLUMNS,
                    state.metrics?.columns || MAX_FROZEN_COLUMNS,
                    Math.max(0, point.col - 1)
                ),
            }
            : { rows: 0, columns: 0 };
    }

    function mergeUniqueIndices(indices, frozenCount, limit) {
        const set = new Set(indices);
        for (let index = 1; index <= Math.min(frozenCount, limit); index++) set.add(index);
        return Array.from(set).sort((a, b) => a - b);
    }

    function scheduleRender(immediate = false) {
        if (!state.workbook || !state.metrics) return;
        if (state.renderFrame) cancelAnimationFrame(state.renderFrame);
        if (immediate) {
            state.renderFrame = null;
            renderGrid();
        } else {
            state.renderFrame = requestAnimationFrame(() => {
                state.renderFrame = null;
                renderGrid();
            });
        }
    }

    function renderGrid() {
        const sheet = activeSheet();
        const metrics = state.metrics;
        if (!sheet || !metrics) return;
        const scrollLeft = elements.viewport.scrollLeft;
        const scrollTop = elements.viewport.scrollTop;
        const viewportWidth = elements.viewport.clientWidth;
        const viewportHeight = elements.viewport.clientHeight;
        const freeze = freezeCounts();
        const rows = mergeUniqueIndices(
            visibleIndices(metrics.rowOffsets, scrollTop, viewportHeight, metrics.rows),
            freeze.rows,
            metrics.rows
        );
        const columns = mergeUniqueIndices(
            visibleIndices(metrics.columnOffsets, scrollLeft, viewportWidth, metrics.columns),
            freeze.columns,
            metrics.columns
        );
        const selection = selectedRange();

        requestVisibleRows(rows);

        elements.canvas.replaceChildren();
        elements.columnTrack.replaceChildren();
        elements.rowTrack.replaceChildren();
        elements.columnTrack.style.transform = `translateX(${-scrollLeft}px)`;
        elements.rowTrack.style.transform = `translateY(${-scrollTop}px)`;

        columns.forEach(col => renderColumnHeader(col, selection, metrics));
        rows.forEach(row => renderRowHeader(row, selection, metrics));

        rows.forEach(row => {
            columns.forEach(col => {
                const address = cellAddress(row, col);
                const merged = state.mergedMap.get(address);
                if (merged && !merged.master) return;
                renderCell(row, col, address, merged, selection, freeze, scrollLeft, scrollTop, metrics);
            });
        });
    }

    function renderColumnHeader(col, selection, metrics) {
        const header = document.createElement('div');
        header.className = 'asix-sheet-column-header';
        header.textContent = columnLabel(col);
        header.style.left = `${metrics.columnOffsets[col - 1]}px`;
        header.style.width = `${metrics.columnOffsets[col] - metrics.columnOffsets[col - 1]}px`;
        header.classList.toggle('is-selected', col >= selection.startCol && col <= selection.endCol);
        header.dataset.col = col;
        header.addEventListener('pointerdown', event => {
            if (event.button !== 0) return;
            selectRange({
                startRow: 1,
                endRow: Math.max(activeSheet().max_row, 200),
                startCol: col,
                endCol: col,
            });
            elements.viewport.focus();
        });
        header.addEventListener('dblclick', () => state.canEdit && setColumnWidth());
        elements.columnTrack.appendChild(header);
    }

    function renderRowHeader(row, selection, metrics) {
        const header = document.createElement('div');
        header.className = 'asix-sheet-row-header';
        header.textContent = row;
        header.style.top = `${metrics.rowOffsets[row - 1]}px`;
        header.style.height = `${metrics.rowOffsets[row] - metrics.rowOffsets[row - 1]}px`;
        header.classList.toggle('is-selected', row >= selection.startRow && row <= selection.endRow);
        header.dataset.row = row;
        header.addEventListener('pointerdown', event => {
            if (event.button !== 0) return;
            selectRange({
                startRow: row,
                endRow: row,
                startCol: 1,
                endCol: Math.max(activeSheet().max_col, 26),
            });
            elements.viewport.focus();
        });
        header.addEventListener('dblclick', () => state.canEdit && setRowHeight());
        elements.rowTrack.appendChild(header);
    }

    function renderCell(row, col, address, merged, selection, freeze, scrollLeft, scrollTop, metrics) {
        const cell = activeSheet().cells[address];
        const style = cell?.style || {};
        const element = document.createElement('div');
        element.className = 'asix-sheet-cell';
        element.dataset.address = address;
        element.dataset.row = row;
        element.dataset.col = col;
        element.setAttribute('role', 'gridcell');
        element.setAttribute('aria-label', `${address}: ${cell?.display ?? cell?.value ?? ''}`);

        const range = merged?.range || { startRow: row, endRow: row, startCol: col, endCol: col };
        const left = metrics.columnOffsets[range.startCol - 1];
        const top = metrics.rowOffsets[range.startRow - 1];
        const width = metrics.columnOffsets[Math.min(range.endCol, metrics.columns)] - left;
        const height = metrics.rowOffsets[Math.min(range.endRow, metrics.rows)] - top;
        element.style.left = `${left}px`;
        element.style.top = `${top}px`;
        element.style.width = `${width}px`;
        element.style.height = `${height}px`;

        const frozenX = col <= freeze.columns;
        const frozenY = row <= freeze.rows;
        if (frozenX || frozenY) {
            element.classList.add('is-frozen');
            element.style.transform = `translate(${frozenX ? scrollLeft : 0}px, ${frozenY ? scrollTop : 0}px)`;
            element.style.zIndex = frozenX && frozenY ? '6' : '4';
        }

        element.classList.toggle('is-selected', row >= selection.startRow && row <= selection.endRow
            && col >= selection.startCol && col <= selection.endCol);
        element.classList.toggle('is-active', row === state.selection.focus.row && col === state.selection.focus.col);
        element.classList.toggle('is-wrap', Boolean(style.wrap));
        element.textContent = String(cell?.display ?? cell?.value ?? '');
        applyCellStyle(element, style);

        element.addEventListener('pointerdown', event => {
            if (event.button !== 0) return;
            event.preventDefault();
            state.dragging = true;
            selectCell(row, col, event.shiftKey);
            elements.viewport.focus();
        });
        element.addEventListener('pointerenter', () => {
            if (state.dragging) selectCell(row, col, true);
        });
        element.addEventListener('dblclick', () => beginCellEdit());
        element.addEventListener('contextmenu', event => {
            event.preventDefault();
            selectCell(row, col, event.shiftKey);
            showContextMenu(event.clientX, event.clientY);
        });
        elements.canvas.appendChild(element);
    }

    function applyCellStyle(element, style) {
        element.style.fontWeight = style.bold ? '700' : '';
        element.style.fontStyle = style.italic ? 'italic' : '';
        element.style.textDecoration = style.underline ? 'underline' : '';
        element.style.color = sanitizeColor(style.font_color);
        element.style.backgroundColor = sanitizeColor(style.fill_color);
        element.style.fontSize = style.font_size ? `${Math.min(48, Math.max(8, Number(style.font_size)))}px` : '';
        element.style.justifyContent = style.horizontal === 'center' ? 'center'
            : style.horizontal === 'right' ? 'flex-end' : 'flex-start';
        element.style.alignItems = style.vertical === 'top' ? 'flex-start'
            : style.vertical === 'bottom' ? 'flex-end' : 'center';
        const borderColor = sanitizeColor(style.border_color, '#64748b');
        const borderStyle = {
            hair: '1px solid',
            dotted: '1px dotted',
            dashed: '1px dashed',
            thin: '1px solid',
            medium: '2px solid',
            thick: '3px solid',
            double: '3px double',
        }[style.border_style];
        if (borderStyle) {
            element.style.border = `${borderStyle} ${borderColor}`;
        }
    }

    function showContextMenu(x, y) {
        closeMenus();
        elements.contextMenu.classList.add('is-visible');
        const width = 208;
        const height = elements.contextMenu.offsetHeight || 310;
        elements.contextMenu.style.left = `${Math.max(8, Math.min(window.innerWidth - width - 8, x))}px`;
        elements.contextMenu.style.top = `${Math.max(8, Math.min(window.innerHeight - height - 8, y))}px`;
    }

    function scrollSelectionIntoView() {
        if (!state.metrics) return;
        const point = activePoint();
        const metrics = state.metrics;
        const left = metrics.columnOffsets[Math.max(0, point.col - 1)];
        const right = metrics.columnOffsets[Math.min(metrics.columns, point.col)];
        const top = metrics.rowOffsets[Math.max(0, point.row - 1)];
        const bottom = metrics.rowOffsets[Math.min(metrics.rows, point.row)];
        if (left < elements.viewport.scrollLeft) elements.viewport.scrollLeft = left;
        else if (right > elements.viewport.scrollLeft + elements.viewport.clientWidth) {
            elements.viewport.scrollLeft = right - elements.viewport.clientWidth;
        }
        if (top < elements.viewport.scrollTop) elements.viewport.scrollTop = top;
        else if (bottom > elements.viewport.scrollTop + elements.viewport.clientHeight) {
            elements.viewport.scrollTop = bottom - elements.viewport.clientHeight;
        }
        scheduleRender();
    }

    function renderTabs() {
        elements.tabs.replaceChildren();
        state.workbook?.sheets?.forEach((sheet, position) => {
            const tab = document.createElement('button');
            tab.type = 'button';
            tab.className = 'asix-sheet-tab';
            tab.textContent = sheet.title;
            tab.title = sheet.title;
            tab.classList.toggle('is-active', position === state.activeSheetPosition);
            tab.addEventListener('click', () => {
                commitCellEditor();
                state.activeSheetPosition = position;
                state.workbook.active_sheet = sheet.index;
                state.selection = { anchor: { row: 1, col: 1 }, focus: { row: 1, col: 1 } };
                elements.viewport.scrollTo({ left: 0, top: 0 });
                rebuildMetrics();
                renderTabs();
                syncSelectionUi();
                scheduleRender();
            });
            tab.addEventListener('dblclick', () => state.canEdit && renameSheet());
            elements.tabs.appendChild(tab);
        });
        elements.addTab.disabled = !state.canEdit || state.saving || state.loading || state.conflictLocked;
    }

    function applyRawOperation(operation) {
        if (!operation || !state.workbook) return;
        const position = state.workbook.sheets.findIndex(sheet => (
            String(sheet.index) === String(operation.sheet) || sheet.title === operation.sheet
        ));
        if (position >= 0) state.activeSheetPosition = position;
        const sheet = activeSheet();
        switch (operation.type) {
            case 'set_cell': {
                const formula = operation.formula || null;
                sheet.cells[operation.address] = normalizeCell({
                    value: formula ? null : operation.value,
                    formula,
                    display: formula ?? operation.value ?? '',
                    style: sheet.cells[operation.address]?.style || {},
                });
                break;
            }
            case 'clear_range': {
                const range = parseRange(operation.range);
                if (!range) break;
                for (let row = range.startRow; row <= range.endRow; row++) {
                    for (let col = range.startCol; col <= range.endCol; col++) delete sheet.cells[cellAddress(row, col)];
                }
                break;
            }
            case 'set_style': {
                const range = parseRange(operation.range);
                if (!range) break;
                for (let row = range.startRow; row <= range.endRow; row++) {
                    for (let col = range.startCol; col <= range.endCol; col++) {
                        const address = cellAddress(row, col);
                        const cell = normalizeCell(sheet.cells[address] || { display: '', style: {} });
                        cell.style = { ...cell.style, ...(operation.style || {}) };
                        sheet.cells[address] = cell;
                    }
                }
                break;
            }
            case 'merge':
                if (!sheet.merged_cells.includes(operation.range)) sheet.merged_cells.push(operation.range);
                break;
            case 'unmerge':
                sheet.merged_cells = sheet.merged_cells.filter(range => range !== operation.range);
                break;
            case 'freeze_pane':
                sheet.freeze_pane = operation.pane || null;
                break;
            case 'set_auto_filter':
                sheet.auto_filter = operation.range || null;
                break;
            case 'set_column_width':
                sheet.column_widths[columnLabel(operation.column)] = operation.width;
                break;
            case 'set_row_height':
                sheet.row_heights[operation.row] = operation.height;
                break;
            case 'insert_rows': {
                const row = Math.max(1, Number(operation.row) || 1);
                const count = Math.max(1, Number(operation.count) || 1);
                remapCells(sheet, point => ({
                    row: point.row >= row ? point.row + count : point.row,
                    col: point.col,
                }));
                sheet.max_row += count;
                break;
            }
            case 'delete_rows': {
                const row = Math.max(1, Number(operation.row) || 1);
                const count = Math.max(1, Number(operation.count) || 1);
                const last = row + count - 1;
                remapCells(sheet, point => {
                    if (point.row >= row && point.row <= last) return null;
                    return { row: point.row > last ? point.row - count : point.row, col: point.col };
                });
                sheet.max_row = Math.max(1, sheet.max_row - count);
                break;
            }
            case 'insert_columns': {
                const column = Math.max(1, Number(operation.column) || columnNumber(operation.column) || 1);
                const count = Math.max(1, Number(operation.count) || 1);
                remapCells(sheet, point => ({
                    row: point.row,
                    col: point.col >= column ? point.col + count : point.col,
                }));
                sheet.max_col += count;
                break;
            }
            case 'delete_columns': {
                const column = Math.max(1, Number(operation.column) || columnNumber(operation.column) || 1);
                const count = Math.max(1, Number(operation.count) || 1);
                const last = column + count - 1;
                remapCells(sheet, point => {
                    if (point.col >= column && point.col <= last) return null;
                    return { row: point.row, col: point.col > last ? point.col - count : point.col };
                });
                sheet.max_col = Math.max(1, sheet.max_col - count);
                break;
            }
            case 'sort_range': {
                const range = parseRange(operation.range);
                if (!range) break;
                const sortColumn = Math.max(range.startCol, Math.min(range.endCol,
                    Number(operation.column) || columnNumber(operation.column) || range.startCol));
                const rows = [];
                for (let row = range.startRow; row <= range.endRow; row++) {
                    const packet = [];
                    for (let col = range.startCol; col <= range.endCol; col++) {
                        const address = cellAddress(row, col);
                        packet.push(sheet.cells[address] ? deepClone(sheet.cells[address]) : null);
                    }
                    const keyCell = sheet.cells[cellAddress(row, sortColumn)];
                    rows.push({ packet, key: keyCell?.value ?? keyCell?.display ?? '' });
                }
                rows.sort((left, right) => {
                    const numericLeft = Number(left.key);
                    const numericRight = Number(right.key);
                    const comparison = Number.isFinite(numericLeft) && Number.isFinite(numericRight)
                        ? numericLeft - numericRight
                        : String(left.key).localeCompare(String(right.key), 'id', { numeric: true, sensitivity: 'base' });
                    return operation.direction === 'desc' ? -comparison : comparison;
                });
                rows.forEach((rowData, rowOffset) => {
                    rowData.packet.forEach((cell, colOffset) => {
                        const address = cellAddress(range.startRow + rowOffset, range.startCol + colOffset);
                        if (cell === null) delete sheet.cells[address];
                        else sheet.cells[address] = cell;
                    });
                });
                break;
            }
            case 'add_sheet': {
                const newIndex = Math.max(-1, ...state.workbook.sheets.map(item => Number(item.index) || 0)) + 1;
                state.workbook.sheets.push(normalizeSheet({
                    index: newIndex,
                    title: operation.title || uniqueSheetTitle(),
                }, state.workbook.sheets.length));
                state.activeSheetPosition = state.workbook.sheets.length - 1;
                break;
            }
            case 'rename_sheet':
                sheet.title = operation.title || sheet.title;
                break;
            case 'delete_sheet':
                if (state.workbook.sheets.length > 1) {
                    state.workbook.sheets.splice(state.activeSheetPosition, 1);
                    state.activeSheetPosition = Math.max(0, state.activeSheetPosition - 1);
                }
                break;
            case 'duplicate_sheet': {
                const copy = deepClone(sheet);
                copy.index = Math.max(-1, ...state.workbook.sheets.map(item => Number(item.index) || 0)) + 1;
                copy.title = operation.title || uniqueSheetTitle(`${sheet.title} Salinan`);
                state.workbook.sheets.push(copy);
                state.activeSheetPosition = state.workbook.sheets.length - 1;
                break;
            }
            default:
                break;
        }
        refreshSheetBounds(activeSheet());
    }

    function executeCommand(command) {
        closeMenus();
        switch (command) {
            case 'save': saveWorkbook(); break;
            case 'undo': undo(); break;
            case 'redo': redo(); break;
            case 'copy': copySelection(false); break;
            case 'cut': copySelection(true); break;
            case 'paste': pasteSelection(); break;
            case 'clear': clearRange(); break;
            case 'find':
                showModal(elements.findModal);
                window.setTimeout(() => elements.findInput.focus(), 30);
                break;
            case 'bold': toggleStyle('bold', 'Format tebal'); break;
            case 'italic': toggleStyle('italic', 'Format miring'); break;
            case 'underline': toggleStyle('underline', 'Format garis bawah'); break;
            case 'wrap': toggleStyle('wrap', 'Bungkus teks'); break;
            case 'merge': toggleMerge(); break;
            case 'insert-rows': insertRows(); break;
            case 'delete-rows': deleteRows(); break;
            case 'insert-columns': insertColumns(); break;
            case 'delete-columns': deleteColumns(); break;
            case 'column-width': setColumnWidth(); break;
            case 'row-height': setRowHeight(); break;
            case 'toggle-filter': toggleAutoFilter(); break;
            case 'sort-asc': sortRange('asc'); break;
            case 'sort-desc': sortRange('desc'); break;
            case 'freeze': freezePane(false); break;
            case 'unfreeze': freezePane(true); break;
            case 'add-sheet': addSheet(); break;
            case 'rename-sheet': renameSheet(); break;
            case 'duplicate-sheet': duplicateSheet(); break;
            case 'delete-sheet': deleteSheet(); break;
        }
    }

    function handleViewportKeydown(event) {
        if (state.editing || !state.workbook) return;
        const modifier = event.ctrlKey || event.metaKey;
        if (modifier) {
            const key = event.key.toLowerCase();
            if (key === 's') { event.preventDefault(); saveWorkbook(); return; }
            if (key === 'z') { event.preventDefault(); event.shiftKey ? redo() : undo(); return; }
            if (key === 'y') { event.preventDefault(); redo(); return; }
            if (key === 'c') { event.preventDefault(); copySelection(false); return; }
            if (key === 'x') { event.preventDefault(); copySelection(true); return; }
            if (key === 'v') { event.preventDefault(); pasteSelection(); return; }
            if (key === 'f' || key === 'h') {
                event.preventDefault();
                showModal(elements.findModal);
                window.setTimeout(() => elements.findInput.focus(), 30);
                return;
            }
            if (key === 'b') { event.preventDefault(); toggleStyle('bold', 'Format tebal'); return; }
            if (key === 'i') { event.preventDefault(); toggleStyle('italic', 'Format miring'); return; }
            if (key === 'u') { event.preventDefault(); toggleStyle('underline', 'Format garis bawah'); return; }
        }

        const movement = {
            ArrowLeft: [0, -1],
            ArrowRight: [0, 1],
            ArrowUp: [-1, 0],
            ArrowDown: [1, 0],
            Tab: [0, event.shiftKey ? -1 : 1],
            Enter: [event.shiftKey ? -1 : 1, 0],
        }[event.key];
        if (movement) {
            event.preventDefault();
            moveSelection(movement[0], movement[1], event.shiftKey && event.key.startsWith('Arrow'));
            return;
        }
        if (event.key === 'Delete' || event.key === 'Backspace') {
            event.preventDefault();
            clearRange();
            return;
        }
        if (event.key === 'F2') {
            event.preventDefault();
            beginCellEdit();
            return;
        }
        if (event.key === 'Escape') {
            hideModal(elements.findModal);
            closeMenus();
            return;
        }
        if (!modifier && !event.altKey && event.key.length === 1 && state.canEdit) {
            event.preventDefault();
            beginCellEdit(event.key);
        }
    }

    function bindEvents() {
        document.body.classList.add('drive-spreadsheet-active');
        elements.viewport.addEventListener('scroll', () => scheduleRender(), { passive: true });
        elements.viewport.addEventListener('keydown', handleViewportKeydown);
        elements.viewport.addEventListener('paste', event => {
            event.preventDefault();
            pasteSelection(event);
        });
        elements.viewport.addEventListener('copy', event => {
            event.preventDefault();
            event.clipboardData.setData('text/plain', copySelection(false));
        });
        elements.viewport.addEventListener('cut', event => {
            event.preventDefault();
            event.clipboardData.setData('text/plain', copySelection(true));
        });
        document.addEventListener('pointerup', () => state.dragging = false);
        document.addEventListener('pointerdown', event => {
            if (!event.target.closest('.asix-sheet-context')) elements.contextMenu.classList.remove('is-visible');
            if (!event.target.closest('.asix-sheet-menu')) {
                $$('.asix-sheet-menu[open]').forEach(menu => menu.removeAttribute('open'));
            }
        });

        $$('[data-command]').forEach(button => {
            button.addEventListener('click', event => {
                event.preventDefault();
                if (!button.disabled) executeCommand(button.dataset.command);
            });
        });
        $$('[data-close-modal]').forEach(button => {
            button.addEventListener('click', () => hideModal(document.getElementById(button.dataset.closeModal)));
        });
        $$('.asix-sheet-modal').forEach(modal => {
            modal.addEventListener('pointerdown', event => {
                if (event.target === modal) hideModal(modal);
            });
        });
        $$('.asix-sheet-menu').forEach(menu => {
            menu.addEventListener('toggle', () => {
                if (!menu.open) return;
                $$('.asix-sheet-menu[open]').forEach(other => {
                    if (other !== menu) other.removeAttribute('open');
                });
                const summary = menu.querySelector('summary');
                const panel = menu.querySelector('.asix-sheet-menu-panel');
                const rect = summary.getBoundingClientRect();
                const width = 226;
                panel.style.left = `${Math.max(8, Math.min(window.innerWidth - width - 8, rect.left))}px`;
                const height = panel.getBoundingClientRect().height;
                panel.style.top = `${Math.max(8, Math.min(window.innerHeight - height - 8, rect.bottom + 4))}px`;
            });
        });

        elements.saveButton.addEventListener('click', () => void saveWorkbook());
        $$('[data-sheet-exit]').forEach(link => {
            link.addEventListener('click', event => {
                event.preventDefault();
                void requestEditorExit(link.href || CONFIG.backUrl);
            });
        });
        elements.exitDiscardButton.addEventListener('click', () => {
            hideModal(elements.exitModal);
            leaveEditor(state.pendingExitUrl || CONFIG.backUrl);
        });
        elements.exitSaveButton.addEventListener('click', async () => {
            elements.exitSaveButton.disabled = true;
            const saved = await saveWorkbook({quiet: true, source: 'exit'});
            elements.exitSaveButton.disabled = false;
            if (saved && !pendingOperations().length) {
                hideModal(elements.exitModal);
                leaveEditor(state.pendingExitUrl || CONFIG.backUrl);
            }
        });
        elements.reload.addEventListener('click', () => {
            const operations = state.conflictLocked
                ? (state.conflictingOperations || pendingOperations())
                : null;
            loadWorkbook({ reapplyOperations: operations });
        });
        elements.addTab.addEventListener('click', addSheet);
        elements.corner.addEventListener('click', () => {
            selectRange({
                startRow: 1,
                startCol: 1,
                endRow: Math.max(activeSheet()?.max_row || 1, 200),
                endCol: Math.max(activeSheet()?.max_col || 1, 26),
            });
        });

        elements.nameBox.addEventListener('keydown', event => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            const range = parseRange(elements.nameBox.value);
            if (!range) {
                showToast('Alamat sel tidak valid.', 'error');
                syncSelectionUi();
                return;
            }
            selectRange(range);
            scrollSelectionIntoView();
            elements.viewport.focus();
        });

        elements.formulaInput.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                const point = activePoint();
                setCellInput(point.row, point.col, elements.formulaInput.value, 'Ubah formula');
                elements.viewport.focus();
            } else if (event.key === 'Escape') {
                syncSelectionUi();
                elements.viewport.focus();
            }
        });

        elements.cellEditor.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                commitCellEditor({ row: event.shiftKey ? -1 : 1, col: 0 });
            } else if (event.key === 'Tab') {
                event.preventDefault();
                commitCellEditor({ row: 0, col: event.shiftKey ? -1 : 1 });
            } else if (event.key === 'Escape') {
                event.preventDefault();
                cancelCellEditor();
            }
        });
        elements.cellEditor.addEventListener('blur', () => {
            if (state.editing) commitCellEditor();
        });

        elements.fontColor.addEventListener('change', () => {
            elements.fontColorSwatch.style.background = elements.fontColor.value;
            applyStyle({ font_color: elements.fontColor.value }, 'Warna teks');
        });
        elements.fillColor.addEventListener('change', () => {
            elements.fillColorSwatch.style.background = elements.fillColor.value;
            applyStyle({ fill_color: elements.fillColor.value }, 'Warna isi');
        });
        elements.fontSize.addEventListener('change', () => {
            if (elements.fontSize.value) {
                applyStyle({ font_size: Number(elements.fontSize.value) }, 'Ukuran font');
            }
        });
        elements.horizontal.addEventListener('change', () => {
            if (elements.horizontal.value) applyStyle({ horizontal: elements.horizontal.value }, 'Perataan');
        });
        elements.vertical.addEventListener('change', () => {
            if (elements.vertical.value) applyStyle({ vertical: elements.vertical.value }, 'Perataan vertikal');
        });
        elements.borderStyle.addEventListener('change', () => {
            if (elements.borderStyle.value) {
                applyStyle({
                    border_style: elements.borderStyle.value,
                    border_color: '#64748b',
                }, 'Garis batas');
            }
        });
        elements.numberFormat.addEventListener('change', () => {
            applyStyle({ number_format: elements.numberFormat.value }, 'Format angka');
        });

        $('#sheetFindNextButton').addEventListener('click', findNext);
        $('#sheetReplaceButton').addEventListener('click', () => replaceCurrent(false));
        $('#sheetReplaceAllButton').addEventListener('click', () => replaceCurrent(true));
        elements.findInput.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                findNext();
            }
        });
        $('#sheetRebaseButton').addEventListener('click', async () => {
            const operations = state.conflictingOperations || pendingOperations();
            hideModal(elements.conflictModal);
            await loadWorkbook({ reapplyOperations: operations });
        });

        window.addEventListener('resize', () => scheduleRender(), { passive: true });
        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(() => scheduleRender()).observe(elements.viewport);
        }
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden' && pendingOperations().length && !state.conflictLocked) {
                void saveWorkbook({quiet: true, source: 'visibility'});
            }
        });
        window.addEventListener('beforeunload', event => {
            if (state.allowUnload || !pendingOperations().length) return;
            event.preventDefault();
            event.returnValue = '';
        });
    }

    bindEvents();
    loadWorkbook();
})();
</script>
@endsection
