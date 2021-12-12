

<?php
require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';
?>

<?php if (!securePage($_SERVER['PHP_SELF'])) {
    die();
} ?>

<?php
if (isset($_POST['email'])) {
 
    // EDIT THE 2 LINES BELOW AS REQUIRED
    $email_to = "elanregistry@gmail.com";
    $email_subject = "[ELANREGISTRY] Feedback";
 
    function died($error)
    {
        // your error code can go here
        echo "We are very sorry, but there were error(s) found with the form you submitted. ";
        echo "These errors appear below.<br /><br />";
        echo $error."<br /><br />";
        echo "Please go back and fix these errors.<br /><br />";
        die();
    }
 
 
    // validation expected data exists
    if (!isset($_POST['name']) ||
        !isset($_POST['email']) ||
        !isset($_POST['id']) ||
        !isset($_POST['comments'])) {
        died('We are sorry, but there appears to be a problem with the form you submitted.');
    }
 
    
 
    $name = $_POST['name']; // required
    $email_from = $_POST['email']; // required
    $id_from = $_POST['id']; // required
    $comments = $_POST['comments']; // required
 
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
 
    $email_message = "Feedback below.\n\n";
 
     
    function clean_string($string)
    {
        $bad = array("content-type","bcc:","to:","cc:","href");
        return str_replace($bad, "", $string);
    }
 
    $email_message .= "Name       : ".clean_string($name)."\n";
    $email_message .= "Email      : ".clean_string($email_from)."\n";
    $email_message .= "Account ID : ".clean_string($id_from)."\n\n";
    $email_message .= "Comments   :\n".clean_string($comments)."\n";
 
    // create email headers
    $headers = 'From: '.$email_from."\r\n".
'Reply-To: '.$email_from."\r\n" .
'X-Mailer: PHP/' . phpversion();
    @mail($email_to, $email_subject, $email_message, $headers);
}

?>
<div id="page-wrapper">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-12">
            <div class="jumbotron">
              <!-- Content Goes Here. Class width can be adjusted -->

            <h2><p>Thank you for feedback!  Your help makes the Elan Registry better!</p></h2>
            <h3><p>Taking you back home in a few secondss</p></h3>

            <script>
            //Using setTimeout to execute a function after 5 seconds.
            setTimeout(function () {
               //Redirect with JavaScript
               window.location.href= '/';
            }, 5000);
            
            </script>
          </div><!-- End of main content section -->
      </div> <!-- /.col -->
    </div> <!-- /.row -->
  </div> <!-- /.container -->
</div> <!-- /.wrapper -->


<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer ?>
