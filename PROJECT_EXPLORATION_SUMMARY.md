# Project Exploration Summary

## 1. ReportSnapshotBuilder.php Location & fetchSegmentRmAggregates Method

**File Path:** [app/Support/ReportSnapshotBuilder.php](app/Support/ReportSnapshotBuilder.php)

### Key Method: fetchSegmentRmAggregates (Line 2049-2122)
Located at [app/Support/ReportSnapshotBuilder.php#L2049](app/Support/ReportSnapshotBuilder.php#L2049)

**Purpose:** Fetches aggregated RM segment data from daily_loan_dinamis using pre-computed shadow columns for optimized queries.

**Key Characteristics:**
- Uses **shadow columns** (segmen_kinerja, produk_kinerja, cabang_normalized, rm_normalized, cifno_clean) instead of function-based WHERE clauses
- **10-50x faster queries** by avoiding UPPER/TRIM/REPLACE overhead in WHERE/GROUP BY
- Implements index-only scans on composite index: `(periode, segmen_kinerja, produk_kinerja, cabang_normalized)`

**Method Signature:**
```php
private function fetchSegmentRmAggregates(
    string $period, 
    string $segment, 
    array $normalizedRules, 
    bool $isSmall
): array
```

**Query Pattern:**
```php
DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->where('segmen_kinerja', $rule['segment'])
    ->whereIn('produk_kinerja', $rule['products'])
    ->select([
        'cabang_normalized as cabang',
        'unit_normalized as unit',
        'branch_normalized as branch_code',
        'rm_normalized as rm',
        'produk_kinerja as produk',
    ])
    ->selectRaw("GROUP_CONCAT(DISTINCT cifno_clean SEPARATOR ',') as cifno_list")
    ->groupBy('cabang_normalized', 'unit_normalized', 'branch_normalized', 'rm_normalized', 'produk_kinerja')
```

### Related Methods:
- **rebuildPerformanceRm()** [Line 1812](app/Support/ReportSnapshotBuilder.php#L1812): Main rebuild orchestrator
- **buildKinerjaRmNormalizedSql()** [Line 2205](app/Support/ReportSnapshotBuilder.php#L2205): Generates normalization SQL pattern
- **normalizeKinerjaRmToken()** [Line 2213](app/Support/ReportSnapshotBuilder.php#L2213): Normalizes tokens for comparison

---

## 2. KinerjaRmMikroReportController Location

**File Path:** [app/Http/Controllers/Report/KinerjaRmMikroReportController.php](app/Http/Controllers/Report/KinerjaRmMikroReportController.php)

**Class Structure:**
```php
namespace App\Http\Controllers\Report;

class KinerjaRmMikroReportController extends Controller
{
    private const SNAPSHOT_TABLE = 'performance_rm_snapshots';
    private const TARGET_MONTHLY_JUTA = 4000.0;
    private const WEEKLY_TARGET_JUTA = 1000.0;

    private const REPORT_CATEGORIES = [
        'per_uker' => 'Per UKER',
        'per_rm' => 'Per RM',
        'series_bulanan' => 'Series Bulanan',
        'series_harian' => 'Series Harian',
        'rekap' => 'Rekap',
        'per_tiering' => 'Per Tiering',
    ];

    private const MANTRI_REPORT_CATEGORIES = [
        'unit_pemutus' => 'Unit per Pemutus',
        'kuadran' => 'Kuadran',
        'pdwk_override' => 'PDWK - Override',
        'rekap_mantri' => 'Rekap Mantri',
    ];
}
```

---

## 3. Console Commands Location

**Directory:** [app/Console/Commands](app/Console/Commands)

### Available Commands (21 total):
```
AnalyzeDeleteAuditsCommand.php
BenchmarkDeletePerformanceCommand.php
EnsureQueueWorkerRunning.php
FlushDueSnapshotBatches.php
ImportHealthCheckCommand.php
ManageSnapshotBatches.php
ProgressiveBackupCommand.php
RebuildDashboardHarianCommand.php
RebuildPerformanceRmCommand.php              ← Performance RM snapshots
RebuildRecoverySnapshot.php
RecoverSafeDumpCommand.php
RunImportJobCommand.php
ScheduledRebuildPerformanceRmCommand.php
ScheduleSnapshotBatchFlush.php
SimulateDeleteScenarioCommand.php
SnapshotForceSyncCommand.php
SyncDashboardHarianSnapshot.php
ValidatePerformanceRmSnapshotsCommand.php
ValidateSnapshotDataIntegrityCommand.php
WarmDashboardCache.php
WarmImportPreviewIndexCommand.php
```

**Key Command Example:** [app/Console/Commands/RebuildPerformanceRmCommand.php](app/Console/Commands/RebuildPerformanceRmCommand.php)
```php
class RebuildPerformanceRmCommand extends Command
{
    protected $signature = 'snapshot:rebuild-rm
        {--period= : Rebuild specific period (e.g., 2026-04-20)}
        {--force : Force rebuild all periods}';
}
```

---

## 4. Database Schema - daily_loan_dinamis Table

**Migration File:** [database/migrations/2026_04_01_000003_main_report_data.php](database/migrations/2026_04_01_000003_main_report_data.php)

### Core Table Columns (Primary Structure):
```
Primary Key: uniqueid_namareport (VARCHAR 255)
periode (DATE) - indexed
kode_kanwil1, kanwil1
kode_cabang1, cabang1 (indexed)
branch1, unit1 (indexed)
cifno (VARCHAR 50) - indexed
nomor_rekening1
status_rekening1, ln_type
nama_debitur1
plafon (DECIMAL 20,2)
baki_debet1 (DECIMAL 20,2)
kol_adk1
segmen_dashboard (VARCHAR 100) - indexed
produk_dashboard (VARCHAR 100) - indexed
tgl_realisasi, tgl_jatuh_tempo
pn_pengelola1 (TEXT)
flag_restruk
```

### Shadow Columns (Pre-Computed Normalization) - Added by Migration

**Migration File:** [database/migrations/2026_04_26_200000_add_normalized_shadow_columns_to_daily_loan.php](database/migrations/2026_04_26_200000_add_normalized_shadow_columns_to_daily_loan.php)

**Shadow Columns Added:**
```sql
segmen_kinerja VARCHAR(50) NULLABLE
    INDEX: idx_segmen_kinerja
    Content: UPPER(REPLACE(REPLACE(...TRIM(segmen_dashboard)...)))

produk_kinerja VARCHAR(100) NULLABLE
    INDEX: idx_produk_kinerja
    Content: UPPER(REPLACE(REPLACE(...TRIM(produk_dashboard)...)))

cabang_normalized VARCHAR(100) NULLABLE
    INDEX: idx_cabang_normalized
    Content: UPPER(TRIM(cabang1))

unit_normalized VARCHAR(100) NULLABLE
    INDEX: idx_unit_normalized
    Content: UPPER(TRIM(unit1))

branch_normalized VARCHAR(100) NULLABLE
    INDEX: idx_branch_normalized
    Content: UPPER(TRIM(branch1))

rm_normalized VARCHAR(100) NULLABLE
    INDEX: idx_rm_normalized
    Content: UPPER(TRIM(pn_pengelola1))

cifno_clean VARCHAR(50) NULLABLE
    INDEX: idx_cifno_clean
    Content: REGEXP_REPLACE(cifno, '[^0-9]', '') - numeric only
```

### Composite Indexes:
```sql
idx_loan_periode_cif (periode, cifno)
idx_loan_periode_rek (periode, nomor_rekening1)
idx_loan_periode_cab_unit (periode, cabang1, unit1)
idx_loan_periode_segmen (periode, segmen_dashboard, produk_dashboard)
idx_loan_periode_produk (periode, produk_dashboard)
idx_snapshot_filter_optimized (periode, segmen_kinerja, produk_kinerja, cabang_normalized)
```

### Normalization Rules (from Migration):
```php
// Pattern used in KinerjaRM normalization
UPPER(
    REPLACE(
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(
                        TRIM($column), 
                    ' ', ''),     // space
                '-', ''),         // hyphen
            '_', ''),             // underscore
        '/', ''),                 // forward slash
    '.', '')                      // period
)
```

---

## 5. MySqlBulkLoadService Location

**File Path:** [app/Services/Import/MySqlBulkLoadService.php](app/Services/Import/MySqlBulkLoadService.php)

**Namespace:** `App\Services\Import\MySqlBulkLoadService`

### Key Features:
```php
class MySqlBulkLoadService
{
    private ?bool $supportsNativeBulkLoad = null;
    private array $tableEngineCache = [];
    private ?DirectLargeFileLoadService $largeFileLoader = null;
    private ?\PDO $persistentPdo = null;  // Reusable PDO connection

    public function supportsNativeBulkLoad(): bool
    public function fallbackInsertBatchSize(int $columnCount = 1): int
    public function assertTransactionalTable(string $tableName, string $operation = 'operasi tulis database'): void
    public function withTableWriteLock(string $tableName, callable $callback, int $timeoutSeconds = 10)
}
```

**Purpose:** Manages MySQL bulk load operations with:
- Native LOAD DATA LOCAL INFILE support detection
- Fallback insert batch size calculation (max 5000 rows per batch)
- Transactional table enforcement (InnoDB check)
- Table write lock management

---

## 6. Migration File - Add Normalized Shadow Columns

**File Path:** [database/migrations/2026_04_26_200000_add_normalized_shadow_columns_to_daily_loan.php](database/migrations/2026_04_26_200000_add_normalized_shadow_columns_to_daily_loan.php)

### Migration Purpose:
Adds pre-computed normalization shadow columns to eliminate function calls in WHERE/GROUP BY clauses.

**Problem Solved:**
- Before: Queries using `UPPER(TRIM()) + REPLACE/REGEXP_REPLACE` in WHERE/GROUP BY = Full Table Scans
- After: Shadow columns with indexes = Index-only scans, **10-50x faster**

### Key Methods:
```php
public function up(): void
    // Adds 7 shadow columns with indexes
    // Backfills existing data
    // Adds composite index idx_snapshot_filter_optimized

public function down(): void
    // Drops all shadow columns and indexes

private function backfillNormalizedColumns(): void
    // SQL UPDATE to compute normalized values from original columns
```

### Backfill SQL Pattern:
```sql
UPDATE daily_loan_dinamis d
SET
    segmen_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
        TRIM(COALESCE(d.segmen_dashboard, '')), ' ', ''), '-', ''), 
        '_', ''), '/', ''), '.', '')),
    produk_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
        TRIM(COALESCE(d.produk_dashboard, '')), ' ', ''), '-', ''), 
        '_', ''), '/', ''), '.', '')),
    cabang_normalized = UPPER(TRIM(COALESCE(d.cabang1, ''))),
    unit_normalized = UPPER(TRIM(COALESCE(d.unit1, ''))),
    branch_normalized = UPPER(TRIM(COALESCE(d.branch1, ''))),
    rm_normalized = UPPER(TRIM(COALESCE(d.pn_pengelola1, ''))),
    cifno_clean = REGEXP_REPLACE(COALESCE(d.cifno, ''), '[^0-9]', '')
WHERE segmen_kinerja IS NULL OR produk_kinerja IS NULL
```

---

## 7. Integration Points

### Query Flow:
```
ReportSnapshotBuilder::rebuildPerformanceRm()
  ↓
ReportSnapshotBuilder::buildPerformanceRmPeriodSnapshot()
  ↓
ReportSnapshotBuilder::computePerformanceRmRows()
  ↓
ReportSnapshotBuilder::fetchSegmentRmAggregates()
  ↓
daily_loan_dinamis (with shadow columns)
  → performance_rm_snapshots (INSERT)
  ↓
ReportSnapshotBuilder::buildPerformanceRmCabangSnapshot()
  ↓
performance_rm_cabang_snapshots (branch-level summary)
```

### Console Command Usage:
```bash
# Rebuild specific period
php artisan snapshot:rebuild-rm --period=2026-04-26

# Force rebuild all periods
php artisan snapshot:rebuild-rm --force
```

---

## Performance Optimization Summary

| Aspect | Benefit |
|--------|---------|
| Shadow Columns | 10-50x faster queries (no function overhead) |
| Index-Only Scans | Reduced disk I/O on composite index |
| cifno_clean | 5x faster GROUP_CONCAT (no REGEXP_REPLACE per row) |
| Batch Inserts | 500-row chunks to manage memory |
| Composite Index | Optimized for common filter patterns |

---

## Important Notes

⚠️ **From Project Notes:**
- Before modifying indexes on `daily_loan_dinamis`, inspect existing indexes for duplicates
- Do not add redundant indexes on this large table
- The database has been optimized; duplicate indexes significantly inflate storage
