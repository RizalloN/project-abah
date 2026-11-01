# Presenter-first UI/UX polish

## Scope and direction

User request: preserve the presented content and improve presentation UI/UX using Scroll World and Scroll Craft. Continue the existing executive financial atlas, not a replacement landing page. Authored UI decisions follow that request; they are not quoted user preferences.

Retain ink navy #071526, BRI blue #0058d0, ice #bddbff, porcelain #f4f6fa, and the existing Geist typography. The geographic scene remains the signature focal point; financial text and controls remain stable. Adapt Scroll World's continuity to the existing live geometry; use Scroll Craft's purposeful journey and separate phone composition. No generated media, new dependencies, financial formulas, data sources, access rules, imports or export behavior changed.

Journey: orient in the atlas → jump to the agenda → choose scope and topics with explicit counts → start with visible busy feedback → navigate one topic at a time → return directly to the visible configuration heading with choices retained. Reuse the existing branch entrance as the visual peak, rather than adding another cinematic sequence.

## Implemented

- Higher-contrast descriptive text, larger decision text and 44px group controls.
- Dedicated compose action with focus transfer and reduced-motion-aware scrolling.
- Selected/total counts per topic family and labelled group actions.
- Empty agenda no longer floats a disabled CTA over the form. A selected agenda retains its sticky, opaque action surface.
- Start button exposes busy state and keeps a meaningful loading label.
- Room chooser supports arrows/Home/End for focus, native Enter/Space for explicit activation, Escape to close/restore focus, and focus-out dismissal.
- Show map/detail context in desktop chrome. Phone navigation retains count, previous/next and the topic chooser instead of relying only on a horizontal rail.
- Anchor offsets follow observed header height; launcher return reveals and focuses the configuration heading.

## Executed evidence

- Knowledge graph check: current before edits.
- Isolated Vite build: passed after final edits; current Blade view rendered successfully using `render.php --views-only`. No fresh financial export or live-data writes performed.
- Focused Node tests: 22 passed, 0 failed (`presentation-presenter-polish`, `presentation-room`, `asix-presentation`).
- Pest `PresentationScrollLayoutTest`: 13 passed, 151 assertions.
- Wider frontend suite: 127 passed, 1 failed out of 128. Failure in untouched `presentation-sector-model.test.mjs:50`: existing allocation returns 60 while test expects 75. Its direct model imports do not include the edited UI modules. No speculative formula adjustment made.
- JS syntax checks and scoped tracked-file `git diff --check`: passed.
- Browser: inspected launcher screenshots at 1440×900 and 390×844. No horizontal document overflow. Compose focus works; empty agenda cannot start; group selection updates 3/3 and enables start.
- Browser: topic chooser opens with current-topic focus; arrow navigation changes focus without selecting; native Enter selects Pinjaman and closes the chooser; Escape restores chooser focus.
- Browser final phone: chooser visible with a >=44px hit target, observed room header 122px, one active room, no horizontal overflow. Return restores visible configuration heading at y=136 and retains two selected topics.
- Browser reduced motion: motion class absent; compose action and empty selection gate still work. With WebGL contexts deliberately unavailable, launcher becomes ready and the SVG poster remains available.

## Limits

Browser verification uses a local offline Blade fixture with previously exported source payloads, not an authenticated live session. No claim of fresh financial reconciliation, physical-device/projector testing, or production readiness. Existing Vite warnings remain: old Browserslist dataset and a Three.js-related chunk over 500kB. Production `public/build` was not replaced; changes require an explicitly approved application asset build/deployment before appearing in the live application.
