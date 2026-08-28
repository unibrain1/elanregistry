<?php

declare(strict_types=1);

use ElanRegistry\LogCategories;

/**
 * Shared infrastructure for admin fix and maintenance scripts.
 *
 * Include after require_once '../../../../users/init.php' and before
 * require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php'.
 *
 * Provides: POST + CSRF + admin-role gate, start-form HTML, close-button HTML, logProgress().
 */

define('SECTION_SEPARATOR', '═══════════════════════════════════════════════════════');

/**
 * Returns true only when the current request is a POST with a valid CSRF token
 * from an admin-role user. Blocks editor accounts from triggering destructive
 * execute paths independent of whatever roles securePage() allows at the page level.
 *
 * Result is cached statically to avoid repeated hasPerm() database queries from
 * isAdmin() across multiple call sites on the same page.
 */
function admin_script_exec_requested(): bool
{
    static $result = null;
    if ($result !== null) {
        return $result;
    }
    global $method, $user;
    if ($method === 'POST' && Token::check($_POST['csrf'] ?? '')) {
        if (isAdmin()) {
            $result = true;
        } else {
            logger($user->data()->id, LogCategories::LOG_CATEGORY_SECURITY,
                'Non-admin submitted admin script execute form — access denied');
            $result = false;
        }
    } else {
        $result = false;
    }
    return $result;
}

/**
 * Returns the HTML for a POST+CSRF "Start" form button.
 *
 * @param string $label   Button label text (HTML-escaped internally)
 * @param string $icon    Font Awesome icon class without "fa-" prefix, e.g. 'play'
 * @param string $btnClass Bootstrap button classes, e.g. 'btn-success btn-lg'
 */
function admin_script_start_form(
    string $label,
    string $icon = 'play',
    string $btnClass = 'btn-success btn-lg'
): string {
    return '<form method="POST"><input type="hidden" name="csrf" value="'
        . htmlspecialchars(Token::generate(), ENT_QUOTES, 'UTF-8')
        . '"><button type="submit" class="btn '
        . htmlspecialchars($btnClass, ENT_QUOTES, 'UTF-8')
        . '"><i class="fa fa-'
        . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8')
        . '"></i> '
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . '</button></form>';
}

/**
 * Returns the HTML for a "Close Window / Return to Menu" button.
 * window.close() works here because the HTML spec allows a script to close
 * a window whose session history has only one entry — i.e. this page has
 * never navigated since it was opened — independent of window.opener, which
 * is unreliable (modern browsers apply implicit noopener to target="_blank"
 * links by default). Do not add an intermediate redirect or reload before
 * this button renders without re-verifying closability, since a second
 * history entry would break the single-entry condition this relies on.
 * Direct URL access to a fix-script page (no opener at all) still closes
 * fine under this rule, but the button may not visibly do anything for a
 * page reached after multiple navigations; acceptable for this admin-only
 * internal tool.
 *
 * @param string $extraClass  Additional Bootstrap/custom classes to append
 * @return string HTML for the close button plus its wiring <script> tag
 */
function admin_script_close_button(string $extraClass = ''): string
{
    global $userspice_nonce;
    $cls = trim('btn btn-primary btn-lg ' . $extraClass);
    $safeNonce = htmlspecialchars($userspice_nonce ?? '', ENT_QUOTES, 'UTF-8');
    return '<button type="button" data-action="adminScriptClose" class="'
        . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8')
        . '"><i class="fa fa-times"></i> Close Window</button>'
        . '<script nonce="' . $safeNonce . '">'
        . '(function(){if(!window.__adminCloseWired){window.__adminCloseWired=true;'
        . 'document.addEventListener("click",function(e){'
        . 'if(!e.target.closest("[data-action=\'adminScriptClose\']"))return;'
        . 'window.close();'
        . '});}})();'
        . '</script>';
}

/**
 * Writes a timestamped progress line inside a <pre> block.
 * For simple (non-streaming) fix scripts only.
 * Streaming scripts (outputMessage() pattern) should not use this function.
 *
 * @param string $message Message to output
 * @param string $type    'info' | 'success' | 'error' | 'warning' | 'step'
 */
function logProgress(string $message, string $type = 'info'): void
{
    $icons = [
        'info'    => 'ℹ️',
        'success' => '✅',
        'error'   => '❌',
        'warning' => '⚠️',
        'step'    => '▶️',
    ];
    echo date('[H:i:s] ') . ($icons[$type] ?? '•') . ' ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "\n";
    flush();
}

/**
 * Records a fix/maintenance script's completion in fix_script_runs, used
 * to populate the "Last Run" column on the maintenance dashboard
 * (app/admin/includes/partials/maintenance-migrations.php and
 * maintenance-scripts.php). Never throws — a recording failure is logged but
 * must not interrupt or mask the calling script's actual result.
 *
 * @param string        $scriptFile   Pass __FILE__ from the calling script
 * @param int           $userId       Acting user's ID, for the failure log entry
 * @param callable|null $onFailure    Optional callback invoked with the failure
 *                                    message on error (e.g. an outputMessage()
 *                                    wrapper), for scripts that stream progress
 *                                    back to the UI. Omit when the caller has
 *                                    no such bridge (e.g. a JSON AJAX handler).
 */
function admin_script_record_completion(
    string $scriptFile,
    int $userId,
    ?callable $onFailure = null
): void {
    global $db;
    try {
        $db->insert('fix_script_runs', [
            'script_name' => basename($scriptFile),
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (\Throwable $e) {
        logger($userId, LogCategories::LOG_CATEGORY_FIX_SCRIPT_ERROR,
            'Could not record fix_script_runs completion for ' . basename($scriptFile) . ': ' . $e->getMessage());
        if ($onFailure !== null) {
            try {
                $onFailure('⚠️ Could not record script completion in fix_script_runs table');
            } catch (\Throwable $callbackError) {
                // Swallowed deliberately: this function's contract is "never throws", so a
                // buggy caller-supplied callback must not escape and crash the calling script.
                logger($userId, LogCategories::LOG_CATEGORY_FIX_SCRIPT_ERROR,
                    'admin_script_record_completion onFailure callback itself threw for '
                    . basename($scriptFile) . ': ' . $callbackError->getMessage());
            }
        }
    }
}
