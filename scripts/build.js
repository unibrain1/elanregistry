'use strict';

const esbuild = require('esbuild');
const fs = require('fs');

const jsFiles = [
  'app/assets/js/api-client.js',
  'app/assets/js/statistics.js',
  'app/assets/js/location-picker.js',
  'app/assets/js/car_details.js',
  'app/assets/js/imagedisplay.js',
  'app/assets/js/highlightDifferences.js',
  'app/assets/js/model-loader.js',
  'app/assets/js/car-showcase.js',
  'app/assets/js/car-list.js',
  'app/assets/js/factory-list.js',
  'app/assets/js/car-details-map.js',
  'app/assets/js/car-edit.js',
  'app/assets/js/contact-form.js',
  'app/assets/js/join-form-beacon.js',
  'app/admin/assets/admin-core.js',
  'app/admin/assets/backup-operations.js',
  'app/admin/assets/js/design-system.js',
  'app/admin/assets/js/tab-manage-cars.js',
  'app/admin/assets/js/tab-account-cleanup.js',
  'app/admin/assets/js/tab-owner-mgmt.js',
  'app/admin/assets/js/tab-settings.js',
  'app/admin/assets/js/load-owner-profile.js',
];

const cssFiles = [
  'app/assets/css/edit_car.css',
  'app/assets/css/location-picker.css',
  'app/admin/assets/admin-core.css',
];

const vendorFiles = [
  ['node_modules/filepond/dist/filepond.min.js', 'usersc/js/filepond.min.js'],
  ['node_modules/filepond/dist/filepond.min.css', 'usersc/css/filepond.min.css'],
  ['node_modules/filepond-plugin-image-exif-orientation/dist/filepond-plugin-image-exif-orientation.min.js', 'usersc/js/filepond-plugin-image-exif-orientation.min.js'],
  ['node_modules/filepond-plugin-file-validate-type/dist/filepond-plugin-file-validate-type.min.js', 'usersc/js/filepond-plugin-file-validate-type.min.js'],
  ['node_modules/filepond-plugin-file-validate-size/dist/filepond-plugin-file-validate-size.min.js', 'usersc/js/filepond-plugin-file-validate-size.min.js'],
  ['node_modules/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.min.js', 'usersc/js/filepond-plugin-image-preview.min.js'],
  ['node_modules/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.min.css', 'usersc/css/filepond-plugin-image-preview.min.css'],
  ['node_modules/filepond-plugin-image-resize/dist/filepond-plugin-image-resize.min.js', 'usersc/js/filepond-plugin-image-resize.min.js'],
  ['node_modules/filepond-plugin-image-transform/dist/filepond-plugin-image-transform.min.js', 'usersc/js/filepond-plugin-image-transform.min.js'],
];

Promise.all([
  ...jsFiles.map(f => esbuild.build({ entryPoints: [f], minify: true, outfile: f.replace(/\.js$/, '.min.js') })),
  ...cssFiles.map(f => esbuild.build({ entryPoints: [f], minify: true, outfile: f.replace(/\.css$/, '.min.css') })),
]).then(async () => {
  console.log(`Built ${jsFiles.length + cssFiles.length} files.`);

  for (const [src, dest] of vendorFiles) {
    fs.copyFileSync(src, dest);
  }
  console.log(`Copied ${vendorFiles.length} vendor files.`);

  // Copy MapLibre GL JS self-hosted assets
  fs.copyFileSync('node_modules/maplibre-gl/dist/maplibre-gl.js', 'usersc/js/maplibre-gl.min.js');
  fs.copyFileSync('node_modules/maplibre-gl/dist/maplibre-gl.css', 'usersc/css/maplibre-gl.css');
  console.log('Copied MapLibre GL JS assets.');

  // Copy Chart.js self-hosted asset
  fs.copyFileSync('node_modules/chart.js/dist/chart.umd.min.js', 'usersc/js/chart.umd.min.js');
  console.log('Copied Chart.js asset.');

  // DataTables: each -bs5 package is a thin Bootstrap 5 styling wrapper —
  // the functional code lives in the corresponding non-bs5 core package.
  // Concatenate core + bs5 wrapper per extension (each is a self-contained
  // UMD IIFE, safe to concatenate) so app pages keep loading one script tag
  // per extension. Only Core, FixedHeader, and Responsive are used in app
  // JS (see ADR-011) — Buttons/ColVis are intentionally excluded.
  const concatFiles = (sources, dest) =>
    fs.writeFileSync(dest, sources.map(src => fs.readFileSync(src, 'utf8')).join(''));

  concatFiles(
    [
      'node_modules/datatables.net/js/dataTables.min.js',
      'node_modules/datatables.net-bs5/js/dataTables.bootstrap5.min.js',
    ],
    'usersc/js/datatables.min.js'
  );
  concatFiles(
    [
      'node_modules/datatables.net-fixedheader/js/dataTables.fixedHeader.min.js',
      'node_modules/datatables.net-fixedheader-bs5/js/fixedHeader.bootstrap5.min.js',
    ],
    'usersc/js/datatables-fixedheader.min.js'
  );
  concatFiles(
    [
      'node_modules/datatables.net-responsive/js/dataTables.responsive.min.js',
      'node_modules/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js',
    ],
    'usersc/js/datatables-responsive.min.js'
  );
  concatFiles(
    [
      'node_modules/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
      'node_modules/datatables.net-fixedheader-bs5/css/fixedHeader.bootstrap5.min.css',
      'node_modules/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css',
    ],
    'usersc/css/datatables.min.css'
  );
  console.log('Vendored DataTables (core, fixedheader, responsive) assets.');

  // Generate VersaTiles Colorful style JSON
  const { colorful } = await import('@versatiles/style');
  const style = colorful({ baseUrl: 'https://tiles.versatiles.org', language: 'en' });
  fs.writeFileSync('usersc/js/versatiles-colorful.json', JSON.stringify(style));
  console.log('Generated usersc/js/versatiles-colorful.json');
}).catch((err) => {
  console.error('Build failed:', err?.stack ?? err?.message ?? err);
  process.exit(1);
});
