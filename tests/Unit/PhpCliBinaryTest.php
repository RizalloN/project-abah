<?php

namespace Tests\Unit;

use App\Support\PhpCliBinary;
use Tests\TestCase;

class PhpCliBinaryTest extends TestCase
{
    public function test_apache_binary_is_skipped_for_the_php_cli_next_to_the_loaded_ini(): void
    {
        $loadedIni = php_ini_loaded_file();
        if ($loadedIni === false || ! is_file(dirname($loadedIni).DIRECTORY_SEPARATOR.'php.exe')) {
            $this->markTestSkipped('Requires a Windows PHP install with php.exe beside php.ini.');
        }

        // Under mod_php PHP_BINARY is httpd.exe and PHP_BINDIR does not exist on disk.
        $resolved = PhpCliBinary::firstUsable([
            'D:\\xampp\\apache\\bin\\httpd.exe',
            'C:\\php\\php.exe-does-not-exist\\php.exe',
            dirname($loadedIni).DIRECTORY_SEPARATOR.'php.exe',
        ]);

        $this->assertSame(realpath(dirname($loadedIni).DIRECTORY_SEPARATOR.'php.exe'), $resolved);
    }

    public function test_resolve_returns_an_existing_absolute_cli_binary_and_never_bare_php(): void
    {
        $resolved = PhpCliBinary::resolve();

        $this->assertNotNull($resolved);
        $this->assertNotSame('php', $resolved);
        $this->assertFileExists($resolved);
        $this->assertContains(strtolower(basename(str_replace('\\', '/', $resolved))), ['php.exe', 'php']);
    }

    public function test_bare_php_is_only_returned_when_a_caller_opts_in(): void
    {
        $this->assertNull(PhpCliBinary::firstUsable(['C:\\missing\\httpd.exe', 'C:\\missing\\php.exe']));
        $this->assertSame('php', PhpCliBinary::firstUsable(['C:\\missing\\php.exe', 'php']));
    }
}
