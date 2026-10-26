{{--
    Panel kontrol Kelola Job: ikhtisar kesehatan, worker & kapasitas, snapshot,
    data & shadow column, dan database (gerbang phpMyAdmin). Tab "Operasional"
    membungkus konten lama halaman ini tanpa mengubahnya.
--}}
<nav class="cc-tabs" role="tablist" aria-label="Panel kontrol Kelola Job" id="ccTabs">
    @foreach([
        'overview' => ['Ikhtisar', 'fa-heartbeat'],
        'operations' => ['Operasional', 'fa-tasks'],
        'workers' => ['Worker & Kapasitas', 'fa-microchip'],
        'snapshot' => ['Snapshot', 'fa-camera-retro'],
        'data' => ['Data & Shadow', 'fa-database'],
        'database' => ['Database', 'fa-server'],
    ] as $key => [$label, $icon])
        <button type="button" class="cc-tab" role="tab" id="cc-tab-{{ $key }}" data-cc-tab="{{ $key }}"
            aria-controls="cc-panel-{{ $key }}" aria-selected="false" tabindex="-1">
            <i class="fas {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
            @if($key === 'overview')<span class="cc-tab__dot" data-cc-overall-dot hidden></span>@endif
        </button>
    @endforeach
</nav>

<div class="cc-root" id="controlCenter"
    data-health-url="{{ route('job-management.control.health') }}"
    data-freshness-url="{{ route('job-management.control.freshness') }}"
    data-workers-url="{{ route('job-management.control.workers') }}"
    data-snapshots-url="{{ route('job-management.control.snapshots') }}"
    data-shadow-url="{{ route('job-management.control.shadow') }}"
    data-shadow-repair-url="{{ route('job-management.control.shadow.repair') }}"
    data-database-url="{{ route('job-management.control.database') }}"
    data-unlock-url="{{ route('job-management.control.database.unlock') }}"
    data-lock-url="{{ route('job-management.control.database.lock') }}"
    data-pma-url="{{ url(\App\Services\ControlCenter\PhpMyAdminGateway::ROUTE_PREFIX) }}"
    data-rebuild-url="{{ route('import.report-management.rebuild') }}"
    data-rebuild-status-url="{{ route('import.report-management.rebuild.status', ['rebuildId' => '__ID__']) }}"
    data-force-sync-url="{{ route('import.report-management.force-sync') }}"
    data-force-sync-status-url="{{ route('import.report-management.force-sync.status', ['syncId' => '__ID__']) }}"
    data-csrf="{{ csrf_token() }}">

    {{-- Ikhtisar --}}
    <section class="cc-panel" id="cc-panel-overview" role="tabpanel" aria-labelledby="cc-tab-overview" data-cc-panel="overview" hidden>
        <div class="cc-hero">
            <div class="cc-score" data-cc-score-wrap aria-live="polite">
                <svg viewBox="0 0 120 120" class="cc-score__ring" aria-hidden="true">
                    <circle cx="60" cy="60" r="52" class="cc-score__track"></circle>
                    <circle cx="60" cy="60" r="52" class="cc-score__value" data-cc-score-ring></circle>
                </svg>
                <div class="cc-score__text"><strong data-cc-score>–</strong><span>skor kesehatan</span></div>
            </div>
            <div class="cc-hero__body">
                <span class="cc-pill" data-cc-overall>Memeriksa…</span>
                <h3 class="cc-hero__title" data-cc-headline>Mengumpulkan status layanan, antrean, dan data…</h3>
                <div class="cc-chips" data-cc-counts></div>
                <div class="cc-hero__meta">
                    <span data-cc-generated></span>
                    <button type="button" class="cc-btn cc-btn--ghost" data-cc-refresh="overview"><i class="fas fa-redo-alt" aria-hidden="true"></i> Periksa ulang</button>
                </div>
            </div>
        </div>
        <div class="cc-groups" data-cc-groups></div>
        <ul class="cc-checks" data-cc-checks aria-label="Daftar pemeriksaan"></ul>
    </section>

    {{-- Worker & Kapasitas --}}
    <section class="cc-panel" id="cc-panel-workers" role="tabpanel" aria-labelledby="cc-tab-workers" data-cc-panel="workers" hidden>
        <div class="cc-card">
            <div class="cc-card__head">
                <h3 class="cc-card__title"><i class="fas fa-balance-scale" aria-hidden="true"></i> Anggaran worker bersama</h3>
                <button type="button" class="cc-btn cc-btn--ghost" data-cc-refresh="workers"><i class="fas fa-redo-alt" aria-hidden="true"></i> Muat ulang</button>
            </div>
            <p class="cc-note">Worker baru diluncurkan menurut prioritas antrean di dalam satu anggaran bersama. Anggaran mengecil otomatis bila RAM bebas tidak cukup menampung worker tambahan pada batas memorinya; worker yang sedang berjalan tidak pernah dihentikan paksa. Pool elastis mengembalikan memorinya begitu antreannya kosong.</p>
            <dl class="cc-stats" data-cc-plan-stats></dl>
        </div>
        <div class="cc-card">
            <h3 class="cc-card__title"><i class="fas fa-layer-group" aria-hidden="true"></i> Pembagian worker per pool</h3>
            <div class="cc-table-wrap"><table class="cc-table" data-abah-no-table-guard="1">
                <thead><tr><th scope="col">Pool</th><th scope="col">Prioritas</th><th scope="col">Antrean</th><th scope="col">Siap / Jalan</th><th scope="col">Worker hidup</th><th scope="col">Target / Kapasitas</th><th scope="col">Status</th></tr></thead>
                <tbody data-cc-pools></tbody>
            </table></div>
        </div>
        <div class="cc-card">
            <h3 class="cc-card__title"><i class="fas fa-bug" aria-hidden="true"></i> Job gagal 7 hari (dikelompokkan)</h3>
            <div class="cc-table-wrap"><table class="cc-table" data-abah-no-table-guard="1">
                <thead><tr><th scope="col">Job</th><th scope="col">Antrean</th><th scope="col">Jumlah</th><th scope="col">Terakhir</th><th scope="col">Pesan</th></tr></thead>
                <tbody data-cc-failed></tbody>
            </table></div>
        </div>
    </section>

    {{-- Snapshot --}}
    <section class="cc-panel" id="cc-panel-snapshot" role="tabpanel" aria-labelledby="cc-tab-snapshot" data-cc-panel="snapshot" hidden>
        <div class="cc-grid-2">
            <div class="cc-card">
                <h3 class="cc-card__title"><i class="fas fa-calendar-check" aria-hidden="true"></i> Sinkronisasi periode</h3>
                <p class="cc-note">Bangun ulang semua snapshot report yang memiliki data pada tanggal terpilih. Ditolak otomatis bila masih ada import berjalan.</p>
                <form class="cc-form" data-cc-force-sync-form>
                    <label class="cc-label" for="ccForceSyncDate">Tanggal posisi</label>
                    <div class="cc-inline">
                        <input type="date" id="ccForceSyncDate" class="cc-input" required>
                        <button type="submit" class="cc-btn cc-btn--warning"><i class="fas fa-bolt" aria-hidden="true"></i> Sinkronkan</button>
                    </div>
                </form>
            </div>
            <div class="cc-card">
                <h3 class="cc-card__title"><i class="fas fa-sync-alt" aria-hidden="true"></i> Rebuild snapshot</h3>
                <p class="cc-note">Refresh seluruh snapshot report. Mode penuh membangun ulang dari awal dan memakan waktu lebih lama.</p>
                <div class="cc-inline">
                    <label class="cc-switch"><input type="checkbox" data-cc-rebuild-full> <span>Mode penuh</span></label>
                    <button type="button" class="cc-btn cc-btn--primary" data-cc-rebuild><i class="fas fa-sync-alt" aria-hidden="true"></i> Jalankan rebuild</button>
                </div>
            </div>
        </div>
        <div class="cc-progress" data-cc-snapshot-progress hidden role="status" aria-live="polite">
            <div class="cc-progress__top"><strong data-cc-progress-title>Memproses…</strong><span data-cc-progress-percent>0%</span></div>
            <div class="cc-progress__bar"><span data-cc-progress-bar></span></div>
            <p class="cc-note" data-cc-progress-text></p>
        </div>
        <div class="cc-card">
            <div class="cc-card__head">
                <h3 class="cc-card__title"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Kesegaran snapshot per sumber</h3>
                <button type="button" class="cc-btn cc-btn--ghost" data-cc-refresh="snapshot"><i class="fas fa-redo-alt" aria-hidden="true"></i> Muat ulang</button>
            </div>
            <div class="cc-table-wrap"><table class="cc-table" data-abah-no-table-guard="1">
                <thead><tr><th scope="col">Sumber</th><th scope="col">Data terbaru</th><th scope="col">Snapshot terbaru</th><th scope="col">Dibangun</th><th scope="col">Tabel snapshot</th><th scope="col">Status</th></tr></thead>
                <tbody data-cc-snapshot-sources></tbody>
            </table></div>
        </div>
        <div class="cc-card">
            <h3 class="cc-card__title"><i class="fas fa-stream" aria-hidden="true"></i> Antrean refresh otomatis</h3>
            <div class="cc-table-wrap"><table class="cc-table" data-abah-no-table-guard="1">
                <thead><tr><th scope="col">Sumber</th><th scope="col">Periode</th><th scope="col">Shard</th><th scope="col">Berubah sejak</th><th scope="col">Percobaan</th><th scope="col">Status</th></tr></thead>
                <tbody data-cc-dirty></tbody>
            </table></div>
        </div>
    </section>

    {{-- Data & Shadow --}}
    <section class="cc-panel" id="cc-panel-data" role="tabpanel" aria-labelledby="cc-tab-data" data-cc-panel="data" hidden>
        <div class="cc-card">
            <div class="cc-card__head">
                <h3 class="cc-card__title"><i class="fas fa-book-reader" aria-hidden="true"></i> Kesegaran data per report</h3>
                <div class="cc-inline">
                    <label class="cc-switch"><input type="checkbox" data-cc-only-issues> <span>Hanya yang bermasalah</span></label>
                    <button type="button" class="cc-btn cc-btn--ghost" data-cc-refresh="data"><i class="fas fa-redo-alt" aria-hidden="true"></i> Muat ulang</button>
                </div>
            </div>
            <p class="cc-note">Periode terbaru dibaca lewat indeks. Keterlambatan dinilai terhadap ritme update alami setiap report (harian, mingguan, bulanan) sehingga report bulanan tidak dianggap terlambat.</p>
            <div class="cc-table-wrap"><table class="cc-table" data-abah-no-table-guard="1">
                <thead><tr><th scope="col">Report</th><th scope="col">Periode terbaru</th><th scope="col">Terlambat</th><th scope="col">Ritme</th><th scope="col">Import terakhir</th><th scope="col">Status</th></tr></thead>
                <tbody data-cc-freshness></tbody>
            </table></div>
        </div>
        <div class="cc-card">
            <div class="cc-card__head">
                <h3 class="cc-card__title"><i class="fas fa-clone" aria-hidden="true"></i> Shadow column Daily Loan</h3>
                <div class="cc-inline">
                    <button type="button" class="cc-btn cc-btn--ghost" data-cc-shadow-verify><i class="fas fa-search" aria-hidden="true"></i> Verifikasi 3 periode terakhir</button>
                    <button type="button" class="cc-btn cc-btn--primary" data-cc-shadow-repair hidden><i class="fas fa-wrench" aria-hidden="true"></i> Perbaiki periode bermasalah</button>
                </div>
            </div>
            <p class="cc-note">Kolom normalisasi yang dibaca dashboard dibandingkan dengan kolom acuan <code>*_gc</code> yang dihitung database secara otomatis. Paritas 100% berarti setiap nilai identik.</p>
            <div class="cc-shadow" data-cc-shadow></div>
        </div>
    </section>

    {{-- Database --}}
    <section class="cc-panel" id="cc-panel-database" role="tabpanel" aria-labelledby="cc-tab-database" data-cc-panel="database" hidden>
        <div class="cc-card cc-gate" data-cc-gate>
            <div class="cc-gate__icon" aria-hidden="true"><i class="fas fa-lock" data-cc-gate-icon></i></div>
            <div class="cc-gate__body">
                <h3 class="cc-card__title">phpMyAdmin</h3>
                <p class="cc-note" data-cc-gate-text>phpMyAdmin hanya dapat diakses dari server lokal. Gerbang ini membukanya untuk superadmin setelah konfirmasi password akun; phpMyAdmin tetap meminta login database.</p>
            </div>
            <div class="cc-inline">
                <button type="button" class="cc-btn cc-btn--primary" data-cc-gate-open><i class="fas fa-external-link-alt" aria-hidden="true"></i> Buka phpMyAdmin</button>
                <button type="button" class="cc-btn cc-btn--ghost" data-cc-gate-lock hidden><i class="fas fa-lock" aria-hidden="true"></i> Kunci sekarang</button>
            </div>
        </div>
        <div class="cc-card">
            <div class="cc-card__head">
                <h3 class="cc-card__title"><i class="fas fa-tachometer-alt" aria-hidden="true"></i> Kondisi server database</h3>
                <button type="button" class="cc-btn cc-btn--ghost" data-cc-refresh="database"><i class="fas fa-redo-alt" aria-hidden="true"></i> Muat ulang</button>
            </div>
            <dl class="cc-stats" data-cc-db-stats></dl>
        </div>
        <div class="cc-card">
            <h3 class="cc-card__title"><i class="fas fa-hourglass-half" aria-hidden="true"></i> Query berjalan ≥ 5 menit</h3>
            <div class="cc-table-wrap"><table class="cc-table" data-abah-no-table-guard="1">
                <thead><tr><th scope="col">ID</th><th scope="col">Durasi</th><th scope="col">Status</th><th scope="col">Pernyataan (nilai disamarkan)</th></tr></thead>
                <tbody data-cc-long-queries></tbody>
            </table></div>
        </div>
        <div class="cc-grid-2">
            <div class="cc-card">
                <h3 class="cc-card__title"><i class="fas fa-weight-hanging" aria-hidden="true"></i> Tabel terbesar</h3>
                <div class="cc-table-wrap"><table class="cc-table" data-abah-no-table-guard="1">
                    <thead><tr><th scope="col">Tabel</th><th scope="col">Ukuran</th><th scope="col">Perkiraan baris</th></tr></thead>
                    <tbody data-cc-top-tables></tbody>
                </table></div>
            </div>
            <div class="cc-card">
                <h3 class="cc-card__title"><i class="fas fa-hdd" aria-hidden="true"></i> Ruang disk</h3>
                <div data-cc-disks></div>
            </div>
        </div>
    </section>
</div>

<style>
    .cc-tabs {
        display: flex; gap: .35rem; overflow-x: auto; padding: .35rem; margin: 0 0 1rem;
        border: 1px solid var(--app-line, #d8e5f7); border-radius: 14px; background: var(--app-surface, #fff);
        box-shadow: var(--app-shadow, 0 18px 42px -30px rgba(4, 42, 95, .34)); scrollbar-width: thin;
    }
    .cc-tab {
        position: relative; display: inline-flex; align-items: center; gap: .45rem; flex: none;
        min-height: 40px; padding: 0 .95rem; border: 0; border-radius: 10px; background: transparent;
        color: var(--app-muted, #526987); font-weight: 700; font-size: .86rem; cursor: pointer; white-space: nowrap;
    }
    .cc-tab:hover { background: var(--theme-hover, rgba(8, 87, 195, .06)); color: var(--app-ink, #0b1f3a); }
    .cc-tab[aria-selected="true"] { background: var(--bri-nusantara, #0857c3); color: #fff; }
    .cc-tab:focus-visible, .cc-root button:focus-visible, .cc-root input:focus-visible, .cc-root a:focus-visible {
        outline: 3px solid var(--app-focus-ring, rgba(48, 127, 226, .34)); outline-offset: 2px;
    }
    .cc-tab__dot { width: 9px; height: 9px; border-radius: 50%; background: var(--cc-dot, #94a3b8); box-shadow: 0 0 0 2px var(--app-surface, #fff); }

    .cc-root { --cc-ok: var(--app-success, #176b4d); --cc-ok-soft: var(--app-success-soft, #edf8f3); --cc-warn: var(--app-warning, #8a5200); --cc-warn-soft: var(--app-warning-soft, #fff8e8); --cc-fail: var(--app-danger, #b4232d); --cc-fail-soft: var(--app-danger-soft, #fff1f2); --cc-info: var(--bri-nusantara, #0857c3); --cc-info-soft: var(--app-info-soft, #edf6ff); color: var(--app-ink, #0b1f3a); }
    .cc-panel { display: grid; gap: 1rem; margin-bottom: 1.25rem; }
    .cc-panel[hidden], .cc-root [hidden] { display: none !important; }
    .cc-card, .cc-hero, .cc-progress {
        padding: 1.1rem 1.2rem; border: 1px solid var(--app-line, #d8e5f7); border-radius: 14px;
        background: var(--app-surface, #fff); box-shadow: var(--app-shadow, 0 18px 42px -30px rgba(4, 42, 95, .34)); min-width: 0;
    }
    .cc-card__head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; margin-bottom: .35rem; }
    .cc-card__title { margin: 0 0 .35rem; font-size: .98rem; font-weight: 800; display: flex; align-items: center; gap: .5rem; }
    .cc-card__title i { color: var(--bri-nusantara, #0857c3); }
    .cc-note { margin: 0 0 .75rem; color: var(--app-muted, #526987); font-size: .84rem; line-height: 1.5; text-wrap: pretty; }
    .cc-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
    .cc-inline { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }

    .cc-hero { display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; }
    .cc-score { position: relative; width: 132px; height: 132px; flex: none; }
    .cc-score__ring { width: 100%; height: 100%; transform: rotate(-90deg); }
    .cc-score__track { fill: none; stroke: var(--app-line, #e6eefa); stroke-width: 10; }
    .cc-score__value { fill: none; stroke: var(--cc-score-color, var(--cc-info)); stroke-width: 10; stroke-linecap: round; stroke-dasharray: 326.7; stroke-dashoffset: 326.7; transition: stroke-dashoffset .8s cubic-bezier(.16, 1, .3, 1), stroke .3s; }
    .cc-score__text { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .cc-score__text strong { font-size: 2.1rem; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
    .cc-score__text span { max-width: 84px; margin-top: 4px; font-size: .62rem; line-height: 1.25; text-align: center; text-transform: uppercase; letter-spacing: .05em; color: var(--app-muted, #526987); font-weight: 700; }
    .cc-hero__body { flex: 1 1 18rem; min-width: 0; }
    .cc-hero__title { margin: .45rem 0 .6rem; font-size: 1.15rem; font-weight: 800; letter-spacing: -.01em; text-wrap: balance; }
    .cc-hero__meta { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; margin-top: .7rem; font-size: .8rem; color: var(--app-muted, #526987); }

    .cc-pill, .cc-badge { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; white-space: nowrap; }
    .cc-chips { display: flex; gap: .4rem; flex-wrap: wrap; }
    .cc-chip { padding: .25rem .6rem; border-radius: 8px; font-size: .78rem; font-weight: 700; background: var(--app-surface-soft, #f7faff); border: 1px solid var(--app-line, #d8e5f7); }
    [data-tone="ok"] { --cc-tone: var(--cc-ok); --cc-tone-soft: var(--cc-ok-soft); }
    [data-tone="warn"] { --cc-tone: var(--cc-warn); --cc-tone-soft: var(--cc-warn-soft); }
    [data-tone="fail"] { --cc-tone: var(--cc-fail); --cc-tone-soft: var(--cc-fail-soft); }
    [data-tone="info"], [data-tone="unknown"] { --cc-tone: var(--cc-info); --cc-tone-soft: var(--cc-info-soft); }
    .cc-pill[data-tone], .cc-badge[data-tone] { background: var(--cc-tone-soft); color: var(--cc-tone); }

    .cc-groups { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .6rem; }
    .cc-group { padding: .7rem .8rem; border-radius: 12px; border: 1px solid var(--app-line, #d8e5f7); background: var(--app-surface, #fff); border-left: 4px solid var(--cc-tone, var(--app-line)); }
    .cc-group__label { font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--app-muted, #526987); }
    .cc-group__value { margin-top: .15rem; font-weight: 800; font-size: .9rem; color: var(--cc-tone); }

    .cc-checks { list-style: none; margin: 0; padding: 0; display: grid; gap: .5rem; }
    .cc-check { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: .2rem .8rem; align-items: start; padding: .75rem .9rem; border-radius: 12px; border: 1px solid var(--app-line, #d8e5f7); background: var(--app-surface, #fff); }
    .cc-check__icon { grid-row: span 3; width: 32px; height: 32px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center; background: var(--cc-tone-soft); color: var(--cc-tone); }
    .cc-check__label { font-weight: 800; font-size: .9rem; }
    .cc-check__value { font-weight: 800; font-size: .84rem; color: var(--cc-tone); text-align: right; font-variant-numeric: tabular-nums; }
    .cc-check__detail, .cc-check__action { grid-column: 2 / 4; font-size: .82rem; color: var(--app-muted, #526987); line-height: 1.45; overflow-wrap: anywhere; }
    .cc-check__action { color: var(--app-ink, #0b1f3a); font-weight: 600; }
    .cc-check__action::before { content: "→ "; color: var(--cc-tone); }
    .cc-check[data-tone="ok"] .cc-check__action { display: none; }

    .cc-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .6rem; margin: 0; }
    .cc-stat { margin: 0; padding: .65rem .8rem; border-radius: 12px; background: var(--app-surface-soft, #f7faff); border: 1px solid var(--app-line, #d8e5f7); }
    .cc-stat dt { font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--app-muted, #526987); }
    .cc-stat dd { margin: .15rem 0 0; font-size: 1.2rem; font-weight: 800; font-variant-numeric: tabular-nums; }
    .cc-stat dd small { font-size: .75rem; color: var(--app-muted, #526987); font-weight: 600; }

    .cc-table-wrap { overflow-x: auto; border: 1px solid var(--app-line, #d8e5f7); border-radius: 12px; }
    .cc-table { width: 100%; border-collapse: collapse; font-size: .83rem; }
    .cc-table th { padding: .55rem .7rem; text-align: left; white-space: nowrap; background: var(--app-surface-soft, #f7faff); color: var(--app-muted, #526987); font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid var(--app-line, #d8e5f7); }
    .cc-table td { padding: .55rem .7rem; border-bottom: 1px solid var(--app-line, #eef3fb); vertical-align: top; }
    .cc-table tbody tr:last-child td { border-bottom: 0; }
    .cc-table td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .cc-table code, .cc-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .78rem; overflow-wrap: anywhere; }
    .cc-empty { padding: 1rem; text-align: center; color: var(--app-muted, #526987); font-size: .84rem; }
    .cc-sub { display: block; color: var(--app-muted, #526987); font-size: .74rem; }

    .cc-btn { display: inline-flex; align-items: center; gap: .4rem; min-height: 38px; padding: 0 .9rem; border-radius: 10px; border: 1px solid transparent; font-weight: 800; font-size: .83rem; cursor: pointer; }
    .cc-btn[disabled] { opacity: .6; cursor: progress; }
    .cc-btn--primary { background: var(--bri-nusantara, #0857c3); color: #fff; }
    .cc-btn--warning { background: #d97706; color: #fff; }
    .cc-btn--ghost { background: transparent; color: var(--app-ink, #0b1f3a); border-color: var(--app-line-strong, #b9cfee); }
    .cc-btn--ghost:hover { background: var(--theme-hover, rgba(8, 87, 195, .06)); }
    .cc-input { min-height: 38px; padding: .3rem .6rem; border-radius: 10px; border: 1px solid var(--app-line-strong, #b9cfee); background: var(--app-surface, #fff); color: var(--app-ink, #0b1f3a); }
    .cc-label { display: block; font-size: .78rem; font-weight: 700; margin-bottom: .3rem; color: var(--app-muted, #526987); }
    .cc-switch { display: inline-flex; align-items: center; gap: .4rem; font-size: .83rem; font-weight: 700; cursor: pointer; }
    .cc-switch input { width: 16px; height: 16px; accent-color: var(--bri-nusantara, #0857c3); }

    .cc-progress__top { display: flex; justify-content: space-between; font-size: .88rem; margin-bottom: .45rem; }
    .cc-progress__bar { height: 10px; border-radius: 999px; background: var(--app-line, #e6eefa); overflow: hidden; }
    .cc-progress__bar span { display: block; height: 100%; width: 0; background: var(--bri-nusantara, #0857c3); transition: width .4s ease; }
    .cc-progress[data-tone="ok"] .cc-progress__bar span { background: var(--cc-ok); }
    .cc-progress[data-tone="fail"] .cc-progress__bar span { background: var(--cc-fail); }

    .cc-shadow { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: .6rem; }
    .cc-shadow__period { padding: .8rem .9rem; border-radius: 12px; border: 1px solid var(--app-line, #d8e5f7); border-top: 4px solid var(--cc-tone); }
    .cc-shadow__head { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; }
    .cc-shadow__parity { font-size: 1.4rem; font-weight: 800; color: var(--cc-tone); font-variant-numeric: tabular-nums; }
    .cc-shadow__rows { margin: .45rem 0 0; padding: 0; list-style: none; font-size: .79rem; display: grid; gap: .15rem; }
    .cc-shadow__rows li { display: flex; justify-content: space-between; gap: .5rem; }

    .cc-gate { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
    .cc-gate__icon { width: 52px; height: 52px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; background: var(--cc-fail-soft); color: var(--cc-fail); flex: none; }
    .cc-gate[data-unlocked="1"] .cc-gate__icon { background: var(--cc-ok-soft); color: var(--cc-ok); }
    .cc-gate__body { flex: 1 1 18rem; min-width: 0; }
    .cc-disk { margin-bottom: .75rem; }
    .cc-disk__top { display: flex; justify-content: space-between; font-size: .83rem; font-weight: 700; margin-bottom: .3rem; }
    .cc-meter { height: 9px; border-radius: 999px; background: var(--app-line, #e6eefa); overflow: hidden; }
    .cc-meter span { display: block; height: 100%; background: var(--cc-tone, var(--cc-info)); }

    :root[data-theme="dark"] .cc-btn--warning { background: #b45309; }
    @media (max-width: 991.98px) { .cc-grid-2 { grid-template-columns: 1fr; } }
    @media (max-width: 575.98px) {
        .cc-hero { flex-direction: column; align-items: flex-start; }
        .cc-check { grid-template-columns: auto minmax(0, 1fr); }
        .cc-check__value { grid-column: 2; text-align: left; }
    }
    @media (prefers-reduced-motion: reduce) { .cc-score__value, .cc-progress__bar span { transition: none; } }
</style>

<script>
(function () {
    'use strict';
    var root = document.getElementById('controlCenter');
    var tabs = document.getElementById('ccTabs');
    if (!root || !tabs) { return; }

    var D = root.dataset;
    var loaded = {};
    var timers = {};
    var TONE_LABEL = { ok: 'Sehat', warn: 'Perhatian', fail: 'Kritis', info: 'Info', unknown: 'Tidak diketahui' };
    var TONE_ICON = { ok: 'fa-check', warn: 'fa-exclamation', fail: 'fa-times', info: 'fa-info', unknown: 'fa-question' };
    var GROUP_LABEL = { runtime: 'Layanan server', queue: 'Worker & antrean', imports: 'Import', data: 'Data report', snapshots: 'Snapshot', shadow: 'Shadow column', database: 'Database', schema: 'Struktur sumber' };

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (key) {
            if (key === 'text') { node.textContent = attrs[key] == null ? '' : String(attrs[key]); }
            else if (key === 'className') { node.className = attrs[key]; }
            else if (attrs[key] !== null && attrs[key] !== undefined && attrs[key] !== false) { node.setAttribute(key, attrs[key] === true ? '' : attrs[key]); }
        });
        (children || []).forEach(function (child) { if (child) { node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child); } });
        return node;
    }
    function icon(name) { return el('i', { className: 'fas ' + name, 'aria-hidden': 'true' }); }
    function q(sel) { return root.querySelector(sel); }
    function fill(sel, nodes, emptyText, colspan) {
        var host = q(sel); host.textContent = '';
        if (!nodes.length) {
            if (emptyText) {
                host.appendChild(colspan ? el('tr', {}, [el('td', { colspan: colspan, className: 'cc-empty', text: emptyText })]) : el('p', { className: 'cc-empty', text: emptyText }));
            }
            return;
        }
        nodes.forEach(function (node) { host.appendChild(node); });
    }
    function badge(tone, text) { return el('span', { className: 'cc-badge', 'data-tone': tone, text: text || TONE_LABEL[tone] || tone }); }
    function fmtNum(n) { return Number(n || 0).toLocaleString('id-ID'); }
    function fmtBytes(b) { if (b == null) { return '-'; } var u = ['B', 'KB', 'MB', 'GB', 'TB'], i = 0; b = Number(b); while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; } return b.toFixed(i > 1 ? 1 : 0) + ' ' + u[i]; }
    function fmtTime(iso) { if (!iso) { return '-'; } var d = new Date(String(iso).replace(' ', 'T')); return isNaN(d) ? String(iso) : d.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }); }
    function fmtDuration(s) { s = Number(s || 0); if (s < 60) { return s + ' dtk'; } if (s < 3600) { return Math.round(s / 60) + ' mnt'; } return (s / 3600).toFixed(1) + ' jam'; }

    function request(url, options) {
        options = options || {};
        return fetch(url, {
            method: options.method || 'GET',
            credentials: 'same-origin',
            headers: Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, options.body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': D.csrf } : {}),
            body: options.body ? JSON.stringify(options.body) : undefined
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok || data.status === 'error') { throw new Error(data.message || ('Permintaan gagal (HTTP ' + response.status + ').')); }
                return data;
            });
        });
    }
    function swal() { return window.ImportSwal && typeof window.ImportSwal.fire === 'function' ? window.ImportSwal : window.Swal; }
    function notify(kind, title, text) { var s = swal(); if (s) { s.fire({ icon: kind, title: title, text: text }); } else { window.alert(title + '\n' + text); } }

    /* ---------- tabs ---------- */
    var TAB_KEYS = Array.prototype.map.call(tabs.querySelectorAll('[data-cc-tab]'), function (b) { return b.dataset.ccTab; });
    function panelFor(key) { return key === 'operations' ? document.getElementById('cc-panel-operations') : q('[data-cc-panel="' + key + '"]'); }
    function activate(key, focus) {
        if (TAB_KEYS.indexOf(key) === -1) { key = 'overview'; }
        TAB_KEYS.forEach(function (k) {
            var btn = document.getElementById('cc-tab-' + k);
            var panel = panelFor(k);
            var on = k === key;
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
            btn.tabIndex = on ? 0 : -1;
            if (panel) { panel.hidden = !on; }
            if (on && focus) { btn.focus(); }
        });
        try { localStorage.setItem('cc-tab', key); } catch (e) { /* storage optional */ }
        var url = new URL(window.location.href); url.searchParams.set('tab', key); window.history.replaceState(null, '', url);
        if (!loaded[key] && LOADERS[key]) { loaded[key] = true; LOADERS[key](); }
    }
    tabs.addEventListener('click', function (e) { var b = e.target.closest('[data-cc-tab]'); if (b) { activate(b.dataset.ccTab); } });
    tabs.addEventListener('keydown', function (e) {
        var i = TAB_KEYS.indexOf(document.activeElement && document.activeElement.dataset ? document.activeElement.dataset.ccTab : '');
        if (i === -1) { return; }
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { e.preventDefault(); activate(TAB_KEYS[(i + (e.key === 'ArrowRight' ? 1 : TAB_KEYS.length - 1)) % TAB_KEYS.length], true); }
        if (e.key === 'Home') { e.preventDefault(); activate(TAB_KEYS[0], true); }
        if (e.key === 'End') { e.preventDefault(); activate(TAB_KEYS[TAB_KEYS.length - 1], true); }
    });

    /* ---------- overview ---------- */
    function loadOverview(refresh) {
        return request(D.healthUrl + (refresh ? '?refresh=1' : '')).then(renderOverview).catch(function (error) {
            q('[data-cc-headline]').textContent = 'Pemeriksaan gagal: ' + error.message;
        });
    }
    function renderOverview(data) {
        var tone = data.overall || 'info';
        var score = Math.max(0, Math.min(100, Number(data.score || 0)));
        q('[data-cc-score]').textContent = score;
        var ring = q('[data-cc-score-ring]');
        ring.style.strokeDashoffset = String(326.7 * (1 - score / 100));
        q('[data-cc-score-wrap]').style.setProperty('--cc-score-color', 'var(--cc-' + tone + ')');
        var pill = q('[data-cc-overall]'); pill.textContent = ''; pill.dataset.tone = tone; pill.appendChild(icon(TONE_ICON[tone])); pill.appendChild(document.createTextNode(TONE_LABEL[tone]));
        q('[data-cc-headline]').textContent = data.headline || '';
        q('[data-cc-generated]').textContent = 'Diperiksa ' + fmtTime(data.generated_at) + ' · ' + fmtNum(data.duration_ms) + ' ms';
        var dot = tabs.querySelector('[data-cc-overall-dot]'); dot.hidden = tone === 'ok'; dot.style.setProperty('--cc-dot', 'var(--cc-' + tone + ')');

        var counts = data.counts || {};
        fill('[data-cc-counts]', [['ok', counts.ok, 'sehat'], ['warn', counts.warn, 'perhatian'], ['fail', counts.fail, 'kritis'], ['info', counts.info, 'info']]
            .filter(function (c) { return c[1] > 0; })
            .map(function (c) { return el('span', { className: 'cc-chip cc-badge', 'data-tone': c[0], text: c[1] + ' ' + c[2] }); }), '');

        var groups = {};
        (data.checks || []).forEach(function (check) {
            var rank = { fail: 4, warn: 3, unknown: 2, info: 1, ok: 0 };
            var current = groups[check.group];
            if (!current || rank[check.status] > rank[current]) { groups[check.group] = check.status; }
        });
        fill('[data-cc-groups]', Object.keys(GROUP_LABEL).filter(function (g) { return groups[g]; }).map(function (g) {
            return el('div', { className: 'cc-group', 'data-tone': groups[g] }, [el('div', { className: 'cc-group__label', text: GROUP_LABEL[g] }), el('div', { className: 'cc-group__value', text: TONE_LABEL[groups[g]] })]);
        }), '');

        fill('[data-cc-checks]', (data.checks || []).map(function (check) {
            return el('li', { className: 'cc-check', 'data-tone': check.status }, [
                el('span', { className: 'cc-check__icon' }, [icon(TONE_ICON[check.status] || 'fa-info')]),
                el('span', { className: 'cc-check__label', text: check.label }),
                el('span', { className: 'cc-check__value', text: check.value }),
                el('span', { className: 'cc-check__detail', text: check.detail }),
                el('span', { className: 'cc-check__action', text: check.action })
            ]);
        }), 'Belum ada hasil pemeriksaan.');
    }

    /* ---------- workers ---------- */
    function loadWorkers() {
        return request(D.workersUrl).then(function (data) {
            var plan = data.plan || {};
            var monitor = data.monitor || {};
            var totals = monitor.totals || {};
            fill('[data-cc-plan-stats]', [
                ['Worker hidup', fmtNum(totals.live_workers), 'dari kapasitas ' + fmtNum(totals.capacity)],
                ['Anggaran global', plan.global_cap != null ? fmtNum(plan.global_cap) : '-', data.planner && data.planner.enabled ? 'maks ' + fmtNum(plan.max_total_workers) : 'perencana nonaktif'],
                ['RAM bebas', plan.free_memory_mb != null ? fmtNum(plan.free_memory_mb) + ' MB' : '-', 'cadangan ' + fmtNum(plan.memory_reserve_mb) + ' MB'],
                ['Batas dari RAM', plan.memory_cap != null ? fmtNum(plan.memory_cap) + ' worker' : '-', '±' + fmtNum(plan.worker_memory_estimate_mb) + ' MB / worker'],
                ['Job siap', fmtNum(totals.ready_jobs), fmtNum(totals.reserved_jobs) + ' sedang jalan'],
                ['Pool macet', fmtNum(totals.stalled_pools), fmtNum(totals.outdated_workers) + ' worker versi lama']
            ].map(function (s) { return el('div', { className: 'cc-stat' }, [el('dt', { text: s[0] }), el('dd', {}, [s[1], el('small', { text: ' ' + s[2] })])]); }), '');

            var monitorPools = {};
            (monitor.pools || []).forEach(function (pool) { monitorPools[pool.name] = pool; });
            var decisions = plan.decisions || {};
            fill('[data-cc-pools]', (data.pool_policy || []).map(function (policy) {
                var pool = monitorPools[policy.name] || {};
                var d = decisions[policy.name] || {};
                var flags = [policy.critical ? 'kritis' : null, policy.elastic ? 'elastis' : null].filter(Boolean).join(' · ');
                var tone = pool.tone === 'danger' ? 'fail' : (pool.tone === 'warning' ? 'warn' : (pool.state === 'running' ? 'ok' : 'info'));
                var status = el('td', {}, [badge(tone, pool.state_label || 'Idle')]);
                if (d.throttled) { status.appendChild(el('span', { className: 'cc-sub', text: d.limited_by === 'memory' ? 'Ditahan: RAM terbatas' : 'Ditahan: anggaran global' })); }
                return el('tr', {}, [
                    el('td', {}, [el('strong', { text: policy.name }), el('span', { className: 'cc-sub', text: flags || 'standar' })]),
                    el('td', { className: 'num', text: policy.priority }),
                    el('td', {}, [el('code', { text: policy.queues })]),
                    el('td', { className: 'num', text: fmtNum(pool.ready_jobs) + ' / ' + fmtNum(pool.reserved_jobs) }),
                    el('td', { className: 'num', text: fmtNum(pool.live_workers) }),
                    el('td', { className: 'num', text: (d.target != null ? fmtNum(d.target) : '-') + ' / ' + fmtNum(policy.capacity) }),
                    status
                ]);
            }), 'Tidak ada pool worker.', 7);

            var failed = (monitor.failed_jobs || {}).groups || [];
            fill('[data-cc-failed]', failed.map(function (g) {
                return el('tr', {}, [el('td', {}, [el('strong', { text: g.job })]), el('td', {}, [el('code', { text: g.queue })]), el('td', { className: 'num', text: fmtNum(g.count) }), el('td', { text: fmtTime(g.last_failed_at) }), el('td', { className: 'cc-mono', text: g.error })]);
            }), 'Tidak ada job gagal dalam 7 hari.', 5);
        }).catch(function (error) { fill('[data-cc-pools]', [], error.message, 7); });
    }

    /* ---------- snapshot ---------- */
    function loadSnapshots() {
        return request(D.snapshotsUrl).then(function (data) {
            fill('[data-cc-snapshot-sources]', (data.sources || []).map(function (s) {
                return el('tr', {}, [el('td', {}, [el('code', { text: s.source })]), el('td', { text: s.source_latest_period || '-' }), el('td', { text: s.snapshot_latest_period || '-' }), el('td', { text: fmtTime(s.last_built) }), el('td', { className: 'num', text: s.snapshot_tables }), el('td', {}, [badge(s.in_sync ? 'ok' : 'warn', s.in_sync ? 'Sinkron' : 'Tertinggal')])]);
            }), 'Belum ada tanda tangan snapshot.', 6);
            fill('[data-cc-dirty]', (data.dirty_periods || []).map(function (p) {
                var tone = p.state === 'error' ? 'fail' : (p.state === 'processing' ? 'info' : 'warn');
                var label = p.state === 'error' ? 'Gagal' : (p.state === 'processing' ? 'Diproses' : 'Menunggu');
                var status = el('td', {}, [badge(tone, label)]);
                if (p.error) { status.appendChild(el('span', { className: 'cc-sub', text: p.error })); }
                return el('tr', {}, [el('td', {}, [el('code', { text: p.source })]), el('td', { text: p.period }), el('td', { text: p.shard }), el('td', { text: fmtTime(p.dirty_since) }), el('td', { className: 'num', text: p.attempts }), status]);
            }), 'Tidak ada periode yang menunggu refresh. Semua snapshot terkini.', 6);
        }).catch(function (error) { fill('[data-cc-snapshot-sources]', [], error.message, 6); });
    }

    function showProgress(title, percent, text, tone) {
        var box = q('[data-cc-snapshot-progress]');
        box.hidden = false; box.dataset.tone = tone || '';
        q('[data-cc-progress-title]').textContent = title;
        q('[data-cc-progress-percent]').textContent = Math.round(percent) + '%';
        q('[data-cc-progress-bar]').style.width = Math.max(0, Math.min(100, percent)) + '%';
        q('[data-cc-progress-text]').textContent = text || '';
    }
    function poll(url, percentField, title) {
        var startedAt = Date.now();
        return new Promise(function (resolve) {
            (function tick() {
                fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (state) {
                        var status = String(state.status || '').toLowerCase();
                        var percent = Number(state[percentField] || 0);
                        var done = ['completed', 'failed', 'error', 'warning', 'not_found'].indexOf(status) !== -1;
                        showProgress(title, done && status === 'completed' ? 100 : percent, state.message || '', status === 'completed' ? 'ok' : (done && status !== 'warning' ? 'fail' : ''));
                        if (done || Date.now() - startedAt > 30 * 60 * 1000) { resolve(state); loadSnapshots(); return; }
                        window.setTimeout(tick, 2500);
                    })
                    .catch(function () { window.setTimeout(tick, 5000); });
            }());
        });
    }
    q('[data-cc-force-sync-form]').addEventListener('submit', function (e) {
        e.preventDefault();
        var value = document.getElementById('ccForceSyncDate').value;
        if (!value) { return; }
        var parts = value.split('-');
        var period = parts[2] + '-' + parts[1] + '-' + parts[0];
        var button = e.target.querySelector('button'); button.disabled = true;
        request(D.forceSyncUrl, { method: 'POST', body: { period: period } }).then(function (data) {
            if (!data.sync_id) { notify('success', 'Sinkronisasi dijalankan', data.message || ''); return; }
            showProgress('Sinkronisasi periode ' + period, 0, data.message || 'Diantrekan…');
            return poll(D.forceSyncStatusUrl.replace('__ID__', encodeURIComponent(data.sync_id)), 'progress', 'Sinkronisasi periode ' + period);
        }).catch(function (error) { notify('error', 'Sinkronisasi gagal', error.message); })
          .finally(function () { button.disabled = false; });
    });
    q('[data-cc-rebuild]').addEventListener('click', function () {
        var full = q('[data-cc-rebuild-full]').checked;
        var s = swal();
        var confirmed = s ? s.fire({ icon: 'question', title: full ? 'Rebuild penuh dari awal?' : 'Refresh seluruh snapshot?', text: full ? 'Seluruh snapshot dibangun ulang; proses bisa lama.' : 'Snapshot seluruh report direfresh tanpa rebuild penuh.', showCancelButton: true, confirmButtonText: 'Lanjutkan', cancelButtonText: 'Batal' }).then(function (r) { return r.isConfirmed; }) : Promise.resolve(window.confirm('Lanjutkan rebuild snapshot?'));
        var button = this;
        confirmed.then(function (ok) {
            if (!ok) { return; }
            button.disabled = true;
            return request(D.rebuildUrl, { method: 'POST', body: { force_rebuild: full } }).then(function (data) {
                if (!data.rebuild_id) { notify(data.status === 'warning' ? 'warning' : 'success', 'Rebuild snapshot', data.message || 'Dijadwalkan.'); return; }
                showProgress('Rebuild snapshot', 0, data.message || 'Diantrekan…');
                return poll(D.rebuildStatusUrl.replace('__ID__', encodeURIComponent(data.rebuild_id)), 'progress_percent', 'Rebuild snapshot');
            });
        }).catch(function (error) { notify('error', 'Rebuild gagal', error.message); })
          .finally(function () { button.disabled = false; });
    });

    /* ---------- data & shadow ---------- */
    var freshnessRows = [];
    function renderFreshness() {
        var onlyIssues = q('[data-cc-only-issues]').checked;
        fill('[data-cc-freshness]', freshnessRows.filter(function (r) { return !onlyIssues || r.status === 'warn' || r.status === 'fail'; }).map(function (r) {
            var last = r.last_import ? (r.last_import.status + ' · ' + fmtTime(r.last_import.finished_at)) : '-';
            var status = el('td', {}, [badge(r.status, r.dormant ? 'Dorman' : null), el('span', { className: 'cc-sub', text: r.message })]);
            return el('tr', {}, [
                el('td', {}, [el('strong', { text: r.report }), el('span', { className: 'cc-sub', text: r.table + (r.period_column ? ' · ' + r.period_column : '') })]),
                el('td', { text: r.latest_period || '-' }),
                el('td', { className: 'num', text: r.lag_days != null ? r.lag_days + ' hari' : '-' }),
                el('td', { className: 'num', text: r.cadence_days != null ? '±' + r.cadence_days + ' hari' : '-' }),
                el('td', { text: last }),
                status
            ]);
        }), onlyIssues ? 'Tidak ada report yang bermasalah.' : 'Belum ada report aktif.', 6);
    }
    var shadowProblemPeriods = [];
    function renderShadow(data) {
        shadowProblemPeriods = [];
        if (!data.available) { fill('[data-cc-shadow]', [], 'Shadow column tidak tersedia pada tabel ini.'); return; }
        fill('[data-cc-shadow]', (data.periods || []).map(function (p) {
            if (p.status === 'warn' || p.status === 'fail') { shadowProblemPeriods.push(p.period); }
            var rows = [el('li', {}, [el('span', { text: 'Baris' }), el('strong', { text: fmtNum(p.total_rows) })]), el('li', {}, [el('span', { text: 'Belum dibangun' }), el('strong', { text: fmtNum(p.unbuilt_rows) })])];
            Object.keys(p.mismatches || {}).forEach(function (col) { rows.push(el('li', {}, [el('code', { text: col }), el('strong', { text: fmtNum(p.mismatches[col]) })])); });
            Object.keys(p.empty_derived || {}).forEach(function (col) { rows.push(el('li', {}, [el('code', { text: col + ' kosong' }), el('strong', { text: fmtNum(p.empty_derived[col]) })])); });
            return el('div', { className: 'cc-shadow__period', 'data-tone': p.status }, [
                el('div', { className: 'cc-shadow__head' }, [el('strong', { text: 'Periode ' + p.period }), el('span', { className: 'cc-shadow__parity', text: p.parity_percent + '%' })]),
                el('span', { className: 'cc-sub', text: 'Diperiksa dalam ' + fmtNum(p.checked_in_ms) + ' ms' }),
                el('ul', { className: 'cc-shadow__rows' }, rows)
            ]);
        }), 'Belum ada periode untuk diperiksa.');
        q('[data-cc-shadow-repair]').hidden = shadowProblemPeriods.length === 0;
    }
    function loadData(refresh) {
        request(D.freshnessUrl + (refresh ? '?refresh=1' : '')).then(function (data) { freshnessRows = data.reports || []; renderFreshness(); })
            .catch(function (error) { fill('[data-cc-freshness]', [], error.message, 6); });
        request(D.shadowUrl + (refresh ? '?refresh=1' : '')).then(renderShadow).catch(function (error) { fill('[data-cc-shadow]', [], error.message); });
    }
    q('[data-cc-only-issues]').addEventListener('change', renderFreshness);
    q('[data-cc-shadow-verify]').addEventListener('click', function () {
        var button = this; button.disabled = true;
        fill('[data-cc-shadow]', [], 'Memverifikasi paritas 3 periode terakhir…');
        request(D.shadowUrl + '?verify=3').then(renderShadow).catch(function (error) { fill('[data-cc-shadow]', [], error.message); })
            .finally(function () { button.disabled = false; });
    });
    q('[data-cc-shadow-repair]').addEventListener('click', function () {
        var button = this; button.disabled = true;
        request(D.shadowRepairUrl, { method: 'POST', body: { periods: shadowProblemPeriods } })
            .then(function (data) { notify('success', 'Perbaikan diantrekan', data.message); })
            .catch(function (error) { notify('error', 'Perbaikan gagal', error.message); })
            .finally(function () { button.disabled = false; });
    });

    /* ---------- database ---------- */
    function renderGate(gate) {
        var card = q('[data-cc-gate]');
        var unlocked = !!(gate && gate.unlocked);
        card.dataset.unlocked = unlocked ? '1' : '0';
        q('[data-cc-gate-icon]').className = 'fas ' + (unlocked ? 'fa-lock-open' : 'fa-lock');
        q('[data-cc-gate-lock]').hidden = !unlocked;
        q('[data-cc-gate-text]').textContent = unlocked
            ? 'Gerbang terbuka. Terkunci otomatis dalam ' + fmtDuration(Math.min(gate.expires_in, gate.idle_expires_in)) + ' tanpa aktivitas (maksimal ' + fmtDuration(gate.expires_in) + '). phpMyAdmin tetap meminta login database.'
            : 'phpMyAdmin hanya dapat diakses dari server lokal. Gerbang ini membukanya untuk superadmin setelah konfirmasi password akun; phpMyAdmin tetap meminta login database.';
    }
    function loadDatabase(refresh) {
        return request(D.databaseUrl + (refresh ? '?refresh=1' : '')).then(function (data) {
            renderGate(data.gateway);
            if (!data.available) { fill('[data-cc-db-stats]', [], 'Statistik hanya tersedia untuk MySQL/MariaDB.'); return; }
            var c = data.connections, s = data.server, p = data.pressure;
            fill('[data-cc-db-stats]', [
                ['Koneksi', fmtNum(c.connected) + ' / ' + fmtNum(c.max), 'puncak ' + fmtNum(c.peak)],
                ['Query aktif', fmtNum(c.running), fmtNum(s.queries_per_second) + ' query/dtk rata-rata'],
                ['Ukuran database', fmtBytes(data.storage.database_bytes), fmtNum(data.storage.tables) + ' tabel'],
                ['Buffer pool', fmtBytes(s.buffer_pool_bytes), s.version],
                ['Uptime', fmtDuration(s.uptime_seconds), ''],
                ['Tunggu row lock', fmtNum(p.row_lock_waits), fmtNum(p.slow_queries) + ' slow query']
            ].map(function (x) { return el('div', { className: 'cc-stat' }, [el('dt', { text: x[0] }), el('dd', {}, [x[1], el('small', { text: ' ' + x[2] })])]); }), '');
            fill('[data-cc-long-queries]', (data.long_queries || []).map(function (lq) {
                return el('tr', {}, [el('td', { className: 'num', text: lq.id }), el('td', { className: 'num', text: fmtDuration(lq.seconds) }), el('td', { text: lq.state || lq.command }), el('td', { className: 'cc-mono', text: lq.sql })]);
            }), 'Tidak ada query yang berjalan lebih dari 5 menit.', 4);
            fill('[data-cc-top-tables]', (data.storage.top_tables || []).map(function (t) {
                return el('tr', {}, [el('td', {}, [el('code', { text: t.table })]), el('td', { className: 'num', text: fmtBytes(t.bytes) }), el('td', { className: 'num', text: fmtNum(t.rows) })]);
            }), '-', 3);
            fill('[data-cc-disks]', (data.storage.disks || []).map(function (d) {
                var used = d.total_bytes ? 1 - d.free_bytes / d.total_bytes : 0;
                var tone = d.free_bytes < 15 * 1073741824 ? 'fail' : (d.free_bytes < 40 * 1073741824 ? 'warn' : 'ok');
                return el('div', { className: 'cc-disk', 'data-tone': tone }, [
                    el('div', { className: 'cc-disk__top' }, [el('span', { text: d.path + ' · ' + d.label }), el('span', { text: fmtBytes(d.free_bytes) + ' kosong' })]),
                    el('div', { className: 'cc-meter', role: 'meter', 'aria-valuemin': '0', 'aria-valuemax': '100', 'aria-valuenow': String(Math.round(used * 100)), 'aria-label': 'Pemakaian disk ' + d.path }, [el('span', { style: 'width:' + Math.round(used * 100) + '%' })])
                ]);
            }), 'Informasi disk tidak tersedia.');
        }).catch(function (error) { fill('[data-cc-db-stats]', [], error.message); });
    }
    q('[data-cc-gate-open]').addEventListener('click', function () {
        if (q('[data-cc-gate]').dataset.unlocked === '1') { window.open(D.pmaUrl, '_blank', 'noopener'); return; }
        var s = swal();
        if (!s) { return; }
        s.fire({
            title: 'Konfirmasi password akun',
            text: 'Akses database memerlukan verifikasi ulang identitas Anda.',
            input: 'password',
            inputAttributes: { autocomplete: 'current-password', 'aria-label': 'Password akun' },
            showCancelButton: true,
            confirmButtonText: 'Buka akses',
            cancelButtonText: 'Batal',
            showLoaderOnConfirm: true,
            preConfirm: function (password) {
                if (!password) { s.showValidationMessage('Password wajib diisi.'); return false; }
                return request(D.unlockUrl, { method: 'POST', body: { password: password } }).catch(function (error) { s.showValidationMessage(error.message); return false; });
            },
            allowOutsideClick: function () { return !s.isLoading(); }
        }).then(function (result) {
            if (!result.isConfirmed || !result.value) { return; }
            renderGate(result.value.gateway);
            s.fire({ icon: 'success', title: 'Akses dibuka', html: '<p>' + result.value.message.replace(/[<>&]/g, '') + '</p><a class="cc-btn cc-btn--primary" href="' + D.pmaUrl + '" target="_blank" rel="noopener">Buka phpMyAdmin</a>', showConfirmButton: false, showCloseButton: true });
        });
    });
    q('[data-cc-gate-lock]').addEventListener('click', function () {
        request(D.lockUrl, { method: 'POST', body: {} }).then(function (data) { renderGate(data.gateway); notify('success', 'Terkunci', data.message); })
            .catch(function (error) { notify('error', 'Gagal mengunci', error.message); });
    });

    /* ---------- refresh buttons & scheduling ---------- */
    var LOADERS = {
        overview: function () { loadOverview(false); },
        workers: loadWorkers,
        snapshot: loadSnapshots,
        data: function () { loadData(false); },
        database: function () { loadDatabase(false); }
    };
    root.addEventListener('click', function (e) {
        var b = e.target.closest('[data-cc-refresh]');
        if (!b) { return; }
        var key = b.dataset.ccRefresh;
        b.disabled = true;
        var done = function () { b.disabled = false; };
        var p = key === 'overview' ? loadOverview(true) : key === 'data' ? Promise.resolve(loadData(true)) : key === 'database' ? loadDatabase(true) : LOADERS[key]();
        Promise.resolve(p).finally(done);
    });
    function every(key, ms, fn) {
        timers[key] = window.setInterval(function () {
            var panel = panelFor(key);
            if (!document.hidden && panel && !panel.hidden) { fn(); }
        }, ms);
    }
    every('overview', 30000, function () { loadOverview(false); });
    every('workers', 15000, loadWorkers);
    every('snapshot', 30000, loadSnapshots);
    every('database', 30000, function () { loadDatabase(false); });

    var initial = new URL(window.location.href).searchParams.get('tab');
    if (!initial) { try { initial = localStorage.getItem('cc-tab'); } catch (e) { initial = null; } }
    activate(initial || 'overview');
    // The overall dot on the tab bar is useful even when another tab is open.
    if (initial && initial !== 'overview') { loaded.overview = true; loadOverview(false); }
}());
</script>
