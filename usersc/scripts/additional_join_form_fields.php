<?php
// This file often teams up with during_user_creation.php although you can use that file without this one.
// However, if you add additional form fields here, you should process them there.
// We will do an example. Let's say you want to make use of the unused account_id column in the users table.

// Uncomment out the code below and it will automagically be inserted into your join form.

// Get the country list
$countryQ = $db->query("SELECT name FROM country");
if ($countryQ->count() > 0) {
    $countrylist = $countryQ->results();
}

?>

<div class="form-group">
    <label for="city" id="city-label">City *</label>
    <div class="input-group-prepend">
        <input class="form-control" type="text" name="city" id="city" placeholder="Enter your City" value="<?php if (!$form_valid && !empty($_POST)) {
                                                                                                                echo $city;
                                                                                                            } ?>" required autocomplete="address-level2">
    </div>
</div>

<div class="form-group">
    <label for="state" id="state-label">State/Province *</label>
    <div class="input-group-prepend">
        <input class="form-control" type="text" name="state" id="state" placeholder="Enter your State/Province" value="<?php if (!$form_valid && !empty($_POST)) {
                                                                                                                            echo $state;
                                                                                                                        } ?>" required autocomplete="address-level1">
    </div>
</div>

<div class="form-group">
    <label for="country" id="country-label">Country *</label>
    <div class="input-group-prepend">
        <select class="form-control" id="country" name="country" required>
            <?php
            if (!$form_valid && !empty($_POST)) {
                echo "<option selected value=\"$country\">$country</option>";
            } else {
                echo '<option value="">Select Country</option>';
            }
            foreach ($countrylist as $c) {
                echo "<option value=\"$c->name\">$c->name</option>";
            } ?>
        </select>
    </div>
</div>




<?php
//Now, go into the during_user_creation script to see how to process it.
?>