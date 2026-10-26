# Cabang sebagai perjalanan, 26 Oktober 2026

This is an iteration of the existing presentation, not a new marketing site.
Creative decisions below implement the user's supplied sequence and BRI identity.

User direction: "jangan pakai pop up gunakan scroll saja", "basic filter klik
wilayah polygon", "berikan keterangan kantor cabang dan munculkan overlay dan
langsung zoom in ke cabang tersebut". The requested points are Marketshare,
Mapping Wilayah, and Penetrasi Sektoral. No native select controls; no orbit hover.

## Direction

- Tone: precise, dimensional, composed, immersive. Financial figures stay readable.
- Palette: BRI blue #0058d0, selected blue #4c9dff, atlas navy #051b3a,
  paper #ffffff, canvas #f5f5f7, ink #1d1d1f. Existing Geist typography.
- Grammar: a working atlas followed by a linear financial reading, with direct
  map selection and a persistent branch identity. Preserve chapter choices.
- Assets: actual kecamatan GeoJSON, actual unit mapping and source financial
  figures. Live Three.js relief plus semantic HTML/SVG; no generated film.
- Camera: click-driven bounds fit, then composed scroll poses; stationary hover.
  Orbiting rings and satellites are absent on the segmented entry path.
- Signature: choosing one KC on the relief carries the same KC through three
  readings while allowing deliberate district and unit exploration.
- Peak: the whole area resolves into one named KC, its geographic bounds fill
  the map, and its three linked business readings become available.
- Tell-someone sentence: the presentation where choosing a branch on its map
  brings its market position, potential, and sectors into one connected reading.

## Feeling curve and score

| Beat | Feeling | On-screen cause | Device |
| --- | --- | --- | --- |
| Choose | Orientation | Real branch names on the selectable relief | Working atlas |
| Enter KC | Recognition, peak | KC identity overlay and damped bounds-fit zoom | Focus pull |
| Marketshare | Clarity | BRI/industry depth donuts and precise product rows | Composition reveal |
| Mapping | Discovery | Actual serving units and potential, explicit unit pins | Geographic drill |
| Sektoral | Understanding | Sector choices and searchable source subsectors | Inline exploration |
| Return | Control | Three-point rail, KC reset, existing launcher | Direct navigation |

No empty cinematic spans or forced movie playback. Desktop maps hold briefly
while the report enters; mobile uses a compact map and natural vertical reading.
Reduced motion provides instant focus with the same data and controls.

## Verification

See storage/framework/testing/asix-presentation-journey/VALIDATION.md for actual
desktop/mobile/keyboard/fallback data, hover, source, and asset evidence.
Scroll Craft doctor reports missing ffmpeg and local playwright-core; this build
uses live application geometry instead of video, and browser verification uses
the available Playwright connector. No dependency or paid-generation changes.

# Atlas rebuild, 26 Oktober 2026

User direction, verbatim: "ketika di hover ada animasi yang melingkar.. itu jujur distracting secara ui hilangkan animasi hovernya" and "penyajian data di marketshare, mapping wilayah, dan penetrasi sektoral itu kurang impresif ... terlalu generic dan terlalu campur aduk".

- Hover is quiet: no lift, no ring, no pulse. The pointed kecamatan is lit in place and the tip names it. On the segmented path the stage runs `fx.calm` (no radar sweep, beacon foot rings, spinning gems, scanning beams or shock rings) and `scene.quietHover`.
- Click: the chosen branch lifts out of the relief (levitate 0.5), the camera follows the lifted island, and the opener figure rolls from its previous value to the new one.
- One visual language for "a part of a whole" across the three chapters (`segments/atlas.js`): glass vessels (Marketshare products and branches against industry, Mapping branches against potential), a share-shift slope, a coverage slab, an opportunity field of unit spheres with the priority quadrant, a sector skyline that chooses the sector, and a units x sectors ledger. Every sheet reads whole, comparison, detail; the exact tables sit in a disclosure.
- Motion is entrance only (glass grows, liquid pours, spheres drop, prisms rise, ledger cells flip in a diagonal). Nothing loops.
