<?php

namespace App\Services\Import;

final class MerchantQrisDetailCsv
{
    public const TABLE = 'jumlah_merchant_qris_detail';

    private const ANCHORS = [
        'MERCHANT_PAN', 'STOREID', 'NAMA_MERCHANT', 'KRITERIA', 'JENIS_USAHA',
        'KODE_POS', 'TGL_BALIKAN_PTEN', 'STATUS', 'MERCHANT_TYPE',
        'AKUMULASI_TRX_TOTAL', 'SALDO_POSISI', 'FLAGGING_BRI_MERCHANT',
    ];

    private const CRITERIA = ['UMI', 'UME', 'UKE', 'UBE', 'BLU', 'PSO'];

    private function __construct(private readonly array $indexes, private readonly int $width)
    {
    }

    public static function forHeaders(array $headers, string $delimiter): ?self
    {
        if ($delimiter !== '|') {
            return null;
        }

        $labels = array_map(static fn ($header): string => strtoupper(trim((string) $header)), $headers);
        $indexes = array_intersect_key(array_flip($labels), array_fill_keys(self::ANCHORS, true));
        if (count($indexes) !== count(self::ANCHORS) || count(array_unique($labels)) !== count($labels)) {
            return null;
        }

        $name = $indexes['NAMA_MERCHANT'];
        if (array_slice($labels, max(0, $name - 2), 5) !== array_slice(self::ANCHORS, 0, 5)
            || $indexes['KODE_POS'] <= $name
            || $indexes['TGL_BALIKAN_PTEN'] <= $name
            || $indexes['STATUS'] <= $name
            || $indexes['MERCHANT_TYPE'] <= $name
            || $indexes['AKUMULASI_TRX_TOTAL'] <= $name
            || $indexes['SALDO_POSISI'] <= $name
            || $indexes['FLAGGING_BRI_MERCHANT'] <= $name) {
            return null;
        }

        return new self($indexes, count($headers));
    }

    /** Rejoin a single unquoted pipe in the name only when the shifted fields agree. */
    public function repair(array $row): array
    {
        if (count($row) !== $this->width + 1) {
            return $row;
        }

        $index = $this->indexes;
        $name = $index['NAMA_MERCHANT'];
        $shifted = static fn (string $field): string => trim((string) $row[$index[$field] + 1]);
        if (preg_match('/^\d{19}$/D', trim((string) $row[$index['MERCHANT_PAN']])) !== 1
            || preg_match('/^\d{8}$/D', trim((string) $row[$index['STOREID']])) !== 1
            || trim((string) $row[$name]) === ''
            || trim((string) $row[$name + 1]) === ''
            || in_array(strtoupper(trim((string) $row[$name + 1])), self::CRITERIA, true)
            || !in_array(strtoupper($shifted('KRITERIA')), self::CRITERIA, true)
            || preg_match('/^\d{5}$/D', $shifted('KODE_POS')) !== 1
            || preg_match('/^\d{8}$/D', $shifted('TGL_BALIKAN_PTEN')) !== 1
            || !in_array(strtoupper($shifted('STATUS')), ['AKTIF', 'BELUM AKTIF'], true)
            || !in_array(strtoupper($shifted('MERCHANT_TYPE')), ['STATIS', 'DINAMIS', 'LINKAJA'], true)
            || !is_numeric($shifted('AKUMULASI_TRX_TOTAL'))
            || !is_numeric($shifted('SALDO_POSISI'))
            || !in_array(strtoupper($shifted('FLAGGING_BRI_MERCHANT')), ['YES', 'NO'], true)) {
            return $row;
        }

        array_splice($row, $name, 2, [$row[$name].'|'.$row[$name + 1]]);

        return $row;
    }
}
