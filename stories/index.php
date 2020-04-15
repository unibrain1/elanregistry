<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<?php
require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';
?>

<?php if (!securePage($_SERVER['PHP_SELF'])) {
    die();
} ?>

<?php
//PHP Goes Here!
?>
<div id="page-wrapper">
    <div class="container">
        <div class="row">
            <div class="col-sm-12">






<h2>User submitted car histories and personal stories</h2>

<ul>

<li><a href="SGO_2F/index.php">The story of SGO 2F:  50/0164</a></li>
<li><a href="brian_walton/index.php">Elan Experimental Rally Car:  36/6086</a></li>
</ul>









                    <!-- End of main content section -->
            </div> <!-- /.col -->
        </div> <!-- /.row -->
    </div> <!-- /.container -->
</div> <!-- /.wrapper -->


<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer ?>

