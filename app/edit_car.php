<?php
/**
 * Redirect file for backward compatibility
 * Redirects edit_car.php to cars/edit.php
 */
header('Location: cars/edit.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;