<?php

namespace App\Support;

use RuntimeException;

final class MicroRmProductivityReference
{
    private ?array $data = null;

    public function __construct(private readonly ?string $path = null) {}

    public function dataForPeriod(string $period): ?array
    {
        $data = $this->data();

        return $data !== null && $period === $data['as_of'] ? $data : null;
    }

    public function asOf(): ?string
    {
        return $this->data()['as_of'] ?? null;
    }

    public function fingerprint(): string
    {
        return $this->data() === null ? 'daily-loan' : hash_file('sha256', $this->path ?? resource_path('data/micro-rm-productivity-reference.json'));
    }

    /** Current-position inputs; the existing calculator still computes nett. */
    public function loanRows(string $period): ?array
    {
        $data = $this->dataForPeriod($period);
        if ($data === null) {
            return null;
        }

        return array_map(static fn (array $row): object => (object) [
            'periode' => $period,
            'cabang1' => $row['branch'], 'unit1' => $row['unit'], 'branch1' => $row['bc'],
            'pn_pemrakarsa1' => $row['pn'] === '' ? '' : str_pad($row['pn'], 8, '0', STR_PAD_LEFT).' - '.$row['name'],
            'nomor_rekening1' => $row['account'], 'cifno' => $row['cif'],
            'plafon' => $row['plafon'], 'baki_debet1' => $row['balance'],
            'tgl_realisasi' => $row['realization_date'],
        ], $data['accounts']);
    }

    private function data(): ?array
    {
        $path = $this->path ?? resource_path('data/micro-rm-productivity-reference.json');
        if (($this->path === null && app()->environment('testing')) || ! is_file($path)) {
            return null;
        }
        if ($this->data === null) {
            $this->data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (($this->data['version'] ?? null) !== 1
                || ! isset($this->data['as_of'], $this->data['source'], $this->data['rows'], $this->data['accounts'])) {
                throw new RuntimeException('Referensi produktivitas RM KUR tidak lengkap.');
            }
        }

        return $this->data;
    }
}
