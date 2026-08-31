// tests/playwright/e2e/auth.setup.js

const { test } = require('@playwright/test');
const { login } = require('../auth-helper');

const authFile = 'tests/playwright/.auth/user.json';

test('authenticate', async ({ page }) => {
  if (!process.env.TEST_USERNAME || !process.env.TEST_PASSWORD) {
    test.skip(true, 'Set TEST_USERNAME and TEST_PASSWORD in .env.local to run authenticated tests');
  }

  await login(page, process.env.TEST_USERNAME, process.env.TEST_PASSWORD);

  await page.context().storageState({ path: authFile });
});
