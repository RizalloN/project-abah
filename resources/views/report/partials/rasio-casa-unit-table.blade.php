<div class="table-container">
    <table class="table table-report casa-no-hover m-0">
        <thead>
            <tr>
                <th rowspan="3" class="bg-header-main sticky-col align-middle" style="min-width: 190px;">UNIT KERJA</th>
                @foreach($segments as $segmentIndex => $segment)
                    <th colspan="15" class="bg-header-main" @if($segmentIndex > 0) style="border-left: 2px solid rgba(255,255,255,0.4) !important;" @endif>
                        {{ $segment['label'] }}
                    </th>
                @endforeach
            </tr>
            <tr class="bg-header-sub">
                @foreach($segments as $segmentIndex => $segment)
                    <th colspan="4" @if($segmentIndex > 0) style="border-left: 2px solid rgba(255,255,255,0.4) !important;" @endif>Total OS</th>
                    <th colspan="4">Total CASA</th>
                    <th colspan="7">Rasio CASA/OS</th>
                @endforeach
            </tr>
            <tr class="bg-header-sub-light">
                @foreach($segments as $segmentIndex => $segment)
                    <th class="lbl-ytd-th" @if($segmentIndex > 0) style="border-left: 2px solid rgba(255,255,255,0.4) !important;" @endif>-</th>
                    <th class="lbl-m2-th">-</th>
                    <th class="lbl-prev-th">-</th>
                    <th class="lbl-curr-th">-</th>
                    <th class="lbl-ytd-th">-</th>
                    <th class="lbl-m2-th">-</th>
                    <th class="lbl-prev-th">-</th>
                    <th class="lbl-curr-th">-</th>
                    <th class="lbl-ytd-th">-</th>
                    <th class="lbl-m2-th">-</th>
                    <th class="lbl-prev-th">-</th>
                    <th class="lbl-curr-th">-</th>
                    <th>MtD</th>
                    <th>M-2</th>
                    <th>YtD</th>
                @endforeach
            </tr>
        </thead>
        <tbody id="{{ $tbodyId }}"></tbody>
    </table>
</div>
