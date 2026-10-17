<?php

namespace App\Console\Commands;

use App\Http\Controllers\DashboardPinjamanReportController;
use App\Http\Controllers\DashboardHarianController;
use App\Http\Controllers\RasioCasaDebiturController;
use App\Http\Controllers\RekeningDormantController;
use App\Http\Controllers\DashboardSimpananController;
use App\Support\UserBranchScope;
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
        $this->warmDashboardHarian();
        $this->warmRasioCasaDebitur();
        $this->warmRekeningDormant();

        // Branch users have their own scoped cache keys; warm them too so the first
        // visit after an import does not hit a cold (and possibly timing out) build.
        foreach (UserBranchScope::branchScopes() as $scope) {
            $this->info('Memanaskan cache cabang ' . $scope['label'] . '...');
            UserBranchScope::runAs($scope, function () use ($scope): void {
                $input = UserBranchScope::requestInput($scope);
                $this->warmDashboardPinjaman($input);
                $this->warmDashboardHarian($input);
                $this->warmRasioCasaDebitur($input);
                $this->warmRekeningDormant($input);
            });
        }

        $this->info('Cache warming selesai!');
        return self::SUCCESS;
    }

    private function warmDashboardPinjaman(array $input = [])
    {
        $this->info('-> Memproses Dashboard Pinjaman...');

        try {
            $controller = app()->make(DashboardPinjamanReportController::class);

            // Buat request tiruan (tanpa parameter periode, agar otomatis mengambil periode terbaru)
            $request = Request::create('/report/dashboard-pinjaman', 'GET', $input);

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

    private function warmDashboardHarian(array $input = []): void
    {
        $this->info('-> Memproses Dashboard Harian...');

        try {
            $controller = app()->make(DashboardHarianController::class);
            $request = Request::create('/dashboard-harian', 'GET', $input);

            $this->line('   [1/2] Mengambil halaman index...');
            $controller->index($request);

            $this->line('   [2/2] Memuat payload data utama...');
            $controller->data($request);

            $this->info('   Berhasil memanaskan cache Dashboard Harian.');
        } catch (Throwable $e) {
            $this->error('   Gagal memanaskan cache Dashboard Harian: ' . $e->getMessage());
        }
    }

    private function warmRasioCasaDebitur(array $input = []): void
    {
        $this->info('-> Memproses Rasio CASA Debitur...');

        try {
            $controller = app()->make(RasioCasaDebiturController::class);
            $request = Request::create('/report/rekening-transaksi-debitur/rasio-casa-debitur', 'POST', $input);

            $controller->index();
            $controller->fetchData($request);

            $this->info('   Berhasil memanaskan cache Rasio CASA Debitur.');
        } catch (Throwable $e) {
            $this->error('   Gagal memanaskan cache Rasio CASA Debitur: ' . $e->getMessage());
        }
    }

    private function warmRekeningDormant(array $input = []): void
    {
        $this->info('-> Memproses Rekening Dormant...');

        try {
            $controller = app()->make(RekeningDormantController::class);
            $getRequest = Request::create('/report/rekening-transaksi-debitur/rekening-dormant', 'GET', $input);
            $postRequest = Request::create('/report/data/rekening-dormant', 'POST', $input);

            $controller->index();
            $controller->filters($getRequest);
            $controller->fetchData($postRequest);

            $this->info('   Berhasil memanaskan cache Rekening Dormant.');
        } catch (Throwable $e) {
            $this->error('   Gagal memanaskan cache Rekening Dormant: ' . $e->getMessage());
        }
    }
}
