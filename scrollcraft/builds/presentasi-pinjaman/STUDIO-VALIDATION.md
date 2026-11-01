# Presentation studio — implementation and validation

28 October 2026. Continues the approved seven-topic presentation in the Pinjaman landing page. Design direction and skill adaptation: [STUDIO-BRIEF.md](STUDIO-BRIEF.md).

## Delivered

- An executive atlas launcher: reserved live geographic stage, compact scope cards, seven individually drawn dimensional topic motifs, and an agenda that immediately reflects the presenter's selection.
- A selected-branch threshold: an opaque navy veil precedes the branch identity. The decorative geographic silhouette fits the actual selected boundaries. Layered frames remain away from the title. Back restores the explicit map choice.
- A moving topic position marker, persistent selected-office context, and the current Kinerja reading section beside the topic title. Directional arrival is cancelled when navigation is interrupted or reduced motion is enabled.
- Optional native fullscreen with pressed state, accessible labels, status announcements, and a recoverable refusal state. Unsupported browsers retain the normal presentation controls.
- Local glass reflections for vessels/prisms and a coordinated donut sheen. Material animations are clipped inside decorative chart surfaces; source labels, axes, tables and hit targets keep stable geometry. Large decorative floors no longer cross KPI/readout content.
- Separate desktop, tablet, landscape and narrow-phone compositions. Caption/agenda clearance was corrected after measured overlap on 900 px and 320 px screens. Scope choices remain native radio inputs within labelled cards; topic choices remain checkboxes.

## Files changed in this pass

- `resources/views/presentation/segments.blade.php`: launcher structure, topic artwork, agenda and fullscreen/status controls.
- `resources/js/presentation/segments/main.js`: live agenda/scope caption, optional fullscreen, restrained cover-map arrangement.
- `resources/js/presentation/segments/presentation-room.js`: topic marker, resize/reduced-motion lifecycle, room identity and fitted decorative branch silhouette.
- `resources/js/presentation/segments/polygon-journey.js`: owned veil/identity sequencing.
- `resources/js/presentation/segments/motion.js`: active reading-section label.
- `resources/css/presentation/segments/segments.css`: launcher, scope/topic cards, top navigation and responsive spacing.
- `resources/css/presentation/segments/branch-room.css`: threshold composition, active topic marker and room identity.
- `resources/js/presentation/segments/expressive-motion.js` and `resources/css/presentation/segments/expressive-motion.css`: clipped material reflections, easing and non-obstructing support surfaces.
- `tests/Frontend/presentation-expressive-motion.test.mjs`: reflection lifecycle/source-preservation coverage.
- `STUDIO-BRIEF.md`, this report, generated knowledge-graph artifacts and production Vite output.

No report formula, API, schema, dependency, source import or authentication behavior was changed in this pass. Existing unrelated worktree changes were preserved.

## Executed checks

| Check | Result |
| --- | --- |
| Full frontend suite, `node --test tests/Frontend/*.test.mjs` | 132 passed / 133; one previously documented sector-model mismatch remains |
| `php artisan test --filter=PresentationScrollLayoutTest` | 13 tests, 151 assertions passed |
| Syntax checks for five edited JavaScript modules; PostCSS parse for three edited stylesheets | Passed |
| Browser composition and seven-topic isolation | 51 checks passed |
| Browser financial-content responsiveness | 88 checks passed |
| Browser navigation races and interrupted requests | 10 checks passed |
| Browser actual 3D selection and Kinerja interactions | 60 checks passed |
| New launcher/agenda/fullscreen/portal/material/cursor checks | 57 checks passed |
| Renderer/source-export reconciliation | 7,584 assertions passed across 20 cases |
| Production Vite build | Exit 0 |
| Fixture / production asset SHA-256 parity | All 11 manifest assets matched |
| `git diff --check` for touched tracked source files | No whitespace errors; Git reports existing line-ending normalization notices |
| `php artisan knowledge:graph --build`, followed by `--check` | Rebuilt and current |

Browser total: **266 passing assertions**, no uncaught page errors. Chromium contexts cover 1440×1000, 1440×900, 1366×768, 1040×850, 900×1000, 844×390, 390×844 and 320×740. This includes touch-emulated profiles, reduced motion, forced unavailable WebGL, keyboard selection, responsive resize, cancelled requests, preserved office context, native fullscreen entry/exit/refusal, and local material clipping. Repeated checks were only run after correcting measured layout or automation issues.

Initial studio checks found caption/agenda overlap on tablet/narrow-phone layouts; these were fixed and the final 57 checks passed. Initial automation attempted to click the visually covered radio input directly; the final test clicks the visible labelled scope card. An incorrect Area-button selector on the performance map was replaced by its keyboard-accessible polygon choice. These fixture changes did not alter application logic.

## Evidence and limits

Artifacts are under `storage/framework/testing/asix-presentation-studio/`: `composition-result.json`, `responsive-result.json`, `races-result.json`, `desktop-result.json`, `studio-result-final.json`, `source-reconciliation.json`, `final-build-parity.json`, the runnable browser checks and screenshots. The earlier `studio-result.json` records the initial failures and is not the final result.

Useful screenshots: `studio-launcher-1440.png`, `studio-launcher-320.png`, `studio-portal-madiun.png`, `studio-savings-reading.png`, `studio-phone-recovery.png`, and the seven-topic responsive/composition captures.

The temporary loopback fixture renders current Blade and serves production-equivalent assets with previously verified runtime exports. This is **not** an authenticated production browser run or a new database export. Source periods remain those of the supplied exports: financial position 26 October 2026, Data PH 25 October, SSA CRAS 30 September and Marketshare May 2026. Reconciliation verifies rendering against these exports, not current database freshness or independent business formulas. No production authentication bypass was installed.

Most browser runs use unchanged asset bytes (`observer=off`). The 60-check real 3D picking test exposes the existing studio instance through a read-only test reference; it does not replace rendering or selection methods. SHA-256 parity confirms the final production assets.

The unrelated pre-existing `tests/Frontend/presentation-sector-model.test.mjs:50` test still expects 75 where the existing population-weighted allocation returns 60, also recorded in [COMPOSITION-VALIDATION.md](COMPOSITION-VALIDATION.md). Its formula and expectation were not changed to force a green suite.

The build reports the existing large Three.js chunk and outdated Browserslist dataset warnings. No dependency changes were made. Physical devices, Safari and Firefox, a signed-in production session, and GPU frame-time profiling remain unvalidated.

Final production entries: `main-C8fQ8dI1.js` and `presentation-segments-BM5m9hwH.css`.
