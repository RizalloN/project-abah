@extends('layouts.admin')

@section('title', 'A-SIX | Dashboard Area 6')

@section('content')
@php
    $hero = data_get($dashboard ?? [], 'hero', []);
    $health = data_get($dashboard ?? [], 'health', []);
    $metrics = data_get($dashboard ?? [], 'metrics', []);
    $performance = data_get($dashboard ?? [], 'performance', []);
    $digitalPerformance = data_get($dashboard ?? [], 'digital_performance', []);
    $priorities = data_get($dashboard ?? [], 'priorities', []);
    $activities = data_get($dashboard ?? [], 'activities', []);
    $agenda = data_get($dashboard ?? [], 'agenda', []);
    $dataQuality = data_get($dashboard ?? [], 'data_quality', []);
    $dashboardLogo = asset('images/a-six-logo.svg');

    $heroStats = is_array(data_get($hero, 'stats')) ? data_get($hero, 'stats') : [];
    $healthItems = is_array(data_get($health, 'items')) ? data_get($health, 'items') : [];
    $performanceBars = is_array(data_get($performance, 'bars')) ? data_get($performance, 'bars') : [];
    $digitalCards = is_array(data_get($digitalPerformance, 'cards')) ? data_get($digitalPerformance, 'cards') : [];

    $healthProgress = min(100, max(0, (float) data_get($health, 'progress', 0)));
@endphp
<style>
    .dashboard-hero {
        background: linear-gradient(135deg, #053b82 0%, #0857c3 52%, #307fe2 100%);
        border-radius: 1rem;
        color: #fff;
        overflow: hidden;
        position: relative;
        box-shadow: 0 20px 45px -25px rgba(4, 42, 95, 0.55);
    }

    .dashboard-brand-mark {
        width: 76px;
        height: 76px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.7rem;
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.22);
        box-shadow: 0 16px 30px -22px rgba(4, 42, 95, 0.65);
        backdrop-filter: blur(12px);
    }

    .dashboard-brand-mark img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
    }

    .hero-kicker {
        letter-spacing: 0.34em;
        text-transform: uppercase;
        font-size: 0.72rem;
        opacity: 0.8;
    }

    .dashboard-hero {
        background: linear-gradient(135deg, #053b82 0%, #0857c3 52%, #307fe2 100%);
        border-radius: 1rem;
        color: #fff;
        overflow: hidden;
        position: relative;
    }

    .metric-card {
        border: 0;
        border-radius: 1rem;
        overflow: hidden;
        box-shadow: 0 18px 35px -28px rgba(4, 42, 95, 0.38);
        border: 1px solid rgba(8, 87, 195, 0.12);
    }

    .metric-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .soft-panel {
        border: 1px solid rgba(8, 87, 195, 0.1);
        border-radius: 1rem;
        box-shadow: 0 16px 34px -26px rgba(4, 42, 95, 0.3);
    }

    .progress-thin {
        height: 9px;
        border-radius: 999px;
        background-color: #dce8fb;
    }

    .progress-thin .progress-bar {
        border-radius: 999px;
        background: linear-gradient(120deg, #0857c3, #307fe2);
    }

    .activity-item + .activity-item {
        border-top: 1px solid #eef2f7;
    }

    .priority-box {
        border: 1px solid rgba(8, 87, 195, 0.14);
        border-radius: 0.9rem;
        background: linear-gradient(180deg, #ffffff 0%, #f4f9ff 100%);
    }

    .live-report-card {
        position: relative;
        overflow: hidden;
        display: block;
        min-height: 250px;
        border: 0;
        border-radius: 1.15rem;
        color: #fff;
        box-shadow: 0 20px 40px -28px rgba(4, 42, 95, 0.4);
        text-decoration: none !important;
        transition: transform 180ms ease, box-shadow 180ms ease, filter 180ms ease;
    }

    .live-report-card::before {
        content: "";
        position: absolute;
        inset: auto -40px -56px auto;
        width: 180px;
        height: 180px;
        border-radius: 999px;
        opacity: 0.18;
        pointer-events: none;
        background: rgba(255, 255, 255, 0.3);
    }

    .live-report-card::after {
        content: "";
        position: absolute;
        inset: 18px 18px auto auto;
        width: 12px;
        height: 12px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.55);
        box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.35);
        animation: report-pulse 1.8s infinite;
    }

    .live-report-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 24px 48px -28px rgba(4, 42, 95, 0.48);
        color: #fff;
        filter: saturate(1.05);
    }

    .live-report-card .report-eyebrow {
        letter-spacing: 0.18em;
        text-transform: uppercase;
        font-size: 0.68rem;
        opacity: 0.82;
    }

    .live-report-card .report-value {
        font-size: 1.95rem;
        line-height: 1.05;
    }

    .live-report-card .report-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.42rem 0.75rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.14);
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .live-report-card .report-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .live-report-card .report-link {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        color: #fff;
        font-weight: 700;
        font-size: 0.85rem;
    }

    .tone-primary {
        background: linear-gradient(145deg, #053b82 0%, #0857c3 52%, #307fe2 100%);
    }

    .tone-info {
        background: linear-gradient(145deg, #0a4f68 0%, #1177a3 56%, #18a8c8 100%);
    }

    .tone-success {
        background: linear-gradient(145deg, #134e4a 0%, #0f766e 54%, #14b8a6 100%);
    }

    .digital-section {
        background: linear-gradient(180deg, #ffffff 0%, #f5f9ff 100%);
        border: 1px solid rgba(8, 87, 195, 0.08);
        border-radius: 1rem;
        box-shadow: 0 18px 36px -26px rgba(4, 42, 95, 0.28);
    }

    .digital-card {
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        min-height: 282px;
        border-radius: 1.2rem;
        color: #fff;
        padding: 1.2rem;
        text-decoration: none !important;
        transition: transform 180ms ease, box-shadow 180ms ease, filter 180ms ease;
        box-shadow: 0 22px 45px -30px rgba(4, 42, 95, 0.5);
        background: linear-gradient(145deg, var(--card-start, #0b5ed7), var(--card-end, #0f8ce9));
    }

    .digital-card::before,
    .digital-card::after {
        content: "";
        position: absolute;
        border-radius: 999px;
        pointer-events: none;
    }

    .digital-card::before {
        width: 180px;
        height: 180px;
        right: -68px;
        top: -62px;
        background: rgba(255, 255, 255, 0.14);
    }

    .digital-card::after {
        width: 120px;
        height: 120px;
        right: 24px;
        bottom: -64px;
        background: rgba(255, 255, 255, 0.08);
    }

    .digital-card:hover {
        transform: translateY(-4px);
        filter: saturate(1.05);
        color: #fff;
        box-shadow: 0 26px 52px -30px rgba(4, 42, 95, 0.62);
    }

    .digital-card__eyebrow {
        letter-spacing: 0.2em;
        text-transform: uppercase;
        font-size: 0.68rem;
        opacity: 0.8;
    }

    .digital-card__badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.72rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.16);
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .digital-card__value {
        font-size: 1.9rem;
        line-height: 1.05;
        letter-spacing: -0.02em;
    }

    .digital-card__chart {
        width: 160px;
        max-width: 42%;
        margin-left: 1rem;
        opacity: 0.95;
        filter: drop-shadow(0 10px 18px rgba(0, 0, 0, 0.18));
    }

    .digital-card__chart svg {
        width: 100%;
        height: auto;
        display: block;
    }

    .digital-card__stat {
        border-radius: 0.85rem;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.12);
        padding: 0.72rem 0.8rem;
        backdrop-filter: blur(8px);
        height: 100%;
    }

    .digital-card__stat-label,
    .digital-card__footnote {
        color: rgba(255, 255, 255, 0.7);
    }

    .digital-card__footnote {
        font-size: 0.83rem;
    }

    .digital-card__link {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        font-weight: 700;
        color: #fff;
        font-size: 0.86rem;
    }

    .digital-card__trend {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.42rem 0.72rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.14);
        font-size: 0.8rem;
        font-weight: 700;
    }

    .digital-card__trend i {
        font-size: 0.8rem;
    }

    .digital-card__meta {
        color: rgba(255, 255, 255, 0.8);
    }

    .tone-edc {
        --card-start: #0a3ea1;
        --card-end: #1d87ff;
    }

    .tone-qris {
        --card-start: #08506c;
        --card-end: #12a5c3;
    }

    .tone-brimo {
        --card-start: #272e8f;
        --card-end: #39a1ff;
    }

    .tone-brilink {
        --card-start: #0d6b4d;
        --card-end: #25bf80;
    }

    .tone-payroll {
        --card-start: #8a5a00;
        --card-end: #f3a712;
    }

    @keyframes report-pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.42);
            transform: scale(1);
        }
        70% {
            box-shadow: 0 0 0 14px rgba(255, 255, 255, 0);
            transform: scale(1.05);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(255, 255, 255, 0);
            transform: scale(1);
        }
    }

    .dash-entrance {
        opacity: 0;
        transform: translateY(8px);
        transition: opacity 260ms ease, transform 320ms ease;
        will-change: opacity, transform;
    }

    .dash-entrance.is-visible {
        opacity: 1;
        transform: translateY(0);
    }

    .metric-card,
    .soft-panel,
    .priority-box {
        transition: transform 180ms ease, box-shadow 180ms ease;
    }

    .metric-card:hover,
    .soft-panel:hover,
    .priority-box:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 32px -24px rgba(4, 42, 95, 0.34);
    }

    @media (prefers-reduced-motion: reduce) {
        .dash-entrance,
        .metric-card,
        .soft-panel,
        .priority-box {
            transition: none !important;
            transform: none !important;
        }
    }
</style>

<div class="row pt-4">
    <div class="col-12">
        <div class="dashboard-hero p-4 p-md-5 mb-4 dash-entrance" data-enter-order="0">
            <div class="row align-items-center">
                <div class="col-12 mb-4">
                    <div class="d-flex align-items-center flex-wrap">
                        <div class="dashboard-brand-mark mr-4">
                            <img src="{{ $dashboardLogo }}" alt="Logo A-SIX">
                        </div>
                        <div>
                            <h1 class="mb-0 font-weight-bold" style="font-size: 2.2rem;">{{ data_get($hero, 'title', 'A-SIX') }}</h1>
                            <div class="text-white-50 mt-1" style="font-size: 1rem;">Ringkasan Posisi Keuangan Area 6 Realtime</div>
                            @if(data_get($dataQuality, 'snapshot_completeness') === 'partial')
                                <span class="badge badge-warning mt-2 px-3 py-2">
                                    Partial Data - menunggu {{ implode(', ', (array) data_get($dataQuality, 'partial_branches', [])) }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="row">
                        @forelse($heroStats as $stat)
                            <div class="col-md-6 col-lg-5 mb-3 mb-md-0">
                                <div class="p-4 h-100" style="background: rgba(255, 255, 255, 0.1); border-radius: 1rem; border: 1px solid rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px);">
                                    <div class="d-flex align-items-center mb-2 text-white-50">
                                        <i class="{{ data_get($stat, 'icon', 'fas fa-chart-line') }} mr-2"></i>
                                        <span class="text-uppercase small font-weight-bold" style="letter-spacing: 1px;">{{ data_get($stat, 'label', '-') }}</span>
                                    </div>
                                    <div class="font-weight-bold mb-2" style="font-size: 2.5rem; line-height: 1.1;">{{ data_get($stat, 'value', '-') }}</div>
                                    <div class="small">
                                        <span class="text-white-50">Posisi Update Terakhir:</span> <strong class="text-white">{{ data_get($stat, 'posisi', '-') }}</strong>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="text-white-50">Data posisi keuangan belum tersedia.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $liveReports = is_array(data_get($dashboard ?? [], 'live_reports')) ? data_get($dashboard ?? [], 'live_reports') : [];
@endphp

@php
    $digitalSectionTitle = data_get($digitalPerformance, 'title', 'Performance Digital Area 6');
    $digitalSectionSubtitle = data_get($digitalPerformance, 'subtitle', 'Snapshot realtime untuk channel digital utama Area 6.');
    $digitalSectionUpdatedAt = data_get($digitalPerformance, 'updated_at');
@endphp

<div class="row">
    <div class="col-12">
        <div class="card soft-panel mb-4 dash-entrance" data-enter-order="1">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div class="mb-2">
                        <h3 class="card-title font-weight-bold text-dark mb-1">Highlight Realtime</h3>
                        <!-- <p class="text-muted mb-0">Snapshot terbaru dari report simpanan, pinjaman, dan coverage portfolio Area 6.</p> -->
                    </div>
                    <div class="d-flex flex-wrap">
                        <span class="badge badge-light mr-2 mb-2 px-3 py-2">Live</span>
                        <span class="badge badge-light mr-2 mb-2 px-3 py-2">Auto refresh</span>
                        <span class="badge badge-light mb-2 px-3 py-2">A-SIX</span>
                    </div>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="row">
                    @forelse($liveReports as $report)
                        <div class="col-lg-4 col-md-6 mb-4">
                            <a href="{{ data_get($report, 'link', '#') }}" class="live-report-card tone-{{ data_get($report, 'tone', 'primary') }} p-4 h-100">
                                <div class="d-flex justify-content-between align-items-start mb-4">
                                    <div>
                                        <div class="report-eyebrow text-white-50 mb-2">{{ data_get($report, 'eyebrow', '-') }}</div>
                                        <div class="report-chip">
                                            <i class="{{ data_get($report, 'icon', 'fas fa-chart-bar') }}"></i>
                                            <span>{{ data_get($report, 'badge', '-') }}</span>
                                        </div>
                                    </div>
                                    <span class="badge badge-light px-3 py-2">{{ data_get($report, 'updated', '-') }}</span>
                                </div>

                                <div class="mb-4">
                                    <h4 class="font-weight-bold mb-2">{{ data_get($report, 'title', '-') }}</h4>
                                    <div class="report-value font-weight-bold mb-2">{{ data_get($report, 'value', '-') }}</div>
                                    <div class="d-flex align-items-center flex-wrap">
                                        <span class="font-weight-bold mr-2 {{ data_get($report, 'trend_class', 'text-white') }}">{{ data_get($report, 'trend', '-') }}</span>
                                        <span class="text-white-50 small">{{ data_get($report, 'meta', '-') }}</span>
                                    </div>
                                </div>

                                <div class="report-footer mt-auto pt-2">
                                    <div class="text-white-50 small" style="max-width: 62%;">{{ data_get($report, 'detail', '-') }}</div>
                                    <div class="report-link">
                                        <span>{{ data_get($report, 'link_label', 'Buka detail') }}</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="text-muted">Highlight realtime belum tersedia.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    @forelse($metrics as $metric)
        <div class="col-lg-3 col-md-6 dash-entrance" data-enter-order="{{ $loop->index + 2 }}">
            <div class="card metric-card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted text-uppercase small mb-2">{{ data_get($metric, 'label', '-') }}</div>
                            <h3 class="font-weight-bold mb-1">{{ data_get($metric, 'value', '-') }}</h3>
                            <p class="mb-0 {{ data_get($metric, 'delta_class', 'text-muted') }} small">{{ data_get($metric, 'delta', '-') }}</p>
                        </div>
                        <span class="metric-icon {{ data_get($metric, 'icon_class', 'text-muted') }}" style="background: {{ data_get($metric, 'icon_bg', 'rgba(108, 117, 125, 0.12)') }};">
                            <i class="{{ data_get($metric, 'icon', 'fas fa-chart-bar') }}"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="alert alert-light border mb-4">Metrik dashboard belum tersedia.</div>
        </div>
    @endforelse
</div>

<div class="row">
    <div class="col-12">
        <div class="card digital-section mb-4 dash-entrance" data-enter-order="6">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div class="mb-2">
                        <h3 class="card-title font-weight-bold text-dark mb-1">{{ $digitalSectionTitle }}</h3>
                        <p class="text-muted mb-0">{{ $digitalSectionSubtitle }}</p>
                    </div>
                    <div class="d-flex flex-wrap align-items-center">
                        <span class="badge badge-light mr-2 mb-2 px-3 py-2">Live</span>
                        <span class="badge badge-light mr-2 mb-2 px-3 py-2">Interactive</span>
                        <span class="badge badge-light mb-2 px-3 py-2">{{ $digitalSectionUpdatedAt ? 'Updated ' . $digitalSectionUpdatedAt . ' WIB' : 'Updated data belum tersedia' }}</span>
                    </div>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="row">
                    @forelse($digitalCards as $digitalCard)
                        @php
                            $cardSeriesPoints = is_array(data_get($digitalCard, 'chart.points')) ? data_get($digitalCard, 'chart.points') : [];
                            $cardSeriesLabels = is_array(data_get($digitalCard, 'series_labels')) ? data_get($digitalCard, 'series_labels') : [];
                            $trendValue = (float) data_get($digitalCard, 'trend_value', 0);
                            $trendIcon = $trendValue >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                        @endphp
                        <div class="col-xl-4 col-lg-6 mb-4">
                            <a href="{{ data_get($digitalCard, 'link', '#') }}" class="digital-card tone-{{ data_get($digitalCard, 'key', 'edc') }}">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="mr-3">
                                        <div class="digital-card__eyebrow text-white-50 mb-2">{{ data_get($digitalCard, 'badge', '-') }}</div>
                                        <span class="digital-card__badge">
                                            <i class="{{ data_get($digitalCard, 'icon', 'fas fa-chart-line') }}"></i>
                                            <span>{{ data_get($digitalCard, 'current_label', '-') }}</span>
                                        </span>
                                    </div>
                                    <span class="digital-card__trend">
                                        <i class="fas {{ $trendIcon }}"></i>
                                        <span>{{ data_get($digitalCard, 'trend', '0,0%') }}</span>
                                    </span>
                                </div>

                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="pr-2">
                                        <h4 class="font-weight-bold mb-1">{{ data_get($digitalCard, 'title', '-') }}</h4>
                                        <p class="digital-card__meta mb-2">{{ data_get($digitalCard, 'subtitle', '-') }}</p>
                                        <div class="digital-card__value font-weight-bold">{{ data_get($digitalCard, 'current_value', '-') }}</div>
                                        <div class="text-white-50 small">{{ data_get($digitalCard, 'secondary_label', '-') }}: {{ data_get($digitalCard, 'secondary_value', '-') }}</div>
                                    </div>
                                    <div class="digital-card__chart">
                                        <svg viewBox="0 0 160 48" preserveAspectRatio="none" aria-hidden="true">
                                            <path d="{{ data_get($digitalCard, 'chart.area_path', '') }}" fill="rgba(255,255,255,0.14)"></path>
                                            <path d="{{ data_get($digitalCard, 'chart.path', '') }}" fill="none" stroke="rgba(255,255,255,0.95)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path>
                                            @foreach($cardSeriesPoints as $point)
                                                <circle cx="{{ data_get($point, 'x', 0) }}" cy="{{ data_get($point, 'y', 0) }}" r="3.6" fill="rgba(255,255,255,0.95)" opacity="0.95"></circle>
                                            @endforeach
                                        </svg>
                                    </div>
                                </div>

                                <div class="row no-gutters mb-3">
                                    @forelse(array_slice(data_get($digitalCard, 'stats', []), 0, 3) as $stat)
                                        <div class="col-4 pr-2">
                                            <div class="digital-card__stat">
                                                <div class="digital-card__stat-label small mb-1">{{ data_get($stat, 'label', '-') }}</div>
                                                <div class="font-weight-bold">{{ data_get($stat, 'value', '-') }}</div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="col-12 text-white-50">Belum ada statistik digital.</div>
                                    @endforelse
                                </div>

                                <div class="d-flex justify-content-between align-items-end mt-auto flex-wrap">
                                    <div class="digital-card__footnote mr-3 mb-2">
                                        <div>{{ data_get($digitalCard, 'trend_reference', '-') }}</div>
                                        <div class="small text-white-50">
                                            {{ $cardSeriesLabels ? implode(' - ', array_slice($cardSeriesLabels, max(0, count($cardSeriesLabels) - 2), 2)) : 'Series tidak tersedia' }}
                                        </div>
                                    </div>
                                    <div class="digital-card__link mb-2">
                                        <span>{{ data_get($digitalCard, 'link_label', 'Buka report') }}</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="text-muted">Performance digital belum tersedia.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Removed Bloat UI -->
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const nodes = document.querySelectorAll('.dash-entrance');

        nodes.forEach(function (node) {
            if (reducedMotion) {
                node.classList.add('is-visible');
                return;
            }

            const order = Number(node.getAttribute('data-enter-order') || 0);
            window.setTimeout(function () {
                node.classList.add('is-visible');
            }, Math.min(order * 45, 360));
        });
    });
</script>
@endsection
