<?php
/*
 * database.php
 *
 * This file is intended to group all database information in a single location
 */

 
/**
 * Database Constants - these constants are required
 * in order for there to be a successful connection
 * to the MySQL database. Make sure the information is
 * correct.
 */
define("DB_SERVER", "localhost");
define("DB_USER", "elanregi_reg");
define("DB_PASS", "LCVFNud6$#U^!^1958kc");
define("DB_NAME", "elanregi_reg");

/**
 * Database Table Constants - these constants
 * hold the names of all the database tables used
 * in the script.
 */
define("TBL_CARS", "cars");
// define("TBL_USERS", "users");
define("TBL_USERCARS", "user_cars"); /* Join of users and cars */
// define("TBL_ACTIVE_USERS",  "active_users");
// define("TBL_ACTIVE_GUESTS", "active_guests");
// define("TBL_BANNED_USERS",  "banned_users");

?>
