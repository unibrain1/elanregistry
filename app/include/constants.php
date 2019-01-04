<?php

/**
 * Constants.php
 *
 * This file is intended to group all constants to
 * make it easier for the site administrator to tweak
 */

define("IMAGE_URL", "userimages/");
define("THUMB_URL", "userimages/thumbs/");
define("IMAGE_PATH", "userimages");

/**
 * Email Constants - these specify what goes in
 * the from field in the emails that the script
 * sends to users, and whether to send a
 * welcome email to newly registered users.
 *
 * I should really get this from the UserSpice databaase
 */
define("EMAIL_FROM_NAME", "Lotus Elan Registrar");
define("EMAIL_FROM_ADDR", "registrar@elanregistry.org");
define("EMAIL_WELCOME", true);
define("EMAIL_CAR", true);

/* 
 SSSL definition for phpMyAdmin - May also be in Spice database
*/
define("SSL", 0 );


?>
