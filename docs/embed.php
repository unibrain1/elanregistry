<?php

require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';


if (!empty($_GET['doc'])) {
    $document = $_GET['doc'];
}

?>
<div id='page-wrapper'>
    <!-- Page Content -->
    <div class='container'>
        <div class='card card-default'>
            <div class='card-header'>
                <h1> <?= $document ?> </h1>
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