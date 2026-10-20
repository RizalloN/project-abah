<?php

namespace App\View\Composers;

use App\Http\Controllers\Import\Concerns\AuthorizesSessionImportStorageFiles;
use App\Services\Import\SchemaDrift\SchemaEvolutionPolicy;
use App\Services\Import\SchemaDrift\SchemaDriftSourceToken;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * Gives every import preview page an encrypted reference to the uploaded file
 * so the schema drift panel can analyse it. Import controllers stay untouched;
 * the file is resolved from the same session keys and allowed directories the
 * controllers themselves authorise.
 */
class SchemaDriftPreviewComposer
{
    use AuthorizesSessionImportStorageFiles;

    /**
     * route-name prefix => [session key, allowed directory, fixed table, reload preview after accept]
     *
     * A null table means the active report (session active_id_report) decides.
     * PH renders its column list server side, so the preview reloads after a
     * column is accepted to include it in the very same import.
     */
    private const SOURCES = [
        'import.reportph.' => ['report_ph_file', 'report_ph_imports', 'lw325_ph', true],
        'import.cognos-ph.' => ['cognos_ph_file', 'cognos_ph_imports', 'cognos_ph', false],
        'import.cognos-recovery.' => ['cognos_recovery_file', 'cognos_recovery_imports', 'cognos_recovery', false],
        'import.performancepis.' => ['performance_pis_file', 'performance_pis_imports', 'performance_pis_per_produk', false],
        'import.casabrilink.' => ['casa_brilink_file', 'casa_brilink_imports', null, false],
        'import.cras.' => ['cras_file', 'cras_uploads', 'cras', false],
    ];

    private const DEFAULT_SOURCE = ['excel_path', 'excel_imports', null, false];

    private const EXTENSIONS = ['csv', 'txt', 'xlsx', 'xls', 'xlsm'];

    public function compose(View $view): void
    {
        if (array_key_exists('schemaDriftSource', $view->getData())) {
            return;
        }

        $resolved = $this->resolveSource();
        $view->with('schemaDriftSource', $resolved['token'] ?? null);
        $view->with('schemaDriftMode', $resolved['mode'] ?? SchemaEvolutionPolicy::MODE_MONITOR);
        $view->with('schemaDriftReloadOnAccept', (bool) ($resolved['reload'] ?? false));
    }

    private function resolveSource(): array
    {
        try {
            $routeName = (string) (request()->route()?->getName() ?? '');
            [$sessionKey, $directory, $table, $reload] = self::DEFAULT_SOURCE;
            foreach (self::SOURCES as $prefix => $source) {
                if (str_starts_with($routeName, $prefix)) {
                    [$sessionKey, $directory, $table, $reload] = $source;
                    break;
                }
            }

            $table ??= $this->activeReportTable();
            if ($table === null || $table === '' || trim((string) session($sessionKey, '')) === '') {
                return [];
            }

            [, $absolutePath] = $this->authorizeSessionImportStorageFile(null, $sessionKey, [$directory], self::EXTENSIONS);

            return [
                'token' => SchemaDriftSourceToken::issue($table, $absolutePath, basename((string) session($sessionKey))),
                'mode' => app(SchemaEvolutionPolicy::class)->modeFor($table),
                'reload' => $reload,
            ];
        } catch (Throwable) {
            // The panel is an enhancement; a preview must never fail because of it.
            return [];
        }
    }

    private function activeReportTable(): ?string
    {
        $reportId = (int) session('active_id_report');
        if ($reportId <= 0) {
            return null;
        }

        $table = DB::table('nama_report')->where('id_report', $reportId)->value('table_name');

        return is_string($table) ? strtolower(trim($table)) : null;
    }
}
