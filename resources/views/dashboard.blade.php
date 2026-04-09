@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
@php
    $hero = data_get($dashboard ?? [], 'hero', []);
    $health = data_get($dashboard ?? [], 'health', []);
    $metrics = data_get($dashboard ?? [], 'metrics', []);
    $performance = data_get($dashboard ?? [], 'performance', []);
    $priorities = data_get($dashboard ?? [], 'priorities', []);
    $activities = data_get($dashboard ?? [], 'activities', []);
    $agenda = data_get($dashboard ?? [], 'agenda', []);

    $heroStats = is_array(data_get($hero, 'stats')) ? data_get($hero, 'stats') : [];
    $healthItems = is_array(data_get($health, 'items')) ? data_get($health, 'items') : [];
    $performanceBars = is_array(data_get($performance, 'bars')) ? data_get($performance, 'bars') : [];

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

    .dashboard-hero::before,
    .dashboard-hero::after {
        content: "";
        position: absolute;
        border-radius: 999px;
        opacity: 0.18;
        pointer-events: none;
    }

    .dashboard-hero::before {
        width: 280px;
        height: 280px;
        background: #71c5e8;
        top: -90px;
        right: -80px;
    }

    .dashboard-hero::after {
        width: 220px;
        height: 220px;
        background: #307fe2;
        bottom: -110px;
        left: -70px;
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

<div class="row">
    <div class="col-12">
        <div class="dashboard-hero p-4 p-md-5 mb-4 dash-entrance" data-enter-order="0">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge badge-light px-3 py-2 text-uppercase" style="letter-spacing: 0.12em;">{{ data_get($hero, 'badge', 'Area 6 Overview') }}</span>
                    <h1 class="mt-3 mb-2 font-weight-bold" style="font-size: 2.35rem; line-height: 1.15;">
                        {{ data_get($hero, 'title', 'Dashboard Simpanan') }}
                    </h1>
                    <p class="mb-4 text-white-50" style="max-width: 700px; font-size: 1rem;">
                        {{ data_get($hero, 'subtitle', 'Ringkasan dashboard belum tersedia.') }}
                    </p>
                    <div class="d-flex flex-wrap">
                        @forelse($heroStats as $stat)
                            <div class="mr-4 mb-3">
                                <div class="text-white-50 text-uppercase small">{{ data_get($stat, 'label', '-') }}</div>
                                <div class="h3 mb-0 font-weight-bold">{{ data_get($stat, 'value', '-') }}</div>
                            </div>
                        @empty
                            <div class="mr-4 mb-3">
                                <div class="text-white-50 text-uppercase small">Total Saldo</div>
                                <div class="h3 mb-0 font-weight-bold">Rp0</div>
                            </div>
                        @endforelse
                        <div class="mb-3">
                            <div class="text-white-50 text-uppercase small">Update Terakhir</div>
                            <div class="h3 mb-0 font-weight-bold">{{ data_get($hero, 'updated_label', 'Belum ada data') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mt-4 mt-lg-0">
                    <div class="bg-white p-4" style="border-radius: 1rem; color: #0f172a;">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <div class="text-muted text-uppercase small">Komposisi Simpanan</div>
                                <h4 class="font-weight-bold mb-0">{{ data_get($health, 'title', 'Menunggu Data') }}</h4>
                            </div>
                            <span class="badge {{ data_get($health, 'badge_class', 'badge-secondary') }} px-3 py-2">{{ data_get($health, 'badge', 'Pending') }}</span>
                        </div>
                        <div class="progress progress-thin mb-3" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $healthProgress }}">
                            <div class="progress-bar" style="width: {{ $healthProgress }}%"></div>
                        </div>
                        <div class="row text-center">
                            @forelse($healthItems as $item)
                                <div class="col-4">
                                    <div class="text-muted small">{{ data_get($item, 'label', '-') }}</div>
                                    <div class="font-weight-bold">{{ data_get($item, 'value', '-') }}</div>
                                </div>
                            @empty
                                <div class="col-12">
                                    <div class="text-muted small">Belum ada komposisi</div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    @forelse($metrics as $metric)
        <div class="col-lg-3 col-md-6 dash-entrance" data-enter-order="{{ $loop->index + 1 }}">
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
    <div class="col-lg-8">
        <div class="card soft-panel mb-4 dash-entrance" data-enter-order="5">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h3 class="card-title font-weight-bold text-dark mb-1">{{ data_get($performance, 'title', 'Performa Simpanan') }}</h3>
                        <p class="text-muted mb-0">{{ data_get($performance, 'subtitle', 'Ringkasan performa belum tersedia.') }}</p>
                    </div>
                    @php
                        $performanceUpdatedAt = data_get($performance, 'updated_at');
                    @endphp
                    <span class="badge badge-light px-3 py-2">
                        {{ $performanceUpdatedAt ? 'Updated ' . $performanceUpdatedAt . ' WIB' : 'Updated data belum tersedia' }}
                    </span>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                @forelse($performanceBars as $bar)
                    @php
                        $barValue = min(100, max(0, (float) data_get($bar, 'value', 0)));
                    @endphp
                    <div class="{{ $loop->last ? '' : 'mb-4' }}">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="font-weight-bold">{{ data_get($bar, 'label', '-') }}</span>
                            <span class="text-muted">{{ data_get($bar, 'display', '0,0%') }}</span>
                        </div>
                        <div class="progress progress-thin">
                            <div class="progress-bar {{ data_get($bar, 'class', 'bg-secondary') }}" style="width: {{ $barValue }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted">Belum ada data performa.</div>
                @endforelse
            </div>
        </div>

        <div class="card soft-panel mb-4 dash-entrance" data-enter-order="6">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h3 class="card-title font-weight-bold text-dark">Prioritas Hari Ini</h3>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="row">
                    @forelse($priorities as $priority)
                        <div class="col-md-4 {{ $loop->first ? '' : 'mt-3 mt-md-0' }}">
                            <div class="priority-box p-3 h-100">
                                <span class="badge {{ data_get($priority, 'badge_class', 'badge-secondary') }} mb-3">{{ data_get($priority, 'badge', '-') }}</span>
                                <h5 class="font-weight-bold">{{ data_get($priority, 'title', '-') }}</h5>
                                <p class="text-muted mb-0">{{ data_get($priority, 'text', '-') }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="text-muted">Belum ada prioritas.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card soft-panel mb-4 dash-entrance" data-enter-order="7">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h3 class="card-title font-weight-bold text-dark">Aktivitas Terbaru</h3>
            </div>
            <div class="card-body px-0 py-0">
                @forelse($activities as $activity)
                    <div class="activity-item px-4 py-3">
                        <div class="d-flex align-items-start">
                            <span class="badge {{ data_get($activity, 'class', 'badge-secondary') }} mr-3 mt-1">&nbsp;</span>
                            <div>
                                <div class="font-weight-bold">{{ data_get($activity, 'title', '-') }}</div>
                                <div class="text-muted small">{{ data_get($activity, 'time', '-') }}</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="activity-item px-4 py-3">
                        <div class="text-muted small">Belum ada aktivitas terbaru.</div>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="card soft-panel mb-4 dash-entrance" data-enter-order="8">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h3 class="card-title font-weight-bold text-dark">Agenda Tim</h3>
            </div>
            <div class="card-body px-4 pb-4">
                @forelse($agenda as $item)
                    <div class="d-flex justify-content-between align-items-center {{ $loop->last ? '' : 'mb-3' }}">
                        <div>
                            <div class="font-weight-bold">{{ data_get($item, 'title', '-') }}</div>
                            <div class="text-muted small">{{ data_get($item, 'time', '-') }}</div>
                        </div>
                        <span class="badge badge-light">{{ data_get($item, 'tag', '-') }}</span>
                    </div>
                @empty
                    <div class="text-muted small">Belum ada agenda tim.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
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
