@extends('layouts.admin')

@section('title', 'Kinerja New Payroll')

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
    .row-total { --row-total-bg: #7ba7e6; --row-total-color: #102a43; background-color: #7ba7e6 !important; color: #102a43 !important; font-weight: bold; }
    .row-total td { font-weight: bold; }
    .val-up { color: #2e8b57; font-weight: bold; margin-right: 4px; }
    .text-negative { color: #ff0000; }
    .nav-tabs.report-tabs { border-bottom: 2px solid #dee2e6; flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; white-space: nowrap; scrollbar-width: thin; }
    .nav-tabs.report-tabs .nav-link { border: none; font-weight: 600; color: #6c757d; padding: 12px 18px; font-size: 0.95rem; background: transparent; }
    .nav-tabs.report-tabs .nav-link.active { border-bottom: 3px solid #007bff; color: #007bff; background: transparent; }
    .nav-tabs.report-tabs .nav-link:hover { border-bottom: 3px solid #9ec5fe; color: #007bff; background: transparent; }
</style>

@php
    $totalRows = [
        ['REGION 1 / MEDAN', '10,117', '15%', '1,343', '11,735', '86%', '7,033', '15%', '1,035', '1,170,945', '-10%', '(131,020)'],
        ['REGION 2 / PEKANBARU', '2,966', '-49%', '(2,813)', '7,026', '42%', '8,973', '-77%', '(6,884)', '199,802', '-69%', '(435,485)'],
        ['REGION 3 / PADANG', '2,906', '-7%', '(217)', '4,410', '66%', '21,308', '-65%', '(13,859)', '1,272,135', '-66%', '(2,503,926)'],
        ['REGION 4 / PALEMBANG', '5,483', '-1%', '(37)', '6,503', '84%', '10,029', '-1%', '(133)', '3,405,243', '6%', '193,962'],
        ['REGION 5 / BANDAR LAMPUNG', '5,058', '-21%', '(1,344)', '4,784', '106%', '2,748', '-4%', '(98)', '340,743', '60%', '128,171'],
        ['REGION 6 / JAKARTA 1', '7,775', '-40%', '(5,151)', '21,900', '36%', '17,552', '-55%', '(9,577)', '1,083,407', '10%', '98,768'],
        ['REGION 7 / JAKARTA 2', '7,361', '-59%', '(10,465)', '26,385', '28%', '18,773', '-69%', '(12,893)', '1,083,297', '-65%', '(2,012,595)'],
        ['REGION 8 / JAKARTA 3', '6,805', '-41%', '(4,792)', '15,472', '44%', '10,039', '-39%', '(3,889)', '458,853', '-30%', '(194,064)'],
        ['REGION 9 / BANDUNG', '13,697', '-22%', '(3,822)', '13,828', '99%', '13,466', '-10%', '(1,314)', '985,149', '-11%', '(121,970)'],
        ['REGION 10 / SEMARANG', '17,804', '0%', '63', '12,931', '138%', '19,785', '51%', '10,039', '4,382,594', '157%', '2,676,583'],
        ['REGION 11 / YOGYAKARTA', '12,335', '1%', '172', '12,632', '98%', '26,868', '-66%', '(17,618)', '3,118,455', '-33%', '(1,530,722)'],
        ['REGION 12 / SURABAYA', '13,404', '-13%', '(1,972)', '13,454', '100%', '15,258', '-25%', '(3,754)', '2,274,270', '-5%', '(109,009)'],
        ['REGION 13 / MALANG', '10,456', '-26%', '(3,669)', '11,660', '90%', '17,265', '-31%', '(5,285)', '1,184,162', '-40%', '(783,564)'],
        ['REGION 14 / BANJARMASIN', '11,774', '-28%', '(4,491)', '10,688', '110%', '10,300', '12%', '1,252', '648,845', '12%', '68,276'],
        ['REGION 15 / MAKASSAR', '9,217', '-4%', '(347)', '8,670', '106%', '8,721', '-10%', '(852)', '853,757', '-6%', '(58,092)'],
        ['REGION 16 / MANADO', '6,890', '28%', '1,488', '10,240', '67%', '12,530', '-55%', '(6,934)', '1,020,538', '-55%', '(1,249,349)'],
        ['REGION 17 / DENPASAR', '5,428', '-10%', '(636)', '11,361', '48%', '8,045', '87%', '6,990', '1,276,954', '158%', '782,324'],
        ['REGION 18 / JAYAPURA', '5,856', '4%', '213', '2,990', '196%', '2,880', '28%', '794', '268,239', '63%', '103,821'],
    ];

    $grandTotal = ['Grand Total', '155,332', '-19%', '(36,477)', '206,667', '75%', '231,573', '-27%', '(62,982)', '1,085,358', '-10%', '(121,954)'];

    $rekeningRows = [
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

    $rekeningGrandTotal = ['Grand Total', '191.809', '85.080', '155.332', '(36.477)', '-19,0%', '206.667', '75%'];

    $saldoRows = [
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

    $saldoGrandTotal = ['Grand Total', '231,573', '51,716', '168,591', '(62,982)', '-27%', '116,874', '226%'];
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
                <a class="nav-link active" data-toggle="tab" href="#tab-total" role="tab">
                    <i class="fas fa-chart-pie mr-1"></i> Total
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-rekening" role="tab">
                    <i class="fas fa-university mr-1"></i> Rekening New Payroll
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab-saldo" role="tab">
                    <i class="fas fa-wallet mr-1"></i> Saldo New Payroll
                </a>
            </li>
        </ul>
    </div>

    <div class="card-body p-0">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-total" role="tabpanel">
                <div class="table-container">
                    <table class="table table-hover table-report m-0">
                        <thead class="sticky-top" style="z-index: 2;">
                            <tr>
                                <th rowspan="2" class="bg-header-main align-middle" style="min-width: 210px;">REGIONAL OFFICE</th>
                                <th colspan="5" class="bg-header-main">New Rekening Payroll</th>
                                <th colspan="3" class="bg-header-main">Saldo New Payroll (Rp. M)</th>
                                <th colspan="3" class="bg-header-main">Kualitas New Payroll</th>
                            </tr>
                            <tr class="bg-header-sub">
                                <th>Feb-26</th>
                                <th>%YoY</th>
                                <th>YoY</th>
                                <th>Target Feb-26</th>
                                <th>Penc (%)</th>
                                <th>Feb-26</th>
                                <th>%YoY</th>
                                <th>YoY</th>
                                <th>Feb-26</th>
                                <th>%YoY</th>
                                <th>YoY</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($totalRows as $row)
                                <tr>
                                    <td class="text-left font-weight-bold">{{ $row[0] }}</td>
                                    <td class="col-block">{{ $row[1] }}</td>
                                    <td>{{ $row[2] }}</td>
                                    <td class="{{ str_contains($row[3], '(') ? 'text-negative' : '' }}">{{ $row[3] }}</td>
                                    <td class="col-block">{{ $row[4] }}</td>
                                    <td><i class="fas fa-arrow-up val-up"></i> {{ $row[5] }}</td>
                                    <td class="col-block">{{ $row[6] }}</td>
                                    <td>{{ $row[7] }}</td>
                                    <td class="{{ str_contains($row[8], '(') ? 'text-negative' : '' }}">{{ $row[8] }}</td>
                                    <td class="col-block">{{ $row[9] }}</td>
                                    <td>{{ $row[10] }}</td>
                                    <td class="{{ str_contains($row[11], '(') ? 'text-negative' : '' }}">{{ $row[11] }}</td>
                                </tr>
                            @endforeach
                            <tr class="row-total">
                                <td class="text-left">{{ $grandTotal[0] }}</td>
                                <td>{{ $grandTotal[1] }}</td>
                                <td>{{ $grandTotal[2] }}</td>
                                <td>{{ $grandTotal[3] }}</td>
                                <td>{{ $grandTotal[4] }}</td>
                                <td><i class="fas fa-arrow-up val-up"></i> {{ $grandTotal[5] }}</td>
                                <td>{{ $grandTotal[6] }}</td>
                                <td>{{ $grandTotal[7] }}</td>
                                <td>{{ $grandTotal[8] }}</td>
                                <td>{{ $grandTotal[9] }}</td>
                                <td>{{ $grandTotal[10] }}</td>
                                <td>{{ $grandTotal[11] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-rekening" role="tabpanel">
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
                            @foreach($rekeningRows as $row)
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
                                <td class="text-left">{{ $rekeningGrandTotal[0] }}</td>
                                <td>{{ $rekeningGrandTotal[1] }}</td>
                                <td>{{ $rekeningGrandTotal[2] }}</td>
                                <td>{{ $rekeningGrandTotal[3] }}</td>
                                <td>{{ $rekeningGrandTotal[4] }}</td>
                                <td>{{ $rekeningGrandTotal[5] }}</td>
                                <td>{{ $rekeningGrandTotal[6] }}</td>
                                <td>{{ $rekeningGrandTotal[7] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-saldo" role="tabpanel">
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
                            @foreach($saldoRows as $row)
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
                                <td class="text-left">{{ $saldoGrandTotal[0] }}</td>
                                <td>{{ $saldoGrandTotal[1] }}</td>
                                <td>{{ $saldoGrandTotal[2] }}</td>
                                <td>{{ $saldoGrandTotal[3] }}</td>
                                <td>{{ $saldoGrandTotal[4] }}</td>
                                <td>{{ $saldoGrandTotal[5] }}</td>
                                <td>{{ $saldoGrandTotal[6] }}</td>
                                <td>{{ $saldoGrandTotal[7] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
