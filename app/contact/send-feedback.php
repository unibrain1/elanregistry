<?php

/**
 * send_form_email.php
 * Handles feedback form submissions and sends emails to the registry admin.
 *
 * Validates input, checks CSRF token, and sends feedback via email.
 * Uses the site template for layout and security.
 *
 * @author Elan Registry Admin
 * @copyright 2025
 */
require_once '../../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>

<?php if (!securePage($_SERVER['PHP_SELF'])) {
    die();
} ?>

<?php
if (isset($_POST['email'])) {
    
    // CSRF Protection
    $token = Input::get('csrf');
    if (!Token::check($token)) {
        include_once $abs_us_root . $us_url_root . 'usersc/scripts/token_error.php';
        exit;
    }

    // EDIT THE 2 LINES BELOW AS REQUIRED
    $email_to = "jim@elanregistry.org";
    $email_subject = "[ELANREGISTRY] Feedback";

    function died($error)
    {
        // your error code can go here
        echo "We are very sorry, but there were error(s) found with the form you submitted. ";
        echo "These errors appear below.<br /><br />";
        echo $error . "<br /><br />";
        echo "Please go back and fix these errors.<br /><br />";
        die();
    }


    // Get and validate form data using secure Input::get()
    $name = Input::get('name');
    $email_from = Input::get('email');
    $id_from = Input::get('id');
    $comments = Input::get('comments');
    
    // Validation expected data exists
    if (empty($name) || empty($email_from) || empty($id_from) || empty($comments)) {
        died('We are sorry, but there appears to be a problem with the form you submitted.');
    }

    $error_message = "";
    $email_exp = '/^[A-Za-z0-9._%-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}$/';

    if (!preg_match($email_exp, $email_from)) {
        $error_message .= 'The Email Address you entered does not appear to be valid.<br />';
    }

    $string_exp = "/^[A-Za-z .'-]+$/";

    if (!preg_match($string_exp, $name)) {
        $error_message .= 'The Name you entered does not appear to be valid.<br />';
    }

    if (strlen($comments) < 2) {
        $error_message .= 'The Comments you entered do not appear to be valid.<br />';
    }

    if (strlen($error_message) > 0) {
        died($error_message);
    }

    $body = "";

    function cleanString($string)
    {
        $bad = array("content-type", "bcc:", "to:", "cc:", "href");
        return str_replace($bad, "", $string);
    }

    $body .= "Name       : " . cleanString($name) . "</br>";
    $body .= "Email      : " . cleanString($email_from) . "</br>";
    $body .= "Account ID : " . cleanString($id_from) . "</br></br>";
    $body .= "Comments   : " . cleanString($comments) . "</br>";

    $opts = array(
        // 'from' => $email_from,  // If you change the from email address gmail thinks it's spam
        // 'from_name'  => $name,
        'reply_name'  => $name,
        'reply' => $email_from
    );


    $email_sent = email($email_to, $email_subject, $body, $opts);
    if (!$email_sent) {
        logger(1, "Feedback form", "Error sending email");
    }
    logger(1, "Feedback form", "Complete:" . $email_to . " " . $email_subject . " " . $body . " " . $opts);
}

?>
<div class="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="jumbotron">
                    <!-- Content Goes Here. Class width can be adjusted -->

                    <h2>
                        <p>Thank you for feedback! Your help makes the Elan Registry better!</p>
                    </h2>
                    <h3>
                        <p>Taking you back home in a few secondss</p>
                    </h3>
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


<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer
?>