<div class="em-analytics-heading"><div><h2>Pantauan eksekusi</h2><p>Setiap grafik mengikuti wilayah dan filter monitoring.</p></div><span class="em-live-label"><i aria-hidden="true"></i> Data sumber tercatat</span></div>
<div class="em-chart-grid">
    <section class="em-panel em-trend-panel" aria-labelledby="em-trend-title">
        <div class="em-title-row"><div><h3 id="em-trend-title">Pergerakan prospek</h3><p data-em-trend-caption>Akumulasi tanggal mulai dan selesai dalam rentang terpilih.</p></div><div class="em-period-switch" role="group" aria-label="Rentang grafik pergerakan prospek"><button type="button" data-em-days="30" aria-pressed="false">30 hari</button><button type="button" data-em-days="90" aria-pressed="true">90 hari</button></div></div>
        <div class="em-trend-summary" aria-live="polite"><div><strong data-em-started>{{ $num(array_sum($chartData['started'])) }}</strong><span><i class="em-dot" style="background:#307fe2" aria-hidden="true"></i> Mulai</span></div><div><strong data-em-completed>{{ $num(array_sum($chartData['completed'])) }}</strong><span><i class="em-dot" style="background:#71c5e8" aria-hidden="true"></i> Selesai</span></div><span class="em-chart-period" data-em-range></span></div>
        <div class="em-chart-mode" role="group" aria-label="Cara menampilkan aktivitas"><button type="button" data-em-chart-mode="cumulative" aria-pressed="true">Akumulasi</button><button type="button" data-em-chart-mode="daily" aria-pressed="false">Harian</button></div>
        @if(array_sum($chartData['started'])+array_sum($chartData['completed'])>0)
            <div class="em-chart-frame em-chart-frame--trend"><canvas id="em-trend-chart" role="img" aria-label="Grafik aktivitas prospek mulai dan selesai; pilihan akumulasi atau harian. Rincian harian tersedia di tabel data grafik."></canvas></div>
        @else
            <div class="em-chart-empty"><i class="fas fa-chart-line" aria-hidden="true"></i><strong>Belum ada tanggal aktivitas</strong><span>Grafik terisi ketika tanggal mulai atau selesai tercatat pada prospek.</span></div>
        @endif
        <div class="em-chart-foot"><span><i class="fas fa-info-circle" aria-hidden="true"></i> {{ $num($chartData['coverage']['started']) }} dari {{ $num($s['total']) }} prospek memiliki tanggal mulai.</span><details class="em-chart-data"><summary>Data grafik</summary><div class="em-scroll" tabindex="0" role="region" aria-label="Data harian grafik pergerakan prospek" data-abah-no-table-guard="1"><table class="em-table" data-abah-no-table-guard="1"><thead><tr><th scope="col">Tanggal</th><th scope="col">Mulai</th><th scope="col">Selesai</th></tr></thead><tbody>@foreach($chartData['dates'] as $index=>$day)<tr><td>{{ $date($day) }}</td><td>{{ $chartData['started'][$index] }}</td><td>{{ $chartData['completed'][$index] }}</td></tr>@endforeach</tbody></table></div></details></div>
    </section>
    <section class="em-panel em-status-panel" aria-labelledby="em-status-title">
        <div class="em-title-row"><div><h3 id="em-status-title">Status pipeline</h3><p>Komposisi {{ $num($s['total']) }} prospek terpantau.</p></div><span class="em-icon-tile"><i class="fas fa-chart-pie" aria-hidden="true"></i></span></div>
        <div class="em-donut-wrap"><div class="em-chart-frame em-chart-frame--donut"><canvas id="em-status-chart" role="img" aria-label="Donut komposisi status prospek; jumlah tiap status pada legenda di bawah."></canvas></div><div class="em-donut-center" aria-hidden="true"><strong>{{ $num($s['total']) }}</strong><span>Total prospek</span></div></div>
        <div class="em-donut-legend">@foreach($s['statuses'] as $status=>$total)<a href="{{ $detailUrl(['status'=>$status]) }}"><i class="em-dot" style="background:{{ ['Belum mulai'=>'#d9e8f9','Berjalan'=>'#307fe2','Tertunda'=>'#e3b761','Selesai'=>'#0a4d98','Batal'=>'#a9b6c6'][$status] ?? '#71c5e8' }}" aria-hidden="true"></i><span>{{ $status }}</span><b>{{ $num($total) }}</b><small>{{ $s['total']>0 ? number_format($total/$s['total']*100,1,',','.') : '0' }}%</small></a>@endforeach</div>
    </section>
</div>
<div class="em-distribution-grid">
    <section class="em-panel" aria-labelledby="em-distribution-title"><div class="em-title-row"><div><h3 id="em-distribution-title">Distribusi kantor cabang</h3><p>Jumlah prospek sesuai filter aktif.</p></div><a class="em-text-link" href="{{ $url(['view'=>'monitor']) }}">Rapor KC <i class="fas fa-arrow-right" aria-hidden="true"></i></a></div>
        @if($s['total']>0)<div class="em-chart-frame em-chart-frame--branch"><canvas id="em-branch-chart" role="img" aria-label="Grafik distribusi prospek per kantor cabang. Rincian pada daftar cabang berikut."></canvas></div>@else<div class="em-chart-empty"><strong>Tidak ada prospek pada filter ini</strong><a href="{{ route('ekosistem.index',['view'=>'overview','kc'=>$selectedKc]) }}">Reset filter</a></div>@endif
        <div class="em-branch-pills">@foreach($chartData['branches'] as $branch=>$total)<a href="{{ $detailUrl(['kc'=>mb_strtolower($branch)]) }}">{{ $branch }}<b>{{ $num($total) }}</b></a>@endforeach</div>
    </section>
    <section class="em-panel em-stages-panel" aria-labelledby="em-stages-title"><div class="em-title-row"><div><h3 id="em-stages-title">Tahapan pengembangan</h3><p>Posisi tahapan saat ini, dari pemetaan hingga pemeliharaan.</p></div></div>
        <ol class="em-stage-flow">@foreach($chartData['stages'] as $stage=>$total)<li><span class="em-stage-marker" aria-hidden="true"><i class="fas fa-{{ ['Pendataan'=>'search','Penawaran'=>'file-alt','Sosialisasi'=>'users','Closing'=>'handshake','Maintenance'=>'sync-alt'][$stage] ?? 'circle' }}"></i></span><div><span>{{ $stage }}</span><div class="em-stage-bar"><i style="--em-stage-ratio:{{ $s['total'] ? max(.01,$total/$s['total']):0 }}" aria-hidden="true"></i></div></div><b>{{ $num($total) }}</b></li>@endforeach</ol>
    </section>
</div>
@php
    $chartPayload = ['series'=>$chartData,'statuses'=>$s['statuses'],
        'branchLinks'=>array_map(fn($branch)=>$detailUrl(['kc'=>mb_strtolower($branch)]),array_keys($chartData['branches'])),
        'statusLinks'=>array_map(fn($status)=>$detailUrl(['status'=>$status]),array_keys($s['statuses']))];
@endphp
<script type="application/json" id="em-chart-payload">@json($chartPayload)</script>
