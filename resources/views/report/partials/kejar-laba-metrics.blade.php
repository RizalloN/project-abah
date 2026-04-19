@foreach(['micro', 'small', 'consumer', 'total'] as $seg)
    <td class="text-right">
        {{ $metrics[$seg] != 0 ? number_format($metrics[$seg], 0, ',', '.') : '-' }}
    </td>
@endforeach
