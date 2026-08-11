@extends('layouts.admin')

@php
    $preview = is_array($preview ?? null) ? $preview : [];
    $format = strtolower((string) ($preview['format'] ?? ''));
    $fileName = (string) ($preview['name'] ?? data_get($file ?? null, 'original_name', 'Dokumen'));
    $documentTitle = (string) ($preview['title'] ?? pathinfo($fileName, PATHINFO_FILENAME));
    $meta = is_array($preview['meta'] ?? null) ? $preview['meta'] : [];
    $warnings = is_array($preview['warnings'] ?? null) ? $preview['warnings'] : [];
    $folderId = data_get($file ?? null, 'folder_id');
    $fileId = data_get($file ?? null, 'id');
    $resolvedBackUrl = $backUrl
        ?? (\Illuminate\Support\Facades\Route::has('drive.index')
            ? route('drive.index', ['folderId' => $folderId])
            : url()->previous());
    $resolvedDownloadUrl = $downloadUrl
        ?? ($fileId && \Illuminate\Support\Facades\Route::has('drive.file.download')
            ? route('drive.file.download', ['file' => $fileId])
            : null);
    $resolvedOfficeEditorUrl = $fileId
        && $file instanceof \App\Models\DriveAsixFile
        && $file->supportsFullFidelityEditor()
        && (bool) config('services.onlyoffice.enabled', false)
        && \Illuminate\Support\Facades\Route::has('drive.file.office-editor')
            ? route('drive.file.office-editor', ['file' => $fileId])
            : null;
@endphp

@section('title')
    {{ $fileName }} | DriveASIX
@endsection

@section('styles')
<style>
    .drive-document-preview {
        --drive-blue: #0756b7;
        --drive-blue-soft: #eaf3ff;
        --drive-ink: #10243e;
        --drive-muted: #64748b;
        --drive-line: #d9e5f4;
        --drive-paper: #ffffff;
        --document-zoom: 1;
        min-width: 0;
        color: var(--drive-ink);
    }

    .drive-preview-toolbar {
        position: sticky;
        top: 0.75rem;
        z-index: 30;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
        padding: 0.72rem;
        border: 1px solid rgba(7, 86, 183, 0.14);
        border-radius: 1rem;
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 0 16px 34px -26px rgba(16, 36, 62, 0.48);
        backdrop-filter: blur(14px);
    }

    .drive-preview-file {
        display: flex;
        align-items: center;
        min-width: 0;
        gap: 0.72rem;
    }

    .drive-preview-file-icon {
        display: inline-flex;
        flex: 0 0 2.7rem;
        width: 2.7rem;
        height: 2.7rem;
        align-items: center;
        justify-content: center;
        border-radius: 0.78rem;
        color: #fff;
        background: linear-gradient(145deg, #0756b7, #2e86df);
        box-shadow: 0 12px 24px -16px rgba(7, 86, 183, 0.8);
    }

    .drive-preview-file-copy {
        min-width: 0;
    }

    .drive-preview-file-copy h1 {
        overflow: hidden;
        margin: 0;
        color: #0f2745;
        font-size: clamp(0.92rem, 1.6vw, 1.08rem);
        font-weight: 800;
        line-height: 1.25;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .drive-preview-file-copy p {
        margin: 0.18rem 0 0;
        color: var(--drive-muted);
        font-size: 0.72rem;
        font-weight: 650;
    }

    .drive-preview-actions {
        display: flex;
        flex: 0 0 auto;
        align-items: center;
        gap: 0.42rem;
    }

    .drive-preview-action {
        display: inline-flex;
        min-width: 2.45rem;
        min-height: 2.45rem;
        align-items: center;
        justify-content: center;
        gap: 0.38rem;
        border: 1px solid var(--drive-line);
        border-radius: 0.72rem;
        color: #234363;
        background: #fff;
        font-size: 0.78rem;
        font-weight: 750;
        transition: border-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
    }

    .drive-preview-action:hover,
    .drive-preview-action:focus-visible {
        border-color: #84b8ee;
        color: var(--drive-blue);
        text-decoration: none;
        transform: translateY(-1px);
    }

    .drive-preview-action.is-primary {
        border-color: var(--drive-blue);
        color: #fff;
        background: linear-gradient(145deg, #0756b7, #2e86df);
    }

    .drive-preview-zoom-value {
        min-width: 3.1rem;
        color: #58708b;
        font-size: 0.72rem;
        font-variant-numeric: tabular-nums;
        text-align: center;
    }

    .drive-preview-notice {
        display: flex;
        align-items: flex-start;
        gap: 0.68rem;
        margin-bottom: 0.75rem;
        padding: 0.8rem 0.95rem;
        border: 1px solid #f4ce79;
        border-radius: 0.85rem;
        color: #765211;
        background: #fff9e8;
        font-size: 0.8rem;
        line-height: 1.5;
    }

    .drive-preview-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.42rem;
        margin-bottom: 0.85rem;
    }

    .drive-preview-meta span {
        display: inline-flex;
        align-items: center;
        min-height: 1.85rem;
        padding: 0.3rem 0.62rem;
        border: 1px solid #d8e6f6;
        border-radius: 999px;
        color: #486582;
        background: rgba(255, 255, 255, 0.78);
        font-size: 0.68rem;
        font-weight: 750;
    }

    .drive-preview-stage {
        min-width: 0;
        overflow: auto;
        padding: clamp(0.7rem, 2.2vw, 1.5rem);
        border: 1px solid #cfdded;
        border-radius: 1rem;
        background:
            linear-gradient(rgba(110, 139, 172, 0.08) 1px, transparent 1px),
            linear-gradient(90deg, rgba(110, 139, 172, 0.08) 1px, transparent 1px),
            #e8eef5;
        background-size: 22px 22px;
        overscroll-behavior: contain;
    }

    .drive-document-paper {
        zoom: var(--document-zoom);
        width: 816px;
        min-height: 1056px;
        margin: 0 auto;
        padding: 72px 78px;
        border: 1px solid #d7e0ea;
        border-radius: 0.2rem;
        background: var(--drive-paper);
        box-shadow: 0 22px 48px -30px rgba(15, 39, 69, 0.52);
        color: #1d2939;
        font-family: Aptos, Calibri, "Segoe UI", sans-serif;
        font-size: 14px;
        line-height: 1.65;
    }

    .drive-document-paper .doc-title {
        margin: 0 0 1.1rem;
        font-size: 2rem;
        font-weight: 750;
        line-height: 1.2;
    }

    .drive-document-paper .doc-subtitle {
        margin: -0.5rem 0 1.3rem;
        color: #667085;
        font-size: 1.15rem;
    }

    .drive-document-paper .doc-heading-1,
    .drive-document-paper .doc-heading-2,
    .drive-document-paper .doc-heading-3,
    .drive-document-paper .doc-heading-4,
    .drive-document-paper .doc-heading-5,
    .drive-document-paper .doc-heading-6 {
        margin: 1.3em 0 0.5em;
        color: #173d68;
        font-weight: 750;
        line-height: 1.25;
    }

    .drive-document-paper .doc-heading-1 { font-size: 1.62rem; }
    .drive-document-paper .doc-heading-2 { font-size: 1.38rem; }
    .drive-document-paper .doc-heading-3 { font-size: 1.18rem; }
    .drive-document-paper .doc-heading-4,
    .drive-document-paper .doc-heading-5,
    .drive-document-paper .doc-heading-6 { font-size: 1rem; }

    .drive-document-paper .doc-paragraph {
        margin: 0 0 0.72rem;
        white-space: pre-wrap;
    }

    .drive-document-paper .doc-list {
        position: relative;
        margin: 0 0 0.48rem;
        padding-left: 1.25rem;
        white-space: pre-wrap;
    }

    .drive-document-paper .doc-list::before {
        position: absolute;
        top: 0;
        left: 0.25rem;
        content: "•";
        color: #315b88;
        font-weight: 900;
    }

    .drive-preview-table-wrap {
        max-width: 100%;
        margin: 1rem 0;
        overflow-x: auto;
        border: 1px solid #cfd9e6;
        border-radius: 0.42rem;
    }

    .drive-preview-table {
        width: 100%;
        min-width: max-content;
        margin: 0 !important;
        border-collapse: collapse;
        color: #243b53;
        font-size: 0.84em;
    }

    .drive-preview-table td {
        position: static !important;
        min-width: 7rem;
        max-width: 22rem;
        padding: 0.48rem 0.58rem !important;
        border: 1px solid #d7e0ea;
        background: #fff !important;
        line-height: 1.4;
        overflow-wrap: anywhere;
        white-space: pre-wrap !important;
    }

    .drive-preview-table tr:first-child td {
        color: #173d68;
        background: #edf5ff !important;
        font-weight: 750;
    }

    .drive-presentation-layout {
        display: grid;
        grid-template-columns: minmax(9.5rem, 13rem) minmax(0, 1fr);
        gap: 0.85rem;
        min-width: 0;
    }

    .drive-slide-index {
        position: sticky;
        top: 6rem;
        align-self: start;
        max-height: calc(100vh - 8rem);
        overflow: auto;
        padding: 0.55rem;
        border: 1px solid #d5e2f0;
        border-radius: 0.9rem;
        background: rgba(255, 255, 255, 0.88);
    }

    .drive-slide-index a {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        margin-bottom: 0.35rem;
        padding: 0.52rem 0.58rem;
        border: 1px solid transparent;
        border-radius: 0.62rem;
        color: #516981;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .drive-slide-index a:hover,
    .drive-slide-index a.is-active {
        border-color: #b8d7f7;
        color: var(--drive-blue);
        background: #edf6ff;
        text-decoration: none;
    }

    .drive-slide-index strong {
        display: inline-flex;
        flex: 0 0 1.65rem;
        height: 1.45rem;
        align-items: center;
        justify-content: center;
        border-radius: 0.42rem;
        color: #fff;
        background: #377ec7;
        font-size: 0.62rem;
    }

    .drive-slide-index span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .drive-presentation-stage {
        display: grid;
        min-width: 0;
        gap: 1.2rem;
    }

    .drive-slide-shell {
        min-width: 0;
        scroll-margin-top: 6.2rem;
    }

    .drive-slide-label {
        margin: 0 0 0.4rem;
        color: #56708d;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .drive-slide-canvas {
        zoom: var(--document-zoom);
        display: flex;
        width: 960px;
        min-height: 540px;
        flex-direction: column;
        justify-content: center;
        margin: 0 auto;
        padding: 54px 64px;
        overflow: hidden;
        border: 1px solid #cad8e7;
        border-radius: 0.25rem;
        color: #172b44;
        background:
            radial-gradient(circle at 92% 8%, rgba(72, 149, 225, 0.14), transparent 26%),
            linear-gradient(155deg, #fff 0%, #f8fbff 100%);
        box-shadow: 0 22px 48px -30px rgba(15, 39, 69, 0.55);
        font-family: Aptos, Calibri, "Segoe UI", sans-serif;
    }

    .drive-slide-canvas h2 {
        margin: 0 0 1.15rem;
        color: #0f4d91;
        font-size: 2rem;
        font-weight: 800;
        line-height: 1.18;
    }

    .drive-slide-text {
        margin-bottom: 0.85rem;
        font-size: 1.05rem;
        line-height: 1.55;
        white-space: pre-wrap;
    }

    .drive-slide-text.is-subtitle {
        color: #55708c;
        font-size: 1.2rem;
    }

    .drive-slide-text.is-footer {
        margin-top: auto;
        color: #718198;
        font-size: 0.72rem;
    }

    .drive-preview-empty {
        display: grid;
        min-height: 20rem;
        place-items: center;
        padding: 2rem;
        border: 1px dashed #adc6df;
        border-radius: 0.9rem;
        color: #607891;
        background: rgba(255, 255, 255, 0.8);
        text-align: center;
    }

    @media (max-width: 991.98px) {
        .drive-presentation-layout {
            grid-template-columns: minmax(0, 1fr);
        }

        .drive-slide-index {
            position: static;
            display: flex;
            max-height: none;
            overflow-x: auto;
        }

        .drive-slide-index a {
            flex: 0 0 9rem;
            margin: 0 0.35rem 0 0;
        }
    }

    @media (max-width: 767.98px) {
        .drive-preview-toolbar {
            position: static;
            align-items: stretch;
            flex-direction: column;
        }

        .drive-preview-actions {
            width: 100%;
            overflow-x: auto;
            padding-bottom: 0.12rem;
        }

        .drive-preview-action {
            flex: 0 0 auto;
        }

        .drive-preview-file-copy h1 {
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .drive-preview-stage {
            padding: 0.55rem;
        }
    }

    @media print {
        .main-header,
        .main-sidebar,
        .drive-preview-toolbar,
        .drive-preview-meta,
        .drive-preview-notice,
        .drive-slide-index,
        .drive-slide-label {
            display: none !important;
        }

        .content-wrapper,
        .drive-preview-stage {
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            background: #fff !important;
        }

        .drive-document-paper,
        .drive-slide-canvas {
            zoom: 1 !important;
            break-after: page;
            border: 0;
            box-shadow: none;
        }
    }
</style>
@endsection

@section('content')
<div class="container-fluid drive-document-preview" id="driveDocumentPreview">
    <div class="drive-preview-toolbar" aria-label="Peralatan preview dokumen">
        <div class="drive-preview-file">
            <span class="drive-preview-file-icon" aria-hidden="true">
                <i class="fas {{ $format === 'pptx' ? 'fa-file-powerpoint' : 'fa-file-word' }}"></i>
            </span>
            <div class="drive-preview-file-copy">
                <h1 title="{{ $fileName }}">{{ $fileName }}</h1>
                <p>{{ strtoupper($format ?: 'FILE') }} · Preview lokal DriveASIX</p>
            </div>
        </div>

        <div class="drive-preview-actions">
            <a class="drive-preview-action" href="{{ $resolvedBackUrl }}" title="Kembali ke DriveASIX">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span>Kembali</span>
            </a>
            <button class="drive-preview-action" type="button" id="driveZoomOut" title="Perkecil tampilan">
                <i class="fas fa-search-minus" aria-hidden="true"></i>
                <span class="sr-only">Perkecil</span>
            </button>
            <span class="drive-preview-zoom-value" id="driveZoomValue" aria-live="polite">100%</span>
            <button class="drive-preview-action" type="button" id="driveZoomIn" title="Perbesar tampilan">
                <i class="fas fa-search-plus" aria-hidden="true"></i>
                <span class="sr-only">Perbesar</span>
            </button>
            <button class="drive-preview-action" type="button" id="driveZoomFit" title="Sesuaikan dengan lebar layar">
                <i class="fas fa-expand-arrows-alt" aria-hidden="true"></i>
                <span class="sr-only">Sesuaikan ukuran</span>
            </button>
            <button class="drive-preview-action" type="button" onclick="window.print()" title="Cetak dokumen">
                <i class="fas fa-print" aria-hidden="true"></i>
                <span class="sr-only">Cetak</span>
            </button>
            @if($resolvedOfficeEditorUrl)
                <a class="drive-preview-action is-primary" href="{{ $resolvedOfficeEditorUrl }}">
                    <i class="fas fa-edit" aria-hidden="true"></i>
                    <span>Edit penuh</span>
                </a>
            @endif
            @if($resolvedDownloadUrl)
                <a class="drive-preview-action" href="{{ $resolvedDownloadUrl }}" download>
                    <i class="fas fa-download" aria-hidden="true"></i>
                    <span>Unduh</span>
                </a>
            @endif
        </div>
    </div>

    @foreach($warnings as $warning)
        <div class="drive-preview-notice" role="status">
            <i class="fas fa-exclamation-triangle mt-1" aria-hidden="true"></i>
            <span>{{ $warning }}</span>
        </div>
    @endforeach

    <div class="drive-preview-meta" aria-label="Ringkasan dokumen">
        @if($format === 'docx')
            <span>{{ number_format((int) ($meta['paragraph_count'] ?? 0), 0, ',', '.') }} paragraf</span>
            <span>{{ number_format((int) ($meta['table_count'] ?? 0), 0, ',', '.') }} tabel</span>
        @elseif($format === 'pptx')
            <span>{{ number_format((int) ($meta['slide_count'] ?? 0), 0, ',', '.') }} slide</span>
            <span>{{ number_format((int) ($meta['table_count'] ?? 0), 0, ',', '.') }} tabel</span>
        @endif
        <span>{{ number_format((int) ($meta['word_count'] ?? 0), 0, ',', '.') }} kata</span>
        <span>Diproses di server lokal</span>
    </div>

    @if($format === 'docx')
        <div class="drive-preview-stage" id="drivePreviewStage">
            <article class="drive-document-paper" aria-label="Isi {{ $documentTitle }}">
                @forelse(($preview['blocks'] ?? []) as $block)
                    @if(($block['type'] ?? null) === 'paragraph')
                        @php
                            $style = in_array(($block['style'] ?? ''), ['title', 'subtitle', 'heading-1', 'heading-2', 'heading-3', 'heading-4', 'heading-5', 'heading-6', 'list', 'paragraph'], true)
                                ? $block['style']
                                : 'paragraph';
                        @endphp
                        @if($style === 'title')
                            <h1 class="doc-title">{!! nl2br(e((string) ($block['text'] ?? ''))) !!}</h1>
                        @elseif($style === 'subtitle')
                            <p class="doc-subtitle">{!! nl2br(e((string) ($block['text'] ?? ''))) !!}</p>
                        @elseif(str_starts_with($style, 'heading-'))
                            @php($headingLevel = max(1, min(6, (int) str_replace('heading-', '', $style))))
                            <h{{ $headingLevel }} class="doc-{{ $style }}">{!! nl2br(e((string) ($block['text'] ?? ''))) !!}</h{{ $headingLevel }}>
                        @else
                            <p class="doc-{{ $style }}">{!! nl2br(e((string) ($block['text'] ?? ''))) !!}</p>
                        @endif
                    @elseif(($block['type'] ?? null) === 'table')
                        <div class="drive-preview-table-wrap">
                            <table class="drive-preview-table">
                                <tbody>
                                    @foreach(($block['rows'] ?? []) as $row)
                                        <tr>
                                            @foreach($row as $cell)
                                                <td>{!! nl2br(e((string) $cell)) !!}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @empty
                    <div class="drive-preview-empty">
                        <div>
                            <i class="far fa-file-alt fa-2x mb-3" aria-hidden="true"></i>
                            <p class="mb-0">Dokumen tidak memiliki teks yang dapat ditampilkan.</p>
                        </div>
                    </div>
                @endforelse
            </article>
        </div>
    @elseif($format === 'pptx')
        <div class="drive-presentation-layout">
            <nav class="drive-slide-index" aria-label="Daftar slide">
                @foreach(($preview['slides'] ?? []) as $slide)
                    <a href="#drive-slide-{{ (int) ($slide['number'] ?? $loop->iteration) }}" class="{{ $loop->first ? 'is-active' : '' }}">
                        <strong>{{ (int) ($slide['number'] ?? $loop->iteration) }}</strong>
                        <span>{{ $slide['title'] ?? 'Slide '.$loop->iteration }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="drive-preview-stage" id="drivePreviewStage">
                <div class="drive-presentation-stage">
                    @forelse(($preview['slides'] ?? []) as $slide)
                        @php($slideNumber = (int) ($slide['number'] ?? $loop->iteration))
                        <section class="drive-slide-shell" id="drive-slide-{{ $slideNumber }}" data-drive-slide="{{ $slideNumber }}">
                            <p class="drive-slide-label">Slide {{ $slideNumber }}</p>
                            <div class="drive-slide-canvas">
                                <h2>{{ $slide['title'] ?? 'Slide '.$slideNumber }}</h2>
                                @foreach(($slide['texts'] ?? []) as $text)
                                    @if(($text['role'] ?? null) !== 'title')
                                        <div class="drive-slide-text is-{{ in_array(($text['role'] ?? ''), ['subtitle', 'footer'], true) ? $text['role'] : 'body' }}">
                                            {!! nl2br(e((string) ($text['text'] ?? ''))) !!}
                                        </div>
                                    @endif
                                @endforeach

                                @foreach(($slide['tables'] ?? []) as $table)
                                    <div class="drive-preview-table-wrap">
                                        <table class="drive-preview-table">
                                            <tbody>
                                                @foreach(($table['rows'] ?? []) as $row)
                                                    <tr>
                                                        @foreach($row as $cell)
                                                            <td>{!! nl2br(e((string) $cell)) !!}</td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @empty
                        <div class="drive-preview-empty">
                            <div>
                                <i class="far fa-file-powerpoint fa-2x mb-3" aria-hidden="true"></i>
                                <p class="mb-0">Presentasi tidak memiliki slide yang dapat ditampilkan.</p>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @else
        <div class="drive-preview-empty">
            <div>
                <i class="far fa-file fa-2x mb-3" aria-hidden="true"></i>
                <p class="mb-1 font-weight-bold">Preview dokumen belum tersedia.</p>
                <p class="mb-0">Format lokal yang didukung pada halaman ini adalah DOCX dan PPTX.</p>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const root = document.getElementById('driveDocumentPreview');
        const stage = document.getElementById('drivePreviewStage');
        const zoomValue = document.getElementById('driveZoomValue');
        const zoomIn = document.getElementById('driveZoomIn');
        const zoomOut = document.getElementById('driveZoomOut');
        const zoomFit = document.getElementById('driveZoomFit');

        if (!root || !stage || !zoomValue) {
            return;
        }

        const format = @json($format);
        const baseWidth = format === 'pptx' ? 960 : 816;
        let zoom = 1;

        function applyZoom(nextZoom) {
            zoom = Math.max(0.35, Math.min(1.75, Math.round(nextZoom * 20) / 20));
            root.style.setProperty('--document-zoom', String(zoom));
            zoomValue.textContent = Math.round(zoom * 100) + '%';
        }

        function fitToWidth() {
            const available = Math.max(280, stage.clientWidth - 28);
            applyZoom(Math.min(1, available / baseWidth));
        }

        zoomIn?.addEventListener('click', function () {
            applyZoom(zoom + 0.1);
        });
        zoomOut?.addEventListener('click', function () {
            applyZoom(zoom - 0.1);
        });
        zoomFit?.addEventListener('click', fitToWidth);

        if (stage.clientWidth < baseWidth + 28) {
            fitToWidth();
        }

        const indexLinks = Array.from(document.querySelectorAll('.drive-slide-index a[href^="#drive-slide-"]'));
        const slides = Array.from(document.querySelectorAll('[data-drive-slide]'));

        if ('IntersectionObserver' in window && indexLinks.length && slides.length) {
            const observer = new IntersectionObserver(function (entries) {
                const visible = entries
                    .filter(function (entry) { return entry.isIntersecting; })
                    .sort(function (left, right) { return right.intersectionRatio - left.intersectionRatio; })[0];

                if (!visible) {
                    return;
                }

                indexLinks.forEach(function (link) {
                    link.classList.toggle('is-active', link.hash === '#' + visible.target.id);
                });
            }, {
                root: stage,
                threshold: [0.25, 0.55]
            });

            slides.forEach(function (slide) {
                observer.observe(slide);
            });
        }
    });
</script>
@endpush
