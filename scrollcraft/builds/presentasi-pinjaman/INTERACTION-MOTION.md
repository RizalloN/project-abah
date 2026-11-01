# Precision and light under the presenter's hand

The next refinement keeps the existing scroll assemblies and live KC camera
journey. Fine-pointer input adds a second, responsive layer: a glass highlight,
a prism's polished face, a leaning dimensional ring, and a sphere's pearl skin.
The BRI financial reading plane remains fixed. The outcome is an instrument a
presenter can explore, with the figures and chart semantics still reliable.

## Direction and visual grammar

The palette inherits BRI blue `#0058d0`, navy `#08254b`, ice `#b8d9ff`, copper
`#ba8152`, and dark metal `#304766` from the expressive layer. Current report
fonts, source category colors, dimensions, and semantic labels are retained.
Source colors identify actual categories; reflections describe physical
material rather than inventing a second financial scale.

Each part has a distinct response:

| Part | Under the pointer | Stationary information |
| --- | --- | --- |
| Launcher | Optical edge, local blue reflection, check medallion | Choice label and entire hit surface |
| Vessels | Narrow glass reflection, up to 4 degrees around the vertical axis | Value, label, source fill fraction |
| Sector prisms | Face reflection, lit shoulder, up to 4 degrees around the vertical axis | Height, source fill, unit identity |
| Composition | The nine-layer disc responds on a fixed diagonal axis within 6 degrees and lifts 3 pixels | Separate center value, legend and ratios |
| Opportunity | Pearl highlight tracks on each sphere's skin | Diameter, x/y, axis labels and hit target |
| Time and slope | Local stroke light, existing exact source probe | Recorded vertices, axes and figure labels |
| Rods/twins | Moving inset reflection and a polished track edge | Length, source color, numerical figure |
| Coverage | Slab hinge responds within 3 degrees | Fill percentage and source labels |
| Metrics | A short accent lengthens; local paper reflection | The exact financial value and label |
| Tables/ledgers | Thin tracking rule identifies the current row | Cells, sticky header and quantitative heat colors |
| KC map | Local stage light supports the actual polygon | Camera orientation, polygon geometry and KC label |

Scroll Craft supplies the varying reading rhythm and independently lit
materials. Scroll World's continuity is adapted to the existing live polygon
camera and lateral map/detail journey. Asix Presentation bounds the effect to
the current financial object, keeps the actual data sources and access scope,
and requires reverse/reselection/mobile evidence. There is no generated-video
dependency or extra paid media pipeline in this live financial presentation.

## Runtime and CSS contract

`InteractionMotion` adds `.sg-interaction-motion` to `.sg-main`, covering both
the launcher and generated financial deck. Current component roots receive
`data-sg-interaction` from their actual `data-sg-motion` kind; actual `.sg-card`
and `.sg-scope` roots use `choice`, `.sg-opener__stage` uses `map`, and the
story rail uses `navigation`. The current local object receives
`data-sg-hot="true"`. Engagement is `pointer`, `focus`, or `press`.

The shared reading RAF supplies `--sg-pointer-x/y` in the inclusive -1..1 range,
`--sg-light-x/y` as bounded percentages, and `--sg-engaged`/`--sg-press` in 0..1.
The source coordinate frame is the untransformed component boundary. CSS keeps
the root unchanged and uses individual `rotate` and `translate` only on local
material so it composes with the scroll owner's `transform`. Before material
takeover the old expressive assembly is settled and cancelled by its owner.
The KC context rail stays stable on return from a Kinerja chapter; its old
arrival reveal does not replay when the hidden state changes.
An early focus takeover also completes that object's entry latch before its
first reading frame; an explicit source interaction can still replay assembly.

All reflection planes are inert, clipped existing `.sg-expressive-plane`
surfaces behind real content, or pointer-transparent material pseudo-elements.
No new semantic children are inserted by this CSS. The donut disc retains
`filter: none` and `transform-style: preserve-3d`; no opacity/filter grouping is
added to it. Source heatmap cell colors and bar gradients are retained.
Glass and prism reflections use bounded local `translate3d` travel rather than
animating their background position. The donut's fixed diagonal axis responds
to both horizontal and vertical pointer movement without a zero-axis singularity.

One active interaction is owned at a time. Pointer work is restricted to mouse
input on a fine hover-capable device. Pointer leave, page scroll/resize, window
blur, pointer cancellation, hidden/replaced content, visibility changes and
media-preference changes clear the current pointer variables. Keyboard focus
remains owned through browser focus scrolling. There is no idle RAF or
infinite animation in this layer. Keyboard focus uses a centered stationary
reflection alongside the existing visible focus ring. Touch uses bounded press
feedback; reduced motion skips spatial response and transitions.

The selected-KC rail is visible only while reading a geographic chapter.
`PolygonJourney.enforce()` derives that context from all current deck chapters,
so Kinerja and the launcher have no stale geographic rail. Returning to the
geographic chapters restores the same KC without changing financial scope.

## Acceptance and evidence

- Compare pre/hover/post exact source text, labels and hitbox coordinates.
- Capture genuinely active hover frames as well as the resting result.
- Confirm reverse scroll and reselecting a KC leave one live scene and no old
  interaction state or assembly handles.
- Verify 320/390-pixel portrait and landscape compositions, touch pointer
  cancellation, keyboard navigation, reduced-motion changes, and SVG fallback.
- Check that table heat colors, bubble diameter, bar lengths, axis positions,
  and chart controls retain their source meaning throughout the interaction.
- Check motion tests, related PHP presentation tests and the Vite production
  build. Browser artifacts must identify actual served asset hashes and state
  whether the authenticated application or an isolated source fixture was used.

The authored stylesheet was parsed with PostCSS successfully (47 rules), and
`git diff --check` produced no whitespace error. Full runtime/browser evidence
belongs to the current `storage/framework/testing/asix-presentation-interaction`
fixture and the lead's final validation report; this design note does not claim
those checks passed before the results exist.
