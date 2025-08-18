<?php
/**
 * Redirect file for backward compatibility
 * Redirects mapmarkers2.xml.php to cars/mapmarkers.xml.php
 */
header('Location: cars/mapmarkers.xml.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;