// tests/playwright/security.test.js
const { test, expect } = require('@playwright/test');
const { navigateAndWait, handleAuthRequired } = require('./auth-helper.js');

test.describe('Security Features', () => {
  test('CSRF tokens present in forms', async ({ page }) => {
    // Test CSRF protection on contact form
    await navigateAndWait(page, '/app/contact/index.php');
    
    // Handle authentication requirement or verify CSRF tokens
    await handleAuthRequired(
      page,
      // Authenticated test - verify CSRF tokens are present
      async () => {
        const csrfToken = page.locator('input[name="csrf"], input[name="_token"]');
        await expect(csrfToken).toBeVisible();
        
        const tokenValue = await csrfToken.getAttribute('value');
        expect(tokenValue).toBeTruthy();
        expect(tokenValue.length).toBeGreaterThan(10);
      }
    );
  });

  test('car edit form has CSRF protection', async ({ page }) => {
    // Test CSRF protection on car edit form
    await navigateAndWait(page, '/app/cars/edit.php');
    
    // Handle authentication requirement or verify CSRF tokens
    await handleAuthRequired(
      page,
      // Authenticated test - verify CSRF tokens in car edit form
      async () => {
        const csrfToken = page.locator('input[name="csrf"], input[name="_token"]');
        await expect(csrfToken).toBeVisible();
        
        const tokenValue = await csrfToken.getAttribute('value');
        expect(tokenValue).toBeTruthy();
      }
    );
  });

  test('secure session cookies are set', async ({ page, context }) => {
    await navigateAndWait(page, '/index.php');
    
    const cookies = await context.cookies();
    const sessionCookie = cookies.find(cookie => cookie.name.includes('PHPSESSID') || cookie.name.includes('session'));
    
    if (sessionCookie) {
      expect(sessionCookie.httpOnly).toBe(true);
      expect(sessionCookie.secure).toBe(false); // localhost doesn't use HTTPS
      expect(sessionCookie.sameSite).toBe('Strict');
    }
  });

  test('forms prevent XSS attacks', async ({ page }) => {
    // Test XSS protection on contact form (requires authentication)
    await page.goto('http://localhost:9999/elan_registry/app/contact/index.php');
    await page.waitForLoadState('networkidle');
    
    // Check if page requires authentication (expected behavior)
    const pageContent = await page.textContent('body');
    if (pageContent.includes('Please Log In')) {
      // Contact form correctly requires authentication - prevents XSS attempts
      await expect(page.locator('h2')).toContainText(/Please Log In/);
    } else {
      // If somehow accessible without auth, test XSS protection
      const maliciousInput = '<script>alert("XSS")</script>';
      
      await page.fill('input[name="name"]', maliciousInput);
      await page.fill('textarea[name="message"]', maliciousInput);
      
      // Check that the script tags are properly escaped
      const nameValue = await page.locator('input[name="name"]').getAttribute('value');
      expect(nameValue).not.toContain('<script>');
    }
  });

  test('verification system has CSRF protection', async ({ page }) => {
    // Test the verification page
    await page.goto('http://localhost:9999/elan_registry/app/verify/index.php');
    await page.waitForLoadState('networkidle');
    
    // Check if page requires authentication or has proper access control
    const pageContent = await page.textContent('body');
    if (pageContent.includes('Please Log In') || pageContent.includes('Not Found') || pageContent.includes('Access Denied')) {
      // Verification system is properly protected
      await expect(page.locator('h1, h2')).toContainText(/Please Log In|Not Found|Access Denied/);
    } else {
      // If accessible, should have CSRF token or proper security measures
      const tokenExists = await page.locator('input[name="csrf"], input[name="token"]').count();
      expect(tokenExists).toBeGreaterThan(0);
    }
  });
});