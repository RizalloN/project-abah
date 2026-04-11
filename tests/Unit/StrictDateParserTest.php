<?php

namespace Tests\Unit;

use App\Support\StrictDateParser;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Tests\TestCase;

class StrictDateParserTest extends TestCase
{
    public function test_it_prefers_day_first_for_ambiguous_slash_dates(): void
    {
        $this->assertSame('2026-04-04', StrictDateParser::normalize('04/04/2026'));
        $this->assertSame('2026-04-04', StrictDateParser::normalize('04-04-2026'));
    }

    public function test_it_accepts_month_first_only_when_unambiguous(): void
    {
        $this->assertSame('2024-04-20', StrictDateParser::normalize('04/20/2024'));
        $this->assertSame('2024-04-20', StrictDateParser::normalize('04-20-2024'));
    }

    public function test_it_normalizes_excel_serial_dates(): void
    {
        $serial = (string) ExcelDate::PHPToExcel(new \DateTimeImmutable('2026-04-04'));

        $this->assertSame('2026-04-04', StrictDateParser::normalize($serial));
    }

    public function test_it_rejects_invalid_calendar_dates(): void
    {
        $this->assertNull(StrictDateParser::normalize('31/02/2026'));
        $this->assertNull(StrictDateParser::normalize('2026-13-04'));
    }
}
