<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';


if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// Get list of files in the directory

$directory    = $abs_us_root . $us_url_root . 'docs/assets/';
$files = preg_grep('~\.(pdf)$~', scandir($directory));

?>
<div id="page-wrapper">
    <div class="container">
        <div class="well">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card card-default">
                        <div class="card-header">
                            <h2><strong>Documents</strong></h2>
                        </div>
                        <div class="card-body">

                            <table class="table table-striped table-bordered table-sm" aria-describedby="legend">
                                <tr>
                                    <th scope=column>Document</th>
                                    <th scope=column>Description </th>
                                </tr>

                                <?php
                                foreach ($files as $file) {
                                    $path_parts = pathinfo($file);
                                    $img = $path_parts['filename'] . '.png';
                                    $description = $path_parts['filename'] . '.txt';

                                ?>

                                    <tr>
                                        <td>
                                            <?php

                                            if (file_exists($directory . $img)) {
                                            ?>
                                                <a href='<?= $us_url_root ?>docs/assets/<?= $path_parts['basename'] ?>' target='_blank'>
                                                    <img src='<?= $us_url_root ?>docs/assets/<?= $img ?>' height='225' alt='<?= $file ?>' /><br>
                                                </a>
                                            <?php
                                            } else {
                                            ?>
                                                <a href='<?= $us_url_root ?>docs/assets/<?= $path_parts['basename'] ?>' target='_blank'><?= $path_parts['filename'] ?></a>
                                            <?php
                                            }
                                            ?>
                                        <td>
                                            <?php
                                            if (file_exists($directory . $description)) {
                                                include_once $directory . $description;
                                            }
                                            ?>
                                        </td>
                                    </tr>




                                <?php
                                }
                                ?>




                            </table>
                        </div> <!-- card-body -->
                    </div> <!-- card -->
                </div> <!-- col -->

            </div> <!-- row -->

        </div> <!-- well -->
    </div><!-- Container -->
</div><!-- page -->


<!-- Javascript -->



<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer
?>