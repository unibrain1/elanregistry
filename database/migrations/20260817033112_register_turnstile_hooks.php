<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Registers the Cloudflare Turnstile hooker hooks (issue #1679).
 *
 * Replaces the manual `database/seed-turnstile-hooks.sql` step (now deleted)
 * with a proper migration. Mirrors the idempotency pattern used by
 * `RegisterLoginLoggerHooks` (SELECT-before-INSERT via fetchRow()/execute()),
 * but uses explicit up()/down() instead of change() since raw INSERT/DELETE
 * statements cannot be auto-reversed by Phinx.
 */
final class RegisterTurnstileHooks extends AbstractMigration
{
    // Each entry: [page, position, hook-filename]
    private const HOOKS = [
        ['login.php',            'form', 'hooks/login_form_turnstile.php'],
        ['login.php',            'post', 'hooks/post_turnstile.php'],
        ['join.php',             'form', 'hooks/join_form_turnstile.php'],
        ['joinAttempt',          'body', 'hooks/post_turnstile.php'],
        ['forgot_password.php',  'post', 'hooks/post_turnstile.php'],
    ];

    public function up(): void
    {
        // All interpolated values come from self::HOOKS — a private const of
        // hardcoded strings. Phinx 0.16 uses PDO::query() (not prepare()) so
        // bind-parameter syntax is unavailable here; interpolation is safe.
        $adapter = $this->getAdapter();
        $adapter->beginTransaction();
        foreach (self::HOOKS as [$page, $position, $hook]) {
            $exists = $this->fetchRow(
                "SELECT id FROM us_plugin_hooks
                 WHERE page = '{$page}' AND folder = 'hooker' AND hook = '{$hook}'"
            );
            if (!$exists) {
                $this->execute(
                    "INSERT INTO us_plugin_hooks (page, folder, position, hook, disabled)
                     VALUES ('{$page}', 'hooker', '{$position}', '{$hook}', 0)"
                );
            }
        }
        $adapter->commitTransaction();
    }

    public function down(): void
    {
        $adapter = $this->getAdapter();
        $adapter->beginTransaction();
        foreach (self::HOOKS as [$page, $position, $hook]) {
            $this->execute(
                "DELETE FROM us_plugin_hooks
                 WHERE page = '{$page}' AND folder = 'hooker' AND hook = '{$hook}'"
            );
        }
        $adapter->commitTransaction();
    }
}
