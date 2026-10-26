<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class SyncMantriSpreadsheetCommand extends Command
{
    public const SPREADSHEET_ID = '1zrRANqCUtDykvF_bO_78_Zr2XDckm9NWww4o33o5r44';

    public const SOURCE_URL = 'https://docs.google.com/spreadsheets/d/'.self::SPREADSHEET_ID.'/export?format=xlsx';

    public const CACHE_KEY = 'reference:mantri-spreadsheet:'.self::SPREADSHEET_ID.':v2-keterangan';

    public const LOCK_KEY = 'reference:mantri-spreadsheet:'.self::SPREADSHEET_ID.':v1:lock';

    private const SHEETS = ['MANTRITUMBUH', 'MANTRIBRIGUNA', 'MANTRIRECOVERY'];

    private const ROLES = ['MANTRI', 'MANTRI BRIGUNA', 'MANTRI RECOVERY'];

    private const BRANCHES = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];

    protected $signature = 'reference:sync-mantri-spreadsheet
        {--dry-run : Validate the current spreadsheet without changing references or cache versions}';

    protected $description = 'Synchronize the three Mantri BRIHC roles from the designated Google spreadsheet';

    public function handle(): int
    {
        $lock = Cache::lock(self::LOCK_KEY, 300);
        if (! $lock->get()) {
            $this->info('Sinkronisasi spreadsheet Mantri sedang berjalan; dilewati.');

            return self::SUCCESS;
        }

        $path = null;
        try {
            $response = Http::connectTimeout(15)->timeout(90)->get(self::SOURCE_URL);
            $response->throw();
            $path = tempnam(sys_get_temp_dir(), 'mantri_spreadsheet_');
            if ($path === false || file_put_contents($path, $response->body()) === false) {
                throw new RuntimeException('Tidak dapat menyimpan unduhan spreadsheet Mantri.');
            }

            $sourceHash = $this->sourceHash($path);
            // Validate every required sheet, even when the source matches the previous run.
            $status = Artisan::call('reference:sync-brihc', ['file' => $path, '--dry-run' => true]);
            if ($status !== self::SUCCESS || $this->option('dry-run')) {
                $this->line(trim(Artisan::output()));

                return $status;
            }

            $marker = Cache::get(self::CACHE_KEY);
            if (is_array($marker)
                && ($marker['source_hash'] ?? null) === $sourceHash
                && ($marker['database_hash'] ?? null) === $this->databaseHash()) {
                Cache::forever(self::CACHE_KEY.':last_success', now()->toIso8601String());
                $this->info('Spreadsheet Mantri dan referensi database tidak berubah; sinkronisasi dilewati.');

                return self::SUCCESS;
            }

            $status = Artisan::call('reference:sync-brihc', ['file' => $path]);
            $this->line(trim(Artisan::output()));
            if ($status === self::SUCCESS) {
                Cache::forever(self::CACHE_KEY, [
                    'source_hash' => $sourceHash,
                    'database_hash' => $this->databaseHash(),
                ]);
                Cache::forever(self::CACHE_KEY.':last_success', now()->toIso8601String());
            }

            return $status;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Sinkronisasi spreadsheet Mantri gagal: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
            $lock->release();
        }
    }

    private function sourceHash(string $path): string
    {
        $reader = IOFactory::createReaderForFile($path);
        $names = [];
        foreach ($reader->listWorksheetNames($path) as $name) {
            $names[preg_replace('/[^A-Z0-9]/', '', strtoupper($name))] = $name;
        }
        foreach (array_merge(['REKAP'], self::SHEETS) as $required) {
            if (! isset($names[$required])) {
                throw new RuntimeException('Sheet spreadsheet Mantri wajib tidak ditemukan: '.$required);
            }
        }

        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(array_map(fn (string $name): string => $names[$name], self::SHEETS));
        $workbook = $reader->load($path);
        try {
            $values = [];
            foreach (self::SHEETS as $name) {
                $values[$name] = $workbook->getSheetByName($names[$name])->toArray(null, false, false, false);
            }

            return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    private function databaseHash(): string
    {
        $pemasar = DB::table('brihc_pemasar')
            ->whereIn(DB::raw("UPPER(TRIM(COALESCE(positiondesc, '')))"), self::ROLES)
            ->orderBy('uniqueid_namareport')->get();
        $brihc = DB::table('brihc')
            ->where(fn ($query) => $query->whereIn('pn', $pemasar->pluck('pernr')->all())
                ->orWhere('uniqueid_brihc', 'like', 'reference_brihc_hc_%'))
            ->whereIn(DB::raw("UPPER(TRIM(COALESCE(jabatan, '')))"), self::ROLES)
            ->orderBy('uniqueid_brihc')->get();
        $canonical = static fn ($rows): array => $rows->map(static function ($row): array {
            $values = (array) $row;
            unset($values['created_at'], $values['updated_at']);
            ksort($values);

            return $values;
        })->all();

        return hash('sha256', json_encode([$canonical($pemasar), $canonical($brihc)], JSON_THROW_ON_ERROR));
    }
}
