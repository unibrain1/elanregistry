<?php
/**
 * Redirect file for backward compatibility
 * Redirects contact.php to contact/index.php
 */
header('Location: contact/index.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;