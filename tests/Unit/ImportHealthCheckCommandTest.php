<?php

namespace Tests\Unit;

use App\Console\Commands\ImportHealthCheckCommand;
use Tests\TestCase;

class ImportHealthCheckCommandTest extends TestCase
{
    public function test_health_check_does_not_classify_import_load_data_as_killable(): void
    {
        $command = new ImportHealthCheckCommand();
        $method = new \ReflectionMethod($command, 'shouldIgnoreSlowQuery');
        $method->setAccessible(true);

        $loadData = "LOAD DATA LOCAL INFILE 'D:/tmp/ponorogo.txt' INTO TABLE `simpanan_multipn`";
        $deleteScope = "DELETE FROM `simpanan_multipn` WHERE `posisi` IN ('2026-06-05')";

        $this->assertTrue($method->invoke($command, strtolower($loadData)));
        $this->assertTrue($method->invoke($command, strtolower($deleteScope)));
        $this->assertFalse($method->invoke($command, 'select sleep(90)'));
    }
}
