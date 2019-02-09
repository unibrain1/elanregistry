<?php
// This file often teams up with during_user_creation.php although you can use that file without this one.
// However, if you add additional form fields here, you should process them there.
// We will do an example. Let's say you want to make use of the unused account_id column in the users table.

// Uncomment out the code below and it will automagically be inserted into your join form.
?>
<!-- <label for="confirm">Pick an account ID number</label>
<input type="number" class="form-control" min="0" step="1" name="account_id" value="" required> -->
<?php

// Get the country list

$countryQ = $db->query("SELECT name FROM country");
 if ($countryQ->count() > 0) {
    $countrylist = $countryQ->results();
}
?>


<label for="confirm">City</label>
<input type="text" class="form-control" name="city"  size="20" maxlength="20"value="" required>

<label for="confirm">State</label>
<input type="text" class="form-control" name="state" value="" size="20" maxlength="20" required>

<label for="confirm">Country</label>
<!-- <input type="text" class="form-control" name="country" value="" size="20" maxlength="20" required> -->
<?php
echo "<select name='country'>";
echo "<option value='country'</option>";
foreach ($countrylist as $c) {
    echo "<option value=\"$c->name\">$c->name</option>";
}
echo "</select>";// Closing of list box 
?>
</br>


<?php
//Now, go into the during_user_creation script to see how to process it.

 ?>
