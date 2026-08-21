// tests/playwright/admin-page-titles.spec.js
//
// Behavioral verification for #1430: app/admin/index.php,
// app/admin/maintenance.php, and app/admin/design-system.php set
// $pageTitle/$pageDescription before requiring users/init.php (previously
// they were set after, so the page-specific title never took effect and the
// generic site title rendered instead). This confirms the actual rendered
// <title> now reflects the page-specific title for each tab.
//
// Requires local MAMP at http://localhost:9999/ElanRegistry/Registry

const { test, expect } = require('@playwright/test');
const { ensureLoggedIn } = require('./auth-helper.js');

// ---------------------------------------------------------------------------
// Area 1: index.php — Registry Management tabs
// ---------------------------------------------------------------------------

test.describe('Admin page titles — index (#1430)', () => {
    test.beforeEach(async ({ page }) => {
        await ensureLoggedIn(page);
    });

    test('default tab (car-mgmt) renders page-specific title', async ({ page }) => {
        await page.goto('app/admin/index.php', { waitUntil: 'networkidle' });
        // The <title> tag appends " {site_name}" after $pageTitle (see
        // users/template/header1_must_include.php), so this is a partial
        // (contains) match rather than an exact one.
        await expect(page).toHaveTitle(/Registry Management - Car\/Owner Relationships/);
    });

    test('manage-cars tab renders page-specific title', async ({ page }) => {
        await page.goto('app/admin/index.php?tab=manage-cars', { waitUntil: 'networkidle' });
        await expect(page).toHaveTitle(/Registry Management - Manage Cars/);
    });
});

// ---------------------------------------------------------------------------
// Area 2: maintenance.php — Registry Maintenance tabs
// ---------------------------------------------------------------------------

test.describe('Admin page titles — maintenance (#1430)', () => {
    test.beforeEach(async ({ page }) => {
        await ensureLoggedIn(page);
    });

    test('default tab (health) renders page-specific title', async ({ page }) => {
        await page.goto('app/admin/maintenance.php', { waitUntil: 'networkidle' });
        await expect(page).toHaveTitle(/Registry Maintenance - Health/);
    });

    test('settings tab renders page-specific title', async ({ page }) => {
        await page.goto('app/admin/maintenance.php?tab=settings', { waitUntil: 'networkidle' });
        await expect(page).toHaveTitle(/Registry Maintenance - Configuration/);
    });
});

// ---------------------------------------------------------------------------
// Area 3: design-system.php — static page-specific title
// ---------------------------------------------------------------------------

test.describe('Admin page titles — design system (#1430)', () => {
    test.beforeEach(async ({ page }) => {
        await ensureLoggedIn(page);
    });

    test('renders page-specific title and description', async ({ page }) => {
        await page.goto('app/admin/design-system.php', { waitUntil: 'networkidle' });
        await expect(page).toHaveTitle(/Color Preview — Elan Registry Token System/);

        // $pageDescription's timing isn't strictly required (head_tags.php reads it
        // at render time, unlike $pageTitle), but its VALUE should still reach the
        // meta tag correctly — this is the one page in the set with an easy static
        // value to assert against.
        const description = await page.locator('meta[name="description"]').getAttribute('content');
        expect(description).toBe('Preview of the Elan Registry design system color tokens and UI components.');
    });
});
