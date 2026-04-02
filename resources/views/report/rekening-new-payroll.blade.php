@extends('layouts.admin')

@section('title', 'Rekening New Payroll')

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
        ['REGION 1 / MEDAN', '8.774', '4.752', '10.117', '1.343', '15,3%', '11.660', '87%'],
        ['REGION 2 / PEKANBARU', '5.779', '1.271', '2.966', '(2.813)', '-48,7%', '12.931', '23%'],
        ['REGION 3 / PADANG', '3.123', '1.698', '2.906', '(217)', '-6,9%', '10.688', '27%'],
        ['REGION 4 / PALEMBANG', '5.520', '2.504', '5.483', '(37)', '-0,7%', '8.670', '63%'],
        ['REGION 5 / BANDAR LAMPUNG', '6.402', '2.851', '5.058', '(1.344)', '-21,0%', '21.900', '23%'],
        ['REGION 6 / JAKARTA 1', '12.926', '4.498', '7.775', '(5.151)', '-39,8%', '6.503', '120%'],
        ['REGION 7 / JAKARTA 2', '17.826', '4.385', '7.361', '(10.465)', '-58,7%', '4.784', '154%'],
        ['REGION 8 / JAKARTA 3', '11.597', '3.630', '6.805', '(4.792)', '-41,3%', '26.385', '26%'],
        ['REGION 9 / BANDUNG', '17.519', '8.041', '13.697', '(3.822)', '-21,8%', '11.735', '117%'],
        ['REGION 10 / SEMARANG', '17.741', '6.833', '17.804', '63', '0,4%', '10.240', '174%'],
        ['REGION 11 / YOGYAKARTA', '12.163', '7.328', '12.335', '172', '1,4%', '2.990', '413%'],
        ['REGION 12 / SURABAYA', '15.376', '7.127', '13.404', '(1.972)', '-12,8%', '11.361', '118%'],
        ['REGION 13 / MALANG', '14.125', '6.443', '10.456', '(3.669)', '-26,0%', '13.828', '76%'],
        ['REGION 14 / BANJARMASIN', '16.265', '6.526', '11.774', '(4.491)', '-27,6%', '7.026', '168%'],
        ['REGION 15 / MAKASSAR', '9.564', '6.272', '9.217', '(347)', '-3,6%', '12.632', '73%'],
        ['REGION 16 / MANADO', '5.402', '3.833', '6.890', '1.488', '27,5%', '13.454', '51%'],
        ['REGION 17 / DENPASAR', '6.064', '3.855', '5.428', '(636)', '-10,5%', '4.410', '123%'],
        ['REGION 18 / JAYAPURA', '5.643', '3.233', '5.856', '213', '3,8%', '15.472', '38%'],
    ];

    $grandTotal = ['Grand Total', '191.809', '85.080', '155.332', '(36.477)', '-19,0%', '206.667', '75%'];
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
                <a class="nav-link active" data-toggle="tab" href="#tab-rekening-payroll" role="tab">
                    <i class="fas fa-money-check-alt mr-1"></i> Rekening New Payroll
                </a>
            </li>
        </ul>
    </div>

    <div class="card-body p-0">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-rekening-payroll" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th colspan="8" class="bg-header-main">New Payroll</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th class="text-left" style="min-width: 210px;">Regional Office</th>
                                <th>Feb-25</th>
                                <th>Jan-26</th>
                                <th>Feb-26</th>
                                <th>YoY</th>
                                <th>YoY (%)</th>
                                <th>Target Feb-26</th>
                                <th>Penc (%)</th>
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
