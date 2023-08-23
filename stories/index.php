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
                        <h2>Owner submitted histories and stories</h2>
                    </div>
                    <div class="card-body">

                        <table class='table table-striped table-bordered table-sm'>
                            <tr class='table-success'>
                                <th>Article</td>
                                <th>Comments</td>
                            </tr>
                            <tr>
                                <td> <a href="SGO_2F/index.php">The story of SGO 2F: 50/0164</a></td>
                                <td></td>
                            </tr>

                            <tr>
                                <td><a href="brian_walton/index.php">Elan Experimental Rally Car: 36/6086</a></td>
                                <td></td>
                            </tr>

                            <tr>
                                <td> <a href='<?= $us_url_root ?>app/assets/docs/embed.php?doc=Mag _issue_50_p12-15_Barry-Shapecraft.pdf'>Shapecraft Elan - 26/4992</a>
                                </td>
                                <td>From <a href="http://www.historiclotusclub.uk/the-magazine/no-50-spring-2022">Historic Lotus Racing magazine, No. 50, Spring 2022</a></td>
                            </tr>

                            <tr>
                                <td> <a href="type26registry/index.html">Archive of www.type26registry.com</a> </td>
                                <td>This is an incomplete archive.</td>
                            </tr>

                        </table>
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