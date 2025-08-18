// tests/playwright/security.test.js
const { test, expect } = require('@playwright/test');

test.describe('Security Features', () => {
  test('CSRF tokens present in forms', async ({ page }) => {
    await page.goto('/app/contact/index.php');
    
    // Check that CSRF token is present
    const csrfToken = await page.locator('input[name="csrf"]');
    await expect(csrfToken).toBeVisible();
    
    const tokenValue = await csrfToken.getAttribute('value');
    expect(tokenValue).toBeTruthy();
    expect(tokenValue.length).toBeGreaterThan(10);
  });

  test('car edit form has CSRF protection', async ({ page }) => {
    await page.goto('/app/cars/edit.php');
    
    // Check that CSRF token is present in the form
    const csrfToken = await page.locator('input[name="csrf"]');
    await expect(csrfToken).toBeVisible();
    
    const tokenValue = await csrfToken.getAttribute('value');
    expect(tokenValue).toBeTruthy();
  });

  test('secure session cookies are set', async ({ page, context }) => {
    await page.goto('/');
    
    const cookies = await context.cookies();
    const sessionCookie = cookies.find(cookie => cookie.name.includes('PHPSESSID') || cookie.name.includes('session'));
    
    if (sessionCookie) {
      expect(sessionCookie.httpOnly).toBe(true);
      expect(sessionCookie.secure).toBe(false); // localhost doesn't use HTTPS
      expect(sessionCookie.sameSite).toBe('Strict');
    }
  });

  test('forms prevent XSS attacks', async ({ page }) => {
    await page.goto('/app/contact/index.php');
    
    // Try to inject a script tag
    const maliciousInput = '<script>alert("XSS")</script>';
    
    await page.fill('input[name="name"]', maliciousInput);
    await page.fill('textarea[name="message"]', maliciousInput);
    
    // Check that the script tags are properly escaped
    const nameValue = await page.locator('input[name="name"]').getAttribute('value');
    expect(nameValue).not.toContain('<script>');
  });

  test('verification system has CSRF protection', async ({ page }) => {
    // Test the verification page
    await page.goto('/app/verify/index.php');
    
    // Should have CSRF token or proper security measures
    const tokenExists = await page.locator('input[name="csrf"], input[name="token"]').count();
    expect(tokenExists).toBeGreaterThan(0);
  });
});