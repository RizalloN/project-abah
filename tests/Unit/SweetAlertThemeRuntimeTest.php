<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class SweetAlertThemeRuntimeTest extends TestCase
{
    public function test_fire_preserves_callbacks_and_keeps_danger_and_class_overrides_local_to_the_call(): void
    {
        $result = $this->runScript(<<<'JS'
const preConfirm = async () => 'validated';
const didOpen = () => {};
const didClose = () => {};
const customClass = {popup: 'custom-popup', confirmButton: 'custom-confirm'};
const options = {titleText: 'Hapus laporan?', customClass, isDanger: true, preConfirm, didOpen, didClose, input: 'text', showLoaderOnConfirm: true, allowOutsideClick: false};
await ImportSwal.fire(options);
const first = calls.at(-1);
await ImportSwal.fire({titleText: 'Informasi berikutnya'});
const second = calls.at(-1);
console.log(JSON.stringify({
    callbackIdentity: first.preConfirm === preConfirm && first.didOpen === didOpen && first.didClose === didClose,
    behaviorPreserved: first.input === 'text' && first.showLoaderOnConfirm === true && first.allowOutsideClick === false,
    internalFlagRemoved: !Object.hasOwn(first, 'isDanger'),
    callerClassesUnchanged: customClass.popup === 'custom-popup' && customClass.confirmButton === 'custom-confirm',
    callerOptionsUnchanged: options.isDanger === true && options.customClass === customClass,
    classesMerged: first.customClass.popup === 'custom-popup' && first.customClass.confirmButton.includes('custom-confirm') && first.customClass.confirmButton.includes('swal-btn-danger'),
    classMapsIndependent: first.customClass !== customClass && first.customClass !== second.customClass && second.customClass !== ImportSwal.theme.customClass,
    nextCallSafe: !second.customClass.confirmButton.includes('swal-btn-danger') && !ImportSwal.theme.customClass.confirmButton.includes('swal-btn-danger')
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_confirm_preserves_input_validation_lifecycle_and_existing_action_options(): void
    {
        $result = $this->runScript(<<<'JS'
const preConfirm = async () => ({accepted: true});
const didOpen = () => {};
const willClose = () => {};
const customClass = {popup: 'wide-confirmation'};
await ImportSwal.confirm({title: 'Konfirmasi', text: 'Verifikasi', preConfirm, didOpen, willClose, input: 'text', inputValue: 'KEEP', showLoaderOnConfirm: true, allowEscapeKey: false, width: '64rem', confirmText: 'Proses', cancelText: 'Kembali', customClass, focusCancel: true, isDanger: true});
const call = calls.at(-1);
console.log(JSON.stringify({
    callbacksPreserved: call.preConfirm === preConfirm && call.didOpen === didOpen && call.willClose === willClose,
    inputPreserved: call.input === 'text' && call.inputValue === 'KEEP' && call.showLoaderOnConfirm === true,
    escapeAndWidthPreserved: call.allowEscapeKey === false && call.width === '64rem',
    actionLabelsPreserved: call.confirmButtonText === 'Proses' && call.cancelButtonText === 'Kembali' && call.showCancelButton === true && call.focusCancel === true,
    classesPreserved: call.customClass.popup === 'wide-confirmation' && call.customClass.confirmButton.includes('swal-btn-danger'),
    internalFlagRemoved: !Object.hasOwn(call, 'isDanger')
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_notification_messages_are_literal_unless_html_is_explicitly_supplied(): void
    {
        $result = $this->runScript(<<<'JS'
const title = '<img src=x onerror=alert(1)>';
const message = '<script>alert(1)</script> & laporan';
const variants = ['success', 'error', 'warning', 'info'];
const observations = [];
for (const variant of variants) {
    await ImportSwal[variant](title, message);
    const call = calls.at(-1);
    observations.push(call.icon === variant && call.text === message && !Object.hasOwn(call, 'html') && call.titleText === title);
}
const didOpen = () => {};
await ImportSwal.success('Ringkasan', 'Pesan', {html: '<strong>2 baris</strong>', didOpen, timer: 0, showConfirmButton: false});
const explicit = calls.at(-1);
console.log(JSON.stringify({
    literalNotifications: observations.every(Boolean),
    explicitHtmlPreserved: explicit.html === '<strong>2 baris</strong>',
    callerOptionsPreserved: explicit.didOpen === didOpen && explicit.timer === 0 && explicit.showConfirmButton === false
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_progress_escapes_source_text_and_uses_finite_bounded_percentages(): void
    {
        $result = $this->runScript(<<<'JS'
const payload = '<img src=x onerror=alert(1)> & "data"';
let opened = 0;
await ImportSwal.progress({title: payload, description: payload, initialStatus: payload, stat1Label: payload, stat2Label: payload, stat1Value: payload, stat2Value: payload, initialPercent: Infinity, didOpen: () => {opened++;}});
const first = calls.at(-1);
first.didOpen(popup);
await ImportSwal.progress({initialPercent: 140, stat1Value: 0, stat2Value: 0});
const high = calls.at(-1);
await ImportSwal.progress({initialPercent: -9});
const low = calls.at(-1);
await ImportSwal.progress({initialPercent: 'not-a-number'});
const invalid = calls.at(-1);
console.log(JSON.stringify({
    titleLiteral: first.titleText === payload,
    sourceTextEscaped: !first.html.includes(payload) && first.html.includes('&lt;img') && first.html.includes('&amp;'),
    noNonfiniteStyle: !first.html.includes('Infinity') && !invalid.html.includes('NaN') && /width:\s*0%/.test(first.html) && /width:\s*0%/.test(invalid.html),
    boundedPercent: /width:\s*100%/.test(high.html) && /width:\s*0%/.test(low.html),
    nonDismissable: first.allowOutsideClick === false && first.allowEscapeKey === false && first.showConfirmButton === false,
    callbackPreserved: opened === 1,
    zeroStatsPreserved: /id="swal-modern-stat-2">0</.test(high.html)
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_progress_updates_accept_zero_and_do_not_write_nan_into_styles(): void
    {
        $result = $this->runScript(<<<'JS'
for (const id of ['swal-modern-bar', 'swal-modern-percent', 'swal-modern-status', 'swal-modern-stat-1', 'swal-modern-stat-2']) {
    elements[id] = {style: {}, textContent: 'previous'};
}
ImportSwal.updateProgress('not-a-number', 0, {stat1: 0, stat2: 0});
const first = {
    width: elements['swal-modern-bar'].style.width,
    percent: elements['swal-modern-percent'].textContent,
    status: elements['swal-modern-status'].textContent,
    stat1: elements['swal-modern-stat-1'].textContent,
    stat2: elements['swal-modern-stat-2'].textContent
};
ImportSwal.updateProgress(160, 'Selesai');
const highWidth = elements['swal-modern-bar'].style.width;
ImportSwal.updateProgress(-10, '');
console.log(JSON.stringify({
    invalidProgressIsZero: first.width === '0%' && first.percent === '0%',
    statusZeroPreserved: String(first.status) === '0',
    numericZeroStatsPreserved: String(first.stat1) === '0' && String(first.stat2) === '0',
    percentagesBounded: highWidth === '100%' && elements['swal-modern-bar'].style.width === '0%',
    emptyStatusPreserved: elements['swal-modern-status'].textContent === ''
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_toast_respects_persistent_timer_and_composes_the_callers_open_callback(): void
    {
        $result = $this->runScript(<<<'JS'
let opened = 0;
await ImportSwal.toast({title: 'Jangan hilangkan', timer: 0, didOpen: () => {opened++;}});
const persistent = calls.at(-1);
persistent.didOpen(popup);
await ImportSwal.toast({title: 'Normal', didOpen: () => {opened++;}});
const normal = calls.at(-1);
normal.didOpen(popup);
console.log(JSON.stringify({
    persistentTimerPreserved: persistent.timer === 0,
    defaultsPreserved: normal.timer === 3500 && normal.toast === true && normal.position === 'top-end' && normal.showConfirmButton === false,
    callbacksComposed: opened === 2,
    toastStaysNonBlocking: persistent.toast === true && persistent.showConfirmButton === false,
    pointerAndFocusListenersAvailable: ['mouseenter', 'mouseleave', 'focusin', 'focusout'].every(event => (popup.listeners[event] || []).length > 0)
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_toast_timer_pauses_for_hover_and_keyboard_focus_and_resumes_after_both_leave(): void
    {
        $result = $this->runScript(<<<'JS'
await ImportSwal.toast({title: 'Baca informasi', timer: 4000});
const call = calls.at(-1);
call.didOpen(popup);
popup.dispatch('mouseenter');
document.activeElement = inside;
popup.dispatch('focusin', {target: inside});
const stopped = timerCalls.filter(event => event === 'stop').length;
popup.dispatch('mouseleave');
const noResumeWhileFocused = !timerCalls.includes('resume');
document.activeElement = outside;
popup.dispatch('focusout', {target: inside, relatedTarget: outside});
const resumesAfterLeaving = timerCalls.at(-1) === 'resume';
console.log(JSON.stringify({
    hoverAndFocusStopTimer: stopped > 0,
    noResumeWhileFocused,
    resumesAfterLeaving
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_user_toast_displays_markup_literally_and_bounds_notification_timers(): void
    {
        $result = $this->runUserToastScript(<<<'JS'
const payload = '<img src=x onerror=alert(1)> & informasi';
showUserToast('error', payload, payload, Infinity);
const toast = stack.children[0];
const literalTitle = toast.children[1].children[0].textContent;
const literalMessage = toast.children[1].children[1].textContent;
const closeButton = toast.children[2];
showUserToast('success', 'Singkat', 'Pesan', 20);
showUserToast('warning', 'Lama', 'Pesan', 999999999);
showUserToast('unknown', 'Default', 'Pesan', 'invalid');
const delays = [...timers.values()].map(item => item.delay);
console.log(JSON.stringify({
    contentLiteral: literalTitle === payload && literalMessage === payload && !htmlAssignments.includes(payload),
    closeButtonAccessible: closeButton.type === 'button' && closeButton.attributes['aria-label'] === 'Tutup notifikasi',
    notificationAnnounced: toast.attributes.role === 'status' && toast.attributes['aria-atomic'] === 'true',
    durationsBounded: delays.includes(1000) && delays.includes(30000) && delays.includes(6500) && delays.includes(4500),
    unknownTypeSafe: stack.children[3].className.includes('user-toast--success')
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_toast_reopening_and_closing_cleans_listeners_and_preserves_lifecycle_arguments(): void
    {
        $result = $this->runScript(<<<'JS'
const opened = [], closed = [];
await ImportSwal.toast({title: 'Notifikasi', didOpen: element => opened.push(element), willClose: element => closed.push(element)});
const call = calls.at(-1);
call.didOpen(popup);
call.didOpen(popup);
const listenerCount = Object.values(popup.listeners).reduce((sum, listeners) => sum + listeners.length, 0);
call.willClose(popup);
const remainingListenerCount = Object.values(popup.listeners).reduce((sum, listeners) => sum + listeners.length, 0);
const timerEventsBefore = timerCalls.length;
popup.dispatch('mouseenter');
popup.dispatch('focusin');
console.log(JSON.stringify({
    listenersNotDuplicated: listenerCount === 4,
    listenersCleaned: remainingListenerCount === 0,
    detachedEventsCannotChangeTimer: timerCalls.length === timerEventsBefore,
    callbackArgumentsPreserved: opened.length === 2 && opened.every(element => element === popup) && closed.length === 1 && closed[0] === popup
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_global_theme_retains_native_button_options_and_cleans_default_toast_listeners(): void
    {
        $result = $this->runScript(<<<'JS'
await Swal.fire({toast: true, title: 'Notifikasi lama', timer: 1200});
const call = calls.at(-1);
call.didOpen(popup);
const bound = Object.values(popup.listeners).reduce((sum, listeners) => sum + listeners.length, 0);
call.willClose(popup);
console.log(JSON.stringify({
    nativeButtonsAvailable: call.buttonsStyling === true,
    originalCallerOptionsPreserved: call.title === 'Notifikasi lama' && call.timer === 1200 && call.toast === true,
    defaultThemeApplied: call.customClass.popup === 'swal-modern-popup',
    defaultToastListenersBound: bound === 4,
    defaultToastListenersCleaned: Object.values(popup.listeners).every(listeners => listeners.length === 0)
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_user_toast_caps_live_nodes_and_clears_timers_after_eviction_and_repeated_dismissal(): void
    {
        $result = $this->runUserToastScript(<<<'JS'
for (let index = 0; index < 16; index++) showUserToast('success', 'Pesan ' + index, 'Isi', 4500);
const before = {nodes: stack.children.length, timers: timers.size, firstTitle: stack.children[0].children[1].children[0].textContent};
const toast = stack.children[0];
toast.children[2].dispatch('click');
const timersAfterClose = timers.size;
toast.children[2].dispatch('click');
const duplicateCloseSafe = timers.size === timersAfterClose;
advance(360);
const afterClose = {nodes: stack.children.length, timers: timers.size};
advance(5000);
console.log(JSON.stringify({
    domAndTimerCountBounded: before.nodes === 4 && before.timers === 4,
    oldestNotificationsEvicted: before.firstTitle === 'Pesan 12',
    duplicateCloseSafe,
    closeClearsTimersAndNode: afterClose.nodes === 3 && afterClose.timers === 3,
    allNotificationsCleaned: stack.children.length === 0 && timers.size === 0
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_user_toast_pauses_remaining_duration_until_hover_and_keyboard_focus_both_leave(): void
    {
        $result = $this->runUserToastScript(<<<'JS'
showUserToast('success', 'Baca pesan', 'Isi', 4000);
const toast = stack.children[0];
const closeButton = toast.children[2];
advance(1000);
toast.dispatch('mouseenter');
const pausedProgress = toast.children[3].children[0].style.transform;
document.activeElement = closeButton;
toast.dispatch('focusin', {relatedTarget: outside});
toast.dispatch('mouseleave');
const stillPaused = timers.size === 0;
advance(50000);
const survivesReading = stack.children.length === 1;
document.activeElement = outside;
toast.dispatch('focusout', {relatedTarget: outside});
const remainingDuration = [...timers.values()][0]?.delay;
advance(2999);
const survivesUntilRemainingDuration = stack.children.length === 1;
advance(361);
console.log(JSON.stringify({
    progressFrozenAtRemainingFraction: pausedProgress === 'scaleX(0.75)',
    focusKeepsTimerPausedAfterHoverLeaves: stillPaused,
    survivesReading,
    resumesRemainingDuration: remainingDuration === 3000,
    survivesUntilRemainingDuration,
    removedOnceDurationFinishes: stack.children.length === 0 && timers.size === 0
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_user_delete_requires_confirmation_and_prevents_duplicate_submission_while_pending(): void
    {
        $result = $this->runUserDeleteScript(<<<'JS'
const observations = [];
for (const confirmed of [false, true]) {
    let resolveConfirmation;
    const options = [];
    window.Swal = {fire(config) {options.push(config); return new Promise(resolve => {resolveConfirmation = resolve;});}};
    const before = submissions;
    const first = form.submitHandler(event);
    await form.submitHandler(event);
    const pendingProtected = options.length === 1 && button.disabled === true && form.dataset.confirmPending === '1';
    resolveConfirmation({isConfirmed: confirmed});
    await first;
    observations.push({
        pendingProtected,
        messageLiteral: options[0].text.includes(form.dataset.userName) && !Object.hasOwn(options[0], 'html'),
        focusSafe: options[0].focusCancel === true && options[0].showCancelButton === true,
        submittedOnlyOnConfirmation: submissions - before === (confirmed ? 1 : 0),
        controlsRestored: button.disabled === false && !Object.hasOwn(form.dataset, 'confirmPending')
    });
}
console.log(JSON.stringify(Object.fromEntries(Object.keys(observations[0]).map(key => [key, observations.every(item => item[key])]))));
JS);

        $this->assertAllTrue($result);
    }

    public function test_user_toast_recreates_an_accessible_stack_and_restores_keyboard_focus_with_reduced_motion(): void
    {
        $result = $this->runUserToastScript(<<<'JS'
stack.remove();
window.matchMedia = () => ({matches: true});
showUserToast('success', 'Berhasil', 'Perubahan tersimpan', 4000);
const createdStack = document.getElementById('userToastStack');
const toast = createdStack.children[0];
const closeButton = toast.children[2];
document.activeElement = closeButton;
toast.dispatch('focusin', {relatedTarget: outside});
closeButton.dispatch('click');
console.log(JSON.stringify({
    recreatedStackAnnounced: createdStack.attributes['aria-live'] === 'polite' && createdStack.attributes['aria-atomic'] === 'false',
    removedWithoutAnimationDelay: createdStack.children.length === 0 && timers.size === 0,
    focusRestored: document.activeElement === outside,
    noProgressAnimation: toast.children[3].children[0].style.transform === undefined
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_user_delete_failure_restores_controls_and_missing_sweetalert_uses_native_confirmation(): void
    {
        $result = $this->runUserDeleteScript(<<<'JS'
window.Swal = {fire() {throw new Error('Dialog unavailable');}};
await form.submitHandler(event);
const failureRestored = button.disabled === false && !Object.hasOwn(form.dataset, 'confirmPending') && submissions === 0;
const failureAnnounced = notices.length === 1 && notices[0][0] === 'error';
delete window.Swal;
let nativeMessage = '';
window.confirm = text => {nativeMessage = text; return false;};
await form.submitHandler(event);
const nativeCancelSafe = submissions === 0;
window.confirm = text => {nativeMessage = text; return true;};
await form.submitHandler(event);
console.log(JSON.stringify({
    failureRestored,
    failureAnnounced,
    nativeCancelSafe,
    nativeConfirmedOnce: submissions === 1,
    nativeMessageLiteral: nativeMessage.includes(form.dataset.userName),
    nativeControlsRestored: button.disabled === false && !Object.hasOwn(form.dataset, 'confirmPending')
}));
JS);

        $this->assertAllTrue($result);
    }

    private function assertAllTrue(array $results): void
    {
        $this->assertNotEmpty($results);
        foreach ($results as $behavior => $passed) {
            $this->assertTrue($passed, $behavior);
        }
    }

    private function runScript(string $action): array
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node.js is required to execute the shared SweetAlert theme.');
        }

        $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/import/partials/swal-modern.blade.php');
        $this->assertNotFalse($source);
        preg_match_all('/<script\b[^>]*>([\s\S]*?)<\/script>/', $source, $scripts);
        $this->assertNotEmpty($scripts[1]);
        $setup = <<<'JS'
const calls = [], timerCalls = [], elements = {};
const inside = {}, outside = {};
const popup = {
    listeners: {},
    classList: {contains(value) {return value === 'swal2-toast';}},
    addEventListener(event, callback) {(this.listeners[event] ||= []).push(callback);},
    removeEventListener(event, callback) {this.listeners[event] = (this.listeners[event] || []).filter(item => item !== callback);},
    contains(node) {return node === inside || node === this;},
    dispatch(event, values = {}) {for (const callback of this.listeners[event] || []) callback.call(this, {...values, currentTarget: this});}
};
global.window = global;
global.document = {
    getElementById(id) {return elements[id] || null;},
    activeElement: outside,
    createElement() {
        return {
            textContent: '',
            get innerHTML() {return String(this.textContent).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');}
        };
    }
};
global.Swal = {
    fire(options) {calls.push(options); return Promise.resolve({isConfirmed: false, isDismissed: true});},
    mixin(defaults) {
        const base = this;
        return {...base, fire(options) {return base.fire({...defaults, ...options});}};
    },
    stopTimer() {timerCalls.push('stop');},
    resumeTimer() {timerCalls.push('resume');},
    getPopup() {return popup;},
    isVisible() {return true;},
    close() {}
};
JS;
        $script = '(async () => {'."\n".$setup."\n".implode("\n", $scripts[1])."\n".$action."\n"
            .'})().catch(error => {console.error(error); process.exitCode = 1;});';
        $process = new Process([$node]);
        $process->setInput($script);
        $process->setTimeout(10);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function runUserToastScript(string $action): array
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node.js is required to execute the user toast engine.');
        }

        $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/user-management.blade.php');
        $this->assertNotFalse($source);
        $start = strpos($source, 'window.showUserToast = function (');
        $this->assertNotFalse($start);
        $end = strpos($source, "document.addEventListener('DOMContentLoaded'", $start);
        $this->assertNotFalse($end);
        $logic = substr($source, $start, $end - $start);
        $setup = <<<'JS'
let now = 0, nextTimerId = 0;
const timers = new Map(), htmlAssignments = [];
class Element {
    constructor(tag = 'div') {
        this.tagName = tag.toUpperCase(); this.children = []; this.parentNode = null;
        this.attributes = {}; this.listeners = {}; this.style = {}; this.className = ''; this.textContent = '';
        this.classList = {
            add: value => {if (!this.className.split(/\s+/).includes(value)) this.className += ' ' + value;},
            remove: value => {this.className = this.className.split(/\s+/).filter(item => item !== value).join(' ');}
        };
    }
    set innerHTML(value) {htmlAssignments.push(value); this._html = value;}
    get innerHTML() {return this._html || '';}
    get firstElementChild() {return this.children[0] || null;}
    get isConnected() {return this === document.body || Boolean(this.parentNode?.isConnected);}
    setAttribute(name, value) {this.attributes[name] = value;}
    appendChild(child) {child.parentNode = this; this.children.push(child); return child;}
    addEventListener(name, callback) {(this.listeners[name] ||= []).push(callback);}
    dispatch(name, values = {}) {for (const callback of this.listeners[name] || []) callback({type: name, target: this, currentTarget: this, ...values});}
    dispatchEvent(event) {this.dispatch(event.type); return true;}
    contains(node) {return node === this || this.children.some(child => child.contains(node));}
    remove() {
        if (this.parentNode) this.parentNode.children = this.parentNode.children.filter(child => child !== this);
        this.parentNode = null;
    }
    focus() {document.activeElement = this;}
}
global.window = global;
global.document = {
    body: new Element('body'), activeElement: null,
    getElementById(id) {return this.body.children.find(child => child.id === id) || null;},
    createElement(tag) {return new Element(tag);}
};
const outside = new Element('button');
document.body.appendChild(outside);
document.activeElement = outside;
const stack = new Element(); stack.id = 'userToastStack';
document.body.appendChild(stack);
window.matchMedia = () => ({matches: false});
window.requestAnimationFrame = callback => {callback(); return 1;};
Date.now = () => now;
window.setTimeout = (callback, delay) => {
    const id = ++nextTimerId; timers.set(id, {callback, delay, at: now + delay}); return id;
};
window.clearTimeout = id => timers.delete(id);
function advance(milliseconds) {
    const target = now + milliseconds;
    while (true) {
        const next = [...timers.entries()].filter(([, item]) => item.at <= target).sort((a, b) => a[1].at - b[1].at)[0];
        if (!next) break;
        timers.delete(next[0]); now = next[1].at; next[1].callback();
    }
    now = target;
}
JS;
        $process = new Process([$node]);
        $process->setInput($setup."\n".$logic."\n".$action);
        $process->setTimeout(10);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function runUserDeleteScript(string $action): array
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node.js is required to execute user delete confirmation.');
        }

        $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/user-management.blade.php');
        $this->assertNotFalse($source);
        $start = strpos($source, "document.querySelectorAll('.form-delete-user').forEach");
        $this->assertNotFalse($start);
        $end = strpos($source, "if (typeof window.jQuery !== 'undefined')", $start);
        $this->assertNotFalse($end);
        $logic = substr($source, $start, $end - $start);
        $setup = <<<'JS'
let submissions = 0;
const notices = [], button = {disabled: false};
const form = {
    dataset: {userName: '<img src=x onerror=alert(1)> "nama"'},
    addEventListener(event, callback) {this.submitHandler = callback;},
    querySelector() {return button;}
};
const event = {submitter: button, preventDefault() {}};
global.window = global;
window.showUserToast = (...args) => notices.push(args);
global.document = {querySelectorAll() {return [form];}};
global.HTMLFormElement = class {submit() {submissions++;}};
JS;
        $script = '(async () => {'."\n".$setup."\n".$logic."\n".$action."\n"
            .'})().catch(error => {console.error(error); process.exitCode = 1;});';
        $process = new Process([$node]);
        $process->setInput($script);
        $process->setTimeout(10);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
