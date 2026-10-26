<?php

namespace Tests\Unit;

use App\Support\PrognosaCalendarWeek;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PrognosaCalendarWeekTest extends TestCase
{
    public static function dates(): array
    {
        return [
            'awal bulan sebelum Sabtu pertama' => ['2026-10-01', 1],
            'Sabtu pertama masih W1' => ['2026-10-03', 1],
            'Minggu setelah Sabtu pertama' => ['2026-10-04', 2],
            'Sabtu kedua masih W2' => ['2026-10-10', 2],
            'Sabtu keempat masih W4' => ['2026-10-24', 4],
            'Minggu setelah cutoff W4' => ['2026-10-25', 5],
            'Senin 26 Okt masuk W5' => ['2026-10-26', 5],
            'Sabtu kelima W5' => ['2026-10-31', 5],
            'September 6 masuk W2' => ['2026-09-06', 2],
            'September 27 masuk W5' => ['2026-09-27', 5],
            'Sabtu tanggal 1 tidak membentuk week sendiri (19 Agu)' => ['2026-08-19', 3],
            'Sabtu tanggal 1 tidak membentuk week sendiri (27 Agu)' => ['2026-08-27', 4],
            'melewati Sabtu kelima tetap dibatasi W5' => ['2026-08-31', 5],
        ];
    }

    #[DataProvider('dates')]
    public function test_week_follows_the_saturday_cutoff_of_the_real_date(string $date, int $week): void
    {
        $this->assertSame($week, PrognosaCalendarWeek::forDate(Carbon::parse($date.' 09:30:00')));
    }
}
