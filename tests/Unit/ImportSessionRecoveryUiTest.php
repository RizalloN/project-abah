<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ImportSessionRecoveryUiTest extends TestCase
{
    #[DataProvider('accessFailures')]
    public function test_status_polling_stops_and_does_not_restart_import_when_access_expires(string $view, int $status): void
    {
        $result = $this->runScript($view, [$status], <<<'JS'
pollMode = true;
connectSSE();
await pollImportStatus(41);
await pollImportStatus(41);
await triggerForceStart(41);
JS);
        $this->assertSame(1, count($result['requests']));
        $this->assertSame('GET', $result['requests'][0]['method']);
        $this->assertTrue($result['done']);
        $this->assertSame(1, count($result['notices']));
        $this->assertSame(1, $result['resetCount']);
        $this->assertGreaterThanOrEqual(1, $result['closed']);
        $this->assertSame([], $result['successes']);
        $this->assertSame(0, $result['timeouts']);
        if ($view === 'csv') {
            $this->assertContains(1, $result['clearedIntervals']);
            $this->assertSame(1, $result['tickerStops']);
        }
    }

    #[DataProvider('accessFailures')]
    public function test_sse_disconnect_does_not_reconnect_or_show_late_success_after_access_failure(string $view, int $status): void
    {
        $result = $this->runScript($view, [$status], <<<'JS'
connectSSE();
const initialStream = evtSource;
await initialStream.onerror();
await initialStream.handlers.complete({data: JSON.stringify({status: 'completed', total_success: 2})});
JS);
        $this->assertTrue($result['done']);
        $this->assertSame(1, count($result['requests']));
        $this->assertSame('GET', $result['requests'][0]['method']);
        $this->assertSame(0, $result['timeouts']);
        $this->assertSame([], $result['successes']);
        $this->assertSame(1, count($result['notices']));
    }

    #[DataProvider('accessFailures')]
    public function test_force_start_access_rejection_is_not_replayed(string $view, int $status): void
    {
        $result = $this->runScript($view, [$status], <<<'JS'
await triggerForceStart(41);
await pollImportStatus(41);
await triggerForceStart(41);
JS);
        $this->assertTrue($result['done']);
        $this->assertSame(1, count($result['requests']));
        $this->assertSame('POST', $result['requests'][0]['method']);
        $this->assertSame([], $result['successes']);
        $this->assertSame(1, count($result['notices']));
    }

    #[DataProvider('temporaryFailures')]
    public function test_temporary_network_errors_still_retry_and_show_verified_terminal_counts(string $view, string|int $failure): void
    {
        $result = $this->runScript($view, [$failure, 'processing', 'completed'], <<<'JS'
pollMode = true;
await pollImportStatus(41);
JS);
        $this->assertSame(3, count($result['requests']));
        $this->assertSame([], $result['notices']);
        $this->assertSame(2, $result['timeouts']);
        $this->assertCount(1, $result['successes']);
        $this->assertSame('completed', $result['successes'][0]['status']);
        $this->assertSame(2, $result['successes'][0]['total_success']);
        $this->assertSame(0, $result['successes'][0]['total_failed']);
    }

    #[DataProvider('views')]
    public function test_sse_network_error_preserves_reconnection(string $view): void
    {
        $result = $this->runScript($view, [500], <<<'JS'
connectSSE();
await evtSource.onerror();
timeoutCallbacks[0]();
JS);
        $this->assertFalse($result['done']);
        $this->assertSame(2, $result['streams']);
        $this->assertSame(1, $result['timeouts']);
        $this->assertSame([], $result['notices']);
        $this->assertSame([], $result['successes']);
    }

    public function test_csv_independent_polling_stops_on_session_failure(): void
    {
        $result = $this->runScript('csv', [401], <<<'JS'
await intervalCallbacks[0]();
await intervalCallbacks[0]();
JS);
        $this->assertTrue($result['done']);
        $this->assertSame(1, count($result['requests']));
        $this->assertContains(1, $result['clearedIntervals']);
        $this->assertSame(1, count($result['notices']));
    }

    #[DataProvider('views')]
    public function test_a_pending_force_start_response_cannot_restart_monitoring_after_session_expiry(string $view): void
    {
        $result = $this->runScript($view, [], <<<'JS'
let resolvePending;
global.fetch = (url, options = {}) => {
    requests.push({url, method: options.method || 'GET'});
    return new Promise(resolve => {resolvePending = resolve;});
};
const pending = triggerForceStart(41);
handleImportAccessFailure({status: 401});
resolvePending({status: 500, ok: false, json: async () => ({status: 'error'})});
await pending;
await inspectForTest(41);
await pollImportStatus(41);
await triggerForceStart(41);
JS);
        $this->assertTrue($result['done']);
        $this->assertSame(1, count($result['requests']));
        $this->assertSame('POST', $result['requests'][0]['method']);
        $this->assertSame(0, $result['timeouts']);
        $this->assertSame([], $result['successes']);
        $this->assertSame(1, count($result['notices']));
    }

    #[DataProvider('accessFailures')]
    public function test_recovery_action_uses_login_reload_or_an_accessible_dashboard(string $view, int $status): void
    {
        $result = $this->runScript($view, [$status], <<<'JS'
confirmed = true;
await inspectForTest(41);
await Promise.resolve();
JS);
        $this->assertSame($status === 419 ? 1 : 0, $result['reloads']);
        $this->assertSame(match ($status) {
            401 => '/login', 403 => '/dashboard', default => '/preview'
        }, $result['location']);
        $this->assertStringNotContainsString('Duplikat', $result['notices'][0]['title']);
        $this->assertSame('GET', $result['requests'][0]['method']);
    }

    public static function views(): iterable
    {
        yield ['excel'];
        yield ['csv'];
    }

    public static function accessFailures(): iterable
    {
        foreach (['excel', 'csv'] as $view) {
            foreach ([401, 403, 419] as $status) {
                yield $view.' '.$status => [$view, $status];
            }
        }
    }

    public static function temporaryFailures(): iterable
    {
        foreach (['excel', 'csv'] as $view) {
            foreach ([500, 'offline'] as $status) {
                yield $view.' '.$status => [$view, $status];
            }
        }
    }

    private function runScript(string $view, array $responses, string $action): array
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node.js is required to execute import preview JavaScript.');
        }
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/import/'.($view === 'excel' ? 'preview_excel' : 'preview').'.blade.php');
        $start = strpos($source, $view === 'excel'
            ? 'function handleImportAccessFailure(response)'
            : 'const handleImportAccessFailure = function (response)');
        $end = strpos($source, $view === 'excel' ? "\n        connectSSE();" : "\n                connectSSE();", $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $logic = substr($source, $start, $end - $start);
        $logic = str_replace(['{{ route("login") }}', '{{ route("dashboard") }}'], ['/login', '/dashboard'], $logic);
        $logic = preg_replace('/\{\{.*?\}\}/s', '/fixture-route', $logic);
        $setup = <<<'JS'
let streamDone = false, terminalVerificationStarted = false, forceStartTriggered = false;
let reconnectAttempts = 0, evtSource = null, pollStarted = false, pollMode = false;
let lastProg = {percent: 12, message: '', rows_done: 0, total: 2, speed: 0};
let importProgressSnapshot = {percent: 12, message: '', rowsDone: 0, totalRows: 2, speed: 0, speedLabel: ''};
const initResult = {job_id: 41}, jobId = 41, streamUrl = '/stream?job_id=41', allowForceStart = false;
const requests = [], notices = [], successes = [], clearedIntervals = [], intervalCallbacks = [], timeoutCallbacks = [];
let resetCount = 0, closed = 0, tickerStops = 0, streams = 0, timeouts = 0, reloads = 0, confirmed = false;
global.window = {location: {href: '/preview', reload() {reloads++;}}};
global.fetch = async (url, options = {}) => {
    requests.push({url, method: options.method || 'GET'});
    if (requests.length > 8) throw new Error('Unbounded requests detected');
    const item = responses.shift();
    if (item === 'offline') throw new Error('Temporary network error');
    const status = typeof item === 'number' ? item : 200;
    return {status, ok: status === 200, json: async () => ({status: typeof item === 'string' ? item : 'error', percent: 100, total_rows: 2, processed_rows: 2, total_success: 2, total_failed: 0})};
};
global.setTimeout = (callback) => {
    timeouts++;
    if (pollMode) callback(); else timeoutCallbacks.push(callback);
    return timeouts;
};
global.setInterval = (callback) => {intervalCallbacks.push(callback); return intervalCallbacks.length;};
global.clearInterval = (id) => clearedIntervals.push(id);
global.EventSource = class {
    constructor(url) {this.url = url; this.handlers = {}; streams++;}
    addEventListener(name, callback) {this.handlers[name] = callback;}
    close() {closed++;}
};
function themedSwal(options) {notices.push(options); return Promise.resolve({isConfirmed: confirmed});}
function resetSubmitBtn() {resetCount++;}
function resetImportButton() {resetCount++;}
function stopImportProgressTicker() {tickerStops++;}
function showImportSuccess(payload) {streamDone = true; successes.push(payload);}
function showImportComplete(payload) {streamDone = true; successes.push(payload);}
function showImportFailure(message) {throw new Error('Unexpected failure: ' + message);}
function showImportError(message) {throw new Error('Unexpected failure: ' + message);}
function setProgress() {}
function setImportProgress() {}
function activateStep() {}
function isNonFatalProcessingMessage() {return false;}
function normalizeProgressStatus() {return {};}
function statusUrlForJob(id) {return '/status/' + id;}
function forceStartUrlForJob(id) {return '/force-start/' + id;}
JS;
        $script = '(async () => { const responses = '.json_encode($responses, JSON_THROW_ON_ERROR).';'."\n"
            .$setup."\n".$logic."\n".'const inspectForTest = '.($view === 'excel' ? 'inspectImportJob' : 'inspectJobStatus').';'."\n".$action."\n"
            .'console.log(JSON.stringify({done: streamDone, requests, notices, successes, resetCount, closed, tickerStops, streams, timeouts, clearedIntervals, reloads, location: window.location.href}));'
            .'})().catch(error => {console.error(error); process.exitCode = 1;});';
        $process = new Process([$node]);
        $process->setInput($script);
        $process->setTimeout(10);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
