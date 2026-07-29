<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class SsaPinjamanDailyLoanRewriteService
{
    public const TARGET_PERIOD = '2025-12-31';

    public function inspect(string $period = self::TARGET_PERIOD): array
    {
        $this->assertTargetPeriod($period);

        $source = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->selectRaw('COUNT(*) as row_count')
            ->selectRaw('COUNT(DISTINCT cabang1) as branch_count')
            ->selectRaw('COUNT(DISTINCT unit1) as unit_count')
            ->selectRaw('SUM(COALESCE(baki_debet1, 0)) as baki_debet')
            ->selectRaw("SUM(CASE WHEN TRIM(COALESCE(cabang1, '')) = '' THEN 1 ELSE 0 END) as blank_branch_count")
            ->selectRaw("SUM(CASE WHEN TRIM(COALESCE(unit1, '')) = '' THEN 1 ELSE 0 END) as blank_unit_count")
            ->selectRaw("SUM(CASE WHEN TRIM(COALESCE(cifno, '')) = '' THEN 1 ELSE 0 END) as blank_cif_count")
            ->selectRaw("SUM(CASE WHEN TRIM(COALESCE(nomor_rekening1, '')) = '' THEN 1 ELSE 0 END) as blank_account_count")
            ->selectRaw("SUM(CASE WHEN TRIM(COALESCE(kolek, '')) NOT REGEXP '^[1-5]$' THEN 1 ELSE 0 END) as invalid_kolek_count")
            ->first();

        if ((int) ($source->row_count ?? 0) <= 0) {
            throw new RuntimeException("Daily Loan tidak memiliki data untuk periode {$period}.");
        }

        foreach (['blank_branch_count', 'blank_unit_count', 'blank_cif_count', 'blank_account_count', 'invalid_kolek_count'] as $column) {
            if ((int) ($source->{$column} ?? 0) > 0) {
                throw new RuntimeException("Daily Loan {$period} tidak layak direwrite: {$column} tidak nol.");
            }
        }

        $projection = $this->projectionStats($period);
        $current = $this->targetStats($period);

        if ((int) ($projection->row_count ?? 0) <= 0) {
            throw new RuntimeException("Agregasi SSA Pinjaman untuk {$period} tidak menghasilkan baris.");
        }

        if (!$this->decimalEquals($source->baki_debet ?? null, $projection->baki_debet ?? null)) {
            throw new RuntimeException("Total baki debet hasil agregasi tidak sama dengan Daily Loan {$period}.");
        }

        return [
            'period' => $period,
            'source' => (array) $source,
            'projection' => (array) $projection,
            'current' => (array) $current,
        ];
    }

    public function backupCurrentPeriod(string $period = self::TARGET_PERIOD): string
    {
        $this->assertTargetPeriod($period);

        $directory = storage_path('app/backups/ssa_pinjaman_rewrite');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Direktori backup tidak dapat dibuat: {$directory}");
        }

        $path = $directory . DIRECTORY_SEPARATOR
            . 'ssa_pinjaman_' . $period . '_before_daily_loan_rewrite_' . now()->format('Ymd_His') . '.jsonl';
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException("File backup tidak dapat dibuat: {$path}");
        }

        try {
            foreach (DB::table('ssa_pinjaman')
                ->where('month_day_year_of_periode', $period)
                ->orderBy('id')
                ->cursor() as $row) {
                $json = json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
                if ($json === false || fwrite($handle, $json . PHP_EOL) === false) {
                    throw new RuntimeException("Gagal menulis backup SSA Pinjaman {$period}.");
                }
            }
        } finally {
            fclose($handle);
        }

        return $path;
    }

    public function rewrite(?array $inspection = null): array
    {
        $period = (string) ($inspection['period'] ?? self::TARGET_PERIOD);
        $this->assertTargetPeriod($period);
        $inspection ??= $this->inspect($period);
        $before = $inspection['current'];
        $now = now();
        $nextId = ((int) DB::table('ssa_pinjaman')->max('id')) + 1;

        DB::statement('SET @skip_snapshot_invalidation = 1');
        DB::statement('SET @ssa_pinjaman_rewrite_id = ?', [$nextId - 1]);

        try {
            DB::transaction(function () use ($period, $now): void {
                DB::table('ssa_pinjaman')
                    ->where('month_day_year_of_periode', $period)
                    ->delete();

                DB::insert($this->insertFromDailyLoanSql(), [
                    $period,
                    $now,
                    $now,
                    $period,
                ]);
            }, 3);
        } finally {
            DB::statement('SET @skip_snapshot_invalidation = 0');
            DB::statement('SET @ssa_pinjaman_rewrite_id = NULL');
        }

        $after = (array) $this->targetStats($period);
        $projection = $inspection['projection'];

        if (
            (int) ($after['row_count'] ?? 0) !== (int) ($projection['row_count'] ?? 0)
            || !$this->decimalEquals($after['baki_debet'] ?? null, $projection['baki_debet'] ?? null)
            || !$this->decimalEquals($after['debitur_count'] ?? null, $projection['debitur_count'] ?? null)
            || !$this->decimalEquals($after['rekening_count'] ?? null, $projection['rekening_count'] ?? null)
        ) {
            throw new RuntimeException("Validasi setelah rewrite SSA Pinjaman {$period} gagal.");
        }

        return [
            'period' => $period,
            'before' => $before,
            'after' => $after,
            'source' => $inspection['source'],
        ];
    }

    private function projectionStats(string $period): object
    {
        return DB::query()
            ->fromSub($this->normalizedDailyLoanQuery($period), 'normalized_daily_loan')
            ->selectRaw('COUNT(*) as row_count')
            ->selectRaw('SUM(baki_debet) as baki_debet')
            ->selectRaw('SUM(jumlah_debitur_aktif) as debitur_count')
            ->selectRaw('SUM(jumlah_rekening_aktif) as rekening_count')
            ->first();
    }

    private function targetStats(string $period): object
    {
        return DB::table('ssa_pinjaman')
            ->where('month_day_year_of_periode', $period)
            ->selectRaw('COUNT(*) as row_count')
            ->selectRaw('SUM(COALESCE(baki_debet, 0)) as baki_debet')
            ->selectRaw('SUM(COALESCE(jumlah_debitur_aktif, 0)) as debitur_count')
            ->selectRaw('SUM(COALESCE(jumlah_rekening_aktif, 0)) as rekening_count')
            ->first();
    }

    private function normalizedDailyLoanQuery(string $period)
    {
        $dailyRows = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->selectRaw("NULLIF(TRIM(kanwil1), '') as regional_office")
            ->selectRaw("NULLIF(TRIM(kode_cabang1), '') as id_cabang")
            ->selectRaw("CASE WHEN TRIM(COALESCE(kode_cabang1, '')) REGEXP '^[0-9]+$' THEN CONCAT(LPAD(CAST(TRIM(kode_cabang1) AS UNSIGNED), 5, '0'), ' -- ', TRIM(cabang1), ' (Konsolidasi-MB)') ELSE NULLIF(TRIM(cabang1), '') END as nama_cabang")
            ->selectRaw("NULLIF(TRIM(branch1), '') as id_uker")
            ->selectRaw("CASE WHEN TRIM(COALESCE(branch1, '')) REGEXP '^[0-9]+$' THEN CONCAT(LPAD(CAST(TRIM(branch1) AS UNSIGNED), 5, '0'), ' -- ', TRIM(unit1)) ELSE NULLIF(TRIM(unit1), '') END as nama_uker")
            ->selectRaw('cifno')
            ->selectRaw('nomor_rekening1')
            ->selectRaw("CASE WHEN UPPER(TRIM(COALESCE(segmen_dashboard, ''))) IN ('MICRO', 'MIKRO') THEN 'Micro' WHEN UPPER(TRIM(COALESCE(segmen_dashboard, ''))) IN ('CONSUMER', 'KONSUMER') THEN 'Consumer' WHEN UPPER(TRIM(COALESCE(segmen_dashboard, ''))) = 'SMALL' THEN 'Small' WHEN UPPER(TRIM(COALESCE(segmen_dashboard, ''))) = 'MEDIUM' THEN 'Medium' ELSE NULLIF(TRIM(segmen_dashboard), '') END as mapped_segment")
            ->selectRaw("CASE WHEN UPPER(TRIM(COALESCE(produk_dashboard, ''))) IN ('KUR-SMALL', 'KUR-KECIL') THEN 'KUR-Mikro' WHEN UPPER(TRIM(COALESCE(produk_dashboard, ''))) = 'BRIGUNA-KONSUMER' THEN 'Briguna-Konsumer' WHEN UPPER(TRIM(COALESCE(produk_dashboard, ''))) = 'BRIGUNA-MIKRO' THEN 'Briguna-Mikro' WHEN UPPER(TRIM(COALESCE(produk_dashboard, ''))) = 'KUR-MIKRO' THEN 'KUR-Mikro' WHEN UPPER(TRIM(COALESCE(produk_dashboard, ''))) = 'KUPEDES' THEN 'Kupedes' WHEN UPPER(TRIM(COALESCE(produk_dashboard, ''))) = 'COMMERCIAL' THEN 'Commercial' WHEN UPPER(TRIM(COALESCE(produk_dashboard, ''))) = 'CASHCALL' THEN 'Cashcall' WHEN UPPER(TRIM(COALESCE(produk_dashboard, ''))) = 'KPR' THEN 'KPR' WHEN UPPER(TRIM(COALESCE(produk_dashboard, ''))) = 'MEDIUM' THEN 'Medium' ELSE NULLIF(TRIM(produk_dashboard), '') END as mapped_product")
            ->selectRaw("UPPER(TRIM(COALESCE(produk_dashboard, ''))) as source_product")
            ->selectRaw("CAST(NULLIF(TRIM(COALESCE(kolek, '')), '') AS UNSIGNED) as kolektabilitas_one_obligor")
            ->selectRaw("NULLIF(TRIM(flag_restruk), '') as flag_restruk")
            ->selectRaw('COALESCE(baki_debet1, 0) as baki_debet');

        $mappedRows = DB::query()
            ->fromSub($dailyRows, 'daily_rows')
            ->select('regional_office', 'id_cabang', 'nama_cabang', 'id_uker', 'nama_uker', 'cifno', 'nomor_rekening1')
            ->selectRaw('mapped_segment as segmen_dashboard')
            ->selectRaw('mapped_product as produk_dashboard')
            ->selectRaw("CASE mapped_segment WHEN 'Consumer' THEN 'Konsumer' WHEN 'Micro' THEN 'Micro' WHEN 'Small' THEN 'SME' WHEN 'Medium' THEN 'Korporasi' ELSE mapped_segment END as segmen")
            ->selectRaw("CASE mapped_segment WHEN 'Consumer' THEN 'Ritel' WHEN 'Micro' THEN 'Mikro' WHEN 'Small' THEN 'Kecil' WHEN 'Medium' THEN 'Menengah' ELSE mapped_segment END as segmen_lama")
            ->selectRaw("CASE WHEN mapped_product = 'Briguna-Konsumer' THEN 'Briguna Ritel' WHEN mapped_product = 'Briguna-Mikro' AND mapped_segment = 'Consumer' THEN 'Briguna Ritel' WHEN mapped_product = 'Briguna-Mikro' THEN 'Briguna Mikro' WHEN mapped_product = 'KPR' AND mapped_segment = 'Micro' THEN 'KREDIT MIKRO - KPP' WHEN mapped_product = 'KPR' THEN 'KPR' WHEN mapped_product = 'Kupedes' THEN 'Kupedes' WHEN mapped_product = 'KUR-Mikro' AND source_product IN ('KUR-SMALL', 'KUR-KECIL') THEN 'KUR Kecil' WHEN mapped_product = 'KUR-Mikro' THEN 'KUR Mikro' WHEN mapped_product IN ('Commercial', 'Cashcall') THEN 'Kecil Komersial' WHEN mapped_product = 'Medium' THEN 'Menengah' ELSE mapped_product END as produk")
            ->selectRaw('mapped_segment as segmen_2025')
            ->addSelect('kolektabilitas_one_obligor', 'flag_restruk', 'baki_debet');

        return DB::query()
            ->fromSub($mappedRows, 'mapped_rows')
            ->whereNotNull('nama_cabang')
            ->whereNotNull('nama_uker')
            ->select('regional_office', 'id_cabang', 'nama_cabang', 'id_uker', 'nama_uker', 'segmen_dashboard', 'produk_dashboard', 'segmen', 'segmen_lama', 'produk', 'segmen_2025', 'kolektabilitas_one_obligor', 'flag_restruk')
            ->selectRaw('SUM(baki_debet) as baki_debet')
            ->selectRaw('COUNT(DISTINCT cifno) as jumlah_debitur_aktif')
            ->selectRaw('COUNT(DISTINCT nomor_rekening1) as jumlah_rekening_aktif')
            ->groupBy(
                'regional_office',
                'id_cabang',
                'nama_cabang',
                'id_uker',
                'nama_uker',
                'segmen_dashboard',
                'produk_dashboard',
                'segmen',
                'segmen_lama',
                'produk',
                'segmen_2025',
                'kolektabilitas_one_obligor',
                'flag_restruk'
            );
    }

    private function insertFromDailyLoanSql(): string
    {
        $query = $this->normalizedDailyLoanQuery(self::TARGET_PERIOD);
        $sql = $query->toSql();

        return <<<SQL
INSERT INTO `ssa_pinjaman` (
    `id`, `month_day_year_of_periode`, `regional_office`, `id_cabang`, `nama_cabang`, `id_uker`, `nama_uker`,
    `cif`, `nominatif`, `segmen_dashboard`, `produk_dashboard`, `segmen`, `segmen_lama`, `produk`, `segmen_2025`,
    `baki_debet`, `jumlah_debitur_aktif`, `jumlah_rekening_aktif`, `kolektabilitas_one_obligor`, `flag_restruk`,
    `created_at`, `updated_at`
)
SELECT
    (@ssa_pinjaman_rewrite_id := @ssa_pinjaman_rewrite_id + 1), ?, `regional_office`, `id_cabang`, `nama_cabang`, `id_uker`, `nama_uker`,
    NULL, NULL, `segmen_dashboard`, `produk_dashboard`, `segmen`, `segmen_lama`, `produk`, `segmen_2025`,
    `baki_debet`, `jumlah_debitur_aktif`, `jumlah_rekening_aktif`, `kolektabilitas_one_obligor`, `flag_restruk`,
    ?, ?
FROM ({$sql}) as `normalized_daily_loan`
SQL;
    }

    private function assertTargetPeriod(string $period): void
    {
        if ($period !== self::TARGET_PERIOD) {
            throw new RuntimeException('Rewrite SSA Pinjaman ini hanya diizinkan untuk periode 2025-12-31.');
        }
    }

    private function decimalEquals($left, $right): bool
    {
        return $this->normalizeDecimal($left) === $this->normalizeDecimal($right);
    }

    private function normalizeDecimal($value): string
    {
        $value = trim((string) ($value ?? '0'));
        if ($value === '') {
            return '0';
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        $fraction = rtrim($fraction, '0');
        $normalized = ($whole === '' ? '0' : $whole) . ($fraction === '' ? '' : '.' . $fraction);

        return $negative && $normalized !== '0' ? '-' . $normalized : $normalized;
    }
}
