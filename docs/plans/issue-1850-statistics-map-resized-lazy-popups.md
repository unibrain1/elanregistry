# Issue #1850: statistics map pins download full-size originals instead of -resized-100

**Branch:** `issue/1850-statistics-map-resized-lazy-popups`
**Milestone:** `milestone/v2.29.6`
**Status:** Implemented — pending commit/PR

## Context

`app/owner/reports/statistics.php`'s MapLibre map builds one popup per car
marker eagerly, inside the `markers.forEach` loop in
`app/assets/js/statistics.js`, and each popup's image tag points at the
full-size original upload instead of the existing `-resized-100` thumbnail
variant. Production access-log analysis (in the issue) shows this page
downloads 100% originals vs. cars/index and cars/details pages' 100% resized
— worst-case single-visitor cost measured at 260 MB. The fix is two related,
independent client-side changes to the same function.

## UserSpice Integration

None — pure client-side JS change. No PHP/server-side code changes; `car.image`
already contains everything needed (`{carId}/{basename}.{ext}`) client-side.

## Database & Security Considerations

None — no schema, auth, or CSRF-relevant code touched. No new user input,
no new endpoints.

## Architecture & Design

Both changes are confined to `app/assets/js/statistics.js`'s
`buildPopupNode()` and the `markers.forEach` loop in `loadMapMarkers()`
(lines ~1403-1512).

**1. Use `-resized-100` thumbnail for the popup image (`buildPopupNode()`,
around line 1433).**

Mirror the existing client-side derivation already used in
`app/assets/js/imagedisplay.js:59-84` — split the filename at the last `.`,
insert `-resized-100` before the extension:

```js
if (car.image) {
  const img = document.createElement("img");
  const dotIndex = car.image.lastIndexOf(".");
  const resizedSrc = dotIndex === -1
    ? car.image
    : car.image.substring(0, dotIndex) + "-resized-100" + car.image.substring(dotIndex);
  img.src = window.statisticsConfig.imageUrl + resizedSrc;
  img.alt = "Car photo";
  img.style.cssText = "width:100px;height:75px;object-fit:cover;float:right;margin:0 0 8px 10px;border-radius:4px;";
  img.onerror = function () {
    this.style.display = "none";
  };
  wrap.appendChild(img);
}
```

Keeps the existing `onerror` fallback (hide broken img) as the safety net for
any car predating consistent thumbnail generation — Explore confirmed no
evidence of a legacy population lacking `-resized-100` (all 5 sizes generated
unconditionally on upload since before the 768px size was added), so no
additional fallback chain is needed.

**2. Build popup DOM content lazily, on first open, not eagerly per marker.**

No existing lazy-popup precedent in this codebase (`car-details-map.js` uses
a single marker with no popup at all) — introduce the standard MapLibre GL JS
pattern: create the `Popup` without content, attach it to the marker, and
build/set the DOM content on the popup's `'open'` event, once, guarded so a
reopen doesn't rebuild:

```js
markers.forEach(function (car) {
  const seriesClass = markerClassForSeries(car.series, car.variant);

  const el = document.createElement("div");
  el.className = "elan-marker-wrapper";
  const dot = document.createElement("div");
  dot.className = "elan-marker " + seriesClass;
  el.appendChild(dot);

  const popup = new maplibregl.Popup({ offset: 25 });
  let popupBuilt = false;
  popup.on("open", function () {
    if (!popupBuilt) {
      popup.setDOMContent(buildPopupNode(car));
      popupBuilt = true;
    }
  });

  new maplibregl.Marker({ element: el, anchor: "bottom" })
    .setLngLat([car.lon, car.lat])
    .setPopup(popup)
    .addTo(map);

  markerList.push({ seriesClass: seriesClass, el: el });
});
```

`'open'` (not marker `'click'`) is the correct hook — it fires regardless of
how the popup was triggered (click, keyboard, programmatic `.togglePopup()`),
matching MapLibre's own documented pattern for lazy popup content.

No other `car.image` or `-resized-` references exist in `statistics.js`
(confirmed via Explore) — these are the only two sites requiring changes.

## Implementation Checklist

- [x] Update `buildPopupNode()` in `app/assets/js/statistics.js` to derive
      and use the `-resized-100` filename for the popup image `src`,
      preserving the existing `onerror` fallback — `app/assets/js/statistics.js`
      (parallel-safe)
- [x] Update the `markers.forEach` loop in `loadMapMarkers()` in
      `app/assets/js/statistics.js` to build popup DOM content lazily via a
      `popup.on('open', ...)` handler instead of calling `buildPopupNode()`
      eagerly for every marker — `app/assets/js/statistics.js` (depends on:
      buildPopupNode update, same file/function neighborhood)
- [x] Run `npm run build` to regenerate minified JS output
- [x] Run `npm run lint` — confirm no ESLint errors introduced
- [x] Manually verify locally (MAMP): open `app/owner/reports/statistics.php`,
      confirm map pin popups still render photo + info on click, confirm
      Network tab shows `-resized-100` requests instead of originals —
      verified live: page load now fires 0 `userimages/` requests (was 1000
      eager originals pre-fix); clicking a marker fires exactly 1 request for
      the `-resized-100` variant (532,177 bytes original vs 2,892 bytes
      resized, 99.5% reduction); popups with and without a photo both render
      correctly
- [x] PHPStan baseline hygiene: N/A — no PHP files touched
- [x] Run `senior-architect` review of the diff, address findings — clean,
      no blocking issues; correctness of `-resized-100` derivation and
      `popup.on('open')` lazy-build lifecycle both verified (no listener
      accumulation, no memory leak, guard prevents rebuild on reopen)

## Test Plan

No existing Playwright coverage asserts on popup content or image `src`
patterns (`tests/playwright/maps-charts.spec.js` checked — only asserts map
container render and `window.elanMapMarkers` presence). Given this is a
narrowly-scoped client-side performance fix with a clear before/after visual
and network-level verification, and no established test precedent for popup
internals in this codebase, verification is manual (Network-tab check on
MAMP) rather than new automated Playwright coverage. If reviewers want
regression protection, a follow-up could add an assertion on `img.src`
containing `-resized-100` after a marker click — noted as a deferred
enhancement, not blocking this fix.

## Documentation Plan

None — no public API, schema, or user-facing doc describes this internal
JS behavior.
