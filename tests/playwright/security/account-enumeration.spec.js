const { test, expect } = require('@playwright/test');

/**
 * Regression guard for account enumeration during registration (#1406).
 *
 * usersc/join.php must show the identical generic failure response whether
 * or not the submitted email already belongs to an existing account — an
 * attacker must not be able to distinguish "email taken" from any other
 * validation failure by observing HTTP status, redirect target, or
 * rendered message text.
 *
 * This spec is scoped to response-shape only. It does not verify the
 * silent recovery-notification email is actually delivered — that's the
 * PHPUnit layer's job (tests/unit/auth/RegistrationRecoveryNotifierTest.php),
 * matching this project's existing practice of never sending real email
 * from a Playwright spec.
 *
 * usersc/join.php is the live registration handler — UserSpice's core
 * loader transparently redirects /users/join.php requests here when the
 * override exists, so this spec targets it directly to avoid an extra
 * redirect hop in each request.
 *
 * @group security
 * @group enumeration
 */

const JOIN_PAGE = 'usersc/join.php';

async function getCsrfFromJoinForm(page) {
  await page.goto(JOIN_PAGE, { waitUntil: 'domcontentloaded' });
  const token = await page.inputValue('input[name="csrf"]', { timeout: 3000 });
  expect(token, 'CSRF token must be present on the registration form').toBeTruthy();
  return token;
}

function uniqueEmail(label) {
  return `enum-test-${label}-${Date.now()}-${Math.floor(Math.random() * 100000)}@example.com`;
}

async function attemptRegistration(page, csrf, overrides = {}) {
  const form = {
    fname: 'Enum',
    lname: 'Test',
    email: uniqueEmail('unused'),
    password: 'CorrectHorseBatteryStaple1!',
    confirm: 'CorrectHorseBatteryStaple1!',
    csrf,
    ...overrides,
  };

  return page.request.post(JOIN_PAGE, {
    form,
    maxRedirects: 0,
  });
}

test.describe('Registration account enumeration (#1406)', () => {
  test('failed registration response is identical for an existing vs. a new email', async ({ page }) => {
    // 1. Create a real account with a unique, throwaway email.
    const existingEmail = uniqueEmail('existing');
    let csrf = await getCsrfFromJoinForm(page);
    const signupResponse = await attemptRegistration(page, csrf, { email: existingEmail });

    // Successful registration redirects to users/complete.php; a FAILED
    // registration also redirects (back to currentPage()), so a bare 3xx
    // status check would pass vacuously even if step 1 never actually
    // created an account — assert the redirect target itself.
    expect(signupResponse.status(), 'Initial signup must succeed (redirect)').toBeGreaterThanOrEqual(300);
    expect(signupResponse.status(), 'Initial signup must succeed (redirect)').toBeLessThan(400);
    expect(
      signupResponse.headers()['location'],
      'Initial signup must redirect to users/complete.php — a failure redirect back to the join page would mean step 1 never created the account, making the rest of this test vacuous'
    ).toContain('users/complete.php');

    // 2. Attempt registration again with the SAME (now-existing) email, but
    //    an intentionally invalid field — exercises the failure branch with
    //    an email that DOES exist.
    csrf = await getCsrfFromJoinForm(page);
    const existingEmailFailure = await attemptRegistration(page, csrf, {
      email: existingEmail,
      confirm: 'DoesNotMatchThePassword1!',
    });

    // 3. Attempt registration with a fresh, definitely-unused email and the
    //    same invalid field — exercises the failure branch with an email
    //    that does NOT exist.
    csrf = await getCsrfFromJoinForm(page);
    const newEmailFailure = await attemptRegistration(page, csrf, {
      email: uniqueEmail('fresh'),
      confirm: 'DoesNotMatchThePassword1!',
    });

    // Both failures must be indistinguishable: same status, same redirect target.
    expect(existingEmailFailure.status()).toBe(newEmailFailure.status());
    expect(existingEmailFailure.headers()['location']).toBe(newEmailFailure.headers()['location']);

    // Follow both redirects and confirm the rendered flash-message text is
    // byte-identical — the actual enumeration vector this issue closes.
    const existingEmailPage = await page.request.get(
      new URL(existingEmailFailure.headers()['location'], page.url()).toString()
    );
    const newEmailPage = await page.request.get(
      new URL(newEmailFailure.headers()['location'], page.url()).toString()
    );

    const existingEmailBody = await existingEmailPage.text();
    const newEmailBody = await newEmailPage.text();

    // Extract just the flash-message text rather than comparing full pages
    // byte-for-byte (session-specific tokens/timestamps elsewhere in the
    // page would make a full-body comparison flaky). Flash messages render
    // as a client-side toast via an inline userSpiceMessage(<json>, 'danger')
    // call (see users/includes/system_messages_footer.php) — not a
    // server-rendered alert div — so the message text must be pulled from
    // that JS call's JSON-encoded first argument.
    const flashMessageRegex = /userSpiceMessage\((".*?"),\s*'danger'\)/;
    const existingEmailMessage = existingEmailBody.match(flashMessageRegex)?.[1];
    const newEmailMessage = newEmailBody.match(flashMessageRegex)?.[1];

    expect(existingEmailMessage, 'Existing-email failure must render a flash message').toBeTruthy();
    expect(existingEmailMessage).toBe(newEmailMessage);
  });

  test('registering with an already-used email does not disclose existence via response text', async ({ page }) => {
    const existingEmail = uniqueEmail('existing2');
    let csrf = await getCsrfFromJoinForm(page);
    await attemptRegistration(page, csrf, { email: existingEmail });

    csrf = await getCsrfFromJoinForm(page);
    const failureResponse = await attemptRegistration(page, csrf, {
      email: existingEmail,
      confirm: 'DoesNotMatchThePassword1!',
    });

    const redirectTarget = new URL(failureResponse.headers()['location'], page.url()).toString();
    const renderedPage = await page.request.get(redirectTarget);
    const body = (await renderedPage.text()).toLowerCase();

    const disclosureTerms = ['already exists', 'already registered', 'already in use', 'already taken'];
    for (const term of disclosureTerms) {
      expect(body, `Response must not disclose email existence via the phrase "${term}"`).not.toContain(term);
    }
  });
});
