<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use Tests\TestCase;

class ImportExcelControllerDecimalNormalizationTest extends TestCase
{
    public function test_normalize_decimal_value_keeps_single_comma_rate_values_as_decimals(): void
    {
        $controller = new class extends ImportExcelController {
            public function normalizeDecimalForTest($value): ?string
            {
                return $this->normalizeDecimalValue($value);
            }
        };

        $this->assertSame('0.05', $controller->normalizeDecimalForTest('0,045'));
        $this->assertSame('0.08', $controller->normalizeDecimalForTest('0,0813'));
        $this->assertSame('150000000.00', $controller->normalizeDecimalForTest('150,000,000.00'));
        $this->assertSame('450000000.00', $controller->normalizeDecimalForTest('450,000,000.00'));
    }
}
