# Device performance — 1 November 2026

The requested outcome is unchanged visual density with lower JavaScript startup and rendering cost on different devices. This is an optimization of the existing Asix / Scroll Craft / Scroll World presentation engine, without new dependencies or a new animation framework.

## Changes

- `resources/js/presentation/segments/main.js`: load Wilayah and Kinerja renderer modules only when the selected agenda requires them. Share successful loads and clear failed application-owned promises. Retain the existing loading/retry state and synchronous render callbacks after loading.
- `resources/js/presentation/segments/device-runtime.js`: device-aware pixel, shadow and particle budgets; moving-frame delay sampling can lower pixel resolution in bounded steps. Missing device hints remain supported. No values, chart coordinates, chapter choices or animation sequences are removed.
- `resources/js/presentation/worlds/territory-studio.js`: first show the SVG map and controls, then schedule the GPU enhancement at idle. Keep one persistent canvas, one scheduled frame or ambient timer, immediate input wakeups, a 60 Hz moving-frame ceiling, and a lower ambient cadence. A stationary pointer no longer requires moving-frame cadence. Clear work on hidden tabs and resume from fresh timing. Recheck viewport dimensions after asynchronous shader preparation.
- `resources/js/presentation/worlds/relief.js`: preserve default behavior for other callers, accept explicit pixel/shadow budgets, and offer asynchronous material compilation with stable material restoration even after failure.
- `resources/js/presentation/segments/motion.js`: hover damping no longer rereads every report on each frame; scroll work visits the active room, opener layout is cached until invalidated, and hidden-tab work is cancelled.
- `resources/js/presentation/segments/expressive-motion.js`: collect base material projections before starting animation writes, reducing repeated forced style recalculation while keeping every sequence.
- `tests/Frontend/presentation-device-runtime.test.mjs`: device budgets, active-only adaptation, single scheduling ownership, hover/scroll separation and shader success/failure cleanup.

## Evidence

The main entry decreased from approximately 234.82 KB to 159.86 KB minified (about 32%); gzip decreased from 68.51 KB to 46.75 KB. This is the main entry reduction, not a claim that all Three.js bytes disappeared. The GPU library remains a separate dynamic import. A Simpanan-only agenda requested only `render-kinerja`; Marketshare-only requested only `render-wilayah`. Neither renderer was requested at the launcher.

Final production entry: `main-PsX7kc8j.js`. Production Vite build passed. Six reachable JS asset bodies were fetched without instrumentation and SHA-256 matched the build on disk.

- 66 focused frontend tests passed.
- 13 PHP presentation layout tests passed, 151 assertions.
- Broad frontend run before the last three new regression cases: 145 passed / 1 known unrelated failure in `presentation-sector-model.test.mjs` (60 versus 75, owned-village expectation). Its production source and test were not changed in this task.
- JavaScript syntax checks for all six touched runtime modules passed; scoped whitespace checks passed; repository knowledge graph is current.
- Final production-asset Chromium matrix: 173 checks passed across desktop 1440x900, phone 390x844 with DPR 3 / 2-core / 2 GB hints and 4x CPU throttling, touch tablet 1024x768, touch landscape 844x390, reduced motion, and 320px SVG fallback without WebGL.
- Each profile traversed all seven chapters, scrolled forward/back, checked existing source cells, one active chapter, page containment, back navigation, one persistent canvas, zero GPU draws when detail covers the map, and frame/timer cancellation on hidden-page events. Sektoral may append asynchronously loaded CRAS rows while existing cells remain intact.

Device budgets keep the same animation sequences and active-frame target; compact/constrained devices get fewer background particles and smaller shadow/pixel surfaces. Adaptation changes resolution only after repeated late moving frames. Ambient sleeps do not count as missed frames. Reduced motion disables ambient scheduling.

## Limits

Tests use browser device emulation and reused verified October 2026 source exports in an isolated rendering of the current Blade view. They are not an authenticated production session, fresh financial-source reconciliation, physical iOS/Android tests, or Safari/Firefox testing. CPU throttling does not fully model a phone GPU, temperature, battery or operating-system load; no universal FPS guarantee is made. Exploratory idle render samples in baseline.json/optimized.json are retained as raw evidence, not a controlled FPS benchmark.

The Scroll Craft doctor could not see FFmpeg in the restricted shell and reported local playwright-core absent; no video/image generation or dependency installation was required. Verification used the available connected Chromium tool.

Detailed scripts, final results, hashes and screenshots: `storage/framework/testing/presentation-device-performance/`.
