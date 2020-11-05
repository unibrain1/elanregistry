<?php
//Anything you put in this file will override the default UserSpice language that's located in
// users/lang/en-US.php
//NOTE: You can also add as many other language keys as you wish and use them wherever you want in
//your project.

//You can test this out by uncommenting the section below and you will note that the menu on the home page changes from the default Home to Homepage

$lang = array_merge($lang, array(
    "AB_PATHCREATE"    => "Create backup path        : ",
    "AB_PATHERROR"     => "Error creating backup path: ",
    "AB_PATHEXISTED"   => "Backup path existed       : ",
    "AB_BACKUPSUCCESS" => "backupObjects succesfull  : ",
    "AB_BACKUPFAIL"    => "backupObjects fail        : ",
    "AB_BACKUPFILE"    => "Log file created          : ",
    "AB_DB_FILES_ZIP"  => "backupZip succesfull      : ",
    "AB_FILE_RENAMED"  => "backupZip renamed         : ",
    "AB_COMMAND"       => "Backup command            : ",
    "AB_FILE_REMOVED"  => "Old Backup removed        : ",
));
