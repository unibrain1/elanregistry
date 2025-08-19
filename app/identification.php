<?php
/**
 * Redirect file for backward compatibility
 * Redirects identification.php to cars/identify.php
 */
header('Location: cars/identify.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;