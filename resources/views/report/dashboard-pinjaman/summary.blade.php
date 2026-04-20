@extends('layouts.admin')

@section('title', 'Dashboard Pinjaman Kredit')

@section('content')
@include('report.dashboard-pinjaman._partials._styles')

<div class="loan-dashboard pt-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h1 class="loan-page-title">Dashboard Pinjaman Kredit</h1>
            <p class="text-muted mb-0">Ringkasan performa portofolio pinjaman kredit.</p>
        </div>
    </div>

    @php
        // Dummy data removed - use kredit dashboard instead
        $dummySummaryData = [];
    @endphp

    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <h4 class="alert-heading"><i class="fas fa-info-circle"></i> Dashboard Baru</h4>
        <p class="mb-0">Dashboard Pinjaman Kredit dengan SME segment analysis telah tersedia dengan fitur yang lebih komprehensif.</p>
        <hr>
        <a href="{{ route('report.dashboard-pinjaman.kredit') }}" class="btn btn-primary btn-sm mt-2">
            <i class="fas fa-arrow-right"></i> Buka Dashboard SME Kredit Terbaru
        </a>
    </div>
</div>


@endsection
