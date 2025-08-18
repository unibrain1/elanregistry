<?php
/**
 * Redirect file for backward compatibility
 * Redirects list_factory.php to cars/factory.php
 */
header('Location: cars/factory.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;