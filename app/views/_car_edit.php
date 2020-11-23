<div class="card card-default">
    <div class="card-header">
        <h2><strong><?= $title ?></strong></h2>
    </div>
    <div class="card-body">
        <!-- Here is a place to display errors -->
        <?php if (!$errors == '') { ?><div class="alert alert-danger"><?= display_errors($errors); ?></div><?php } ?>
        <?php if (!$successes == '') { ?><div class="alert alert-success"><?= display_successes($successes); ?></div><?php } ?>

        <?php
        if (isset($cardetails['id'])) { ?>
            <div class="form-group row">
                <label for="year" class="col-3 col-form-label">Car ID</label>
                <div class="col-sm-9">
                    <div class="input-group-prepend">
                        <?= $cardetails['id'] ?>
                    </div>
                </div>
            </div>
        <?php } ?>

        <!-- Year -->
        <div class="form-group row">
            <label for="year" class="col-3 col-form-label">Year *</label>
            <div class="col-sm-9">
                <div class="input-group-prepend">
                    <div class="input-group-text"><i aria-hidden="true" class="fas fa-calendar-check"></i> </div>
                    <select required name='year' id='year' class='custom-select form-control'>
                        <option value="">--Choose Year--</option>
                        <option value="1963">1963</option>
                        <option value="1964">1964</option>
                        <option value="1965">1965</option>
                        <option value="1966">1966</option>
                        <option value="1967">1967</option>
                        <option value="1968">1968</option>
                        <option value="1969">1969</option>
                        <option value="1970">1970</option>
                        <option value="1971">1971</option>
                        <option value="1972">1972</option>
                        <option value="1973">1973</option>
                        <option value="1974">1974</option>
                    </select>
                    <div class='input-group-text'><i id="year_icon" aria-hidden='true' class="fas fa-thumbs-down"></i></div>
                </div>
            </div>
        </div>

        <!-- Model -->
        <div class="form-group row">
            <label for="model" class="col-3 col-form-label">Model *</label>
            <div class="col-sm-9">
                <div class="input-group-prepend">
                    <div class="input-group-text"><i aria-hidden="true" class="fas fa-car-side"></i></div>
                    <select required disabled class="form-control custom-select" name="model" id="model">
                        <option value="">--Please Select Model--</option>
                    </select>
                    <div class='input-group-text'><i id="model_icon" aria-hidden='true' class="fas fa-thumbs-down "></i></div>
                </div>
            </div>
        </div>

        <!-- Chassis -->
        <div class="form-group row">
            <label for="chassis" class="col-3 col-form-label">Chassis *</label>
            <div class="col-sm-9">
                <div class="input-group-prepend">
                    <div class="input-group-text"><i aria-hidden="true" class="fas fa-barcode"></i></div>
                    <input required disabled class="form-control" type="text" name="chassis" id="chassis" placeholder="<?= $carprompt['chassis'] ?>" value="<?= $cardetails['chassis'] ?>" /> <!-- Add some validation -->
                    <div class='input-group-text'><i id="chassis_icon" aria-hidden='true' class="fas fa-thumbs-down "></i></div>
                </div>
                <div id="chassis_taken" style="color: red" class="hidden">
                    <strong>This chassis number is already in the registry. </strong>
                    <br> Please contact the Registrar<br>
                </div>
                <div id="chassis_pre1970" class="hidden">
                    <strong>Before 1970</strong><br>The chassis number should be 4 digits. Do not enter the type (i.e. 26/0001 enter 0001)<br>
                </div>
                <div id="chassis_1970" class="hidden">
                    <strong>1970</strong><br>The chassis can have two forms<br>
                    <ul>
                        <li>4 Digits plus letter - Do not enter the type (i.e. 26/0001x enter 0001x)</li>
                        <li>11 digits starting with the Year (i.e. YYmmbbssssT)</li>
                        <ul>
                            <li>YY = 2 digit year</li>
                            <li>mm = month</li>
                            <li>bb = batch numner</li>
                            <li>uuuu = unit number</li>
                            <li>T = Type Letter</li>
                        </ul>
                    </ul>
                </div>
                <div id="chassis_post1970" class="hidden">
                    <strong>After 1970</strong><br>The Chassis number is 11 digits starting with the Year (i.e. YYmmbbssssT)<br>
                    <ul>
                        <li>YY = 2 digit year</li>
                        <li>mm = month</li>
                        <li>bb = batch numner</li>
                        <li>uuuu = unit number</li>
                        <li>T = Type Letter</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Color -->
        <div class="form-group row">
            <label for="color" class="col-3 col-form-label">Color</label>
            <div class="col-sm-9">
                <div class="input-group-prepend">
                    <div class="input-group-text"><i aria-hidden="true" class="fas fa-palette"></i></div>
                    <input disabled class="form-control" type="text" name="color" id="color" placeholder="<?= $carprompt['color'] ?>" value="<?= $cardetails['color'] ?>" />
                </div>
            </div>
        </div>

        <!-- Engine Number -->
        <div class="form-group row">
            <label for="engine" class="col-3 col-form-label">Engine Number</label>
            <div class="col-sm-9">
                <div class="input-group-prepend">
                    <div class="input-group-text"><i aria-hidden="true" class="fas fa-car"></i> </div>
                    <input disabled class="form-control" type="text" name="engine" id="engine" placeholder="<?= $carprompt['engine'] ?>" value="<?= $cardetails['engine'] ?>" /> <!-- Add validation -->
                </div>
            </div>
        </div>

        <!-- Purchase Date  -->
        <div class="form-group row">
            <label for="purchasedate" class="col-3 col-form-label">Purchase Date</label>
            <div class="col-sm-9">
                <div class="input-group-prepend">
                    <div class="input-group-text"> <i aria-hidden="true" class="fas fa-calendar"></i></div>
                    <input disabled class="form-control" name="purchasedate" id="purchasedate" placeholder="<?= $carprompt['purchasedate'] ?>" value="<?= $cardetails['purchasedate'] ?>" type="text" />
                </div>
                <small id="purchaseHelp" class="form-text text-muted">Approximate date you purchased the car</small>
            </div>
        </div>

        <!-- Sold Date -->
        <div class="form-group row">
            <label for="solddate" class="col-3 col-form-label">Sold Date</label>
            <div class="col-sm-9">
                <div class="input-group-prepend">
                    <div class="input-group-text"><i aria-hidden="true" class="fas fa-calendar"></i></div>
                    <input disabled class="form-control" name="solddate" id="solddate" placeholder="<?= $carprompt['solddate'] ?>" value="<?= $cardetails['solddate'] ?>" type="text" />
                </div>
                <small id="purchaseHelp" class="form-text text-muted">If you no longer own the car, approximate date you sold the car</small>
            </div>
        </div>

        <!-- Website -->
        <div class="form-group row">
            <label for="website" class="col-3 col-form-label">Website</label>
            <div class="col-sm-9">
                <div class="input-group-prepend">
                    <div class="input-group-text"><i aria-hidden="true" class="fas fa-palette"></i></div>
                    <input disabled class="form-control" type="url" name="website" id="website" placeholder="<?= $carprompt['website'] ?>" value="<?= $cardetails['website'] ?>" />
                </div>
            </div>
        </div>

        <!-- Comments -->
        <div class="form-group row">
            <label for="comments" class="col-3 col-form-label">Comments</label>
            <div class="col-sm-9">
                <div class="input-group-prepend">
                    <div class="input-group-text"><i aria-hidden="true" class="fas fa-comment-alt"></i></div>
                    <textarea disabled class="form-control" name="comments" id="comments" rows="10" wrap="soft" placeholder="<?= $carprompt['comments'] ?>"><?= htmlspecialchars($cardetails['comments']); ?></textarea>
                </div>
            </div>
        </div>
    </div> <!-- card-body -->
</div>