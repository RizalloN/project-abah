<?php

namespace Tests\Unit;

use App\Support\ConsumerRmKanwilAudit;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

class ConsumerRmKanwilAuditTest extends TestCase
{
    private function reference(string $name = 'RM A'): array
    {
        return ['name' => $name, 'branch' => 'MADIUN', 'target' => 100000000,
            'count' => 2, 'amount' => 100000000, 'new_count' => 1, 'new_amount' => 80000000,
            'supp_count' => 1, 'supp_amount' => 20000000, 'quadrant' => 2];
    }

    public function test_small_error_must_fail_if_it_changes_quadrant(): void
    {
        $expected = $this->reference();
        $actual = array_replace($expected, ['amount' => 99990000, 'new_amount' => 79990000]);
        $audit = (new ConsumerRmKanwilAudit)->compare(['A' => $expected], ['A' => $actual], ['A' => $actual], 1);

        $this->assertEqualsWithDelta(0.01, $audit['weighted_absolute_error_percent'], 0.00001);
        $this->assertSame(0, $audit['quadrant_matches']);
        $this->assertFalse($audit['passed']);
        $this->assertTrue($audit['rows'][0]['snapshot_match']);
    }

    public function test_opposite_rm_errors_and_component_errors_do_not_cancel(): void
    {
        $a = $this->reference();
        $b = $this->reference('RM B');
        $actualA = array_replace($a, ['amount' => 110000000, 'new_amount' => 90000000]);
        $actualB = array_replace($b, ['amount' => 90000000, 'new_amount' => 70000000]);
        $audit = (new ConsumerRmKanwilAudit)->compare(['A' => $a, 'B' => $b],
            ['A' => $actualA, 'B' => $actualB], ['A' => $actualA, 'B' => $actualB], 1);
        $this->assertSame(10.0, $audit['weighted_absolute_error_percent']);
        $this->assertFalse($audit['passed']);

        $swapped = array_replace($a, ['new_amount' => 70000000, 'supp_amount' => 30000000]);
        $audit = (new ConsumerRmKanwilAudit)->compare(['A' => $a], ['A' => $swapped], ['A' => $a], 1);
        $this->assertSame(0.0, $audit['weighted_absolute_error_percent']);
        $this->assertSame(1, $audit['quadrant_matches']);
        $this->assertFalse($audit['passed']);
    }

    public function test_zero_reference_missing_snapshot_and_extra_rm_cannot_pass(): void
    {
        $reference = array_replace($this->reference(), ['amount' => 0, 'new_amount' => 0, 'supp_amount' => 0, 'quadrant' => 4]);
        $actual = array_replace($reference, ['amount' => 1, 'new_amount' => 1]);
        $audit = (new ConsumerRmKanwilAudit)->compare(['A' => $reference], ['A' => $actual], ['A' => $actual], 1);
        $this->assertNull($audit['rows'][0]['error_percent_amount']);
        $this->assertFalse($audit['passed']);

        $reference = $this->reference();
        $audit = (new ConsumerRmKanwilAudit)->compare(['A' => $reference], ['A' => $reference], [], 1);
        $this->assertFalse($audit['passed']);
        $audit = (new ConsumerRmKanwilAudit)->compare(['A' => $reference],
            ['A' => $reference, 'EXTRA' => $reference], ['A' => $reference], 1);
        $this->assertSame(['EXTRA'], $audit['unmatched_realization_identities']);
        $this->assertFalse($audit['passed']);
    }

    public function test_exact_values_and_snapshots_pass(): void
    {
        $expected = $this->reference();
        $audit = (new ConsumerRmKanwilAudit)->compare(['A' => $expected], ['A' => $expected], ['A' => $expected], 0.5);
        $this->assertTrue($audit['passed']);
        $this->assertSame(1, $audit['quadrant_matches']);
    }

    public function test_workbook_uses_exact_month_cached_values_in_millions_and_area_scope(): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('RM - Sort');
        $sheet->setCellValue('G3', 'Nama RM');
        foreach ([6 => 'Madiun', 7 => 'Malang'] as $row => $branch) {
            $sheet->setCellValue('E'.$row, $branch);
            $sheet->setCellValue('G'.$row, 'RM TEST '.$row);
            // June starts at BU; July starts at CG. Cached values must not be rounded.
            foreach ([73 => 100.123456, 85 => 101.654321] as $start => $total) {
                foreach ([1, 80, 1, $total - 80, 2, $total, 19, 100] as $offset => $value) {
                    $sheet->setCellValue([$start + $offset, $row], $value);
                }
                $sheet->getCell([$start + 5, $row])->setValue('='.$total);
                $sheet->setCellValue([$start + 11, $row], 'Kuadran 2');
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'consumer-audit-');
        try {
            (new Xlsx($book))->save($path);
            // Deliberately disagree with cached values: the reader must not evaluate formulas.
            $zip = new \ZipArchive;
            $zip->open($path);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->addFromString('xl/worksheets/sheet1.xml', preg_replace('/<f>[^<]*<\/f>/', '<f>999</f>', $xml));
            $zip->close();
            $june = (new ConsumerRmKanwilAudit)->referenceRows($path, '2026-06-30');
            $july = (new ConsumerRmKanwilAudit)->referenceRows($path, '2026-07-31');
            $this->assertCount(1, $june);
            $this->assertEqualsWithDelta(100123456, array_values($june)[0]['amount'], 0.01);
            $this->assertEqualsWithDelta(101654321, array_values($july)[0]['amount'], 0.01);
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }
}
