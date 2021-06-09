<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
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
                    <div class="card-header">
                        <h2>User submitted car histories and personal stories</h2>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center"><a href="SGO_2F/index.php">The story of SGO 2F: 50/0164</a></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center"><a href="brian_walton/index.php">Elan Experimental Rally Car: 36/6086</a></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center"><a href="type26registry/index.html">Archive of www.type26registry.com</a> - This is probably incomplete.</li>
                        </ul>
                    </div>
                </div>
            </div> <!-- /.col -->
            <div class="col">
                <div class="card bg-secondary">
                    <div class="card-header">
                        <h2>Elan Manuals</h2>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href='<?= $us_url_root ?>app/assets/docs/embed.php?doc=Elan_26_36_Workshop_Manual.pdf'>
                                    <img src="<?= $us_url_root ?>app/assets/docs/Elan_26_36_Workshop_Manual.png" height="225" alt="Elan 26/36 Workshop Manual - 1966" /><br>
                                    Elan 26/36 Workshop Manual - 1966</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href='<?= $us_url_root ?>app/assets/docs/embed.php?doc=Elan_S1_S2_Coupe_Masterpartslist.pdf'>
                                    <img src="<?= $us_url_root ?>app/assets/docs/Elan_S1_S2_Coupe_Masterpartslist.png" height="225" alt="Elan S1/S2/Coupe Master parts list - 1966" /><br>
                                    Elan S1/S2/Coupe Master parts list - 1966</a>
                            </li>

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