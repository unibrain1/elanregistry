<?php
require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';
?>

<?php if (!securePage($_SERVER['PHP_SELF'])) {
    die();
} ?>

<div id="page-wrapper">
    <div class="container">
    <br>
        <div class="row">
            <div class="col">
                <div class="card bg-secondary">
				<div class="card-header"><h2>User submitted car histories and personal stories</h2></div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center"><a href="SGO_2F/index.php">The story of SGO 2F:  50/0164</a></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center"><a href="brian_walton/index.php">Elan Experimental Rally Car:  36/6086</a></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center"><a href="type26registry/index.php">Archive of www.type26registry.com</a> - This is probably incomplete.</li>
                        </ul>
                    </div>
                </div>
            </div> <!-- /.col -->
        </div> <!-- /.row -->
    </div> <!-- /.container -->
</div> <!-- /.wrapper -->

<!-- footers -->
<?php
require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer
?>