// tests/playwright/auth-helper.js

/**
 * Authentication helper for Playwright tests
 * Provides login functionality for tests that require authentication
 */

/**
 * Login to the application with provided credentials
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @param {string} username - Username for login
 * @param {string} password - Password for login
 */
async function login(page, username = 'jim.unibrain@me.com', password = 'testingPassword') {
  // Navigate to login page
  await page.goto('http://localhost:9999/elan_registry/users/login.php');
  
  // Wait for login form to load
  await page.waitForSelector('input[name="username"], input[name="email"]', { timeout: 10000 });
  
  // Fill in credentials
  const usernameField = page.locator('input[name="username"], input[name="email"]').first();
  const passwordField = page.locator('input[name="password"]');
  
  await usernameField.fill(username);
  await passwordField.fill(password);
  
  // Submit the form
  await page.click('button[type="submit"], input[type="submit"]');
  
  // Wait for successful login (redirect or success indicator)
  await page.waitForTimeout(2000);
  
  // Verify login was successful by checking for logout link or user area
  try {
    await page.waitForSelector('a[href*="logout"], .user-menu, .account-menu', { timeout: 5000 });
  } catch (error) {
    // If no logout link found, check if we're on a dashboard/account page
    const currentUrl = page.url();
    if (!currentUrl.includes('login') && !currentUrl.includes('error')) {
      // Assume login was successful if we're not on login page
      return;
    }
    throw new Error('Login may have failed - no logout link or account area found');
  }
}

/**
 * Check if user is already logged in
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @returns {boolean} - True if user appears to be logged in
 */
async function isLoggedIn(page) {
  try {
    // Check for logout link or user menu
    const logoutLink = await page.locator('a[href*="logout"], .user-menu, .account-menu').count();
    return logoutLink > 0;
  } catch {
    return false;
  }
}

/**
 * Logout from the application
 * @param {import('@playwright/test').Page} page - Playwright page object
 */
async function logout(page) {
  try {
    const logoutLink = page.locator('a[href*="logout"]').first();
    if (await logoutLink.count() > 0) {
      await logoutLink.click();
      await page.waitForTimeout(1000);
    }
  } catch (error) {
    // Ignore errors if logout link not found
  }
}

/**
 * Ensure user is logged in before running test
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @param {string} username - Username for login
 * @param {string} password - Password for login
 */
async function ensureLoggedIn(page, username = 'jim.unibrain@me.com', password = 'testingPassword') {
  const alreadyLoggedIn = await isLoggedIn(page);
  if (!alreadyLoggedIn) {
    await login(page, username, password);
  }
}

module.exports = {
  login,
  isLoggedIn,
  logout,
  ensureLoggedIn
};