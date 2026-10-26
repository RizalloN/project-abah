# Presentasi Kinerja Area 6: redesign 3D

**Self-authored under explicit creative delegation.** Permintaan user (2026-10-17), verbatim:

> lakukan penyempurnaan desain untuk mode presentasi di landing page pinjaman... dengan skill scrollcraft buat animasi 3d yang menabjubkan misal untuk polygon ada animasi bumi yang nanti zoom in ke polygon tersebut... untuk tabelnya jg perbanyak animasi 3d yang tampak hyper realis dan tidak terlihat polos... karena casenya banyak teks polos yang hanya ada transisi in dan out... perbanyak illustrasi 3d yang masih relevan yang menyatu dengan sempurna... buat aku kagum dengan hasil redesign secara menyeruruh

"Buat aku kagum ... secara menyeluruh" is read as delegation of creative direction. Everything below that is not a quote is an authored decision.

Target: `/dashboard/presentation` (scroll layout), opened by "Mode Presentasi" on the `/dashboard` landing. `?layout=slides` and the PowerPoint export stay untouched.

## The eight topics

1. **Vibe.** Authored: "orbital briefing room, glass and metal, calm authority". References: the opening of *Contact* (1997) pulling out from Earth, a Bloomberg terminal at night, Apple's product-film studio lighting.
2. **Journey (user's sequence is the existing deck order, kept):** Cover, Daftar isi, Pasar (market share, mapping polygon), Dana, Pinjaman (OS, SME, Konsumer, Mikro), Kualitas (SML, NPL), Tren, Aksi, penutup.
3. **Energy curve.** Authored: open big (planet), drop to calm (agenda), climb to the peak (descent onto the polygons), steady analytical stretch (Dana, Pinjaman), tension (Kualitas), wide perspective (Tren), resolve (Aksi, close).
4. **Feeling, stage by stage, and the one moment.** User's stated pain: "banyak teks polos yang hanya ada transisi in dan out". The one moment (user's own example): the Earth zooming in to our polygons.
5. **What no site they have seen does.** User seed: "animasi bumi yang nanti zoom in ke polygon tersebut". Authored: the polygons do not just appear, every kecamatan rises out of the planet as a lit prism whose height is its real penetration.
6. **Range.** Authored: premium dark, hyper-real materials (metal, glass, liquid), not playful, not brutalist. Corporate BRI blue with one red accent.
7. **One world or scenes?** Distinct scenes. Each chapter has its own 3D object that is made of that chapter's real numbers. The planet is the only object that appears twice (cover, descent), as bookends of the market chapter.
8. **Assets.** No kie.ai key, so nothing is generated. Real data from the presentation payload, real kecamatan polygons (`public/data/marketshare-area6-kecamatan.geojson`), Earth textures from Solar System Scope (CC BY 4.0, via the three.js examples), BRI and Danantara logos already in the app.

## Grammar

Chaptered editorial (kept from the previous build, the deck is literally chaptered and the spine nav already exists) with a **3D chapter plate** per opener. The other seven lost: filmic one-shot and continuous world would hide 15 dense report sections behind a camera; live surface bans the 3D illustration the user asked for; typographic poster, gallery and rhythmic cutlist do not fit a management report; split stage would halve every table.

## Signature move

**Orbit-to-polygon descent.** At the top of the mapping section the reader scrolls from orbit down onto East Java. The 76 kecamatan of Area 6 rise out of the planet surface as lit prisms, height and colour from real penetration, the four kabupaten names float in as real HTML labels pinned to 3D positions, and the scene settles into the interactive Leaflet map underneath.

The existing ledger spine (figures lock into the left rail as chapters pass) stays as the secondary interaction.

## Feeling curve

| Act | Feeling | What on screen causes it |
|---|---|---|
| Cover | awe, pride | a photoreal Earth turns to Indonesia, a beacon pulses on Area 6, the title sits in space |
| Daftar isi | orientation, calm | flat type, no 3D, breath before the climb |
| Pasar opener | curiosity | a 3D market-share ring: BRI's slice lifts out of the industry ring |
| Market share tables | focus | tables tilt up from depth into reading position |
| **Descent (peak)** | **wonder, then recognition** | **orbit to surface, polygons rise, "that is us"** |
| Dana opener | solidity | metal coin stacks per product, height from real balances |
| Pinjaman opener | momentum | a glass skyline, one tower per cabang x segmen, height from OS |
| Kualitas opener | tension | two glass capsules filling with amber (SML) and red (NPL) to the real ratio |
| Tren opener | perspective | the timeseries becomes a 3D ribbon the camera travels along |
| Aksi opener | resolve | eight pillars, one per strategy, lit green or red by real trend |
| Close | closure, readiness | the ledger totals and the PPT action, held, not faded |

## The peak

"You scroll and you fall from space straight onto our kecamatan, and they grow up out of the map." Lives in the mapping section, owns 3.4 viewport heights, the largest span on the page. The act before it (market share tables) is flat and analytical on purpose.

## Tell-someone sentence

It's the site where our monthly numbers are built out of real objects, and you fall from orbit onto our own kecamatan.

## Authored silence

Daftar isi has no 3D object and no motion beyond reveal. That is deliberate rest before the market chapter, not dead scroll.

## Constraints carried from the codebase

- Same figures as the slide deck and PPT: every 3D object reads through the renderer's accessors.
- Reduced motion: scenes render one static final frame, no scroll travel.
- No WebGL: the existing CSS sculptures and the Leaflet map remain the fallback.
- Production is served from this folder, so the live build happens only after verification on an offline preview.

## Score (device per beat)

| Beat | Device | Why this one |
|---|---|---|
| Cover | `parallax` + WebGL planet | Stars, planet, cloud deck and beacon move at different rates; the title holds its own plane |
| Daftar isi | `flow` + `in` | Authored rest before the market chapter |
| Pasar opener | `pin` + WebGL ring | The BRI slice rises out of the industry ring as the act advances |
| Market share tables | `flow` + surface depth | Tables tilt up from below the page plane and settle flat for reading |
| Descent (peak) | bespoke pinned track, 3.4vh | Signature move; copy beats hand over on the same scroll clock |
| Dana opener | `pin` + WebGL coins | Coins drop one by one into stacks sized by real balances |
| Pinjaman opener | `pin` + WebGL skyline | Towers rise per cabang and segmen while the camera dollies |
| Kualitas opener | `pin` + WebGL capsules | Liquid fills to the real SML and NPL ratio on an honest 0 to 100% gauge |
| Tren opener | `pin` + WebGL ribbon | The camera travels along time; readings light up where it passes |
| Aksi opener | `pin` + WebGL pillars | Eight pillars rise in sequence, capstones lit by real trend sign |
| Close | `flow` | Ledger totals and actions held on the last ground |

## Feel check (2026-10-17, scrolled cold on the offline preview)

| Act | Intended | Felt | Change made |
|---|---|---|---|
| Cover | awe | awe, but the planet crowded the subtitle | planet moved right and lower, glint and atmosphere toned down |
| Pasar | curiosity | crowded, ring sat on the headline | ring pulled back and up, branch rings read from one legend |
| Descent | wonder, recognition | stage scrolled away, no pin | section overflow changed to clip; then it landed as intended |
| Descent close | recognition | Java texture blurred at altitude | quarter-degree graticule fades in and the planet quiets as the prisms rise |
| Dana | solidity | solidity | none |
| Pinjaman | momentum | towers read white, not glass | darker floor plates, tinted glass, Mikro in BRI amber |
| Kualitas | tension | title clipped by the kinetic mask | single-column copy, smaller display size |
| Tren | perspective | ribbon covered the headline | camera pulled back, subject shifted up |
| Aksi | resolve | pillars too close, labels cut | camera pulled back; portrait gets extra distance |

Peak check: the descent owns the largest span (3.4vh against 2.2vh for the next largest act) and is the largest visual change on the page. The act before it is the flat market-share table.

Not verified: a real phone (iOS Safari and Android Chrome GPU behaviour), and a logged-in session on the live server. Verification ran on an offline preview rendered from the same Blade view and the same captured payload.

---

# v3 (2026-10-26): depth everywhere

**Self-authored under explicit creative delegation.** Permintaan user, verbatim:

> sebagai profesional ui ux designer... aku ingin kamu benahi mode presentasi di landing page pinjaman dengan semua skill desainmu .. utamanya scrollcraft... memang sudah style apple tapi animasinya 3d premiumnya sangat sedikit.. buat agar lebih melimpah dan lebih interaktif.. untuk yang sekarang kuberi nilai 2/10 buat aku bangga

Target is the keynote layout (`resources/views/presentation/keynote.blade.php`), the default since the persistent-relief rebuild. Grammar (chaptered editorial) and journey are kept; the score changes.

## Diagnosis of the 2/10 (offline tour, 1440x900, 61 viewport-heights)

- 3D lives only in 7 statement screens, about 12 of 61 viewport-heights. The other ~49 are flat sheets whose only motion is "fade plus a 14px rise, once" (sheets.css header).
- The one 3D object never changes form: every chapter is the same relief, recoloured and re-lifted. Abundance of colour, not of 3D.
- Interaction is hidden: hover, drag and click on the relief only, nowhere else. Nothing on the page answers the pointer.
- Statement to sheet hand-off is a flat slide; the object just disappears under a page.

## Decisions

1. **The relief changes form, not only colour.** Pieces become independently movable. Hero: the map floats exploded in layers and the reader assembles it with the wheel. Dana and Pinjaman: the four branches pull apart into islands (exploded view by branch). Kualitas: the riskiest branch levitates out of the glass. Close: turntable.
2. **The agenda is a remote control.** The relief follows into the agenda; pointing at a chapter row morphs the map into that chapter's state, with its real labels.
3. **Every sheet tile is a 3D surface.** Scroll-scrubbed entry from depth (rotateX, translateZ, alternating yaw by column), pointer tilt with a specular glare, table rows flip in like a departure board, the statement recedes into depth as the sheet rises over it like a page.
4. Reduced motion: everything resolves to the flat final state. No WebGL: posters and flat tiles, same content.

## Feeling curve (v3)

| Act | Feeling | Cause |
|---|---|---|
| Hero | wonder, agency | exploded map assembles under the reader's own scroll |
| Agenda | play, orientation | pointing at a chapter previews it on the map |
| Pasar | curiosity | penetration heights per kecamatan |
| Pasar sheets | focus with depth | tiles rise from depth, rows flip in |
| Dana | solidity | branches separate into four islands, each its own height |
| Pinjaman | momentum | islands + data arcs from Madiun |
| **Kualitas (peak)** | **tension** | **dark theatre, glass, lit cores, the riskiest branch lifts out** |
| Tren | perspective | signed lift, up and down |
| Aksi, close | resolve | reassembled map on a turntable |

Peak stays Kualitas: largest span and the largest visual change (ground goes dark, material turns to glass).

## Feel check v3 (2026-10-26, offline preview, GPU headless 1440x900, 390x844, reduced motion)

| Act | Intended | Felt | Change made |
|---|---|---|---|
| Hero | wonder, agency | first cut too flat, pieces barely lifted | float offsets roughly doubled; now a layered cloud that docks by 62% of the hero |
| Agenda | play | labels collided with the scope line | stage moved down, headroom for labels; risk preview kept metal on the light ground |
| Dana / Pinjaman | solidity, momentum | islands read only on drag | heights now grow with the scroll first, islands separate after (0.3 to 0.6) with larger drift |
| Kualitas | tension | lifted branch read as a hole | lifted island also steps outward and its cores burn brighter |
| Sheets | focus with depth | tiles, rows and headings read as physical | none |
| Close | resolve | resolve | none |

Not verified: a real phone GPU, and a logged-in session on the live server.
