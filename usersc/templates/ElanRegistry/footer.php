<?php
require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/container_close.php'; //custom template container
require_once $abs_us_root . $us_url_root . 'users/includes/page_footer.php';
?>


<script>
  var $hamburger = $(".hamburger");
  $hamburger.on("click", function(e) {
    $hamburger.toggleClass("is-active");
    // Do something else, like open/close menu
  });
</script>

<!-- Global site tag (gtag.js) - Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=UA-7230761-6"></script>
<script>
  window.dataLayer = window.dataLayer || [];

  function gtag() {
    dataLayer.push(arguments);
  }
  gtag('js', new Date());

  gtag('config', 'UA-7230761-6');
</script>

<div class="container">
  <div class="row">
    <div class="col-12 text-center">
      <footer><br>&copy;
        <?php echo date("Y"); ?>
        <?= $settings->copyright; ?></footer>
      <br>
    </div>
  </div>
</div>
<?php require_once($abs_us_root . $us_url_root . 'users/includes/html_footer.php'); ?>