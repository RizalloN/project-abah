import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { FLIGHT, cinemaTier, flightProgress, litAgendaCount, seamDirection, PresentationCinema } from '../../resources/js/presentation/segments/cinema.js';
import { glide, wheelPixels } from '../../resources/js/presentation/segments/smooth-scroll.js';
import { PresentationRoom, showOnlyRoom } from '../../resources/js/presentation/segments/presentation-room.js';

const source = (path) => readFileSync(new URL(`../../resources/${path}`, import.meta.url), 'utf8');

test('the flight holds above Earth until the deck is ready and never passes its end', () => {
    assert.equal(flightProgress(0), 0);
    assert.equal(flightProgress(60_000), FLIGHT.hold, 'a slow server keeps the camera holding, not landing');
    const landedAt = 5000;
    assert.equal(flightProgress(landedAt, landedAt), FLIGHT.hold, 'landing starts exactly where the hold stopped');
    assert.equal(flightProgress(landedAt + FLIGHT.landing, landedAt), FLIGHT.end);
    assert.equal(flightProgress(landedAt + FLIGHT.landing * 9, landedAt), FLIGHT.end);
    let previous = 0;
    for (let t = 0; t <= FLIGHT.landing; t += 100) {
        const value = flightProgress(landedAt + t, landedAt);
        assert.ok(value >= previous, 'the camera never climbs back during the descent');
        previous = value;
    }
});

test('the agenda lights in order and is complete by the hand-off', () => {
    assert.equal(litAgendaCount(0, 7), 0);
    assert.equal(litAgendaCount(FLIGHT.handoff, 7), 7);
    assert.equal(litAgendaCount(FLIGHT.end, 7), 7);
    assert.equal(litAgendaCount(0.5, 0), 0);
    assert.ok(litAgendaCount(0.3, 7) > 0 && litAgendaCount(0.3, 7) < 7);
});

test('only a capable desktop gets the planet; reduced motion always wins', () => {
    assert.equal(cinemaTier({}), 'full');
    assert.equal(cinemaTier({ reduced: true }), 'still');
    assert.equal(cinemaTier({ reduced: true, width: 390, coarse: true }), 'still');
    for (const small of [{ width: 390 }, { coarse: true }, { saveData: true }, { memory: 4 }, { cores: 4 }]) assert.equal(cinemaTier(small), 'lite');
    assert.equal(cinemaTier({ memory: 16, cores: 12 }), 'full');
});

test('a seam travels the way the reader moves through the agenda', () => {
    const keys = ['marketshare', 'mapping', 'simpanan'];
    assert.equal(seamDirection(keys, 'marketshare', 'simpanan'), 1);
    assert.equal(seamDirection(keys, 'simpanan', 'mapping'), -1);
    assert.equal(seamDirection(keys, 'unknown', 'mapping'), 1);
});

test('the wheel glide converges exactly and is independent of notch units', () => {
    assert.equal(wheelPixels({ deltaY: 100 }), 100);
    assert.equal(wheelPixels({ deltaY: 3, deltaMode: 1 }), 48);
    assert.equal(wheelPixels({ deltaY: 1, deltaMode: 2 }, 800), 800);
    let position = 0;
    let frames = 0;
    while (position !== 300 && frames < 600) { position = glide(position, 300, 1 / 60); frames += 1; }
    assert.equal(position, 300, 'it ends on the requested pixel instead of approaching forever');
    assert.ok(frames > 8 && frames < 120, `one notch glides over several frames, got ${frames}`);
    const first = glide(0, 300, 1 / 60);
    assert.ok(first > 0 && first < 300);
});

test('the finale is one more room: it replaces the last topic and returns through history', () => {
    const chapters = ['marketshare', 'penutup'].map((key) => ({ dataset: { sgSegment: key }, setAttribute() {} }));
    showOnlyRoom(chapters, 'penutup');
    assert.deepEqual(chapters.filter((chapter) => !chapter.hidden).map((chapter) => chapter.dataset.sgSegment), ['penutup']);
    const room = source('js/presentation/segments/presentation-room.js');
    assert.match(room, /value\.view === 'finale'/, 'browser history can come back to the closing frame');
    assert.match(room, /data-room-finale/, 'the last topic offers the closing frame');
    assert.equal(typeof PresentationRoom.prototype.showFinale, 'function');
});

test('decorative layers never carry a figure and cannot be read or focused', () => {
    const cinema = source('js/presentation/segments/cinema.js');
    assert.doesNotMatch(cinema, /textContent\s*=/, 'the film never writes into source text');
    assert.match(cinema, /sg-atmos-track[\s\S]*aria-hidden/);
    assert.match(cinema, /sg-act-ghost[\s\S]*aria-hidden/);
    const css = source('css/presentation/segments/cinema.css');
    assert.match(css, /\.sg-atmos-track[\s\S]*z-index: -1/, 'atmosphere stays behind the report');
    assert.match(css, /pointer-events: none/);
    assert.match(css, /prefers-reduced-motion: reduce[\s\S]*display: none !important/, 'reduced motion removes the decoration entirely');
    assert.doesNotMatch(css, /transition:\s*all/);
});

test('labels of the previous state are cut, never faded, so a stale figure cannot linger', () => {
    const studio = source('js/presentation/worlds/territory-studio.js');
    assert.match(studio, /hideLabel\(element\) \{\s*element\.style\.transition = 'none';/);
    assert.doesNotMatch(studio, /element\.style\.opacity = '0'; element\.inert = true; \}/, 'every hide goes through hideLabel');
});

test('the camera stays one camera: no flight state survives its end', () => {
    const relief = source('js/presentation/worlds/relief.js');
    assert.match(relief, /endFlight\(\) \{\s*this\.flight\?\.dispose\(\);\s*this\.flight = null;\s*this\.camera\.far = 200;/, 'the planet frees its GPU memory and the depth range returns');
    const studio = source('js/presentation/worlds/territory-studio.js');
    assert.match(studio, /registerFlightState\(\)/, 'a deck rebuild during the flight keeps its state');
    const main = source('js/presentation/segments/main.js');
    assert.match(main, /studio\.registerFlightState\(\)/);
    assert.match(main, /cinema\.abortFlight\(\)/, 'a failed build never leaves the stage over the launcher');
});

test('a second start does not repeat the flight', () => {
    const cinema = Object.create(PresentationCinema.prototype);
    Object.assign(cinema, { flown: true, tier: () => 'full', studio: { canFly: () => true } });
    assert.equal(cinema.canFly(), false);
});

test('the film is imported last and needs no Blade change, so the live page cannot drift from its bundle', () => {
    const entry = source('css/presentation/presentation-segments.css');
    assert.match(entry.trim(), /@import "\.\/segments\/cinema\.css";$/);
    const view = source('views/presentation/segments.blade.php');
    assert.doesNotMatch(view, /sg-flight|sg-atmos|sg-seam/);
});
