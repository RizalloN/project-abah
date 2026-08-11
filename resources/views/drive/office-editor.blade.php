@extends('layouts.admin')

@section('title', 'Editor Office - DriveASIX')

@section('styles')
<style>
    body.drive-office-active {
        overflow: hidden;
    }

    body.drive-office-active .content-wrapper {
        overflow: hidden !important;
    }

    body.drive-office-active .content-wrapper > .content {
        padding: 0 !important;
    }

    body.drive-office-active .content-wrapper > .content > .container-fluid {
        max-width: none !important;
        padding: 0 !important;
    }

    .asix-office-shell {
        --office-navy: #07345e;
        --office-blue: #0b78c5;
        --office-amber: #f2ae34;
        --office-line: #d8e4ef;
        display: flex;
        min-height: 560px;
        height: calc(100dvh - 68px);
        flex-direction: column;
        overflow: hidden;
        background: #edf4fa;
    }

    .asix-office-toolbar {
        position: relative;
        z-index: 3;
        display: flex;
        min-height: 58px;
        align-items: center;
        gap: 12px;
        padding: 8px 14px;
        color: #fff;
        border-bottom: 3px solid var(--office-amber);
        background: linear-gradient(105deg, #052d52, #086698);
        box-shadow: 0 3px 14px rgba(5, 45, 82, .2);
    }

    .asix-office-back,
    .asix-office-action {
        display: inline-flex;
        min-width: 42px;
        min-height: 42px;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 0 12px;
        color: #fff;
        border: 1px solid rgba(255, 255, 255, .22);
        border-radius: 11px;
        background: rgba(255, 255, 255, .1);
        text-decoration: none;
        transition: background .18s ease, transform .18s ease;
    }

    .asix-office-back:hover,
    .asix-office-action:hover {
        color: #fff;
        background: rgba(255, 255, 255, .2);
        transform: translateY(-1px);
    }

    .asix-office-file {
        min-width: 0;
        flex: 1;
    }

    .asix-office-file h1 {
        overflow: hidden;
        margin: 0;
        color: #fff;
        font-size: 15px;
        font-weight: 800;
        line-height: 1.25;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .asix-office-status {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 3px;
        color: #d9efff;
        font-size: 11px;
        font-weight: 650;
    }

    .asix-office-status-dot {
        width: 8px;
        height: 8px;
        flex: 0 0 auto;
        border-radius: 50%;
        background: #f6c44c;
        box-shadow: 0 0 0 3px rgba(246, 196, 76, .16);
    }

    .asix-office-status.is-ready .asix-office-status-dot {
        background: #55e0a3;
        box-shadow: 0 0 0 3px rgba(85, 224, 163, .16);
    }

    .asix-office-status.is-error .asix-office-status-dot {
        background: #ff7b86;
        box-shadow: 0 0 0 3px rgba(255, 123, 134, .16);
    }

    .asix-office-canvas {
        position: relative;
        min-height: 0;
        flex: 1;
        overflow: hidden;
        background: #fff;
    }

    #asixOfficeEditor {
        width: 100%;
        height: 100%;
        min-height: 500px;
    }

    .asix-office-fallback {
        display: grid;
        height: 100%;
        min-height: 500px;
        place-items: center;
        padding: 24px;
        background:
            radial-gradient(circle at 15% 0%, rgba(11, 120, 197, .12), transparent 32%),
            linear-gradient(160deg, #f7fbff, #eaf2f9);
    }

    .asix-office-fallback[hidden] {
        display: none !important;
    }

    .asix-office-fallback-card {
        width: min(620px, 100%);
        padding: clamp(22px, 4vw, 38px);
        border: 1px solid var(--office-line);
        border-radius: 22px;
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 20px 50px rgba(17, 52, 82, .13);
        text-align: center;
    }

    .asix-office-fallback-icon {
        display: inline-grid;
        width: 66px;
        height: 66px;
        margin-bottom: 16px;
        place-items: center;
        color: #fff;
        border-radius: 18px;
        background: linear-gradient(145deg, var(--office-blue), #18a5c4);
        box-shadow: 0 12px 26px rgba(11, 120, 197, .24);
        font-size: 26px;
    }

    .asix-office-fallback-card h2 {
        margin: 0 0 8px;
        color: #15324e;
        font-size: clamp(20px, 3vw, 28px);
        font-weight: 850;
    }

    .asix-office-fallback-card p {
        margin: 0 auto;
        color: #60758b;
        font-size: 14px;
        line-height: 1.65;
    }

    .asix-office-fallback-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px;
        margin-top: 22px;
    }

    .asix-office-fallback-button {
        display: inline-flex;
        min-height: 44px;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 16px;
        color: #fff;
        border: 0;
        border-radius: 11px;
        background: var(--office-blue);
        font-size: 13px;
        font-weight: 750;
        text-decoration: none;
    }

    .asix-office-fallback-button:hover {
        color: #fff;
        background: #0767a9;
    }

    .asix-office-fallback-button.is-secondary {
        color: #24445f;
        border: 1px solid #cbd9e5;
        background: #fff;
    }

    @media (max-width: 640px) {
        .asix-office-shell {
            height: calc(100dvh - 58px);
        }

        .asix-office-toolbar {
            min-height: 56px;
            padding: 7px 9px;
        }

        .asix-office-action span {
            display: none;
        }

        .asix-office-file h1 {
            font-size: 13px;
        }
    }
</style>
@endsection

@section('content')
<div class="asix-office-shell" id="asixOfficeShell">
    <header class="asix-office-toolbar">
        <a class="asix-office-back" href="{{ $backUrl }}" aria-label="Kembali ke DriveASIX" title="Kembali">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
        </a>
        <div class="asix-office-file">
            <h1 title="{{ $file->original_name }}">{{ $file->original_name }}</h1>
            <div class="asix-office-status {{ $available ? '' : 'is-error' }}" id="asixOfficeStatus">
                <span class="asix-office-status-dot" aria-hidden="true"></span>
                <span id="asixOfficeStatusText">
                    {{ $available ? 'Menyiapkan editor Office lokal...' : $unavailableReason }}
                </span>
            </div>
        </div>
        <a class="asix-office-action" href="{{ route('drive.file.download', $file) }}" title="Unduh file">
            <i class="fas fa-download" aria-hidden="true"></i>
            <span>Unduh</span>
        </a>
    </header>

    <main class="asix-office-canvas">
        @if($available)
            <div id="asixOfficeEditor" aria-label="Editor penuh {{ strtoupper($file->extension()) }}"></div>
            <div class="asix-office-fallback" id="asixOfficeRuntimeFallback" hidden>
                <section class="asix-office-fallback-card" role="alert">
                    <span class="asix-office-fallback-icon" aria-hidden="true">
                        <i class="fas fa-unlink"></i>
                    </span>
                    <h2>Editor gagal dimuat</h2>
                    <p id="asixOfficeRuntimeMessage">
                        Koneksi ke Document Server terputus. File asli tidak berubah.
                    </p>
                    <div class="asix-office-fallback-actions">
                        @if($fallbackUrl)
                            <a class="asix-office-fallback-button" href="{{ $fallbackUrl }}">
                                Buka mode kompatibel
                            </a>
                        @endif
                        <button class="asix-office-fallback-button is-secondary" type="button" onclick="window.location.reload()">
                            Coba lagi
                        </button>
                        <a class="asix-office-fallback-button is-secondary" href="{{ $backUrl }}">
                            Kembali ke DriveASIX
                        </a>
                    </div>
                </section>
            </div>
        @else
            <div class="asix-office-fallback">
                <section class="asix-office-fallback-card" role="status">
                    <span class="asix-office-fallback-icon" aria-hidden="true">
                        <i class="fas fa-file-signature"></i>
                    </span>
                    <h2>Editor Office belum tersedia</h2>
                    <p>{{ $unavailableReason }}</p>
                    <div class="asix-office-fallback-actions">
                        @if($fallbackUrl)
                            <a class="asix-office-fallback-button" href="{{ $fallbackUrl }}">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                                Buka mode kompatibel
                            </a>
                        @endif
                        <button class="asix-office-fallback-button is-secondary" type="button" onclick="window.location.reload()">
                            <i class="fas fa-sync-alt" aria-hidden="true"></i>
                            Coba lagi
                        </button>
                        <a class="asix-office-fallback-button is-secondary" href="{{ $backUrl }}">
                            Kembali ke DriveASIX
                        </a>
                    </div>
                </section>
            </div>
        @endif
    </main>
</div>
@endsection

@section('scripts')
<script>
document.body.classList.add('drive-office-active');
</script>
@if($available)
<script src="{{ $editorScriptUrl }}" id="asixOnlyOfficeApi"></script>
<script>
(() => {
    'use strict';

    const config = {{ Illuminate\Support\Js::from($editorConfig) }};
    const backUrl = {{ Illuminate\Support\Js::from($backUrl) }};
    const status = document.getElementById('asixOfficeStatus');
    const statusText = document.getElementById('asixOfficeStatusText');
    const editorCanvas = document.getElementById('asixOfficeEditor');
    const runtimeFallback = document.getElementById('asixOfficeRuntimeFallback');
    const runtimeMessage = document.getElementById('asixOfficeRuntimeMessage');
    let editor = null;

    function setStatus(message, state = '') {
        status.classList.toggle('is-ready', state === 'ready');
        status.classList.toggle('is-error', state === 'error');
        statusText.textContent = message;
    }

    function showRuntimeFallback(message) {
        setStatus(message, 'error');
        if (editorCanvas) editorCanvas.hidden = true;
        if (runtimeMessage) runtimeMessage.textContent = message + ' File asli tetap aman.';
        if (runtimeFallback) runtimeFallback.hidden = false;
    }

    config.events = {
        onAppReady() {
            setStatus('Editor Office siap digunakan', 'ready');
        },
        onDocumentReady() {
            setStatus('Dokumen termuat · penyimpanan otomatis aktif', 'ready');
        },
        onError(event) {
            const code = event?.data?.errorCode ?? event?.data ?? 'tidak diketahui';
            setStatus(`Editor melaporkan kesalahan (${code})`, 'error');
        },
        onWarning(event) {
            const code = event?.data?.warningCode ?? event?.data ?? 'peringatan';
            setStatus(`Peringatan editor (${code})`, 'error');
        },
        onRequestClose() {
            window.location.assign(backUrl);
        },
        onRequestRefreshFile() {
            window.location.reload();
        },
        onOutdatedVersion() {
            window.location.reload();
        },
    };

    try {
        if (!window.DocsAPI?.DocEditor) {
            throw new Error('API editor tidak termuat.');
        }
        editor = new window.DocsAPI.DocEditor('asixOfficeEditor', config);
    } catch (error) {
        console.error(error);
        showRuntimeFallback('Document Server tidak dapat dimuat. Periksa koneksi editor lokal.');
    }

    window.addEventListener('beforeunload', () => {
        try {
            editor?.destroyEditor?.();
        } catch (error) {
            console.debug('Editor cleanup dilewati.', error);
        }
    });
})();
</script>
@endif
@endsection
