<?php
/**
 * Redirect file for backward compatibility
 * Redirects contact_owner.php to contact/owner.php
 */
header('Location: contact/owner.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;