<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SafeSqlDumpRecoveryService
{
    /**
     * Recover a database from a mysqldump-style SQL file without dropping live tables.
     *
     * The recovery strategy is intentionally conservative:
     * - DROP TABLE statements are skipped.
     * - CREATE TABLE statements are rewritten to CREATE TABLE IF NOT EXISTS.
     * - INSERT INTO statements are rewritten to INSERT IGNORE INTO.
     * - Other housekeeping statements are skipped.
     *
     * This makes the restore idempotent enough to safely run against a partially
     * recovered database while still filling missing tables/rows from the dump.
     *
     * @return array{statements_processed:int, statements_executed:int, tables_created:int, insert_statements:int}
     */
    public function restore(string $dumpPath, ?callable $progressCallback = null): array
    {
        $dumpPath = $this->normalizePath($dumpPath);
        if (!is_file($dumpPath)) {
            throw new RuntimeException("File dump tidak ditemukan: {$dumpPath}");
        }

        $reader = fopen($dumpPath, 'rb');
        if (!is_resource($reader)) {
            throw new RuntimeException('Dump SQL tidak bisa dibaca.');
        }

        $this->setSessionRestoreMode(true);

        $buffer = '';
        $processed = 0;
        $executed = 0;
        $tablesCreated = 0;
        $insertStatements = 0;
        $bytesRead = 0;
        $fileSize = max(1, (int) filesize($dumpPath));
        $lastPercent = -1;

        try {
            while (($line = fgets($reader)) !== false) {
                $bytesRead += strlen($line);
                $buffer .= $line;

                if (!$this->statementEnds($line)) {
                    continue;
                }

                $statement = trim($buffer);
                $buffer = '';
                $processed++;

                $rewritten = $this->rewriteStatement($statement);
                if ($rewritten === null) {
                    $lastPercent = $this->emitProgress($progressCallback, $bytesRead, $fileSize, $lastPercent, $processed, $executed);
                    continue;
                }

                DB::unprepared($this->stripTrailingSemicolon($rewritten));
                $executed++;

                if ($this->isCreateTableStatement($statement)) {
                    $tablesCreated++;
                }

                if ($this->isInsertStatement($statement)) {
                    $insertStatements++;
                }

                $lastPercent = $this->emitProgress($progressCallback, $bytesRead, $fileSize, $lastPercent, $processed, $executed);
            }
        } catch (Throwable $e) {
            throw $e;
        } finally {
            fclose($reader);
            $this->setSessionRestoreMode(false);
        }

        return [
            'statements_processed' => $processed,
            'statements_executed' => $executed,
            'tables_created' => $tablesCreated,
            'insert_statements' => $insertStatements,
        ];
    }

    private function rewriteStatement(string $statement): ?string
    {
        $trimmed = ltrim($statement);

        if ($trimmed === '' || $this->shouldSkipStatement($trimmed)) {
            return null;
        }

        if (preg_match('/^CREATE TABLE `([^`]+)`/i', $trimmed) === 1) {
            return preg_replace('/^CREATE TABLE `([^`]+)`/i', 'CREATE TABLE IF NOT EXISTS `$1`', $trimmed, 1);
        }

        if (preg_match('/^INSERT INTO `([^`]+)`/i', $trimmed) === 1) {
            return preg_replace('/^INSERT INTO `([^`]+)`/i', 'INSERT IGNORE INTO `$1`', $trimmed, 1);
        }

        return null;
    }

    private function shouldSkipStatement(string $statement): bool
    {
        return preg_match('/^(--|\/\*[^!]|SET\s+|USE\s+|DROP\s+DATABASE|CREATE\s+DATABASE|LOCK\s+TABLES|UNLOCK\s+TABLES)/i', $statement) === 1
            || preg_match('/^DROP TABLE IF EXISTS `/i', $statement) === 1;
    }

    private function isCreateTableStatement(string $statement): bool
    {
        return preg_match('/^CREATE TABLE `/i', $statement) === 1;
    }

    private function isInsertStatement(string $statement): bool
    {
        return preg_match('/^INSERT INTO `/i', $statement) === 1;
    }

    private function statementEnds(string $line): bool
    {
        return preg_match('/;\s*$/', rtrim($line)) === 1;
    }

    private function stripTrailingSemicolon(string $statement): string
    {
        return preg_replace('/;\s*$/', '', trim($statement)) ?? trim($statement);
    }

    private function normalizePath(string $dumpPath): string
    {
        $dumpPath = trim($dumpPath);
        if ($dumpPath === '') {
            throw new RuntimeException('Path dump kosong.');
        }

        return str_replace(['"', "'"], '', $dumpPath);
    }

    private function setSessionRestoreMode(bool $enabled): void
    {
        if ($enabled) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::statement('SET UNIQUE_CHECKS=0');
            return;
        }

        DB::statement('SET UNIQUE_CHECKS=1');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function emitProgress(
        ?callable $progressCallback,
        int $bytesRead,
        int $fileSize,
        int $lastPercent,
        int $processed,
        int $executed
    ): int {
        if ($progressCallback === null) {
            return (int) floor(($bytesRead / max(1, $fileSize)) * 100);
        }

        $percent = (int) floor(($bytesRead / max(1, $fileSize)) * 100);
        if ($percent === $lastPercent) {
            return $lastPercent;
        }

        $progressCallback([
            'bytes_read' => $bytesRead,
            'total_bytes' => $fileSize,
            'progress_percent' => $percent,
            'statements_processed' => $processed,
            'statements_executed' => $executed,
        ]);

        return $percent;
    }
}
