# Presentation composition and presenter controls

Completed 28 October 2026. Scope: the loan landing page's existing Mode Presentasi. Applies asix-presentation, Scroll Craft and Scroll World to the existing live geometry and chart materials.

## Result

- Marketshare displays Simpanan on the left and Pinjaman on the right simultaneously. Each side contains its source total, four ordered product vessels, and source-derived product/share-change highlights. The former category toggle is gone. Below 900px the same content stacks in reading order.
- The seven-topic route remains visible, with the active topic, selected office, Back, desktop previous/next controls and a keyboard-accessible topic chooser. Moving between details retains the selected KC/Area and drops unrelated unit/subsector context. Moving between maps does not imply a new office choice.
- Available content width increases to 1680px. Redundant detail titles, large gaps and repeated navigation are reduced. Mapping's largest/smallest opportunity cards sit beside each other on wide screens; performance section shortcuts and data summaries use tighter spacing.
- Topic arrivals have cancellable directional motion. Existing polygon zoom, named office overlay, chart assembly, hover and reverse-scroll behavior remain. Reduced motion bypasses the new spatial transition.
- Delayed requests retain their office intent across rapid topic changes; stale responses and cancelled requests cannot replace the active topic. Keyboard focus returns to a visible title when the duplicate detail title is hidden.
- Browser testing exposed a settled chart returning to its unrevealed CSS state after room changes. The animation owner now also commits the chart's reveal state, so finished bars retain their actual source proportions even if an intersection notification was missed.

Business formulas, endpoints, source data, database schema and authentication were not changed in this iteration. Unrelated local edits were preserved.

## Files changed in this iteration

- `resources/js/presentation/segments/render-wilayah.js`: concurrent Marketshare composition and source-derived insights.
- `resources/js/presentation/segments/presentation-room.js`: topic route, keyboard controls and owned arrival animation.
- `resources/js/presentation/segments/main.js`: branch context across topic navigation, guarded pending context and visible focus.
- `resources/js/presentation/segments/polygon-journey.js`: visible focus after polygon entry.
- `resources/js/presentation/segments/motion.js`: settled charts retain their reveal state.
- `resources/css/presentation/segments/atlas.css`, `branch-room.css`, `performance-room.css`: comparison layout, presenter navigation, spacing and responsive composition.
- `tests/Frontend/presentation-marketshare.test.mjs`, `presentation-room.test.mjs`: concurrent source totals/products, missing data, source-ranked insights and branch context.
- `COMPOSITION-BRIEF.md` and this validation record. Knowledge-graph artifacts were regenerated using the project command.

Production build: `main-tSzIytp3.js`, `presentation-segments-DWDZMyTh.css`.

## Validation

Evidence directory: `storage/framework/testing/asix-presentation-composition/`.

| Check | Result | Evidence |
| --- | --- | --- |
| Marketshare/room/performance/transition unit tests | 37 passed | Root command output |
| Reading/expressive/interaction motion unit tests after reveal fix | 36 passed | Root command output |
| Full frontend suite after final change | 130/131 passed; one existing sector-model failure | `final-frontend.txt` |
| PresentationScrollLayoutTest | 13 passed, 151 assertions | `validation-agent-php.txt` |
| Modified JavaScript syntax and three CSS files parsed with PostCSS | Passed | Lead/reviewer command output |
| Renderer against verified source exports | 7,584 assertions passed, 20 scope/chapter cases, 458 eligible entity rows and 800 history points | `source-reconciliation.json` |
| Comparison layout, source shares, all seven topics, KC continuity, Back and keyboard | 51 browser checks passed | `browser-composition.json` |
| Responsive, touch, reduced motion, SVG fallback, tables and all topics | 88 browser checks passed | `browser-responsive.json` |
| Delayed request, rapid topic replacement, Back cancellation and animation cleanup | 10 browser checks passed | `browser-races.json` |
| Real 3D polygon click, overlay, four Kinerja rooms, hover, scroll animation and complete displayed unit rows | 60 browser checks passed after reveal fix | `browser-desktop.json` |
| Suppressed tile intersection notifications | Visible bars retain source proportions after assembly | `intersection-regression.json` |
| Final original-asset browser smoke at 1920x1080 | Simultaneous columns, source totals, hover readout, no horizontal overflow | `final-browser-smoke.json` |
| Production Vite build | Passed; all 11 manifest assets match tested isolated build byte for byte | `final-build-parity.json` |
| Scoped diff whitespace check; knowledge graph | Passed; graph current after rebuild | Command output |

Browser dimensions covered: 1920x1080, 1440x1000, 1440x900, 1366x768, touch 390x844, touch landscape 844x390 and reduced-motion/no-WebGL 320x740. No uncaught browser errors occurred in the passing runs.

The first desktop run exposed the empty-bar issue (`browser-desktop-before-settle-fix.json` and `desktop-failure.png`). After the narrow animation-state fix, the full desktop sequence passed and a separate fault-injection check suppressed tile intersection callbacks while confirming the final bar proportions.

Visual evidence: `final-market-1920.png`, `market-desktop-refined.png`, `final-desktop-mapping-ranks.png`, `final-desktop-simpanan.png`, `phone320-svg-market.png`, `phone390-simpanan-table.png`, and the four `*-composition.png` / `*-trend.png` performance captures.

## Limits

- The existing `tests/Frontend/presentation-sector-model.test.mjs:50` assertion remains failing: the current allocation returns 60 and its older expectation is 75. This layout change does not modify that allocation formula or its test.
- Browser validation uses current Blade and compiled assets with the previously verified runtime exports from the earlier corrections run. These are real exported figures, not a fresh database reconciliation on 28 October. `source-reconciliation.json` explicitly records `currentDatabaseExport: false`.
- No authenticated production browser session or physical device was tested. Most browser tests serve original asset bytes with `?observer=off`. Only the real 3D hit-target test adds a read-only `window.__asixStudio` assignment to the existing constructor result; it does not change methods or state. Asset parity verifies the final production bytes.
- Vite retains its existing large-chunk and old Browserslist-data warnings. No dependencies or configuration were changed.
- Final operational check found no listener on the owned temporary fixture port 4641.
