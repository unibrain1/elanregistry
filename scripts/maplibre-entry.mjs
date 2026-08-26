// Thin ESM entry point bundled by esbuild (format: 'iife', globalName: 'maplibregl')
// into usersc/js/maplibre-gl.min.js. MapLibre GL JS 6.x ships ESM-only (no UMD
// bundle), so this wrapper re-exports everything as named exports, which
// esbuild attaches directly onto the IIFE global (`window.maplibregl.Map`,
// etc.) rather than nesting them under a `.default` property.
//
// It also points the worker loader at the co-located worker file (.js, not
// .mjs — see build.js) that scripts/build.js copies alongside this bundle.
// `import.meta.url` is not
// available in esbuild's IIFE output, so resolve the sibling worker URL from
// `document.currentScript` instead — the consuming pages load this bundle via
// a plain `<script src="...">` tag (not `type="module"`), so
// `document.currentScript` is reliably this script while it runs.
import { setWorkerUrl } from 'maplibre-gl';

export * from 'maplibre-gl';

setWorkerUrl(new URL('./maplibre-gl-worker.js', document.currentScript.src).toString());
