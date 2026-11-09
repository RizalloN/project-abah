<section class="em-hero" aria-labelledby="em-overview-title">
    <div class="em-hero-copy">
        <span class="em-scope"><i class="fas fa-map-marker-alt" aria-hidden="true"></i> {{ $kcOptions[$selectedKc] }}</span>
        <h2 id="em-overview-title">Peluang terhubung.<br>Eksekusi terpantau.</h2>
        <p>Pahami jejaring bisnis, ikuti progres prospek, dan fokuskan tindak lanjut pada peluang yang tepat.</p>
        <div class="em-hero-actions">
            <a class="em-btn em-btn--primary" href="{{ $detailUrl() }}">Jelajahi prospek <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            <a class="em-text-link" href="{{ $url(['view'=>'followup']) }}">Pantau tindak lanjut <i class="fas fa-arrow-up" aria-hidden="true"></i></a>
        </div>
        <div class="em-hero-footer"><span><b>{{ $num($s['total']) }}</b> prospek terpetakan</span><span><b>{{ $num($s['ecosystems']) }}</b> ekosistem</span><span><b>{{ $completion }}%</b> selesai</span></div>
    </div>
    <div class="em-network" aria-label="Jejaring prospek menurut ring ekosistem">
        <svg class="em-network-lines" viewBox="0 0 520 300" aria-hidden="true">
            <defs><radialGradient id="em-network-glow"><stop offset="0" stop-color="#71c5e8" stop-opacity=".32"/><stop offset="1" stop-color="#307fe2" stop-opacity="0"/></radialGradient></defs>
            <circle cx="260" cy="150" r="145" fill="url(#em-network-glow)"/>
            <g class="em-network-orbits" fill="none" stroke="#c4e3ff" stroke-opacity=".28"><ellipse cx="260" cy="150" rx="111" ry="77" transform="rotate(-24 260 150)"/><ellipse cx="260" cy="150" rx="171" ry="111" transform="rotate(-24 260 150)"/><ellipse cx="260" cy="150" rx="230" ry="139" transform="rotate(-24 260 150)"/></g>
            <g stroke="#b8dcff" stroke-width="1.4" stroke-dasharray="4 6" stroke-opacity=".65"><path d="M260 150L100 66M260 150L414 96M260 150L375 242M260 150L101 229"/></g>
            <g fill="#fff"><circle cx="171" cy="136" r="4"/><circle cx="312" cy="69" r="3"/><circle cx="175" cy="241" r="3"/><circle cx="429" cy="191" r="4"/><circle cx="353" cy="163" r="3"/></g>
        </svg>
        <a class="em-network-core" href="{{ $url(['view'=>'ecosystems']) }}"><i class="fas fa-project-diagram" aria-hidden="true"></i><strong>{{ $num($s['ecosystems']) }}</strong><span>Ekosistem</span></a>
        @foreach(['L1'=>['Inti ekosistem','em-node--one'],'L2'=>['Rantai pasok','em-node--two'],'L3'=>['Jejaring turunan','em-node--three']] as $ring=>$meta)
            <a class="em-network-node {{ $meta[1] }}" href="{{ $detailUrl(['ring'=>$ring]) }}"><span class="em-ring-id">{{ $ring }}</span><span>{{ $meta[0] }}<b>{{ $num($ringCounts[$ring] ?? 0) }} <small>prospek</small></b></span></a>
        @endforeach
        <span class="em-network-caption">Jejaring peluang Area 6</span>
    </div>
</section>
