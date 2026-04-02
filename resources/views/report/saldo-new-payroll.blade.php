@extends('layouts.admin')

@section('title', 'Saldo New Payroll')

@section('content')

<style>
    .report-filter-card,
    .report-data-card {
        border: 1px solid #e9ecef;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 0.5rem 1rem rgba(15, 23, 42, 0.08) !important;
    }
    .report-filter-card .card-body,
    .report-data-card .card-header,
    .report-data-card .card-body {
        background-color: #ffffff;
    }
    .report-filter-card .form-control {
        border-radius: 10px;
        min-height: 40px;
    }
    .table-container { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .table-report {
        border-collapse: collapse;
        width: 100%;
        table-layout: auto;
        white-space: nowrap;
    }
    .table-report th, .table-report td {
        vertical-align: middle !important;
        border: 1px solid #dee2e6;
    }
    .table-report th { font-size: 0.65rem; padding: 10px 6px; text-align: center; }
    .table-report td { font-size: 0.70rem; padding: 6px 8px; text-align: right; }
    .table-report td.text-left { text-align: left; }
    .bg-header-main { background-color: #0056b3 !important; color: #ffffff !important; border-color: #004085 !important; }
    .bg-header-sub { background-color: #8fb5df !important; color: #102a43 !important; font-weight: bold; border-color: #7ea7d3 !important; }
    .col-block { background-color: #dbe9f8; }
    .table-hover tbody tr:hover { background-color: #f1f7ff; }
    .row-total { background-color: #7ba7e6 !important; color: #102a43 !important; font-weight: bold; }
    .row-total td { font-weight: bold; }
    .val-up { color: #2e8b57; font-weight: bold; margin-right: 4px; }
    .text-negative { color: #ff0000; }
    .nav-tabs.report-tabs { border-bottom: 2px solid #dee2e6; flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; white-space: nowrap; scrollbar-width: thin; }
    .nav-tabs.report-tabs .nav-link { border: none; font-weight: 600; color: #6c757d; padding: 12px 18px; font-size: 0.95rem; background: transparent; }
    .nav-tabs.report-tabs .nav-link.active { border-bottom: 3px solid #007bff; color: #007bff; background: transparent; }
    .nav-tabs.report-tabs .nav-link:hover { border-bottom: 3px solid #9ec5fe; color: #007bff; background: transparent; }
</style>

@php
    $rows = [
        ['REGION 1 / MEDAN', '7,033', '2,758', '8,068', '1,035', '15%', '5,310', '193%'],
        ['REGION 2 / PEKANBARU', '8,973', '707', '2,089', '(6,884)', '-77%', '1,382', '196%'],
        ['REGION 3 / PADANG', '21,308', '590', '7,450', '(13,859)', '-65%', '6,860', '1163%'],
        ['REGION 4 / PALEMBANG', '10,029', '1,594', '9,896', '(133)', '-1%', '8,302', '521%'],
        ['REGION 5 / BANDAR LAMPUNG', '2,748', '1,425', '2,649', '(98)', '-4%', '1,224', '86%'],
        ['REGION 6 / JAKARTA 1', '17,552', '5,096', '7,975', '(9,577)', '-55%', '2,879', '56%'],
        ['REGION 7 / JAKARTA 2', '18,773', '2,102', '5,880', '(12,893)', '-69%', '3,778', '180%'],
        ['REGION 8 / JAKARTA 3', '10,039', '2,573', '6,150', '(3,889)', '-39%', '3,578', '139%'],
        ['REGION 9 / BANDUNG', '13,466', '3,016', '12,152', '(1,314)', '-10%', '9,136', '303%'],
        ['REGION 10 / SEMARANG', '19,785', '5,005', '29,824', '10,039', '51%', '24,818', '496%'],
        ['REGION 11 / YOGYAKARTA', '26,868', '4,497', '9,249', '(17,618)', '-66%', '4,752', '106%'],
        ['REGION 12 / SURABAYA', '15,258', '1,996', '11,503', '(3,754)', '-25%', '9,507', '476%'],
        ['REGION 13 / MALANG', '17,265', '2,389', '11,980', '(5,285)', '-31%', '9,591', '401%'],
        ['REGION 14 / BANJARMASIN', '10,300', '6,111', '11,552', '1,252', '12%', '5,441', '89%'],
        ['REGION 15 / MAKASSAR', '8,721', '1,614', '7,869', '(852)', '-10%', '6,255', '387%'],
        ['REGION 16 / MANADO', '12,530', '582', '5,596', '(6,934)', '-55%', '5,014', '862%'],
        ['REGION 17 / DENPASAR', '8,045', '8,524', '15,035', '6,990', '87%', '6,511', '76%'],
        ['REGION 18 / JAYAPURA', '2,880', '1,137', '3,674', '794', '28%', '2,537', '223%'],
    ];

    $grandTotal = ['Grand Total', '231,573', '51,716', '168,591', '(62,982)', '-27%', '116,874', '226%'];
@endphp

<div class="card card-outline card-primary shadow-sm mb-3 report-filter-card">
    <div class="card-body py-3">
        <div class="row align-items-end">
            <div class="col-md-4">
                <div class="form-group mb-0">
                    <label class="text-dark text-sm font-weight-bold mb-1">Periode Akhir <i class="fas fa-edit text-primary ml-1"></i></label>
                    <input type="date" class="form-control border-primary shadow-sm" value="{{ date('Y-m-d') }}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Branch Office (Kanca)</label>
                    <input type="text" class="form-control font-weight-bold" value="Area 6 - All" disabled>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-0">
                    <label class="text-muted text-sm mb-1">Nama Uker</label>
                    <input type="text" class="form-control" value="ALL UKER" disabled>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 report-data-card">
    <div class="card-header bg-white p-0 border-bottom-0">
        <ul class="nav nav-tabs report-tabs px-3 pt-2" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#tab-saldo-payroll" role="tab">
                    <i class="fas fa-wallet mr-1"></i> Saldo New Payroll
                </a>
            </li>
        </ul>
    </div>

    <div class="card-body p-0">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-saldo-payroll" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th colspan="8" class="bg-header-main">Akumulasi Saldo New Payroll</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="text-left" style="min-width: 210px;">Regional Office</th>
                                <th>Feb-25</th>
                                <th>Jan-26</th>
                                <th>Feb-26</th>
                                <th>YoY</th>
                                <th>YoY (%)</th>
                                <th>Mtd</th>
                                <th>Mtd (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr>
                                    <td class="text-left font-weight-bold">{{ $row[0] }}</td>
                                    <td>{{ $row[1] }}</td>
                                    <td>{{ $row[2] }}</td>
                                    <td class="col-block">{{ $row[3] }}</td>
                                    <td class="{{ str_contains($row[4], '(') ? 'text-negative' : '' }}">{{ $row[4] }}</td>
                                    <td class="{{ str_contains($row[5], '-') ? 'text-negative' : '' }}">{{ $row[5] }}</td>
                                    <td>{{ $row[6] }}</td>
                                    <td>{{ $row[7] }}</td>
                                </tr>
                            @endforeach
                            <tr class="row-total">
                                <td class="text-left">{{ $grandTotal[0] }}</td>
                                <td>{{ $grandTotal[1] }}</td>
                                <td>{{ $grandTotal[2] }}</td>
                                <td>{{ $grandTotal[3] }}</td>
                                <td>{{ $grandTotal[4] }}</td>
                                <td>{{ $grandTotal[5] }}</td>
                                <td>{{ $grandTotal[6] }}</td>
                                <td>{{ $grandTotal[7] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
