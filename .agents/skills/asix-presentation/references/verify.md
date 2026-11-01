# Verification

Run commands from the repository root using its installed PHP/Node tools. This
guide distinguishes source checks, synthetic browser fixtures, live source
reconciliation, and an authenticated application session.

## Focused commands

```powershell
php -d max_execution_time=0 vendor/bin/pest tests/Unit/PresentationSegmentDataServiceTest.php tests/Unit/PresentationScrollLayoutTest.php
php -d max_execution_time=0 vendor/bin/pest tests/Feature/CrasMappingDashboardTest.php
node --test tests/Frontend/asix-presentation.test.mjs
node --test tests/Frontend/presentation-polygon.test.mjs
node --test tests/Frontend/presentation-panel-transition.test.mjs
node --test tests/Frontend/presentation-reading-motion.test.mjs
node --test tests/Frontend/presentation-expressive-motion.test.mjs
node --test tests/Frontend/presentation-cinema.test.mjs
node --check resources/js/presentation/segments/main.js
node --check resources/js/presentation/segments/render-wilayah.js
node --check resources/js/presentation/segments/map-controls.js
node --check resources/js/presentation/segments/cras-subsectors.js
node --check resources/js/presentation/segments/choice-panel.js
node --check resources/js/presentation/segments/panel-transition.js
node --check resources/js/presentation/segments/polygon-journey.js
node --check resources/js/presentation/segments/reading-clock.js
node --check resources/js/presentation/segments/expressive-motion.js
node --test tests/Frontend/presentation-sector-model.test.mjs tests/Frontend/presentation-subsector-coverage.test.mjs tests/Frontend/presentation-atlas.test.mjs
php -d max_execution_time=0 vendor/bin/pest tests/Unit/SectorPotentialServiceTest.php
git diff --check
```

Run CRAS tests when changing CRAS wiring; include PHP syntax checks for PHP files
changed in that task. Scope diff checks if unrelated existing changes produce
findings. Report them accurately rather than modifying unrelated work.

For an isolated asset build:

```powershell
npx.cmd vite build --outDir storage/framework/testing/asix-presentation/public/build
```

That writes a separate build tree. Do not add `--emptyOutDir` to a computed path
without first verifying the absolute target. `npm.cmd run build` writes the
application's `public/build`; use it when deployment of the authorized UI changes
is part of the task, after review of the isolated result. Do not leave `public/hot`
from a development server in the Apache application.

## Browser and data checks

The task harness lives in `storage/framework/testing/asix-presentation/`:

```powershell
php -d max_execution_time=0 storage/framework/testing/asix-presentation/render.php
node storage/framework/testing/asix-presentation/server.mjs
```

`render.php` exports actual source payloads with process-only array caching and
renders the current Blade view against the isolated manifest. `server.mjs`
serves the fixture at `http://127.0.0.1:4626`. Inspect these scripts and verify
the port is free before reuse; rebuilding asset hashes requires a fresh render.
Use current source periods instead of assuming the original fixture date is still
current. Store screenshots/results in this directory and classify each run as an
offline view fixture, even when its payloads were exported from the real database.
Any added mocks must be disclosed separately from actual-data validation.

Verify at a desktop size such as 1440x900 and a phone size such as 390x844,
including reduced motion, no WebGL, and a failed/empty data response:

- The user can select one, several, or all chapters, and cannot start an empty
  selection. Scope/period survive a return to the launcher and a shared URL.
  On phones, verify that an empty agenda's disabled action does not cover the
  form, the topic chooser remains visible, and returning from a room reveals
  the focused configuration heading. Use the measured room-header offset for
  anchor targets. Check chooser arrows/Home/End move focus without activation,
  Enter/Space activate explicitly, and Escape returns focus to its trigger.
- Area presents branch rows; a branch presents only its outlets. All source
  totals and the effective source period match their tables, charts, and labels.
- A branch user's server responses exclude other branches even when the URL is
  tampered with. Verify the endpoint response, not just the hidden options.
- The geographic chapters have no branch/unit dropdowns. Explicit polygon
  activation by mouse, keyboard, and touch opens the selected source-backed
  details; automatic movement does not. Reset closes them and keeps backwards
  navigation usable. Shared polygons offer stable unit pins or a labelled
  service-area group. Outside-branch picking cannot change authorized scope.
- Blur affects only the moving map and settles to zero. Reduced motion changes
  focus instantly and retains every usable control and value.
- Hover leaves map orientation and cards stationary, including launcher and the
  decorative orbit layer. First polygon activation names its KC before unit
  drilldown in both Area and branch scope. Verify overlay, camera bounds, three
  scroll points, synchronized branch data, rapid branch/district selection,
  reverse scroll, and reset. There are no modals or native selects. Searchable
  CRAS choices preserve exact source values and return-to-map preserves filters.
- Each geographic chapter has exactly one stable map/detail view. Explicit KC
  selection names and zooms the branch before the map exits left and detail
  enters from the right. The back action reverses it; selected rail/top-nav
  links open detail directly. Verify the live summary nodes retain their exact
  values and return to the map instead of duplicating or losing callbacks.
- Interrupt both transition directions with another chapter, reset, a new
  selection, the launcher, and a deck rebuild. There must be one transition
  owner, no delayed re-entry, no animation fills or busy/absolute-height state
  left behind, and no stale world/labels on detail. Hidden or detached map slots
  must not reach bounding-box/projection code. A long detail must not change
  the outgoing map's framing during the handoff. Check desktop, short landscape,
  phone, keyboard, reduced motion and the selectable SVG fallback.
- CRAS sector/subsector choices use real endpoint options; parent changes reset
  dependent selection; rapid changes cannot apply an older response. Missing
  fine-subsector potential stays unavailable. Derived coverage uses the approved
  parent sector's population in the selected UKER territory, exact raw counts,
  and a disclosed 100% cap. Verify a zero-customer finer filter keeps the fixed
  denominator, and selected owned villages are counted once in totals. Reconcile
  the full current Google Sheet and September SSA, including category unions,
  both source-ID/hash provenance, qualified village ownership, active/PH splits
  including OTHER, excluded Consumer/Konsumer, missing population/unowned UKER,
  missing branches and persisted snapshot roundtrip. Use the supplied workbook
  as a complete numeric benchmark, without hardcoding ranking/proxy scores or
  rounding source credit values to force agreement with whole-rupiah displays.
- Donut legends and timeseries choices respond, null periods remain gaps, and
  table headers/rows remain readable. Check horizontal overflow, clipped numbers,
  nested scroll containers, runtime/WebGL errors, and loading/error recovery.
- Compare the same reading positions after downward and upward scroll. Exact
  numeric text, table row/cell rectangles, axes, bubble centers/hit targets and
  source chart proportions must agree. Entry remains complete after settling;
  only decorative passage reverses. Financial cards, rows and cells have no
  perspective or independent transforms. Parent sections settling cannot finish
  child material early. Verify local visual bounds without clipping glass rims,
  source labels, focus outlines or expanded exact-data disclosures.
- Exercise focused charts, reduced motion, hidden/inert panels, replacement
  filter results and disclosure opening. Hidden/detached/transitioning content
  is not measured; visible replacement content receives fresh measurements and
  its own entry latch. Check for transformed-geometry feedback during scrolling
  and for old motion selectors reapplying time-based fades or row transforms.
- Rebuilding the deck, reversing scroll, and changing chapters leave one current
  map scene with no duplicate handlers or detached content receiving updates.

For the finishing film, shoot the flight through its hand-off, sample visible
label text on every frame of a room change (only the new room's figures may
appear), measure that the act rail overlaps no tile at any scroll depth, and check
a real wheel notch passes through intermediate positions. Skip the flight early
and late, reach the closing room, and use browser back and forward from it.

Capture opening, middle, and settled frames for map transitions plus representative
chart/table states. Read the screenshots; a success JSON alone cannot establish
visual quality. Repeat interaction checks after the final changes.

For expressive material, include a large real wheel jump that takes reading
entry directly to 1. The timed assembly must remain visible for roughly 1-2
seconds. Capture with Web Animations allowed (do not fast-forward them using a
screenshot's animations-disabled option). Check real donut wall/fan depth as
well as matrices, flat unchanged source labels/axes/cells, rapid replay ownership,
offscreen/disposal cancellation and dynamic reduced motion.

If browser navigation redirects to login, record that limitation. An offline
render proves the fixture behavior; it does not prove an authenticated live page,
actual phone behavior, or actual source totals. Do not bypass authentication or
use unrestricted cache flushes to obtain a passing fixture.

## Validate this skill package

```powershell
python C:/Users/msi/.codex/skills/.system/skill-creator/scripts/quick_validate.py .agents/skills/asix-presentation
```

Check that every linked reference exists. For substantial skill changes, forward
test a realistic presentation request against the skill and current sources in
a read-only or isolated evaluation. Judge the resulting scope/data decisions,
not whether the output repeats these instructions.

The official validator imports PyYAML. If the selected Python environment lacks
it, report that failure; do not change application dependencies to make a skill
validator run. The repository's existing `Symfony\Component\Yaml\Yaml` parser can
independently validate YAML, followed by name/description/reference checks, but
that is a separate validation result from running the official script.
