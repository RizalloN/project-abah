@extends('layouts.admin')

@section('title', 'Rasio CASA Debitur')

@section('content')

<style>
    /* 🔥 PERBAIKAN UI: Tabel elastis dan cerdas menyesuaikan ukuran layar */
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
    .table-report th { font-size: 0.65rem; padding: 10px 4px; text-align: center; }
    .table-report td { font-size: 0.70rem; padding: 6px 6px; text-align: right; }
    .table-report td.text-left { text-align: left; }
    
    /* Pewarnaan Header Khas */
    .bg-header-main { background-color: #0056b3 !important; color: #ffffff !important; border-color: #004085 !important; }
    .bg-header-sub { background-color: #a6a6a6 !important; color: #ffffff !important; font-weight: bold; border-color: #808080 !important; }
    .bg-header-sub-light { background-color: #d9d9d9 !important; color: #333 !important; font-weight: bold; border-color: #bfbfbf !important; }

    /* Conditional Formatting Latar Belakang Sel (%) */
    .bg-good { background-color: #d4edda !important; color: #155724 !important; font-weight: bold;}
    .bg-bad { background-color: #f8d7da !important; color: #721c24 !important; font-weight: bold;}

    .table-hover tbody tr:hover { background-color: #f1f7ff; }
    .row-total { background-color: #0056b3 !important; color: white !important; font-weight: bold; }
    .row-total td { color: white !important; }
    .val-up { color: #28a745; font-weight: bold; margin-left: 2px; }
    .val-down { color: #dc3545; font-weight: bold; margin-left: 2px; }
    
    .nav-tabs.report-tabs { border-bottom: 2px solid #dee2e6; }
    .nav-tabs.report-tabs .nav-link { border: none; font-weight: 600; color: #6c757d; padding: 12px 20px; }
    .nav-tabs.report-tabs .nav-link.active { border-bottom: 3px solid #0056b3; color: #0056b3; background: transparent; }
    .nav-tabs.report-tabs .nav-link:hover { border-bottom: 3px solid #66a3ff; }
    
    .label-date { display: block; font-size: 0.55rem; font-weight: normal; margin-top: 3px; color: #666; }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Rasio CASA Debitur</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Rasio CASA Debitur</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- 🔥 CARD HEADER FILTER -->
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-body py-3">
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <div class="form-group mb-0">
                                <label class="text-muted text-sm mb-1">Nama Report</label>
                                <input type="text" class="form-control font-weight-bold" value="Rasio CASA Debitur" disabled>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-0">
                                <label class="text-muted text-sm mb-1">Branch Office (Kanca)</label>
                                <input type="text" class="form-control font-weight-bold" value="Area 6 - All" disabled>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group mb-0">
                                <label class="text-muted text-sm mb-1">Nama Uker</label>
                                <input type="text" class="form-control" value="ALL UKER" disabled>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group mb-0">
                                <label class="text-dark text-sm font-weight-bold mb-1">Posisi Terakhir <i class="fas fa-edit text-primary ml-1"></i></label>
                                <input type="date" id="filter_posisi" class="form-control border-primary shadow-sm filter-trigger" value="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group mb-0">
                                <label class="text-muted text-sm mb-1">Posisi RKA</label>
                                <input type="text" class="form-control" disabled value="--------">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🔥 TABEL DATA PERFORMANCE -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white p-0 border-bottom-0">
                    <ul class="nav nav-tabs report-tabs px-3 pt-2" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#tab-total" role="tab" data-tab="total">
                                <i class="fas fa-chart-bar mr-1"></i> Total
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-briguna" role="tab" data-tab="briguna">
                                <i class="fas fa-chart-bar mr-1"></i> BRIGUNA
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-kpr" role="tab" data-tab="kpr">
                                <i class="fas fa-chart-bar mr-1"></i> KPR
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-mikro" role="tab" data-tab="mikro">
                                <i class="fas fa-chart-bar mr-1"></i> MIKRO
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-smc" role="tab" data-tab="smc">
                                <i class="fas fa-chart-bar mr-1"></i> SMC
                            </a>
                        </li>
                        <li class="nav-item ml-auto d-flex align-items-center pr-2">
                            <!-- Indikator Loading -->
                            <span id="loadingIndicator" class="text-primary font-weight-bold" style="display: none; font-size: 0.9rem;">
                                <i class="fas fa-spinner fa-spin mr-1"></i> Memuat Data...
                            </span>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body p-0">
                    <div class="tab-content">
                        <!-- TAB TOTAL -->
                        <div class="tab-pane fade show active" id="tab-total" role="tabpanel">
                            <div class="table-container">
                                <table class="table table-hover table-report m-0">
                                    <thead class="sticky-top" style="z-index: 2;">
                                        <!-- Header Utama Sesuai Gambar -->
                                        <tr>
                                            <th rowspan="3" class="bg-header-main align-middle" style="min-width: 150px;">Regional Office</th>
                                            <th colspan="7" class="bg-header-main">MIKRO</th>
                                        </tr>
                                        <tr class="bg-header-sub">
                                            <th colspan="2">Total OS</th>
                                            <th colspan="2">Total CASA</th>
                                            <th colspan="3">Rasio CASA/OS</th>
                                        </tr>
                                        <tr class="bg-header-sub-light">
                                            <th class="lbl-prev-th">Jan '26</th>
                                            <th class="lbl-curr-th">13 Feb '26</th>
                                            <th class="lbl-prev-th">Jan '26</th>
                                            <th class="lbl-curr-th">13 Feb '26</th>
                                            <th class="lbl-prev-th">Jan '26</th>
                                            <th class="lbl-curr-th">13 Feb '26</th>
                                            <th>MtD</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-mikro">
                                        <!-- Data Statik Sesuai Gambar -->
                                        <tr>
                                            <td class="text-left">Region 1 / Medan</td>
                                            <td>30.226</td><td>30.468</td>
                                            <td>3.654</td><td>3.986</td>
                                            <td>12,09%</td><td>13,08%</td><td>0,99%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 2 / Pekanbaru</td>
                                            <td>19.754</td><td>19.838</td>
                                            <td>3.365</td><td>3.418</td>
                                            <td>17,04%</td><td>17,23%</td><td>0,19%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 3 / Padang</td>
                                            <td>13.631</td><td>13.797</td>
                                            <td>1.702</td><td>1.821</td>
                                            <td>12,49%</td><td>13,20%</td><td>0,71%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 4 / Palembang</td>
                                            <td>26.403</td><td>26.562</td>
                                            <td>3.715</td><td>3.844</td>
                                            <td>14,07%</td><td>14,47%</td><td>0,40%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 5 / Bandar Lampung</td>
                                            <td>20.553</td><td>20.668</td>
                                            <td>2.966</td><td>3.102</td>
                                            <td>14,43%</td><td>15,01%</td><td>0,58%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 6 / Jakarta 1</td>
                                            <td>6.308</td><td>6.341</td>
                                            <td>733</td><td>756</td>
                                            <td>11,62%</td><td>11,92%</td><td>0,29%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 7 / Jakarta 2</td>
                                            <td>17.117</td><td>17.150</td>
                                            <td>1.958</td><td>1.991</td>
                                            <td>11,44%</td><td>11,61%</td><td>0,17%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 8 / Jakarta 3</td>
                                            <td>11.365</td><td>11.418</td>
                                            <td>1.463</td><td>1.500</td>
                                            <td>12,87%</td><td>13,14%</td><td>0,27%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 9 / Bandung</td>
                                            <td>44.370</td><td>44.609</td>
                                            <td>5.202</td><td>5.295</td>
                                            <td>11,72%</td><td>11,87%</td><td>0,15%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 10 / Semarang</td>
                                            <td>39.025</td><td>39.195</td>
                                            <td>5.810</td><td>6.027</td>
                                            <td>14,89%</td><td>15,38%</td><td>0,49%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 11 / Yogyakarta</td>
                                            <td>43.561</td><td>43.774</td>
                                            <td>6.415</td><td>6.525</td>
                                            <td>14,73%</td><td>14,91%</td><td>0,18%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 12 / Surabaya</td>
                                            <td>28.891</td><td>29.197</td>
                                            <td>3.650</td><td>3.922</td>
                                            <td>12,63%</td><td>13,43%</td><td>0,80%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 13 / Malang</td>
                                            <td>46.834</td><td>46.998</td>
                                            <td>6.222</td><td>6.315</td>
                                            <td>13,28%</td><td>13,44%</td><td>0,15%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 14 / Banjarmasin</td>
                                            <td>33.840</td><td>34.012</td>
                                            <td>5.078</td><td>5.179</td>
                                            <td>15,00%</td><td>15,23%</td><td>0,22%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 15 / Makassar</td>
                                            <td>47.164</td><td>47.379</td>
                                            <td>6.072</td><td>6.127</td>
                                            <td>12,87%</td><td>12,93%</td><td>0,06%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 16 / Manado</td>
                                            <td>23.396</td><td>23.508</td>
                                            <td>2.008</td><td>2.118</td>
                                            <td>8,58%</td><td>9,01%</td><td>0,43%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 17 / Denpasar</td>
                                            <td>37.041</td><td>37.202</td>
                                            <td>4.370</td><td>4.520</td>
                                            <td>11,80%</td><td>12,15%</td><td>0,35%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 18 / Jayapura</td>
                                            <td>9.552</td><td>9.606</td>
                                            <td>1.061</td><td>1.095</td>
                                            <td>11,10%</td><td>11,40%</td><td>0,30%</td>
                                        </tr>
                                        <tr class="row-total">
                                            <td class="text-left">Total</td>
                                            <td>499.029</td><td>501.722</td>
                                            <td>65.444</td><td>67.541</td>
                                            <td>12,93%</td><td>13,30%</td><td>0,37%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB SMC -->
                        <div class="tab-pane fade" id="tab-smc" role="tabpanel">
                            <div class="table-container">
                                <table class="table table-hover table-report m-0">
                                    <thead class="sticky-top" style="z-index: 2;">
                                        <tr>
                                            <th rowspan="3" class="bg-header-main align-middle" style="min-width: 150px;">Regional Office</th>
                                            <th colspan="7" class="bg-header-main">SMC</th>
                                        </tr>
                                        <tr class="bg-header-sub">
                                            <th colspan="2">Total OS</th>
                                            <th colspan="2">Total CASA</th>
                                            <th colspan="3">Rasio CASA/OS</th>
                                        </tr>
                                        <tr class="bg-header-sub-light">
                                            <th class="lbl-prev-th">Jan '26</th>
                                            <th class="lbl-curr-th">13 Feb '26</th>
                                            <th class="lbl-prev-th">Jan '26</th>
                                            <th class="lbl-curr-th">13 Feb '26</th>
                                            <th class="lbl-prev-th">Jan '26</th>
                                            <th class="lbl-curr-th">13 Feb '26</th>
                                            <th>MtD</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-smc">
                                        <!-- Data Statik Sesuai Gambar -->
                                        <tr>
                                            <td class="text-left">Region 1 / Medan</td>
                                            <td>17.672</td><td>17.273</td>
                                            <td>2.591</td><td>2.123</td>
                                            <td>14,66%</td><td>12,29%</td><td>-2,37%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 2 / Pekanbaru</td>
                                            <td>10.947</td><td>10.946</td>
                                            <td>2.277</td><td>2.153</td>
                                            <td>20,80%</td><td>19,67%</td><td>-1,13%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 3 / Padang</td>
                                            <td>4.898</td><td>4.776</td>
                                            <td>591</td><td>424</td>
                                            <td>12,06%</td><td>8,87%</td><td>-3,19%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 4 / Palembang</td>
                                            <td>11.524</td><td>11.517</td>
                                            <td>1.615</td><td>1.416</td>
                                            <td>14,02%</td><td>12,29%</td><td>-1,72%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 5 / Bandar Lampung</td>
                                            <td>7.376</td><td>7.218</td>
                                            <td>868</td><td>715</td>
                                            <td>11,76%</td><td>9,91%</td><td>-1,85%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 6 / Jakarta 1</td>
                                            <td>20.919</td><td>20.395</td>
                                            <td>5.623</td><td>5.007</td>
                                            <td>26,88%</td><td>24,55%</td><td>-2,33%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 7 / Jakarta 2</td>
                                            <td>20.062</td><td>19.553</td>
                                            <td>5.635</td><td>4.388</td>
                                            <td>28,09%</td><td>22,44%</td><td>-5,65%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 8 / Jakarta 3</td>
                                            <td>16.176</td><td>15.856</td>
                                            <td>3.514</td><td>2.660</td>
                                            <td>21,72%</td><td>16,77%</td><td>-4,95%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 9 / Bandung</td>
                                            <td>13.497</td><td>13.178</td>
                                            <td>2.018</td><td>1.507</td>
                                            <td>14,95%</td><td>11,43%</td><td>-3,52%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 10 / Semarang</td>
                                            <td>15.107</td><td>14.631</td>
                                            <td>2.011</td><td>1.471</td>
                                            <td>13,31%</td><td>10,05%</td><td>-3,26%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 11 / Yogyakarta</td>
                                            <td>15.018</td><td>14.798</td>
                                            <td>1.523</td><td>1.229</td>
                                            <td>10,14%</td><td>8,30%</td><td>-1,84%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 12 / Surabaya</td>
                                            <td>24.630</td><td>23.954</td>
                                            <td>3.397</td><td>2.501</td>
                                            <td>13,79%</td><td>10,44%</td><td>-3,35%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 13 / Malang</td>
                                            <td>17.692</td><td>17.300</td>
                                            <td>1.901</td><td>1.505</td>
                                            <td>10,75%</td><td>8,70%</td><td>-2,05%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 14 / Banjarmasin</td>
                                            <td>16.791</td><td>16.520</td>
                                            <td>3.510</td><td>3.062</td>
                                            <td>20,91%</td><td>18,54%</td><td>-2,37%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 15 / Makassar</td>
                                            <td>14.517</td><td>14.149</td>
                                            <td>5.043</td><td>4.137</td>
                                            <td>34,74%</td><td>29,24%</td><td>-5,50%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 16 / Manado</td>
                                            <td>8.504</td><td>8.381</td>
                                            <td>1.188</td><td>1.064</td>
                                            <td>13,97%</td><td>12,69%</td><td>-1,27%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 17 / Denpasar</td>
                                            <td>14.528</td><td>14.316</td>
                                            <td>1.951</td><td>1.647</td>
                                            <td>13,43%</td><td>11,50%</td><td>-1,93%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 18 / Jayapura</td>
                                            <td>4.925</td><td>4.747</td>
                                            <td>943</td><td>741</td>
                                            <td>19,14%</td><td>15,60%</td><td>-3,54%</td>
                                        </tr>
                                        <tr class="row-total">
                                            <td class="text-left">Total</td>
                                            <td>254.784</td><td>249.507</td>
                                            <td>46.200</td><td>37.749</td>
                                            <td>17,51%</td><td>14,63%</td><td>-2,88%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Script untuk handle tab switching jika diperlukan
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        // let activeTab = $(e.target).data('tab');
        // console.log('Tab active:', activeTab);
    });
});
</script>
@endsection
                                            <th colspan="7" class="bg-header-main">Total</th>
                                        </tr>
                                        <tr class="bg-header-sub">
                                            <th colspan="2">Total OS</th>
                                            <th colspan="2">Total CASA</th>
                                            <th colspan="3">Rasio CASA/OS</th>
                                        </tr>
                                        <!-- Sub-Header Metrik Dinamis -->
                                        <tr class="bg-header-sub-light">
                                            <!-- Sub Total OS -->
                                            <th class="lbl-prev-th">Jan '26</th>
                                            <th class="lbl-curr-th">13 Feb '26</th>

                                            <!-- Sub Total CASA -->
                                            <th class="lbl-prev-th">Jan '26</th>
                                            <th class="lbl-curr-th">13 Feb '26</th>

                                            <!-- Sub Rasio CASA/OS -->
                                            <th class="lbl-prev-th">Jan '26</th>
                                            <th class="lbl-curr-th">13 Feb '26</th>
                                            <th>MtD</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-total">
                                        <!-- Data Statik Sesuai Gambar -->
                                        <tr>
                                            <td class="text-left">Region 1 / Medan</td>
                                            <td>13.571</td><td>13.309</td>
                                            <td>7.325</td><td>7.199</td>
                                            <td>12,92%</td><td>12,73%</td><td>-0,19%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 2 / Pekanbaru</td>
                                            <td>11.702</td><td>11.569</td>
                                            <td>6.059</td><td>5.998</td>
                                            <td>16,68%</td><td>16,45%</td><td>-0,23%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 3 / Padang</td>
                                            <td>4.879</td><td>4.786</td>
                                            <td>2.586</td><td>2.542</td>
                                            <td>10,92%</td><td>10,70%</td><td>-0,22%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 4 / Palembang</td>
                                            <td>11.299</td><td>11.182</td>
                                            <td>5.968</td><td>5.922</td>
                                            <td>12,50%</td><td>12,35%</td><td>-0,15%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 5 / Bandar Lampung</td>
                                            <td>8.124</td><td>8.117</td>
                                            <td>4.291</td><td>4.300</td>
                                            <td>12,45%</td><td>12,48%</td><td>0,03%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 6 / Jakarta 1</td>
                                            <td>14.349</td><td>13.120</td>
                                            <td>7.993</td><td>7.357</td>
                                            <td>18,11%</td><td>16,85%</td><td>-1,26%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 7 / Jakarta 2</td>
                                            <td>16.469</td><td>14.023</td>
                                            <td>8.875</td><td>7.644</td>
                                            <td>16,12%</td><td>13,99%</td><td>-2,14%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 8 / Jakarta 3</td>
                                            <td>10.525</td><td>8.908</td>
                                            <td>5.548</td><td>4.748</td>
                                            <td>13,59%</td><td>11,69%</td><td>-1,90%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 9 / Bandung</td>
                                            <td>15.446</td><td>14.635</td>
                                            <td>8.226</td><td>7.833</td>
                                            <td>10,76%</td><td>10,25%</td><td>-0,51%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 10 / Semarang</td>
                                            <td>16.412</td><td>15.766</td>
                                            <td>8.590</td><td>8.268</td>
                                            <td>13,28%</td><td>12,83%</td><td>-0,45%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 11 / Yogyakarta</td>
                                            <td>16.693</td><td>16.325</td>
                                            <td>8.755</td><td>8.572</td>
                                            <td>12,78%</td><td>12,51%</td><td>-0,27%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 12 / Surabaya</td>
                                            <td>14.787</td><td>13.532</td>
                                            <td>7.740</td><td>7.110</td>
                                            <td>12,01%</td><td>11,09%</td><td>-0,92%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 13 / Malang</td>
                                            <td>17.161</td><td>16.561</td>
                                            <td>9.038</td><td>8.741</td>
                                            <td>11,73%</td><td>11,37%</td><td>-0,36%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 14 / Banjarmasin</td>
                                            <td>18.313</td><td>17.612</td>
                                            <td>9.725</td><td>9.370</td>
                                            <td>14,61%</td><td>14,09%</td><td>-0,52%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 15 / Makassar</td>
                                            <td>23.604</td><td>21.955</td>
                                            <td>12.489</td><td>11.691</td>
                                            <td>15,23%</td><td>14,27%</td><td>-0,97%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 16 / Manado</td>
                                            <td>7.074</td><td>7.070</td>
                                            <td>3.879</td><td>3.888</td>
                                            <td>8,55%</td><td>8,57%</td><td>0,01%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 17 / Denpasar</td>
                                            <td>13.533</td><td>13.233</td>
                                            <td>7.212</td><td>7.067</td>
                                            <td>10,97%</td><td>10,75%</td><td>-0,22%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 18 / Jayapura</td>
                                            <td>4.372</td><td>4.055</td>
                                            <td>2.369</td><td>2.219</td>
                                            <td>11,44%</td><td>10,76%</td><td>-0,68%</td>
                                        </tr>
                                        <tr class="row-total">
                                            <td class="text-left">Total</td>
                                            <td>238.311</td><td>225.758</td>
                                            <td>126.666</td><td>120.469</td>
                                            <td>13,04%</td><td>12,43%</td><td>-0,61%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- TAB BRIGUNA -->
                        <div class="tab-pane fade" id="tab-briguna" role="tabpanel">
                            <div class="table-container">
                                <table class="table table-hover table-report m-0">
                                    <thead class="sticky-top" style="z-index: 2;">
                                        <tr>
                                            <th rowspan="3" class="bg-header-main align-middle" style="min-width: 150px;">Regional Office</th>
                                            <th colspan="7" class="bg-header-main">BRIGUNA</th>
                                        </tr>
                                        <tr class="bg-header-sub">
                                            <th colspan="2">Total OS</th>
                                            <th colspan="2">Total CASA</th>
                                            <th colspan="3">Rasio CASA/OS</th>
                                        </tr>
                                        <tr class="bg-header-sub-light">
                                            <th class="lbl-prev-th">Jan '26</th>
                                            <th class="lbl-curr-th">13 Feb '26</th>
                                            <th class="lbl-prev-th">Jan '26</th>
                                            <th class="lbl-curr-th">13 Feb '26</th>
                                            <th class="lbl-prev-th">Jan '26</th>
                                            <th class="lbl-curr-th">13 Feb '26</th>
                                            <th>MtD</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-briguna">
                                        <!-- Data Statik Sesuai Gambar -->
                                        <tr>
                                            <td class="text-left">Region 1 / Medan</td>
                                            <td>5.635</td><td>5.657</td>
                                            <td>403</td><td>420</td>
                                            <td>7,16%</td><td>7,42%</td><td>0,26%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 2 / Pekanbaru</td>
                                            <td>4.025</td><td>4.062</td>
                                            <td>292</td><td>298</td>
                                            <td>7,25%</td><td>7,34%</td><td>0,09%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 3 / Padang</td>
                                            <td>4.207</td><td>4.226</td>
                                            <td>225</td><td>240</td>
                                            <td>5,35%</td><td>5,67%</td><td>0,32%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 4 / Palembang</td>
                                            <td>7.998</td><td>8.030</td>
                                            <td>536</td><td>559</td>
                                            <td>6,70%</td><td>6,96%</td><td>0,26%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 5 / Bandar Lampung</td>
                                            <td>5.417</td><td>5.443</td>
                                            <td>382</td><td>405</td>
                                            <td>7,06%</td><td>7,43%</td><td>0,37%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 6 / Jakarta 1</td>
                                            <td>10.460</td><td>10.491</td>
                                            <td>814</td><td>788</td>
                                            <td>7,79%</td><td>7,52%</td><td>-0,27%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 7 / Jakarta 2</td>
                                            <td>11.022</td><td>11.091</td>
                                            <td>802</td><td>818</td>
                                            <td>7,27%</td><td>7,38%</td><td>0,10%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 8 / Jakarta 3</td>
                                            <td>5.854</td><td>5.890</td>
                                            <td>330</td><td>348</td>
                                            <td>5,64%</td><td>5,92%</td><td>0,28%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 9 / Bandung</td>
                                            <td>14.410</td><td>14.499</td>
                                            <td>790</td><td>833</td>
                                            <td>5,48%</td><td>5,75%</td><td>0,26%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 10 / Semarang</td>
                                            <td>7.135</td><td>7.166</td>
                                            <td>571</td><td>586</td>
                                            <td>8,01%</td><td>8,18%</td><td>0,17%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 11 / Yogyakarta</td>
                                            <td>7.392</td><td>7.427</td>
                                            <td>643</td><td>657</td>
                                            <td>8,70%</td><td>8,85%</td><td>0,15%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 12 / Surabaya</td>
                                            <td>6.835</td><td>6.865</td>
                                            <td>506</td><td>508</td>
                                            <td>7,40%</td><td>7,39%</td><td>-0,004%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 13 / Malang</td>
                                            <td>9.506</td><td>9.545</td>
                                            <td>740</td><td>753</td>
                                            <td>7,79%</td><td>7,89%</td><td>0,11%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 14 / Banjarmasin</td>
                                            <td>11.643</td><td>11.694</td>
                                            <td>759</td><td>778</td>
                                            <td>6,52%</td><td>6,66%</td><td>0,14%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 15 / Makassar</td>
                                            <td>14.419</td><td>14.492</td>
                                            <td>948</td><td>992</td>
                                            <td>6,57%</td><td>6,84%</td><td>0,27%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 16 / Manado</td>
                                            <td>10.742</td><td>10.788</td>
                                            <td>527</td><td>550</td>
                                            <td>4,90%</td><td>5,10%</td><td>0,20%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 17 / Denpasar</td>
                                            <td>11.118</td><td>11.171</td>
                                            <td>678</td><td>705</td>
                                            <td>6,10%</td><td>6,31%</td><td>0,21%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 18 / Jayapura</td>
                                            <td>3.887</td><td>3.910</td>
                                            <td>250</td><td>263</td>
                                            <td>6,43%</td><td>6,72%</td><td>0,29%</td>
                                        </tr>
                                        <tr class="row-total">
                                            <td class="text-left">Total</td>
                                            <td>151.704</td><td>152.449</td>
                                            <td>10.196</td><td>10.502</td>
                                            <td>6,78%</td><td>6,96%</td><td>0,18%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB KPR -->
                        <div class="tab-pane fade" id="tab-kpr" role="tabpanel">
                            <div class="table-container">
                                <table class="table table-hover table-report m-0">
                                    <thead class="sticky-top" style="z-index: 2;">
                                        <tr>
                                            <th rowspan="3" class="bg-header-main align-middle" style="min-width: 150px;">Regional Office</th>
                                            <th colspan="7" class="bg-header-main">KPR</th>
                                        </tr>
                                        <tr class="bg-header-sub">
                                            <th colspan="2">Total OS</th>
                                            <th colspan="2">Total CASA</th>
                                            <th colspan="3">Rasio CASA/OS</th>
                                        </tr>
                                        <tr class="bg-header-sub-light">
                                            <th class="lbl-prev-th">Jan '26</th>
                                            <th class="lbl-curr-th">13 Feb '26</th>
                                            <th class="lbl-prev-th">Jan '26</th>
                                            <th class="lbl-curr-th">13 Feb '26</th>
                                            <th class="lbl-prev-th">Jan '26</th>
                                            <th class="lbl-curr-th">13 Feb '26</th>
                                            <th>MtD</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-kpr">
                                        <!-- Data Statik Sesuai Gambar -->
                                        <tr>
                                            <td class="text-left">Region 1 / Medan</td>
                                            <td>3.142</td><td>3.147</td>
                                            <td>676</td><td>670</td>
                                            <td>21,50%</td><td>21,28%</td><td>-0,22%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 2 / Pekanbaru</td>
                                            <td>1.605</td><td>1.616</td>
                                            <td>125</td><td>129</td>
                                            <td>7,80%</td><td>7,98%</td><td>0,18%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 3 / Padang</td>
                                            <td>954</td><td>955</td>
                                            <td>68</td><td>58</td>
                                            <td>7,15%</td><td>6,03%</td><td>-1,12%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 4 / Palembang</td>
                                            <td>1.827</td><td>1.834</td>
                                            <td>101</td><td>103</td>
                                            <td>5,53%</td><td>5,62%</td><td>0,09%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 5 / Bandar Lampung</td>
                                            <td>1.126</td><td>1.141</td>
                                            <td>75</td><td>79</td>
                                            <td>6,64%</td><td>6,89%</td><td>0,25%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 6 / Jakarta 1</td>
                                            <td>6.438</td><td>6.437</td>
                                            <td>822</td><td>807</td>
                                            <td>12,76%</td><td>12,53%</td><td>-0,23%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 7 / Jakarta 2</td>
                                            <td>6.842</td><td>6.852</td>
                                            <td>479</td><td>447</td>
                                            <td>7,00%</td><td>6,52%</td><td>-0,48%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 8 / Jakarta 3</td>
                                            <td>7.442</td><td>7.449</td>
                                            <td>241</td><td>239</td>
                                            <td>3,24%</td><td>3,21%</td><td>-0,03%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 9 / Bandung</td>
                                            <td>4.145</td><td>4.143</td>
                                            <td>215</td><td>198</td>
                                            <td>5,20%</td><td>4,78%</td><td>-0,42%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 10 / Semarang</td>
                                            <td>3.435</td><td>3.465</td>
                                            <td>197</td><td>185</td>
                                            <td>5,75%</td><td>5,33%</td><td>-0,42%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 11 / Yogyakarta</td>
                                            <td>2.544</td><td>2.548</td>
                                            <td>175</td><td>161</td>
                                            <td>6,87%</td><td>6,32%</td><td>-0,55%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 12 / Surabaya</td>
                                            <td>4.085</td><td>4.087</td>
                                            <td>188</td><td>180</td>
                                            <td>4,59%</td><td>4,40%</td><td>-0,20%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 13 / Malang</td>
                                            <td>3.045</td><td>3.060</td>
                                            <td>175</td><td>168</td>
                                            <td>5,74%</td><td>5,49%</td><td>-0,25%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 14 / Banjarmasin</td>
                                            <td>4.286</td><td>4.295</td>
                                            <td>378</td><td>350</td>
                                            <td>8,83%</td><td>8,15%</td><td>-0,68%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 15 / Makassar</td>
                                            <td>5.896</td><td>5.930</td>
                                            <td>426</td><td>435</td>
                                            <td>7,23%</td><td>7,33%</td><td>0,10%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 16 / Manado</td>
                                            <td>2.698</td><td>2.716</td>
                                            <td>156</td><td>156</td>
                                            <td>5,79%</td><td>5,75%</td><td>-0,05%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 17 / Denpasar</td>
                                            <td>3.071</td><td>3.075</td>
                                            <td>213</td><td>195</td>
                                            <td>6,92%</td><td>6,34%</td><td>-0,59%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-left">Region 18 / Jayapura</td>
                                            <td>2.340</td><td>2.358</td>
                                            <td>115</td><td>120</td>
                                            <td>4,94%</td><td>5,09%</td><td>0,15%</td>
                                        </tr>
                                        <tr class="row-total">
                                            <td class="text-left">Total</td>
                                            <td>64.920</td><td>65.109</td>
                                            <td>4.826</td><td>4.677</td>
                                            <td>7,42%</td><td>7,17%</td><td>-0,25%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB MIKRO -->
                        <div class="tab-pane fade" id="tab-mikro" role="tabpanel">
                            <div class="table-container">
                                <table class="table table-hover table-report m-0">
                                    <thead class="sticky-top" style="z-index: 2;">
                                        <tr>
                                            <th rowspan="3" class="bg-header-main align-middle" style="min-width: 150px;">Regional Office</th>
