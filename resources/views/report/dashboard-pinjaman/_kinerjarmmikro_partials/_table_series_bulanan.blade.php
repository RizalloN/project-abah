<div class="rm-mikro-table-wrap">
    <table class="rm-mikro-table" style="min-width: 1900px;">
        <thead>
            <tr>
                <th rowspan="2">No</th><th rowspan="2">Kanca</th><th rowspan="2">PN</th><th rowspan="2">Nama</th><th rowspan="2">BC UKER</th><th rowspan="2">UKER</th><th rowspan="2">TMT Jabatan</th><th rowspan="2">Bulan Masuk</th>
                @foreach (($payload['months'] ?? []) as $month)
                    <th colspan="2" class="group-head">Real {{ $month['label'] }}</th>
                @endforeach
                <th colspan="2" class="group-head">Akumulasi s.d {{ $selectedMonthLabel }}</th>
            </tr>
            <tr>
                @foreach (($payload['months'] ?? []) as $month)
                    <th>Deb</th><th>Plafon Juta</th>
                @endforeach
                <th>Deb</th><th>Plafon Juta</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td><td>{{ $row['cabang'] }}</td><td>{{ $row['pn'] }}</td><td class="strong">{{ $row['nama'] }}</td><td>{{ $row['branch_code'] }}</td><td>{{ $row['unit'] }}</td><td>{{ $row['tmt_jabatan'] }}</td><td>{{ $row['bulan_masuk'] }}</td>
                    @foreach (($payload['months'] ?? []) as $month)
                        @php $monthData = $row['months'][$month['key']] ?? ['deb' => 0, 'os' => 0]; @endphp
                        <td class="text-right">{{ $formatAmount($monthData['deb']) }}</td>
                        <td class="text-right {{ $achievementClass(($monthData['os'] ?? 0) / 1000000, $targetMonthlyJuta) }}">{{ $formatJuta($monthData['os']) }}</td>
                    @endforeach
                    <td class="text-right strong">{{ $formatAmount($row['total_deb']) }}</td>
                    <td class="text-right {{ $achievementClass(($row['total_os'] ?? 0) / 1000000, $targetMonthlyJuta) }}">{{ $formatJuta($row['total_os']) }}</td>
                </tr>
            @empty
                <tr><td colspan="34" class="rm-mikro-empty">Data tidak ditemukan.</td></tr>
            @endforelse
            @if ($rows->isNotEmpty())
                <tr class="rm-mikro-total">
                    <td colspan="8" class="strong">GRAND TOTAL</td>
                    @foreach (($payload['months'] ?? []) as $month)
                        @php
                            $monthDeb = $rows->sum(fn ($row) => (int) data_get($row, 'months.' . $month['key'] . '.deb', 0));
                            $monthOs = $rows->sum(fn ($row) => (float) data_get($row, 'months.' . $month['key'] . '.os', 0));
                        @endphp
                        <td class="text-right">{{ $formatAmount($monthDeb) }}</td>
                        <td class="text-right">{{ $formatJuta($monthOs) }}</td>
                    @endforeach
                    <td class="text-right strong">{{ $formatAmount($rows->sum('total_deb')) }}</td>
                    <td class="text-right strong">{{ $formatJuta($rows->sum('total_os')) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
