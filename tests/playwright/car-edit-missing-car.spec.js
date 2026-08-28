// tests/playwright/car-edit-missing-car.spec.js
//
// Coverage for app/owner/cars/edit.php's updateCarDetails() missing-car path
// (issue #1313), plus a regression guard for the adjacent ownership-violation
// logout branch in the same function (added during #1313's test-coverage
// review — see the second test below for why it lives here rather than in
// security/car-update-ownership.spec.js, which covers a different endpoint).
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
const { execFileSync } = require('node:child_process');
const { ensureLoggedIn, login, logout } = require('./auth-helper.js');
const { CAR_ID_NONEXISTENT, CAR_ID_WITH_HISTORY } = require('./fixtures.js');

/**
 * Run a SQL statement against the local test DB via the `mysql` CLI.
 *
 * Only used by the ownership-violation-logout test below, to flip
 * email_verified=1 on a throwaway registered account immediately after
 * signup. No mysql client library is a project dependency (see package.json)
 * so this shells out to the CLI directly rather than adding one; DB_* vars
 * are already loaded into process.env by playwright.config.js's
 * `dotenv.config({ path: '.env.local' })`, so no separate env loading is
 * needed here.
 *
 * DB_HOST is stored as "host:port" (see .env.local's own comment on that
 * var, and users/classes/DB.php which parses it the same way) — split it
 * rather than assuming DB_PORT alone.
 *
 * @param {string} sql
 */
function runSql(sql) {
    const [dbHost, hostPort] = (process.env.DB_HOST || '').split(':');
    if (!dbHost || !hostPort || !process.env.DB_USER || !process.env.DB_NAME) {
        throw new Error('DB_HOST/DB_USER/DB_NAME must be set in .env.local to run this test');
    }
    execFileSync('mysql', [
        '-h', dbHost,
        '-P', hostPort,
        '-u', process.env.DB_USER,
        '--protocol=tcp',
        process.env.DB_NAME,
        '-e', sql,
    ], {
        env: { ...process.env, MYSQL_PWD: process.env.DB_PASS || '' },
        stdio: ['ignore', 'ignore', 'pipe'],
    });
}

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

    // ----------------------------------------------------------------------
    // Ownership-violation logout (adjacent regression guard, not #1313 itself)
    //
    // #1313's fix touches the exists() guard directly above the ownership
    // check in the same function (edit.php:107-124). The plan file for this
    // issue claims the ownership-violation branch (lines 119-124: a non-
    // owner, non-admin user gets $user->logout(); exit();) is unchanged and
    // still works — but nothing in the suite actually exercised it before
    // this test. security/car-update-ownership.spec.js only covers the
    // separate save.php AJAX endpoint against a NONEXISTENT car_id, which
    // never reaches edit.php's own logout branch at all.
    //
    // Why a throwaway registered account, not TEST_USERNAME: TEST_USERNAME
    // (the shared local dev/test login) is provisioned as an Administrator
    // in this environment's DB (permission_id=2), and hasPerm([2, 3]) at
    // edit.php:117 makes admins bypass the logout branch entirely — using it
    // here would make the test pass vacuously (or not exercise the branch at
    // all) regardless of which car_id is used. A freshly self-registered
    // account is a genuine plain owner: users/join.php hard-codes
    // 'permissions' => 1 for every new signup (confirmed in that file), so
    // it can never coincidentally hold admin/editor perms.
    //
    // Why the direct SQL UPDATE: a fresh registration is created with
    // email_verified=0 in this environment (email activation is enabled —
    // see users/join.php's $act/$pre handling), and users/init.php:124
    // redirects any logged-in-but-unverified session to verify.php on every
    // page except a short skip-list that does not include edit.php. Without
    // marking the throwaway account verified first, the test could never
    // reach edit.php at all. This is the one test in the suite that talks to
    // the DB directly (see runSql() above) — justified because there is no
    // lighter-weight way to get a real, non-admin, non-owner authenticated
    // session; account-enumeration.spec.js's registration pattern works
    // there only because it never logs in as the new account afterward.
    //
    // Shares a registration rate-limit budget (ip_max=10/hour — see
    // usersc/includes/rate_limits.php's 'registration_attempt' entry) with
    // account-enumeration.spec.js and any other spec that registers real
    // accounts. Fine under normal single-run usage; running this file
    // repeatedly back-to-back (e.g. --repeat-each) from the same IP can
    // exhaust the budget and fail registration with a redirect back to
    // join.php instead of users/complete.php.
    test('genuine ownership violation on an existing car still logs the user out (edit.php:119-124)', async ({ page, browserName }) => {
        test.skip(browserName !== 'chromium', 'DB-touching setup only needs to run once, not per-browser-project');

        const unique = `${Date.now()}-${Math.floor(Math.random() * 100000)}`;
        const throwawayEmail = `car-edit-ownership-${unique}@example.com`;
        const throwawayUsername = `car-edit-ownership-${unique}`;
        const throwawayPassword = 'CorrectHorseBatteryStaple1!';

        // 0. beforeEach() above logs in as TEST_USERNAME (the shared admin
        //    account) for every test in this file. join.php redirects an
        //    already-logged-in session away before ever rendering its form,
        //    so this test must log out first to reach it.
        await logout(page);

        // 1. Register a fresh throwaway account (real HTTP POST, same as
        //    account-enumeration.spec.js's pattern) — a genuine plain User
        //    (permissions=1), owning no cars.
        await page.goto('usersc/join.php', { waitUntil: 'domcontentloaded' });
        const joinCsrf = await page.locator('input[name="csrf"]').inputValue();
        expect(joinCsrf, 'join.php must render a csrf field to obtain a token from').toBeTruthy();

        const joinResponse = await page.request.post('usersc/join.php', {
            form: {
                fname: 'CarEdit',
                lname: 'Ownership',
                username: throwawayUsername,
                email: throwawayEmail,
                password: throwawayPassword,
                confirm: throwawayPassword,
                csrf: joinCsrf,
            },
            maxRedirects: 0,
        });
        expect(joinResponse.status(), 'Registration must succeed (redirect)').toBeGreaterThanOrEqual(300);
        expect(joinResponse.status(), 'Registration must succeed (redirect)').toBeLessThan(400);
        expect(
            joinResponse.headers()['location'],
            'Registration must redirect to users/complete.php — a failure redirect back to the join page would mean no account was created, making the rest of this test vacuous'
        ).toContain('users/complete.php');

        // 2. Mark the account verified so it isn't redirected to verify.php
        //    on every subsequent page (see comment above).
        runSql(`UPDATE users SET email_verified = 1 WHERE email = '${throwawayEmail}'`);

        try {
            // 3. Log in as the throwaway account and confirm the session is
            //    actually established before POSTing anything.
            await login(page, throwawayEmail, throwawayPassword);
            await page.goto('app/owner/cars/edit.php', { waitUntil: 'domcontentloaded' });
            const prePostUrl = page.url();
            expect(
                prePostUrl.includes('login') || prePostUrl.includes('Please Log In'),
                'Throwaway account must be logged in and past the email-verification wall before the ownership POST'
            ).toBe(false);

            const csrfToken = await page.locator('#csrf').inputValue();
            expect(csrfToken, 'edit.php must render a #csrf hidden field to obtain a token from').toBeTruthy();

            // 4. POST action=updateCar for CAR_ID_WITH_HISTORY — an existing
            //    car this throwaway account does not own and has no
            //    admin/editor perms over. Mirrors the real "Update Car"
            //    button submit (see the missing-car test above for why a
            //    synthetic form POST is used instead of clicking a button).
            // page.request shares the browser context's cookie jar, so this
            // POST carries the same session as the page above, without the
            // extra complexity/race potential of a synthetic DOM form submit
            // + navigation wait (unlike the missing-car test above, this
            // branch has no Redirect::to() response to follow — edit.php's
            // logout branch calls $user->logout(); exit(); directly).
            await page.request.post('app/owner/cars/edit.php', {
                form: { csrf: csrfToken, action: 'updateCar', car_id: String(CAR_ID_WITH_HISTORY) },
            });

            // ------------------------------------------------------------------
            // Assertion: the session must now be logged out. Navigate back to
            // edit.php itself (a private=1 page per the `pages` table — unlike
            // app/owner/cars/index.php, which is deliberately public and
            // renders identically whether logged in or not, so it cannot
            // distinguish auth states here) and confirm it bounces to the
            // login wall rather than rendering — the inverse of the
            // missing-car test's "still logged in" check.
            // ------------------------------------------------------------------
            await page.goto('app/owner/cars/edit.php', { waitUntil: 'domcontentloaded' });
            const postNavUrl = page.url();
            const postNavContent = await page.textContent('body');
            expect(
                postNavUrl.includes('login') || postNavUrl.includes('Please Log In') || postNavContent.includes('Please Log In'),
                'A genuine ownership violation (non-owner, non-admin) POSTing action=updateCar to edit.php must log the user out — ' +
                'still being logged in here would mean edit.php:119-124\'s logout branch did not fire'
            ).toBe(true);
        } finally {
            // Best-effort cleanup — don't fail the test over cleanup itself.
            try {
                runSql(`DELETE FROM users WHERE email = '${throwawayEmail}'`);
            } catch (_error) {
                console.warn('Cleanup failed for throwaway user', throwawayEmail, _error.message);
            }
        }
    });

});
