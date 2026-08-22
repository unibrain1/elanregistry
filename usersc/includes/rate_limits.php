<?php
/*
By default, UserSpice ships with EXTREMELY high rate limits. The goal is to provide a framework for rate limiting without 
blocking legitimate users. The defaults are set to allow for a high number of attempts over a long period of time.

Below I have provided a more reasonable set of defaults that you can use to get started.  You can either use these lower limits
or you can override certain limits individually. See below.

*/

// Recommended Reasonable Rate Limits (Enabled for Issue #418)
// Production rate limits enabled to mitigate SQL timing attack vulnerabilities

$rateLimits = [
    // Authentication attempts
    'login_attempt' => [
        // Limits based on IP address. Set higher to avoid blocking legitimate users on shared networks (e.g., universities, corporate NATs).
        'ip_max' => 20,           // Max FAILED attempts from a single IP.
        'ip_window' => 900,       // 15-minute window for the IP failure count to reset.

        // Limits based on User ID/Username. Set lower to quickly block targeted brute-force attacks on a specific account.
        'user_max' => 5,          // Max FAILED attempts for a single user account.
        'user_window' => 300,     // 5-minute window for a user's failure count to reset. A shorter window allows legitimate users to retry sooner.

        // A high-level circuit breaker for a single misbehaving identifier (IP or User). This is a limit on TOTAL attempts (successful + failed).
        'total_max' => 50,        // Max TOTAL attempts from one IP or for one User. Prevents a single identifier from spamming the system.
        'total_window' => 900     // 15-minute window, same as the IP failure window.
    ],


    // Multi-Factor Authentication (MFA)
    'totp_verify' => [
        'ip_max' => 10,
        'ip_window' => 600,
        'user_max' => 5,
        'user_window' => 600,
        'total_max' => 30,
        'total_window' => 600
    ],

    'totp_verify_and_activate' => [
        'ip_max' => 5,
        'ip_window' => 3600,
        'user_max' => 3,
        'user_window' => 3600,
        'total_max' => 20,
        'total_window' => 3600
    ],

    'totp_regenerate_backup_codes' => [
        'ip_max' => 3,
        'ip_window' => 3600,
        'user_max' => 2,
        'user_window' => 3600,
        'total_max' => 10,
        'total_window' => 3600
    ],

    // Passkey operations
     'passkey_register' => [ 
        'ip_max' => 8,  
        'ip_window' => 3600,                       
        'user_max' => 3, 
        'user_window' => 3600,
        'total_max'=> 25,
        'total_window'=> 3600 ],

    'passkey_verify' => [
        'ip_max'         => 30,
        'ip_window'   => 600,   // 30 fails / 10 min / IP
        'user_max'       => 10,
        'user_window' => 600,   // 10 fails / 10 min / account
        'credential_max' => 6,
        'credential_window' => 900, // 6 fails / 15 min / cred
        'total_max'      => 100,
        'total_window' => 900
    ],
    'passkey_store' => [
        'ip_max'   => 8,
        'ip_window'   => 3600,
        'user_max' => 3,
        'user_window' => 3600,
        'total_max' => 25,
        'total_window' => 3600
    ],

    // Password and account recovery
    'password_reset_request' => [
        'ip_max' => 5,
        'ip_window' => 3600,
        'email_max' => 3,
        'email_window' => 3600,
        'total_max' => 25,
        'total_window' => 3600
    ],

    // Silent recovery notification sent when a registration attempt targets
    // an already-registered email (#1406) — separate action from
    // password_reset_request so audit logs distinguish a self-initiated
    // password reset from a notification triggered by someone else's
    // registration attempt. Same limits as password_reset_request.
    'registration_recovery_email' => [
        'ip_max' => 5,
        'ip_window' => 3600,
        'email_max' => 3,
        'email_window' => 3600,
        'total_max' => 25,
        'total_window' => 3600
    ],

    'password_reset_submit' => [
        'ip_max' => 8,
        'ip_window' => 1800,
        'token_max' => 4,
        'token_window' => 1800,
        'total_max' => 30,
        'total_window' => 1800
    ],

    // Registration and verification
    'registration_attempt' => [
        'ip_max' => 5,
        'ip_window' => 3600,
        'total_max' => 20,
        'total_window' => 3600
    ],

    // Client-side join-failure beacon (#1690) — deliberately its own,
    // higher-ceiling config rather than sharing registration_attempt's tight
    // 5/hr IP bucket. A shared bucket would let beacon calls (Turnstile
    // retries, GPS failures, JS exceptions — none of them a real
    // registration attempt) exhaust the cap for every visitor behind a
    // shared/NAT IP before any of them could actually submit the form.
    'join_failure_beacon' => [
        'ip_max' => 30,
        'ip_window' => 3600,
        'total_max' => 100,
        'total_window' => 3600
    ],

    'email_verification' => [
        'ip_max' => 5,
        'ip_window' => 3600,
        'email_max' => 4,
        'email_window' => 3600,
        'total_max' => 30,
        'total_window' => 3600
    ],

    // Contact and communication
    'owner_contact_email' => [
        'user_max' => 5,
        'user_window' => 3600,
        'ip_max' => 10,
        'ip_window' => 3600,
        'total_max' => 20,
        'total_window' => 3600,
    ],

    'feedback_submission' => [
        'ip_max' => 10,
        'ip_window' => 3600,
        'total_max' => 50,
        'total_window' => 3600,
    ],

    'transfer_request' => [
        'user_max' => 3,
        'user_window' => 3600,
        'ip_max' => 5,
        'ip_window' => 3600,
        'total_max' => 20,
        'total_window' => 3600,
    ],

    // Development/testing - very lenient
    'diagnostics' => [
        'ip_max' => 100,
        'ip_window' => 300,
        'user_max' => 100,
        'user_window' => 300,
        'total_max' => 500,
        'total_window' => 300
    ],

    'statistics_request' => [
        'ip_max'       => 20,
        'ip_window'    => 60,
        'user_max'     => 30,
        'user_window'  => 60,
        'total_max'    => 100,
        'total_window' => 60,
    ],

    // Admin AJAX — ip_max/ip_window are present because the validator requires them, but set
    // to PHP_INT_MAX so they never trigger; admin volume is controlled per-user via total_max:
    //   • per-IP failure counting doesn't apply — authenticated admin sessions are tracked reliably
    //   • user_max (failed-only) doesn't apply — volume, not failure count, is the concern
    //   • total_max is still enforced per-identifier (IP and user) by the RateLimit engine
    'admin_ajax_search' => [
        'ip_max'       => PHP_INT_MAX,
        'ip_window'    => 60,
        'total_max'    => 30,
        'total_window' => 60,
    ],
    'admin_ajax_write' => [
        'ip_max'       => PHP_INT_MAX,
        'ip_window'    => 60,
        'total_max'    => 10,
        'total_window' => 60,
    ],

    // Location search/reverse-geocode autocomplete (#1582). Shared by both
    // LocationService::searchLocation() and reverseGeocode() — there is no
    // separate location_reverse action. ip_max is set to PHP_INT_MAX only to
    // satisfy the validator's required-key check and to disable the
    // failed-attempts-only IP sub-limit — it does NOT disable per-IP scoping.
    // total_max is the limit that actually governs anonymous traffic, and
    // RateLimit::check() keys total_max by identifier (IP, for an anonymous
    // caller) just like ip_max, so this remains a genuine per-visitor bucket,
    // not a shared global one — see tests/integration/LocationRateLimitIsolationTest.php.
    'location_search' => [
        'ip_max'       => PHP_INT_MAX,
        'ip_window'    => 60,
        'total_max'    => 10,
        'total_window' => 60,
    ],
];

//You can also override individual limits with custom values.
// $rateLimits['login_attempt']['ip_max'] = 50;
// $rateLimits['login_attempt']['ip_window'] = 1800;

// During development, you may want to use more lenient limits.

/*
if (defined('US_ENVIRONMENT') && US_ENVIRONMENT === 'development') {
    // More lenient limits for development
    foreach ($rateLimits as $action => &$limits) {
        foreach ($limits as $key => &$value) {
            if (strpos($key, '_max') !== false) {
                $value = (int)($value * 100); // Massively increase limits for development
            }
        }
    }
    unset($limits, $value); // Clean up references
}
*/