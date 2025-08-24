/**
 * CSP (Content Security Policy) Validation Tests
 * 
 * These tests detect CSP violations across the application to prevent
 * security policy regressions and ensure external resources load properly.
 * 
 * @file csp-validation.spec.js
 * @author Claude Code Assistant
 * @created 2025-08-24
 */

const { test, expect } = require('@playwright/test');

/**
 * CSP violation monitoring helper
 * @param {Page} page - Playwright page object
 * @returns {Array} Array to store CSP violations
 */
function setupCSPViolationMonitoring(page) {
  const cspViolations = [];
  
  // Listen for CSP violations in console
  page.on('console', (msg) => {
    const text = msg.text();
    if (text.includes('Content Security Policy') || 
        text.includes('Refused to load') ||
        text.includes('violates the following Content Security Policy directive')) {
      cspViolations.push({
        type: 'console',
        message: text,
        timestamp: new Date().toISOString()
      });
    }
  });

  // Listen for security policy violation events
  page.on('pageerror', (error) => {
    if (error.message.includes('Content Security Policy') || 
        error.message.includes('CSP')) {
      cspViolations.push({
        type: 'error',
        message: error.message,
        timestamp: new Date().toISOString()
      });
    }
  });

  return cspViolations;
}

test.describe('CSP Validation Tests', () => {
  
  test('Statistics page should not have CSP violations', async ({ page }) => {
    const cspViolations = setupCSPViolationMonitoring(page);
    
    // Navigate to statistics page
    await page.goto('/app/reports/statistics.php');
    
    // Wait for page to fully load including external resources
    await page.waitForLoadState('networkidle');
    
    // Wait a bit more for dynamic resources like Google Charts
    await page.waitForTimeout(3000);
    
    // Check for CSP violations
    if (cspViolations.length > 0) {
      console.log('CSP Violations found:', cspViolations);
    }
    
    expect(cspViolations, `Found ${cspViolations.length} CSP violations: ${JSON.stringify(cspViolations, null, 2)}`).toHaveLength(0);
  });

  test('Car details page should not have CSP violations', async ({ page }) => {
    const cspViolations = setupCSPViolationMonitoring(page);
    
    // First get a car ID to test with
    await page.goto('/app/cars/index.php');
    await page.waitForLoadState('networkidle');
    
    // Click on first car link if available
    const firstCarLink = page.locator('a[href*="details.php?car_id="]').first();
    if (await firstCarLink.count() > 0) {
      await firstCarLink.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(2000);
    } else {
      // Skip if no cars available
      test.skip('No cars available for testing');
    }
    
    expect(cspViolations, `Found ${cspViolations.length} CSP violations: ${JSON.stringify(cspViolations, null, 2)}`).toHaveLength(0);
  });

  test('Car listing page should not have CSP violations', async ({ page }) => {
    const cspViolations = setupCSPViolationMonitoring(page);
    
    await page.goto('/app/cars/index.php');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    expect(cspViolations, `Found ${cspViolations.length} CSP violations: ${JSON.stringify(cspViolations, null, 2)}`).toHaveLength(0);
  });

  test('Login page should not have CSP violations', async ({ page }) => {
    const cspViolations = setupCSPViolationMonitoring(page);
    
    await page.goto('/users/login.php');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    
    expect(cspViolations, `Found ${cspViolations.length} CSP violations: ${JSON.stringify(cspViolations, null, 2)}`).toHaveLength(0);
  });

  test('Home page should not have CSP violations', async ({ page }) => {
    const cspViolations = setupCSPViolationMonitoring(page);
    
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    
    expect(cspViolations, `Found ${cspViolations.length} CSP violations: ${JSON.stringify(cspViolations, null, 2)}`).toHaveLength(0);
  });

  test('Statistics page Google Charts should load successfully', async ({ page }) => {
    const cspViolations = setupCSPViolationMonitoring(page);
    
    await page.goto('/app/reports/statistics.php');
    await page.waitForLoadState('networkidle');
    
    // Wait for Google Charts to load
    await page.waitForTimeout(5000);
    
    // Check if charts are actually rendered (they should have SVG elements)
    const chartElements = await page.locator('#chart_country svg, #chart_type svg, #chart_series svg').count();
    
    // Verify no CSP violations occurred
    expect(cspViolations, `Found ${cspViolations.length} CSP violations while loading charts: ${JSON.stringify(cspViolations, null, 2)}`).toHaveLength(0);
    
    // Verify at least some charts loaded successfully
    expect(chartElements, 'No chart SVG elements found - charts may not be loading properly').toBeGreaterThan(0);
  });

  test('Statistics page external resources should load', async ({ page }) => {
    const cspViolations = setupCSPViolationMonitoring(page);
    const failedRequests = [];
    
    // Monitor failed network requests
    page.on('response', (response) => {
      if (!response.ok() && response.status() !== 304) {
        failedRequests.push({
          url: response.url(),
          status: response.status(),
          statusText: response.statusText()
        });
      }
    });
    
    await page.goto('/app/reports/statistics.php');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);
    
    // Log failed requests for debugging
    if (failedRequests.length > 0) {
      console.log('Failed requests:', failedRequests);
    }
    
    // Check for CSP violations
    expect(cspViolations, `Found ${cspViolations.length} CSP violations: ${JSON.stringify(cspViolations, null, 2)}`).toHaveLength(0);
    
    // Verify critical resources loaded successfully
    const criticalFailures = failedRequests.filter(req => 
      req.url.includes('charts.googleapis.com') || 
      req.url.includes('gstatic.com') ||
      req.url.includes('cloudflareinsights.com')
    );
    
    expect(criticalFailures, `Critical external resources failed to load: ${JSON.stringify(criticalFailures, null, 2)}`).toHaveLength(0);
  });
});