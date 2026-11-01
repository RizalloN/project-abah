# Presentation architecture and source contracts

Start with `PROJECT_MAP.md` and `docs/knowledge-graph/README.md`. The graph is
navigation evidence; current sources and tests remain authoritative. Check
`git status --short` before editing because presentation work may already be local.

## Entrypoints and files

| Path or symbol | Responsibility |
| --- | --- |
| `routes/web.php` | Authenticated presentation and report routes |
| `DashboardSimpananController::presentation()` | Resolves period and allowed scope; default segmented view |
| `resources/views/presentation/segments.blade.php` | Chapter groups, scope launcher, `window.__SEGMENT_CONFIG__` |
| `resources/js/presentation/segments/main.js` | URL/localStorage choice, scoped data loading, deck assembly |
| `segments/render-wilayah.js` | Marketshare, mapping, sector and CRAS presentation |
| `segments/render-kinerja.js` | Savings, loans, quality, Recovery DH |
| `segments/charts.js` | Layered SVG donuts, timeseries, rods, tables |
| `segments/atlas.js` | Source-backed vessels, slope, opportunity bubbles, sector skyline, ledger and exact-data disclosure |
| `segments/motion.js` | Reading registration, material progress, exact source text and navigation state |
| `segments/expressive-motion.js` | Per-object timed material assemblies, capped stagger, cancellation and source interaction replay |
| `segments/cinema.js`, `segments/smooth-scroll.js` | Finishing film: flight, seams, staged arrival, report atmosphere, act rail, device tier (`still`, `lite`, `full`) and the damped wheel |
| `segments/reading-clock.js` | Hidden-safe layout measurement, monotonic entry latch and reversible decorative passage |
| `segments/territory-data.js` | Polygon-to-unit index and geographic allocation |
| `segments/map-controls.js` | Semantic branch/unit map focus and reset controls |
| `segments/polygon-journey.js` | Explicit KC selection, stable map/detail views, lateral handoff, reset, and accessible pins |
| `segments/panel-transition.js` | One cancellable owner for zoom delays, Web Animations and layout cleanup |
| `segments/polygon-selection.js` | Authorized branch/district/unit identity resolution without guessing shared units |
| `segments/choice-panel.js` | Searchable inline source choices without native selects |
| `segments/sector-model.js`, `segments/render-sector.js` | Population/SSA sector view with normalized coverage and exact source metrics |
| `segments/subsector-coverage.js` | Parent-sector reference and unique service-area denominator for finer CRAS filters |
| `app/Support/SectorPotentialService.php` | Source-ID/hash verified village population, September SSA active/PH comparison and owned-village territories |
| `app/Support/VillageOwnershipReferenceService.php` | Qualified village-to-UKER reference from the authoritative mapping Sheet, without staff columns |
| `segments/cras-subsectors.js` | CRAS request state, source filters, metrics, and subsector rendering |
| `worlds/territory-studio.js` | Persistent canvas, scene states, focus, labels, blur |
| `worlds/relief.js` | Extruded polygons and camera bounds/focus |
| `resources/css/presentation/presentation-segments.css` | Segmented layout CSS entrypoint |
| `resources/css/presentation/segments/segments.css` | Chapter, chart, motion, responsive styles |
| `resources/css/presentation/segments/panel-journey.css` | Two-view layout, clipped transition viewport and live financial detail header |
| `resources/css/presentation/segments/reading-motion.css` | Final motion layer: flat financial planes, kind-scoped chart material and bounded decorative light |
| `resources/css/presentation/segments/cinema.css` | Imported last. Flight stage, seam band, atmosphere, act ghost titles and rail, closing room, tier and reduced-motion guards |
| `resources/css/presentation/segments/expressive-motion.css` | Expressive material, inert clipped planes, glass/prism/donut depth and input energy |

JavaScript paths beginning `segments/` or `worlds/` are relative to
`resources/js/presentation/`. Preserve `?layout=keynote`, `?layout=slides`, and
`?layout=classic`; shared scene changes can affect those layouts too.

## Kinerja data

`GET /dashboard/presentation-data/segments?scope=<key>&periode=<date>` calls
`PresentationSegmentDataService`. Allowed keys currently include `area6` and
the authorized branch keys. `allowedScopes()` and `resolveScope()` are the
server-side gate; a URL, localStorage entry, or disabled input is not a gate.

The payload contains availability, effective period, allowed scopes,
`scope.entity_level`, comparison dates, timeseries labels, entities, and the
`simpanan`, `pinjaman`, `kualitas`, and `recovery` chapters. Metrics carry raw
values plus formatted values. Reuse formatted values in the UI.

Source: `dashboard_harian_snapshots`. A branch aggregate has
`kanca_key === unit_key`; outlet rows have different keys. Area reads branch
aggregates; a branch reads its outlets. Never sum both grains together. Validate
outlet coverage and reconciliation instead of assuming sums are always complete.

| Reading | Established columns |
| --- | --- |
| Savings | `total_simpanan`, product/segment columns, `total_casa` |
| Loans | `total_os_non_commercial`, `sme_os`, `consumer_os`, `micro_os` |
| Quality | `total_sml_abs_non_commercial`, `total_npl_abs_non_commercial`, segment/product quality columns |
| Recovery | `rec_dh_total`, `rec_dh_small`, `rec_dh_consumer`, `rec_dh_micro` |

Product definitions belong to the service. Preserve their existing business
classification. If runtime columns or periods are missing, distinguish source
unavailability from genuine zero before presenting the figure.

## Marketshare and REKAP mapping

`GET /dashboard/presentation-data/wilayah` combines `MarketShareArea6Report`
with workbook geography through `PresentationWilayahDataService`. The shared
cache is scoped again per request for the user's branch authorization.

Marketshare uses BRI and industry figures at the report's cabang grain; it does
not establish industry marketshare per unit or named competitor-bank balances.
Preserve report units, source formatting, and source period, which can differ
from the selected daily snapshot.

Mapping contains branches, units with `district_codes`, sector potential and
existing values, source metadata, and coverage. GeoJSON is fetched from
`public/data/marketshare-area6-kecamatan.geojson`; identities use `KDCPUM`,
`WADMKC`, and `WADMKK`. When the owned-village reference is available,
`withVillageTerritory()` overlays the unit's actual village-containing districts
onto the presentation mapping by exact branch/unit code. It preserves REKAP
figures and source period. Unknown ownership has no invented district. The
legacy mapping service/config remains unchanged for its other consumers.

The current geographic allocator splits a unit equally across its mapped
kecamatan and sums shared coverage. Explain that this is allocation on the map;
whole-unit source figures remain in tables. Restrict the units used for map
heights, labels, hover, and picks to the active selected scope. Unmapped outlets
must remain in source totals/tables and receive a coverage disclosure.

## Actual CRAS subsectors

Use config `crasUrl` pointing to the existing route
`report.dashboard-dana.market-share.mapping-cras.data`:

```text
GET /report/dashboard-dana/market-share/mapping-cras/data
    ?periode=<date>&wilayah=all|<branch>&sektor=all|<source-value>&sub_sektor=all|<source-value>
```

Build parameters with `URLSearchParams`. `area6` maps to `wilayah=all`.
`App\Support\CrasMappingService` applies `UserBranchScope` and returns
`filters.selected`, dependent `filters.options`, effective period context,
`units`, `metrics`, `coverage`, and availability. Each unit has a code, name,
`branch_key`, `district_codes`, and values such as `jumlah_debitur`,
`jumlah_rekening`, and `baki_debet`.

Use exact source selector values, reset dependent selections, and ignore stale
responses after a newer request or chapter rebuild. Display source availability,
period, loading/errors, and mapping coverage. A request's requested period may
resolve to an available CRAS period; disclose the effective one.

This payload supplies portfolio metrics, not a subsector potential population.
Without a compatible numerator and denominator of the same geography, period,
and grain, show subsector penetration as unavailable. Keep actual counts/OS and
the existing REKAP sector penetration usable. Do not repurpose LPG mapping ratio,
NPL ratio, or share of BRI debtors as market penetration.

## Reading motion

The deck's `.sg-reading-motion` layer is imported last and keeps financial text,
cards, tables, rows, cells, labels and axes stationary. `data-sg-motion` identifies
the actual component kind. `--reading-enter` reaches 1 before the reading band
and latches until that node is replaced; `--reading-pass` follows reversible
viewport passage for decoration only. `data-reading-phase` records approach,
entry, hold and departure, while `data-reading-settled` records the entry latch.
Focus and reduced motion immediately receive complete geometry. Keep numeric
text equal to the formatted source throughout scroll and selection changes;
an opacity transition must not substitute intermediate numeric values.

Animate only local chart material. Sphere material scales around its recorded
center; its hit target and axes never move independently. A settled section or
header must not force a nested chart to settle: match the component kind in its
settled selector. Preserve the settled baseline projection and source ratios.
Clip decorative material selectively, retaining rims, labels, focus rings and
exact-data disclosures.

`measureReadingBox()` rejects hidden, inert, transitioning, detached and zero-size
content before reading its dimensions. It uses the untransformed offset chain
instead of a bounding rectangle affected by the animation itself. Re-measure
after a panel becomes visible, a disclosure opens, or content is replaced; new
DOM receives a new entry latch. Do not carry stale measurements or completed
entry state into a replacement source/filter result.

## The flight and the seams

`TerritoryStudio.beginFlight()` attaches `territoryFlight` to the live `ReliefScene`
at its true `geoFrame`, and registers a full-screen `flight` state that survives
the deck rebuild. `PresentationCinema` holds the camera above Earth until the deck
exists, then descends on a fixed clock. At `FLIGHT.handoff` the slot is removed and
`studio.handoff` damps the frame, clip and pose into the first room's slot, so there
is no cut. `endFlight()` disposes the planet's textures and restores the camera's
depth range. Any wheel, touch, Escape, Space, Enter, `Lewati`, a hidden tab or
sustained slow frames skips it. A failed build calls `abortFlight()`.

`studio.seam(direction, { assemble })` swings the camera the way the reader travels
and raises the new figures from the floor. `showFinale()` is a room outside `keys`
with its own history entry (`halaman=finale`).

## Scene integration

Expressive assemblies have their own per-object owner inside `SegmentMotion`.
They use the same visible untransformed measurements, do not animate financial
labels/axes/rows, and settle reading geometry before cancelling filled handles.
First entry still plays after a wheel jump directly to complete reading progress.
Decorative planes clip their own moving children. Donut layers retain a real
preserve-3d hierarchy; final keyframes use the actual settled CSS projection.
The panel/camera owner remains separate from these local material owners.

Register a scene state with `TerritoryStudio.register(key, spec)`. The established
spec includes a `journey` of camera stops, focus polygon sets, labels, heights,
captions, and optional `describe`, `pick`, and arrangement callbacks. Existing
`ReliefScene.setFocus()` frames polygon bounds. Keep one scene instance and
rebind current slots after deck replacement; do not preserve detached chapter
listeners or act on stale data.

For the three geographic chapters, keep `ctx.scope` as authorized launcher scope
and use chapter-local `ctx.selection` with stable branch/unit codes. Re-render
the figure and details from that selection before unlocking the sheet; the same
camera instance persists. Marketshare stays at the source branch grain. A shared
district groups unique serving units until the user chooses an explicit unit pin.
Only explicit map activation selects a branch, and the first activation always
names the KC before unit drilldown, including in branch-scoped presentations.
Synchronize the branch across geographic chapters, frame it on the persistent
camera, and show its identity in an overlay and sticky three-point reading rail.
The first explicit choice zooms to the named KC, then opens its financial detail
through a leftward handoff. Returning to the map reverses the handoff. Once a KC
is selected, the three-point rail and chapter navigation open its detail
directly. Do not create a popup or native select. CRAS return-to-map uses the
same view lifecycle and preserves its filters.

`data-polygon-view="map|detail"` records a chapter's stable view. Only its current
opener or sheet is visible and interactive: synchronize `hidden`, `inert`, and
`aria-hidden`. Detail stays in normal vertical document flow. Move the actual
headline figure/label/chips into `.sg-detail-summary`, retaining markers for
restoration to the opener; never clone live forms or chart nodes. Preserve the
source values and period when the same nodes change location.

One `PanelTransition` owns the zoom delay, animation handles and cleanup. New
navigation, a new polygon choice, reset, launcher suspend, or deck disposal must
cancel that owner before another begins. Cancellation removes temporary layout,
animation fills, busy state and hidden labels, returning to a coherent stable
view. Use a temporarily clipped chapter with `--sg-panel-height` and absolute
opener/sheet while both participate in the handoff; release those rules at its
end so a long detail never changes the outgoing map's layout mid-animation.

Keep the map stage's layout rectangle stationary. Animate the copy, sheet and
shared WebGL/label visual planes, not the geometry used by camera projection.
`TerritoryStudio` must ignore detached, hidden or zero-size slots before reading
bounds, and release stale targets, hover and active scene state when a panel or
deck leaves. Do not create an isolated opaque chapter stacking context below the
shared fixed canvas: its copy and incoming sheet must remain above that canvas.
After settlement, re-scan current charts/scene and transfer focus without an
unintended scroll. Reduced motion reaches the same views immediately; touch and
the SVG fallback use the same branch identity and back action.

Hover must not change camera orientation or tilt cards. Hide StageFX.rings only
in TerritoryStudio so decorative orbit motion is absent on this entry path.
Keep SVG polygon keyboard/touch selection, backwards navigation, and reset usable.
Blur is temporary camera feedback on the canvas.
Reduced motion and a static fallback must preserve reading and selection.

Sector potential is separate from the existing REKAP Mapping totals. Column F
of the configured business-population Sheet is aggregated by unique geography;
17 original categories form 14 explicitly compatible SSA groups; OTHER/tanpa
sektor remains in the total without an invented potential category. UKER
population is the sum of its owned villages in the authoritative mapping Sheet
`1m2Mx7JjK3r0oH7gLzAaOjPREasIdxxiKdq-aJcOB0hM` (`data`, A:H). Village keys
include regency, district and village. Same-owner duplicates do not multiply
population; conflicts and missing population remain explicit. Unit, branch and
Area totals use the same owned cohort, with unowned SSA UKER listed separately.
Intra = AKTIF, ekstra = PH, total = both including unclassified sectors;
Consumer/Konsumer is excluded from the business cohort. Both source IDs/hashes
belong in the cache identity. Generic CRAS portfolio metrics remain intact;
the additive `sector_reference_units` supplies this presentation's cohort.
`penetration_raw` retains the ratio; presented `penetration` is capped
at 100 as requested. Missing/zero denominators remain unavailable. Fine CRAS
uses the parent category's UKER territory population as an explicitly named
reference. Its filter must never discard zero-customer UKERs from a fixed
selected territory. Potential source vintages and September 2026 SSA dates are
separate; a capture date is not a population reference date.
