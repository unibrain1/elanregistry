<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}
// EDIT THE 2 LINES BELOW AS REQUIRED
$subject = 'elanregistry.org - Owner to Owner Contact';

function died($error)
{
    // your error code can go here
    echo 'We are very sorry, but there were error(s) found with the form you submitted. ';
    echo 'These errors appear below.<br /><br />';
    echo $error . '<br /><br />';
    echo 'Please go back and fix these errors.<br /><br />';
    die();
}

// Make sure no one tries to add header like keywords
function clean_string($string)
{
    $bad = array('content-type', 'bcc:', 'to:', 'cc:', 'href');
    return str_replace($bad, '', $string);
}

//Forms posted now process it
if (!empty($_POST)) {
    $token = Input::get('csrf');
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {
        $action = Input::get('action');
        if ($action === 'send_message' &&  $_POST['from'] && $_POST['to'] && $_POST['message']) {
            $f              =  unserialize($_POST['from']);
            $t              =  unserialize($_POST['to']);

            $toEmail        =  $t['email'];

            $toName         =  $t['fname'] . ' ' . $t['lname'];
            $fromEmail      =  $f['email'];
            $fromName       =  $f['fname'] . ' ' . $f['lname'];

            $options        =  array(
                'from'     => $fromEmail,
                'from_name'      => $fromName,
                'reply'     => $fromEmail,
            );

            $template       =  array(
                'message'   => Input::get('message'),
                'from'      => $fromName,
                'fromEmail' => $fromEmail,
                'to'        => $toName
            );

            $body = email_body('_email_contact_owner.php', $template);

            $result = email($toEmail, $subject, $body, $options);

            logger($user->data()->id, "ElanRegistry", "contact_owner_email.php from " . $fromEmail . " to " . $toEmail);
        } else {
            died('Not enough parameters');
        }
    } // End Post with data
} // End Post

?>

<div id='page-wrapper'>
    <div class='container-fluid'>
        <div class='row'>
            <div class='col-sm-12'>
                <div class='jumbotron'>
                    <?php
                    if ($result) {
                        echo '<div class="alert alert-success" role="alert"><strong>Mail sent successfully<strong><br />
                        <p>Taking you back home in a few seconds</p></div>';
                    } else {
                        echo '<div class="alert alert-danger" role="alert">Mail ERROR</div><br />';
                    }
                    ?>
                    <script>
                        //Using setTimeout to execute a function after 5 seconds.
                        setTimeout(function() {
                            //Redirect with JavaScript
                            window.location.href = '<?= $us_url_root ?>';
                        }, 5000);
                    </script>
                </div><!-- End of main content section -->
            </div> <!-- /.col -->
        </div> <!-- /.row -->
    </div> <!-- /.container -->
</div> <!-- /.wrapper -->