---
name: asix-presentation
description: Build, extend, or polish project-ABAH Mode Presentasi with selectable financial chapters, Area/cabang/unit filters, polygon maps, CRAS subsectors, and premium 3D charts and scroll motion. Use for presentation-specific UI and its data wiring; preserve report formulas and access scopes.
---

# asix-presentation

Turn the application's existing financial sources into a structured presentation
that the presenter can configure and navigate. Keep Laravel/Blade, the BRI visual
identity, real financial labels, and the existing presentation layouts.

Read [architecture.md](references/architecture.md) when changing data, scope,
maps, or chapter wiring. Read [verify.md](references/verify.md) before validation.

## The presentation contract

Let the user choose chapters in two named groups, with a clear heading for every
selected chapter and a way to return to the selection without losing scope:

| Group | Chapter | Required reading |
| --- | --- | --- |
| Wilayah | Marketshare | BRI versus industry for loans and savings, per cabang |
| Wilayah | Mapping Wilayah | Existing customers, potential, and penetration per unit |
| Wilayah | Penetrasi Sektoral | Source-backed CRAS sector/subsector choices and unit geography |
| Kinerja | Kinerja Simpanan | Giro, Tabungan, Deposito; Ritel and Mikro |
| Kinerja | Kinerja Pinjaman | SME, Konsumer, Mikro and their source-defined products |
| Kinerja | Kinerja Kualitas | SME, Konsumer, Mikro and products with existing quality measures |
| Kinerja | Kinerja Recovery DH | Existing Recovery DH measures, comparisons, and scope |

Area summarizes per cabang; selecting a cabang gives its units/outlets. Maps
also allow Area users to drill down to an authorized unit. Filter selection is
separate from authorization: enforce the user's allowed branches on the server
before returning data, then keep headings, tables, charts, tooltips, and map
selection within the requested scope. Carry selected scope and period through
requests and shareable URLs. Reset a dependent unit/subsector when its parent
selection changes.

Preserve additional source categories, such as Wholesale, when they contribute
to a displayed total. Do not silently drop them to force a two-segment chart.

## Adapted scroll methods

- [Scroll Craft](https://github.com/nateherkai/scroll-craft): use a deliberate
  reading journey, varied visual devices, dimensional layers, and scroll-frame
  inspection. Its installed reference is `../scroll-craft/SKILL.md`. Reuse the
  existing application engine; upstream engines are not project-specific files
  to rewrite.
- [Scroll World](https://github.com/oso95/scroll-world): borrow camera continuity,
  composed stops, smooth entry/exit, and seam-free movement. Its installed
  reference is `../scroll-world/SKILL.md`. For live finance, drive the existing
  polygon scene with real data and a live camera. Paid generated video is not
  required; use the upstream generation workflow only if that output is requested.

Choose the rhythm around the financial reading task. A map can hold a geographic
focus, a donut can reveal composition, a line can reveal time, and a table can
settle row by row. Let a presenter jump to a chosen chapter; avoid mandatory
long cinematic sequences between working controls.

## Premium motion with readable figures

Use the existing Three.js relief and HTML/SVG chart parts. Keep one camera scene
alive while moving between focus targets; fit the selected polygon bounds and
animate both zoom-in and zoom-out with damping. A small transient blur belongs
on the map canvas during movement, never on financial text or controls. Remove
it once the camera settles. Geographic shapes and identities come from the
actual GeoJSON and unit mapping; derive feature counts rather than hardcoding them.

Give donuts visible depth, tables a controlled 3D entrance, and timeseries a
legible drawing transition. Preserve exact formatted values, units, legends,
and access to data; depth must not conceal a row or distort the meaning of a
comparison. Re-scan and rebind newly rendered content after asynchronous updates.

Marketshare, Mapping Wilayah, and Penetrasi Sektoral use direct polygon selection
instead of geographic dropdowns. The first activation always selects and names
the KC, including in branch-scoped presentations. Zoom to that branch, show its
identity on the map, and carry it through Marketshare, Mapping, and Sektoral.
Use a sticky three-point reading rail and inline scroll details, without modals.
Further polygon activations can drill into districts and explicit unit pins;
keep the parent KC visible. Sector/subsector filters use searchable inline
choices, never native selects. Automatic camera stops cannot choose a branch.
Keep hover orientation stationary and disable the segmented scene's decorative
orbit rings/satellites; click zoom and scroll transitions remain available.
Keep backward navigation and the launcher available. Accessible SVG polygon
buttons and anchored unit pins provide the same selection by keyboard and touch.
Shared polygons must identify all serving units or offer explicit unit pins,
never silently choose the first or largest unit. Keep visible focus,
labels, source-period context, and loading/empty/error states. Reduced motion
shows the same figures and functional selections with instant camera changes
and flat, stable charts. With unavailable WebGL, retain selectable SVG polygons,
tables, and reset. Other financial chapters retain their existing controls.

## Financial and geography precision

Use the existing services and accessors; reconcile chapter totals against the
same source, effective period, and scope. Keep branch aggregate rows separate
from outlet rows to avoid double counting. Missing columns, missing periods,
nulls, and zero denominators must not become invented zero performance.

CRAS subsectors must come from the existing `crasUrl` endpoint and its actual
`filters.options.sub_sektor` values. REKAP sector potential is not proof of
subsector potential. Sector population now comes from spreadsheet
`1rvsgRrblzkIB28pDpBNzSP9scCmhdnDXEl6eOShfLm0`, column F: preserve its 17 KBLI
categories and the 14 compatible SSA comparison groups. Use the latest valid
SSA cohort within September 2026. Village ownership comes from spreadsheet
`1m2Mx7JjK3r0oH7gLzAaOjPREasIdxxiKdq-aJcOB0hM`, tab `data`, A:H only.
Join villages by Kabupaten/Kota + Kecamatan + Desa/Kelurahan and resolve UKER
against SSA identity; never give every unit the whole kecamatan population.
Deduplicate same-owner rows and disclose ownership conflicts, villages without
population, and SSA UKER without owned villages. Known matched village potential
can remain visible with a partial-coverage note. Cache/snapshot provenance must
include both source IDs and data hashes. Do not retain PN or staff names.
Intra means status AKTIF and ekstra means status PH. Exclude Consumer/Konsumer
from this business comparison. Total is intra + ekstra, including OTHER/tanpa
sektor even when PH has no economic-sector classification. Keep these counts
separate; PH is BRI debt, never another bank. Credit intra uses baki_debet;
credit ekstra uses positive saldo_ph, otherwise baki_debet. Preserve source
precision rather than adjusting money to rounded workbook benchmarks. Keep the
generic CRAS units/metrics contract and use its additive sector reference cohort
for presentation. Keep exact counts and `penetration_raw`; the user
requested presented coverage bounded to 0–100%, with normalization disclosed.
For a CRAS subsector, the user chose its parent sector's population in the UKER
owned villages as denominator. Label it coverage against the parent sector,
retain exact fine-subsector potential as unavailable, and never invent a finer
population. Filter changes must not shrink the selected territory when a UKER
has zero matching customers. Keep numerator, denominator, source date and grain
visible. Never label portfolio composition as penetration.

Allocate unit values to service-area polygons only according to the established
mapping method; disclose allocation and unmatched coverage. Keep whole unit
values in the unit table. Do not invent unit identities or infer ownership from
polygon shape.

## Finish with evidence

Patch only the requested presentation concern and preserve unrelated dirty work,
import/delete/export behavior, dependencies, and schema. Validate the affected
service/rendering contracts and inspect desktop, mobile, reduced-motion, and
fallback states. Report changed files, commands/results, and any authenticated
runtime or source limitation. A passing skill validator validates the skill
package; it does not establish that the application is correct.
