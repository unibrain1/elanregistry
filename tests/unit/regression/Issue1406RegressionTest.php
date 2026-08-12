<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;

/**
 * Regression test for Issue #1406: account enumeration during registration
 *
 * usersc/join.php shows one generic failure message for ANY registration
 * validation failure, regardless of the reason (weak password, mismatched
 * confirm, email or username already taken, etc.) — this was already true
 * before this issue and must remain true. What #1406 adds is a silent
 * recovery notification to the existing account holder when the submitted
 * email already has an account, without ever varying the user-facing
 * response based on that fact (the enumeration vector this issue closes).
 *
 * This test asserts the response-invariance property directly against the
 * live source — usersc/join.php is a top-level script with no
 * independently-callable message-selection logic to unit-test at the
 * HTTP/behavior level. The notification side-effect itself (and the #1442
 * edge case — a username-only collision, with no real email collision,
 * must not trigger a notification) is unit-tested against a real
 * ElanRegistry\RegistrationRecoveryNotifier instance with a mocked \User
 * in tests/unit/auth/RegistrationRecoveryNotifierTest.php.
 *
 * @issue 1406
 * @link https://github.com/elan-registry/registry/issues/1406
 * @description Registration with an already-registered email no longer
 *   confirms the email exists; a silent recovery notification is sent
 *   instead, with the same generic failure response either way.
 * @category security
 */
#[Group('regression')]
#[Group('fast')]
final class Issue1406RegressionTest extends RegressionTestCase
{
    private const JOIN_PHP_PATH = __DIR__ . '/../../../usersc/join.php';

    /**
     * The registration-failure branch in usersc/join.php must show exactly
     * one, unconditional generic message — never a message that varies based
     * on $validation->_errors content or on whether the submitted email
     * already has an account.
     */
    public function testGenericFailureMessageIsUnconditionalAndAppearsOnlyOnce(): void
    {
        $source = file_get_contents(self::JOIN_PHP_PATH);
        $this->assertIsString($source, 'usersc/join.php must be readable');

        $genericMessage = 'Check your inbox — if an account exists, we have sent you a sign-in link. Otherwise, please check your information and try again.';

        $this->assertSame(
            1,
            substr_count($source, $genericMessage),
            'The generic registration-failure message must appear exactly once in usersc/join.php — '
                . 'a second occurrence would indicate a differentiated (enumeration-leaking) message path exists.'
        );

        // Isolate the validation-failure branch: from the failed-attempt log
        // call through to the generic usError() call. Between those two
        // markers, there must be no OTHER usError(...) call — i.e. nothing
        // conditionally shows a different message before the generic one.
        //
        // Note: the handleAuthFailure('registration_attempt', ...) prefix
        // alone is NOT a unique marker — the try/catch block around user
        // creation (a separate, earlier failure path for DB errors during
        // signup) calls it with the same prefix. Taking the LAST regex match
        // in the file always lands on the validation-failure branch's call
        // (it appears later in the file); if that later occurrence is ever
        // removed, end() naturally falls back to this earlier one — an
        // emergent property of always taking the last match, not designed
        // fallback branching. The pattern tolerates whitespace variation
        // around commas/brackets (\s*) so a cosmetic reformat of join.php
        // doesn't fail this test for a non-substantive change.
        preg_match_all(
            '/handleAuthFailure\(\s*\'registration_attempt\'\s*,\s*null\s*,\s*\$email\s*,\s*\[\s*\]\s*,\s*\[/',
            $source,
            $startMatches,
            PREG_OFFSET_CAPTURE
        );
        $this->assertNotEmpty($startMatches[0], 'Could not locate the registration-failure branch start marker in usersc/join.php');
        $branchStart = end($startMatches[0])[1];

        preg_match(
            '/usError\(\s*\'' . preg_quote($genericMessage, '/') . '\'\s*\)\s*;/',
            $source,
            $endMatch,
            PREG_OFFSET_CAPTURE
        );
        $this->assertNotEmpty($endMatch, 'Could not locate the generic usError() call in usersc/join.php');
        $branchEnd = $endMatch[0][1];

        $this->assertLessThan($branchEnd, $branchStart, 'Failure-branch markers are out of expected order');

        $branch = substr($source, $branchStart, $branchEnd - $branchStart);

        $this->assertSame(
            0,
            substr_count($branch, 'usError('),
            'No usError() call may appear between recording the failed attempt and showing the generic '
                . 'message — any such call would be a differentiated, potentially enumeration-leaking message.'
        );
    }

    /**
     * The recovery-notification call must be keyed on whether an account
     * exists for the submitted email — never on which validation rule broke.
     * This is what makes the #1442 edge case (a username-only collision with
     * no real email collision) safe: the notifier's own exists()-gated logic
     * is unit-tested directly, but this asserts the *call site* wires it up
     * that way (single unconditional call inside the failure branch, not
     * one gated by which field the validation error was on).
     */
    public function testRecoveryNotificationCallIsUnconditionalOnValidationFailure(): void
    {
        $source = file_get_contents(self::JOIN_PHP_PATH);
        $this->assertIsString($source, 'usersc/join.php must be readable');

        $this->assertStringContainsString(
            'notifyIfAccountExists($fuser, $email, $settings)',
            $source,
            'The failure branch must call notifyIfAccountExists() — the notifier class itself decides '
                . 'whether an account exists; the call site must not pre-filter by validation error type.'
        );

        $this->assertStringNotContainsString(
            "\$validation->_errors[0][1] === 'email'",
            $source,
            'The call site must not branch on which specific field failed validation — that would '
                . 'reintroduce the #1442 gap (a username-only collision must still resolve correctly '
                . 'via exists(), not be inferred from validation error content).'
        );
    }

    /**
     * The account lookup ahead of notifyIfAccountExists() must resolve $fuser
     * against the email column specifically, via User's 'forceEmail' login
     * handler — not the default field-detection logic, which falls back to a
     * username-column lookup for any string that isn't a valid email address.
     * This branch can be reached with a malformed $email (validation failed
     * on valid_email), so without 'forceEmail' a non-email string could match
     * an unrelated user by username and overwrite that user's vericode.
     */
    public function testAccountLookupForcesEmailColumn(): void
    {
        $source = file_get_contents(self::JOIN_PHP_PATH);
        $this->assertIsString($source, 'usersc/join.php must be readable');

        $this->assertMatchesRegularExpression(
            '/new\s+\\\\?User\(\s*\$email\s*,\s*\'forceEmail\'\s*\)/',
            $source,
            'The failure branch must resolve $fuser via new User($email, \'forceEmail\') — omitting '
                . '\'forceEmail\' would let a malformed $email fall through to a username-column lookup '
                . 'and could overwrite an unrelated user\'s vericode.'
        );
    }

    /**
     * The recovery-notification side effects (checkRateLimit/new User/recordRateLimit —
     * everything RegistrationRecoveryNotifier's own try/catch does NOT cover, since that
     * only guards its internals) must be wrapped in a try/catch(\Throwable) that resolves
     * before the generic failure message is shown. Without this, a DB error in any of
     * those calls (e.g. RateLimit's constructor failing to create its table) would fatal
     * uncaught instead of falling through to the generic response — itself a
     * distinguishing, enumeration-adjacent failure mode.
     */
    public function testRecoveryNotificationSideEffectsAreExceptionSafe(): void
    {
        $source = file_get_contents(self::JOIN_PHP_PATH);
        $this->assertIsString($source, 'usersc/join.php must be readable');

        $probePos = strpos($source, "checkRateLimit('registration_recovery_email'");
        $this->assertNotFalse($probePos, 'Could not locate the registration_recovery_email checkRateLimit() call');

        $tryPos = strrpos(substr($source, 0, $probePos), 'try {');
        $this->assertNotFalse(
            $tryPos,
            'The recovery-notification side effects must be preceded by a try { — an uncaught DB error '
                . 'here would fatal instead of falling through to the generic failure response.'
        );

        $catchPos = strpos($source, 'catch (\Throwable', $probePos);
        $this->assertNotFalse(
            $catchPos,
            'The recovery-notification side effects must be followed by a catch (\Throwable ...) block, '
                . 'broad enough to catch any DB-layer exception (RateLimit\'s constructor, User\'s '
                . 'constructor/find(), DB::query()), not just a narrower Exception type.'
        );

        $genericMessage = 'Check your inbox — if an account exists, we have sent you a sign-in link. Otherwise, please check your information and try again.';
        $genericMessagePos = strpos($source, "usError('{$genericMessage}');");
        $this->assertNotFalse($genericMessagePos, 'Could not locate the generic usError() call');

        $this->assertTrue(
            $tryPos < $probePos && $probePos < $catchPos && $catchPos < $genericMessagePos,
            'The try/catch must fully enclose the recovery-notification side effects and resolve before '
                . 'the generic failure message is shown.'
        );
    }

    /**
     * The registration_recovery_email rate-limit key must be case-normalized
     * before being passed to checkRateLimit()/recordRateLimit(). The users.email
     * column uses a case-insensitive collation (utf8mb4_unicode_ci), so
     * new \User($email, 'forceEmail') resolves 'Victim@x.com' and
     * 'victim@x.com' to the same account — without normalizing the rate-limit
     * key the same way, an attacker could vary the submitted email's case on
     * each attempt to dodge the email_max cap while still targeting that one
     * real account (the exact mail-bombing abuse this rate limit exists to stop).
     */
    public function testRateLimitKeyIsCaseNormalized(): void
    {
        $source = file_get_contents(self::JOIN_PHP_PATH);
        $this->assertIsString($source, 'usersc/join.php must be readable');

        $this->assertMatchesRegularExpression(
            '/mb_strtolower\(\s*\$email\s*\)/',
            $source,
            'The rate-limit key must be derived from mb_strtolower($email) so case variations '
                . 'of the same email collapse to the same rate-limit bucket.'
        );

        $this->assertStringNotContainsString(
            "checkRateLimit('registration_recovery_email', null, \$email)",
            $source,
            'checkRateLimit() must not be called with the raw, case-sensitive $email — that would '
                . 'let an attacker bypass email_max by varying case on each attempt.'
        );

        $this->assertStringNotContainsString(
            "recordRateLimit('registration_recovery_email', false, null, \$email)",
            $source,
            'recordRateLimit() must not be called with the raw, case-sensitive $email — that would '
                . 'let an attacker bypass email_max by varying case on each attempt.'
        );
    }

    /**
     * Timing-equalization must apply to EVERY outcome (account exists,
     * doesn't exist, rate-limited, malformed email, or a caught DB error) —
     * not just the "doesn't exist" branch. A one-sided sleep() there would
     * leave the "exists" branch (real DB write + email_body() render +
     * synchronous email() send — plausibly sub-second for a transactional
     * email API) measurably faster, reopening via response latency the exact
     * enumeration oracle #1406 closes for response content.
     */
    public function testTimingEqualizationAppliesToAllOutcomesNotJustNonExistentEmail(): void
    {
        $source = file_get_contents(self::JOIN_PHP_PATH);
        $this->assertIsString($source, 'usersc/join.php must be readable');

        $this->assertStringNotContainsString(
            'fuser->exists()) {',
            $source,
            'Timing-equalization must not be gated behind whether the account exists — that is '
                . 'exactly the one-sided pattern that reintroduces the timing side-channel.'
        );

        $this->assertStringContainsString(
            'finally {',
            $source,
            'The recovery-notification try block must have a finally clause enforcing a timing '
                . 'floor unconditionally, regardless of which branch executed or whether it threw.'
        );

        $this->assertMatchesRegularExpression(
            '/microtime\(\s*true\s*\)/',
            $source,
            'Timing equalization must be based on measured elapsed time (microtime(true)), not a '
                . 'fixed one-sided sleep().'
        );
    }
}
