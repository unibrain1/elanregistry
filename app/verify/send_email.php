<?php
require_once '../../users/init.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}


$db = DB::getInstance();

$query = $db->query("SELECT * FROM email");
$base_url = $query->first()->verify_url;

$verify_url=$base_url.$us_url_root."app/verify/verify_car.php";

$carQ = $db->query("SELECT * FROM users_carsview WHERE mtime < DATE_SUB(NOW(), INTERVAL 16 YEAR) ORDER BY `users_carsview`.`mtime` ASC LIMIT 1");
  $carData=$carQ->results();  // Results as an array

  // Set verification codes

foreach ($carData as $car) {
    echo "<hr>Send email for car:".$car->id."<br>";
    // Update the verification code
    $verificationCode = md5(uniqid(rand(), true));
    $db->query("UPDATE cars SET vericode = ? WHERE id = ?", [$verificationCode, $car->id]);

    // Delete the cars_hist entry for adding the vericode
    $id = $db->query('SELECT car_id, id, MAX(timestamp) AS max FROM cars_hist where car_id = ? GROUP BY id, car_id ORDER BY `max` DESC LIMIT 1', [$car->id])->first()->id;
    $db->deleteById("cars_hist", $id);


    if ($car->image and file_exists($abs_us_root.$us_url_root.'app/userimages/'.$car->image)) {
        $image = '<img src="'.$base_url.$us_url_root.'app/userimages/'.$car->image.'">';
    } else {
        $image = 'No image';
    }
    $verify_btn = $verify_url.'?code='.$verificationCode."&action=verify";
    $sold_btn = $verify_url.'?code='.$verificationCode."&action=sold";
    $edit_btn = $verify_url.'?code='.$verificationCode."&action=edit";


    $to= $car->email;
    $subject = "Lotus Elan Registry - Request for Information Verification";

    // Get the email template
    ob_start();
    include('_email_template.php');
    $body = ob_get_contents();
    ob_get_clean();
    //echo $body;
    email($to, $subject, $body); // PHPMailer
}
