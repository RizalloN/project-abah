# Enter the branch, then read its subject

The geographic chapter becomes a deliberate place to enter. A presenter first
chooses a KC on the actual polygon. The camera focuses on that geography, a
full-viewport threshold confirms the branch identity, and a dedicated report
page opens. Back returns through the same identity and then reveals the map.
Changing the subject is explicit. Scrolling reads the current subject without
accidentally advancing into the next chapter.

## Art direction

The scene uses the application's BRI palette: a navy architectural threshold
`#071d3b`, blue `#1264d5`, ice `#bddbff`, and the existing financial category
colors. Typography remains Geist with clear sentence-case hierarchy. The KC
name is the destination, shown as the largest, crispest object at the center.
Three progressively separated rectangular contours give the feeling of
passing through a physical threshold. Fine vertical grid lines establish
distance; a short progress line marks the handoff without competing with the
branch identity. There is no floating dialog, generic gradient curtain,
autonomous orbit, or unrelated illustration.

The persistent context bar acts like the header of a presenter's folio. Back
is visible at the left, the KC/topic identity is next, and inline subject
choices use a separate navigation band. On compact screens, choices wrap into
a horizontally scrollable second row with real 44-pixel targets. The main
report remains a normal document with bounded charts, exact tables, and air
between financial subjects.

This adapts Scroll Craft's layered composition and varied material response,
Scroll World's continuous arrival/return handoff, and Asix Presentation's
source, identity, accessibility, and lifecycle contracts. All camera movement
uses the actual live polygon scene. No generated video, paid media pipeline,
new dependency, or financial formula is introduced by this design layer.

## CSS and markup contract

`resources/css/presentation/segments/branch-room.css` is an additive final
layer. `.sg-room-mode` belongs to `.sg-main`, covering the active chapter and
the transition. The runtime remains authoritative about which chapter is
active and applies `hidden` to the others. This stylesheet forces hidden
chapters, the context bar, and the threshold to remain hidden. It does not
choose a KC or a subject on the user's behalf.

The context structure is `.sg-room-head`, containing:

- `.sg-room-head__back`, an actual button/link for returning to the map;
- `.sg-room-head__identity`, with a small context line and a strong subject;
- `.sg-room-head__chapters`, containing actual subject links or buttons with
  the current choice represented by `aria-current` or `aria-pressed`.

The bar is sticky directly below the existing application navigation. Its
default reserved height is `--sg-room-head-h: 82px` on a wide desktop and
128px when choices occupy a second row. The runtime can replace that variable
with a measured height. The old geographic story rail is suppressed only when
the new visible header exists, avoiding duplicate context controls.

The transient threshold structure is `.sg-room-portal`, containing:

- `.sg-room-portal__world`, with three decorative `i` contour frames;
- `.sg-room-portal__map`, a non-interactive outline copied from the selected
  source polygon paths, with no duplicated controls or financial text;
- `.sg-room-portal__copy`, containing the topic, an `h2` or `[data-room-kc]`
  identity, a short status line, and `.sg-room-portal__line > i`.

The threshold fills the viewport and owns no independent scroll or input. It
is pointer-transparent. Root runtime owns visibility, accessible status text,
transition cancellation, perspective travel and any temporary navigation lock.
`--sg-room-progress` controls the line through scale, without moving layout.
The identity remains separate from the dimensional world so it stays crisp.
No CSS keyframe or idle loop can replay after the runtime has completed it.

## Reading and device boundaries

The desktop geographic entrance reserves one composition beneath the two
context bars, with copy on the left and a map stage on the right. Stage and
copy use their natural height if content requires more room. Small or short
screens use one column: a compact explanation followed by a map with a
300-pixel minimum usable stage. They can scroll within this selected subject
instead of clipping text or shrinking polygon targets.

The dedicated detail sheet uses normal document flow with no nested vertical
scroll container. Current chart coordinates, table columns, source text and
hover material are retained. Reduced motion removes threshold geometry and
transitions while keeping the branch identity, controls, report, and route
usable. Keyboard focus remains visible and offsets reserve the context bars.

## Ownership and history

`PresentationRoom` owns the single visible chapter, the persistent topic/Back
controls and the namespaced browser history. Inactive chapters remain native
`hidden` and `inert`. Scroll cannot activate them. Only stable map/detail/report
states are committed; camera phases never add history entries. Existing foreign
history state, scope and period query parameters survive.

`PolygonJourney` owns one cancellable transition and the selected branch/unit.
Its sequence is zoom-in, identity threshold, detail; Back reverses the sequence.
`TerritoryStudio.waitForFocus` observes actual matching camera frames, with
bounded cancellation/fallback when the surface is unavailable or slow. A goal
change event alone is not camera completion. No second render clock is added.

Back returns to the authorized branch overview. Mapping and sector detail also
offer an explicit unit exploration action; shared service areas retain actual
labelled unit pins. Neither popup nor native branch/unit select is introduced.

See [BRANCH-ROOM-VALIDATION.md](BRANCH-ROOM-VALIDATION.md) for the final build,
device checks, source limitations and the existing unrelated sector-test failure.
