<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array{period:string}>
     */
    private array $sources = [
        'gi405_recovery' => ['period' => 'periode'],
        'dly_kap_resegmentasi' => ['period' => 'periode'],
        'l1133' => ['period' => 'periode'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('snapshot_dirty_periods') || !in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($this->sources as $table => $config) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $config['period'])) {
                continue;
            }

            $this->dropTriggers($table);
            $this->createDirtyTrigger($table, 'insert', $config['period']);
            $this->createDirtyTrigger($table, 'update', $config['period']);
            $this->createDirtyTrigger($table, 'delete', $config['period']);
        }
    }

    public function down(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (array_keys($this->sources) as $table) {
            if (Schema::hasTable($table)) {
                $this->dropTriggers($table);
            }
        }
    }

    private function createDirtyTrigger(string $table, string $event, string $periodColumn): void
    {
        $trigger = $this->triggerName($table, $event);
        $rowAlias = $event === 'delete' ? 'OLD' : 'NEW';
        $periodValue = "{$rowAlias}.`{$periodColumn}`";
        $periodExpr = "DATE_FORMAT({$periodValue}, '%Y-%m-%d')";
        $sessionKey = '@' . str_replace(['-', '.'], '_', $table) . '_snapshot_period_keys';

        $oldPeriodBlock = '';
        if ($event === 'update') {
            $oldPeriodBlock = $this->dirtyMarkerSql(
                $table,
                'OLD',
                $periodColumn,
                "DATE_FORMAT(OLD.`{$periodColumn}`, '%Y-%m-%d')",
                "OLD.`{$periodColumn}`",
                $sessionKey,
                true
            );
        }

        $body = $this->dirtyMarkerSql($table, $rowAlias, $periodColumn, $periodExpr, $periodValue, $sessionKey, false);

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
        bool $onlyWhenChanged
    ): string {
        $changedGuard = $onlyWhenChanged
            ? " AND (NEW.`{$periodColumn}` IS NULL OR OLD.`{$periodColumn}` <> NEW.`{$periodColumn}`)"
            : '';

        return "
            IF {$periodValue} IS NOT NULL{$changedGuard} THEN
                SET {$sessionKey} = COALESCE({$sessionKey}, '');
                SET @snapshot_dirty_period_key = {$periodExpr};
                SET @snapshot_dirty_shard_key = '*';
                SET @snapshot_dirty_dedupe_key = CONCAT_WS(':', @snapshot_dirty_period_key, 'period', @snapshot_dirty_shard_key);

                IF FIND_IN_SET(@snapshot_dirty_dedupe_key, {$sessionKey}) = 0 THEN
                    INSERT INTO snapshot_dirty_periods
                        (source_table, period_key, shard_type, shard_key, dirty_since, dirty_row_count, attempts, created_at, updated_at)
                    VALUES
                        ('{$table}', @snapshot_dirty_period_key, 'period', @snapshot_dirty_shard_key, NOW(6), 1, 0, NOW(6), NOW(6))
                    ON DUPLICATE KEY UPDATE
                        dirty_since = LEAST(dirty_since, VALUES(dirty_since)),
                        dirty_row_count = dirty_row_count + 1,
                        claimed_at = NULL,
                        claim_token = NULL,
                        dirty_since_at_claim = NULL,
                        last_error = NULL,
                        attempts = 0,
                        updated_at = NOW(6);

                    SET {$sessionKey} = CONCAT_WS(',', {$sessionKey}, @snapshot_dirty_dedupe_key);
                END IF;
            END IF;
        ";
    }

    private function dropTriggers(string $table): void
    {
        foreach (['insert', 'update', 'delete'] as $event) {
            DB::unprepared('DROP TRIGGER IF EXISTS ' . $this->triggerName($table, $event));
        }
    }

    private function triggerName(string $table, string $event): string
    {
        return '`trg_' . str_replace('`', '``', $table) . '_after_' . $event . '`';
    }
};
