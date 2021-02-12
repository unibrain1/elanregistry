<?php
// This is a user-facing page
/*
UserSpice 5
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

ini_set('allow_url_fopen', 1);
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>

<?php if (!securePage($_SERVER['PHP_SELF'])) {
  die();
}
$hooks =  getMyHooks();
if (ipCheckBan()) {
  Redirect::to($us_url_root . 'usersc/scripts/banned.php');
  die();
}
if ($user->isLoggedIn()) {
  Redirect::to($us_url_root . 'index.php');
}
includeHook($hooks, 'pre');

$form_method = 'POST';
$form_action = 'join.php';
$vericode = randomstring(15);

$form_valid = FALSE;

//Decide whether or not to use email activation
$query = $db->query('SELECT * FROM email');
$results = $query->first();
$act = $results->email_act;

//Opposite Day for Pre-Activation - Basically if you say in email
//settings that you do NOT want email activation, this lists new
//users as active in the database, otherwise they will become
//active after verifying their email.
if ($act == 1) {
  $pre = 0;
} else {
  $pre = 1;
}

$dateFormat = "Y-m-d H:i:s";
$reCaptchaValid = FALSE;

if (Input::exists()) {
  $token = $_POST['csrf'];
  if (!Token::check($token)) {
    include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
  }

  $fname = Input::get('fname');
  $lname = Input::get('lname');
  $email = Input::get('email');
  $city = Input::get('city');
  $state = Input::get('state');
  $country = Input::get('country');

  if ($settings->auto_assign_un == 1) {
    $username = username_helper($fname, $lname, $email);
    if (!$username) {
      $username = null;
    }
  } else {
    $username = Input::get('username');
  }

  $validation = new Validate();
  if ($settings->auto_assign_un == 0) {
    if (pluginActive('userInfo', true)) {
      $is_not_email = false;
    } else {
      $is_not_email = true;
    }
    $validation->check($_POST, [
      'username' => [
        'display' => lang('GEN_UNAME'),
        'is_not_email' => $is_not_email,
        'required' => true,
        'min' => $settings->min_un,
        'max' => $settings->max_un,
        'unique' => 'users',
      ],
      'fname' => [
        'display' => lang('GEN_FNAME'),
        'required' => true,
        'min' => 1,
        'max' => 100,
      ],
      'lname' => [
        'display' => lang('GEN_LNAME'),
        'required' => true,
        'min' => 1,
        'max' => 100,
      ],
      'email' => [
        'display' => lang('GEN_EMAIL'),
        'required' => true,
        'valid_email' => true,
        'unique' => 'users',
      ],

      'password' => [
        'display' => lang('GEN_PASS'),
        'required' => true,
        'min' => $settings->min_pw,
        'max' => $settings->max_pw,
      ],
      'confirm' => [
        'display' => lang('PW_CONF'),
        'required' => true,
        'matches' => 'password',
      ],
    ]);
  }
  if ($settings->auto_assign_un == 1) {
    $validation->check($_POST, [
      'fname' => [
        'display' => lang('GEN_FNAME'),
        'required' => true,
        'min' => 1,
        'max' => 60,
      ],
      'lname' => [
        'display' => lang('GEN_LNAME'),
        'required' => true,
        'min' => 1,
        'max' => 60,
      ],
      'email' => [
        'display' => lang('GEN_EMAIL'),
        'required' => true,
        'valid_email' => true,
        'unique' => 'users',
        'min' => 5,
        'max' => 100,
      ],

      'password' => [
        'display' => lang('GEN_PASS'),
        'required' => true,
        'min' => $settings->min_pw,
        'max' => $settings->max_pw,
      ],
      'confirm' => [
        'display' => lang('PW_CONF'),
        'required' => true,
        'matches' => 'password',
      ],
    ]);
  }

  if ($validation->passed()) {
    //Logic if ReCAPTCHA is turned ON
    if ($settings->recaptcha > 0) {
      if (!function_exists('post_captcha')) {
        function post_captcha($user_response)
        {
          global $settings;
          $fields_string = '';
          $fields = [
            'secret' => $settings->recap_private,
            'response' => $user_response
          ];
          foreach ($fields as $key => $value) {
            $fields_string .= $key . '=' . $value . '&';
          }
          $fields_string = rtrim($fields_string, '&');

          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
          curl_setopt($ch, CURLOPT_POST, count($fields));
          curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, True);

          $result = curl_exec($ch);
          curl_close($ch);

          return json_decode($result, true);
        }
      }

      // Call the function post_captcha
      $res = post_captcha($_POST['g-recaptcha-response']);

      if (!$res['success']) {
        // What happens when the reCAPTCHA is not properly set up
        echo 'reCAPTCHA error: Check to make sure your keys match the registered domain and are in the correct locations. You may also want to doublecheck your code for typos or syntax errors.';
      } else {
        $reCaptchaValid = TRUE;
        $form_valid = TRUE;
      }
    } //else for recaptcha

    if ($reCaptchaValid || $settings->recaptcha == 0) {
      $form_valid = TRUE;
      //add user to the database
      $user = new User();
      $join_date = date($dateFormat);
      $params = [
        'fname' => Input::get('fname'),
        'email' => $email,
        'username' => $username,
        'vericode' => $vericode,
        'join_vericode_expiry' => $settings->join_vericode_expiry
      ];
      $vericode_expiry = date($dateFormat);
      if ($act == 1) {
        //Verify email address settings
        $to = rawurlencode($email);
        $subject = html_entity_decode($settings->site_name, ENT_QUOTES);
        $body = email_body('_email_template_verify.php', $params);
        email($to, $subject, $body);
        $vericode_expiry = date($dateFormat, strtotime("+$settings->join_vericode_expiry hours", strtotime(date($dateFormat))));
      }
      try {
        $fields = [
          'username' => $username,
          'fname' => ucfirst(Input::get('fname')),
          'lname' => ucfirst(Input::get('lname')),
          'email' => Input::get('email'),
          'password' => password_hash(Input::get('password', true), PASSWORD_BCRYPT, ['cost' => 12]),
          'permissions' => 1,
          'join_date' => $join_date,
          'email_verified' => $pre,
          'vericode' => $vericode,
          'vericode_expiry' => $vericode_expiry,
          'oauth_tos_accepted' => true,
        ];
        $activeCheck = $db->query('SELECT active FROM users');
        if (!$activeCheck->error()) {
          $fields['active'] = 1;
        }
        $theNewId = $user->create($fields);
        includeHook($hooks, 'post');
      } catch (Exception $e) {
        if ($eventhooks =  getMyHooks(['page' => 'joinFail'])) {
          includeHook($eventhooks, 'body');
        }
        die($e->getMessage());
      }
      if ($form_valid === true) { //this allows the plugin hook to kill the post but it must delete the created user
        include($abs_us_root . $us_url_root . 'usersc/scripts/during_user_creation.php');

        if ($act == 1) {
          logger($theNewId, 'User', 'Registration completed and verification email sent.');
          $query = $db->query('SELECT * FROM email');
          $results = $query->first();
          $act = $results->email_act;
          require $abs_us_root . $us_url_root . 'users/views/_joinThankYou_verify.php';
        } else {
          logger($theNewId, 'User', 'Registration completed.');
          if (file_exists($abs_us_root . $us_url_root . 'usersc/views/_joinThankYou.php')) {
            require_once $abs_us_root . $us_url_root . 'usersc/views/_joinThankYou.php';
          } else {
            require $abs_us_root . $us_url_root . 'users/views/_joinThankYou.php';
          }
        }
        die();
      }
    }
  } //Validation
} //Input exists

?>
<?php header('X-Frame-Options: DENY'); ?>
<div id="page-wrapper">
  <div class="container">
    <?php
    if ($settings->registration == 1) {
      require $abs_us_root . $us_url_root . 'usersc/views/_join.php';
    } else {
      require $abs_us_root . $us_url_root . 'users/views/_joinDisabled.php';
    }
    includeHook($hooks, 'bottom');
    ?>

  </div>
</div>

<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; // currently just the closing /body and /html 
?>

<?php if ($settings->recaptcha > 0) { ?>
  <script src="https://www.google.com/recaptcha/api.js?render=<?= $settings->recap_public; ?>"></script>
  <script>
    grecaptcha.ready(function() {
      grecaptcha.execute("<?= $settings->recap_public; ?>", {
        action: 'contact'
      }).then(function(token) {
        var recaptchaResponse = document.getElementById('recaptchaResponse');
        recaptchaResponse.value = token;
      });
    });
  </script>

<?php } ?>


<script>
  $(document).ready(function() {

    $("#password").keyup(function() {
      $('#password_view_control').hover(function() {
        $('#password').attr('type', 'text');
        $('#confirm').attr('type', 'text');
      }, function() {
        $('#password').attr('type', 'password');
        $('#confirm').attr('type', 'password');
      });

      var pswd = $("#password").val();
      //validate the length
      if (pswd.length >= '<?= $settings->min_pw ?>' && pswd.length <= '<?= $settings->max_pw ?>') {
        $("#character_range_icon").removeClass("gray_out_icon");
        $("#character_range").removeClass("gray_out_text");
      } else {
        $("#character_range_icon").addClass("gray_out_icon");
        $("#character_range").addClass("gray_out_text");
      }

      //validate capital letter
      if (pswd.match(/[A-Z]/)) {
        $("#num_caps_icon").removeClass("gray_out_icon");
        $("#caps").removeClass("gray_out_text");
      } else {
        $("#num_caps_icon").addClass("gray_out_icon");
        $("#caps").addClass("gray_out_text");
      }

      //validate number
      if (pswd.match(/\d/)) {
        $("#num_numbers_icon").removeClass("gray_out_icon");
        $("#number").removeClass("gray_out_text");
      } else {
        $("#num_numbers_icon").addClass("gray_out_icon");
        $("#number").addClass("gray_out_text");
      }
    });

    $("#confirm").keyup(function() {
      var pswd = $("#password").val();
      var confirm_pswd = $("#confirm").val();

      //validate password_match
      if (pswd == confirm_pswd) {
        $("#password_match_icon").removeClass("gray_out_icon");
        $("#password_match").removeClass("gray_out_text");
      } else {
        $("#password_match_icon").addClass("gray_out_icon");
        $("#password_match").addClass("gray_out_text");
      }

    });
  });
</script>