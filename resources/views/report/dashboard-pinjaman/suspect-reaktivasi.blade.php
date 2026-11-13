@extends('layouts.admin')
@section('title', 'Suspect Reaktivas')
@section('styles')
<style>{!! file_get_contents(resource_path('css/suspect-reaktivasi.css')) !!}</style>
@endsection
@section('content')
@php
    $dateLabel = fn ($value) => $value ? \Carbon\Carbon::parse($value)->translatedFormat('d M Y') : 'Belum tersedia';
@endphp
<div class="sr-page">
    <header class="sr-hero">
        <div class="sr-heading-icon"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i></div>
        <div class="sr-heading-copy"><div class="sr-eyebrow">DASHBOARD PINJAMAN <span>/</span> {{ $scope['label'] ?? 'AREA 6' }}</div><h1>Suspect Reaktivas</h1><p>Telusuri frekuensi reaktivasi, outstanding, dan riwayat rekening dalam satu tampilan.</p></div>
        <span class="sr-monitor-label"><span></span> Monitoring pinjaman</span>
    </header>
    <form class="sr-filters" method="GET" id="suspect-filters" action="{{ route('report.dashboard-pinjaman.suspect-reaktivasi') }}">
        <div class="sr-filter-heading"><div><i class="fas fa-sliders-h" aria-hidden="true"></i><strong>Sesuaikan tampilan</strong></div><span>Filter berlaku untuk seluruh ringkasan dan tabel</span></div>
        <div class="sr-filter-grid">
            <div class="sr-field"><label for="sr-period" id="sr-period-label">Periode laporan</label><select id="sr-period" name="periode">@forelse($periods as $option)<option value="{{ $option }}" @selected($period === $option)>{{ $dateLabel($option) }}</option>@empty<option value="">Belum ada periode</option>@endforelse</select></div>
            <div class="sr-field"><label for="sr-branch" id="sr-branch-label">Cabang</label><select id="sr-branch" name="cabang">@if(!$scope)<option value="">Semua cabang Area 6</option>@endif @foreach($branches as $option)@if(!$scope || $option === $scope['label'])<option value="{{ $option }}" @selected($branch === $option)>{{ $option }}</option>@endif @endforeach</select></div>
            <div class="sr-field"><label for="sr-unit" id="sr-unit-label">Nama uker</label><select id="sr-unit" name="nama_uker"><option value="">Semua uker</option>@foreach($units as $option)<option value="{{ $option }}" @selected($unit === $option)>{{ $option }}</option>@endforeach</select></div>
            <div class="sr-field"><label for="sr-frequency" id="sr-frequency-label">Jumlah reaktivasi</label><select id="sr-frequency" name="reaktivasi"><option value="">Semua frekuensi</option>@foreach($frequencies as $option)<option value="{{ $option['value'] }}" @selected($frequency === $option['value'])>{{ $option['label'] }} · {{ $option['count'] }} rekening</option>@endforeach</select></div>
        </div>
        <div class="sr-filter-footer"><span><i class="fas fa-info-circle" aria-hidden="true"></i> Jumlah reaktivasi mengikuti keterangan laporan sumber.</span><div><a class="sr-reset" href="{{ route('report.dashboard-pinjaman.suspect-reaktivasi', ['periode' => $period]) }}">Reset filter</a><button type="submit" class="sr-apply"><i class="fas fa-filter" aria-hidden="true"></i> Terapkan filter</button></div></div>
    </form>
    <div id="sr-error" class="sr-error" role="alert" hidden></div>
    <p id="sr-status" class="sr-status" role="status" aria-live="polite"></p>
    <div id="sr-results">@include('report.dashboard-pinjaman.suspect-reaktivasi-results')</div>
</div>
<dialog class="sr-dialog" id="sr-history-dialog" aria-labelledby="sr-dialog-title"><header><div><h2 id="sr-dialog-title">Riwayat tanggal sumber</h2><p id="sr-dialog-name"></p></div><button type="button" class="sr-close" aria-label="Tutup riwayat">✕</button></header><p>Tanggal ditampilkan mengikuti nilai pada 12 kolom bulan laporan dalam dokumen sumber.</p><div class="sr-history-grid" id="sr-history-grid"></div></dialog>
<script src="{{ asset('js/suspect-reaktivasi.js') }}?v={{ filemtime(public_path('js/suspect-reaktivasi.js')) }}" defer></script>
@endsection
