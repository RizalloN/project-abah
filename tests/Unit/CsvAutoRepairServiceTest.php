<?php

namespace Tests\Unit;

use App\Services\Import\CsvAutoRepairService;
use Tests\TestCase;

class CsvAutoRepairServiceTest extends TestCase
{
    public function test_daily_loan_candidate_signature_is_memory_safe_for_large_rows(): void
    {
        $service = new CsvAutoRepairService();
        $largeValue = str_repeat('A', 2 * 1024 * 1024);

        $row = $service->parseDailyLoanCsvRow([$largeValue, 'rekening', 'saldo'], ',', 3);

        $this->assertCount(3, $row);
        $this->assertSame('rekening', $row[1]);
        $this->assertSame('normal', $service->getLastParseMeta()['status']);
    }

    public function test_csv_row_preview_samples_large_single_field_without_full_payload(): void
    {
        $service = new CsvAutoRepairService();
        $preview = $service->buildCsvRowPreview([str_repeat("\0", 2 * 1024 * 1024)], ',');

        $this->assertSame('[binary/empty sample]', $preview);
    }
}
