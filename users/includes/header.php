<?php
ob_start();
header('X-Frame-Options: SAMEORIGIN');
/*
UserSpice 4
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
//check for a custom page
$currentPage = currentPage();


if(file_exists($abs_us_root.$us_url_root.'usersc/'.$currentPage)){
	if(currentFolder() == 'users'){
		$url = $us_url_root.'usersc/'.$currentPage;
		if(isset($_GET)){
			$url .= '?'; //add initial ?
			foreach ($_GET as $key=>$value){
				$url .= '&'.$key.'='.$value;
			}
		}
		Redirect::to($url);
	}
}

$db = DB::getInstance();
$settingsQ = $db->query("Select * FROM settings");
$settings = $settingsQ->first();

//dealing with logged in users
if($user->isLoggedIn() && !checkMenu(2,$user->data()->id)){
	if (($settings->site_offline==1) && (!in_array($user->data()->id, $master_account)) && ($currentPage != 'login.php') && ($currentPage != 'maintenance.php')){
		//:: force logout then redirect to maint.page
		logger($user->data()->id,"Offline","Landed on Maintenance Page."); //Lggger
		$user->logout();
		Redirect::to($us_url_root.'users/maintenance.php');
	}
}

//deal with guests
if(!$user->isLoggedIn()){
	if (($settings->site_offline==1) && ($currentPage != 'login.php') && ($currentPage != 'maintenance.php')){
		//:: redirect to maint.page
		logger(1,"Offline","Guest Landed on Maintenance Page."); //Logger
		Redirect::to($us_url_root.'users/maintenance.php');
	}
}

//notifiy master_account that the site is offline
if($user->isLoggedIn()){
	if (($settings->site_offline==1) && (in_array($user->data()->id, $master_account)) && ($currentPage != 'login.php') && ($currentPage != 'maintenance.php')){
		err("<br>Maintenance Mode Active");
	}
}

if($settings->glogin==1 && !$user->isLoggedIn()){
	require_once $abs_us_root.$us_url_root.'users/includes/google_oauth.php';
}

if ($settings->force_ssl==1){

	if (!isset($_SERVER['HTTPS']) || !$_SERVER['HTTPS']) {
		// if request is not secure, redirect to secure url
		$url = 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		Redirect::to($url);
		exit;
	}
}
require_once $abs_us_root.$us_url_root.'usersc/includes/security_headers.php';

//if track_guest enabled AND there is a user logged in
if($settings->track_guest == 1 && $user->isLoggedIn()){
	if ($user->isLoggedIn()){
		$user_id=$user->data()->id;
	}else{
		$user_id=0;
	}
	new_user_online($user_id);

}

if($user->isLoggedIn() && $currentPage != 'user_settings.php' && $user->data()->force_pr == 1 && !isset($_SESSION['twofa']) && $_SESSION['twofa']!=1 && $currentPage !== 'twofa.php') Redirect::to($us_url_root.'users/user_settings.php?err=You+must+change+your+password!');

$page=currentFile();
$titleQ = $db->query('SELECT title FROM pages WHERE page = ?', array($page));
if ($titleQ->count() > 0) {
    $pageTitle = $titleQ->first()->title;
}
else $pageTitle = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
	<meta name="description" content="">
	<meta name="author" content="">

	<?php
	if(file_exists($abs_us_root.$us_url_root.'usersc/includes/head_tags.php')){
		require_once $abs_us_root.$us_url_root.'usersc/includes/head_tags.php';
	}

	if(($settings->messaging == 1) && ($user->isLoggedIn())){
		$msgQ = $db->query("SELECT id FROM messages WHERE msg_to = ? AND msg_read = 0 AND deleted = 0",array($user->data()->id));
		$msgC = $msgQ->count();
		if($msgC == 1){
			$grammar = 'Message';
		}else{
			$grammar = 'Messages';
		}
	}
	?>

	<title><?= (($pageTitle != '') ? $pageTitle : ''); ?> <?=$settings->site_name?></title>

	<!-- Bootstrap Core CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">

	<!-- AKA Primary CSS -->
	<link href="<?=$us_url_root?><?=str_replace('../','',$settings->us_css1);?>" rel="stylesheet">

	<!-- Template CSS -->
	<!-- AKA Secondary CSS -->
	<link href="<?=$us_url_root?><?=str_replace('../','',$settings->us_css2);?>" rel="stylesheet">

	<!-- Table Sorting and Such -->
	<link href="<?=$us_url_root?>users/css/datatables.css" rel="stylesheet">

	<!-- Your Custom CSS Goes Here and will override everything above this!-->
	<link href="<?=$us_url_root?><?=str_replace('../','',$settings->us_css3);?>" rel="stylesheet">

	<!-- Custom Fonts/Animation/Styling-->
	<link rel="stylesheet" href="<?=$us_url_root?>users/fonts/css/font-awesome.min.css">

	<script src="https://code.jquery.com/jquery-3.1.1.min.js" integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8=" crossorigin="anonymous"></script>
	<!-- jQuery Fallback -->
	<script type="text/javascript">
	if (typeof jQuery == 'undefined') {
		document.write(unescape("%3Cscript src='<?=$us_url_root?>users/js/jquery.js' type='text/javascript'%3E%3C/script%3E"));
	}
	</script>

	<?php require_once $abs_us_root.$us_url_root.'usersc/includes/bootstrap_corrections.php'; ?>

<script src="<?=$us_url_root?>users/js/tomfoolery.js"></script>
<?php if(!isset($_SESSION['fingerprint'])) {?>
<script>
new Fingerprint2().get(function(result, components) {
  var fingerprint = result;
		$.ajax({
						type: "POST",
						url: '<?=$us_url_root?>users/parsers/fingerprint_post.php',
						data: ({fingerprint:fingerprint}),
		});
});
</script>
<?php }
if($settings->session_manager==1) storeUser(); ?>
</head>

<body class="nav-md">
	<?php

	if ($user->isLoggedIn() && $settings->admin_verify==1) { (!reAuth()); }
	if ($user->isLoggedIn() && isset($_SESSION['twofa']) && $_SESSION['twofa']==1 && $currentPage !== 'twofa.php') Redirect::to($us_url_root.'users/twofa.php');
	require_once $abs_us_root.$us_url_root.'usersc/includes/timepicker.php';
	?>

	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.1/bootstrap-editable/css/bootstrap-editable.css" integrity="sha256-YsJ7Lkc/YB0+ssBKz0c0GTx0RI+BnXcKH5SpnttERaY=" crossorigin="anonymous" />
	<style>
	.editableform-loading {
	    background: url('https://cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.1/bootstrap-editable/img/loading.gif') center center no-repeat !important;
	}
	.editable-clear-x {
	   background: url('https://cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.1/bootstrap-editable/img/clear.png') center center no-repeat !important;
	}
	</style>

<?php if(isset($settings->oauth_tos_accepted) && $user->isLoggedIn() && !$user->data()->oauth_tos_accepted && $currentPage != 'oauth_success.php') Redirect::to($us_url_root.'users/oauth_success.php?action=tos'); ?>
