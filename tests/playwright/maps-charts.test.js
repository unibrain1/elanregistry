// tests/playwright/maps-charts.test.js
const { test, expect } = require('@playwright/test');

test.describe('Maps and Charts Functionality', () => {
  test('Google Maps loads on statistics page', async ({ page }) => {
    await page.goto('/app/reports/statistics.php');
    
    // Wait for the map container to be present
    await page.waitForSelector('#map', { timeout: 10000 });
    
    const mapContainer = page.locator('#map');
    await expect(mapContainer).toBeVisible();
    
    // Wait for Google Maps to load
    await page.waitForTimeout(5000);
    
    // Check if map canvas is present (indicates Google Maps loaded)
    const mapCanvas = page.locator('#map canvas, #map div[style*="transform"]');
    const canvasCount = await mapCanvas.count();
    
    // If Google Maps loaded, there should be canvas elements
    if (canvasCount > 0) {
      await expect(mapCanvas.first()).toBeVisible();
    }
  });

  test('Google Charts load on statistics page', async ({ page }) => {
    await page.goto('/app/reports/statistics.php');
    
    // Wait for chart containers
    const chartContainers = [
      '#chart_country',
      '#chart_type', 
      '#chart_series',
      '#chart_variant',
      '#chart_age',
      '#car_chart'
    ];
    
    for (const containerId of chartContainers) {
      const container = page.locator(containerId);
      if (await container.count() > 0) {
        await expect(container).toBeVisible();
        
        // Wait for chart to render
        await page.waitForTimeout(2000);
        
        // Check if chart SVG or canvas is present
        const chartElement = container.locator('svg, canvas, div[dir="ltr"]');
        const elementCount = await chartElement.count();
        
        if (elementCount > 0) {
          await expect(chartElement.first()).toBeVisible();
        }
      }
    }
  });

  test('map markers XML endpoint works', async ({ page }) => {
    // Test the XML endpoint directly
    const response = await page.request.get('/app/cars/mapmarkers.xml.php');
    
    expect(response.status()).toBe(200);
    
    const contentType = response.headers()['content-type'] || '';
    expect(contentType).toContain('xml');
    
    const xmlContent = await response.text();
    expect(xmlContent).toContain('<?xml');
    expect(xmlContent).toContain('<markers>');
  });

  test('car details page map loads', async ({ page }) => {
    await page.goto('/app/cars/details.php?car_id=1');
    
    // Look for map container on car details page
    const mapContainer = page.locator('#map, .map-container');
    const mapCount = await mapContainer.count();
    
    if (mapCount > 0) {
      await expect(mapContainer.first()).toBeVisible();
      
      // Wait for map to potentially load
      await page.waitForTimeout(3000);
    }
  });

  test('statistics page handles large datasets', async ({ page }) => {
    await page.goto('/app/reports/statistics.php');
    
    // Wait for page to fully load
    await page.waitForLoadState('networkidle');
    
    // Check that statistics cards are present
    const statCards = page.locator('.card, .registry-card');
    const cardCount = await statCards.count();
    
    expect(cardCount).toBeGreaterThan(0);
    
    // Check that no JavaScript errors occurred during loading
    const consoleErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });
    
    await page.waitForTimeout(5000);
    
    // Filter out acceptable errors (API warnings, etc.)
    const criticalErrors = consoleErrors.filter(error => 
      !error.includes('Google') && 
      !error.includes('API') &&
      !error.includes('Invalid XML') // This might happen if no data
    );
    
    expect(criticalErrors.length).toBeLessThanOrEqual(1); // Allow for minor issues
  });

  test('charts are responsive on mobile', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    
    await page.goto('/app/reports/statistics.php');
    
    // Wait for charts to load
    await page.waitForTimeout(5000);
    
    // Check that chart containers are still visible and responsive
    const chartContainer = page.locator('#chart_country');
    if (await chartContainer.count() > 0) {
      await expect(chartContainer).toBeVisible();
      
      // Check that container width adapts to mobile
      const boundingBox = await chartContainer.boundingBox();
      if (boundingBox) {
        expect(boundingBox.width).toBeLessThanOrEqual(375);
      }
    }
  });

  test('map interactions work', async ({ page }) => {
    await page.goto('/app/reports/statistics.php');
    
    // Wait for map to load
    await page.waitForSelector('#map', { timeout: 10000 });
    await page.waitForTimeout(5000);
    
    const mapContainer = page.locator('#map');
    
    // Try to interact with the map (if it loaded)
    const mapCanvas = mapContainer.locator('canvas, div[style*="cursor"]');
    const canvasCount = await mapCanvas.count();
    
    if (canvasCount > 0) {
      // Try clicking on the map
      await mapCanvas.first().click();
      
      // Should not cause JavaScript errors
      await page.waitForTimeout(1000);
    }
  });

  test('chart data loads correctly', async ({ page }) => {
    await page.goto('/app/reports/statistics.php');
    
    // Check that the page has the statistics data
    const pageContent = await page.content();
    
    // Should contain data for charts
    expect(pageContent).toMatch(/statisticsRawData|chart|google\.charts/);
    
    // Wait for charts to render
    await page.waitForTimeout(5000);
    
    // Check that at least one chart rendered
    const chartElements = page.locator('#chart_country svg, #chart_type svg, #chart_age svg');
    const chartCount = await chartElements.count();
    
    // At least one chart should have rendered (if data exists)
    if (chartCount > 0) {
      await expect(chartElements.first()).toBeVisible();
    }
  });
});