<?php
//if you would like your dashboard to use a different template, specify that template here.
//it is strongly recommended that you ONLY use a bootstrap 5 or later menu that supports the advanced UserSpice menu system.

$template_override = "bs5";
if(!file_exists($abs_us_root . $us_url_root . 'usersc/templates/'.$template_override.'/assets/v2template.php') &&
  !file_exists($abs_us_root . $us_url_root . 'usersc/templates/'.$template_override.'/assets/v3template.php')
){
  echo "<h5 style='color:red;'>You are not using a proper template for the dashboard and this may cause problems.
  If you know what you're doing, please add a file called v2template.php to the assets folder of your template to make this error go away<h5>";
}

//by default we are using menu id 2 for your admin menu, but you can put whatever you want in its place
$menu_override = 2;

//use sidebar menu instead of top menu
$dashboard_sidebar_menu = false;

//include the footer from your template on the userspice dashboard
$use_template_footer = false; 

//hide the top menu on the dashboard
$hide_top_navigation = false;

// The sidebar menu disappears on small screen sizes
// If you show the sidebar and hide the top menu, a fallback menu will appear on small screens
// giving you links to your home page and dashboard for convenience. 