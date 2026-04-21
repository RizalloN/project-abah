<?php

namespace App\Console\Commands;

use App\Http\Controllers\Import\ImportFileController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class WarmImportPreviewIndexCommand extends Command
{
    protected $signature = 'import:warm-preview-index
        {--file_path= : File path used by the preview page}
        {--delimiter=auto : Delimiter used for parsing}
        {--table_name= : Logical report table name}
        {--is_brilink_summary=0 : Whether the report uses BRILink summary parsing}
        {--warm_index_columns_json= : JSON array of columns to index eagerly}
        {--lock_key= : Cache lock key to release when done}';

    protected $description = 'Warm the import preview index in the background';

    public function handle(): int
    {
        $filePath = (string) $this->option('file_path');
        $delimiter = (string) $this->option('delimiter');
        $tableName = (string) $this->option('table_name');
        $isBrilinkSummary = (string) $this->option('is_brilink_summary') === '1';
        $warmIndexColumns = json_decode(rawurldecode((string) $this->option('warm_index_columns_json')), true);
        if (!is_array($warmIndexColumns)) {
            $warmIndexColumns = [];
        }
        $lockKey = trim((string) $this->option('lock_key'));

        if ($filePath === '' || $tableName === '') {
            $this->error('Missing required options: file_path and table_name.');
            return self::FAILURE;
        }

        try {
            /** @var ImportFileController $controller */
            $controller = app()->make(ImportFileController::class);
            $dbPath = $controller->warmPreviewIndexDatabase($filePath, $delimiter, $isBrilinkSummary, $tableName, $warmIndexColumns);
            $this->info('Preview index warmed: ' . $dbPath);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Failed to warm preview index: ' . $e->getMessage());
            Log::warning('Import preview index warmup failed', [
                'file_path' => $filePath,
                'table_name' => $tableName,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        } finally {
            if ($lockKey !== '') {
                Cache::forget($lockKey);
            }
        }
    }
}
