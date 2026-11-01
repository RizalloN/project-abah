# Geographic chapter panel journey

The user enters a KC by clicking its real polygon. Marketshare, Mapping Wilayah,
and Penetrasi Sektoral keep that branch identity and the existing three-point
rail. There are two document views per chapter: a map and its financial reading.
The detail is a normal page, with the existing data objects, accessible tables,
inline choices, and source labels. No modal or native select is introduced.
Once a KC has been selected, the reading rail and chapter navigation can enter
its detail directly instead of replaying a map selection.

The signature move is a lateral handoff: the map and its copy leave toward the
left as the financial reading arrives from the right. Returning to the map
reverses that movement. The shared scene and labels move under the same clock;
the map stage's layout rectangle remains unchanged so projection stays correct.
The reader can identify the KC before the handoff and again above the detail.

During the short handoff, the chapter is a clipped viewport with a temporary
height. It contains two absolutely positioned views rather than adding the
entire report height to the outgoing map. Runtime code removes this temporary
layout after the movement, restores normal document flow, and transfers focus.
Reduced motion uses the same selection and reading states without a slide.
The detail header receives the actual headline figures from the opener and
returns those same nodes on the back action. A single cancellable owner holds
the delay, animation handles and cleanup, so interruption cannot reopen a panel
after a new chapter, launcher or deck has taken over. Hidden/detached map slots
are not candidates for projection measurements or pointer picking.

CSS contract: `data-polygon-view="map|detail"`, `.is-panel-transition`, and
`--sg-panel-height` belong to the chapter. `.sg-detail-head` is the first detail
child and contains a return button plus KC/title. Runtime owns hidden/inert,
Web Animations, interruption, shared-scene visibility, and focus.

Validation must inspect forward/backward transitions, a long report, rapid
input, mobile and short landscape, keyboard focus, reduced motion, and the
SVG fallback. This document records the design contract, not a claim that those
runtime checks have already passed.
