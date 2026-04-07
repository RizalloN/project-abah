@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
@php
    $hero = $dashboard['hero'];
    $health = $dashboard['health'];
    $metrics = $dashboard['metrics'];
    $performance = $dashboard['performance'];
    $priorities = $dashboard['priorities'];
    $activities = $dashboard['activities'];
    $agenda = $dashboard['agenda'];
@endphp
<style>
    .dashboard-hero {
        background: linear-gradient(135deg, #0f172a 0%, #164e63 48%, #0f766e 100%);
        border-radius: 1rem;
        color: #fff;
        overflow: hidden;
        position: relative;
        box-shadow: 0 20px 45px -25px rgba(15, 23, 42, 0.55);
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
        background: #67e8f9;
        top: -90px;
        right: -80px;
    }

    .dashboard-hero::after {
        width: 220px;
        height: 220px;
        background: #fbbf24;
        bottom: -110px;
        left: -70px;
    }

    .metric-card {
        border: 0;
        border-radius: 1rem;
        overflow: hidden;
        box-shadow: 0 18px 35px -28px rgba(15, 23, 42, 0.45);
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
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 16px 34px -26px rgba(15, 23, 42, 0.35);
    }

    .progress-thin {
        height: 9px;
        border-radius: 999px;
        background-color: #e9ecef;
    }

    .progress-thin .progress-bar {
        border-radius: 999px;
    }

    .activity-item + .activity-item {
        border-top: 1px solid #eef2f7;
    }
</style>

<div class="row">
    <div class="col-12">
        <div class="dashboard-hero p-4 p-md-5 mb-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge badge-light px-3 py-2 text-uppercase" style="letter-spacing: 0.12em;">{{ $hero['badge'] }}</span>
                    <h1 class="mt-3 mb-2 font-weight-bold" style="font-size: 2.35rem; line-height: 1.15;">
                        {{ $hero['title'] }}
                    </h1>
                    <p class="mb-4 text-white-50" style="max-width: 700px; font-size: 1rem;">
                        {{ $hero['subtitle'] }}
                    </p>
                    <div class="d-flex flex-wrap">
                        @foreach($hero['stats'] as $stat)
                            <div class="mr-4 mb-3">
                                <div class="text-white-50 text-uppercase small">{{ $stat['label'] }}</div>
                                <div class="h3 mb-0 font-weight-bold">{{ $stat['value'] }}</div>
                            </div>
                        @endforeach
                        <div class="mb-3">
                            <div class="text-white-50 text-uppercase small">Update Terakhir</div>
                            <div class="h3 mb-0 font-weight-bold">{{ $hero['updated_label'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mt-4 mt-lg-0">
                    <div class="bg-white p-4" style="border-radius: 1rem; color: #0f172a;">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <div class="text-muted text-uppercase small">Komposisi Simpanan</div>
                                <h4 class="font-weight-bold mb-0">{{ $health['title'] }}</h4>
                            </div>
                            <span class="badge {{ $health['badge_class'] }} px-3 py-2">{{ $health['badge'] }}</span>
                        </div>
                        <div class="progress progress-thin mb-3">
                            <div class="progress-bar bg-success" style="width: {{ min(100, max(0, $health['progress'])) }}%"></div>
                        </div>
                        <div class="row text-center">
                            @foreach($health['items'] as $item)
                                <div class="col-4">
                                    <div class="text-muted small">{{ $item['label'] }}</div>
                                    <div class="font-weight-bold">{{ $item['value'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    @foreach($metrics as $metric)
        <div class="col-lg-3 col-md-6">
            <div class="card metric-card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted text-uppercase small mb-2">{{ $metric['label'] }}</div>
                            <h3 class="font-weight-bold mb-1">{{ $metric['value'] }}</h3>
                            <p class="mb-0 {{ $metric['delta_class'] }} small">{{ $metric['delta'] }}</p>
                        </div>
                        <span class="metric-icon {{ $metric['icon_class'] }}" style="background: {{ $metric['icon_bg'] }};">
                            <i class="{{ $metric['icon'] }}"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card soft-panel mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h3 class="card-title font-weight-bold text-dark mb-1">{{ $performance['title'] }}</h3>
                        <p class="text-muted mb-0">{{ $performance['subtitle'] }}</p>
                    </div>
                    <span class="badge badge-light px-3 py-2">
                        {{ $performance['updated_at'] ? 'Updated ' . $performance['updated_at'] . ' WIB' : 'Updated data belum tersedia' }}
                    </span>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                @foreach($performance['bars'] as $bar)
                    <div class="{{ $loop->last ? '' : 'mb-4' }}">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="font-weight-bold">{{ $bar['label'] }}</span>
                            <span class="text-muted">{{ $bar['display'] }}</span>
                        </div>
                        <div class="progress progress-thin">
                            <div class="progress-bar {{ $bar['class'] }}" style="width: {{ min(100, max(0, $bar['value'])) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card soft-panel mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h3 class="card-title font-weight-bold text-dark">Prioritas Hari Ini</h3>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="row">
                    @foreach($priorities as $priority)
                        <div class="col-md-4 {{ $loop->first ? '' : 'mt-3 mt-md-0' }}">
                            <div class="border rounded-lg p-3 h-100">
                                <span class="badge {{ $priority['badge_class'] }} mb-3">{{ $priority['badge'] }}</span>
                                <h5 class="font-weight-bold">{{ $priority['title'] }}</h5>
                                <p class="text-muted mb-0">{{ $priority['text'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card soft-panel mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h3 class="card-title font-weight-bold text-dark">Aktivitas Terbaru</h3>
            </div>
            <div class="card-body px-0 py-0">
                @foreach($activities as $activity)
                    <div class="activity-item px-4 py-3">
                        <div class="d-flex align-items-start">
                            <span class="badge {{ $activity['class'] }} mr-3 mt-1">&nbsp;</span>
                            <div>
                                <div class="font-weight-bold">{{ $activity['title'] }}</div>
                                <div class="text-muted small">{{ $activity['time'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card soft-panel mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h3 class="card-title font-weight-bold text-dark">Agenda Tim</h3>
            </div>
            <div class="card-body px-4 pb-4">
                @foreach($agenda as $item)
                    <div class="d-flex justify-content-between align-items-center {{ $loop->last ? '' : 'mb-3' }}">
                        <div>
                            <div class="font-weight-bold">{{ $item['title'] }}</div>
                            <div class="text-muted small">{{ $item['time'] }}</div>
                        </div>
                        <span class="badge badge-light">{{ $item['tag'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
