<?php

declare(strict_types=1);

namespace ElanRegistry;

/**
 * Registration Recovery Notifier
 *
 * Sends a password-recovery notification when someone attempts to register a
 * new account using an email address that already belongs to an existing
 * account. This closes the account-enumeration vector on the registration
 * form (issue #1406): the join flow behaves identically whether or not the
 * email already exists, and the only account-specific action — this
 * notification — is delivered privately to the existing account holder.
 *
 * The token-generation, hashing, expiry, and storage mechanism intentionally
 * mirrors the proven password-reset flow in users/forgot_password.php so that
 * the emitted recovery link resolves against the existing
 * users/forgot_password_reset.php verification endpoint. No new endpoint or
 * setting is introduced.
 *
 * This notifier must never alter the observable behaviour of the registration
 * flow that invokes it. Every failure path is logged and swallowed; the method
 * returns a boolean and never throws.
 *
 * @package    ElanRegistry
 * @subpackage Classes
 * @since      v2.29.0
 */
class RegistrationRecoveryNotifier
{
    /**
     * @param \DB $db UserSpice database handle used to persist the recovery vericode.
     */
    public function __construct(private \DB $db)
    {
    }

    /**
     * Send a recovery notification if an account already exists for the email.
     *
     * When the account does not exist, this is a no-op and returns false with no
     * database write and no email sent — the caller must treat that outcome as
     * "nothing observable happened".
     *
     * When the account exists, a fresh reset vericode is generated, hashed, and
     * stored (mirroring users/forgot_password.php), and a recovery email is sent
     * pointing at the existing password-reset verification flow.
     *
     * @param \User  $fuser    UserSpice user resolved from the email address.
     * @param string $email    The email address the registration was attempted with (raw, un-encoded).
     * @param object $settings UserSpice settings object; must expose reset_vericode_expiry (minutes).
     * @return bool True only if an account existed and the recovery email was sent successfully; false otherwise.
     */
    public function notifyIfAccountExists(\User $fuser, string $email, object $settings): bool
    {
        try {
            if (!$fuser->exists()) {
                return false;
            }

            $userId = $fuser->data()->id;

            $vericode = randomstring(15);
            $hashedVericode = hashVericode($vericode);
            $vericodeExpiry = date(
                "Y-m-d H:i:s",
                strtotime("+{$settings->reset_vericode_expiry} minutes", strtotime(date("Y-m-d H:i:s")))
            );

            $this->db->update('users', $userId, [
                'vericode' => $hashedVericode,
                'vericode_expiry' => $vericodeExpiry,
            ]);

            $options = [
                'fname' => $fuser->data()->fname,
                'email' => rawurlencode($email),
                'vericode' => $vericode,
                'user_id' => $userId,
                'reset_vericode_expiry' => $settings->reset_vericode_expiry,
            ];

            $body = email_body('_email_template_registration_attempt.php', $options);

            if ($body === '') {
                logger(
                    $userId,
                    LogCategories::LOG_CATEGORY_EMAIL_ERROR,
                    'RegistrationRecoveryNotifier: email_body() returned empty — template missing or failed',
                    ['template' => '_email_template_registration_attempt.php']
                );
                return false;
            }

            $subject = 'Someone tried to register with your email — Lotus Elan Registry';
            $emailResult = email($email, $subject, $body);

            if ($emailResult !== true) {
                $safeToLog = preg_replace('/[\r\n\t]/', '', $email);
                logger(
                    $userId,
                    LogCategories::LOG_CATEGORY_EMAIL_ERROR,
                    'RegistrationRecoveryNotifier: recovery notification SEND FAILED to ' . $safeToLog
                );
                return false;
            }

            logger(
                $userId,
                LogCategories::LOG_CATEGORY_SECURITY,
                'Registration attempted with existing email — recovery notification sent.'
            );

            return true;
        } catch (\Throwable $e) {
            $safeToLog = preg_replace('/[\r\n\t]/', ' ', $e->getMessage()) ?? '[message unavailable]';
            logger(
                0,
                LogCategories::LOG_CATEGORY_EMAIL_ERROR,
                'RegistrationRecoveryNotifier: unexpected failure — ' . get_class($e) . ': ' . $safeToLog
            );
            return false;
        }
    }
}
