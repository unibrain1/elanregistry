<?php
/**
 * Generic Error Page Handler
 *
 * Canonical handler for all site error pages: 400, 401, 403, 404, 405, 408,
 * 500, 502, 504. Despite the filename, this file is not 500-specific — the
 * actual status code is read from $_SERVER['REDIRECT_STATUS'] at runtime.
 * The name is retained for continuity (minimizes the .htaccess diff and
 * keeps the most mechanically complete existing handler as the base).
 *
 * Gracefully falls back to a generic 500-style message for any status code
 * not present in $errorMessages/$logCategoryMap.
 *
 * @package ElanRegistry
 * @since 2.12.0
 */

declare(strict_types=1);

use ElanRegistry\LogCategories;

// Get the HTTP status code from server variables. http_response_code()
// returns int|false (never null), so its result can't itself be chained
// with ?? — a plain OR-like fallback via ?: is used instead.
$currentResponseCode = http_response_code();
$statusCode = (int)($_SERVER['REDIRECT_STATUS'] ?? ($currentResponseCode ?: 500));

// Set proper HTTP response code
http_response_code($statusCode);

// Anti-clickjacking headers (set explicitly in case init.php fails to load).
// Intentionally minimal: frame-ancestors only. If a <form> is ever added to
// this page, add form-action 'self' here before shipping.
header("X-Frame-Options: SAMEORIGIN");
header("Content-Security-Policy: frame-ancestors 'self'");

// Try to initialize UserSpice session for personalized navigation
$isLoggedIn = false;
$userName = '';

try {
    if (file_exists(__DIR__ . '/../users/init.php')) {
        require_once __DIR__ . '/../users/init.php';
        if (isset($user) && $user->isLoggedIn()) {
            $isLoggedIn = true;
            $userData = $user->data();
            $userName = $userData->fname ?? '';
        }
    }
} catch (Throwable $e) {
    // Show anonymous version — but this failure must not be silent: logger()
    // is defined inside init.php, so if init.php itself throws, the normal
    // logger() path below is unreachable and this is the only trace this
    // failure will ever leave.
    error_log('500.php: init.php failed to load — ' . get_class($e) . ': ' . $e->getMessage());
    $isLoggedIn = false;
}

// Ensure server globals are available (may not be if init.php failed)
if (!isset($request_uri)) {
    require_once __DIR__ . '/../users/classes/Server.php';
    require_once __DIR__ . '/../usersc/includes/server_globals.php';
}

// Log the error for administrator review
$userId = ($isLoggedIn && isset($userData->id)) ? (int)$userData->id : 0;

// Determine log category based on error code. Guarded by class_exists():
// the Composer autoloader is only registered inside users/init.php, which
// this page loads inside a try/catch above precisely because it may be
// missing or fail before the autoloader registers. Referencing
// LogCategories:: constants unconditionally in that state would fatal this
// page too — exactly when a working error page matters most. Falls back to
// the same string literals LogCategories::LOG_CATEGORY_* resolve to, so
// logger() (if reachable at all) still receives a valid category.
if (class_exists(LogCategories::class)) {
    $logCategoryMap = [
        400 => LogCategories::LOG_CATEGORY_VALIDATION_ERROR,
        401 => LogCategories::LOG_CATEGORY_ACCESS_DENIED,
        403 => LogCategories::LOG_CATEGORY_ACCESS_DENIED,
        404 => LogCategories::LOG_CATEGORY_PAGE_NOT_FOUND,
        405 => LogCategories::LOG_CATEGORY_SYSTEM_ERROR,
        408 => LogCategories::LOG_CATEGORY_SYSTEM_ERROR,
        500 => LogCategories::LOG_CATEGORY_SYSTEM_ERROR,
        502 => LogCategories::LOG_CATEGORY_SYSTEM_ERROR,
        504 => LogCategories::LOG_CATEGORY_SYSTEM_ERROR,
    ];
    $logCategory = $logCategoryMap[$statusCode] ?? LogCategories::LOG_CATEGORY_SYSTEM_ERROR;
} else {
    $logCategoryMap = [
        400 => 'ValidationError',
        401 => 'AccessDenied',
        403 => 'AccessDenied',
        404 => 'PageNotFound',
        405 => 'SystemError',
        408 => 'SystemError',
        500 => 'SystemError',
        502 => 'SystemError',
        504 => 'SystemError',
    ];
    $logCategory = $logCategoryMap[$statusCode] ?? 'SystemError';
}

$logMessage = sprintf(
    "%d Error | URI: %s | Referer: %s | IP: %s | Method: %s | User-Agent: %s",
    $statusCode,
    $request_uri,
    $referer ?: 'direct',
    $remote_addr,
    $method,
    substr($user_agent, 0, 150)
);

// 404s on static-asset paths (images, css, js, sourcemaps) are routine
// noise — bots and stale links account for a large volume of these — and
// would otherwise recreate the log-volume problem closed as won't-fix in
// #1477. All other codes, including 404s on non-static paths, log normally.
$skipLogging = false;
if ($statusCode === 404) {
    $staticExtensions = ['jpg', 'jpeg', 'png', 'gif', 'css', 'js', 'map'];
    $requestPath = parse_url($request_uri ?? '', PHP_URL_PATH) ?: '';
    $ext = strtolower(pathinfo($requestPath, PATHINFO_EXTENSION));
    $skipLogging = in_array($ext, $staticExtensions, true);
}

if (!$skipLogging && function_exists('logger')) {
    try {
        logger($userId, $logCategory, $logMessage);
    } catch (Throwable $e) {
        // Silently fail if logging not available
    }
}

// Define error messages for supported status codes
$errorMessages = [
    400 => [
        'title' => 'Bad Request',
        'message' => 'The server cannot process your request due to invalid syntax.',
        'icon_type' => 'warning'
    ],
    401 => [
        'title' => 'Unauthorized',
        'message' => 'You must be authenticated to access this resource.',
        'icon_type' => 'lock'
    ],
    403 => [
        'title' => 'Access Forbidden',
        'message' => "You don't have permission to access this resource. This area may require special privileges or authentication.",
        'icon_type' => 'lock'
    ],
    404 => [
        'title' => 'Page Not Found',
        'message' => "The page you're looking for doesn't exist, has been moved, or the URL was mistyped.",
        'icon_type' => 'search'
    ],
    405 => [
        'title' => 'Method Not Allowed',
        'message' => 'The request method is not supported for this resource.',
        'icon_type' => 'warning'
    ],
    408 => [
        'title' => 'Request Timeout',
        'message' => 'Your request took too long to process. Please try again.',
        'icon_type' => 'hourglass'
    ],
    500 => [
        'title' => 'Internal Server Error',
        'message' => 'An unexpected error occurred on the server. Please try again later.',
        'icon_type' => 'error'
    ],
    502 => [
        'title' => 'Bad Gateway',
        'message' => 'The server received an invalid response. Please try again later.',
        'icon_type' => 'error'
    ],
    504 => [
        'title' => 'Gateway Timeout',
        'message' => 'The server took too long to respond. Please try again later.',
        'icon_type' => 'hourglass'
    ],
];

// Use provided error details or defaults
$errorInfo = $errorMessages[$statusCode] ?? [
    'title' => 'Server Error',
    'message' => 'An unexpected error occurred. Please contact support if the problem persists.',
    'icon_type' => 'error'
];

$errorTitle = $errorInfo['title'];
$errorMessage = $errorInfo['message'];
$iconType = $errorInfo['icon_type'];

// Icon SVGs per icon_type. lock/search sourced from the former dedicated
// 403.php/404.php pages; warning/hourglass/error share one generic icon
// (as the pre-consolidation 500.php did for all codes) — no new SVGs
// invented for those, out of scope for this consolidation.
$lockIconSvg = <<<SVG
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="5" y="11" width="14" height="10" rx="2" fill="#d9230f"/>
                        <path d="M8 11V7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7V11"
                              stroke="#d9230f" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="12" cy="16" r="1.5" fill="white"/>
                        <rect x="11.25" y="16" width="1.5" height="3" fill="white"/>
                    </svg>
SVG;

$searchIconSvg = <<<SVG
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="11" cy="11" r="7" stroke="#d9230f" stroke-width="2" fill="none"/>
                        <path d="M15.5 15.5L20 20" stroke="#d9230f" stroke-width="2" stroke-linecap="round"/>
                        <text x="11" y="14" text-anchor="middle" fill="#d9230f" font-size="8" font-weight="bold">?</text>
                    </svg>
SVG;

$genericIconSvg = <<<SVG
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="10" stroke="#d9230f" stroke-width="2" fill="none"/>
                        <line x1="12" y1="8" x2="12" y2="12" stroke="#d9230f" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="12" cy="16" r="0.5" fill="#d9230f"/>
                    </svg>
SVG;

$errorIconSvg = match ($iconType) {
    'lock' => $lockIconSvg,
    'search' => $searchIconSvg,
    default => $genericIconSvg,
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($statusCode . ' ' . $errorTitle, ENT_QUOTES, 'UTF-8') ?> - Lotus Elan Registry</title>
    <link href="<?= $us_url_root ?? '/' ?>users/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --elan-red: #d9230f;
            --elan-green: #469408;
            --elan-dark: #373a3c;
        }

        body {
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Navbar styling to match site */
        .navbar {
            background-color: var(--elan-dark) !important;
        }

        .navbar-brand img {
            height: 40px;
        }

        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.9) !important;
            padding: 0.5rem 1rem;
        }

        .navbar-nav .nav-link:hover {
            color: #fff !important;
        }

        /* Main content area */
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* Card styling to match site */
        .error-card {
            background: #fff;
            border: 1px solid rgba(0,0,0,0.125);
            border-radius: 4px;
            max-width: 600px;
            width: 100%;
        }

        .error-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,0.125);
            padding: 1rem 1.25rem;
        }

        .error-card .card-header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 400;
            color: #333;
        }

        .error-card .card-body {
            padding: 1.5rem;
            text-align: center;
        }

        .error-code {
            font-size: 5rem;
            font-weight: 700;
            color: var(--elan-red);
            line-height: 1;
            margin-bottom: 10px;
        }

        .error-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 15px;
        }

        .error-message {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .error-icon {
            margin-bottom: 20px;
        }

        /* Button styling to match site */
        .btn-elan-red {
            background-color: var(--elan-red);
            border-color: var(--elan-red);
            color: #fff;
        }

        .btn-elan-red:hover {
            background-color: #b81d0c;
            border-color: #b81d0c;
            color: #fff;
        }

        .btn-elan-green {
            background-color: var(--elan-green);
            border-color: var(--elan-green);
            color: #fff;
        }

        .btn-elan-green:hover {
            background-color: #3a7a07;
            border-color: #3a7a07;
            color: #fff;
        }

        .user-greeting {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            color: var(--elan-dark);
        }
    </style>
</head>
<body>
    <!-- Navbar matching site design -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="/">
                <img src="<?= $us_url_root ?? '/' ?>usersc/images/logo-72x72.png"
                     alt="Lotus Elan Registry"
                     onerror="this.parentElement.innerHTML='Lotus Elan Registry'">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">Home</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <div class="main-content">
        <div class="error-card">
            <div class="card-header">
                <h1><?= htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
            <div class="card-body">
                <?php if ($isLoggedIn && $userName): ?>
                <div class="user-greeting">
                    Logged in as <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>

                <div class="error-icon">
                    <?= $errorIconSvg ?>
                </div>

                <div class="error-code"><?= $statusCode ?></div>
                <h2 class="error-title"><?= htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="error-message">
                    <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                </p>

                <div class="btn-group" role="group">
                    <a href="/" class="btn btn-elan-red">Return Home</a>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= $us_url_root ?? '/' ?>users/js/bootstrap.bundle.min.js"></script>
</body>
</html>
