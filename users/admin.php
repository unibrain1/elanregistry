<?php
/*
UserSpice 5
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
ini_set('max_execution_time', 1356);
ini_set('memory_limit','1024M');
?>
<?php
require_once '../users/init.php';
include $abs_us_root.$us_url_root."users/includes/dashboard_language.php";
$db = DB::getInstance();
if (!securePage($_SERVER['PHP_SELF'])){die();}
$settings = $db->query("SELECT * FROM settings")->first();
?>
<?php require_once $abs_us_root.$us_url_root.'users/includes/user_spice_ver.php'; ?>
<?php
$page=currentFile();
$titleQ = $db->query('SELECT title FROM pages WHERE page = ?', array($page));
if ($titleQ->count() > 0) {
    $pageTitle = $titleQ->first()->title;
}
else $pageTitle = '';
?>
<title><?= (($pageTitle != '') ? $pageTitle : ''); ?> <?=$settings->site_name?></title>
<?php $view = Input::get('view');?>
<?php require_once $abs_us_root.$us_url_root.'users/views/_admin_menu.php';
if($view == '' || $view == 'dashboard'){
if((time() - strtotime($settings->announce)) > 10800){
$db->update('settings',1,['announce'=>date("Y-m-d H:i:s")]);
require_once $abs_us_root.$us_url_root.'users/views/_admin_announcements.php';
}
}
?>
<div id="right-panel" class="right-panel">

  <div id="messages" class="sufee-alert alert with-close alert-primary alert-dismissible fade show d-none">
    <span id="message"></span>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>

  <?php require_once $abs_us_root.$us_url_root.'users/views/_admin_header.php'; ?>
  <?php
  function usView($file){
    global $abs_us_root;
    global $us_url_root;
    if(checkAccess('page',$file)){
      if(file_exists($abs_us_root.$us_url_root.'usersc/includes/admin/'.$file)){
        $path = $abs_us_root.$us_url_root.'usersc/includes/admin/'.$file;
      }elseif(file_exists($abs_us_root.$us_url_root.'users/views/'.$file)){
        $path = $abs_us_root.$us_url_root.'users/views/'.$file;
      }else{
        $path = $abs_us_root.$us_url_root.'users/views/_admin_dashboard.php';
      }
      return $path;
    }else{
      $path = $abs_us_root.$us_url_root.'users/views/_admin_dashboard.php';
      return $path;
    }
  }

  function checkAccess ($key,$value) {
    global $db, $user, $master_account;
    //Check if they belong to the master account array or have the Administrator (default 2) Perm
    if (in_array($user->data()->id, $master_account) || hasPerm([2],$user->data()->id)) {
      return true;
    } else {
      //They're not, now we're gonna check if the view exists in us_management and if they have perms
      $checkQ = $db->query("SELECT * FROM us_management WHERE $key = ?",[$value]);
      if (!$db->error()) {
        $checkC = $checkQ->count();
        if ($checkC < 1) {
          //The page isn't in the table, so we're gonna reject their ability to go
          return false;
        } else {
          //The page is in there, so now we're gonna check if they have permission
          $check = $checkQ->first();
          if (hasPerm([$check->access],$user->data()->id)) {
            //They have permissions listed in us_management, let them through
            return true;
          } else {
            //They don't have permissions, reject them
            return false;
          }
        }
      } else {
        //It failed to retrieve anything from us_management, so we log the error and send them away
        logger($user->data()->id,"checkAccess","Failed to check access for ".$file.", Error: ".$db->errorString());
        return false;
      }
    }
  }

  if(file_exists($abs_us_root.$us_url_root.'usersc/includes/admin_override.php')){
    include($abs_us_root.$us_url_root.'usersc/includes/admin_override.php');
  }

  switch ($view) {
    case "access":
    $path = usView('_dashboard_access.php');
    include($path);
    break;
    case "backup":
    $path = usView('_admin_tools_backup.php');
    include($path);
    break;
    case "bugs":
    $path = usView('_bug_report.php');
    include($path);
    break;
    case "cron":
    $path = usView('_admin_cron.php');
    include($path);
    break;
    case "custom":
    $path = usView('_admin_settings_custom.php');
    include($path);
    break;
    case "email":
    $path = usView('_admin_email.php');
    include($path);
    break;
    case "email_test":
    $path = usView('_admin_email_test.php');
    include($path);
    break;
    case "general":
    $path = usView('_admin_settings_general.php');
    include($path);
    break;
    case "ip":
    $path = usView('_admin_manage_ip.php');
    include($path);
    break;
    case "legacy":
    if(file_exists($abs_us_root.$us_url_root.'usersc/includes/admin_panels.php')){
      include($abs_us_root.$us_url_root.'usersc/includes/admin_panels.php');
    }else{
      Redirect::to('admin.php?view=stats&err=Legacy+files+not+found');
    }
    break;
    case "logs":
    $path = usView('_admin_logs.php');
    include($path);
    break;
    case "logsman":
    $path = usView('_admin_logs_manager.php');
    include($path);
    break;
    case "nav":
    $path = usView('_admin_nav.php');
    include($path);
    break;
    case "nav_item":
    $path = usView('_admin_nav_item.php');
    include($path);
    break;
    case "page":
    $path = usView('_admin_page.php');
    include($path);
    break;
    case "pages":
    $path = usView('_admin_pages.php');
    include($path);
    break;
    case "permission":
    $path = usView('_admin_permission.php');
    include($path);
    break;
    case "permissions":
    $path = usView('_admin_permissions.php');
    include($path);
    break;
    case "pin":
    $path = usView('_admin_pin.php');
    include($path);
    break;
    case "plugins":
    $path = usView('_admin_plugins.php');
    include($path);
    break;
    case "plugins_config":
    $plugin = Input::get('plugin');
    if(file_exists($abs_us_root.$us_url_root.'usersc/plugins/'.$plugin.'/configure.php')){
      if(file_exists($abs_us_root.$us_url_root.'users/views/_configure_plugin_header.php')){
        include $abs_us_root.$us_url_root.'users/views/_configure_plugin_header.php';
      }
      include $abs_us_root.$us_url_root.'usersc/plugins/'.$plugin.'/configure.php';
      echo "</div>";
    }
    break;
    case "reg":
    $path = usView('_admin_settings_register.php');
    include($path);
    break;
    case "security_logs":
    $path = usView('_admin_security_logs.php');
    include($path);
    break;
    case "sessions":
    $path = usView('_admin_sessions.php');
    include($path);
    break;
    case "spice":
    $path = usView('_spice_shaker.php');
    include($path);
    break;
    case "stats":
    $path = usView('_admin_statistics.php');
    include($path);
    break;
    case "templates":
    $path = usView('_admin_templates.php');
    include($path);
    break;
    case "updates":
    $path = usView('_admin_tools_check_updates.php');
    include($path);
    break;
    case "user":
    $path = usView('_admin_user.php');
    include($path);
    break;
    case "users":
    $path = usView('_admin_users.php');
    include($path);
    break;
    case "verify":
      $path = usView('_admin_verify.php');
      include($path);
      break;
      default:
      if($view == ''){
        include($abs_us_root.$us_url_root.'users/views/_admin_dashboard.php');
      }else{
        $path = usView($view.".php");
        include($path);
      }
    }
    ?>
    <p align="center">
      <font color='black'><br>&copy;<?=date("Y ")?><?=$settings->copyright; ?></font>
    </p>


  </div> <!-- .content -->
</div><!-- /#right-panel -->

<!-- Right Panel -->
<script type="text/javascript">
$(document).ready(function() {
$('[data-toggle="popover"]').popover();

  function messages(data) {
    console.log("messages found");
    $('#messages').removeClass();
    $('#message').text("");
    $('#messages').show();
    if(data.success == "true"){
      $('#messages').addClass("sufee-alert alert with-close alert-success alert-dismissible fade show");
    }else{
      $('#messages').addClass("sufee-alert alert with-close alert-success alert-dismissible fade show");
    }
    $('#message').text(data.msg);
    $('#messages').delay(3000).fadeOut('slow');

  }

  $( ".toggle" ).change(function() { //use event delegation
    var value = $(this).prop("checked");
    $(this).prop("checked",value);

    var field = $(this).attr("id"); //the id in the input tells which field to update
    var desc = $(this).attr("data-desc"); //For messages
    var formData = {
      'value' 				: value,
      'field'					: field,
      'desc'					: desc,
      'type'          : 'toggle',
    };

    $.ajax({
      type 		: 'POST',
      url 		: 'parsers/admin_settings.php',
      data 		: formData,
      dataType 	: 'json',
    })

    .done(function(data) {
      messages(data);
    })
  });

  $("#force_user_pr").click(function(data) {
    console.log("clicked");
    var formData = {
      'type'								: 'resetPW'
    };
    $.ajax({
      type 		: 'POST',
      url 		: 'parsers/admin_settings.php',
      data 		: formData,
      dataType 	: 'json',
      encode 		: true
    })
    .done(function(data) {
      messages(data);
    })
  });

  $( ".ajxnum" ).change(function() { //use event delegation
    var value = $(this).val();
    // console.log(value);

    var field = $(this).attr("id"); //the id in the input tells which field to update
    var desc = $(this).attr("data-desc"); //For messages
    var formData = {
      'value' 				: value,
      'field'					: field,
      'desc'					: desc,
      'type'          : 'num',
    };

    $.ajax({
      type 		: 'POST',
      url 		: 'parsers/admin_settings.php',
      data 		: formData,
      dataType 	: 'json',
    })

    .done(function(data) {
      messages(data);
    })
  });

  $( ".ajxtxt" ).change(function() { //use event delegation
    var value = $(this).val();
    console.log(value);

    var field = $(this).attr("id"); //the id in the input tells which field to update
    var desc = $(this).attr("data-desc"); //For messages
    var formData = {
      'value' 				: value,
      'field'					: field,
      'desc'					: desc,
      'type'          : 'txt',
    };

    $.ajax({
      type 		: 'POST',
      url 		: 'parsers/admin_settings.php',
      data 		: formData,
      dataType 	: 'json',
    })

    .done(function(data) {
      messages(data);
    })
  });

  // Toggle menu
  $('#menuToggle').on('click', function() {
    $('body').toggleClass('open');
    $(".dropdown-toggle").dropdown('toggle');

  });

  $('.search-trigger').on('click', function() {
    $('.search-trigger').parent('.header-left').addClass('open');
  });

  $('.search-close').on('click', function() {
    $('.search-trigger').parent('.header-left').removeClass('open');
  });
});
</script>
<?php foreach($usplugins as $k=>$v){
  if($v == 1){
    if(file_exists($abs_us_root.$us_url_root."usersc/plugins/".$k."/footer.php")){
      include($abs_us_root.$us_url_root."usersc/plugins/".$k."/footer.php");
    }
  }
}?>

</body>
</html>
