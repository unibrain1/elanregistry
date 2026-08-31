// playwright.config.js
require('dotenv').config({ path: '.env.local' });
const { defineConfig, devices } = require('@playwright/test');

/**
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: './tests/playwright',
  /* Run tests in files in parallel */
  fullyParallel: true,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,
  /* Opt out of parallel tests on CI. */
  workers: process.env.CI ? 1 : undefined,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: 'html',
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    /* Trailing slash is required — goto('') resolves to baseURL; without it the path collapses. */
    baseURL: 'http://localhost:9999/ElanRegistry/Registry/',

    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',
    
    /* Take screenshot on failure */
    screenshot: 'only-on-failure',
    
    /* Record video on failure */
    video: 'retain-on-failure',
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      testIgnore: ['**/e2e/**', '**/mobile-responsive.spec.js'],
    },
    {
      name: 'Mobile Chrome',
      use: { ...devices['iPhone SE'] },
      testMatch: '**/mobile-responsive.spec.js',
      testIgnore: '**/e2e/**',
    },
    {
      name: 'setup',
      testMatch: /.*\.setup\.js/,
      use: { ...devices['Desktop Chrome'] },
    },
    {
      // NOTE: this is an exact-filename allowlist, not a directory/suffix
      // match — deliberately narrow so it can't accidentally also match
      // not-logged-in.spec.js (see #1781's regex-anchoring fix). The
      // trade-off: any NEW spec file added under e2e/ that needs an
      // authenticated session must be added to this alternation explicitly,
      // or it will be silently unreachable (chromium/Mobile Chrome both
      // testIgnore e2e/** entirely) — the same failure mode #1781 exists to
      // fix. When adding such a file, add it here too.
      name: 'logged-in',
      testMatch: /(?:^|\/)(logged-in|factory-registry-link)\.spec\.js$/,
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'tests/playwright/.auth/user.json',
      },
    },
  ],

  /* Run your local dev server before starting the tests */
  // webServer: {
  //   command: 'npm run start',
  //   url: 'http://127.0.0.1:3000',
  //   reuseExistingServer: !process.env.CI,
  // },
});