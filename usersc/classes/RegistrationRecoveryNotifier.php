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
 * Accepted trade-off: this overwrites the target account's shared
 * users.vericode/vericode_expiry columns — the same columns used by that
 * account's own pending email-verification link (from signup) and by
 * users/forgot_password.php's password-reset flow. A failed registration
 * attempt targeting an existing email (bounded to 3/hour by the
 * registration_recovery_email rate limit — see usersc/join.php) will
 * silently invalidate whichever link is currently outstanding for that
 * account, even if it was never requested by the account holder. This is
 * not a lockout — a fresh, working link is issued every time — and mirrors
 * a risk already inherent to forgot_password.php itself (repeated
 * self-service reset requests already overwrite the same columns). Deemed
 * an acceptable trade-off given the alternative (a separate token/column
 * just for this notification) adds real complexity to close a low-severity,
 * self-healing edge case.
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
     * pointing at the existing password-reset verification flow. If the vericode
     * write fails, the method returns false and no email is attempted.
     *
     * @param \User  $fuser    UserSpice user resolved from the email address.
     * @param string $email    The email address the registration was attempted with (raw, un-encoded).
     * @param object $settings UserSpice settings object; must expose reset_vericode_expiry (minutes) and site_name.
     * @return bool True only if an account existed and the recovery email was sent successfully; false otherwise.
     */
    public function notifyIfAccountExists(\User $fuser, string $email, object $settings): bool
    {
        $userId = 0;
        try {
            if (!$fuser->exists()) {
                return false;
            }

            // \User::data()->id may come back from PDO as a numeric string rather than
            // an int (see docs/development/STRICT_TYPE_HANDLING.md) — DB::update()'s
            // $id parameter is strictly typed int, and this file declares strict_types=1.
            $userId = (int) $fuser->data()->id;

            $vericode = randomstring(15);
            $hashedVericode = hashVericode($vericode);
            $vericodeExpiry = date(
                "Y-m-d H:i:s",
                strtotime("+{$settings->reset_vericode_expiry} minutes", strtotime(date("Y-m-d H:i:s")))
            );

            if (!$this->db->update('users', $userId, [
                'vericode' => $hashedVericode,
                'vericode_expiry' => $vericodeExpiry,
            ])) {
                logger(
                    $userId,
                    LogCategories::LOG_CATEGORY_EMAIL_ERROR,
                    'RegistrationRecoveryNotifier: DB update failed — vericode not persisted, recovery email not sent'
                );
                return false;
            }

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

            $siteName = html_entity_decode($settings->site_name, ENT_QUOTES);
            $subject = "Someone tried to register with your email — {$siteName}";
            $emailResult = email($email, $subject, $body);

            if ($emailResult !== true) {
                $safeToLog = preg_replace('/[\r\n\t]/', '', $email) ?? '[email unavailable]';
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
                $userId,
                LogCategories::LOG_CATEGORY_EMAIL_ERROR,
                'RegistrationRecoveryNotifier: unexpected failure — ' . get_class($e) . ': ' . $safeToLog
            );
            return false;
        }
    }
}
