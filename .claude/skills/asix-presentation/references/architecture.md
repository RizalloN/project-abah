# Architecture

## Routes and views

| Route | Controller method | Notes |
|---|---|---|
| `GET /dashboard/presentation` | `DashboardSimpananController::presentation` | default view `presentation.segments`; `?layout=keynote` the single-relief keynote; `?layout=slides`/`classic` the old deck |
| `GET /dashboard/presentation-data/segments` | `presentationSegmentData` | `?scope=&periode=`; cached 15 min per period+scope after scope enforcement |
| `GET /dashboard/presentation-data/wilayah` | `presentationWilayahData` | Area payload cached 30 min on workbook mtime; branch scoping applied per request |

PowerPoint export stays on the keynote view (linked from the launcher foot).

## Backend

- `app/Services/Presentation/PresentationSegmentDataService.php`
  `build($period, $scope)` -> `{ available, period, period_label, scope{key,label,level,entity_level}, scopes, compare{dtd,mtd,ytd,yoy}, timeseries_labels, entities[], simpanan, pinjaman, kualitas, recovery }`.
  Compare periods: previous available day, last position of previous month,
  of previous year, same date last year. Timeseries: last position of each of the
  past 11 months plus the current position.
- `app/Services/Presentation/PresentationWilayahDataService.php`
  `marketShare(segments, meta, ?branch)` normalizes RO report rows (deposit columns
  2/6/13/14/16, loan 2/9/15/16/18); `mapping(geography)` strips the GeoJSON body.

## Frontend (`resources/js/presentation/`)

| File | Role |
|---|---|
| `segments/main.js` | entry: choice (URL + localStorage), data loading, deck assembly, caption events |
| `segments/layout.js` | `chapter()`, `block()`, `tile()`, `specs()` |
| `segments/charts.js` | `donut()` (stacked SVG layers = ring wall), `timeseries()` (+ `shown` series), `rods()`, `table()`, `meter()` |
| `segments/motion.js` | `--e`, `--sp`, `--cover`, `--rise`, reveals, count-up, pointer tilt + specular, nav state |
| `segments/territory-data.js` | `TerritoryIndex`: kecamatan <-> branch/unit, sector allocation, outlet matching; penetration colour ramp |
| `segments/render-wilayah.js` | marketshare, mapping, sektoral |
| `segments/atlas.js` | Wilayah data objects: `vessels` (whole = height, our part = liquid), `slope`, `coverage`, `opportunity` (units on penetration x potential), `skyline` (sector prisms, click = choose), `ledger` (units x sectors), `disclosure` (exact tables folded); CSS in `segments/atlas.css`, entrance-only motion |
| `segments/render-kinerja.js` | simpanan, pinjaman, kualitas, recovery |
| `worlds/territory-studio.js` | fixed canvas, slot selection, state registry, journeys, click drill-down, labels, tip, blur |
| `worlds/relief.js` | `ReliefScene`: extruded kecamatan, arrangement (explode/spread/levitate/grow/wave), `setFocus()` |
| `worlds/stage-fx.js` | dust, floor grid, rings, beacons, flow arcs, shocks; `fx.calm` (set by the territory studio) removes everything that circles or pulses: radar sweep, beacon foot rings, gems, beam scan, shocks |

CSS: `resources/css/presentation/presentation-segments.css` imports keynote
`base.css` (tokens, nav, buttons, loader) and `segments/segments.css`.

## Territory state spec

```js
studio.register(key, {
  journey: [{ until, focus: Set|null, heights(code), labels: [{ id, codes, title, value }], caption }],
  glass, dark,                       // 0..1; risk uses glass cores, maps use dark
  fx: { beams, flow, speed, color }, // flow -1 inward (funds), +1 outward (credit)
  describe(code) -> html,            // hover tip
  pick(code) -> Set|null,            // click focus
  labelsFor(codes), captionFor(codes),
  arrangement(progress, still) -> { explode, spread, levitate, levitateBranch, grow, wave },
  version,                           // bump to force re-apply (e.g. sector change)
});
```

Heights: `{ lift 0..1, color hex, roughness, core 0..1 }` per KDCPUM code.
