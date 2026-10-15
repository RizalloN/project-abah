<?php

namespace App\Support;

use RuntimeException;

final class MantriProductivityReference
{
    private ?array $data = null;

    private ?array $accountIndex = null;

    private ?string $loadedFingerprint = null;

    public function __construct(private readonly ?string $path = null) {}

    public function dataForPeriod(string $period): ?array
    {
        $path = $this->path ?? resource_path('data/mantri-productivity-reference.json');
        if (($this->path === null && app()->environment('testing')) || ! is_file($path)) {
            return null;
        }

        if ($this->data === null) {
            $contents = (string) file_get_contents($path);
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (($data['version'] ?? null) !== 1
                || ! isset($data['month_start'], $data['as_of'], $data['baseline_period'], $data['people'], $data['accounts'], $data['baseline_accounts'], $data['current_accounts'], $data['sources'])) {
                throw new RuntimeException('Referensi produktivitas Mantri tidak lengkap.');
            }
            $this->data = $data;
            $this->loadedFingerprint = hash('sha256', $contents);
        }

        return $period >= $this->data['month_start'] && $period <= $this->data['as_of']
            ? $this->data : null;
    }

    public function fingerprint(string $period): string
    {
        return $this->dataForPeriod($period) === null
            ? 'brihc'
            : $this->loadedFingerprint;
    }

    public function accountReference(string $period, string $account, string $cif, string $realizationDate): ?array
    {
        $data = $this->dataForPeriod($period);
        if ($data === null) {
            return null;
        }
        $this->accountIndex ??= array_column($data['accounts'], null, 'account');
        $reference = $this->accountIndex[$this->accountKey($account)] ?? null;

        return is_array($reference) && $reference['cif'] === strtoupper(trim($cif))
            && $reference['realization_date'] === substr($realizationDate, 0, 10)
            && $reference['realization_date'] <= $period
            ? $reference : null;
    }

    public function closedPreviousOsByCif(string $period, array $cifs): ?array
    {
        $data = $this->dataForPeriod($period);
        // The workbook closing state is a snapshot at as_of. Earlier report
        // positions must use their own database closing state.
        if ($data === null || $period !== $data['as_of']) {
            return null;
        }
        $requested = array_fill_keys(array_map(static fn ($cif): string => strtoupper(trim((string) $cif)), $cifs), true);
        $closed = [];
        foreach ($data['accounts'] as $reference) {
            if (isset($requested[$reference['cif']])) {
                $closed[$reference['cif']] = 0.0;
            }
        }
        $present = array_fill_keys($data['current_accounts'], true);
        $accountBalances = [];
        foreach ($data['baseline_accounts'] as $row) {
            $account = $this->accountKey($row['account']);
            $cif = strtoupper(trim($row['cif']));
            if (array_key_exists($cif, $closed) && ! isset($present[$account])) {
                $accountBalances[$cif][$account] = max((float) ($accountBalances[$cif][$account] ?? 0), (float) $row['balance']);
            }
        }
        foreach ($accountBalances as $cif => $balances) {
            $closed[$cif] = array_sum($balances);
        }

        return $closed;
    }

    private function accountKey(string $value): string
    {
        $value = strtoupper(trim($value));

        return ctype_digit($value) ? (ltrim($value, '0') ?: '0') : $value;
    }
}
