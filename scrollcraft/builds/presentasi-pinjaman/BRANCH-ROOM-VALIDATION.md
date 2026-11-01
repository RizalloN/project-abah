# Branch room validation — 27 October 2026

Implemented on Landing Pinjaman > Mode Presentasi using the project-adapted
asix-presentation, scroll-craft and scroll-world approach. The live geographic
camera and actual source polygons replace a video flythrough. Financial sources,
formulas, access rules and API routes were not changed in this rebuild.

## Result

- A polygon choice zooms to its KC, shows a full-frame navy identity threshold
  with the selected polygon silhouette, then opens that topic's detail page.
- Back reverses the threshold and restores the map. Escape can reverse an
  entrance; changing motion preference settles the requested page immediately.
- Only one selected topic has layout or keyboard access. Scrolling reads that
  topic, with explicit controls to return or choose another subject.
- The persistent header retains KC/topic identity, Back and a topic picker.
  Native browser Back/Forward restore stable presentation states.
- Unit exploration remains explicit and source-backed. A shared Kartoharjo
  polygon exposed three unit pins; selecting BALE LINTANG kept KC Madiun context.
- Existing material scroll/hover choreography is suspended during the handoff,
  then rescanned in the visible page. Financial text and table geometry stay
  stable. Hidden chapters never compete for the camera or reading clock.

## Files changed in this rebuild

- `resources/js/presentation/segments/presentation-room.js` — page/history owner.
- `resources/js/presentation/segments/main.js` — launcher and topic wiring.
- `resources/js/presentation/segments/polygon-journey.js` — reversible entry,
  actual branch silhouette, reset, unit exploration, cancellation/recovery.
- `resources/js/presentation/worlds/territory-studio.js` — camera settlement API.
- `resources/js/presentation/segments/motion.js`, `reading-clock.js`,
  `expressive-motion.js`, `interaction-motion.js` — active-room/header guards.
- `resources/css/presentation/presentation-segments.css` and
  `resources/css/presentation/segments/branch-room.css` — bounded responsive UI.
- `tests/Frontend/presentation-room.test.mjs` — eight behavioral regressions.
- This report and `BRANCH-ROOM.md` — implementation and validation evidence.

Existing unrelated worktree changes were retained. No dependency, schema,
authentication middleware, financial source or import/delete code was changed.

## Validation performed

- **68/68 focused JavaScript tests passed**, including eight new tests for
  history, isolation, cancellation, camera convergence and the SVG fallback.
- **25/25 PHP tests passed (229 assertions)**:
  `PresentationSegmentDataServiceTest` and `PresentationScrollLayoutTest`.
- **75/75 final browser checks passed**, across 1440×900 desktop, 390×844 touch,
  844×390 landscape, 320×740 reduced-motion SVG and 390×844 touch SVG.
- Browser coverage includes actual Three.js raycast polygon clicks, keyboard,
  touch, KC identity, camera settlement, three geographic topics, branch/unit
  drilldown, scroll isolation, explicit topic picker, Back/Forward, repeated
  choice, interrupted navigation, Escape, dynamic reduced motion and deck rebuild.
- An expanded source table was measured with nonzero row bounds. Its exact
  text and row sizes matched after scrolling away and back. No horizontal body
  overflow, native select, popup or uncaught browser error occurred in these runs.
- A final **uninstrumented** asset smoke test opened detail and returned to the
  map successfully; the test-only studio observer was absent.
- JavaScript syntax, targeted whitespace checks and the knowledge-graph check
  passed. Isolated and application Vite builds succeeded.

The full frontend suite is **97/98 passing**. The pre-existing failure remains
in `presentation-sector-model.test.mjs:50`: expected 75, actual 60 for owned
village population. It also existed before this navigation rebuild. Its financial
implementation and expectation were not changed to make this task's checks pass.

## Asset and data provenance

Final application entries:

- `public/build/assets/main-CQkPf5NY.js`
- `public/build/assets/presentation-segments-DeZfA0CY.css`

All **five** entry/transitive assets in the isolated fixture are byte-identical
to the application build, verified by SHA-256 in
`storage/framework/testing/asix-presentation-room/final-build-parity.json`.

The isolated Blade fixture uses read-only actual source exports from the prior
village fixture (15 exported files, eight CRAS cases; selected period 25 October
2026). It does not silently substitute unknown CRAS requests or fabricate new
financial data. This is rendered-view testing, not a fresh source reconciliation.

Evidence lives under `storage/framework/testing/asix-presentation-room/`:
`desktop-final-results.json`, `device-final-results.json`,
`interruption-final-results.json`, `final-build-parity.json`, test logs and PNGs.
Earlier baseline evidence demonstrates scroll leakage into the following topic
and missing detail history before this rebuild; its files were preserved.

## Remaining limits

The real application URL redirects the available browser to login. No
authenticated live application session was available; no authentication bypass
was added. Device checks use Chromium viewport/touch emulation, not physical
phones or Safari. Build warnings about Browserslist age and the existing large
Three.js chunk remain. Reduced motion and SVG fallback were tested separately;
frame-rate guarantees on all hardware are not claimed.

The temporary loopback fixture server on port 4636 was stopped after validation.
Its Node process identity, creation time and listener were checked before stopping;
the port had no remaining listener afterward.
