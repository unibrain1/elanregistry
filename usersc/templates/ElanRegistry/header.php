<?php require_once($abs_us_root.$us_url_root.'users/includes/template/header1_must_include.php'); ?>


<!-- Custom Fonts/Animation/Styling from FontAwsome -->
<!-- <link rel="stylesheet" href="<?=$us_url_root?>users/fonts/css/font-awesome.min.css"> -->
<script src="https://kit.fontawesome.com/2d8f489b15.js" crossorigin="anonymous"></script>

<!-- Bootstrap Core CSS -->
  <!-- https://bootswatch.com/simplex/ -->
  <link rel="stylesheet" href="<?=$us_url_root?>usersc/templates/<?=$settings->template?>/assets/css/bootstrap.min.css">

<!-- JS, Popper.js, and jQuery -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"   integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0="   crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" crossorigin="anonymous"></script>

<!-- Table Sorting and Such -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.22/css/jquery.dataTables.min.css"/>
<script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>

<!-- https://jonsuh.com/hamburgers -->
<link href="<?=$us_url_root?>usersc/templates/<?=$settings->template?>/assets/css/hamburgers.min.css" rel="stylesheet">
<?php
//optional
if (file_exists($abs_us_root.$us_url_root.'usersc/templates/'.$settings->template.'.css')) {?> <link href="<?=$us_url_root?>usersc/templates/<?=$settings->template?>.css" rel="stylesheet"> <?php } ?>
<?php
require_once($abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/assets/functions/style.php');
?>
</head>
<?php require_once($abs_us_root.$us_url_root.'users/includes/template/header3_must_include.php'); ?>
