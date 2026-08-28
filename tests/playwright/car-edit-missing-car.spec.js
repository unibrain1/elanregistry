// tests/playwright/car-edit-missing-car.spec.js
//
// Coverage for app/owner/cars/edit.php's updateCarDetails() missing-car path
// (issue #1313).
//
// Originally filed as: loading edit.php for a deleted/merged car force-logs
// the owner out (a null $carQ->data() flowed into the ownership comparison,
// hitting the $user->logout(); exit(); branch). That specific defect was
// already fixed by an earlier commit (#1300), which added an exists() guard
// that returns early before the ownership check ever runs. But the guard's
// early return was silent — no message, no redirect — leaving the owner on a
// blank/broken-looking edit form with no explanation.
//
// This session's fix (edit.php:107-113) makes the exists() guard show a
// flash message ("This car could not be found.") and redirect to
// cars/index.php, mirroring the existing pattern in details.php for the same
// scenario.
//
// No test exercised updateCarDetails() at all before this file — the closest
// existing coverage, security/car-update-ownership.spec.js, covers the AJAX
// save.php?action=updateCar endpoint, a different code path than edit.php's
// GET-render flow, and would not have caught either the original force-logout
// bug or this narrower silent-failure gap.
//
// This test is a plain form POST with no FilePond/AJAX mocking needed
// (unlike car-edit-text-save.spec.js, which mocks save.php for form-submit
// scenarios), so it lives in its own file rather than growing that one.
//
// updateCarDetails() — and therefore the exists() guard this test covers —
// only runs on a POST with action=updateCar (see edit.php's
// `if (Input::existsPost()) { ... if ($action === 'updateCar') { updateCarDetails(...) } }`
// block). A plain GET to edit.php?car_id=<id> never reaches it; the real
// client flow (app/views/cars/_car_hero_actions.php's "Update Car" button)
// submits a POST form with csrf/action/car_id fields. This test reproduces
// that POST directly rather than simulating a button click, since the
// missing-car scenario has no such button in the UI to click (there is no
// car to view a hero-actions form for).

const { test, expect } = require('@playwright/test');
const { ensureLoggedIn } = require('./auth-helper.js');
const { CAR_ID_NONEXISTENT } = require('./fixtures.js');

test.describe('Car edit page — missing car (#1313)', () => {

    test.beforeEach(async ({ page }) => {
        await ensureLoggedIn(page);
    });

    test('nonexistent car_id shows a not-found message, redirects to cars index, and does not log the user out', async ({ page }) => {
        // Load edit.php's plain add-car GET form to obtain a valid, session-bound
        // CSRF token (Token::check() validates against the session, not the page
        // that issued it — any page's token works for any POST in the same session).
        await page.goto('app/owner/cars/edit.php', { waitUntil: 'domcontentloaded' });

        const initialUrl = page.url();
        if (initialUrl.includes('login') || initialUrl.includes('Please Log In')) {
            test.skip('Session not established locally — skipping missing-car assertion');
            return;
        }

        const csrfToken = await page.locator('#csrf').inputValue();
        expect(csrfToken, 'edit.php must render a #csrf hidden field to obtain a token from').toBeTruthy();

        // Submit a real POST form (mirrors _car_hero_actions.php's "Update Car"
        // button) with action=updateCar and a nonexistent car_id, letting the
        // browser navigate and follow the server's redirect naturally.
        //
        // Redirect::to() (users/classes/Redirect.php) only sends an HTTP
        // Location header when headers_sent() is false; edit.php has already
        // flushed output (usError()'s flash message, page markup) by the time
        // updateCarDetails() runs, so it falls back to an inline
        // `<script>window.location.href=...</script>` redirect. That means the
        // POST response itself still renders at the edit.php URL, and the real
        // navigation to cars/index.php only happens once the browser executes
        // that script — so we must wait for the URL to actually change, not
        // just for the POST response's DOMContentLoaded.
        await Promise.all([
            page.waitForURL(url => !url.toString().includes('/cars/edit.php'), { timeout: 15000 }),
            page.evaluate(({ csrf, carId }) => {
                const form = document.createElement('form');
                form.method = 'POST';
                // Self-submit (we are already on edit.php) — avoids relative-path
                // resolution pitfalls (edit.php's own URL is a file, not a directory,
                // so a bare relative "app/owner/cars/edit.php" action 404s).
                form.action = window.location.pathname;

                const fields = { csrf, action: 'updateCar', car_id: String(carId) };
                for (const [name, value] of Object.entries(fields)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.appendChild(input);
                }

                document.body.appendChild(form);
                form.submit();
            }, { csrf: csrfToken, carId: CAR_ID_NONEXISTENT }),
        ]);

        await page.waitForLoadState('domcontentloaded');

        const currentUrl = page.url();
        if (currentUrl.includes('login') || currentUrl.includes('Please Log In')) {
            test.skip('Session not established locally — skipping missing-car assertion');
            return;
        }

        // ------------------------------------------------------------------
        // Assertion 1: redirected away from edit.php to cars/index.php.
        // This is the actual fix — before it, the exists() guard returned
        // silently and the edit form rendered in place with blank fields.
        // ------------------------------------------------------------------
        expect(
            currentUrl,
            'A nonexistent car_id must redirect to cars/index.php, not stay on edit.php'
        ).toContain('cars/index.php');

        // ------------------------------------------------------------------
        // Assertion 2: the "not found" flash message is visible on the page
        // the redirect landed on. usError() surfaces as a UserSpice toast
        // (#us-toast-container .us-toast .toast-body), not a Bootstrap
        // .alert-danger — confirmed by inspecting the rendered markup.
        // ------------------------------------------------------------------
        await expect(
            page.locator('.us-toast .toast-body', { hasText: 'This car could not be found.' }),
            'The "This car could not be found." flash message must be visible after redirect'
        ).toBeVisible();

        // ------------------------------------------------------------------
        // Assertion 3 (regression guard for the originally-reported bug):
        // the user must still be logged in. Before #1300, a missing car's
        // null $carQ->data() flowed into the ownership check and forced a
        // logout via $user->logout(); exit();. That specific defect is
        // already fixed elsewhere, but no test locked it in until now.
        // Verify by navigating to another authenticated owner page and
        // confirming it renders rather than bouncing to the login page.
        // ------------------------------------------------------------------
        await page.goto('app/owner/cars/index.php', { waitUntil: 'domcontentloaded' });
        const postNavUrl = page.url();
        expect(
            postNavUrl.includes('login') || postNavUrl.includes('Please Log In'),
            'User must still be logged in after visiting edit.php with a missing car_id — ' +
            'landing on the login page would indicate the original force-logout regression'
        ).toBe(false);
    });

});
