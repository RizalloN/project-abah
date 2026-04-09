<?php

namespace App\Console\Commands;

use App\Http\Controllers\DashboardPinjamanReportController;
use App\Http\Controllers\DashboardSimpananController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Throwable;

class WarmDashboardCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:warm-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up the report dashboard caches for faster initial loading';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Mulai pemanasan cache (Cache Warming) untuk Dasbor Laporan...');

        $this->warmDashboardPinjaman();
        // $this->warmDashboardSimpanan(); // Optional, if you want to warm other dashboards as well

        $this->info('Cache warming selesai!');
        return self::SUCCESS;
    }

    private function warmDashboardPinjaman()
    {
        $this->info('-> Memproses Dashboard Pinjaman...');

        try {
            $controller = app()->make(DashboardPinjamanReportController::class);

            // Buat request tiruan (tanpa parameter periode, agar otomatis mengambil periode terbaru)
            $request = Request::create('/report/dashboard-pinjaman', 'GET');

            $this->line('   [1/3] Mengambil periode dan merender index...');
            $controller->index($request);

            $this->line('   [2/3] Memuat filter default...');
            $controller->filters($request);

            $this->line('   [3/3] Menghitung matriks data utama...');
            $controller->data($request);

            $this->info('   Berhasil memanaskan cache Dashboard Pinjaman.');
        } catch (Throwable $e) {
            $this->error('   Gagal memanaskan cache Dashboard Pinjaman: ' . $e->getMessage());
        }
    }
}
