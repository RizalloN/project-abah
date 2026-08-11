<?php

namespace Tests\Unit;

use App\Services\DatabaseBackup\CompressedDatabaseDumpRunner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CompressedDatabaseDumpRunnerTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $testingDirectory = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'storage'
            .DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing';

        if (! is_dir($testingDirectory) && ! mkdir($testingDirectory, 0777, true) && ! is_dir($testingDirectory)) {
            throw new RuntimeException('Unable to create the framework testing directory.');
        }

        $this->temporaryDirectory = $testingDirectory.DIRECTORY_SEPARATOR
            .'compressed-database-dump-runner-'.getmypid().'-'.bin2hex(random_bytes(8));

        if (! mkdir($this->temporaryDirectory, 0777) && ! is_dir($this->temporaryDirectory)) {
            throw new RuntimeException('Unable to create an isolated dump runner test directory.');
        }
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();

        parent::tearDown();
    }

    public function test_it_streams_stdout_to_a_valid_gzip_and_keeps_stderr_separate(): void
    {
        $gzipPath = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'database.sql.gz.part';
        $stderrPath = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'database.stderr.log';
        $script = <<<'PHP'
for ($index = 0; $index < 8192; $index++) {
    $line = sprintf(
        "row-%05d|%s\n",
        $index,
        str_repeat(chr(65 + ($index % 26)), 96)
    );

    if (fwrite(STDOUT, $line) === false) {
        exit(70);
    }
}

fwrite(STDERR, "diagnostic-only\n");
PHP;

        $result = (new CompressedDatabaseDumpRunner)->run(
            [PHP_BINARY, '-r', $script],
            $gzipPath,
            $stderrPath,
            4
        );

        $expectedPayload = $this->expectedStreamingPayload();

        $this->assertSame(0, $result['exit_code']);
        $this->assertSame(strlen($expectedPayload), $result['uncompressed_bytes']);
        $this->assertSame(hash('sha256', $expectedPayload), $result['uncompressed_sha256']);
        $this->assertSame(substr($expectedPayload, 0, 65536), $result['sql_prefix']);
        $this->assertFileExists($gzipPath);
        $this->assertGreaterThan(0, filesize($gzipPath));
        $this->assertSame("diagnostic-only\n", file_get_contents($stderrPath));

        $compressed = file_get_contents($gzipPath);
        $this->assertNotFalse($compressed);

        $decompressed = gzdecode($compressed);
        $this->assertNotFalse($decompressed, 'Runner must create a readable gzip stream.');
        $this->assertSame($expectedPayload, $decompressed);
        $this->assertStringNotContainsString('diagnostic-only', $decompressed);
    }

    public function test_it_rejects_a_child_process_exit_failure_and_preserves_stderr(): void
    {
        $gzipPath = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'failed.sql.gz.part';
        $stderrPath = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'failed.stderr.log';
        $script = <<<'PHP'
fwrite(STDOUT, "partial dump output\n");
fwrite(STDERR, "intentional child failure\n");
exit(23);
PHP;

        try {
            (new CompressedDatabaseDumpRunner)->run(
                [PHP_BINARY, '-r', $script],
                $gzipPath,
                $stderrPath
            );

            $this->fail('A non-zero child process exit code must fail the dump run.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('exit code 23', $exception->getMessage());
        }

        $this->assertFileDoesNotExist($gzipPath);
        $this->assertSame("intentional child failure\n", file_get_contents($stderrPath));
    }

    private function expectedStreamingPayload(): string
    {
        $payload = '';

        for ($index = 0; $index < 8192; $index++) {
            $payload .= sprintf(
                "row-%05d|%s\n",
                $index,
                str_repeat(chr(65 + ($index % 26)), 96)
            );
        }

        return $payload;
    }

    private function removeTemporaryDirectory(): void
    {
        if (! isset($this->temporaryDirectory) || ! is_dir($this->temporaryDirectory)) {
            return;
        }

        $testingDirectory = realpath(
            dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'storage'
            .DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing'
        );
        $temporaryParent = realpath(dirname($this->temporaryDirectory));

        if (
            $testingDirectory === false
            || $temporaryParent === false
            || $temporaryParent !== $testingDirectory
            || ! str_starts_with(
                basename($this->temporaryDirectory),
                'compressed-database-dump-runner-'
            )
        ) {
            throw new RuntimeException('Refusing to remove a directory outside the isolated test root.');
        }

        $entries = scandir($this->temporaryDirectory);

        if ($entries === false) {
            throw new RuntimeException('Unable to inspect the isolated dump runner test directory.');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $this->temporaryDirectory.DIRECTORY_SEPARATOR.$entry;

            if (! is_file($path) && ! is_link($path)) {
                throw new RuntimeException('Unexpected nested directory in isolated dump runner test directory.');
            }

            if (! unlink($path)) {
                throw new RuntimeException('Unable to remove an isolated dump runner test file.');
            }
        }

        if (! rmdir($this->temporaryDirectory)) {
            throw new RuntimeException('Unable to remove the isolated dump runner test directory.');
        }
    }
}
