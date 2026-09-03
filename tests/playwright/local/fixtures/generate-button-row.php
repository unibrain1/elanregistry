<?php
declare(strict_types=1);

/**
 * Standalone fixture script for the button-row responsive Playwright test.
 *
 * Prints the full HTML document returned by EmailTemplate::render() — not just
 * createButtonRow()'s fragment — to stdout so
 * tests/playwright/local/email-button-row-responsive.spec.js can load it into
 * a real browser via page.setContent() and assert on the actual rendered
 * layout (not just the HTML string).
 *
 * render() is required (rather than calling createButtonRow() alone) because
 * the .btn-row-cell responsive rule lives in getBaseTemplate()'s head-level
 * <style> block, not in createButtonRow()'s own returned fragment — some mail
 * clients strip <style> tags found outside <head>, so the rule was moved
 * there deliberately (see createButtonRow()'s docblock). A fixture that only
 * rendered the fragment standalone would test a responsive rule that isn't
 * present in the fragment at all.
 *
 * Deliberately does NOT boot the full application (z_us_root.php / UserSpice).
 * A minimal getBaseUrl() stub avoids pulling in DB::getInstance() and
 * LogCategories from the real usersc/includes/custom_functions.php
 * implementation, which requires a live database connection.
 */

if (!function_exists('getBaseUrl')) {
    function getBaseUrl(): string
    {
        return 'https://test.elanregistry.org';
    }
}

require __DIR__ . '/../../../../usersc/classes/EmailTemplate.php';

use ElanRegistry\EmailTemplate;

$template = new EmailTemplate();

$buttonRow = $template->createButtonRow([
    ['label' => 'Verify', 'url' => 'https://example.com/verify'],
    ['label' => 'Report Sold', 'url' => 'https://example.com/sold'],
]);

echo $template->render('Test Subject', 'Test Subtitle', $buttonRow);
