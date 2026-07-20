<div class="rm-mikro-table-wrap table-container">
    <table class="rm-mikro-table" style="min-width: 1050px;">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Cabang</th>
                <th>Kode Uker</th>
                <th>Nama Uker</th>
                <th>Jml Mantri</th>
                <th>Kuadran 1</th>
                <th>Kuadran 2</th>
                <th>Kuadran 3</th>
                <th>Kuadran 4</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="strong">{{ $row['cabang'] ?? '-' }}</td>
                    <td class="text-center strong">{{ $row['bc'] ?? '-' }}</td>
                    <td>{{ $row['unit'] ?? '-' }}</td>
                    <td class="text-right strong">{{ $formatAmount($row['jumlah_mantri'] ?? 0) }}</td>
                    <td class="text-right heat-green">{{ $formatAmount($row['kuadran_1'] ?? 0) }}</td>
                    <td class="text-right heat-lime">{{ $formatAmount($row['kuadran_2'] ?? 0) }}</td>
                    <td class="text-right heat-orange">{{ $formatAmount($row['kuadran_3'] ?? 0) }}</td>
                    <td class="text-right heat-red">{{ $formatAmount($row['kuadran_4'] ?? 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="rm-mikro-empty">Data tidak ditemukan.</td></tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr class="rm-mikro-total">
                    <th colspan="4">TOTAL {{ $userBranchScope['upper_label'] ?? 'AREA 6' }}</th>
                    <td class="text-right">{{ $formatAmount($total['jumlah_mantri'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatAmount($total['kuadran_1'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatAmount($total['kuadran_2'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatAmount($total['kuadran_3'] ?? 0) }}</td>
                    <td class="text-right">{{ $formatAmount($total['kuadran_4'] ?? 0) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
