const { test, expect } = require('@playwright/test');

// Escapes regex metacharacters so an expectedTitle string can be used inside
// a RegExp for a partial (contains) match against the real <title> tag,
// which appends " {site_name}" after the page-specific title (see
// users/template/header1_must_include.php).
function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

test.describe('Elan Registry - All Pages (Not Logged In)', () => {
  // Skip these tests if running in logged-in project
  test.beforeEach(async ({ }, testInfo) => {
    if (testInfo.project.name !== 'not-logged-in') {
      testInfo.skip();
    }
  });
  const pages = [
    {
      path: '/',
      name: 'Home',
      selector: 'h1',
      expectedText: 'Lotus Elan Registry',
    },
    {
      path: '/app/owner/cars/index.php',
      name: 'List Cars',
      selector: 'h2',
      expectedText: 'Registry Cars',
      expectedTitle: 'Browse All Registered Cars',
      expectedDescription: 'Search and browse every Lotus Elan and Elan Plus 2 currently registered, with chassis, model, and ownership history details.',
    },
    {
      path: '/users/join.php',
      name: 'Register',
      selector: 'h1.h3.text-primary',
      expectedText: 'Join the Lotus Elan Registry',
    },
    {
      path: '/app/owner/reports/statistics.php',
      name: 'Statistics',
      selector: 'h1',
      expectedText: 'Registry Analytics & Statistics',
      expectedTitle: 'Registry Analytics & Statistics',
      expectedDescription: 'Explore production trends, geographic distribution, paint colour popularity, and data-completeness statistics across the Lotus Elan Registry.',
    },
    {
      path: '/docs/reference/identification-guide.php',
      name: 'Identification Guide',
      selector: 'h1',
      expectedText: 'Lotus Elan Identification Guide',
      expectedTitle: 'How to Identify a Lotus Elan or Elan Plus 2',
      expectedDescription: 'Identify your Lotus Elan or Elan Plus 2 variant by chassis number, body style, and distinguishing features like Roadster, Drophead, and Coupé.',
    },
    {
      path: '/app/owner/cars/factory.php',
      name: 'Factory Data',
      selector: 'h2',
      expectedText: 'Elan Factory Information',
      expectedTitle: 'Lotus Elan Factory Build Records — Registry Factory Data',
      expectedDescription: 'Browse original factory build records for registered Lotus Elan and Elan Plus 2 cars, cross-referenced against registry ownership data.',
    },
    {
      path: '/docs/',
      name: 'Docs Index',
      selector: 'h1',
      expectedText: 'Documentation',
      expectedTitle: 'Documentation Hub',
      expectedDescription: 'Guides, technical references, and car histories for Lotus Elan and Elan Plus 2 owners, organized by topic.',
    },
    {
      path: '/docs/reference/index.php',
      name: 'Reference Index',
      selector: 'h1',
      expectedText: 'Technical Reference',
      expectedTitle: 'Technical Reference Library',
      expectedDescription: 'Workshop manuals, parts lists, technical articles, and identification guides for the Lotus Elan and Elan Plus 2.',
    },
    {
      path: '/docs/reference/chassis-validation.php',
      name: 'Chassis Validation',
      selector: 'h1',
      expectedText: 'Chassis Validation Rules',
      expectedTitle: 'Lotus Elan Chassis Number Formats',
      expectedDescription: 'Reference guide to the chassis numbering formats the Lotus Elan Registry recognizes and validates during car registration.',
    },
    {
      path: '/docs/reference/paint-colors.php',
      name: 'Paint Colors',
      selector: 'h1',
      expectedText: 'Lotus Elan',
      expectedTitle: 'Lotus Elan Paint Codes — Official Colour Chart L01–L26',
      expectedDescription: 'Complete reference for Lotus Elan and Elan Plus 2 paint codes L01–L26 with colour chips, date ranges, model applicability, and early pre-code colours.',
    },
    {
      path: '/docs/reference/technical-articles.php',
      name: 'Technical Articles',
      selector: 'h1',
      expectedText: 'Technical Articles',
      expectedTitle: 'Lotus Elan Technical Articles — Club Lotus Reference Archive',
      expectedDescription: 'Historical Club Lotus technical articles covering maintenance, engineering, and restoration topics for the Lotus Elan and Elan Plus 2.',
    },
    {
      path: '/docs/reference/workshop.php',
      name: 'Workshop & Parts',
      selector: 'h1',
      expectedText: 'Workshop',
      expectedTitle: 'Lotus Elan Workshop Manuals & Parts References',
      expectedDescription: 'Workshop manuals, parts lists, and engine reference documents for maintaining and restoring the Lotus Elan and Elan Plus 2.',
    },
    {
      path: '/docs/car-stories.php',
      name: 'Car Stories',
      selector: 'h1',
      expectedText: 'Car Stories',
      expectedTitle: 'Lotus Elan Car Stories — Registry Ownership Histories',
      expectedDescription: 'Read individual ownership histories and stories for Lotus Elan and Elan Plus 2 cars in the registry.',
    },
    {
      path: '/docs/stories/brian_walton/index.php',
      name: 'Brian Walton Story',
      selector: 'h1',
      expectedText: 'Elan Experimental Rally Car',
    },
    {
      path: '/docs/stories/SGO_2F/index.php',
      name: 'SGO 2F Story',
      selector: 'h1',
      expectedText: 'SGO 2F',
    },
    {
      path: '/docs/stories/type26register.php',
      name: 'Type 26 Register',
      selector: 'h2',
      expectedText: 'type26register.com',
    },
    {
      path: '/docs/guides/index.php',
      name: 'Owner Guides',
      selector: 'h1',
      expectedText: 'Owner Guides',
      expectedTitle: 'Owner Guides',
      expectedDescription: 'Practical guides for Lotus Elan and Elan Plus 2 owners, covering registration, transfers, and car management.',
    },
    {
      path: '/docs/guides/car-transfer-faq.php',
      name: 'Car Transfer FAQ',
      selector: 'h1',
      expectedText: 'Car Transfer FAQ',
    },
    {
      path: '/docs/pdf-viewer.php',
      name: 'PDF Viewer',
      selector: 'h1',
      expectedText: 'Document Viewer',
    },
    {
      path: 'usersc/login.php',
      name: 'Log In',
      selector: '.modal-header',
      expectedText: 'Please Log In',
      isLoginPage: true,
    },
    {
      path: '/users/forgot_password.php',
      name: 'Forgot Password',
      selector: 'h2',
      expectedText: 'Reset Password',
    },
  ];

  test('page-specific titles are unique across all pages (#1432)', () => {
    const titles = pages.map(p => p.expectedTitle).filter(Boolean);
    expect(new Set(titles).size).toBe(titles.length);
  });

  test('page-specific descriptions are unique across all pages (#1432)', () => {
    const descriptions = pages.map(p => p.expectedDescription).filter(Boolean);
    expect(new Set(descriptions).size).toBe(descriptions.length);
  });

  pages.forEach(({ path, name, selector, expectedText, isLoginPage, expectedTitle, expectedDescription }) => {
    test(`should be able to reach ${name} page`, async ({ page }) => {
      const response = await page.goto(path);

      // Layer 1: HTTP response must be successful
      expect(response.status()).toBeLessThan(400);

      await page.waitForLoadState('domcontentloaded');

      // Layer 2: Must not have been redirected to the login page
      // (skip this check for the login page itself)
      if (!isLoginPage) {
        expect(page.url()).not.toContain('login.php');
      }

      // Layer 3: Verify expected page content is present
      if (selector && expectedText) {
        await expect(page.locator(selector)).toContainText(expectedText);
      }

      // Layer 4: Verify page-specific title and meta description (#1432)
      if (expectedTitle) {
        // The <title> tag appends " {site_name}" after $pageTitle (see
        // users/template/header1_must_include.php), so this is a partial
        // (contains) match rather than an exact one.
        await expect(page).toHaveTitle(new RegExp(escapeRegExp(expectedTitle)));

        // og:title/twitter:title mirror $pageTitle exactly, with no
        // site-name suffix (see usersc/includes/head_tags.php), so these
        // are exact matches.
        const ogTitle = await page.locator('meta[property="og:title"]').getAttribute('content');
        expect(ogTitle).toBe(expectedTitle);
        const twitterTitle = await page.locator('meta[name="twitter:title"]').getAttribute('content');
        expect(twitterTitle).toBe(expectedTitle);
      }
      if (expectedDescription) {
        // $site_description is $pageDescription verbatim, with no suffix (unlike
        // $pageTitle's <title> tag above), so these are exact matches.
        const description = await page.locator('meta[name="description"]').getAttribute('content');
        expect(description).toBe(expectedDescription);

        // og:description/twitter:description share $site_description with the
        // meta description tag (see usersc/includes/head_tags.php), so they
        // pick up $pageDescription the same way.
        const ogDescription = await page.locator('meta[property="og:description"]').getAttribute('content');
        expect(ogDescription).toBe(expectedDescription);
        const twitterDescription = await page.locator('meta[name="twitter:description"]').getAttribute('content');
        expect(twitterDescription).toBe(expectedDescription);
      }

      console.log(`✓ Successfully reached: ${name} (${path})`);
    });
  });
});

// paint-colors.php's page-specific <title>/meta description assertions
// (#1372) are now covered by the table-driven pages[] array above via
// expectedTitle/expectedDescription (#1432). The regression guard below
// (confirming the generic site-wide title/description still renders on
// pages outside #1432's scope) is retained, retargeted at
// car-transfer-faq.php since identification-guide.php now has a
// page-specific title of its own.
test('docs/guides/car-transfer-faq.php still renders the generic site title/description (regression guard) (#1432)', async ({ page }, testInfo) => {
  if (testInfo.project.name !== 'not-logged-in') {
    testInfo.skip();
  }

  await page.goto('/docs/guides/car-transfer-faq.php');

  await expect(page).toHaveTitle(/^ Lotus Elan Registry$/);

  const description = await page
    .locator('meta[name="description"]')
    .getAttribute('content');
  expect(description).toContain('Registry for the Lotus Elan (1963-1973) and Elan Plus 2 (1967-1974)');

  // og:title/twitter:title must still fall back to $site_title (the generic
  // site name) on pages that don't set $pageTitle — this is the actual new
  // conditional in usersc/includes/head_tags.php ($og_title) introduced by
  // #1432, and this is its only regression coverage.
  const ogTitle = await page.locator('meta[property="og:title"]').getAttribute('content');
  expect(ogTitle).toBe('Lotus Elan Registry');
  const twitterTitle = await page.locator('meta[name="twitter:title"]').getAttribute('content');
  expect(twitterTitle).toBe('Lotus Elan Registry');
});

test.describe('Internal Links Discovery and Testing (Not Logged In)', () => {
  const pages = [
    { path: '/', name: 'Home' },
    { path: '/app/owner/cars/index.php', name: 'List Cars' },
    { path: '/users/join.php', name: 'Register' },
    { path: '/app/owner/reports/statistics.php', name: 'Statistics' },
    { path: '/docs/reference/identification-guide.php', name: 'Identification Guide' },
    { path: '/app/owner/cars/factory.php', name: 'Factory Data' },
    { path: '/docs/reference/index.php', name: 'Reference Index' },
    { path: '/docs/car-stories.php', name: 'Car Stories' },
    { path: '/docs/guides/index.php', name: 'Owner Guides' },
    { path: 'usersc/login.php', name: 'Log In' },
    { path: '/users/forgot_password.php', name: 'Forgot Password' },
  ];

  test('find all internal links across all pages (excluding header)', async ({ page }) => {
    const allInternalLinks = new Map(); // Use Map to track unique links with their source pages

    for (const { path, name } of pages) {
      await page.goto(path);
      await page.waitForLoadState('domcontentloaded');

      // Get all links NOT in the header/nav
      const contentLinks = await page.locator('a:not(header a, nav a)').all();

      for (const link of contentLinks) {
        const href = await link.getAttribute('href');
        const text = await link.textContent();

        // Filter for internal links (elanregistry.org or relative paths)
        // Security: Use proper URL parsing to validate hostname, not substring matching
        let isInternalLink = href && href.startsWith('/');
        if (href && href.startsWith('http')) {
          try {
            const url = new URL(href);
            isInternalLink = url.hostname === 'elanregistry.org' || url.hostname === 'www.elanregistry.org';
          } catch (_e) {
            isInternalLink = false;
          }
        }

        if (isInternalLink) {
          // Convert to relative path if it's a full URL
          const relativePath = href.startsWith('http')
            ? new URL(href).pathname
            : href;

          // Track which page this link was found on
          if (!allInternalLinks.has(relativePath)) {
            allInternalLinks.set(relativePath, {
              url: relativePath,
              text: text?.trim(),
              foundOn: [name],
            });
          } else {
            // Add this page to the list of pages where this link was found
            const existing = allInternalLinks.get(relativePath);
            if (!existing.foundOn.includes(name)) {
              existing.foundOn.push(name);
            }
          }
        }
      }

      console.log(`✓ Scanned ${name} (${path})`);
    }

    console.log('\n=== All Internal Links Found (Excluding Header) ===');
    console.log(`Total unique internal links: ${allInternalLinks.size}\n`);

    const sortedLinks = Array.from(allInternalLinks.values()).sort((a, b) =>
      a.url.localeCompare(b.url)
    );

    sortedLinks.forEach((link, index) => {
      console.log(`${index + 1}. ${link.url}`);
      console.log(`   Text: ${link.text || '(no text)'}`);
      console.log(`   Found on: ${link.foundOn.join(', ')}\n`);
    });

    // Verify we found at least some links
    expect(allInternalLinks.size).toBeGreaterThan(0);
  });

  test('test all unique internal links found across all pages', async ({ page }) => {
    test.setTimeout(120000); // 2 minutes for testing 50+ links on production
    const allInternalLinks = new Map();
    // All discovery pages except Forgot Password count as public pages for warning purposes
    const publicPageNames = pages.filter(p => p.name !== 'Forgot Password').map(p => p.name);

    console.log('\n=== Discovering Internal Links ===');
    for (const { path, name } of pages) {
      await page.goto(path);
      await page.waitForLoadState('domcontentloaded');

      const contentLinks = await page.locator('a:not(header a, nav a)').all();

      for (const link of contentLinks) {
        const href = await link.getAttribute('href');

        // Security: Use proper URL parsing to validate hostname, not substring matching
        let isInternalLink = href && href.startsWith('/');
        if (href && href.startsWith('http')) {
          try {
            const url = new URL(href);
            isInternalLink = url.hostname === 'elanregistry.org' || url.hostname === 'www.elanregistry.org';
          } catch (_e) {
            isInternalLink = false;
          }
        }

        if (isInternalLink) {
          // Convert to relative path if it's a full URL
          const relativePath = href.startsWith('http')
            ? new URL(href).pathname
            : href;

          // Track which page this link was found on
          if (!allInternalLinks.has(relativePath)) {
            allInternalLinks.set(relativePath, {
              url: relativePath,
              foundOn: [name]
            });
          } else {
            const existing = allInternalLinks.get(relativePath);
            if (!existing.foundOn.includes(name)) {
              existing.foundOn.push(name);
            }
          }
        }
      }
    }

    const uniqueLinks = Array.from(allInternalLinks.values()).map(link => link.url).sort();

    // Separate links into navigable pages and downloadable files
    const downloadExtensions = ['.pdf', '.zip', '.doc', '.docx', '.xls', '.xlsx', '.jpg', '.jpeg', '.png', '.gif', '.svg'];
    const navigableLinks = [];
    const downloadableLinks = [];

    uniqueLinks.forEach(link => {
      const isDownloadable = downloadExtensions.some(ext => link.toLowerCase().endsWith(ext));
      if (isDownloadable) {
        downloadableLinks.push(link);
      } else {
        // Exclude /app/owner/cars/details.php links except for car_id=1 (to avoid testing many individual car pages)
        if (link.includes('/app/owner/cars/details.php') && !link.includes('car_id=1')) {
          // Skip this link - it's a car details page other than car_id=1
          return;
        }
        navigableLinks.push(link);
      }
    });

    console.log(`\n=== Testing Links ===`);
    console.log(`Navigable pages: ${navigableLinks.length}`);
    console.log(`Downloadable files: ${downloadableLinks.length}`);
    console.log(`Total unique links: ${uniqueLinks.length}`);
    console.log(`Links from public pages: ${allInternalLinks.size}\n`);

    let successCount = 0;
    let failCount = 0;
    let protectedLinksFromPublic = []; // Track protected pages linked from public pages

    // Test navigable pages
    console.log('=== Testing Navigable Pages ===\n');
    for (const linkPath of navigableLinks) {
      try {
        const response = await page.goto(linkPath);
        const status = response.status();
        const currentUrl = page.url();
        const redirectsToLogin = currentUrl.includes('login.php');

        if (status < 400) {
          if (redirectsToLogin) {
            // This link is protected (redirects to login)
            const linkData = allInternalLinks.get(linkPath);
            const foundOn = linkData ? linkData.foundOn : [];
            const foundOnPublic = foundOn.some(name => publicPageNames.includes(name));

            if (foundOnPublic) {
              // Protected link found on public page - log as warning
              protectedLinksFromPublic.push({
                link: linkPath,
                foundOn: foundOn
              });
              console.log(`⚠️  ${linkPath} - Protected (requires login, found on: ${foundOn.join(', ')})`);
            } else {
              // Protected link found on non-public pages - OK
              console.log(`✓ ${linkPath} - Protected (found on non-public pages)`);
              successCount++;
            }
          } else {
            successCount++;
            console.log(`✓ ${linkPath} - Status: ${status}`);
          }
        } else {
          failCount++;
          console.log(`✗ ${linkPath} - Status: ${status}`);
        }

        expect(status).toBeLessThan(400);
      } catch (error) {
        failCount++;
        console.log(`✗ ${linkPath} - Error: ${error.message}`);
        throw error;
      }
    }

    // Report protected links found on public pages
    if (protectedLinksFromPublic.length > 0) {
      console.log('\n=== Protected Pages Linked From Public Pages ===\n');
      protectedLinksFromPublic.forEach((item, index) => {
        console.log(`${index + 1}. ${item.link}`);
        console.log(`   Found on: ${item.foundOn.join(', ')}\n`);
      });
    }

    // Test downloadable files using fetch API
    console.log('\n=== Testing Downloadable Files ===\n');
    for (const linkPath of downloadableLinks) {
      try {
        const context = page.context();
        const baseURL = 'https://elanregistry.org';
        const fullURL = linkPath.startsWith('http') ? linkPath : baseURL + linkPath;

        // Use API request context to check if file exists without downloading
        const response = await context.request.head(fullURL);
        const status = response.status();

        if (status < 400) {
          successCount++;
          console.log(`✓ ${linkPath} - Status: ${status} (file exists)`);
        } else {
          failCount++;
          console.log(`✗ ${linkPath} - Status: ${status}`);
        }

        expect(status).toBeLessThan(400);
      } catch (error) {
        failCount++;
        console.log(`✗ ${linkPath} - Error: ${error.message}`);
        throw error;
      }
    }

    console.log(`\n=== Results ===`);
    console.log(`Total links tested: ${uniqueLinks.length}`);
    console.log(`Navigable pages: ${navigableLinks.length}`);
    console.log(`Downloadable files: ${downloadableLinks.length}`);
    console.log(`Successful: ${successCount}`);
    console.log(`Failed: ${failCount}`);
  });
});

test.describe('Redirect verification — GSC 404 and soft 404 cleanup (#1369)', () => {
  test.beforeEach(async ({ }, testInfo) => {
    if (testInfo.project.name !== 'not-logged-in') {
      testInfo.skip();
    }
  });

  const BASE = 'https://elanregistry.org';

  const redirects = [
    {
      from: '/docs/embed.php?doc=Elan_26_36_Workshop_Manual.pdf',
      to: '/docs/pdf-viewer.php?subdir=reference&doc=Elan_26_36_Workshop_Manual.pdf',
      label: 'embed.php soft 404 → pdf-viewer.php with subdir',
    },
    {
      from: '/docs/pdf-viewer.php?doc=Elan_26_36_Workshop_Manual.pdf',
      to: '/docs/pdf-viewer.php?subdir=reference&doc=Elan_26_36_Workshop_Manual.pdf',
      label: 'pdf-viewer.php single-param → two-param format',
    },
    {
      from: '/stories/type26register.com/index.html',
      to: '/docs/stories/type26register.com/index.html',
      label: 'stories/* migration',
    },
    // Legacy doc viewer removed in #911
    {
      from: '/docs/view.php?doc=IDENTIFICATION_GUIDE.md',
      to: '/docs/reference/identification-guide.php',
      label: 'docs/view.php IDENTIFICATION_GUIDE.md',
    },
    {
      from: '/docs/view.php?doc=CAR_TRANSFER_FAQ.md',
      to: '/docs/guides/car-transfer-faq.php',
      label: 'docs/view.php CAR_TRANSFER_FAQ.md',
    },
    {
      from: '/docs/guide-viewer.php?doc=CAR_TRANSFER_FAQ.md',
      to: '/docs/guides/car-transfer-faq.php',
      label: 'docs/guide-viewer.php CAR_TRANSFER_FAQ.md',
    },
    {
      from: '/docs/guide-viewer.php?doc=CAR_TRANSFER_USER_GUIDE.md',
      to: '/docs/guides/',
      label: 'docs/guide-viewer.php CAR_TRANSFER_USER_GUIDE.md',
    },
    {
      from: '/app/car_details.php?car_id=100',
      to: '/app/owner/cars/details.php?car_id=100',
      label: '/app/car_details.php preserves car_id query string',
    },
    {
      from: '/app/identification.php',
      to: '/docs/reference/identification-guide.php',
      label: '/app/identification.php legacy path',
    },
    {
      from: '/app/list_cars.php',
      to: '/app/owner/cars/index.php',
      label: '/app/list_cars.php legacy path',
    },
    {
      from: '/list_cars.php',
      to: '/app/owner/cars/index.php',
      label: '/list_cars.php root-level legacy path',
    },
    {
      from: '/guide.php',
      to: '/docs/',
      label: '/guide.php legacy guide index',
    },
    // Duplicate PDF path: /docs/assets/ renamed to /docs/reference/assets/ in #715
    {
      from: '/docs/assets/Elan_26_36_Workshop_Manual.pdf',
      to: '/docs/reference/assets/Elan_26_36_Workshop_Manual.pdf',
      label: '/docs/assets/ → /docs/reference/assets/ (duplicate PDF path)',
    },
  ];

  redirects.forEach(({ from, to, label }) => {
    test(`301: ${label}`, async ({ request }) => {
      const response = await request.get(`${BASE}${from}`, { maxRedirects: 0 });
      expect(response.status(), `Expected 301 for ${from}`).toBe(301);
      const location = response.headers()['location'] ?? '';
      // Normalize absolute Location headers to path + query for comparison
      const locationPath = location.startsWith('http')
        ? new URL(location).pathname + new URL(location).search
        : location;
      expect(locationPath, `Expected Location: ${to} for ${from}`).toBe(to);
    });
  });
});

test.describe('GSC 404 cleanup redirects (#1409)', () => {
  test.beforeEach(async ({ }, testInfo) => {
    if (testInfo.project.name !== 'not-logged-in') {
      testInfo.skip();
    }
  });

  const BASE = 'https://elanregistry.org';

  const redirects = [
    {
      from: '/docs/guide-viewer.php?doc=ADD_CAR_GUIDE.md',
      to: '/docs/guides/',
      label: 'docs/guide-viewer.php ADD_CAR_GUIDE.md',
    },
    {
      from: '/app/manage_cars.php',
      to: '/app/owner/cars/index.php',
      label: '/app/manage_cars.php legacy path',
    },
    {
      from: '/app/edit_car.php',
      to: '/app/owner/cars/edit.php',
      label: '/app/edit_car.php legacy path',
    },
    {
      from: '/app/statistics.php',
      to: '/app/owner/reports/statistics.php',
      label: '/app/statistics.php legacy path',
    },
    {
      from: '/docs/guide-viewer.php?doc=PRIVACY.md',
      to: '/app/owner/privacy.php',
      label: 'docs/guide-viewer.php PRIVACY.md',
    },
    {
      from: '/docs/stories/type26registry/',
      to: '/docs/stories/type26register.com/',
      label: 'docs/stories/type26registry/ typo-fix redirect',
    },
    {
      from: '/docs/reference/assets/Elan_S1_S2_Coupe_Masterpartslist.pdf',
      to: '/docs/reference/assets/elan_s1_s2_coupe_masterpartslist.pdf',
      label: 'PDF asset case mismatch: capitalized legacy URL → renamed lowercase asset',
    },
    {
      from: '/embed.php?doc=elan_s1_s2_coupe_masterpartslist.pdf',
      to: '/docs/pdf-viewer.php?subdir=reference&doc=elan_s1_s2_coupe_masterpartslist.pdf',
      label: 'root embed.php soft 404 → pdf-viewer.php',
    },
  ];

  redirects.forEach(({ from, to, label }) => {
    test(`301: ${label}`, async ({ request }) => {
      const response = await request.get(`${BASE}${from}`, { maxRedirects: 0 });
      expect(response.status(), `Expected 301 for ${from}`).toBe(301);
      const location = response.headers()['location'] ?? '';
      // Normalize absolute Location headers to path + query for comparison
      const locationPath = location.startsWith('http')
        ? new URL(location).pathname + new URL(location).search
        : location;
      expect(locationPath, `Expected Location: ${to} for ${from}`).toBe(to);
    });
  });

  test('renamed PDF asset (lowercase) returns 200', async ({ request }) => {
    const response = await request.get(
      `${BASE}/docs/reference/assets/elan_s1_s2_coupe_masterpartslist.pdf`
    );
    expect(response.status()).toBe(200);
  });

  test('embed.php?doc=... regression: lands on a working pdf-viewer.php page, not "Invalid document path."', async ({ page }) => {
    const response = await page.goto(`${BASE}/embed.php?doc=elan_s1_s2_coupe_masterpartslist.pdf`);

    expect(response.status()).toBeLessThan(400);
    await page.waitForLoadState('domcontentloaded');

    // Must land on the PDF viewer, not be bounced to the login page
    expect(page.url()).toContain('/docs/pdf-viewer.php');
    expect(page.url()).not.toContain('login.php');

    // Regression guard: the pre-fix redirect used an invalid `subdir` value,
    // which pdf-viewer.php rejected with this exact error text (#1409).
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).not.toContain('Invalid document path.');

    // Positive assertion: the viewer actually rendered the iframe pointing at
    // the document, not just an error-free page. The <h1> alone isn't a
    // reliable signal here — pdf-viewer.php computes it via pathinfo() before
    // the subdir/file-existence checks run, so it renders the same filename
    // whether or not those checks pass. The iframe only renders in the
    // success branch, so its src is the actual discriminator.
    await expect(page.locator('iframe')).toHaveAttribute(
      'src',
      /elan_s1_s2_coupe_masterpartslist\.pdf$/
    );
  });
});

test.describe('Location picker city disambiguation (#1400)', () => {
  test.beforeEach(async ({ }, testInfo) => {
    if (testInfo.project.name !== 'not-logged-in') {
      testInfo.skip();
    }
  });

  // Live Nominatim/Photon results are required for this test, so it only runs
  // against deployed environments (npm run test:e2e / test:e2e:test) — this
  // file only executes via playwright.config.prod.js / playwright.config.test.js,
  // never locally against MAMP (see playwright.config.js's testIgnore: '**/e2e/**').
  //
  // This is an integration smoke check, not the primary regression guard for
  // the dedupe-key fix itself — see tests/playwright/location-picker-dedupe.spec.js
  // for a deterministic, mock-data test of filterAndRankResults() directly.
  // A plain "more than one result" count would pass even without the fix,
  // since Photon/Nominatim already returns Springfields from *different
  // countries* as distinct entries under the old city|country key too — the
  // bug was specifically about same-country, different-state collisions. So
  // this asserts on distinct *same-country* (United States) result text
  // instead of a raw count.
  test('searching an ambiguous city name shows multiple distinct same-country results (regression guard)', async ({ page }) => {
    await page.goto('/users/join.php');

    const input = page.locator('#location-picker-registration-input');
    const resultsContainer = page.locator('#location-picker-registration-results');

    await input.fill('Springfield');

    // The picker debounces input by 300ms before firing the search request;
    // wait for the results list to actually populate rather than a fixed
    // sleep, since the live geocoding API latency varies.
    await expect(async () => {
      // Query <small> elements directly rather than iterating .list-group-item
      // and locating a child inside each — the "No locations found" fallback
      // item has no <small> child, so this avoids a throw on that render.
      const texts = await resultsContainer.locator('.list-group-item small').allTextContents();
      const distinctUsResults = new Set(texts.map(t => t.trim()).filter(t => t.includes('United States')));
      expect(distinctUsResults.size).toBeGreaterThan(1);
    }).toPass({ timeout: 15000 });
  });
});
