<?php
require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/container_close.php'; //custom template container
require_once $abs_us_root . $us_url_root . 'users/includes/page_footer.php';
?>
<div class="<?= $settings->container_open_class ?>">
  <div class="row">
    <div class="col-12 text-center">
      <footer>
        <br>
        <div class="mb-2">
          <a href="<?= $us_url_root ?>app/privacy.php" class="text-muted me-3">Privacy Policy</a>
        </div>
        &copy; <?php echo date("Y"); ?> <?= $settings->copyright; ?>
      </footer>
      <br>
    </div>
  </div>
</div>

<?php
require_once($abs_us_root . $us_url_root . 'users/includes/html_footer.php');

// // jQuery
// echo html_entity_decode($settings->elan_jquery_cdn);

// // Bootstrap Core CSS
// echo html_entity_decode($settings->elan_bootstrap_js_cdn);

// // Popper
// echo html_entity_decode($settings->elan_popper_cdn);

// // Custom Fonts/Animation/Styling from FontAwsome 
// echo html_entity_decode($settings->elan_fontawesome_cdn);
?>

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