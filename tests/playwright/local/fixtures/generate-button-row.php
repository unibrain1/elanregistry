<?php
declare(strict_types=1);

/**
 * Standalone fixture script for the button-row responsive Playwright test.
 *
 * Prints the raw HTML fragment returned by EmailTemplate::createButtonRow()
 * to stdout so tests/playwright/local/email-button-row-responsive.spec.js can
 * load it into a real browser via page.setContent() and assert on the actual
 * rendered layout (not just the HTML string).
 *
 * Deliberately does NOT boot the full application (z_us_root.php / UserSpice).
 * createButtonRow() never reads $this->baseUrl or $this->logoUrl — only the
 * constructor does (via getBaseUrl(), to build $logoUrl) — so a minimal
 * getBaseUrl() stub is sufficient here and avoids pulling in DB::getInstance()
 * and LogCategories from the real usersc/includes/custom_functions.php
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

echo $template->createButtonRow([
    ['label' => 'Verify', 'url' => 'https://example.com/verify'],
    ['label' => 'Report Sold', 'url' => 'https://example.com/sold'],
]);
