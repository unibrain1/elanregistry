<?php

/**
 * privacy.php
 * Displays the privacy policy for the Lotus Elan Registry project.
 *
 * Loads PRIVACY.md, converts markdown to HTML, and renders it using the site template.
 *
 * @author Elan Registry Admin
 * @copyright 2025
 */
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

$mdFile = __DIR__ . '/../PRIVACY.md';
$policy = '';
if (file_exists($mdFile)) {
    $policy = file_get_contents($mdFile);
    // Convert markdown to HTML (simple conversion)
    $policy = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $policy);
    $policy = preg_replace('/^## (.*)$/m', '<h2>$1</h2>', $policy);
    $policy = preg_replace('/^\-\-? (.*)$/m', '<li>$1</li>', $policy);
    $policy = preg_replace('/^\- (.*)$/m', '<li>$1</li>', $policy);
    $policy = str_replace("\n\n", '<br><br>', $policy);
    $policy = str_replace("\n", '<br>', $policy);
    $policy = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $policy);
}
?>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="well">
            <div class="row">
                <div class="col-12">
                    <div class="card card-default">
                        <div class="card-header">
                            <h2><strong>Privacy Policy</strong></h2>
                        </div>
                        <div class="card-body">
                            <div style="max-width:800px;margin:auto;">
                                <?php echo $policy; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php

require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php';
?>
[newline]