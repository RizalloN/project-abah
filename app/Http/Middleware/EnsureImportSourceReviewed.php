<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Import\Concerns\AuthorizesSessionImportStorageFiles;
use App\Services\Import\SchemaDrift\SchemaDriftImportGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Dedicated report endpoints must enforce the same review as the generic importer. */
class EnsureImportSourceReviewed
{
    use AuthorizesSessionImportStorageFiles;

    private const SOURCES = [
        'import.reportph.' => ['report_ph_file', 'report_ph_imports', 'lw325_ph'],
        'import.cognos-ph.' => ['cognos_ph_file', 'cognos_ph_imports', 'cognos_ph'],
        'import.cognos-recovery.' => ['cognos_recovery_file', 'cognos_recovery_imports', 'cognos_recovery'],
        'import.performancepis.' => ['performance_pis_file', 'performance_pis_imports', 'performance_pis_per_produk'],
        'import.casabrilink.' => ['casa_brilink_file', 'casa_brilink_imports', null],
        'import.cras.' => ['cras_file', 'cras_uploads', 'cras'],
    ];

    public function __construct(private readonly SchemaDriftImportGuard $guard) {}

    public function handle(Request $request, Closure $next)
    {
        $routeName = (string) ($request->route()?->getName() ?? '');
        $source = null;
        foreach (self::SOURCES as $prefix => $specification) {
            if (str_starts_with($routeName, $prefix)) {
                $source = $specification;
                break;
            }
        }
        abort_if($source === null, 403, 'Sumber pemeriksaan import tidak sesuai dengan route.');
        [$sessionKey, $directory, $table] = $source;
        if ($table === null) {
            $table = strtolower(trim((string) DB::table('nama_report')
                ->where('id_report', (int) session('active_id_report'))->value('table_name')));
            abort_unless(in_array($table, ['casa_brilink_web', 'casa_brilink_edc'], true), 403, 'Report aktif tidak sesuai dengan import CASA BRILink.');
        }

        // Authorize first. An arbitrary client path must never be inspected.
        [, $path] = $this->authorizeSessionImportStorageFile(
            $request->input('file_path', $request->input('path')),
            $sessionKey,
            [$directory],
            ['csv', 'txt', 'xlsx', 'xls', 'xlsm']
        );
        try {
            $this->guard->assertReady($table, $path);
        } catch (RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'title' => 'Struktur sumber perlu diperiksa',
                'text' => $e->getMessage(),
                'message' => $e->getMessage(),
                'schema_drift' => $this->guard->lastReport(),
            ], 422);
        }

        return $next($request);
    }
}
