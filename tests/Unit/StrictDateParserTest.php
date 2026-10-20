<?php

namespace Tests\Unit;

use App\Support\StrictDateParser;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('reportDateTimeProvider')]
    public function test_it_normalizes_report_dates_with_meridiem_without_losing_the_source_day(string $value, string $expected): void
    {
        $this->assertSame($expected, StrictDateParser::normalize($value));
    }

    public static function reportDateTimeProvider(): array
    {
        return [
            'lw325 two digit month and day' => ['10/18/2026 12:00:00 AM', '2026-10-18'],
            'lw325 one digit month' => ['4/27/2026 12:00:00 AM', '2026-04-27'],
            'one digit month and day' => ['4/1/2026 12:00:00 AM', '2026-04-01'],
            'padded ambiguous US export' => ['04/01/2026 12:00:00 AM', '2026-04-01'],
            'unambiguous day first export' => ['18/10/2026 12:00:00 AM', '2026-10-18'],
            'afternoon timestamp' => ['10/18/2026 1:05:09 PM', '2026-10-18'],
            'lowercase meridiem' => ['10/18/2026 11:59:59 pm', '2026-10-18'],
            'minute precision' => ['10/18/2026 12:00 AM', '2026-10-18'],
            'surrounding whitespace' => [' 10/18/2026 12:00:00 AM ', '2026-10-18'],
            'valid leap day' => ['02/29/2024 12:00:00 AM', '2024-02-29'],
        ];
    }

    #[DataProvider('invalidReportDateTimeProvider')]
    public function test_it_rejects_invalid_meridiem_dates_and_times(string $value): void
    {
        $this->assertNull(StrictDateParser::normalize($value));
    }

    public static function invalidReportDateTimeProvider(): array
    {
        return [
            'non leap day' => ['02/29/2026 12:00:00 AM'],
            'impossible month day' => ['10/32/2026 12:00:00 AM'],
            'impossible day first date' => ['31/02/2026 12:00:00 AM'],
            'invalid twelve hour time' => ['10/18/2026 13:00:00 PM'],
            'invalid minute' => ['10/18/2026 12:60:00 AM'],
            'invalid second' => ['10/18/2026 12:00:60 AM'],
            'trailing data' => ['10/18/2026 12:00:00 AM invalid'],
        ];
    }

    public function test_it_accepts_textual_month_dates(): void
    {
        $this->assertSame('2026-04-14', StrictDateParser::normalize('14 April 2026'));
        $this->assertSame('2026-04-14', StrictDateParser::normalize('14 Apr 2026'));
        $this->assertSame('2026-07-13', StrictDateParser::normalize('13 Jul 26'));
        $this->assertSame('2026-07-13', StrictDateParser::normalize('13-Jul-26'));
        $this->assertSame('2026-07-13', StrictDateParser::normalize('13 Juli 26'));
    }

    public function test_locale_month_names_can_be_disabled_for_scoped_imports(): void
    {
        $this->assertNull(StrictDateParser::normalize('14 Mei 2026', [], false));
        $this->assertSame('2026-04-14', StrictDateParser::normalize('14 Apr 2026', [], false));
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

    public function test_it_builds_mysql_case_expression_for_textual_month_dates(): void
    {
        $expression = StrictDateParser::buildMySqlCaseExpression("NULLIF(TRIM(COALESCE(`month_day_year_of_posisi`, '')), '')");

        $this->assertStringContainsString("%e %M %Y", $expression);
        $this->assertStringContainsString("%e %b %Y", $expression);
        $this->assertStringContainsString("%e %M %y", $expression);
        $this->assertStringContainsString("%e %b %y", $expression);
    }
}
