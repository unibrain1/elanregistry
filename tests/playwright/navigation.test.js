// tests/playwright/navigation.test.js
const { test, expect } = require('@playwright/test');
const { login, ensureLoggedIn } = require('./auth-helper.js');

test.describe('Navigation and File Reorganization', () => {
  test('homepage loads successfully', async ({ page }) => {
    await page.goto('http://localhost:9999/elan_registry/index.php');
    await expect(page).toHaveTitle(/Lotus Elan Registry/);
  });

  test('car listing page loads (reorganized)', async ({ page }) => {
    await page.goto('http://localhost:9999/elan_registry/app/cars/index.php');
    
    // Wait for page to load completely
    await page.waitForLoadState('networkidle');
    
    // Check for List Cars header
    await expect(page.locator('h2')).toContainText(/List Cars/);
    
    // Test backward compatibility redirect
    await page.goto('http://localhost:9999/elan_registry/app/list_cars.php');
    await expect(page.url()).toContain('app/cars/index.php');
  });

  test('car details page loads (reorganized)', async ({ page }) => {
    // This page might require authentication, check if we get redirected to login
    await page.goto('http://localhost:9999/elan_registry/app/cars/details.php?car_id=1');
    await page.waitForLoadState('networkidle');
    
    // Check if we're on login page or car details page
    const pageTitle = await page.title();
    const pageContent = await page.textContent('body');
    
    if (pageContent.includes('Please Log In') || pageTitle.includes('Log In')) {
      // Page requires authentication, that's expected behavior
      await expect(page.locator('h2')).toContainText(/Please Log In/);
    } else {
      // Look for car details content (use first match to avoid strict mode violation)
      await expect(page.locator('h1, h2, .card-header').first()).toContainText(/Car|Details|Information/);
    }
    
    // Test backward compatibility redirect
    await page.goto('http://localhost:9999/elan_registry/app/car_details.php?car_id=1');
    await expect(page.url()).toContain('app/cars/details.php');
  });

  test('car edit page loads (reorganized)', async ({ page }) => {
    // Test new location first to see if it requires authentication
    await page.goto('http://localhost:9999/elan_registry/app/cars/edit.php');
    await page.waitForLoadState('networkidle');
    
    // Check if page requires authentication
    const pageContent = await page.textContent('body');
    if (pageContent.includes('Please Log In')) {
      // Edit page requires authentication, that's expected
      await expect(page.locator('h2')).toContainText(/Please Log In/);
    } else {
      await expect(page.locator('#progressbar')).toBeVisible();
    }
    
    // Test backward compatibility redirect
    await page.goto('http://localhost:9999/elan_registry/app/edit_car.php');
    await expect(page.url()).toContain('app/cars/edit.php');
  });

  test('statistics page loads (reorganized)', async ({ page }) => {
    // Test new location
    await page.goto('http://localhost:9999/elan_registry/app/reports/statistics.php');
    await page.waitForLoadState('networkidle');
    
    // Look for statistics page content (page loads successfully)
    await expect(page.getByRole('heading', { name: /Where are the cars around the world/i })).toBeVisible();
    
    // Test backward compatibility redirect
    await page.goto('http://localhost:9999/elan_registry/app/statistics.php');
    await expect(page.url()).toContain('app/reports/statistics.php');
  });

  test('contact page loads (reorganized)', async ({ page }) => {
    // Test new location
    await page.goto('http://localhost:9999/elan_registry/app/contact/index.php');
    await page.waitForLoadState('networkidle');
    
    // Check if page requires authentication
    const pageContent = await page.textContent('body');
    if (pageContent.includes('Please Log In')) {
      // Contact page requires authentication, that's expected
      await expect(page.locator('h2')).toContainText(/Please Log In/);
    } else {
      await expect(page.locator('h2')).toContainText(/Contact/);
    }
    
    // Test backward compatibility redirect
    await page.goto('http://localhost:9999/elan_registry/app/contact.php');
    await expect(page.url()).toContain('app/contact/index.php');
  });

  test('identification guide loads (reorganized)', async ({ page }) => {
    await page.goto('http://localhost:9999/elan_registry/app/cars/identify.php');
    await expect(page.locator('h2')).toContainText(/Identification/);
    
    // Test backward compatibility redirect
    await page.goto('http://localhost:9999/elan_registry/app/identification.php');
    await expect(page.url()).toContain('app/cars/identify.php');
  });
});