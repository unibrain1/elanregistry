<?php

/*
Universal Error File

Add this to the htaccess file

ErrorDocument 400 /error/index.php
ErrorDocument 401 /error/index.php
ErrorDocument 403 /error/index.php
ErrorDocument 404 /error/index.php
ErrorDocument 405 /error/index.php
ErrorDocument 408 /error/index.php
ErrorDocument 500 /error/index.php
ErrorDocument 502 /error/index.php
ErrorDocument 504 /error/index.php

*/
?>

<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// server protocol
$protocol = empty($_SERVER['HTTPS']) ? 'http' : 'https';

// domain name
$domain = $_SERVER['SERVER_NAME'];

// server port
$port = $_SERVER['SERVER_PORT'];
$disp_port = ($protocol == 'http' && $port == 80 || $protocol == 'https' && $port == 443) ? '' : ":$port";

// put em all together to get the complete base URL
$url = "${protocol}://${domain}${disp_port}${us_url_root}";


$status = $_SERVER['REDIRECT_STATUS'];
$codes = array(
    403 => array('403 Forbidden', 'The server has refused to fulfill your request.'),
    404 => array('404 Not Found', 'The document/file requested was not found on this server.'),
    405 => array('405 Method Not Allowed', 'The method specified in the Request-Line is not allowed for the specified resource.'),
    408 => array('408 Request Timeout', 'Your browser failed to send a request in the time allowed by the server.'),
    500 => array('500 Internal Server Error', 'The request was unsuccessful due to an unexpected condition encountered by the server.'),
    502 => array('502 Bad Gateway', 'The server received an invalid response from the upstream server while trying to fulfill the request.'),
    504 => array('504 Gateway Timeout', 'The upstream server failed to send a request in the time allowed by the server.'),
);

$title = $codes[$status][0];
$message = $codes[$status][1];
if ($title === false || strlen($status) != 3) {
    $message = 'Please supply a valid status code.';
}


?>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="jumbotron">

            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h4 class="card-title">Lucas Prince of Darkness has brought you here</h4>
                    <p class="card-text"><?= $message ?><br><br>Redirecting to <?= $url ?> in <span id="counter">10</span> second(s)<br>
                    </p>
                </div>
            </div><!-- card block -->

        </div> <!-- /.jumbotron -->
    </div> <!-- /.container -->
</div> <!-- page-wrapper -->

<script type="text/javascript">
    function countdown() {
        var i = document.getElementById('counter');
        if (parseInt(i.innerHTML) <= 0) {
            location.href = '<?= $url ?>';
        }
        if (parseInt(i.innerHTML) != 0) {
            i.innerHTML = parseInt(i.innerHTML) - 1;
        }
    }
    setInterval(function() {
        countdown();
    }, 1000);
</script>

<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'users/includes/page_footer.php'; // the final html footer copyright row + the external js calls
?>

<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer
