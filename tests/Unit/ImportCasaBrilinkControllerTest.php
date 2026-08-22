<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportCasaBrilinkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class ImportCasaBrilinkControllerTest extends TestCase
{
    public function test_casa_preview_builds_a_bounded_mapped_sample_without_changing_values(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'casa_brilink_preview_').'.csv';
        $handle = fopen($path, 'w');
        $branches = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];

        fputcsv($handle, [
            'row_num', 'region', 'rgdesc', 'mainbr', 'mbdesc', 'branch', 'brdesc',
            'kode_agen', 'mid_code', 'account', 'keterangan', 'sumber',
            'jml_nominal_casa', 'textbox9', 'cifno',
        ]);

        for ($index = 1; $index <= 1205; $index++) {
            fputcsv($handle, [
                $index,
                '6',
                'RO Malang',
                '0045',
                $branches[($index - 1) % count($branches)],
                '0045',
                'UNIT '.$index,
                'AG-'.$index,
                'MID-'.$index,
                'ACC-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'Aktif',
                'BRILINK WEB',
                '1250000',
                '2500000',
                'CIF-'.$index,
            ]);
        }
        fclose($handle);

        try {
            $controller = new ImportCasaBrilinkController;
            $context = $this->invokeMethod($controller, 'buildCsvContext', [$path, '2026-08', 'auto']);
            [$previewRows, $uniqueValues] = $this->invokeMethod($controller, 'collectMappedPreviewSample', [
                $path,
                $context,
                100,
                1000,
                200,
            ]);

            $this->assertCount(100, $previewRows);
            $this->assertSame('2026-08-31', $previewRows[0][0]);
            $this->assertSame('KC Madiun', $previewRows[0][4]);
            $this->assertSame('ACC-0001', $previewRows[0][9]);
            $this->assertSame('1250000.00', $previewRows[0][12]);
            $this->assertCount(4, $uniqueValues[4]);
            $this->assertCount(200, $uniqueValues[9]);
            $this->assertNotContains('ACC-1205', $uniqueValues[9]);
        } finally {
            @unlink($path);
        }
    }

    public function test_casa_branch_filter_endpoint_reads_all_branches_beyond_the_initial_sample(): void
    {
        $relativePath = 'casa_brilink_imports/filter-branches-'.Str::random(12).'.csv';
        Storage::makeDirectory('casa_brilink_imports');
        $handle = fopen(Storage::path($relativePath), 'w');
        $branches = ['KC Banyuwangi'];
        foreach (range(2, 24) as $branchNumber) {
            $branches[] = 'KC CABANG '.str_pad((string) $branchNumber, 2, '0', STR_PAD_LEFT);
        }

        fputcsv($handle, [
            'row_num', 'region', 'rgdesc', 'mainbr', 'mbdesc', 'branch', 'brdesc',
            'kode_agen', 'mid_code', 'account', 'keterangan', 'sumber',
            'jml_nominal_casa', 'textbox9', 'cifno',
        ]);

        $rowNumber = 0;
        foreach ($branches as $branchIndex => $branch) {
            $rowsForBranch = $branchIndex === 0 ? 1100 : 2;
            foreach (range(1, $rowsForBranch) as $sequence) {
                $rowNumber++;
                fputcsv($handle, [
                    $rowNumber,
                    'R',
                    'KANWIL MALANG',
                    $branchIndex + 1,
                    $branch,
                    $branchIndex + 1,
                    $branch,
                    'AG-'.$rowNumber,
                    'MID-'.$rowNumber,
                    'ACC-'.$rowNumber,
                    '-',
                    'penampungan',
                    '0.00',
                    '100.00',
                    'CIF-'.$rowNumber,
                ]);
            }
        }
        fclose($handle);

        try {
            session([
                'casa_brilink_file' => $relativePath,
                'casa_brilink_periode' => '2026-08',
            ]);

            $controller = new ImportCasaBrilinkController;
            $context = $this->invokeMethod($controller, 'buildCsvContext', [
                Storage::path($relativePath),
                '2026-08',
                'auto',
            ]);
            [, $initialOptions] = $this->invokeMethod($controller, 'collectMappedPreviewSample', [
                Storage::path($relativePath),
                $context,
                100,
                1000,
                200,
            ]);

            $this->assertSame(['KC Banyuwangi'], $initialOptions[4]);

            $request = Request::create('/import/casa-brilink/preview/filter-options', 'GET', [
                'file_path' => $relativePath,
                'delimiter' => 'auto',
                'column_index' => 4,
                'active_filters_json' => '{}',
            ]);
            $payload = $controller->previewFilterOptions($request)->getData(true);

            $this->assertSame('success', $payload['status']);
            $this->assertCount(24, $payload['values']);
            $this->assertContains('KC Banyuwangi', $payload['values']);
            $this->assertContains('KC CABANG 24', $payload['values']);
        } finally {
            Storage::delete($relativePath);
        }
    }

    private function invokeMethod(object $target, string $method, array $arguments)
    {
        $reflection = new ReflectionClass($target);
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        return $methodReflection->invokeArgs($target, $arguments);
    }
}
