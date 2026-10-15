<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class StandaloneToastRuntimeTest extends TestCase
{
    public function test_spreadsheet_toast_keeps_source_messages_literal_and_limits_duration_and_variants(): void
    {
        $result = $this->runRuntime('spreadsheet', <<<'JS'
const message = '<img src=x onerror=alert(1)> & "laporan"\nBaris berikutnya';
showToast(message, 'error', Infinity);
const first = stack.children[0];
const defaultDuration = [...timers.values()][0].delay;
showToast('Pendek', 'success', 1);
showToast('Panjang', 'unknown is-error', 999999);
const delays = [...timers.values()].map(item => item.delay);
console.log(JSON.stringify({
    messageLiteral: first.children[1].textContent === message && htmlAssignments.length === 0,
    statusAnnounced: first.attributes.role === 'status' && first.attributes['aria-atomic'] === 'true',
    decorativeIconHidden: first.children[0].attributes['aria-hidden'] === 'true',
    accessibleClose: first.children[2].tagName === 'BUTTON' && first.children[2].type === 'button' && first.children[2].attributes['aria-label'] === 'Tutup notifikasi',
    boundedDuration: defaultDuration === 3200 && delays.includes(1000) && delays.includes(30000),
    variantAllowlisted: stack.children[2].className === 'asix-sheet-toast is-info'
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_spreadsheet_toast_bursts_and_manual_close_release_all_nodes_and_timers(): void
    {
        $result = $this->runRuntime('spreadsheet', <<<'JS'
for (let index = 0; index < 32; index++) showToast('Pesan ' + index, 'info', 4000);
const bounded = stack.children.length === 4 && timers.size === 4;
const oldestEvicted = stack.children[0].children[1].textContent === 'Pesan 28';
const first = stack.children[0];
first.children[2].dispatch('click');
first.children[2].dispatch('click');
const duplicateCloseSafe = stack.children.length === 3 && timers.size === 3;
advance(4000);
console.log(JSON.stringify({bounded, oldestEvicted, duplicateCloseSafe, fullyCleaned: stack.children.length === 0 && timers.size === 0}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_spreadsheet_toast_preserves_remaining_time_until_hover_and_keyboard_focus_both_leave(): void
    {
        $result = $this->runRuntime('spreadsheet', <<<'JS'
showToast('Baca pesan', 'success', 4000);
const notification = stack.children[0];
const closeButton = notification.children[2];
advance(1000);
notification.dispatch('mouseenter');
document.activeElement = closeButton;
notification.dispatch('focusin', {relatedTarget: outside});
notification.dispatch('mouseleave');
const keyboardKeepsPaused = timers.size === 0;
advance(50000);
const readingDoesNotDismiss = stack.children.length === 1;
document.activeElement = outside;
notification.dispatch('focusout', {relatedTarget: outside});
const remainingPreserved = [...timers.values()][0].delay === 3000;
advance(2999);
const lastsForRemainingTime = stack.children.length === 1;
advance(1);
showToast('Tutup dengan keyboard', 'info');
const next = stack.children[0];
document.activeElement = next.children[2];
next.dispatch('focusin', {relatedTarget: outside});
next.children[2].dispatch('click');
console.log(JSON.stringify({keyboardKeepsPaused, readingDoesNotDismiss, remainingPreserved, lastsForRemainingTime, focusRestored: document.activeElement === outside, fullyCleaned: stack.children.length === 0 && timers.size === 0}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_presentation_notifications_reuse_one_node_and_keep_source_copy_literal(): void
    {
        $result = $this->runRuntime('presentation', <<<'JS'
const hiddenInitially = toast.hidden === true && toast.children[1].disabled === true;
for (let index = 0; index < 32; index++) presentation.notify('Pesan ' + index);
const stableFootprint = toast.children.length === 2 && timers.size === 1;
const latestOnly = toast.children[0].textContent === 'Pesan 31';
const message = '<script>alert(1)</script> & "copy"\nBaris berikutnya';
presentation.notify(message);
console.log(JSON.stringify({
    hiddenInitially,
    stableFootprint,
    latestOnly,
    messageLiteral: toast.children[0].textContent === message && htmlAssignments.length === 0,
    visibleAndAccessible: toast.hidden === false && toast.classList.contains('visible') && toast.children[1].disabled === false && toast.children[1].attributes['aria-label'] === 'Tutup notifikasi',
    existingDurationPreserved: [...timers.values()][0].delay === 2200
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_presentation_toast_pauses_reading_and_finishes_its_fade_when_transition_events_are_missing(): void
    {
        $result = $this->runRuntime('presentation', <<<'JS'
presentation.notify('Baca pesan');
advance(1000);
toast.dispatch('mouseenter');
document.activeElement = toast.children[1];
toast.dispatch('focusin', {relatedTarget: outside});
toast.dispatch('mouseleave');
const bothPauseSourcesRespected = timers.size === 0;
advance(50000);
const stillVisible = toast.classList.contains('visible');
document.activeElement = outside;
toast.dispatch('focusout', {relatedTarget: outside});
const remainingDurationPreserved = [...timers.values()][0].delay === 1200;
advance(1200);
const hidesAfterDuration = !toast.classList.contains('visible') && toast.children[1].disabled === true;
advance(180);
console.log(JSON.stringify({bothPauseSourcesRespected, stillVisible, remainingDurationPreserved, hidesAfterDuration, fallbackFullyCleansTimers: toast.hidden === true && timers.size === 0}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_new_presentation_notification_cancels_pending_dismissal_and_keeps_focus_pause(): void
    {
        $result = $this->runRuntime('presentation', <<<'JS'
presentation.notify('Pesan pertama');
toast.children[1].dispatch('click');
const exitWasPending = timers.size === 1;
presentation.notify('Pesan terbaru');
advance(180);
const oldExitCannotHideNewMessage = toast.hidden === false && toast.classList.contains('visible') && toast.children[0].textContent === 'Pesan terbaru' && timers.size === 1;
document.activeElement = toast.children[1];
toast.dispatch('focusin', {relatedTarget: outside});
presentation.notify('Pesan ketika fokus');
const replacementStillPaused = timers.size === 0;
advance(50000);
const survivesReading = toast.classList.contains('visible');
document.activeElement = outside;
toast.dispatch('focusout', {relatedTarget: outside});
const replacementGetsFullDuration = [...timers.values()][0].delay === 2200;
toast.dispatch('transitionend', {propertyName: 'opacity'});
console.log(JSON.stringify({exitWasPending, oldExitCannotHideNewMessage, replacementStillPaused, survivesReading, replacementGetsFullDuration, staleTransitionCannotHideVisibleToast: toast.hidden === false}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_presentation_close_restores_focus_and_supports_reduced_motion_without_animation_timers(): void
    {
        $result = $this->runRuntime('presentation', <<<'JS'
window.matchMedia = () => ({matches: true});
presentation.notify('Tutup pesan');
const closeButton = toast.children[1];
document.activeElement = closeButton;
toast.dispatch('focusin', {relatedTarget: outside});
let shortcutBubbled = true;
closeButton.dispatch('keydown', {stopPropagation() {shortcutBubbled = false;}});
closeButton.dispatch('click');
console.log(JSON.stringify({
    keyboardShortcutDoesNotReachSlides: shortcutBubbled === false,
    focusRestored: document.activeElement === outside,
    closedImmediately: toast.hidden === true && !toast.classList.contains('visible'),
    closeNoLongerFocusable: closeButton.disabled === true,
    timersCleared: timers.size === 0
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_presentation_toast_joins_the_active_native_modal_and_returns_home_after_close(): void
    {
        $result = $this->runRuntime('presentation', <<<'JS'
const dialog = new Element('dialog');
document.body.appendChild(dialog);
activeDialogs = [dialog];
presentation.notify('Catatan dihapus');
const movedIntoModal = toast.parentNode === dialog && dialog.children.includes(toast);
presentation.notify('Pesan terbaru');
const onlyOneListenerAndTimer = dialog.listeners.close.length === 1 && timers.size === 1;
advance(500);
toast.dispatch('mouseenter');
activeDialogs = [];
dialog.dispatch('close');
const remainingAfterClose = [...timers.values()][0]?.delay;
console.log(JSON.stringify({
    movedIntoModal,
    visibleInsideModal: toast.classList.contains('visible'),
    onlyOneListenerAndTimer,
    restoredHome: toast.parentNode === document.body && !dialog.children.includes(toast),
    closeListenerCleaned: dialog.listeners.close.length === 0,
    readingTimePreservedAfterDialogCloses: remainingAfterClose === 1700
}));
JS);

        $this->assertAllTrue($result);
    }

    public function test_presentation_toast_switches_to_the_last_modal_and_removes_the_previous_close_listener(): void
    {
        $result = $this->runRuntime('presentation', <<<'JS'
const first = new Element('dialog'), last = new Element('dialog');
document.body.appendChild(first); document.body.appendChild(last);
activeDialogs = [first];
presentation.notify('Pesan pertama');
activeDialogs = [first, last];
presentation.notify('Pesan untuk modal teratas');
const upperModalSelected = toast.parentNode === last && first.listeners.close.length === 0 && last.listeners.close.length === 1;
first.dispatch('close');
const staleCloseCannotMoveToast = toast.parentNode === last;
activeDialogs = [];
last.dispatch('close');
advance(2380);
console.log(JSON.stringify({
    upperModalSelected,
    staleCloseCannotMoveToast,
    allCloseListenersReleased: first.listeners.close.length === 0 && last.listeners.close.length === 0,
    originalNodeRestored: toast.parentNode === document.body && toast.children.length === 2,
    timersCleaned: timers.size === 0 && toast.hidden === true
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

    private function runRuntime(string $surface, string $action): array
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node.js is required to execute the standalone toast engines.');
        }

        $base = dirname(__DIR__, 2);
        if ($surface === 'spreadsheet') {
            $source = file_get_contents($base.'/resources/views/drive/spreadsheet-editor.blade.php');
            $this->assertNotFalse($source);
            $start = strpos($source, 'function showToast(');
            $this->assertNotFalse($start);
            $end = strpos($source, 'function setStatus(', $start);
            $this->assertNotFalse($end);
            $logic = 'const elements = {toastStack: stack};'."\n".substr($source, $start, $end - $start);
        } else {
            $source = file_get_contents($base.'/resources/js/presentation/pres-interactions.js');
            $this->assertNotFalse($source);
            $logic = str_replace('export const setupPresentationInteractions', 'const setupPresentationInteractions', $source);
            $logic .= <<<'JS'

const presentation = setupPresentationInteractions({
    root: new Element(), config: {periods: []}, dataLoader: {}, getData: () => ({}),
    getSlideIndex: () => 0, getTotalSlides: () => 15, getScope: () => 'area6',
    showSlide() {}, toggleAutoplay() {}
});
JS;
        }

        $setup = <<<'JS'
let now = 0, nextTimerId = 0, activeDialogs = [];
const timers = new Map(), htmlAssignments = [];
class Element {
    constructor(tag = 'div') {
        this.tagName = tag.toUpperCase(); this.children = []; this.parentNode = null;
        this.attributes = {}; this.listeners = {}; this.style = {}; this.className = ''; this.textContent = '';
        this.classList = {
            add: value => {if (!this.className.split(/\s+/).includes(value)) this.className += ' ' + value;},
            remove: value => {this.className = this.className.split(/\s+/).filter(item => item !== value).join(' ');},
            contains: value => this.className.split(/\s+/).includes(value)
        };
    }
    set innerHTML(value) {htmlAssignments.push(value); this._html = value;}
    get innerHTML() {return this._html || '';}
    get firstElementChild() {return this.children[0] || null;}
    get isConnected() {return this === document.body || Boolean(this.parentNode?.isConnected);}
    setAttribute(name, value) {this.attributes[name] = value;}
    appendChild(child) {if (child.parentNode) child.remove(); child.parentNode = this; this.children.push(child); return child;}
    append(...children) {children.forEach(child => this.appendChild(child));}
    replaceChildren(...children) {this.children.forEach(child => {child.parentNode = null;}); this.children = []; this.append(...children);}
    addEventListener(name, callback) {(this.listeners[name] ||= []).push(callback);}
    removeEventListener(name, callback) {this.listeners[name] = (this.listeners[name] || []).filter(item => item !== callback);}
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
    createElement(tag) {return new Element(tag);},
    querySelectorAll(selector) {return selector === 'dialog:modal' || selector === 'dialog[open]' ? activeDialogs : [];},
    querySelector() {return null;}, addEventListener() {}
};
const outside = new Element('button');
document.body.appendChild(outside);
document.activeElement = outside;
const stack = new Element(); stack.id = 'sheetToastStack';
document.body.appendChild(stack);
const toast = new Element(); toast.id = 'pres-toast'; toast.className = 'pres-toast';
document.body.appendChild(toast);
window.matchMedia = () => ({matches: false});
window.location = {hash: '', pathname: '/presentation', search: ''};
window.history = {replaceState() {}};
global.localStorage = {getItem() {return null;}, setItem() {}, removeItem() {}};
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
}
