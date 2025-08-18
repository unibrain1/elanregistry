<?php
/**
 * Redirect file for backward compatibility
 * Redirects send_form_email.php to contact/send-feedback.php
 */
header('Location: contact/send-feedback.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;