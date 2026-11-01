# Four financial rooms

Design direction authored from the user's request to extend the established
Wilayah branch-entry interaction to Simpanan, Pinjaman, Kualitas and Recovery.
Use the existing asix-presentation adaptation of Scroll Craft and Scroll World:
actual branch geography, a persistent live camera, an explicit threshold, then
a dedicated report page. No generated footage or second animation clock.

## Visual sequence

Each room starts with the named branch and source position, then offers native
in-page waypoints. They lead to composition, the twelve-month history and the
entity contribution table; Kualitas adds its segment/product risk detail.
These are reading controls within the chosen topic, not routes to another topic.

The emotional peak is entry into the selected branch. Inside the report,
material assembly exposes the meaning of the chart, followed by a stable
reading plane. Existing ExpressiveMotion owns bounded entry/re-entry, while
the existing reading clock drives only decorative header depth. No idle loop,
text tilt, row movement, axis transformation or synthetic numeric count-up is
introduced by this design layer.

| Room | Material | Signature | Reading order |
| --- | --- | --- | --- |
| Simpanan | Blue glass, pale ice edges | Three separated ring planes | CASA and total, product/segment composition, history, unit contribution |
| Pinjaman | Brushed bronze edge, blue faces | Three extruded portfolio planes | Outstanding and segment amounts, product distribution, history, units |
| Kualitas | Dark glass with amber and red | Layered protective shells | Source SML/NPL ratios, segment/product detail, history, geographic detail |
| Recovery | Emerald, pale jade reflection | Three stacked recovery medallions | Recovery total, segment mix and ranking, history, unit contribution |

These small sculptures are decorative and carry no numeric scale. The actual
chart still carries its source quantities, semantic series colors, null periods,
legend interactions and exact text. The ring center and legend remain outside
the moving ring material. Rod reflections do not change the source width.

## CSS integration

Import `resources/css/presentation/segments/performance-room.css` after the
shared branch-room and expressive/interaction layers. All financial content
styles are scoped to `.sg-performance`. Existing `data-performance-theme`
identifies the four room palettes. The shared threshold may carry the same
attribute to tint its background and selected-geography silhouette.

Each `.sg-performance-act` is a normal-flow wrapper with a unique
`performance-{chapter}-{act}` anchor. Its `.sg-block__head` may contain
`<span class="sg-performance-sculpture" aria-hidden="true"><i></i><i></i><i></i></span>`.
The sculpture inherits `--reading-pass` from that registered header. Variants
come from the ancestor act classes `--composition`, `--trend`, `--entities`
and `--products`. If a sculpture is present, the earlier small signature is
hidden to avoid duplicated decoration.

Waypoint panels use native anchors, minimum 64-pixel mobile targets, clear
labels and visible focus. They become one column on small screens. The room
head's measured offset reserves scroll space for each destination. Charts keep
their normal dimensions; tables retain their existing horizontal-scroll region.
The header sculpture contracts from 128×104 to 76×66 and halves travel on mobile.
Reduced motion removes travel/reflection motion while preserving the static
material and all report controls.

## Source boundaries

The four map gateways use branch identities. In branch scope, the gateway's
money label uses the exact source total and its quality light uses the source
branch NPL ratio. It never sums outlet ratios in a shared district. Report
rows, products, comparisons, formulas and period labels retain their source
values. Mapping-coverage disclosure remains in the sheet when some outlets
cannot be placed on the map, and unmatched outlets remain in totals/tables.
Missing, null and zero-recovery views retain a selectable neutral map and the
existing distinct empty-state wording.

## Validation handoff

The renderer passed Node syntax checking. PostCSS parsed the final stylesheet
successfully (92 rules). These are static checks only. The lead must validate
current built browser output, entry/return cancellation, repeated branch changes,
waypoint scrolling, hidden-state cleanup, actual source totals/rows, chart legend
interaction, overflow, short landscape, touch, reduced motion and SVG fallback.
No authenticated runtime or browser result is claimed by this design-only pass.
