---
name: asix-presentation
description: Build, extend, restyle or debug the segmented 3D presentation mode of project-ABAH (/dashboard/presentation, "Mode Presentasi" on the landing pinjaman). Covers the segment launcher (Marketshare, Mapping Wilayah, Penetrasi Sektoral, Kinerja Simpanan, Pinjaman, Kualitas, Recovery DH), the Area/cabang/unit scope filters, the persistent WebGL kecamatan relief with premium zoom-in/zoom-out and lens blur, 3D donuts, timeseries and departure-board tables, and the offline verification loop. Use this whenever the user mentions mode presentasi, presentasi segmen, relief/peta 3D, polygon kecamatan zoom, scrollcraft or scroll-world for this app, a new segment or chart in the presentation, per-unit or per-cabang breakdowns in the presentation, or says the presentation looks flat, plain, or not premium enough, even if they don't name the skill.
---

# asix-presentation

The presentation is a scroll film built from real data. Scroll is the clock; a
single WebGL relief of Area 6's 76 kecamatan is the stage that follows the reader
from chapter to chapter; every number is real markup from the same accessors as
the landing page. This skill is how to change it without breaking any of that.

It distills two external methods and adapts them to live data:

- **scrollcraft** (journey first, a feeling curve with one peak, device variety,
  dimensional layering, verify by scrolling). The vendored engine is
  `resources/js/presentation/vendor/scrollcraft.js`; never edit it, drive it with
  `data-sc-*` attributes (`data-sc-magnet` on CTAs).
- **scroll-world** (one continuous camera, seams that never pop, dive-in, linger).
  Scroll-world renders the flight to video; here the flight is a live camera, so
  continuity is free and the craft moves to *where the camera stops* and *how it
  travels between stops* (damped dolly + lens blur).

Read `references/architecture.md` before changing code: it maps every file and
the data contracts. Read `references/verify.md` before claiming anything works.

For the current sector/village contract, also read
`../../../.agents/skills/asix-presentation/SKILL.md` and its references. The
authoritative village ownership Sheet is
`1m2Mx7JjK3r0oH7gLzAaOjPREasIdxxiKdq-aJcOB0hM`, tab `data` (A:H only).
Join Kabupaten/Kota + Kecamatan + Desa/Kelurahan, not village names alone.
Potential is column F of `1rvsgRrblzkIB28pDpBNzSP9scCmhdnDXEl6eOShfLm0`
summed only over owned villages. Intra = AKTIF; ekstra = PH; exclude
Consumer/Konsumer in this business cohort. Keep OTHER in total and display
intra/ekstra/total separately. Do not replace generic CRAS portfolio metrics;
use its additive `sector_reference_units`. Missing population, ownership
conflicts and unowned SSA UKER remain explicit. Preserve raw ratios and the
requested 100% display cap. Fine subsectors use parent-category population
in the selected owned villages, including fixed zero-customer territory.

## The page in one picture

```
launcher (dark)  pick segments + scope  ->  ?segmen=...&scope=...   (shareable URL)
  relief: floating apart, assembles as the reader scrolls the form
for each chosen segment, in group order:
  opener (pinned, data-territory-track="pin")   statement + figure + relief slot
     relief: a registered state; a journey of camera stops by scroll progress
  sheet (opaque, slides over the opener)       tiles: donut, timeseries, rods, tables
close (dark)  reassembled relief, "Ubah segmen"
```

Scopes: `area6` breaks down **per cabang** (branch rows); a branch (`madiun`,
`magetan`, `ngawi`, `ponorogo`) breaks down **per unit** (outlet rows: KC detail,
KCP, Unit). Branch users only ever get their own branch, enforced server side in
`PresentationSegmentDataService::resolveScope()`; never trust the URL.

## Where data comes from

| Segment | Endpoint | Source |
|---|---|---|
| Simpanan, Pinjaman, Kualitas, Recovery DH | `dashboard.presentation-data.segments?scope=` | `dashboard_harian_snapshots`: one row per outlet plus a branch row where `kanca_key = unit_key`; outlets sum exactly to the branch row |
| Marketshare | `dashboard.presentation-data.wilayah` | `MarketShareArea6Report` (RO report, per cabang only), normalized by `PresentationWilayahDataService` |
| Mapping Wilayah | same | REKAP financial values; polygon territory follows the current village ownership reference |
| Penetrasi Sektoral | same + existing CRAS endpoint | `SectorPotentialService`: village population and September 2026 active/PH reference cohort |

Rules that keep numbers honest:
- Totals must equal the landing page. Same columns: simpanan `total_simpanan`,
  OS `total_os_non_commercial`, SML/NPL `total_*_abs_non_commercial`, recovery
  `rec_dh_total` (+ `rec_dh_small/consumer/micro`).
- A unit serving several kecamatan is split evenly across them on the map; tables
  use the unit's whole value. Say so in a note under the table.
- Outlets are matched to REKAP units by name token (`outletToken()` in
  `segments/format.js`); unmatched outlets simply don't lift on the map.
- No invented numbers, no counters for values that do not exist.

## Adding or changing a segment

1. Data: extend the service (PHP) and its Pest test in
   `tests/Unit/PresentationSegmentDataServiceTest.php`. Keep raw `value` plus
   server-formatted `formatted` (house style: `Rp14,35 T`, `+Rp60,00 M`, `2,33%`,
   `+0,65 pp`).
2. Launcher: add the card in `$segmentGroups` (`resources/views/presentation/segments.blade.php`).
3. Renderer: a function in `segments/render-*.js` returning
   `{ html, territory(studio), bind(root, studio) }`, registered in `RENDERERS`
   in `segments/main.js`. Build the html with `chapter()`, `block()`, `tile()`,
   `specs()` from `segments/layout.js` and the parts in `segments/charts.js`.
4. Relief state: `studio.register(key, spec)` (see the spec contract in
   `worlds/territory-studio.js`). Give it a journey worth scrolling: overview,
   then the stops the reader should look at, then back out.
5. Vary the opener: alternate `anchor` lead/trail, choose ground light or dark by
   feeling (risk and maps are dark theatres), keep one peak per presentation.

## The zoom (what makes it feel premium)

- Camera focus is `ReliefScene.setFocus(codes)`: bounding box of the kecamatan,
  damped glide (`-2.4` per second). Never cut; let the glide carry it.
- While the camera travels, the studio blurs the canvas slightly
  (`--sg-blur`, max ~3px) from `scene.zooming` and the chapter swoop. "Sedikit
  blur", not a smear.
- Outside the focus the relief sinks and greys out so the eye lands where the
  camera goes.
- Clicks drill down: Area -> branch -> unit; clicking the same focus again or
  empty floor zooms back out. Pick at the pointer on click (hover is cleared while
  the button is held).

## Motion contract

All depth runs on scroll-written custom properties (`segments/motion.js`):
`--e` tiles rise from depth, `--sp` opener progress, `--cover`/`--rise` the sheet
tipping up over the opener. Measure with the offset chain (`layoutBox`), never
`getBoundingClientRect` on the element being transformed, or the value feeds back
on itself. Reduced motion: every surface flat, the camera holds the overview.

Stacking: `position: sticky` creates a stacking context. The opener's sticky
wrapper must have `z-index: 2` or its copy sinks under the relief canvas (z 1).

## Verify before saying it works

The repo is served live by Apache; `npm run build` deploys instantly. Build to a
scratch dir, render the Blade view offline, shoot it with GPU Chromium, read the
contact sheets, then run `npm run build`. Exact commands in `references/verify.md`.

Never `Cache::flush()` in a probe script: the app's file cache is production's
cache (KPI and dashboard payloads). Use `Cache::forget($key)` for the one key.
