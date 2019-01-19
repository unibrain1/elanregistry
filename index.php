<?php

require_once 'users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/header.php';
require_once $abs_us_root.$us_url_root.'users/includes/navigation.php';
if(isset($user) && $user->isLoggedIn()){
}
?>

<div id="page-wrapper">
<div class="container">
<div class="row">
	<div class="col-xs-12">

		<div class="jumbotron">
			<h1>Welcome to <?php echo $settings->site_name;?></h1>
			<p class="text-muted">A place to document Lotus Elan and Lotus Elan Plus 2</p>
			<p>
			<?php if($user->isLoggedIn()){$uid = $user->data()->id;?>
				<a class="btn btn-default" href="users/account.php" role="button">User Account &raquo;</a>
			<?php }else{?>
				<a class="btn btn-warning" href="users/login.php" role="button">Log In &raquo;</a>
				<a class="btn btn-info" href="users/join.php" role="button">Sign Up &raquo;</a>
			<?php } ?>
			</p>
		</div>
	</div>
</div>
<div class="row">
<?php
// To generate a sample notification, uncomment the code below.
// It will do a notification everytime you refresh index.php.
// $msg = 'This is a sample notification! <a href="'.$us_url_root.'users/logout.php">Go to Logout Page</a>';
// $notifications->addNotification($msg, $user->data()->id);
 ?>
<div class="col-md-6">
	<div class="panel panel-default">
		<div class="panel-heading"><strong>About the Registry</strong></div>
		<div class="panel-body">
			<p>This is the Registry for the 1963 thru 1973 Lotus
			Elan and the 1967 thru 1974 Lotus Elan Plus 2.  The purpose of the registry is to keep a
			history of the cars, trace the evolution of the
			Lotus Elan and to facilitate owner communication.
			</p>

			<p>The Lotus Elan Registry started in January
			2003.  A thread on LotusElan.net asked the
			question, <a
			href="http://www.lotuselan.net/forums/elan-f14/lotus-elan-register-t349.html">Does
			anybody know if there is a Lotus Elan register?</a>
			I bashed together a registry and a few years later
			we have over 300 cars accounted for with more added every month.<p>

			<h3>Important Resources</h3>
			<ul>
			<li><a href="http://www.lotuselansprint.com/index.asp">The Lotus Elan Sprint</a></li>
			<li><a href="http://www.type26register.com/">The Lotus Elan Type 26 Registry</a></li>
			<li><a href="http://www.lotuselan.net/">LotusElan.Net</a></li>
			</ul>
			
		</div> 

	</div><!-- /panel -->
</div><!-- /.col -->
<div class="col-md-6">
	<div class="panel panel-default">
		<div class="panel-heading"><strong>Picture</strong></div>
		<div class="panel-body">
			<img class="polaroid" src="app/random_picture.php" width="510" ><br>
	<br />

		</div>
	</div><!-- /panel -->
</div><!-- /.col -->
</div><!-- /.row -->

<!--  Don't show the box 

-->
	</div><!-- /panel -->
</div><!-- /.col -->
</div><!-- /.row -->

</div> <!-- /container -->

</div> <!-- /#page-wrapper -->

<!-- footers -->
<?php require_once $abs_us_root.$us_url_root.'users/includes/page_footer.php'; // the final html footer copyright row + the external js calls ?>

<!-- Place any per-page javascript here -->


<?php require_once $abs_us_root.$us_url_root.'users/includes/html_footer.php'; // currently just the closing /body and /html ?>
