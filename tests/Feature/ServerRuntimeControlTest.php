<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Import\QueueSupervisorProcessRunner;
use App\Services\ServerRuntimeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ServerRuntimeControlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('pn')->unique();
            $table->string('role');
            $table->string('password');
            $table->string('branch_scope')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        $runner = Mockery::mock(QueueSupervisorProcessRunner::class);
        $runner->shouldNotReceive('run');
        $this->app->instance(QueueSupervisorProcessRunner::class, $runner);
    }

    #[DataProvider('unauthorizedIdentities')]
    public function test_runtime_routes_reject_unauthorized_identities_before_any_service_or_process_runs(?string $role, ?string $pn, int $status): void
    {
        $runtime = Mockery::mock(ServerRuntimeService::class);
        $runtime->shouldNotReceive('snapshot');
        $runtime->shouldNotReceive('control');
        $this->app->instance(ServerRuntimeService::class, $runtime);
        if ($role !== null) {
            $this->actingAs($this->createUser($role, $pn));
        }

        $this->getJson(route('job-management.runtime.status'))->assertStatus($status);
        $this->postJson(route('job-management.runtime.control'), ['service' => 'all', 'action' => 'start'])->assertStatus($status);
    }

    public static function unauthorizedIdentities(): array
    {
        return [
            'guest' => [null, null, 401],
            'admin' => ['admin', '80000001', 403],
            'user' => ['user', '80000002', 403],
            'forged superadmin' => ['superadmin', '80000003', 403],
        ];
    }

    public function test_reserved_superadmin_can_read_runtime_status_without_changing_services(): void
    {
        $runtime = Mockery::mock(ServerRuntimeService::class);
        $runtime->shouldReceive('snapshot')->once()->withNoArgs()->andReturn(['healthy' => false, 'scheduler' => ['state' => 'stopped']]);
        $runtime->shouldNotReceive('control');
        $this->app->instance(ServerRuntimeService::class, $runtime);

        $response = $this->actingAs($this->createUser())->getJson(route('job-management.runtime.status'));

        $response->assertOk()->assertJsonPath('runtime.healthy', false)->assertJsonPath('runtime.scheduler.state', 'stopped');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    #[DataProvider('allowedControls')]
    public function test_valid_controls_pass_only_validated_service_action_and_actor_to_the_runtime(string $service, string $action): void
    {
        $runtime = Mockery::mock(ServerRuntimeService::class);
        $runtime->shouldReceive('control')->once()->with($service, $action, User::SUPERADMIN_PN)->andReturn([
            'status' => 'success', 'message' => 'Request accepted; waiting for heartbeat.', 'runtime' => ['healthy' => false], 'results' => [],
        ]);
        $runtime->shouldNotReceive('snapshot');
        $this->app->instance(ServerRuntimeService::class, $runtime);

        $response = $this->actingAs($this->createUser())->postJson(route('job-management.runtime.control'), compact('service', 'action'));

        $response->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('runtime.healthy', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public static function allowedControls(): array
    {
        return [
            ['all', 'start'], ['apache', 'start'], ['mysql', 'start'], ['tunnel', 'start'],
            ['scheduler', 'start'], ['scheduler', 'stop'], ['scheduler', 'restart'], ['scheduler', 'run'],
            ['backup', 'run'],
        ];
    }

    #[DataProvider('invalidControls')]
    public function test_invalid_controls_and_shell_payloads_are_rejected_without_calling_runtime_control(array $payload): void
    {
        $runtime = Mockery::mock(ServerRuntimeService::class);
        $runtime->shouldNotReceive('snapshot');
        $runtime->shouldNotReceive('control');
        $this->app->instance(ServerRuntimeService::class, $runtime);

        $this->actingAs($this->createUser())->postJson(route('job-management.runtime.control'), $payload)->assertUnprocessable();
    }

    public static function invalidControls(): iterable
    {
        yield 'missing service' => [['action' => 'start']];
        yield 'missing action' => [['service' => 'scheduler']];
        yield 'unknown service' => [['service' => 'redis', 'action' => 'start']];
        yield 'unknown action' => [['service' => 'scheduler', 'action' => 'delete']];
        yield 'injected service' => [['service' => 'mysql; Stop-Process -Name php', 'action' => 'start']];
        yield 'injected action' => [['service' => 'scheduler', 'action' => 'start & whoami']];
        foreach (['all', 'apache', 'mysql', 'tunnel'] as $service) {
            foreach (['stop', 'restart', 'run'] as $action) {
                yield $service.' '.$action => [compact('service', 'action')];
            }
        }
        foreach (['start', 'stop', 'restart'] as $action) {
            yield 'backup '.$action => [['service' => 'backup', 'action' => $action]];
        }
    }

    public function test_runtime_operation_failure_returns_503_with_a_reviewable_status_payload(): void
    {
        $runtime = Mockery::mock(ServerRuntimeService::class);
        $runtime->shouldReceive('control')->once()->with('scheduler', 'start', User::SUPERADMIN_PN)->andReturn([
            'status' => 'error', 'message' => 'Task permission denied.', 'runtime' => ['healthy' => false],
            'results' => [['service' => 'scheduler', 'success' => false, 'message' => 'Task permission denied.']],
        ]);
        $this->app->instance(ServerRuntimeService::class, $runtime);

        $this->actingAs($this->createUser())->postJson(route('job-management.runtime.control'), ['service' => 'scheduler', 'action' => 'start'])
            ->assertStatus(503)->assertJsonPath('status', 'error')->assertJsonPath('results.0.success', false);
    }

    public function test_rendered_runtime_script_retains_the_last_valid_status_and_displays_source_text_safely(): void
    {
        $result = $this->runRuntimeUi(<<<'JS'
responses.push({runtime: runtimeFixture('stopped', '<img src=x onerror=alert(1)>')}, 'offline');
await api.refreshRuntime();
const rowsBefore = elements['runtime-schedule-body'].children.length;
await api.refreshRuntime();
result.retainedState = api.runtimeState.lastValid.scheduler.state;
result.retainedMessage = elements['runtime-scheduler-message'].textContent;
result.rowsBefore = rowsBefore;
result.rowsAfter = elements['runtime-schedule-body'].children.length;
result.warning = elements['runtime-health-badge'].textContent;
result.logLines = elements['runtime-scheduler-log'].textContent.split('\n').length;
result.scheduleText = elements['runtime-schedule-body'].children[0].children[0].children[0].textContent;
JS);

        $this->assertSame('stopped', $result['retainedState']);
        $this->assertSame('<img src=x onerror=alert(1)>', $result['retainedMessage']);
        $this->assertSame(1, $result['rowsBefore']);
        $this->assertSame($result['rowsBefore'], $result['rowsAfter']);
        $this->assertStringContainsString('belum terbarui', $result['warning']);
        $this->assertLessThanOrEqual(120, $result['logLines']);
        $this->assertSame('<script>source label</script>', $result['scheduleText']);
        $this->assertSame(['GET', 'GET'], array_column($result['requests'], 'method'));
    }

    public function test_rendered_runtime_script_disables_pending_controls_and_ignores_a_stale_get_during_a_post(): void
    {
        $result = $this->runRuntimeUi(<<<'JS'
responses.push({runtime: runtimeFixture('stopped', 'Initial')});
await api.refreshRuntime();
let resolveRead, resolvePost;
global.fetch = async (url, options = {}) => {
    requests.push({method: options.method || 'GET', body: options.body || null, csrf: options.headers?.['X-CSRF-TOKEN'] || null});
    if (options.method === 'POST') return new Promise(resolve => {resolvePost = resolve;});
    if (!resolveRead) return new Promise(resolve => {resolveRead = resolve;});
    return responseFor({runtime: runtimeFixture('starting', 'Verified after action')});
};
const reading = api.refreshRuntime();
const action = api.controlRuntime(buttons.find(button => button.dataset.runtimeService === 'scheduler' && button.dataset.runtimeAction === 'start'));
result.pending = api.runtimeState.actionPending;
result.allDisabled = buttons.every(button => button.disabled);
resolveRead(responseFor({runtime: runtimeFixture('active', 'Stale reading')}));
await reading;
result.whilePending = elements['runtime-scheduler-message'].textContent;
resolvePost(responseFor({runtime: runtimeFixture('starting', 'Task accepted'), status: 'success'}));
await action;
result.after = elements['runtime-scheduler-message'].textContent;
result.pendingAfter = api.runtimeState.actionPending;
JS);

        $this->assertTrue($result['pending']);
        $this->assertTrue($result['allDisabled']);
        $this->assertSame('Initial', $result['whilePending']);
        $this->assertSame('Verified after action', $result['after']);
        $this->assertFalse($result['pendingAfter']);
        $posts = array_values(array_filter($result['requests'], fn (array $request): bool => $request['method'] === 'POST'));
        $this->assertCount(1, $posts);
        $this->assertSame(['service' => 'scheduler', 'action' => 'start'], json_decode($posts[0]['body'], true));
        $this->assertSame('fixture-csrf-token', $posts[0]['csrf']);
    }

    public function test_rendered_runtime_script_requires_confirmation_and_pauses_polling_when_hidden_or_auto_is_off(): void
    {
        $result = $this->runRuntimeUi(<<<'JS'
elements['job-auto-refresh'].checked = true;
responses.push({runtime: runtimeFixture('active', 'Healthy')});
await api.refreshRuntime();
result.healthyDelay = [...timers.values()].map(timer => timer.delay);
await api.controlRuntime(buttons.find(button => button.dataset.runtimeAction === 'stop'));
result.confirmCalls = confirmCalls;
elements['job-auto-refresh'].checked = false;
elements['job-auto-refresh'].handlers.change();
result.timersAfterAutoOff = timers.size;
document.hidden = true;
document.handlers.visibilitychange();
await api.refreshRuntime();
result.timersWhileHidden = timers.size;
JS);

        $this->assertContains(15000, $result['healthyDelay']);
        $this->assertSame(1, $result['confirmCalls']);
        $this->assertSame(0, $result['timersAfterAutoOff']);
        $this->assertSame(0, $result['timersWhileHidden']);
        $this->assertSame(['GET'], array_column($result['requests'], 'method'));
    }

    public function test_rendered_runtime_script_keeps_the_failure_snapshot_when_a_control_returns_503(): void
    {
        $result = $this->runRuntimeUi(<<<'JS'
responses.push({runtime: runtimeFixture('stopped', 'Initial')});
await api.refreshRuntime();
responses.push({httpStatus: 503, status: 'error', runtime: runtimeFixture('error', 'Scheduler task was denied'), message: 'Permission denied'}, 'offline');
await api.controlRuntime(buttons.find(button => button.dataset.runtimeAction === 'start'));
result.observedState = api.runtimeState.lastValid.scheduler.state;
result.observedMessage = elements['runtime-scheduler-message'].textContent;
result.pending = api.runtimeState.actionPending;
JS);

        $this->assertSame('error', $result['observedState']);
        $this->assertSame('Scheduler task was denied', $result['observedMessage']);
        $this->assertFalse($result['pending']);
        $this->assertSame(['GET', 'POST', 'GET'], array_column($result['requests'], 'method'));
    }

    public function test_rendered_runtime_script_requires_a_registered_enabled_task_to_run_and_distinguishes_unknown_startup(): void
    {
        $result = $this->runRuntimeUi(<<<'JS'
const unregistered = runtimeFixture('active', 'External scheduler only');
unregistered.scheduler.task_registered = false;
unregistered.infrastructure[0].automatic = null;
responses.push({runtime: unregistered});
await api.refreshRuntime();
const runButton = buttons.find(button => button.dataset.runtimeAction === 'run');
result.unregisteredDisabled = runButton.disabled;
result.unknownStartup = infrastructureRows[0].querySelector('[data-runtime-service-startup]').textContent;
const registered = runtimeFixture('active', 'Registered scheduler');
registered.infrastructure[0].automatic = false;
responses.push({runtime: registered});
await api.refreshRuntime();
result.registeredDisabled = runButton.disabled;
result.manualStartup = infrastructureRows[0].querySelector('[data-runtime-service-startup]').textContent;
responses.push({runtime: runtimeFixture('stopped', 'Disabled native task')});
await api.refreshRuntime();
result.disabledTaskDisabled = runButton.disabled;
result.automaticStartup = infrastructureRows[0].querySelector('[data-runtime-service-startup]').textContent;
JS);

        $this->assertTrue($result['unregisteredDisabled']);
        $this->assertFalse($result['registeredDisabled']);
        $this->assertTrue($result['disabledTaskDisabled']);
        $this->assertSame('Startup Windows belum diketahui', $result['unknownStartup']);
        $this->assertSame('Startup Windows manual', $result['manualStartup']);
        $this->assertSame('Startup Windows otomatis', $result['automaticStartup']);
        $this->assertSame(['GET', 'GET', 'GET'], array_column($result['requests'], 'method'));
    }

    public function test_rendered_runtime_script_does_not_describe_an_unreadable_task_as_unregistered(): void
    {
        $result = $this->runRuntimeUi(<<<'JS'
const unreadable = runtimeFixture('error', 'Protected Windows task cannot be read');
Object.assign(unreadable.scheduler, {available: false, task_registered: null, task_enabled: null});
responses.push({runtime: unreadable});
await api.refreshRuntime();
result.startup = elements['runtime-scheduler-startup'].textContent;
result.detail = elements['runtime-scheduler-task-detail'].textContent;
result.schedulerControlsDisabled = buttons.filter(button => button.dataset.runtimeService === 'scheduler').every(button => button.disabled);
JS);

        $this->assertSame('Belum dapat diverifikasi', $result['startup']);
        $this->assertStringContainsString('belum dapat diverifikasi', mb_strtolower($result['detail']));
        $this->assertStringNotContainsString('belum terdaftar', mb_strtolower($result['detail']));
        $this->assertTrue($result['schedulerControlsDisabled']);
    }

    public function test_rendered_runtime_script_shows_reboot_readiness_and_confirms_a_backup_run(): void
    {
        $result = $this->runRuntimeUi(<<<'JS'
const fixture = runtimeFixture('active', 'Healthy');
fixture.infrastructure[1].automatic = false;
fixture.backup = {available: true, registered: true, enabled: true, owned: true, state: 'ready', runnable: true, failed: false, last_run_at: null, next_run_at: null, message: ''};
fixture.readiness = [
    {key: 'service-mysql', label: '<b>MySQL</b> berjalan dan otomatis menyala', ok: false, hint: 'Klik Aktifkan untuk mengubah startup menjadi otomatis.'},
    {key: 'worker', label: 'Worker', ok: true, hint: ''},
];
fixture.restart_ready = false;
responses.push({runtime: fixture});
await api.refreshRuntime();
const list = elements['runtime-readiness-list'];
result.items = list.children.length;
result.firstLabel = list.children[0].children[1].textContent;
result.firstClass = list.children[0].className;
result.badge = elements['runtime-readiness-badge'].textContent;
result.backupState = elements['runtime-backup-state'].textContent;
result.mysqlEnabled = !buttons.find(button => button.dataset.runtimeService === 'mysql').disabled;
result.apacheEnabled = !buttons.find(button => button.dataset.runtimeService === 'apache').disabled;
const backupButton = buttons.find(button => button.dataset.runtimeService === 'backup');
result.backupEnabled = !backupButton.disabled;
await api.controlRuntime(backupButton);
result.confirmCalls = confirmCalls;
JS);

        $this->assertSame(2, $result['items']);
        $this->assertSame('<b>MySQL</b> berjalan dan otomatis menyala', $result['firstLabel'], 'Labels are text, never markup.');
        $this->assertStringContainsString('--fail', $result['firstClass']);
        $this->assertSame('1 butir belum siap', $result['badge']);
        $this->assertSame('Terjadwal', $result['backupState']);
        $this->assertTrue($result['mysqlEnabled'], 'A running service with manual startup must be fixable.');
        $this->assertFalse($result['apacheEnabled']);
        $this->assertTrue($result['backupEnabled']);
        $this->assertSame(1, $result['confirmCalls']);
        $this->assertSame([], array_values(array_filter($result['requests'], fn (array $request): bool => $request['method'] === 'POST')), 'A declined confirmation must not post.');
    }

    private function runRuntimeUi(string $action): array
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node.js is required to execute runtime controls.');
        }
        $this->withoutVite();
        View::share('errors', new ViewErrorBag);
        $this->actingAs($this->createUser());
        $html = view('import.job-management')->render();
        $this->assertTrue(str_contains($html, 'id="server-runtime-card"'), 'Runtime panel must render.');
        $this->assertTrue(str_contains($html, 'data-status-url="'.route('job-management.runtime.status').'"'), 'Runtime status must use its registered route.');
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $buttonData = [];
        foreach ((new \DOMXPath($dom))->query('//*[@data-runtime-service and @data-runtime-action]') as $button) {
            $buttonData[] = ['runtimeService' => $button->getAttribute('data-runtime-service'), 'runtimeAction' => $button->getAttribute('data-runtime-action')];
        }
        $this->assertCount(9, $buttonData);
        $infrastructureKeys = [];
        foreach ((new \DOMXPath($dom))->query('//*[@data-runtime-infrastructure]') as $row) {
            $infrastructureKeys[] = $row->getAttribute('data-runtime-infrastructure');
        }
        $this->assertCount(3, $infrastructureKeys);
        preg_match_all('/<script\b[^>]*>(.*?)<\/script>/s', $html, $scripts);
        $runtimeScripts = array_values(array_filter($scripts[1], fn (string $script): bool => str_contains($script, "const runtimeCard = document.getElementById('server-runtime-card')")));
        $this->assertCount(1, $runtimeScripts);
        $logic = preg_replace('/\s+refreshRuntime\(\);\s*\}\);\s*$/', "\n    global.api = {refreshRuntime, controlRuntime, runtimeState};\n});", $runtimeScripts[0]);
        $this->assertStringContainsString('global.api = ', $logic);
        $setup = <<<'JS'
const elements = {}, requests = [], responses = [], timers = new Map(), result = {};
let timerId = 0, confirmCalls = 0;
class Element {
    constructor() {this.dataset = {}; this.children = []; this.handlers = {}; this.disabled = false; this.checked = false; this.textContent = ''; this.className = ''; this.attributes = {}; this.classList = {add() {}, remove() {}};}
    set innerHTML(value) {throw new Error('Runtime source values must never use innerHTML');}
    setAttribute(key, value) {this.attributes[key] = value;}
    getAttribute(key) {return key === 'content' ? 'fixture-csrf-token' : this.attributes[key];}
    addEventListener(name, callback) {this.handlers[name] = callback;}
    replaceChildren() {this.children = [];}
    insertRow() {const child = new Element(); this.children.push(child); return child;}
    insertCell() {return this.insertRow();}
    appendChild(child) {this.children.push(child);}
    append(...children) {this.children.push(...children);}
    prepend(child) {this.children.unshift(child);}
    remove() {}
    contains() {return true;}
    click() {this.handlers.click?.();}
    querySelector(selector) {return this.queryElements?.[selector] || null;}
    querySelectorAll(selector) {return selector === '[data-runtime-service][data-runtime-action]' ? buttons : (selector === '[data-runtime-infrastructure]' ? infrastructureRows : []);}
}
for (const id of ['server-runtime-card', 'job-auto-refresh', 'btn-runtime-refresh', 'runtime-notice', 'runtime-schedule-body', 'runtime-schedule-count', 'runtime-health-badge', 'runtime-scheduler-state', 'runtime-scheduler-message', 'runtime-scheduler-heartbeat', 'runtime-scheduler-last-run', 'runtime-scheduler-next-run', 'runtime-scheduler-startup', 'runtime-scheduler-task-detail', 'runtime-scheduler-log', 'runtime-updated', 'runtime-refresh-mode', 'runtime-backup-state', 'runtime-backup-schedule', 'runtime-backup-message', 'runtime-readiness-badge', 'runtime-readiness-list']) elements[id] = new Element();
const buttons = buttonDefinitions.map(definition => {const button = new Element(); button.dataset = definition; return button;});
const infrastructureRows = infrastructureDefinitions.map(key => {const row = new Element(); row.dataset.runtimeInfrastructure = key; row.queryElements = {}; for (const selector of ['[data-runtime-service-label]', '[data-runtime-service-state]', '[data-runtime-service-startup]', '[data-runtime-service-message]']) row.queryElements[selector] = new Element(); return row;});
elements['server-runtime-card'].dataset = {statusUrl: '/runtime', controlUrl: '/runtime/control'};
global.document = {hidden: false, handlers: {}, getElementById: id => elements[id] || null, querySelector: () => new Element(), createElement: () => new Element(), addEventListener(name, callback) {if (name === 'DOMContentLoaded') callback(); else this.handlers[name] = callback;}};
global.window = {handlers: {}, setTimeout(callback, delay) {timers.set(++timerId, {callback, delay}); return timerId;}, clearTimeout(id) {timers.delete(id);}, addEventListener(name, callback) {this.handlers[name] = callback;}, Swal: {fire: async () => {confirmCalls++; return {isConfirmed: false};}}};
function runtimeFixture(state, message) {return {healthy: state === 'active', generated_at: '2026-10-15T12:00:00+07:00', scheduler: {state, state_label: state, message, available: true, enabled: state !== 'stopped', task_registered: true, tone: state === 'active' ? 'success' : 'warning', log_tail: Array.from({length: 180}, (_, index) => 'source log ' + index).join('\n')}, infrastructure: infrastructureDefinitions.map(key => ({key, state: 'running', startable: true, automatic: true})), schedules: [{label: '<script>source label</script>', command: 'scheduler:heartbeat', expression: '* * * * *', next_run_at: '2026-10-15T12:01:00+07:00', timezone: 'Asia/Jakarta'}]};}
function responseFor(item) {return {ok: item.httpStatus === undefined || item.httpStatus < 400, status: item.httpStatus || 200, json: async () => ({status: item.status || 'success', runtime: item.runtime, message: item.message})};}
global.fetch = async (url, options = {}) => {requests.push({method: options.method || 'GET', body: options.body || null}); const item = responses.shift(); if (item === 'offline') throw new Error('Network unavailable'); if (!item) throw new Error('Unexpected request'); return responseFor(item);};
JS;
        $script = '(async () => {const buttonDefinitions = '.json_encode($buttonData, JSON_THROW_ON_ERROR).'; const infrastructureDefinitions = '.json_encode($infrastructureKeys, JSON_THROW_ON_ERROR).';'.$setup."\n".$logic."\n".$action."\n".'console.log(JSON.stringify({...result, requests}));})().catch(error => {console.error(error); process.exitCode = 1;});';
        $process = new Process([$node]);
        $process->setInput($script);
        $process->setTimeout(10);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function createUser(string $role = 'superadmin', string $pn = User::SUPERADMIN_PN): User
    {
        return User::create(['name' => 'Runtime Operator', 'pn' => $pn, 'role' => $role, 'password' => 'Runtime-password-123', 'branch_scope' => 'area6']);
    }
}
