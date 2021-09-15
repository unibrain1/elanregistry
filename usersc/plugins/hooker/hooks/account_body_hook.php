<?php
if (count(get_included_files()) == 1) die(); //Direct Access Not Permitted Leave this line in place

global $user;

// Get some interesting user information to display later

$user_id = $user->data()->id;

// USER ID is in $user_id .  Use the USER ID to get the users Profile information
$userQ = $db->query("SELECT * FROM usersview WHERE id = ?", array($user_id));
if ($userQ->count() > 0) {
    $thatUser = $userQ->results();
}

$signupdate = new DateTime($thatUser[0]->join_date);
$lastlogin = new DateTime($thatUser[0]->last_login);

?>

<div class="card card-default">
    <div class="card-header">
        <h2><strong>Account Information</strong></h2>
    </div>
    <div class="card-body">
        <table id="accounttable" class="table table-striped table-bordered table-sm" aria-describedby="card-header">
            <tr>
                <th scope=column><strong>First name : </strong></th>
                <th scope=column><?= ucfirst($thatUser[0]->fname) ?></th>
            </tr>
            <tr>
                <td><strong>Last name : </strong></td>
                <td><?= ucfirst($thatUser[0]->lname) ?></td>
            </tr>
            <tr>
                <td><strong>Email : </strong></td>
                <td><?= $thatUser[0]->email ?></td>
            </tr>
            <tr>
                <td><strong>City : </strong></td>
                <td><?= html_entity_decode($thatUser[0]->city); ?></td>
            </tr>
            <tr>
                <td><strong>State : </strong></td>
                <td><?= html_entity_decode($thatUser[0]->state); ?></td>
            </tr>
            <tr>
                <td><strong>Country : </strong></td>
                <td><?= html_entity_decode($thatUser[0]->country); ?></td>
            </tr>
            <tr>
                <td><strong>Member Since : </strong></td>
                <td><?= $signupdate->format("Y-m-d") ?></td>
            </tr>
            <tr>
                <td><strong>Last Login : </strong></td>
                <td><?= $lastlogin->format("Y-m-d") ?></td>
            </tr>
            <tr>
                <td><strong>Number of Logins: </strong></td>
                <td><?= $thatUser[0]->logins ?></td>
            </tr>

            <tr>
                <td colspan="2"><a class="btn btn-success" href=<?= $us_url_root . "users/user_settings.php" ?>>Update Account Info</a>
                </td>
            </tr>
        </table>

    </div>
</div>


<script>
    // Remove/ things in the master that I don't want
    $('.row .col-md-3 img').remove(); // Avitar
    $('.row .col-md-3 a').first().remove(); // Edit Button

    $('.col-sm-12.col-md-3').addClass('col-md-4 col-xs-12').removeClass('col-sm-12 col-md-3');
</script>