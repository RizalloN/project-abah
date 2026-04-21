<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportFileController;
use Tests\TestCase;

class ImportFileControllerTest extends TestCase
{
    public function test_raw_load_data_fast_path_is_disabled_for_qris_detail_report(): void
    {
        $controller = new ImportFileController();
        $csvPath = storage_path('framework/testing/qris_detail_quotes.csv');

        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'PERIODE|POSISI|REGION|RGDESC|MAINBR|MBDESC',
            '2025-04|2025-04-30|R|KANWIL MALANG|45|TOKO "SUMBER AGUNG"',
        ]) . "\n");

        try {
            $skip = $this->invokeMethod($controller, 'shouldSkipRawLoadDataFastPath', [
                'jumlah_merchant_qris_detail',
                $csvPath,
                '|',
            ]);

            $this->assertTrue($skip);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_raw_load_data_fast_path_is_disabled_when_sample_contains_quotes(): void
    {
        $controller = new ImportFileController();
        $csvPath = storage_path('framework/testing/generic_quotes.csv');

        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'col1|col2|col3',
            'A|merchant "alpha"|C',
        ]) . "\n");

        try {
            $skip = $this->invokeMethod($controller, 'shouldSkipRawLoadDataFastPath', [
                'merchant_qris',
                $csvPath,
                '|',
            ]);

            $this->assertTrue($skip);
        } finally {
            @unlink($csvPath);
        }
    }

    private function invokeMethod(object $target, string $method, array $args = [])
    {
        $reflection = new \ReflectionClass($target);
        $methodRef = $reflection->getMethod($method);
        $methodRef->setAccessible(true);

        return $methodRef->invokeArgs($target, $args);
    }
}
