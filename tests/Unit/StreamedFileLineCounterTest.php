<?php

namespace Tests\Unit;

use App\Support\StreamedFileLineCounter;
use Tests\TestCase;

class StreamedFileLineCounterTest extends TestCase
{
    public function test_it_counts_csv_data_rows_without_requiring_a_trailing_newline(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'line-counter-');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, "header_a,header_b\r\n1,2\r\n3,4");

            $this->assertSame(2, StreamedFileLineCounter::countDataRows($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_it_handles_empty_and_header_only_files(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'line-counter-');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, '');
            $this->assertSame(0, StreamedFileLineCounter::countDataRows($path));

            file_put_contents($path, "header\n");
            $this->assertSame(0, StreamedFileLineCounter::countDataRows($path));
        } finally {
            @unlink($path);
        }
    }
}
