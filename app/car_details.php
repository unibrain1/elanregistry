<?php
/**
 * Redirect file for backward compatibility
 * Redirects car_details.php to cars/details.php
 */
header('Location: cars/details.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;