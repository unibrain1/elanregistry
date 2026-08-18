<?php
/*
 * Custom Cloak Policy
 * -------------------
 * Controls what a *cloaked* admin (one who used "log in as" / impersonation to
 * become another user) may do on that user's credential-management pages.
 *
 * Background: enrolling a passkey or TOTP factor is as sensitive as changing a
 * password. For a normal logged-in user, UserSpice gates those pages behind
 * step-up re-authentication (forceReauth / reauth.php). A cloaked admin has
 * already authenticated to start the cloak, so they are NOT asked to re-auth
 * again — instead, whether they may manage the impersonated user's passkeys or
 * TOTP at all is decided HERE.
 *
 * Secure default: BOTH are false. While cloaked, an admin cannot register or
 * delete the impersonated user's passkeys, nor enrol/disable their TOTP. They
 * are redirected back to the account page with a notice. (Admins can still
 * reset passwords as before — this file governs passkeys/TOTP only.)
 *
 * A missing file or an unset variable is treated as false, so deleting this
 * file is equivalent to denying both.
 *
 * Set either to true to ALLOW a cloaked admin to manage that factor for the
 * user they are impersonating. When either is true, the Security Dashboard
 * flags it as a deliberate, relaxed setting.
 */

$cloak_can_manage_passkeys = false; // allow cloaked admin to manage the target's passkeys?
$cloak_can_manage_totp     = false; // allow cloaked admin to manage the target's TOTP?