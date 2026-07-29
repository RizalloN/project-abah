@extends('layouts.admin')

@section('title', 'Run OFF')

@section('content')
@php
    $formatCount = static fn (int $value): string => $value === 0 ? '-' : number_format($value, 0, ',', '.');
    $formatAmount = static function (int $cents): string {
        if ($cents === 0) {
            return '-';
        }

        return number_format($cents / 100000000, 0, ',', '.');
    };
    $baselineLabel = $baseline_period ? \Carbon\Carbon::parse($baseline_period)->translatedFormat('d M Y') : '-';
    $latestLabel = $latest_period ? \Carbon\Carbon::parse($latest_period)->translatedFormat('d M Y') : '-';
@endphp

<style>
    .runoff-page { padding: 1.25rem; }
    .runoff-hero, .runoff-table-card { background: #fff; border: 1px solid #dbe6f3; border-radius: 16px; box-shadow: 0 16px 34px -28px rgba(15, 23, 42, .5); }
    .runoff-hero { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.15rem 1.3rem; margin-bottom: 1rem; }
    .runoff-title { margin: 0; color: #102a56; font-size: 1.25rem; font-weight: 800; }
    .runoff-subtitle { margin: .3rem 0 0; color: #64748b; font-size: .85rem; font-weight: 600; }
    .runoff-actions { display: flex; gap: .55rem; align-items: center; flex-wrap: wrap; }
    .runoff-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .35rem .7rem; border-radius: 999px; background: #e8f1ff; color: #135eb3; font-size: .78rem; font-weight: 800; white-space: nowrap; }
    .runoff-refresh { display: inline-flex; align-items: center; gap: .4rem; border: 0; border-radius: 10px; padding: .58rem .8rem; background: #0f5fb8; color: #fff; font-size: .8rem; font-weight: 800; }
    .runoff-table-card { overflow: auto; }
    .runoff-table { --runoff-category-width: 180px; --runoff-branch-width: 135px; width: 100%; min-width: 1100px; margin: 0; border-collapse: separate; border-spacing: 0; }
    .runoff-table th, .runoff-table td { padding: .72rem .8rem; border-right: 1px solid #dbe6f3; border-bottom: 1px solid #dbe6f3; vertical-align: middle; }
    .runoff-table thead th { position: sticky; top: var(--abah-table-head-top, 0px); z-index: 5; text-align: center; }
    .runoff-table thead tr:first-child th { background: #11386f; color: #fff; font-size: .78rem; text-transform: uppercase; letter-spacing: .035em; }
    .runoff-table thead tr:last-child th { top: var(--abah-table-head-top, 0px); background: #eaf2fc; color: #25466f; font-size: .76rem; }
    .runoff-table thead small { display: block; margin-top: .22rem; color: #cfe1fa; font-size: .66rem; font-weight: 700; text-transform: none; letter-spacing: 0; }
    .runoff-table tbody td { color: #1e293b; font-weight: 600; }
    .runoff-table .runoff-number { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .runoff-table .runoff-category { position: sticky; left: 0; z-index: 20; width: var(--runoff-category-width); min-width: var(--runoff-category-width); max-width: var(--runoff-category-width); background: #fff; }
    .runoff-table .runoff-branch { position: sticky; left: var(--runoff-category-width); z-index: 20; width: var(--runoff-branch-width); min-width: var(--runoff-branch-width); max-width: var(--runoff-branch-width); background: #fff; }
    .runoff-table thead .runoff-category, .runoff-table thead .runoff-branch { z-index: 55 !important; }
    .runoff-table tr.runoff-summary td { background: #edf4fc; color: #12396e; font-weight: 800; }
    .runoff-table tr.runoff-summary .runoff-category, .runoff-table tr.runoff-summary .runoff-branch { background: #edf4fc; }
    .runoff-table tr.runoff-product .runoff-category { padding-left: 1.35rem; }
    .runoff-negative { color: #b42318 !important; }
    .runoff-empty { padding: 3rem 1rem; text-align: center; color: #64748b; font-weight: 600; }
    @media (max-width: 768px) { .runoff-page { padding: .8rem; } .runoff-hero { align-items: flex-start; flex-direction: column; } }
</style>

<div class="runoff-page">
    <section class="runoff-hero">
        <div>
            <h1 class="runoff-title">{{ $title }}</h1>
            <p class="runoff-subtitle">{{ $scopeLabel }} · laporan {{ $report_month ?: '-' }} · posisi terbaru {{ $latestLabel }}</p>
        </div>
        <div class="runoff-actions">
            <span class="runoff-chip"><i class="fas fa-database"></i> Daily Loan Dinamis</span>
            @if($isBranchScoped)
                <span class="runoff-chip"><i class="fas fa-lock"></i> Cabang terkunci</span>
            @endif
            <a href="{{ route('report.dashboard-pinjaman.run-off', ['refresh' => 1]) }}" class="runoff-refresh">
                <i class="fas fa-sync-alt"></i> Perbarui
            </a>
        </div>
    </section>

    @if($error)
        <div class="alert alert-warning">{{ $error }}</div>
    @endif

    <section class="runoff-table-card">
        @if(count($rows))
            <table class="runoff-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="runoff-category">Segmen / Produk</th>
                        <th rowspan="2" class="runoff-branch">Cabang</th>
                        <th colspan="2">Run OFF Total<small>Posisi {{ $baselineLabel }}</small></th>
                        <th colspan="2">Sisa Run OFF<small>Posisi {{ $latestLabel }}</small></th>
                        <th colspan="2">Sudah Bayar<small>Total dikurangi sisa</small></th>
                    </tr>
                    <tr>
                        <th>Rek</th>
                        <th>Run OFF</th>
                        <th>Rek</th>
                        <th>Run OFF</th>
                        <th>Rek</th>
                        <th>Run OFF</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr class="{{ $row['is_summary'] ? 'runoff-summary' : '' }} {{ $row['level'] === 'product' ? 'runoff-product' : '' }}">
                            <td class="runoff-category">{{ $row['category'] }}</td>
                            <td class="runoff-branch">{{ $row['branch'] }}</td>
                            <td class="runoff-number">{{ $formatCount($row['baseline_accounts']) }}</td>
                            <td class="runoff-number {{ $row['baseline_amount_cents'] < 0 ? 'runoff-negative' : '' }}">{{ $formatAmount($row['baseline_amount_cents']) }}</td>
                            <td class="runoff-number">{{ $formatCount($row['remaining_accounts']) }}</td>
                            <td class="runoff-number {{ $row['remaining_amount_cents'] < 0 ? 'runoff-negative' : '' }}">{{ $formatAmount($row['remaining_amount_cents']) }}</td>
                            <td class="runoff-number {{ $row['paid_accounts'] < 0 ? 'runoff-negative' : '' }}">{{ $formatCount($row['paid_accounts']) }}</td>
                            <td class="runoff-number {{ $row['paid_amount_cents'] < 0 ? 'runoff-negative' : '' }}">{{ $formatAmount($row['paid_amount_cents']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="runoff-empty">Belum ada data Run OFF untuk ditampilkan.</div>
        @endif
    </section>
</div>
@endsection
