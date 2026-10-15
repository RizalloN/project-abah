<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ImportPreviewPopupReadinessTest extends TestCase
{
    public function test_loaded_popup_starts_import_once_without_loading_another_script(): void
    {
        $result = $this->runScript(<<<'JS'
window.Swal = {fire() {}};
await submit();
JS);
        $this->assertSame(1, $result['jobs']);
        $this->assertSame([], $result['scripts']);
        $this->assertSame([], $result['alerts']);
    }

    public function test_missing_popup_is_loaded_from_same_origin_before_a_single_import_can_start(): void
    {
        $result = $this->runScript(<<<'JS'
const first = submit();
await submit();
if (jobs !== 0) throw new Error('Import started without a popup');
window.Swal = {fire() {}};
scripts[0].onload();
await first;
JS);
        $this->assertSame(1, $result['jobs']);
        $this->assertSame(['/adminlte/plugins/sweetalert2/sweetalert2.all.min.js'], $result['scripts']);
        $this->assertSame([], $result['alerts']);
        $this->assertSame(0, $result['timers']);
    }

    public function test_failed_asset_blocks_job_creation_and_allows_a_later_retry(): void
    {
        $result = $this->runScript(<<<'JS'
const first = submit();
scripts[0].onerror();
await first;
if (jobs !== 0 || importSubmitInProgress) throw new Error('Failed popup must not submit a job or lock the button');
const retry = submit();
window.Swal = {fire() {}};
scripts[1].onload();
await retry;
JS);
        $this->assertSame(1, $result['jobs']);
        $this->assertCount(2, $result['scripts']);
        $this->assertCount(1, $result['alerts']);
        $this->assertStringContainsString('Import belum dikirim', $result['alerts'][0]);
        $this->assertSame(1, $result['resets']);
        $this->assertSame(1, $result['removed']);
    }

    public function test_asset_timeout_blocks_import_and_restores_button(): void
    {
        $result = $this->runScript(<<<'JS'
const pending = submit();
[...timers.values()][0]();
await pending;
JS);
        $this->assertSame(0, $result['jobs']);
        $this->assertFalse($result['busy']);
        $this->assertCount(1, $result['alerts']);
        $this->assertSame(0, $result['timers']);
    }

    private function runScript(string $action): array
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node.js is required for the import popup runtime test.');
        }
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/import/preview.blade.php');
        $start = strpos($source, 'let importPopupReadyPromise = null;');
        $end = strpos($source, 'function redirectToImportIndex()', $start);
        $ready = substr($source, $start, $end - $start);
        $ready = preg_replace('/@json\(asset\([^\n]+\)\)/', '"https://configured-host.example/adminlte/plugins/sweetalert2/sweetalert2.all.min.js"', $ready);
        $start = strpos($source, "document.getElementById('importForm').addEventListener('submit'");
        $end = strpos($source, 'const form = this;', $start);
        $submit = substr($source, $start, $end - $start).'jobs++; });';
        $setup = <<<'JS'
let jobs = 0, resets = 0, removed = 0, importSubmitInProgress = false;
const scripts = [], alerts = [], timers = new Map();
let handler, nextTimer = 0;
global.window = {location: {href: 'https://actual-host.example/import/report-ph/preview'}, alert: value => alerts.push(value)};
global.document = {
    createElement() {return {remove() {removed++;}};},
    head: {appendChild(script) {scripts.push(script);}},
    getElementById() {return {addEventListener(event, callback) {handler = callback;}};}
};
global.setTimeout = callback => {const id = ++nextTimer; timers.set(id, callback); return id;};
global.clearTimeout = id => timers.delete(id);
function resetImportButton() {resets++; importSubmitInProgress = false;}
const submit = () => handler({preventDefault() {}});
JS;
        $script = '(async () => {'.$setup."\n".$ready."\n".$submit."\n".$action."\n".
            'console.log(JSON.stringify({jobs, resets, removed, alerts, scripts:scripts.map(script=>script.src), timers:timers.size, busy:importSubmitInProgress}));})().catch(error=>{console.error(error);process.exitCode=1;});';
        $process = new Process([$node]);
        $process->setInput($script);
        $process->setTimeout(10);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
