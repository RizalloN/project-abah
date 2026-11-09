@extends('layouts.admin')
@section('title', 'Peta Prospek Ekosistem')
@php
    $s = $monitorSummary;
    $money = static function ($value): string {
        if ($value === null || $value === '') return '—';
        $value = (float) $value;
        if (abs($value) >= 1_000_000) return 'Rp'.number_format($value / 1_000_000, 2, ',', '.').' T';
        if (abs($value) >= 1_000) return 'Rp'.number_format($value / 1_000, 1, ',', '.').' M';
        return 'Rp'.number_format($value, $value == floor($value) ? 0 : 1, ',', '.').' jt';
    };
    $range = static function ($min, $max) use ($money): string {
        if ($min === null && $max === null) return '—';
        $min = $min ?? $max;
        $max = $max ?? $min;
        return $min == $max ? $money($max) : $money($min).' – '.$money($max);
    };
    $num = static fn ($n): string => number_format((float) $n, 0, ',', '.');
    $date = static fn ($v): string => $v ? \Carbon\Carbon::parse($v)->locale('id')->translatedFormat('d M Y') : 'Belum diisi';
    $url = static function (array $patch = []) use ($filters, $activeView, $followupFilter): string {
        $params = array_merge($filters, ['view' => $activeView, 'followup' => $followupFilter], $patch);
        if ($params['view'] !== 'followup') unset($params['followup']);
        return route('ekosistem.index', array_filter($params, static fn ($v): bool => $v !== '' && $v !== null));
    };
    $detailUrl = static fn (array $patch = []): string => $url(array_merge(['view' => 'prospects', 'page' => null], $patch));
    $statusTone = ['Belum mulai' => 'muted', 'Berjalan' => 'info', 'Tertunda' => 'warning', 'Selesai' => 'success', 'Batal' => 'danger'];
    $filterLabels = ['jalur'=>'Jalur RM', 'condition'=>'Kondisi', 'q'=>'Pencarian', 'jenis'=>'Jenis potensi', 'status'=>'Status', 'pic'=>'PIC', 'ring'=>'Ring', 'arah'=>'Arah', 'dasar'=>'Dasar angka', 'eco'=>'Ekosistem', 'peran'=>'Peran PIC'];
    $extraOpen = count(array_filter(array_intersect_key($filters, array_flip(['jenis','status','pic','ring','arah','dasar','eco','peran'])))) > 0;
    $attention = array_sum(array_intersect_key($s['conditions'], array_flip(['late','stale','due','hold','nopic'])));
    $detailRecords = array_column(($activeView === 'followup' ? $followupPaginator : $paginator)->items(), null, 'ID_Prospek');
    $ecoNames = array_column($allEcosystems, 'Nama_Ekosistem', 'ID_Ekosistem');
    $ringCounts = array_count_values(array_column($prospects, 'Ring'));
    $stageCounts = array_count_values(array_column($prospects, 'Tahap'));
    $completion = $s['total'] ? round($s['statuses']['Selesai'] / $s['total'] * 100) : 0;
    $viewLabels = ['overview'=>'Ringkasan', 'monitor'=>'Monitoring KC & PIC', 'followup'=>'TL & Penawaran', 'prospects'=>'Daftar prospek', 'ecosystems'=>'Ekosistem & ring'];
@endphp

@section('styles')
@include('report._ekosistem_styles')
@endsection

@section('content')
<div class="em" id="eco-workspace">
    <header class="em-head">
        <div class="em-brand"><span class="em-brand-icon"><i class="fas fa-project-diagram" aria-hidden="true"></i></span><div><h1>Peta Prospek Ekosistem</h1><p>Peluang bisnis, penugasan PIC, dan progres tindak lanjut.</p></div></div>
        <div class="em-actions"><span class="em-subtle"><i class="fas fa-eye" aria-hidden="true"></i> Monitoring</span><a class="em-btn" href="{{ $spreadsheetUrl }}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt" aria-hidden="true"></i> Sumber</a><a class="em-btn em-btn--primary" data-em-refresh href="{{ $url(['refresh'=>1]) }}"><i class="fas fa-sync-alt" aria-hidden="true"></i> Perbarui</a></div>
    </header>
    <div class="em-location"><span><b>BRI Area 6</b> / {{ $viewLabels[$activeView] }} / {{ $kcOptions[$selectedKc] }}</span><span @class(['em-source-status','em-source-status--error'=>(bool)$error]) role="status">{{ $fetchedAt ? 'Pembaruan sumber: '.$fetchedAt : 'Sumber belum tersedia' }}</span></div>
    <nav class="em-tabs" aria-label="Tampilan monitoring">
        @foreach(['overview'=>['Ringkasan','chart-pie'],'monitor'=>['KC & PIC','users'],'followup'=>['TL & Penawaran','tasks'],'prospects'=>['Daftar prospek','list'],'ecosystems'=>['Ekosistem & ring','project-diagram']] as $key=>$tab)
            <a href="{{ $url(['view'=>$key]) }}" @if($activeView===$key) aria-current="page" 
@endif
><i class="fas fa-{{ $tab[1] }}" aria-hidden="true"></i>{{ $tab[0] }}</a>
        
@endforeach

    </nav>
    <details class="em-controls" data-em-controls data-active-filters="{{ count(array_filter(array_diff_key($filters,['kc'=>true])))>0 ? 'true' : 'false' }}" @if(count(array_filter(array_diff_key($filters,['kc'=>true])))>0) open @endif>
        <summary><span><i class="fas fa-filter" aria-hidden="true"></i> Filter monitoring <span class="em-subtle">{{ $kcOptions[$selectedKc] }}</span></span><span class="em-subtle">{{ $fetchedAt ? 'Data '.$fetchedAt : 'Sumber belum tersedia' }} <i class="fas fa-chevron-down" aria-hidden="true"></i></span></summary>
    <form method="GET" action="{{ route('ekosistem.index') }}" class="em-filter" aria-label="Filter seluruh monitoring">
        <input type="hidden" name="view" value="{{ $activeView }}">
        @if($isLocked)<input type="hidden" name="kc" value="{{ $selectedKc }}">
@endif

        @if($filters['peran'] !== '')<input type="hidden" name="peran" value="{{ $filters['peran'] }}">
@endif

        <div class="em-filter-main">
            <div class="em-field"><label for="em-kc">Wilayah{{ $isLocked ? ' · sesuai akses' : '' }}</label><select name="kc" id="em-kc" @disabled($isLocked)>@foreach($kcOptions as $value=>$label)<option value="{{ $value }}" @selected($selectedKc===$value)>{{ $label }}</option>
@endforeach
</select></div>
            <div class="em-field"><label for="em-search">Pencarian</label><input name="q" id="em-search" type="search" value="{{ $filters['q'] }}" placeholder="Prospek, ekosistem, PIC…" maxlength="200"></div>
            <div class="em-field"><label for="em-jalur">Jalur RM</label><select name="jalur" id="em-jalur"><option value="">Semua jalur</option>@foreach($choices['jalur'] as $value)<option @selected($filters['jalur']===$value)>{{ $value }}</option>
@endforeach
</select></div>
            <div class="em-field"><label for="em-condition">Kondisi tindak lanjut</label><select name="condition" id="em-condition"><option value="">Semua kondisi</option>@foreach($conditions as $value=>$label)<option value="{{ $value }}" @selected($filters['condition']===$value)>{{ $label }}</option>
@endforeach
</select></div>
            <button class="em-btn em-btn--primary" type="submit">Terapkan filter</button>
        </div>
        <details @if($extraOpen) open 
@endif
><summary>Filter lainnya</summary><div class="em-filter-extra">
            @foreach(['jenis'=>'Jenis potensi','status'=>'Status','pic'=>'PIC','ring'=>'Ring','arah'=>'Arah garapan','dasar'=>'Dasar angka','eco'=>'Ekosistem'] as $key=>$label)
                <div class="em-field"><label for="em-{{ $key }}">{{ $label }}</label><select name="{{ $key }}" id="em-{{ $key }}"><option value="">Semua</option>@if($key==='pic')<option value="__unassigned" @selected($filters['pic']==='__unassigned')>Belum diisi</option><option value="__assigned" @selected($filters['pic']==='__assigned')>Sudah diisi</option>
@endif
 @if(in_array($key,['ring','jenis']))<option value="__empty" @selected($filters[$key]==='__empty')>Belum diisi</option>
@endif
@foreach($choices[$key] as $value)<option value="{{ $value }}" @selected($filters[$key]===$value)>{{ $key==='eco' ? ($ecoNames[$value] ?? $value) : $value }}</option>
@endforeach
</select></div>
            
@endforeach

        </div></details>
        @if($filters['condition']==='nopic')<p class="em-subtle">Prioritas tanpa PIC mengikuti urutan kondisi pada webapp. Prospek yang menunggu prasyarat berada pada kondisi tersendiri; untuk seluruh PIC kosong pilih PIC ?Belum diisi?.</p>@endif
        <div class="em-filter-bottom"><span>Semua ringkasan mengikuti filter. {{ $fetchedAt ? 'Data diambil '.$fetchedAt : 'Sumber belum tersedia' }}.</span><a href="{{ route('ekosistem.index',['kc'=>$selectedKc,'view'=>$activeView]) }}">Reset filter</a></div>
        @if(count(array_filter(array_diff_key($filters,['kc'=>true])))>0)<div class="em-filter-active">@foreach($filterLabels as $key=>$label)@if($filters[$key]!=='')<a href="{{ $url([$key=>'']) }}" aria-label="Hapus filter {{ $label }}">{{ $label }}: {{ $key==='condition' ? $conditions[$filters[$key]] : ($key==='eco' ? ($ecoNames[$filters[$key]] ?? $filters[$key]) : ($filters[$key]==='__unassigned' ? 'Belum diisi' : ($filters[$key]==='__empty' ? 'Belum diisi' : ($filters[$key]==='__assigned' ? 'Sudah diisi' : $filters[$key])))) }} ×</a>
@endif
 
@endforeach
</div>
@endif

    </form>
    </details>
    @if($error)
        <div class="em-alert" role="alert">{{ $error }} Gunakan tombol Perbarui untuk mencoba kembali.</div>
    
@else

        @if($s['unit_issues']>0)
            <details class="em-notice"><summary><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <strong>{{ $num($s['unit_issues']) }} prospek perlu cek satuan</strong> · nominal yang ditandai tidak masuk rekap.</summary><div>Nilai ≥ Rp10 T per prospek ditandai karena sumber memakai juta rupiah. Nilai asli tetap dapat dilihat pada detail; tidak dikonversi atau diubah di spreadsheet. <a href="{{ $detailUrl(['condition'=>'unit']) }}">Lihat rinciannya</a></div></details>
        
@endif

        @if($activeView==='overview')
            @include('report._ekosistem_hero')
        @else
            <div class="em-view-heading"><div><h2>{{ $viewLabels[$activeView] }}</h2><p>{{ $num($s['total']) }} prospek sesuai filter pada {{ $kcOptions[$selectedKc] }}.</p></div><span class="em-badge em-badge--info"><i class="fas fa-eye mr-1" aria-hidden="true"></i> Monitoring</span></div>
        @endif
        <div class="em-finance" aria-label="Potensi dipisahkan menurut jenis, tidak dijumlahkan">
            @foreach(['Simpanan'=>['wallet','Peluang dana simpanan'],'Kredit'=>['hand-holding-usd','Peluang pembiayaan'],'Payroll'=>['users','Target orang, indikatif'],'Arus dana'=>['exchange-alt','Per tahun, bukan saldo']] as $category=>$meta)
                <a href="{{ $detailUrl(['jenis'=>$category]) }}"><span><i class="fas fa-{{ $meta[0] }}" aria-hidden="true"></i>{{ $category==='Arus dana' ? 'Arus dana / tahun' : 'Potensi '.$category }}</span><strong>{{ $category==='Payroll' ? $num($s['payroll']).' orang' : $range($s['categories'][$category]['min'],$s['categories'][$category]['max']) }}</strong><span class="em-finance-note">{{ $meta[1] }}</span></a>
            
@endforeach

        </div>
        @if($activeView==='overview')
            @include('report._ekosistem_charts')
            <div class="em-overview">
                <section class="em-panel em-map-panel" aria-labelledby="em-map-title">
                    <div class="em-title-row"><div><h2 id="em-map-title">Peta cakupan wilayah</h2><p>Pilih poligon atau nama KC untuk memfokuskan monitoring.</p></div>@if($selectedKc!=='all' && !$isLocked)<a class="em-subtle" href="{{ $url(['kc'=>'all']) }}">Semua KC</a>
@endif
</div>
                    <div class="em-map">
                        @if($geography['ready'])
                            <svg viewBox="{{ $geography['viewBox'] }}" role="group" aria-labelledby="em-map-access-title"><title id="em-map-access-title">Poligon wilayah KC yang dapat diakses. Rekap berdasarkan kolom KC pada spreadsheet.</title>
                                @foreach($geography['regions'] as $key=>$region)
                                    <a class="em-region" href="{{ $url(['kc'=>$key]) }}" style="--region-color:{{ $region['color'] }}" data-selected="{{ $selectedKc===$key ? 'true':'false' }}" aria-label="KC {{ $region['label'] }}, {{ $branches[$key]['stats']['total'] }} prospek. Pilih wilayah.">
                                        <title>KC {{ $region['label'] }} · {{ $branches[$key]['stats']['total'] }} prospek</title>
                                        @foreach($region['paths'] as $path)<path d="{{ $path }}" fill-rule="evenodd"/>
@endforeach

                                        <rect x="{{ $region['x']-52 }}" y="{{ $region['y']-20 }}" width="104" height="43" rx="7"/>
                                        <text x="{{ $region['x'] }}" y="{{ $region['y']-2 }}">{{ $region['label'] }}</text><text class="em-map-count" x="{{ $region['x'] }}" y="{{ $region['y']+13 }}">{{ $num($branches[$key]['stats']['total']) }} prospek</text>
                                    </a>
                                
@endforeach

                                <g transform="translate({{ $geography['north']['x'] }},{{ $geography['north']['y'] }})"><path d="M0 25V-12M-5-4L0-12L5-4" stroke="#647e99" fill="none"/><text x="0" y="-20" text-anchor="middle" fill="#536a83" font-size="11">U</text></g>
                            </svg>
                        
@else
<div class="em-map-empty">Peta batas wilayah belum tersedia. Pilih wilayah melalui rekap KC di bawah.</div>
@endif

                    </div>
                    <div class="em-map-legend">@foreach($branches as $key=>$branch)<a href="{{ $url(['kc'=>$key]) }}" style="--region-color:{{ $geography['regions'][$key]['color'] ?? '#0857c3' }}"><i aria-hidden="true"></i>{{ $branch['label'] }}<strong>{{ $num($branch['stats']['total']) }} prospek</strong></a>
@endforeach
</div>
                    <p class="em-map-note">{{ $geography['source'] }}. Poligon menunjukkan batas administrasi, bukan lokasi nasabah. Rekap KC mengikuti filter selain wilayah.</p>
                </section>
                <section class="em-panel" aria-labelledby="em-execution-title">
                    <div class="em-title-row"><div><h2 id="em-execution-title">Eksekusi {{ $kcOptions[$selectedKc] }}</h2><p>Prospek yang sesuai seluruh filter.</p></div></div>
                    <div class="em-total"><div><strong>{{ $num($s['total']) }}</strong><span>prospek pada {{ $num($s['ecosystems']) }} ekosistem terdaftar</span>@if($s['unmapped_ecosystem']>0)<a class="em-subtle" href="{{ $detailUrl(['condition'=>'unmapped']) }}">{{ $s['unmapped_ecosystem'] }} prospek belum terhubung ke master</a>@endif</div><a href="{{ $detailUrl() }}" class="em-btn">Lihat detail <i class="fas fa-arrow-right" aria-hidden="true"></i></a></div>
                    <div class="em-execution"><a href="{{ $detailUrl(['status'=>'Berjalan']) }}"><b>{{ $num($s['statuses']['Berjalan']) }}</b><span>Berjalan</span></a><a href="{{ $detailUrl(['status'=>'Selesai']) }}"><b>{{ $num($s['statuses']['Selesai']) }}</b><span>Selesai</span></a><a href="{{ $detailUrl(['pic'=>'__assigned']) }}"><b>{{ $s['coverage'] }}%</b><span>PIC terisi · {{ $s['assigned'] }}/{{ $s['total'] }}</span></a></div>
                    @php $colors=['Belum mulai'=>'#d9e8f9','Berjalan'=>'#307fe2','Tertunda'=>'#e3b761','Selesai'=>'#0a4d98','Batal'=>'#a9b6c6']; @endphp
                    <div class="em-stack" role="img" aria-label="Komposisi status; rincian jumlah pada legenda berikut.">@foreach($s['statuses'] as $status=>$total)<span style="width:{{ $s['total']>0 ? $total/$s['total']*100:0 }}%;background:{{ $colors[$status] ?? '#9bacc2' }}"></span>
@endforeach
</div>
                    <div class="em-statuses">@foreach($s['statuses'] as $status=>$total)<a href="{{ $detailUrl(['status'=>$status]) }}"><i class="em-dot" style="background:{{ $colors[$status] ?? '#9bacc2' }}" aria-hidden="true"></i>{{ $status }}<b>{{ $num($total) }}</b></a>
@endforeach
</div>
                    <div class="em-real"><span>Realisasi tercatat<br>{{ $num($s['customers']) }} nasabah terealisasi</span><strong>{{ $money($s['realization']) }}</strong></div>
                    <div class="em-attention">@foreach(['late'=>'Lewat target','stale'=>'Mandek > 14 hari','unassigned'=>'PIC belum diisi'] as $key=>$label)<a href="{{ $detailUrl($key==='unassigned' ? ['pic'=>'__unassigned'] : ['condition'=>$key]) }}"><b>{{ $num($key==='unassigned' ? $s['unassigned'] : $s['conditions'][$key]) }}</b><span>{{ $label }}</span></a>
@endforeach
</div>
                </section>
            </div>
        
@endif


        @if(in_array($activeView,['overview','monitor']))
            <section class="em-panel em-section" aria-labelledby="em-branch-title">
                <div class="em-title-row"><div><h2 id="em-branch-title">Rapor kantor cabang</h2><p>Perbandingan KC yang dapat diakses; filter lain tetap berlaku. Klik angka untuk membuka daftar.</p></div>@if($activeView==='overview')<a href="{{ $url(['view'=>'monitor']) }}">Lihat KC & PIC <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
@endif
</div>
                <div class="em-scroll" tabindex="0" role="region" aria-label="Rapor per KC" data-abah-no-table-guard="1"><table class="em-table" data-abah-no-table-guard="1"><thead><tr><th scope="col">Kantor cabang</th><th scope="col">Prospek</th><th scope="col">PIC terisi</th><th scope="col">Berjalan</th><th scope="col">Selesai</th><th scope="col">Perlu perhatian</th><th scope="col" class="em-right">Est. simpanan</th><th scope="col" class="em-right">Est. kredit</th><th scope="col" class="em-right">Realisasi</th></tr></thead><tbody>
                    @foreach($branches as $key=>$branch) @php $b=$branch['stats']; @endphp
                    <tr><td class="em-kc"><a href="{{ $url(['kc'=>$key]) }}">{{ $branch['label'] }}</a></td><td><a href="{{ $detailUrl(['kc'=>$key]) }}">{{ $num($b['total']) }}</a></td><td><span class="em-coverage">{{ $b['coverage'] }}% <span aria-hidden="true"><i style="width:{{ $b['coverage'] }}%"></i></span></span><span class="em-subtle">{{ $b['assigned'] }}/{{ $b['total'] }} prospek</span></td><td><a href="{{ $detailUrl(['kc'=>$key,'status'=>'Berjalan']) }}">{{ $num($b['statuses']['Berjalan']) }}</a></td><td><a href="{{ $detailUrl(['kc'=>$key,'status'=>'Selesai']) }}">{{ $num($b['statuses']['Selesai']) }}</a></td><td><a href="{{ $detailUrl(['kc'=>$key,'condition'=>'late']) }}">{{ $b['conditions']['late'] }} lewat target</a><span class="em-subtle">{{ $b['conditions']['stale'] }} mandek</span></td><td class="em-right">{{ $range($b['categories']['Simpanan']['min'],$b['categories']['Simpanan']['max']) }}</td><td class="em-right">{{ $range($b['categories']['Kredit']['min'],$b['categories']['Kredit']['max']) }}</td><td class="em-right">{{ $money($b['realization']) }}</td></tr>
                    
@endforeach

                </tbody></table></div>
            </section>
        
@endif


        @if($activeView==='overview')
            <div class="em-bottom-grid">
                <section class="em-panel" aria-labelledby="em-priority-title"><div class="em-title-row"><div><h2 id="em-priority-title">Prioritas tindak lanjut</h2><p>Urutan kondisi mengikuti webapp; prospek menunggu prasyarat dipisahkan dari prioritas tanpa PIC.</p></div><span class="em-badge em-badge--warning">{{ $num($attention) }} prospek</span></div>
                    @if(empty($priority))<div class="em-empty"><strong>Tidak ada prioritas pada filter ini</strong>Ubah filter untuk meninjau cakupan lainnya.</div>
@else
<ul class="em-priority">@foreach(array_slice($priority,0,5) as $row)<li><a href="{{ $detailUrl(['q'=>$row['ID_Prospek']]) }}"><span><strong>{{ $row['Prospek'] }}</strong><span class="em-subtle">{{ $row['ID_Prospek'] }} · KC {{ $row['KC'] }} · {{ $row['Nama_PIC'] ?: ($row['Peran_PIC'] ?? 'PIC belum diisi') }}</span></span><span class="em-badge em-badge--{{ $row['Kondisi']['tone'] }}">{{ $row['Kondisi']['label'] }}</span></a></li>
@endforeach
</ul>
@endif

                </section>
                <section class="em-panel" aria-labelledby="em-types-title"><div class="em-title-row"><div><h2 id="em-types-title">Komposisi peluang</h2><p>Jumlah prospek per jenis potensi, bukan penjumlahan nominal.</p></div></div><div class="em-bar-list">@foreach($s['types'] as $type=>$total)<a href="{{ $detailUrl(['jenis'=>$type==='Belum diklasifikasi' ? '__empty' : $type]) }}"><span>{{ $type }}</span><span class="em-bar" aria-hidden="true"><i style="width:{{ $s['total']>0 ? $total/$s['total']*100:0 }}%"></i></span><b>{{ $num($total) }}</b></a>
@endforeach
</div></section>
            </div>
            <section class="em-panel em-section" aria-labelledby="em-activity-title"><div class="em-title-row"><h2 id="em-activity-title">Aktivitas terakhir</h2><span class="em-subtle">Tercatat pada sumber</span></div>@if(empty($activity))<p class="em-subtle">Belum ada aktivitas untuk prospek pada filter ini.</p>
@else
<ul class="em-log">@foreach(array_slice($activity,0,5) as $log)<li><time>{{ $log['Waktu'] }}</time><a href="{{ $detailUrl(['q'=>$log['ID_Prospek']]) }}">{{ $log['ID_Prospek'] }}</a><span>{{ $log['Kolom'] }}: @if(($log['Nilai_Lama'] ?? '')!=='')<s>{{ $log['Nilai_Lama'] }}</s> → 
@endif
 {{ $log['Nilai_Baru'] }}</span></li>
@endforeach
</ul>
@endif
</section>
        
@endif


        @if($activeView==='monitor')
            <section class="em-panel em-section" aria-labelledby="em-matrix-title"><div class="em-title-row"><div><h2 id="em-matrix-title">Matriks KC × jalur RM</h2><p>Selesai / total prospek. Kuning menandai prospek lewat target atau mandek.</p></div></div><div class="em-scroll" tabindex="0" role="region" aria-label="Matriks KC dan jalur RM" data-abah-no-table-guard="1"><table class="em-table em-matrix" data-abah-no-table-guard="1"><thead><tr><th scope="col">KC</th>@foreach($jalurOptions as $jalur)<th scope="col">{{ $jalur }}</th>
@endforeach
</tr></thead><tbody>@foreach($branches as $key=>$branch)<tr><td class="em-kc">{{ $branch['label'] }}</td>@foreach($jalurOptions as $jalur)@php $cell=$matrix[$key][$jalur]; @endphp<td><a href="{{ $detailUrl(['kc'=>$key,'jalur'=>$jalur]) }}" @class(['em-cell-attention'=>$cell['conditions']['late']+$cell['conditions']['stale']>0])><b>{{ $cell['statuses']['Selesai'] }}</b> / {{ $cell['total'] }}<span class="em-subtle">{{ $cell['conditions']['late'] }} lewat · {{ $cell['conditions']['stale'] }} mandek</span></a></td>
@endforeach
</tr>
@endforeach
</tbody></table></div></section>
            <section class="em-panel em-section" aria-labelledby="em-pic-title"><div class="em-title-row"><div><h2 id="em-pic-title">Beban dan eksekusi PIC</h2><p>Nama mengikuti sumber; PIC kosong dikelompokkan menurut peran dan jalur RM.</p></div><span class="em-subtle">{{ count($picGroups) }} kelompok</span></div><div class="em-scroll" tabindex="0" role="region" aria-label="Rekap PIC" data-abah-no-table-guard="1"><table class="em-table" data-abah-no-table-guard="1"><thead><tr><th scope="col">PIC / peran</th><th scope="col">KC & jalur</th><th scope="col">Prospek</th><th scope="col">Belum mulai</th><th scope="col">Berjalan</th><th scope="col">Selesai</th><th scope="col">Lewat / mandek</th><th scope="col" class="em-right">Realisasi</th></tr></thead><tbody>
            @forelse($picPaginator as $group)@php $p=$group['stats']; $picLink=['kc'=>mb_strtolower($group['kc']),'pic'=>$group['pic'] ?: '__unassigned','jalur'=>$group['jalur'],'peran'=>$group['pic']!=='' ? '' : ($group['peran'] ?: '__empty')]; @endphp<tr><td><a href="{{ $detailUrl($picLink) }}">{{ $group['label'] }}</a></td><td>{{ $group['kc'] }}<span class="em-subtle">{{ $group['jalur'] }}</span></td><td><a href="{{ $detailUrl($picLink) }}">{{ $p['total'] }}</a></td><td>{{ $p['statuses']['Belum mulai'] }}</td><td>{{ $p['statuses']['Berjalan'] }}</td><td>{{ $p['statuses']['Selesai'] }}</td><td>{{ $p['conditions']['late'] }} / {{ $p['conditions']['stale'] }}</td><td class="em-right">{{ $money($p['realization']) }}</td></tr>
@empty
<tr><td colspan="8"><div class="em-empty">Tidak ada PIC sesuai filter.</div></td></tr>
@endforelse

            </tbody></table></div><div class="em-pagination"><span class="em-subtle">{{ $picPaginator->firstItem() ?? 0 }}?{{ $picPaginator->lastItem() ?? 0 }} dari {{ $picPaginator->total() }} kelompok PIC</span><div class="em-actions">@if($picPaginator->previousPageUrl())<a class="em-btn" href="{{ $picPaginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>@endif @if($picPaginator->nextPageUrl())<a class="em-btn" href="{{ $picPaginator->nextPageUrl() }}" rel="next">Berikutnya</a>@endif</div></div></section>
        
@endif


        @if($activeView==='ecosystems')
            @if($s['unmapped_ecosystem']>0)<p class="em-notice" style="padding:.75rem 1rem">{{ $s['unmapped_ecosystem'] }} prospek belum terhubung ke master ekosistem. Data ini tetap dihitung dalam total prospek. <a href="{{ $detailUrl(['condition'=>'unmapped']) }}">Lihat daftar</a></p>@endif
            <section class="em-panel em-section" aria-labelledby="em-rings-title"><div class="em-title-row"><div><h2 id="em-rings-title">Cakupan ring per ekosistem</h2><p>Klik ring untuk melihat prospek. Rincian arah: akuisisi, pendalaman, dan pendataan.</p></div></div><div class="em-scroll" tabindex="0" role="region" aria-label="Cakupan ring ekosistem" data-abah-no-table-guard="1"><table class="em-table" data-abah-no-table-guard="1"><thead><tr><th scope="col">Ekosistem</th><th scope="col">KC</th><th scope="col">L1 · Inti</th><th scope="col">L2 · Pemasok</th><th scope="col">L3 · Turunan</th><th scope="col">Belum dipetakan</th><th scope="col">Selesai</th></tr></thead><tbody>
            @forelse($ecosystems as $eco)@php $ecoRows=array_values(array_filter($prospects,static fn($p)=>$p['ID_Ekosistem']===$eco['ID_Ekosistem'])); @endphp<tr><td><a href="{{ $detailUrl(['eco'=>$eco['ID_Ekosistem']]) }}">{{ $eco['Nama_Ekosistem'] }}</a></td><td>{{ $eco['KC'] }}</td>@foreach(['L1','L2','L3',''] as $ring)@php $rr=array_values(array_filter($ecoRows,static fn($p)=>($p['Ring'] ?? '')===$ring)); $directions=array_count_values(array_column($rr,'Arah')); @endphp<td class="em-ring-cell"><a href="{{ $detailUrl(['eco'=>$eco['ID_Ekosistem'],'ring'=>$ring ?: '__empty']) }}">{{ count($rr) }} prospek</a><span>{{ $directions['Akuisisi'] ?? 0 }} akuisisi · {{ $directions['Pendalaman'] ?? 0 }} pendalaman · {{ $directions['Pendataan'] ?? 0 }} pendataan</span></td>
@endforeach
<td>{{ $eco['status']['Selesai'] }} / {{ $eco['total'] }}</td></tr>
@empty
<tr><td colspan="7"><div class="em-empty">Tidak ada ekosistem sesuai filter.</div></td></tr>
@endforelse

            </tbody></table></div></section>
            <div class="em-eco-grid">@foreach($ecosystems as $eco)<article class="em-eco"><span class="em-badge em-badge--info">KC {{ $eco['KC'] }}</span><h3>{{ $eco['Nama_Ekosistem'] }}</h3><p>{{ $eco['Jenis'] }}{{ ($eco['Lokasi'] ?? '')!=='' ? ' · '.$eco['Lokasi'] : '' }}</p>@if(($eco['Fakta_Kunci'] ?? '')!=='')<p>{{ $eco['Fakta_Kunci'] }}</p>
@endif
<p><strong>Posisi BRI:</strong> {{ ($eco['Posisi_BRI'] ?? '') ?: 'Belum dicatat' }}</p><p><strong>Bank pesaing:</strong> {{ ($eco['Bank_Pesaing'] ?? '') ?: 'Belum dicatat' }}</p><div class="em-eco-rings">@foreach(['L1','L2','L3'] as $ring)<a href="{{ $detailUrl(['eco'=>$eco['ID_Ekosistem'],'ring'=>$ring]) }}" title="{{ $ringOptions[$ring] }}">{{ $ring }}<b>{{ $eco['ring'][$ring] }}</b>prospek</a>
@endforeach
</div></article>
@endforeach
</div>
        
@endif


        @if($activeView==='followup')
            <div class="em-tl-metrics" aria-label="Kelengkapan catatan tindak lanjut dan penawaran">
                @foreach(['all'=>['Semua prospek',$followupSummary['total']],'followup'=>['Kolom TL terisi',$followupSummary['followup']],'offer'=>['Kolom penawaran terisi',$followupSummary['offer']],'empty'=>['Belum ada isian TL / penawaran',$followupSummary['empty']]] as $key=>$metric)
                    <a href="{{ $url(['followup'=>$key,'tl_page'=>null]) }}" aria-current="{{ $followupFilter===$key ? 'true' : 'false' }}"><span>{{ $metric[0] }}</span><strong>{{ $num($metric[1]) }}</strong></a>
                @endforeach
            </div>
            <section class="em-panel em-section" aria-labelledby="em-followup-title">
                <div class="em-title-row"><div><h2 id="em-followup-title">Progres TL & Penawaran</h2><p>Penawaran mengikuti isian sumber, termasuk pilihan “Tidak”. Satu prospek dapat memiliki isian TL dan penawaran.</p></div><span class="em-subtle">{{ $num($followupPaginator->total()) }} prospek</span></div>
                <div class="em-scroll" tabindex="0" role="region" aria-label="Monitoring catatan tindak lanjut dan penawaran" data-abah-no-table-guard="1">
                    <table class="em-table em-followup-table" data-abah-no-table-guard="1"><thead><tr><th scope="col">KC & PIC</th><th scope="col">Ekosistem & prospek</th><th scope="col">Tindak lanjut tercatat</th><th scope="col">Penawaran tercatat</th><th scope="col">Informasi lainnya</th></tr></thead><tbody>
                    @forelse($followupPaginator as $row)
                        <tr data-prospect-row="{{ $row['ID_Prospek'] }}">
                            <td><strong>KC {{ $row['KC'] }}</strong><span class="em-subtle">{{ $row['Nama_PIC'] ?: 'PIC belum diisi' }}</span><span class="em-subtle">{{ $row['Jalur_RM'] }}</span><span class="em-badge em-badge--{{ $statusTone[$row['Status']] ?? 'muted' }}">{{ $row['Status'] }}</span></td>
                            <td class="em-prospect"><button type="button" data-em-detail="{{ $row['ID_Prospek'] }}" aria-haspopup="dialog">{{ $row['Prospek'] }}</button><span class="em-subtle">{{ $row['ID_Prospek'] }} · {{ $row['Ekosistem'] }}</span><span class="em-subtle">{{ $row['Produk'] }}</span></td>
                            @foreach(['followup','offer','other'] as $group)
                                <td>@if($row['Monitoring_Fields'][$group]!==[])<dl class="em-recorded">@foreach($row['Monitoring_Fields'][$group] as $field)<div><dt>{{ $field['label'] }}</dt><dd>{{ match(strtoupper($field['value'])) { 'TRUE'=>'Ya', 'FALSE'=>'Tidak', default=>$field['value'] } }}</dd></div>@endforeach</dl>@else<span class="em-subtle">Belum ada isian</span>@endif</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="em-empty"><strong>Tidak ada prospek sesuai filter</strong>Ubah filter catatan atau pilih cakupan yang lebih luas.</div></td></tr>
                    @endforelse
                    </tbody></table>
                </div>
                <div class="em-pagination"><span class="em-subtle">{{ $followupPaginator->firstItem() ?? 0 }}–{{ $followupPaginator->lastItem() ?? 0 }} dari {{ $num($followupPaginator->total()) }} prospek</span><div class="em-actions">@if($followupPaginator->previousPageUrl())<a class="em-btn" rel="prev" href="{{ $followupPaginator->previousPageUrl() }}">Sebelumnya</a>@endif @if($followupPaginator->nextPageUrl())<a class="em-btn" rel="next" href="{{ $followupPaginator->nextPageUrl() }}">Berikutnya</a>@endif</div></div>
            </section>
        @endif

        @if($activeView==='prospects')
            <section class="em-panel em-section" aria-labelledby="em-prospects-title"><div class="em-title-row"><div><h2 id="em-prospects-title">Detail prospek</h2><p>Klik nama prospek untuk membuka seluruh rincian dalam mode baca.</p></div><span class="em-subtle" id="em-record-count">{{ $num($paginator->total()) }} prospek sesuai filter</span></div>
            @if($paginator->isEmpty())<div class="em-empty"><strong>Tidak ada prospek sesuai filter</strong>Coba perluas pilihan wilayah atau reset filter. <a href="{{ route('ekosistem.index',['view'=>'prospects','kc'=>$selectedKc]) }}">Reset filter</a></div>
@else

            <div class="em-scroll" data-abah-no-table-guard="1"><table class="em-table em-prospect-table" data-abah-no-table-guard="1"><caption class="sr-only">Daftar prospek sesuai filter. Klik nama untuk membuka rincian.</caption><thead><tr><th scope="col">Prospek & ekosistem</th><th scope="col">PIC / jalur RM</th><th scope="col">Status & kondisi</th><th scope="col">Target</th><th scope="col" class="em-right">Estimasi</th><th scope="col" class="em-right">Realisasi</th></tr></thead><tbody>
            @foreach($paginator as $row)<tr data-prospect-row="{{ $row['ID_Prospek'] }}"><td class="em-prospect" data-label="Prospek"><button type="button" data-em-detail="{{ $row['ID_Prospek'] }}" aria-haspopup="dialog">{{ $row['Prospek'] }}</button><span class="em-subtle">{{ $row['ID_Prospek'] }} · KC {{ $row['KC'] }} · {{ $row['Ekosistem'] }}</span><span class="em-subtle">{{ $row['Produk'] }} · {{ $row['Ring'] ?: 'Ring belum diisi' }} · {{ $row['Arah'] }}</span>@if(!empty($row['Cek_Satuan']))<span class="em-badge em-badge--warning">Cek satuan nominal</span>
@endif
</td><td data-label="PIC / jalur">{{ $row['Nama_PIC'] ?: 'Belum diisi' }}<span class="em-subtle">{{ $row['Jalur_RM'] }}</span></td><td data-label="Status"><span class="em-badge em-badge--{{ $statusTone[$row['Status']] ?? 'muted' }}">{{ $row['Status'] }}</span>@if(!in_array($row['Kondisi']['key'],['done','cancel']))<span class="em-subtle"><span class="em-badge em-badge--{{ $row['Kondisi']['tone'] }}">{{ $row['Kondisi']['label'] }}</span></span>
@endif
<span class="em-subtle">{{ $row['Tahap'] }}</span></td><td data-label="Target">{{ $date($row['Target_Selesai']) }}</td><td class="em-right" data-label="Estimasi">{{ $range($row['Est_Min_Juta'] ?? null,$row['Est_Max_Juta'] ?? null) }}<span class="em-subtle">{{ ($row['Jenis_Potensi'] ?? '') ?: 'Belum diklasifikasi' }}@if(($row['Est_Nasabah'] ?? null)!==null) · {{ $num($row['Est_Nasabah']) }} nasabah
@endif
</span></td><td class="em-right" data-label="Realisasi">{{ $money($row['Realisasi_Nominal_Juta'] ?? null) }}<span class="em-subtle">{{ $num($row['Realisasi_Nasabah'] ?? 0) }} nasabah</span></td></tr>
@endforeach

            </tbody></table></div><div class="em-pagination"><span class="em-subtle">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $num($paginator->total()) }} prospek · halaman {{ $paginator->currentPage() }}/{{ $paginator->lastPage() }}</span><div class="em-actions">@if($paginator->previousPageUrl())<a class="em-btn" rel="prev" href="{{ $paginator->previousPageUrl() }}">Sebelumnya</a>
@endif
 @if($paginator->nextPageUrl())<a class="em-btn" rel="next" href="{{ $paginator->nextPageUrl() }}">Berikutnya</a>
@endif
</div></div>
            
@endif
</section>
        
@endif

        @if(in_array($activeView,['prospects','followup']))
            <dialog class="em-dialog" id="em-detail-dialog" aria-labelledby="em-detail-title"><div class="em-dialog-head"><div><span class="em-subtle" id="em-detail-id"></span><h2 id="em-detail-title">Rincian prospek</h2></div><button type="button" class="em-btn" id="em-detail-close" aria-label="Tutup rincian prospek">Tutup</button></div><div class="em-dialog-body" id="em-detail-body"></div></dialog>
        @endif

        <p class="em-method">Nominal sumber menggunakan juta rupiah. Simpanan, kredit, dan arus dana tahunan disajikan terpisah. Estimasi dan target nasabah bersifat indikatif dan dapat tumpang tindih. Monitoring ini hanya membaca sumber; pembaruan tetap mengikuti proses pada spreadsheet/webapp.</p>
    
@endif

</div>
@endsection

@section('scripts')
<script>
(function () {
    const refresh = document.querySelector('[data-em-refresh]');
    if (refresh) refresh.addEventListener('click', function (event) {
        if (this.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
        this.setAttribute('aria-busy', 'true');
        this.textContent = 'Memperbarui…';
    });
    const form = document.querySelector('.em-filter');
    if (form) form.addEventListener('submit', function () {
        const button = this.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Menerapkan…';
    });
    const controls = document.querySelector('[data-em-controls]');
    if (controls && controls.dataset.activeFilters !== 'true' && window.matchMedia('(max-width: 575.98px)').matches) {
        controls.open = false;
    }
    const dialog = document.getElementById('em-detail-dialog');
    if (!dialog) return;
    const records = @json($detailRecords);
    const number = new Intl.NumberFormat('id-ID', {maximumFractionDigits: 1});
    const nominal = value => value === null || value === undefined || value === '' ? 'Belum dicatat' : 'Rp'+number.format(Number(value))+' juta';
    const body = document.getElementById('em-detail-body');
    const close = document.getElementById('em-detail-close');
    document.querySelectorAll('[data-em-detail]').forEach(button => button.addEventListener('click', function () {
        const record = records[this.dataset.emDetail];
        if (!record) return;
        document.getElementById('em-detail-title').textContent = record.Prospek;
        document.getElementById('em-detail-id').textContent = record.ID_Prospek+' · KC '+record.KC+' · Mode baca';
        body.replaceChildren();
        const sections = [
            ['Identitas & cakupan', [['Ekosistem',record.Ekosistem],['Jalur RM',record.Jalur_RM],['Produk',record.Produk],['Sasaran',record.Sasaran],['Ring',record.Ring],['Simpul',record.Simpul],['Arah garapan',record.Arah],['Status di BRI',record.Status_BRI],['Bank lain',record.Bank_Lain]]],
            ['PIC & tindak lanjut', [['PIC',record.Nama_PIC],['Peran',record.Peran_PIC],['Status',record.Status],['Kondisi',record.Kondisi.label],['Tahap',record.Tahap],['Mulai',record.Tgl_Mulai],['Target',record.Target_Selesai],['Selesai',record.Tgl_Selesai],['Prasyarat',record.Prasyarat_ID],['Pembaruan',record.Diubah_Pada]]],
            ['Potensi & realisasi', [['Jenis potensi',record.Jenis_Potensi],['Estimasi minimum',nominal(record.Est_Min_Juta)],['Estimasi maksimum',nominal(record.Est_Max_Juta)],['Target nasabah',record.Est_Nasabah],['Realisasi',nominal(record.Realisasi_Nominal_Juta)],['Nasabah terealisasi',record.Realisasi_Nasabah],['Dasar angka',record.Dasar_Angka],['Pemeriksaan satuan',(record.Cek_Satuan || []).map(issue=>issue.label+': '+number.format(issue.value)+' juta (nilai asli; tidak masuk rekap)').join('\n')]]],
            ['Sumber & catatan', [['Sumber',record.Sumber],['Catatan analis',record.Catatan_Analis],['Kendala',record.Kendala],['Catatan tindak lanjut',record.Catatan]]]
        ];
        const extraGroups = record.Monitoring_Fields || {};
        [['followup','Tindak lanjut tercatat'],['offer','Penawaran tercatat'],['other','Informasi tambahan']].forEach(([key,label]) => {
            const fields = extraGroups[key] || [];
            if (fields.length) sections.push([label, fields.map(field => [field.label, String(field.value).toUpperCase()==='TRUE' ? 'Ya' : (String(field.value).toUpperCase()==='FALSE' ? 'Tidak' : field.value)])]);
        });
        sections.forEach(([title, fields]) => {
            const section = document.createElement('section');
            const heading = document.createElement('h3'); heading.textContent = title;
            const list = document.createElement('dl');
            fields.forEach(([label, value]) => {
                const term = document.createElement('dt'); term.textContent = label;
                const definition = document.createElement('dd'); definition.textContent = value === null || value === undefined || value === '' ? 'Belum diisi' : String(value);
                list.append(term, definition);
            });
            section.append(heading, list); body.append(section);
        });
        dialog.showModal();
        close.focus();
    }));
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) {const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom) dialog.close();} });
})();
</script>
@if($activeView==='overview' && ! $error)
    <script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>
    @include('report._ekosistem_charts_script')
@endif
@endsection
