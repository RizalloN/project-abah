# Refinement of the existing presentation

## Scope

User direction: "untuk yang disajikan sudah oke sempurnakan dari segi ui dan uxnya" using Scroll World and Scroll Craft. Preserve content, financial calculations, authorization, report/export behavior and the current Laravel/Blade engine. This is an implementation refinement, not a replacement landing page. Creative decisions below are authored design choices, not invented user quotations.

## Direction

Retain the live executive atlas grammar and existing Geist type. Palette: navy #071526, deeper canvas #061322, ice #bddbff, blue #0058d0, porcelain #f4f6fa. Geography is the signature object; controls and financial reading planes stay stationary. Prefer depth through an actual rendered map and structural hierarchy rather than more moving decoration.

Journey and feeling: orient at the atlas (confident) → choose territory and agenda (in control) → enter a real selected branch (focused visual peak) → read exact financial figures (calm) → move to the next topic or return to the agenda (resolved). Preserve the existing deliberate branch-entry peak rather than inserting competing cinematic sequences.

Adapt Scroll World's composed camera stops and spatial continuity to existing live geometry. Adapt Scroll Craft's rhythm, grounded typography and separate mobile composition to the existing presenter workflow. No paid image/video generation or new engine/dependency is needed. Existing content and chapter order are accepted; do not repeat a new-site interview or fingerprint a second site over the same application.

## Ownership and acceptance

Frontend owns the launcher/visual finishing layer. Manager owns focus behavior and integration. Independent reviewer checks the final changes after implementation.

Acceptance: readable and cohesive launcher; selected scope/topics unmistakable without color alone; no horizontal document overflow on desktop, phone or short landscape; visible usable focus; at least 44px touch actions; no focus stranded inside a closed topic chooser; no focus stolen from outside controls; reduced motion and selectable no-WebGL fallback preserved; no financial/data behavior edits. Verify with real tests, an isolated asset build and browser interaction against the existing offline view fixture. Do not equate that fixture with an authenticated live-data audit.

## Initial checks

- Fresh git status inspected; existing modified and untracked work retained.
- Knowledge graph check reports stale. No generated graph rebuild is needed for this UI-only task; source files and tests remain authoritative.
- Baseline focused frontend checks: 22 passed.
- Added chooser-focus regression first: failed with 0 focus restorations instead of 1; minimal fix then yielded 23 focused frontend tests passing.
- Broad frontend run: 139 tests, 138 passed, 1 failure in the untouched sector model test (60 versus expected 75). The same mismatch is documented in the previous presenter-polish validation. No financial rule changed to conceal it.
- Pest PresentationScrollLayoutTest: 13 passed, 151 assertions.

## Integrated execution evidence

- Isolated build passed using `node node_modules/vite/bin/vite.js build --outDir storage/framework/testing/asix-presentation/public/build`. `npx vite` was rejected by the terminal's process classifier; calling the installed Vite entry directly completed in 14.74 seconds. No production asset replacement.
- Current Blade fixture refreshed with `php -d max_execution_time=0 storage/framework/testing/asix-presentation/render.php --views-only`, without re-exporting financial data. Browser loaded the new `presentation-segments-D14JAWqR.css` bundle.
- Final focused frontend run: 23 passed. Final PHP layout run: 13 passed, 151 assertions. Scoped tracked-source diff check passed.
- Desktop 1440x900 and phone 390x844 launcher screenshots visually inspected. Also measured 320x568, 768x900 and 1280x640; no horizontal document overflow. On short desktop the 140px map stage and agenda ending at y=612 fit the composition rather than colliding with headline or context.
- Phone interaction: empty agenda disables start; compose moves focus to the configuration heading; selecting Wilayah updates all three choices and 3/3 count and enables start.
- Started a four-topic agenda: exactly one room visible, no document overflow; topic chooser is 44px high. ArrowDown moves focus to Mapping without activating it. Closing the chooser returns focus to its trigger. Returning to the launcher retains all four selected topics and focuses the configuration heading at y=136.16.
- Reduced-motion plus deliberately unavailable WebGL: launcher ready, motion class absent, SVG fallback present, no document overflow.

## Independent review and final correction

Independent Auditor review found no blockers, but reproduced an existing offscreen-focus issue when opening the last topic on a 320x568 phone with all seven topics. A regression test was added and observed failing before the fix. Opening the chooser now focuses and scrolls the current topic into the nearest visible area. Final focused suite: 24 passed, zero failures. Rebuilt isolated assets and refreshed the view fixture after the correction. Browser reproduction navigated through all seven topics to Recovery DH: focused topic fully visible, chooser scrollTop=374, no document overflow; Escape closes the chooser and restores trigger focus.

Browser checks use a local offline Blade fixture with previously exported source payloads, not an authenticated live application or fresh financial reconciliation. Physical phone/projector behavior is not established. Existing build warnings remain: stale Browserslist dataset and a >500kB Three.js-related chunk. Knowledge graph was already stale; not rewritten for this UI-only change. Production was not deployed.
