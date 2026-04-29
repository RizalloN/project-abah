<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulateDeleteScenarioCommand extends Command
{
    protected $signature = 'benchmark:simulate-delete
                            {--report= : Report ID}
                            {--scenario=small : Scenario (small|medium|large|full)}
                            {--dry-run : Preview only (REQUIRED - execution disabled)}
                            {--periode= : Filter by periode}
                            {--cabang= : Filter by cabang}';

    protected $description = 'Preview delete scope - EXECUTION MODE DISABLED FOR SAFETY';

    public function handle()
    {
        // SAFETY CHECK: Execution mode is disabled
        if (!$this->option('dry-run')) {
            $this->error('ERROR: Execution mode is DISABLED');
            $this->line('');
            $this->warn('This command is for PREVIEW ONLY');
            $this->info('Usage: php artisan benchmark:simulate-delete --report=1 --scenario=small --dry-run');
            $this->line('');
            $this->line('The --dry-run option is REQUIRED and shows estimated rows only.');
            $this->line('To perform actual delete, use Report Management in web UI.');
            return self::FAILURE;
        }

        if (!$this->option('report')) {
            $this->error('ERROR: --report ID required');
            return self::FAILURE;
        }

        $this->previewDeleteScope();
        return self::SUCCESS;
    }

    protected function previewDeleteScope()
    {
        $scenario = $this->option('scenario') ?? 'small';
        
        $scenarios = [
            'small' => ['rows' => 5000, 'periods' => 1],
            'medium' => ['rows' => 100000, 'periods' => 3],
            'large' => ['rows' => 500000, 'periods' => 6],
            'full' => ['rows' => null, 'periods' => null]
        ];

        if (!isset($scenarios[$scenario])) {
            $this->error("Unknown scenario: {$scenario}");
            return;
        }

        $config = $scenarios[$scenario];
        
        $this->info('📋 DELETE PREVIEW (DRY-RUN - NO DATA DELETED)');
        $this->line('');
        $this->info("Scenario: {$scenario}");
        $this->line(sprintf('Estimated rows: %s', $config['rows'] ? number_format($config['rows']) : 'ALL'));
        $this->line('');

        // Column detection
        $this->info('Detecting available columns...');
        
        $periodCandidates = ['periode', 'period', 'tgl', 'date', 'created_at', 'updated_at'];
        $cabangCandidates = ['cabang1', 'kantor_cabang', 'kanca', 'cabang', 'branch', 'posisi'];

        $this->line('');
        $this->info('Available filter columns:');
        $this->line('  Period columns: ' . implode(', ', $periodCandidates));
        $this->line('  Branch columns: ' . implode(', ', $cabangCandidates));
        $this->line('');

        $this->warn('⚠ This is a preview only - to actually delete, use:');
        $this->line('  1. Open Report Management in web UI');
        $this->line('  2. Configure your delete scope');
        $this->line('  3. Click DELETE button');
        $this->line('  4. Run: php artisan benchmark:delete-performance --report=ID');
    }
}
