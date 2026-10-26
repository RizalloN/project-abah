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
| `segments/motion.js` | Scroll progress, 3D surfaces, count-up, navigation state |
| `segments/territory-data.js` | Polygon-to-unit index and geographic allocation |
| `segments/map-controls.js` | Semantic branch/unit map focus and reset controls |
| `segments/polygon-journey.js` | Explicit polygon selection, geography detail gates, reset, and accessible pins |
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

## Scene integration

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
Details remain inline under the scroll clock. Do not create a popup or native
select. CRAS return-to-map scrolls back to its map with filters preserved.
Hover must not change camera orientation or tilt cards. Hide StageFX.rings only
in TerritoryStudio so decorative orbit motion is absent on this entry path.
Keep SVG
polygon keyboard/touch selection, backwards navigation, and reset usable.
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
