// tests/playwright/fixtures.js
// Centralized car-ID fixtures used across Playwright specs. Values must not
// change without verifying the referenced car/data still exists in the test DB.

// CAR_ID_STANDARD is overridable via the CAR_ID_STANDARD env var (set in
// .env.local, same convention as TEST_USERNAME/TEST_PASSWORD in
// auth-helper.js) for local MAMP snapshots where the default id (1) doesn't
// exist — e.g. a stripped/anonymized snapshot whose car ids start higher.
const CAR_ID_STANDARD = Number(process.env.CAR_ID_STANDARD) || 1;
const CAR_ID_WITH_HISTORY = 1091;
const CAR_ID_WITH_SPECIAL_CHARS = 650; // depends on a one-time migration having run against this row — see car-edit-text-save.spec.js
const CAR_ID_NONEXISTENT = 999999999;
const CAR_ID_REDIRECT_TEST = 100;

module.exports = {
  CAR_ID_STANDARD,
  CAR_ID_WITH_HISTORY,
  CAR_ID_WITH_SPECIAL_CHARS,
  CAR_ID_NONEXISTENT,
  CAR_ID_REDIRECT_TEST,
};
