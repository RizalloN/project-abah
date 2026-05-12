<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array{period:string, snapshots:array<string, string>, shard?:string}>
     */
    private array $sources = [
        'daily_loan_dinamis' => [
            'period' => 'periode',
            'snapshots' => [
                'dashboard_pinjaman_snapshots' => 'periode',
                'dashboard_pinjaman_chart_periodik_snapshots' => 'periode',
                'dashboard_harian_snapshots' => 'snapshot_period',
                'rasio_casa_debitur_snapshots' => 'loan_period',
                'rasio_casa_debitur_uker_snapshots' => 'loan_period',
                'performance_rm_snapshots' => 'periode',
            ],
        ],
        'simpanan_multipn' => [
            'period' => 'posisi',
            'shard' => "UPPER(TRIM(REPLACE(REPLACE(COALESCE(%s.kantor_cabang, ''), 'KC ', ''), 'KC', '')))",
            'snapshots' => [
                'dashboard_simpanan_snapshots' => 'snapshot_period',
                'dashboard_simpanan_branch_snapshots' => 'snapshot_period',
                'dashboard_harian_snapshots' => 'snapshot_period',
                'rekening_dormant_snapshots' => 'posisi',
                'rasio_casa_debitur_snapshots' => 'casa_period',
                'rasio_casa_debitur_uker_snapshots' => 'casa_period',
                'performance_rm_snapshots' => 'periode',
            ],
        ],
        'ssa_simpanan' => [
            'period' => 'Month_Day_Year_of_Posisi',
            'snapshots' => [
                'dashboard_harian_snapshots' => 'snapshot_period',
                'ssa_simpanan_snapshots' => 'periode',
            ],
        ],
        'ssa_pinjaman' => [
            'period' => 'month_day_year_of_periode',
            'snapshots' => [
                'dashboard_harian_snapshots' => 'snapshot_period',
            ],
        ],
        'lw325_ph' => [
            'period' => 'periode',
            'snapshots' => [
                'dashboard_harian_snapshots' => 'snapshot_period',
            ],
        ],
        'hourly_dpk' => [
            'period' => 'posisi',
            'snapshots' => [
                'dashboard_harian_snapshots' => 'snapshot_period',
            ],
        ],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('snapshot_dirty_periods') || !in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($this->sources as $table => $config) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $this->dropTriggers($table);
            $this->createDirtyTrigger($table, 'insert', $config);
            $this->createDirtyTrigger($table, 'update', $config);
            $this->createDirtyTrigger($table, 'delete', $config);
        }
    }

    public function down(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($this->sources as $table => $config) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $this->dropTriggers($table);
            $this->createDeleteSnapshotTrigger($table, 'insert', $config);
            $this->createDeleteSnapshotTrigger($table, 'update', $config);
            $this->createDeleteSnapshotTrigger($table, 'delete', $config);
        }
    }

    /**
     * @param array{period:string, snapshots:array<string, string>, shard?:string} $config
     */
    private function createDirtyTrigger(string $table, string $event, array $config): void
    {
        $trigger = $this->triggerName($table, $event);
        $periodColumn = $config['period'];
        $rowAlias = $event === 'delete' ? 'OLD' : 'NEW';
        $periodExpr = "DATE_FORMAT({$rowAlias}.`{$periodColumn}`, '%Y-%m-%d')";
        $periodValue = "{$rowAlias}.`{$periodColumn}`";
        $sessionKey = '@' . str_replace(['-', '.'], '_', $table) . '_snapshot_period_keys';
        $shardType = isset($config['shard']) ? 'branch' : 'period';
        $shardExpr = isset($config['shard'])
            ? sprintf($config['shard'], $rowAlias)
            : "'*'";

        $oldPeriodBlock = '';
        if ($event === 'update') {
            $oldPeriodExpr = "DATE_FORMAT(OLD.`{$periodColumn}`, '%Y-%m-%d')";
            $oldShardExpr = isset($config['shard'])
                ? sprintf($config['shard'], 'OLD')
                : "'*'";
            $oldPeriodBlock = $this->dirtyMarkerSql($table, 'OLD', $periodColumn, $oldPeriodExpr, "OLD.`{$periodColumn}`", $sessionKey, $shardType, $oldShardExpr, true);
        }

        $body = $this->dirtyMarkerSql($table, $rowAlias, $periodColumn, $periodExpr, $periodValue, $sessionKey, $shardType, $shardExpr, false);

        DB::unprepared("
            CREATE TRIGGER {$trigger}
            AFTER " . strtoupper($event) . " ON `{$table}`
            FOR EACH ROW
            BEGIN
                IF COALESCE(@skip_snapshot_invalidation, 0) = 0 THEN
                    {$body}
                    {$oldPeriodBlock}
                END IF;
            END
        ");
    }

    private function dirtyMarkerSql(
        string $table,
        string $rowAlias,
        string $periodColumn,
        string $periodExpr,
        string $periodValue,
        string $sessionKey,
        string $shardType,
        string $shardExpr,
        bool $onlyWhenChanged
    ): string {
        $changedGuard = $onlyWhenChanged
            ? " AND (NEW.`{$periodColumn}` IS NULL OR OLD.`{$periodColumn}` <> NEW.`{$periodColumn}`)"
            : '';

        return "
            IF {$periodValue} IS NOT NULL{$changedGuard} THEN
                SET {$sessionKey} = COALESCE({$sessionKey}, '');
                SET @snapshot_dirty_period_key = {$periodExpr};
                SET @snapshot_dirty_shard_key = COALESCE(NULLIF({$shardExpr}, ''), '*');
                SET @snapshot_dirty_dedupe_key = CONCAT_WS(':', @snapshot_dirty_period_key, '{$shardType}', @snapshot_dirty_shard_key);

                IF FIND_IN_SET(@snapshot_dirty_dedupe_key, {$sessionKey}) = 0 THEN
                    INSERT INTO snapshot_dirty_periods
                        (source_table, period_key, shard_type, shard_key, dirty_since, dirty_row_count, attempts, created_at, updated_at)
                    VALUES
                        ('{$table}', @snapshot_dirty_period_key, '{$shardType}', @snapshot_dirty_shard_key, NOW(6), 1, 0, NOW(6), NOW(6))
                    ON DUPLICATE KEY UPDATE
                        dirty_since = LEAST(dirty_since, VALUES(dirty_since)),
                        dirty_row_count = dirty_row_count + 1,
                        claimed_at = NULL,
                        claim_token = NULL,
                        dirty_since_at_claim = NULL,
                        last_error = NULL,
                        updated_at = NOW(6);

                    SET {$sessionKey} = CONCAT_WS(',', {$sessionKey}, @snapshot_dirty_dedupe_key);
                END IF;
            END IF;
        ";
    }

    /**
     * @param array{period:string, snapshots:array<string, string>, shard?:string} $config
     */
    private function createDeleteSnapshotTrigger(string $table, string $event, array $config): void
    {
        $trigger = $this->triggerName($table, $event);
        $rowAlias = $event === 'delete' ? 'OLD' : 'NEW';
        $periodColumn = $config['period'];
        $deletes = $this->snapshotDeleteSql($rowAlias, $periodColumn, $config['snapshots']);

        $oldDeletes = '';
        if ($event === 'update') {
            $oldDeletes = "
                IF OLD.`{$periodColumn}` IS NOT NULL AND (NEW.`{$periodColumn}` IS NULL OR OLD.`{$periodColumn}` <> NEW.`{$periodColumn}`) THEN
                    {$this->snapshotDeleteSql('OLD', $periodColumn, $config['snapshots'])}
                END IF;
            ";
        }

        DB::unprepared("
            CREATE TRIGGER {$trigger}
            AFTER " . strtoupper($event) . " ON `{$table}`
            FOR EACH ROW
            BEGIN
                IF {$rowAlias}.`{$periodColumn}` IS NOT NULL THEN
                    {$deletes}
                END IF;
                {$oldDeletes}
            END
        ");
    }

    /**
     * @param array<string, string> $snapshots
     */
    private function snapshotDeleteSql(string $rowAlias, string $periodColumn, array $snapshots): string
    {
        $sql = [];
        foreach ($snapshots as $snapshotTable => $snapshotPeriodColumn) {
            if (!Schema::hasTable($snapshotTable)) {
                continue;
            }

            $sql[] = "DELETE FROM `{$snapshotTable}` WHERE `{$snapshotPeriodColumn}` = {$rowAlias}.`{$periodColumn}`;";
        }

        return implode("\n", $sql);
    }

    private function dropTriggers(string $table): void
    {
        foreach (['insert', 'update', 'delete'] as $event) {
            DB::unprepared('DROP TRIGGER IF EXISTS ' . $this->triggerName($table, $event));
        }
    }

    private function triggerName(string $table, string $event): string
    {
        $name = match ($table) {
            'daily_loan_dinamis' => 'trg_daily_loan_after_' . $event,
            'simpanan_multipn' => 'trg_simpanan_after_' . $event,
            default => 'trg_' . $table . '_after_' . $event,
        };

        return '`' . str_replace('`', '``', $name) . '`';
    }
};
