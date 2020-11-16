<?php require_once($abs_us_root . $us_url_root . 'users/includes/template/header1_must_include.php'); ?>

<!-- JS, Popper.js, and jQuery -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>

<!-- Bootstrap Core CSS -->
<!-- https://bootswatch.com/simplex/ -->
<!-- CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css" integrity="sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2" crossorigin="anonymous">

<!-- jQuery and JS bundle w/ Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx" crossorigin="anonymous"></script>
<link rel="stylesheet" href="<?= $us_url_root ?>usersc/templates/<?= $settings->template ?>/assets/css/bootstrap.min.css">

<!-- Custom Fonts/Animation/Styling from FontAwsome -->
<script src="https://kit.fontawesome.com/2d8f489b15.js" crossorigin="anonymous"></script>

<!-- https://jonsuh.com/hamburgers -->
<link href="<?= $us_url_root ?>usersc/templates/<?= $settings->template ?>/assets/css/hamburgers.min.css" rel="stylesheet">
<?php
//optional
if (file_exists($abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '.css')) { ?>
  <link href="<?= $us_url_root ?>usersc/templates/<?= $settings->template ?>.css" rel="stylesheet"> <?php

                                                                                                  } ?>
<?php
require_once($abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/assets/functions/style.php');
?>
</head>
<?php require_once($abs_us_root . $us_url_root . 'users/includes/template/header3_must_include.php'); ?>