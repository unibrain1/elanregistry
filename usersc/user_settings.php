<?php
/*
UserSpice 4
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
?>
<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>


<?php
if (!securePage($_SERVER['PHP_SELF'])) {
    die();
} ?>

<?php
//dealing with if the user is logged in
if ($user->isLoggedIn() && !checkMenu(2, $user->data()->id)) {
    if (($settings->site_offline == 1) && (!in_array($user->data()->id, $master_account)) && ($currentPage != 'login.php') && ($currentPage != 'maintenance.php')) {
        $user->logout();
        Redirect::to($us_url_root . 'users/maintenance.php');
    }
}


$emailQ = $db->query("SELECT * FROM email");
$emailR = $emailQ->first();
// dump($emailR);
// dump($emailR->email_act);
//PHP Goes Here!
$errors = [];
$successes = [];
$userId = $user->data()->id;
// $grav = get_gravatar(strtolower(trim($user->data()->email)));
$validation = new Validate();
$userdetails = $user->data();
// Get User Profile Information
// This is a hack and should be fixed - Get the Profile ID
$profileQ = $db->query("SELECT id FROM profiles WHERE user_id = ?", array($userId));
$profileId = $profileQ->results()[0]->id;
// USER ID is in $user_id .  Use the USER ID to get the users Profile information
$userQ = $db->query("SELECT * FROM profiles LEFT JOIN users ON user_id = users.id WHERE user_id = ?", array($userId));
if ($userQ->count() > 0) {
    $profiledetails = $userQ->first();

    /* Set the city, state, country for geolocation.  If there is an update of any of these values they will be overwritten */
    $city = $profiledetails->city;
    $state = $profiledetails->state;
    $country = $profiledetails->country;
} else {
    echo 'USER_SETTING(59) something is wrong with the user profile <br>';
    dump($userId);
    dump($userQ);
}

// Get the country list
$countryQ = $db->query("SELECT name FROM country");
if ($countryQ->count() > 0) {
    $countrylist = $countryQ->results();
}


//Temporary Success Message
$holdover = Input::get('success');
if ($holdover == 'true') {
    bold("Account Updated");
}
//Forms posted
if (!empty($_POST)) {
    $token = $_POST['csrf'];
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {
        //Update display name
        //if (($settings->change_un == 0) || (($settings->change_un == 2) && ($user->data()->un_changed == 1)))
        if ($userdetails->username != $_POST['username'] && ($settings->change_un == 1 || (($settings->change_un == 2) && ($user->data()->un_changed == 0)))) {
            $displayname = Input::get("username");
            $fields = array(
                'username' => $displayname,
                'un_changed' => 1,
            );
            $validation->check($_POST, array(
                'username' => array(
                    'display' => 'Username',
                    'required' => true,
                    'unique_update' => 'users,' . $userId,
                    'min' => $settings->min_un,
                    'max' => $settings->max_un
                )
            ));
            if ($validation->passed()) {
                if (($settings->change_un == 2) && ($user->data()->un_changed == 1)) {
                    Redirect::to($us_url_root . 'users/user_settings.php?err=Username+has+already+been+changed+once.');
                }
                $db->update('users', $userId, $fields);
                $successes[] = "Username updated.";
                logger($user->data()->id, "User", "Changed username from $userdetails->username to $displayname.");
            } else {
                //validation did not pass
                foreach ($validation->errors() as $error) {
                    $errors[] = $error;
                }
            }
        } else {
            $displayname = $userdetails->username;
        }
        //Update first name
        if ($userdetails->fname != $_POST['fname']) {
            $fname = ucfirst(Input::get("fname"));
            $fields = array('fname' => $fname);
            $validation->check($_POST, array(
                'fname' => array(
                    'display' => 'First Name',
                    'required' => true,
                    'min' => 1,
                    'max' => 25
                )
            ));
            if ($validation->passed()) {
                $db->update('users', $userId, $fields);
                $successes[] = 'First name updated.';
                logger($user->data()->id, "User", "Changed fname from $userdetails->fname to $fname.");
            } else {
                //validation did not pass
                foreach ($validation->errors() as $error) {
                    $errors[] = $error;
                }
            }
        } else {
            $fname = $userdetails->fname;
        }
        //Update last name
        if ($userdetails->lname != $_POST['lname']) {
            $lname = ucfirst(Input::get("lname"));
            $fields = array('lname' => $lname);
            $validation->check($_POST, array(
                'lname' => array(
                    'display' => 'Last Name',
                    'required' => true,
                    'min' => 1,
                    'max' => 25
                )
            ));
            if ($validation->passed()) {
                $db->update('users', $userId, $fields);
                $successes[] = 'Last name updated.';
                logger($user->data()->id, "User", "Changed lname from $userdetails->lname to $lname.");
            } else {
                //validation did not pass
                foreach ($validation->errors() as $error) {
                    $errors[] = $error;
                }
            }
        } else {
            $lname = $userdetails->lname;
        }
        // Extend user_setttings.php with some PROFILE information
        //Update City
        if ($profiledetails->city != $_POST['city']) {
            $city = ucfirst(Input::get("city"));
            $fields = array('city' => $city);
            $validation->check($_POST, array(
                'city' => array(
                    'display' => 'City',
                    'required' => true,
                    'min' => 1,
                    'max' => 25        // TODO Check the field valuidation to be consistant across inputs
                )
            ));
            if ($validation->passed()) {
                $db->update('profiles', $profileId, $fields);
                $successes[] = 'City updated.';
                logger($user->data()->id, "User", "Changed city from $profiledetails->city to $city.");
            } else {
                //validation did not pass
                foreach ($validation->errors() as $error) {
                    $errors[] = $error;
                }
            }
        } else {
            $city = $profiledetails->city;
        }

        //Update State
        if ($profiledetails->state != $_POST['state']) {
            $state = ucfirst(Input::get("state"));
            $fields = array('state' => $state);
            $validation->check($_POST, array(
                'state' => array(
                    'display' => 'State',
                    'required' => true,
                    'min' => 1,
                    'max' => 25        // TODO Check the field valuidation to be consistant across inputs
                )
            ));
            if ($validation->passed()) {
                $db->update('profiles', $profileId, $fields);
                $successes[] = 'State updated.';
                logger($user->data()->id, "User", "Changed state from $profiledetails->state to $state.");
            } else {
                //validation did not pass
                foreach ($validation->errors() as $error) {
                    $errors[] = $error;
                }
            }
        } else {
            $state = $profiledetails->state;
        }
        //Update Country
        if ($profiledetails->country != $_POST['country']) {
            $country = ucfirst(Input::get("country"));
            $fields = array('country' => $country);
            $validation->check($_POST, array(
                'country' => array(
                    'display' => 'Country',
                    'required' => true,
                    'min' => 1,
                    'max' => 25        // TODO Check the field valuidation to be consistant across inputs
                )
            ));
            if ($validation->passed()) {
                $db->update('profiles', $profileId, $fields);
                $successes[] = 'Country updated.';
                logger($user->data()->id, "User", "Changed country from $profiledetails->country to $country.");
            } else {
                //validation did not pass
                foreach ($validation->errors() as $error) {
                    $errors[] = $error;
                }
            }
        } else {
            $country = $profiledetails->country;
        }

        // Update geolocation
        include($abs_us_root . $us_url_root . 'app/views/_geolocate.php');
        $db->update('profiles', $profileId, $fields);
        $successes[] = 'Lat/Lon updated.';
        logger($user->data()->id, "User", "Changed updated lat/lon");

        //Update Website
        if ($profiledetails->website != $_POST['website']) {
            $website = Input::get("website");
            $fields = array('website' => $website);

            // Remove all illegal characters from a url
            $fields['website'] = filter_var($fields['website'], FILTER_SANITIZE_URL);

            // Validate url
            if (filter_var($fields['website'], FILTER_VALIDATE_URL)) {
                $db->update('profiles', $profileId, $fields);
                $successes[] = 'website updated.';
                logger($user->data()->id, "User", "Changed website from $profiledetails->website to $website.");
            } else {
                echo ("$url is not a valid URL");
                //validation did not pass
                $errors[] = "$url is not a valid URL";
            }
        } else {
            $state = $profiledetails->website;
        }

        // END Extend user_setttings.php with some PROFILE information

        if (!empty($_POST['password']) || $userdetails->email != $_POST['email'] || !empty($_POST['resetPin'])) {
            //Check password for email or pw update
            if (is_null($userdetails->password) || password_verify(Input::get('old'), $user->data()->password)) {

                //Update email
                if ($userdetails->email != $_POST['email']) {
                    $email = Input::get("email");
                    $confemail = Input::get("confemail");
                    $fields = array('email' => $email);
                    $validation->check($_POST, array(
                        'email' => array(
                            'display' => 'Email',
                            'required' => true,
                            'valid_email' => true,
                            'unique_update' => 'users,' . $userId,
                            'min' => 3,
                            'max' => 75
                        )
                    ));
                    if ($validation->passed()) {
                        if ($confemail == $email) {
                            if ($emailR->email_act == 0) {
                                $db->update('users', $userId, $fields);
                                $successes[] = 'Email updated.';
                                logger($user->data()->id, "User", "Changed email from $userdetails->email to $email.");
                            }
                            if ($emailR->email_act == 1) {
                                $vericode = randomstring(15);
                                $vericode_expiry = date("Y-m-d H:i:s", strtotime("+$settings->join_vericode_expiry hours", strtotime(date("Y-m-d H:i:s"))));
                                $db->update('users', $userId, ['email_new' => $email, 'vericode' => $vericode, 'vericode_expiry' => $vericode_expiry]);
                                //Send the email
                                $options = array(
                                    'fname' => $user->data()->fname,
                                    'email' => rawurlencode($user->data()->email),
                                    'vericode' => $vericode,
                                    'join_vericode_expiry' => $settings->join_vericode_expiry
                                );
                                $encoded_email = rawurlencode($email);
                                $subject = 'Verify Your Email';
                                $body =  email_body('_email_template_verify_new.php', $options);
                                $email_sent = email($email, $subject, $body);
                                if (!$email_sent) {
                                    $errors[] = 'Email NOT sent due to error. Please contact site administrator.';
                                } else {
                                    $successes[] = "Email request received. Please check your email to perform verification. Be sure to check your Spam and Junk folder as the verification link expires in $settings->join_vericode_expiry hours.";
                                }
                                if ($emailR->email_act == 1) {
                                    logger($user->data()->id, "User", "Requested change email from $userdetails->email to $email. Verification email sent.");
                                }
                            }
                        } else {
                            $errors[] = "Your email did not match.";
                        }
                    } else {
                        //validation did not pass
                        foreach ($validation->errors() as $error) {
                            $errors[] = $error;
                        }
                    }
                } else {
                    $email = $userdetails->email;
                }
                if (!empty($_POST['password'])) {
                    $validation->check($_POST, array(
                        'password' => array(
                            'display' => 'New Password',
                            'required' => true,
                            'min' => $settings->min_pw,
                            'max' => $settings->max_pw,
                        ),
                        'confirm' => array(
                            'display' => 'Confirm New Password',
                            'required' => true,
                            'matches' => 'password',
                        ),
                    ));
                    foreach ($validation->errors() as $error) {
                        $errors[] = $error;
                    }
                    if (empty($errors) && Input::get('old') != Input::get('password')) {
                        //process
                        $new_password_hash = password_hash(Input::get('password'), PASSWORD_BCRYPT, array('cost' => 12));
                        $user->update(array('password' => $new_password_hash, 'force_pr' => 0, 'vericode' => randomstring(15),), $user->data()->id);
                        $successes[] = 'Password updated.';
                        logger($user->data()->id, "User", "Updated password.");
                        if ($settings->session_manager == 1) {
                            $passwordResetKillSessions = passwordResetKillSessions();
                            if (is_numeric($passwordResetKillSessions)) {
                                if ($passwordResetKillSessions == 1) {
                                    $successes[] = "Successfully Killed 1 Session";
                                }
                                if ($passwordResetKillSessions > 1) {
                                    $successes[] = "Successfully Killed $passwordResetKillSessions Session";
                                }
                            } else {
                                $errors[] = "Failed to kill active sessions, Error: " . $passwordResetKillSessions;
                            }
                        }
                    } else {
                        if (Input::get('old') == Input::get('password')) {
                            $errors[] = "Your old password cannot be the same as your new";
                        }
                    }
                }
                if (!empty($_POST['resetPin']) && Input::get('resetPin') == 1) {
                    $user->update(['pin' => null]);
                    logger($user->data()->id, "User", "Reset PIN");
                    $successes[] = 'Reset PIN';
                    $successes[] = 'You can set a new PIN the next time you require verification';
                }
            } else {
                $errors[] = "Current password verification failed. Update failed. Please try again.";
            }
        }
    }
}
// mod to allow edited values to be shown in form after update
$user2 = new User();
$userdetails = $user2->data();

// Extend for profile
$userQ2 = $db->query("SELECT * FROM profiles LEFT JOIN users ON user_id = users.id WHERE user_id = ?", array($userId));
if ($userQ2->count() > 0) {
    $profiledetails = $userQ2->first();
} else {
    echo 'USER_SETTING(390) something is wrong with the user profile <br>';
}
// End Extend

?>
<div id="page-wrapper">
    <div class="container">
        <div class="well">
            <div class="row">
                <div class="col-xs-12 col-md-10">
                    <h1>Update your user settings</h1> <br>
                    <?php if (!$errors == '') {
                    ?><div class="alert alert-danger"><?= display_errors($errors); ?></div><?php
                                                                                        } ?>
                    <?php if (!$successes == '') {
                    ?><div class="alert alert-success"><?= display_successes($successes); ?></div><?php
                                                                                                } ?>

                    <form name='updateAccount' action='user_settings.php' method='post'>

                        <div class="form-group">
                            <label>Username</label>
                            <?php if (($settings->change_un == 0) || (($settings->change_un == 2) && ($userdetails->un_changed == 1))) {
                            ?>
                                <div class="input-group">
                                    <input class='form-control' type='text' name='username' value='<?= $userdetails->username ?>' readonly />
                                    <span class="input-group-addon" data-toggle="tooltip" title="<?php if ($settings->change_un == 0) {
                                                                                                    ?>The Administrator has disabled changing usernames.<?php
                                                                                                                                                    }
                                                                                                                                                    if (($settings->change_un == 2) && ($userdetails->un_changed == 1)) {
                                                                                                                                                        ?>The Administrator set username changes to occur only once and you have done so already.<?php
                                                                                                                                                                                                                                                } ?>">Why can't I change this?</span>
                                </div>
                            <?php
                            } else {
                            ?>
                                <input class='form-control' type='text' name='username' value='<?= $userdetails->username ?>'>
                            <?php
                            } ?>
                        </div>

                        <div class="form-group">
                            <label>First Name</label>
                            <input class='form-control' type='text' name='fname' value='<?= $userdetails->fname ?>' />
                        </div>

                        <div class="form-group">
                            <label>Last Name</label>
                            <input class='form-control' type='text' name='lname' value='<?= $userdetails->lname ?>' />
                        </div>
                        <!-- Extend user_setttings.php with some PROFILE information -->
                        <div class="form-group">
                            <label>City</label>
                            <input class='form-control' type='text' name='city' value='<?= $profiledetails->city ?>' />
                        </div>
                        <div class="form-group">
                            <label>State</label>
                            <input class='form-control' type='text' name='state' value='<?= $profiledetails->state ?>' />
                        </div>


                        <div class="form-group">
                            <label>Country <?= $profiledetails->country ?></label>
                            <?php
                            echo "<select name='country'>";
                            echo '<option selected>' . $profiledetails->country . '</option>';
                            foreach ($countrylist as $c) {
                                echo "<option value=\"$c->name\">$c->name</option>";
                            }
                            echo "</select>"; // Closing of list box
                            ?>
                        </div>

                        <div class="form-group">
                            <label>Website</label>
                            <input class='form-control' type='text' name='website' value='<?= $profiledetails->website ?>' />
                        </div>
                        <!-- END Extend user_setttings.php with some PROFILE information -->

                        <div class="form-group">
                            <label>Email</label>
                            <input class='form-control' type='text' name='email' value='<?= $userdetails->email ?>' />
                            <?php if (!IS_NULL($userdetails->email_new)) {
                            ?><br />
                                <div class="alert alert-danger">
                                    <p><strong>Please note</strong> there is a pending request to update your email to <?= $userdetails->email_new ?>.</p>
                                    <p>Please use the verification email to complete this request.</p>
                                    <p>If you need a new verification email, please re-enter the email above and submit the request again.</p>
                                </div><?php
                                    } ?>
                        </div>

                        <div class="form-group">
                            <label>Confirm Email</label>
                            <input class='form-control' type='text' name='confemail' />
                        </div>

                        <div class="form-group">
                            <label>New Password</label>
                            <div class="input-group" data-container="body">
                                <span class="input-group-addon password_view_control" id="addon1"><span class="glyphicon glyphicon-eye-open"></span></span>
                                <input class="form-control" type="password" autocomplete="off" name="password" id="password">
                                <span class="input-group-addon pwpopover" id="addon2" data-container="body" data-toggle="popover" data-placement="top" data-content="<?= $settings->min_pw ?> char min, <?= $settings->max_pw ?> max.">?</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Confirm Password</label>
                            <div class="input-group" data-container="body">
                                <span class="input-group-addon password_view_control" id="addon3"><span class="glyphicon glyphicon-eye-open"></span></span>
                                <input type="password" autocomplete="off" id="confirm" name="confirm" class="form-control">
                                <span class="input-group-addon pwpopover" id="addon4" data-container="body" data-toggle="popover" data-placement="top" data-content="Must match the New Password">?</span>
                            </div>
                        </div>

                        <?php if (!is_null($userdetails->pin)) {
                        ?>
                            <div class="form-group">
                                <label>Reset PIN
                                    <input type="checkbox" id="resetPin" name="resetPin" value="1" /></label>
                            </div>
                        <?php
                        } ?>

                        <div class="form-group">
                            <label>Old Password<?php if (!is_null($userdetails->password)) {
                                                ?>, required for changing password, email, or resetting PIN<?php
                                                                                                        } ?></label>
                            <div class="input-group" data-container="body">
                                <span class="input-group-addon password_view_control" id="addon6"><span class="glyphicon glyphicon-eye-open"></span></span>
                                <input class='form-control' type='password' id="old" name='old' <?php if (is_null($userdetails->password)) {
                                                                                                ?>disabled<?php
                                                                                                        } ?> />
                                <span class="input-group-addon pwpopover" id="addon5" data-container="body" data-toggle="popover" data-placement="top" data-content="Required to change your password">?</span>
                            </div>
                        </div>

                        <input type="hidden" name="csrf" value="<?= Token::generate(); ?>" />
                        <p><input class='btn btn-primary' type='submit' value='Update' /></p>
                        <p><a class="btn btn-info" href="../users/account.php">Cancel</a></p>

                    </form>
                    <?php
                    if (isset($user->data()->oauth_provider) && $user->data()->oauth_provider != null) {
                        echo "<strong>NOTE:</strong> If you originally signed up with your Google/Facebook account, you will need to use the forgot password link to change your password...unless you're really good at guessing.";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div> <!-- /container -->
</div> <!-- /#page-wrapper -->

<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'users/includes/page_footer.php'; // the final html footer copyright row + the external js calls
?>

<!-- Place any per-page javascript here -->
<script>
    $(document).ready(function() {
        $('.password_view_control').hover(function() {
            $('#old').attr('type', 'text');
            $('#password').attr('type', 'text');
            $('#confirm').attr('type', 'text');
        }, function() {
            $('#old').attr('type', 'password');
            $('#password').attr('type', 'password');
            $('#confirm').attr('type', 'password');
        });
    });
    $(function() {
        $('[data-toggle="popover"]').popover()
    })
    $('.pwpopover').popover();
    $('.pwpopover').on('click', function(e) {
        $('.pwpopover').not(this).popover('hide');
    });
</script>

<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer
?>