<?php
/**
 * Redirect file for backward compatibility
 * Redirects contact_owner_email.php to contact/send-owner-email.php
 */
header('Location: contact/send-owner-email.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;