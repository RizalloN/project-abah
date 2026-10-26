# Verify

Apache serves `public/` of this repo, so `npm run build` changes production at
once and a Vite dev server (`public/hot`) would break remote users. Verify on an
offline render first.

## 1. Build to scratch and render offline

```bash
SP=<scratchpad>/pres            # holds public/, data/, boot.php, render-segments.php, server.mjs
npx vite build --outDir "$SP/public/build" --emptyOutDir
SP="$SP" php -d max_execution_time=0 "$SP/boot.php" "$SP/render-segments.php"
```

`boot.php` bootstraps Laravel and requires the script. The render script logs in
an Area user, sets `URL::forceRootUrl('http://127.0.0.1:4610')` and
`app()->usePublicPath("$SP/public")`, renders `presentation()` to
`public/index.html`, and dumps `presentationSegmentData` for every scope and
`presentationWilayahData` to `data/*.json`. Copy `public/data/*.geojson` into the
scratch public dir. Re-render after every build: hashed asset names change.

## 2. Serve and shoot

A tiny Node server maps `/dashboard/presentation-data/segments?scope=x` to
`data/segments-x.json`, `/wilayah` to `data/wilayah.json`, everything else to
the scratch build or the project `public/`. Check the port is free first; a
server left from an earlier session will silently serve stale files.

Shoot with Playwright and the real GPU, never SwiftShader (1.5 fps makes every
damped animation look unfinished):

```js
chromium.launch({ channel: 'chrome', args: ['--use-angle=d3d11', '--enable-gpu', '--ignore-gpu-blocklist'] })
```

Shoot each opener at 12/45/80% and each sheet at several depths, at 1440x900,
390x844, with `reducedMotion: 'reduce'`, and with `?scope=<branch>`. Build
contact sheets and read them. Then drive interactions: launcher choice + start,
a click on the relief (expect a `territoryfocus` event with a code and a new
caption), sector chips, legend toggles.

## 3. Tests, then deploy

```bash
php -d max_execution_time=0 vendor/bin/pest tests/Unit/PresentationSegmentDataServiceTest.php tests/Unit/PresentationScrollLayoutTest.php
npm run build
```

The segments view test needs the production manifest, so it passes only after
`npm run build`. Report what was verified offline and what was not (a logged-in
session on the live server, a real phone).
