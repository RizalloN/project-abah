import test from 'node:test';
import assert from 'node:assert/strict';
import { PresentationRoom, topicFocusIndex } from '../../resources/js/presentation/segments/presentation-room.js';

test('topic focus moves in agenda order without changing the selected chapter', () => {
    assert.equal(topicFocusIndex('ArrowRight', 0, 7), 1);
    assert.equal(topicFocusIndex('ArrowDown', 2, 7), 3);
    assert.equal(topicFocusIndex('ArrowLeft', 3, 7), 2);
    assert.equal(topicFocusIndex('ArrowUp', 3, 7), 2);
    assert.equal(topicFocusIndex('Home', 4, 7), 0);
    assert.equal(topicFocusIndex('End', 1, 7), 6);
});

test('topic focus wraps at either end, including a single-topic agenda', () => {
    assert.equal(topicFocusIndex('ArrowRight', 6, 7), 0);
    assert.equal(topicFocusIndex('ArrowLeft', 0, 7), 6);
    assert.equal(topicFocusIndex('ArrowRight', 0, 1), 0);
    assert.equal(topicFocusIndex('ArrowLeft', 0, 1), 0);
});

test('empty lists, foreign focus and native activation keys remain untouched', () => {
    assert.equal(topicFocusIndex('ArrowRight', 0, 0), null);
    assert.equal(topicFocusIndex('ArrowRight', -1, 7), null);
    assert.equal(topicFocusIndex('End', 7, 7), null);
    for (const key of ['Tab', 'Enter', ' ', 'Escape', 'PageDown', 'a']) {
        assert.equal(topicFocusIndex(key, 1, 7), null);
    }
});

test('closing the topic chooser returns stranded focus without stealing outside focus', t => {
    const previous = globalThis.document;
    t.after(() => { globalThis.document = previous; });
    const topic = {};
    const panel = { hidden: false, contains: node => node === topic };
    let restored = 0;
    const trigger = { setAttribute() {}, focus() { restored++; } };
    const head = { hidden: false, querySelector: selector => selector === '.sg-room-topics' ? panel : trigger };
    const room = Object.assign(Object.create(PresentationRoom.prototype), { head });
    globalThis.document = { activeElement: topic };
    room.toggleTopics(false);
    assert.equal(panel.hidden, true);
    assert.equal(restored, 1);
    document.activeElement = {};
    room.toggleTopics(false);
    assert.equal(restored, 1, 'a click or focus outside the chooser retains its destination');
    panel.hidden = false;
    head.hidden = true;
    document.activeElement = topic;
    room.toggleTopics(false);
    assert.equal(restored, 1, 'returning to the launcher never focuses a hidden room trigger');
});

test('opening the topic chooser reveals the active topic in a short viewport', () => {
    const calls = [];
    const panel = { hidden: true };
    const trigger = { setAttribute() {} };
    const topic = {
        focus(options) { calls.push(['focus', options]); },
        scrollIntoView(options) { calls.push(['scroll', options]); },
    };
    const head = { querySelector: selector => selector === '.sg-room-topics' ? panel : selector === '[data-room-chooser]' ? trigger : topic };
    const room = Object.assign(Object.create(PresentationRoom.prototype), { head, activeKey: 'recovery' });
    room.toggleTopics(true);
    assert.equal(panel.hidden, false);
    assert.deepEqual(calls, [
        ['focus', { preventScroll: true }],
        ['scroll', { block: 'nearest', inline: 'nearest', behavior: 'instant' }],
    ]);
});
