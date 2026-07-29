<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Symfony\Component\Process\Process;

it('compiles every Blade view into valid PHP', function () {
    $filesystem = app(Filesystem::class);
    $compiler = app(BladeCompiler::class);
    $temporaryDirectory = storage_path('framework/testing/blade-syntax-' . getmypid());
    $bladeFiles = collect($filesystem->allFiles(resource_path('views')))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ->values();

    $filesystem->ensureDirectoryExists($temporaryDirectory);

    try {
        foreach ($bladeFiles as $index => $bladeFile) {
            $compiledPath = $temporaryDirectory . DIRECTORY_SEPARATOR . $index . '.php';
            $compiledPhp = $compiler->compileString($filesystem->get($bladeFile->getPathname()));
            $filesystem->put($compiledPath, $compiledPhp);

            $lint = new Process([PHP_BINARY, '-l', $compiledPath]);
            $lint->run();

            expect($lint->isSuccessful())
                ->toBeTrue(
                    'Blade syntax invalid in ' .
                    $bladeFile->getRelativePathname() .
                    ': ' .
                    trim($lint->getErrorOutput() ?: $lint->getOutput())
                );
        }

        expect($bladeFiles)->not->toBeEmpty();
    } finally {
        $filesystem->deleteDirectory($temporaryDirectory);
    }
});
