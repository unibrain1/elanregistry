<?php
/**
 * Redirect file for backward compatibility
 * Redirects manage_cars.php to cars/manage.php
 */
header('Location: cars/manage.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;