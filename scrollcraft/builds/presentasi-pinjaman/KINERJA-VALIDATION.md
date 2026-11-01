# Kinerja presentation validation — 27 October 2026

## Implemented behavior

Simpanan, Pinjaman, Kualitas and Recovery DH now use the same explicit
branch-room experience as the three geographic chapters. Selecting a polygon
loads its actual KC snapshot, zooms to the branch, crosses a named identity
overlay and enters one dedicated report. Back reverses that journey to the map.
Scrolling cannot enter another topic. Area consolidation remains an explicit
action and uses the supplied Area snapshot, not a sum of displayed figures.

Each financial room has its own material palette and decorative sculpture.
Composition layers assemble in depth, chart lines reveal, ranking fills enter
at their actual source proportions, and local hover/focus lighting provides
feedback. All use the existing animation owners and shared reading clock.
Amounts, labels, table rows and financial chart coordinates retain their source
values and reading geometry. Reduced motion settles the view immediately.

Reading waypoints move to composition, history and entity details without
adding browser-history entries. Kualitas also has a segment/product waypoint.
Narrow screens keep readable chart axes in a labelled, keyboard-accessible
horizontal region. KC labels are packed inside the visible map, below the
persistent header; their targets stay separate in portrait and landscape.

Loading can be cancelled or retried. Old responses cannot overwrite a newer
KC choice, including a choice made during asynchronous history restoration.
No financial formula, endpoint, database, dependency, authentication middleware,
import or report deletion behavior was changed for this task.

## Files changed for this extension

- `resources/js/presentation/segments/performance-journey.js`: four financial gateways, scoped loading, recovery and identity.
- `resources/js/presentation/segments/main.js`: lifecycle, renderer and restoration ownership wiring.
- `resources/js/presentation/segments/polygon-journey.js`: reusable chapter keys and pending cancellation.
- `resources/js/presentation/segments/presentation-room.js`: Kinerja map/detail history, section controls and themed overlay.
- `resources/js/presentation/segments/render-kinerja.js`: financial room content, waypoints, overview labels and source coverage.
- `resources/js/presentation/segments/charts.js`: optional labelled scrollable trend region, enabled only by Kinerja renderers.
- `resources/js/presentation/segments/motion.js` and `expressive-motion.js`: active waypoint and bounded financial material sequences.
- `resources/js/presentation/worlds/territory-studio.js`: scoped narrow-screen KC label placement and header clearance.
- `resources/css/presentation/segments/performance-room.css` and the presentation CSS entry: scoped financial materials, responsive charts and reduced motion.
- `tests/Frontend/presentation-performance.test.mjs`, `presentation-room.test.mjs`, `presentation-expressive-motion.test.mjs`: lifecycle and material regressions.
- `KINERJA-ROOM.md`, this report, and regenerated knowledge-graph artifacts.

Pre-existing and concurrent unrelated changes in the working tree were retained.

## Validation results

- 95 focused frontend tests pass. The full frontend suite is 111/112 passing.
  Its existing unrelated failure is `presentation-sector-model.test.mjs:50`,
  expected 75 and actual 60 for owned village population. Neither that business
  logic nor its expectation was changed for this Kinerja implementation.
- `PresentationSegmentDataServiceTest` and `PresentationScrollLayoutTest`:
  25 passing tests, 229 assertions.
- Source-renderer reconciliation: 20 cases (four topics × Area plus four KCs),
  6,356 assertions. It checks 458 displayed entity rows, products, ratios,
  ranking values, 840 underlying series points, KC map labels and immutable
  payloads. Two zero-OS quality rows remain excluded by the existing OS > 0
  rule; those exclusions are recorded explicitly.
- Desktop checks: 56 pass, including actual Three.js polygon selection,
  identity overlay, every displayed KC Madiun row, hover/series controls,
  source-proportional ranking bars, reverse scroll and Back behavior.
- Lifecycle checks: 18 pass, covering delayed history vs a new polygon,
  cancellation, synthetic network failure/retry, Area consolidation and all
  four topics under branch-authorized fixture scope.
- Final original-byte browser regression: 25 pass across all seven topics,
  including source identity, single-topic scroll isolation and Back/Forward.
- Final device follow-up: 114 pass across 390×844 touch, 844×390 touch landscape
  and 320×740 reduced-motion SVG fallback. Checks include physical label hit
  testing, no collisions/header occlusion, actual rendered axis text heights,
  trusted touch swipe/keyboard chart scrolling, last-period reachability,
  exact KC headlines, series controls and Back. No uncaught errors occurred.
- JavaScript syntax and scoped whitespace checks pass. Vite isolated and
  application builds pass. Knowledge graph rebuilt and check passes.

Visual review identified and corrected a new rod-fill positioning override,
phone SVG text shrinking below readable size, and phone/landscape KC label
collisions. Browser assertions were strengthened to measure real fill/label
geometry and hit targets rather than relying on computed font sizes alone.

## Build and evidence

Final application entries are `main-Agk9nd7F.js` and
`presentation-segments-Z3ODNVJF.css`. All five entry/transitive assets in the
isolated fixture match the application build byte-for-byte by SHA-256.

Evidence is under `storage/framework/testing/asix-presentation-performance/`:

- `desktop-verified-results.json`, `lifecycle-results.json`
- `mobile-verified-results.json`, `regression-final-results.json`
- `source-reconciliation.mjs`, `source-reconciliation.json`
- `final-build-parity.json`, `runtime-auth-probe.json`
- frontend/PHP/build logs and desktop/mobile PNG screenshots.

The final mobile and regression runs use original uninstrumented assets.
Desktop raycast inspection uses a read-only observer of the existing studio;
it does not change application methods or state. Earlier intermediate results
and baseline files are preserved as evidence of issues found and corrected.

## Limits

The fixture renders the actual application source with existing read-only
exports for 25 October 2026; this is a rendering/source-preservation audit,
not a fresh database or external spreadsheet reconciliation. The live URL
`http://127.0.0.1/dashboard/presentation` redirects the available browser to
login. Authenticated live-session validation remains unavailable; no bypass
was added. Device checks use Chromium emulation, not physical phones or Safari.
Existing Browserslist-age and large Three.js chunk build warnings remain.

The owned temporary loopback fixture server on port 4637 was stopped after
validation; no listener remained on that port.
