<?php
/**
 * Redirect file for backward compatibility
 * Redirects statistics.php to reports/statistics.php
 */
header('Location: reports/statistics.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;