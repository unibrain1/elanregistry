// tests/playwright/navigation.test.js
const { test, expect } = require('@playwright/test');
const { navigateAndWait, testRedirect, handleAuthRequired } = require('./auth-helper.js');

test.describe('Navigation and File Reorganization', () => {
  test('homepage loads successfully', async ({ page }) => {
    await navigateAndWait(page, '/index.php');
    await expect(page).toHaveTitle(/Lotus Elan Registry/);
  });

  test('car listing page loads (reorganized)', async ({ page }) => {
    await navigateAndWait(page, '/app/cars/index.php');
    
    // Check for List Cars header
    await expect(page.locator('h2')).toContainText(/List Cars/);
    
    // Test backward compatibility redirect
    await testRedirect(page, '/app/list_cars.php', 'app/cars/index.php');
  });

  test('car details page loads (reorganized)', async ({ page }) => {
    // Navigate to car details page
    await navigateAndWait(page, '/app/cars/details.php?car_id=1');
    
    // Handle authentication requirement or verify content
    await handleAuthRequired(
      page,
      // Authenticated test - verify car details content
      async () => {
        await expect(page.locator('h1, h2, .card-header').first()).toContainText(/Car|Details|Information/);
      }
    );
    
    // Test backward compatibility redirect
    await testRedirect(page, '/app/car_details.php?car_id=1', 'app/cars/details.php');
  });

  test('car edit page loads (reorganized)', async ({ page }) => {
    // Navigate to car edit page
    await navigateAndWait(page, '/app/cars/edit.php');
    
    // Handle authentication requirement or verify edit form
    await handleAuthRequired(
      page,
      // Authenticated test - verify edit form elements
      async () => {
        await expect(page.locator('#progressbar')).toBeVisible();
      }
    );
    
    // Test backward compatibility redirect
    await testRedirect(page, '/app/edit_car.php', 'app/cars/edit.php');
  });

  test('statistics page loads (reorganized)', async ({ page }) => {
    // Navigate to statistics page
    await navigateAndWait(page, '/app/reports/statistics.php');
    
    // Look for statistics page content (page loads successfully)
    await expect(page.getByRole('heading', { name: /Where are the cars around the world/i })).toBeVisible();
    
    // Test backward compatibility redirect
    await testRedirect(page, '/app/statistics.php', 'app/reports/statistics.php');
  });

  test('contact page loads (reorganized)', async ({ page }) => {
    // Navigate to contact page
    await navigateAndWait(page, '/app/contact/index.php');
    
    // Handle authentication requirement or verify contact form
    await handleAuthRequired(
      page,
      // Authenticated test - verify contact form elements
      async () => {
        await expect(page.locator('h2')).toContainText(/Contact/);
      }
    );
    
    // Test backward compatibility redirect
    await testRedirect(page, '/app/contact.php', 'app/contact/index.php');
  });

  test('identification guide loads (reorganized)', async ({ page }) => {
    await navigateAndWait(page, '/app/cars/identify.php');
    await expect(page.locator('h2')).toContainText(/Identification/);
    
    // Test backward compatibility redirect
    await testRedirect(page, '/app/identification.php', 'app/cars/identify.php');
  });
});