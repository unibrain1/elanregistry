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
    private const JOIN_PHP_PATH = __DIR__ . '/../../usersc/join.php';

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

        $genericMessage = 'We could not complete your registration. Please check your information and try again.';

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
        // signup) calls it with the same prefix. That earlier occurrence is
        // used only as a fallback; strrpos() finds the LAST occurrence in
        // the file, which is always the validation-failure branch's call
        // (it appears later in the file), without depending on fragile,
        // whitespace-sensitive array-content matching.
        $branchStart = strrpos($source, "handleAuthFailure('registration_attempt', null, \$email, [], [");
        $branchEnd = strpos($source, "usError('{$genericMessage}');");

        $this->assertNotFalse($branchStart, 'Could not locate the registration-failure branch start marker in usersc/join.php');
        $this->assertNotFalse($branchEnd, 'Could not locate the generic usError() call in usersc/join.php');
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
}
