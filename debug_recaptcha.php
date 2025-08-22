<?php
/**
 * reCAPTCHA Configuration Diagnostic Script
 * Temporary script to check current reCAPTCHA settings
 */

require_once 'users/init.php';

echo "<h1>reCAPTCHA Configuration Diagnostic</h1>";

// Check if reCAPTCHA plugin is active
$pluginsFile = $abs_us_root . $us_url_root . 'usersc/plugins/plugins.ini.php';
if (file_exists($pluginsFile)) {
    $plugins = file_get_contents($pluginsFile);
    echo "<h2>Plugin Status:</h2>";
    if (strpos($plugins, 'recaptcha = 1') !== false) {
        echo "✅ reCAPTCHA plugin is ACTIVE<br>";
    } else {
        echo "❌ reCAPTCHA plugin is INACTIVE<br>";
    }
} else {
    echo "❌ Plugins config file not found<br>";
}

// Check database settings
try {
    $db = DB::getInstance();
    $settings = $db->query("SELECT recaptcha, recap_public, recap_private, recap_version, recap_type FROM settings")->first();
    
    echo "<h2>Database Configuration:</h2>";
    echo "reCAPTCHA Enabled: " . ($settings->recaptcha ? "✅ YES" : "❌ NO") . "<br>";
    echo "Site Key Length: " . strlen($settings->recap_public) . " characters<br>";
    echo "Secret Key Length: " . strlen($settings->recap_private) . " characters<br>";
    echo "Version: " . $settings->recap_version . "<br>";
    echo "Type: " . $settings->recap_type . " (" . ($settings->recap_type == 1 ? "Checkbox" : "Invisible") . ")<br>";
    
    echo "<h2>Site Key (First 10 chars):</h2>";
    echo substr($settings->recap_public, 0, 10) . "...<br>";
    
    if (empty($settings->recap_public) || empty($settings->recap_private)) {
        echo "<h2>❌ PROBLEM: Missing API Keys</h2>";
        echo "You need to configure Google reCAPTCHA keys in the admin panel.<br>";
        echo "Visit: <a href='https://www.google.com/recaptcha/admin'>https://www.google.com/recaptcha/admin</a><br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "<br>";
}

// Check if hook files exist
$hookFiles = [
    'loginform.php' => $abs_us_root . $us_url_root . 'usersc/plugins/recaptcha/hooks/loginform.php',
    'loginpost.php' => $abs_us_root . $us_url_root . 'usersc/plugins/recaptcha/hooks/loginpost.php',
    'loginbottom.php' => $abs_us_root . $us_url_root . 'usersc/plugins/recaptcha/hooks/loginbottom.php'
];

echo "<h2>Hook Files Status:</h2>";
foreach ($hookFiles as $name => $path) {
    if (file_exists($path)) {
        echo "✅ $name exists<br>";
    } else {
        echo "❌ $name missing<br>";
    }
}

// Test the addCaptcha function
echo "<h2>Function Test:</h2>";
if (function_exists('addCaptcha')) {
    echo "✅ addCaptcha() function is available<br>";
} else {
    echo "❌ addCaptcha() function not found<br>";
}

if (function_exists('verifyCaptcha')) {
    echo "✅ verifyCaptcha() function is available<br>";
} else {
    echo "❌ verifyCaptcha() function not found<br>";
}

echo "<h2>Current Domain:</h2>";
echo $_SERVER['HTTP_HOST'] . "<br>";
echo "Make sure this domain is registered in your Google reCAPTCHA console.<br>";

?>