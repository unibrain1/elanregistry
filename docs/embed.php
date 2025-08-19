<?php

/**
 * Document Embed Page
 *
 * Embeds a selected document (PDF) in an iframe for viewing.
 * Requires authentication and uses Bootstrap for layout.
 */
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!empty($_GET['doc'])) {
    $document = $_GET['doc'];
    $path_parts = pathinfo($document);
}

?>
<div id='page-wrapper'>
    <!-- Page Content -->
    <div class='container'>
        <div class='card card-default'>
            <div class='card-header'>
                <h1> <?= $path_parts['filename']  ?></h1><a href="javascript:history.go(-1)">Back ...</a>
            </div>
            <div class='card-body'>
                <iframe style='width:100%; height:100vw;' src='<?= $us_url_root ?>docs/assets/<?= $document ?>' title='<?= $document ?>' allowfullscreen></iframe>
            </div>
        </div>
    </div> <!-- /.container -->
</div><!-- .page-wrapper -->
<!-- footers -->
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; //custom template footer
?>
