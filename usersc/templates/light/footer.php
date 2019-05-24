<?php
require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/container_close.php'; //custom template container    

require_once $abs_us_root . $us_url_root . 'users/includes/page_footer.php'; 

require_once($abs_us_root.$us_url_root.'users/includes/html_footer.php');
?>
<script>
  var $hamburger = $(".hamburger");
  $hamburger.on("click", function(e) {
    $hamburger.toggleClass("is-active");
    // Do something else, like open/close menu
  });
</script>

