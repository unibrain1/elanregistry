<?php
require_once("init.php");
//For security purposes, it is MANDATORY that this page be wrapped in the following
//if statement. This prevents remote execution of this code.
if (in_array($user->data()->id, $master_account)){


$db = DB::getInstance();
include "plugin_info.php";


// This should update all the users to make sure they have a profile:q!
$users = $db->query("SELECT id FROM users")->results();
foreach($users as $u){
$check = $db->query("SELECT id FROM profiles WHERE user_id = ?",[$u->id])->count();
if($check < 1){
  $db->insert('profiles',['user_id'=>$u->id,'bio'=>"This is your bio"]);
}
}

//all actions should be performed here.
$check = $db->query("SELECT * FROM us_plugins WHERE plugin = ?",array($plugin_name))->count();
if($check > 0){
	err($plugin_name.' has already been installed!');
}else{
 $fields = array(
	 'plugin'=>$plugin_name,
	 'status'=>'installed',
 );
 $db->insert('us_plugins',$fields);
 if(!$db->error()) {
	 	err($plugin_name.' installed');
		logger($user->data()->id,"USPlugins",$plugin_name." installed");
 } else {
	 	err($plugin_name.' was not installed');
		logger($user->data()->id,"USPlugins","Failed to to install plugin, Error: ".$db->errorString());
 }
}

//do you want to inject your plugin in the middle of core UserSpice pages?
$hooks = [];

// Add Car Information to update/process/display
// Stubs for future plugin functionality
// $hooks['admin.php?view=user']['form'] = 'hooks/admin_user_form.php';
// $hooks['admin.php?view=user']['bottom'] = 'hooks/admin_user_bottom.php';

//  Add car information to the table
$hooks['admin.php?view=users']['pre'] = 'hooks/admin_users_pre.php';  // Override the default user selection query and get all users plus the carid
$hooks['admin.php?view=users']['body'] = 'hooks/admin_users_body.php';  
$hooks['admin.php?view=users']['bottom'] = 'hooks/admin_users_bottom.php';
// 
registerHooks($hooks,$plugin_name);

} //do not perform actions outside of this statement
