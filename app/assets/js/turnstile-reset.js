/**
 * Shared Turnstile Reset Helper
 *
 * A minimal, form-ID-agnostic reset mechanism for Cloudflare Turnstile
 * widgets. Extracted so the login form (which has no failure-beacon
 * reporting concern) and the join form (whose more elaborate
 * join-form-beacon.js also reports failures via a CSRF-scoped beacon) can
 * both wire a working `data-error-callback`/`data-expired-callback` without
 * duplicating the `turnstile.reset()` call in two places.
 *
 * `elanTurnstileError`/`elanTurnstileExpired` here are the same global
 * function names `usersc/includes/turnstile.php`'s `addTurnstile(true)`
 * wires into the widget's `data-*-callback` attributes. join-form-beacon.js
 * redefines these two names with its own richer join-specific versions
 * (status message + failure beacon) that call `window.elanTurnstileReset()`
 * internally — so on the join page, this file's own
 * `elanTurnstileError`/`elanTurnstileExpired` definitions are intentionally
 * overwritten, as long as this script loads before join-form-beacon.js.
 *
 * @package ElanRegistry
 * @since 2.29.4
 * @link https://github.com/elan-registry/registry/issues/1798
 */
(function () {
    'use strict';

    window.elanTurnstileReset = function () {
        if (window.turnstile && typeof window.turnstile.reset === 'function') {
            window.turnstile.reset();
        }
    };

    window.elanTurnstileError = function () {
        window.elanTurnstileReset();
    };

    window.elanTurnstileExpired = function () {
        window.elanTurnstileReset();
    };
})();
