<?php
/**
 * Redirect file for backward compatibility
 * Redirects list_cars.php to cars/index.php
 */
header('Location: cars/index.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;