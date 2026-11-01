# Presentation corrections — 27 October 2026

## Delivered behavior

- Every geographic and performance gateway offers an explicit Area 6 consolidation action. An authorized Area user who starts in a KC scope can return to Area without losing the chosen chapters. Server scope resolution remains in force.
- Marketshare has two tabs, Simpanan and Pinjaman. A separate source total precedes four product vessels: Tabungan/Giro/Deposito/CASA, or UMKM/Konsumer/BRIGUNA/KPR. Products are not added to fabricate the total; overlapping categories are disclosed. The selected category survives returning to the map.
- Mapping shows separate largest and smallest opportunity cards, ten UKER each where the source permits. Small scopes do not repeat the same UKER in both cards. Bubble axes and size interpretation remain visible.
- CRAS uses owned-village potential for the selected parent category. Unfiltered results explicitly use all sectors. Missing/ambiguous reference populations remain unavailable; real zero values are retained. Ratios are bounded to 100% with the raw ratio preserved and normalization disclosed.
- Savings and loans retain one entity/growth table, with nominal YoY, YtD and MtD deltas and actual comparison dates. Savings adds a separate CASA detail below it. Both money charts start at zero so small decreases no longer occupy the whole chart height; missing points stay distinct from zero.
- The quality gateway opens on SML and changes to NPL along a bounded scroll track. Direct buttons remain available, including reduced motion and short landscape viewports. Entity columns are Unit, SML amount, SML ratio MtD/current, NPL amount, NPL ratio MtD/current. MtD is the actual previous-month ratio, not a percentage-point delta.
- Recovery now uses the existing Data PH calculation: LW325 principal movement, closed accounts and new claims, with the report's Cognos fallback. Consumer includes Briguna and KPR. Its own source date, monthly series and actual comparison dates are displayed separately from the dashboard snapshot.
- Existing polygon zoom, named KC overlay, dedicated detail room, reverse Back animation, chart material assemblies and hover feedback are retained. Financial text and table geometry stay stable. A mobile quality-layout overlap found during verification was corrected.

## Files for this correction

Application:

- `app/Services/Presentation/PresentationSegmentDataService.php`: entity deltas, prior quality ratios and Data PH Recovery payload.
- `app/Http/Controllers/DashboardSimpananController.php`: presentation cache namespace only, `v3-data-ph`, avoiding reuse of the old Recovery payload.
- `resources/js/presentation/segments/main.js`, `polygon-selection.js`, `polygon-journey.js`: authorized Area selection and map/detail wiring.
- `resources/js/presentation/segments/performance-journey.js`: quality scroll controls and Recovery source header.
- `resources/js/presentation/segments/render-kinerja.js`, `charts.js`: consolidated tables, CASA, prior ratios, source dates and zero-baseline money trends.
- `resources/js/presentation/segments/render-wilayah.js`, `atlas.js`: two Marketshare categories and separate opportunity rankings.
- `resources/js/presentation/segments/cras-subsectors.js`, `subsector-coverage.js`, `sector-model.js`: reference populations and Area selection.
- `resources/css/presentation/segments/atlas.css`, `performance-room.css`: total/product hierarchy, ranking cards and quality scroll layout.

Tests: `PresentationSegmentDataServiceTest.php`; frontend `presentation-atlas`, `presentation-marketshare`, `presentation-polygon`, `presentation-subsector-coverage`, and `presentation-trend-scale` tests. Existing presentation lifecycle tests were also run. Generated knowledge-graph artifacts were refreshed as required by AGENTS.md. Prior presentation work and unrelated dirty files were preserved. Authentication, imports, deletion, dependencies, schema and indexes were not changed.

## Source evidence

Actual local runtime exports in `storage/framework/testing/asix-presentation-corrections/data`, with source lineage in `DATA-CONTRACT.md`:

- Kinerja snapshot: 26 October 2026, Area plus four KC, 111 UKER.
- Recovery Data PH: 25 October 2026, previous-month position 30 September 2026. Area Rp11,750,454,120.98; Consumer Rp218,681,620.53 (Briguna Rp214,694,620.53 and KPR Rp3,987,000). Actual available YoY comparison is 30 April 2025; this date is explicitly shown rather than implying a missing October 2025 position.
- CRAS: 30 September 2026. All-sector Area potential 1,028,885 and 339,430 BRI debtors, 32.99%. Madiun trade parent potential 45,256; the tested LPG subsector retains that denominator with 156 debtors, 0.34%.
- Marketshare retains its source May 2026 position; it is not relabelled as October data.

## Validation results

1. PHP: 31 tests / 276 assertions passed (`PresentationSegmentDataServiceTest`, `PresentationScrollLayoutTest`).
2. Focused frontend: 67 tests passed. Full frontend suite: 128/129 passed. The pre-existing `presentation-sector-model.test.mjs:50` failure remains: population-weighted debtor allocation returns 60 while the test expects the older equal allocation value 75. No allocation formula was changed to make this unrelated assertion pass.
3. Independent actual-source audit: 14,720 assertions, zero mismatch across all 111 UKER / four KC and source snapshot rows. Evidence: `storage/framework/testing/presentation-data-ph-20261027/reconciliation.json`.
4. Complete renderer reconciliation: 7,584 assertions across 20 scope/chapter cases; 458 eligible entity rows, all CASA rows, 800 timeseries points, source figures, deltas and geographic labels. Two zero-OS quality rows remain excluded under the existing table contract. Evidence: `corrections/source-reconciliation.json`.
5. CRAS source reconciliation: 3,135 assertions; all 352 tested cohort rows have source-backed potential, no unmatched identity. Evidence: `corrections/cras-source-reconciliation.json`.
6. Desktop actual browser checks passed for all four performance rooms: real 3D polygon activation, KC overlay, exact data in all displayed unit rows, growth columns, material animation, hover, series controls, reverse scroll, Back and isolated chapters. No uncaught browser errors. Script/screens: `corrections/verify-desktop.cjs`, `*-composition.png`, `*-trend.png`.
7. Area/responsive controls: 59 checks passed across 1440x960, touch 390x844, touch 844x390 and reduced-motion/no-WebGL 320x740. All seven Area choices, KC-scoped to Area navigation, SML/NPL controls, map/copy separation, table containment and Back passed. Evidence: `corrections/browser-controls.json`.
8. Lifecycle: 18 checks passed, including delayed history responses, newer KC selection winning, network failure/retry, cancellation while loading and standalone KC entry. Evidence: `corrections/browser-lifecycle.json`.
9. Independent Marketshare/Mapping/CRAS browser review passed, including all 40 largest/smallest ranking key/value pairs, eight Madiun product figures, four Area CRAS rows and all 23 tested subsector UKER rows. Reports and inspected screenshots: `corrections/browser-marketshare-report.md`, `browser-marketshare-cras-report.md`.
10. JS syntax (19 files), PHP syntax (three paths) and scoped diff whitespace check passed. Pint passes the service/test; the existing controller still fails formatting. A read-only copy of HEAD fails the same 19 style-rule families; the working copy additionally reports existing CRLF line endings. No mass formatting was applied.
11. `npm run build` passed. Production assets `main-DP9J-7QE.js` and `presentation-segments-D-8_sx9-.css`; all 11 manifest assets match the final isolated build byte-for-byte. Original, uninstrumented assets passed the final Recovery/source-date browser smoke. Evidence: `corrections/final-build-parity.json`, `final-browser-smoke.json`.
12. Knowledge graph rebuilt after stale detection; an intermediate check was current. Final check is structurally valid but stale: the independently edited `DashboardPinjamanReportController.php` changed after the rebuild (graph records 210,610 bytes; file then had 216,237 bytes). This unrelated controller was not edited or reverted. Source/data/browser checks above are the authority for this presentation change.

`corrections/` above abbreviates `storage/framework/testing/asix-presentation-corrections/`.

## Limits and operational notes

- Browser checks use current Blade/Vite assets and actual runtime-exported payloads on a temporary loopback fixture. The live application redirects this browser to login; a logged-in production browser session and physical devices were not tested. No authentication bypass was installed.
- The read-only cold service profile took about 16 seconds for Area, mainly Data PH history aggregation. The existing scoped endpoint caches results for 15 minutes. This change does not claim to eliminate cold-load latency.
- Full-suite and controller-format findings above remain open. Build retains existing large-chunk and old Browserslist-data warnings; no dependency/configuration changes were made.
- Population source completeness and normalized ratios remain disclosed in the UI. Presentation never invents missing populations or financial history.
- The owned temporary fixture server on port 4640 was stopped; no listening socket remained after validation.
