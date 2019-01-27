<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
UserSpice Initialization`
*/
?>
<?php
require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/header.php';
require_once $abs_us_root.$us_url_root.'users/includes/navigation.php';
?>


<?php if (!securePage($_SERVER['PHP_SELF'])) {
    die();
} ?>

<?php
    //PHP Goes Here!
    // Always handy to know who I am
    $user_id = $user->data()->id;
    
    // A place to store default values and what the user entered
    $select_str = 'Please Select';
    
    // It's a car add
    $cardetails['id']          = null;
    $cardetails['year']        = $select_str;
    $cardetails['model']       = $select_str;
    $cardetails['chassis']     = null;
    $cardetails['color']       = null;
    $cardetails['engine']      = null;
    $cardetails['purchasedate']= null;
    $cardetails['solddate']    = null;
    $cardetails['comments']    = null;
    $cardetails['image']       = null;
    
    // Holding place before processing
    $fields['id']          = null;
    $fields['year']        = null;
    $fields['model']       = null;
    $fields['series']      = null;
    $fields['variant']     = null;
    $fields['type']        = null;
    $fields['chassis']     = null;
    $fields['color']       = null;
    $fields['engine']      = null;
    $fields['purchasedate']= null;
    $fields['solddate']    = null;
    $fields['comments']    = null;
    $fields['image']       = null;
    
    // 'placeholder' to prompt for response.  Background text in input boxes
    $carprompt['chassis']     = 'Enter Chassis Number - Pre 1970 - xxxx, 1970 and on 70xxyy0001z';
    $carprompt['color']       = 'Enter the current color of the car';
    $carprompt['engine']      = 'Enter Engine number - LPAxxxxx';
    $carprompt['comments']    = 'Please give a brief history of your car and anything special';
    
    // A place to put some messages
    $errors=[];
    $successes=[];
    
    // A place to put my fields
    $fields=[];
    
    //Temporary Success Message
    $holdover = Input::get('success');
    if ($holdover == 'true') {
        bold("Account Updated");
    }
    //Forms posted now process it
    //
    
    if (!empty($_POST)) {
        $token = $_POST['csrf'];
        if (!Token::check($token)) {
            include($abs_us_root.$us_url_root.'usersc/scripts/token_error.php');
        } else {
    
            //Update id
            $id = ($_POST['car_id']);
            $fields['id'] = Input::sanitize($id);
    
            //Update Year
            $year = ($_POST['year']);
            if (strcmp($year, $select_str)) {
                $fields['year'] = Input::sanitize($year);
                $successes[]='Year Updated - '.$fields['year'];
            } else {
                $errors[] = "Please select Year";
            }
    
            // Update 'model'
            //
            $model = ($_POST['model']);
            $model = Input::sanitize($model);
            if (strcmp($model, $select_str)) {
                // Model isn't really a thing.
                //      We need to explode it into the proper columns
                $fields['model'] = Input::sanitize($model);  // Still save it for later so we can remember what the user entered
                list($series, $variant, $type) = explode('|', $model);
                /* MST value is from form, so I shouldn't have to do this but to be safe ... */
                $fields['series'] = filter_var($series, FILTER_SANITIZE_STRING);
                $fields['variant']= filter_var($variant, FILTER_SANITIZE_STRING);
                $fields['type']   = filter_var($type, FILTER_SANITIZE_STRING);
    
                $successes[]='Model Updated -'.$fields['model'];
            } else {
                $errors[] = "Please select Model";
            }
    
            // Update 'chassis'
            $chassis = ($_POST['chassis']);
            $len = strlen($chassis);
            $fields['chassis'] = Input::sanitize($chassis);
            if (strcmp($fields['variant'], 'Race') == 0) { /* For the 26R let them do what they want */
                $successes[]='Chassis Updated -'.$fields['chassis'];
            } elseif ($year < 1970) {
                if ($len != 4) { // Chassis number for years < 1970 are 4 digits
                    $errors[] = "Enter Chassis Number. Four Digits,6490 not 36/6490";
                }
            // } elseif ($len != 11) { 	// Chassis number for years >= 1970 are 11 digits
            //     $errors[] = "Enter Chassis Number. 70xxyy0001z";
            } else {
                $successes[]='Chassis Updated -'.$fields['chassis'];
            }
    
            // Update 'color'
            $color = ($_POST['color']);
            $fields['color'] = Input::sanitize($color);
            $successes[]='Color Updated -'.$fields['color'];
    
            // Update 'engine'
            $engine = ($_POST['engine']);
            $engine = Input::sanitize($engine);
            $fields['engine'] = filter_var(str_replace(" ", "", strtoupper(trim($engine))), FILTER_SANITIZE_STRING);
            $successes[]='Engine Updated -'.$fields['engine'];
    
            // Update 'purchasedate'
            if($_POST['purchasedate'] != '')
            {
                $purchasedate = ($_POST['purchasedate']);
                $purchasedate = Input::sanitize($purchasedate);
                // Convert to SQL date format
                if ($purchasedate = date("Y-m-d H:i:s", strtotime($purchasedate))) {
                    $fields['purchasedate'] = filter_var($purchasedate, FILTER_SANITIZE_STRING);
                    $successes[]='Purchased Updated -'.$fields['purchasedate'];
                } else {
                    $errors[] = "Purchase Date conversion error";
                }
            }else {
                $fields['purchasedate'] = '0000-00-00';
            }
    
            // Update 'solddate'
            if($_POST['solddate'] != '')
            {
                $solddate = ($_POST['solddate']);
                $solddate = Input::sanitize($solddate);
                if ($solddate = date("Y-m-d H:i:s", strtotime($solddate))) {
                    $fields['solddate'] = filter_var($solddate, FILTER_SANITIZE_STRING);
                    $successes[]='Sold Date Updated -'.$fields['solddate'] ;
                } else {
                    $errors[] = "Sold Date conversion error";
                }
            } else {
                $fields['solddate'] = '0000-00-00';
            }
    
            // Update 'comments'
            $comments = ($_POST['comments']);
            $comments = Input::sanitize($comments);
            $fields['comments'] = filter_var($comments, FILTER_SANITIZE_STRING);
            $successes[]='Comment Updated';
            //
            // Update 'image'

            if (isset($_FILES['uploadedFile']) && $_FILES['uploadedFile']['error'] === UPLOAD_ERR_OK) {
                $successes[]='in Image Upload';

                // get details of the uploaded file
                $fileTmpPath = $_FILES['uploadedFile']['tmp_name'];
                $fileName = $_FILES['uploadedFile']['name'];
                $fileSize = $_FILES['uploadedFile']['size'];
                $fileType = $_FILES['uploadedFile']['type'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));

                // sanitize file-name and give it a random name
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;

                // check if file has one of the following extensions
                $allowedfileExtensions = array('jpg', 'gif', 'png');

                if (in_array($fileExtension, $allowedfileExtensions)) {
                    // directory in which the uploaded file will be moved
                    $uploadFileDir = './userimages/';
                    $thumbnailDir = $uploadFileDir . 'thumbs/';
                    $dest_path = $uploadFileDir . $newFileName;
                    $thumb_dest_path = $thumbnailDir . $newFileName;


                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        $successes[] ='Image is successfully uploaded.' . $newFileName;

                        // Resize the image

                        list($width, $height) = getimagesize($dest_path) ;

                        $modwidth = 600;  // Only 600 wide

                        $diff = $width / $modwidth;

                        $modheight = $height / $diff;
                        $tn = imagecreatetruecolor($modwidth, $modheight) ;
                        $image = imagecreatefromjpeg($dest_path) ;
                        imagecopyresampled($tn, $image, 0, 0, 0, 0, $modwidth, $modheight, $width, $height) ;

                        imagejpeg($tn, $dest_path, 100) ;
                        $successes[]='Image Resized';

                        // Create a thumbnail
                        list($width, $height) = getimagesize($dest_path) ;
                        $modwidth = 80;

                        $diff = $width / $modwidth;

                        $modheight = $height / $diff;
                        $tn = imagecreatetruecolor($modwidth, $modheight) ;
                        $image = imagecreatefromjpeg($dest_path) ;
                        imagecopyresampled($tn, $image, 0, 0, 0, 0, $modwidth, $modheight, $width, $height) ;

                        imagejpeg($tn, $thumb_dest_path, 80) ;
                        $successes[]='Image Thumbnail';

                        $fields['image'] = $newFileName;
                    } else {
                        $errors[] = 'There was some error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
                    }
                } else {
                    $errors[] = 'Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions);
                }
            } 
            
            // If there are no errors then INSERT the $fields into the DB,
            if (empty($errors)) {
                // Add a create time
                $fields['ctime'] = date('Y-m-d G:i:s');
                
                // Make sure user owns the car before update

                if ($db->get('car_user', ['AND',  ['userid','=',$user_id], ['carid','=',$fields['id']]  ])) {
                    // Owner
                    $db->update('cars', $fields['id'], $fields);
                    if ($db->error()) {
                        $errors[]='DB ERROR'.$db->errorString();
                        logger($user->data()->id, "User", "edit_car error car ".$db->errorString());
                    } else {
                        // Grab the id of the last insert
                        $successes[]='Update Car ID: '.$fields['id'];
                        // then log it
                        logger($user->data()->id, "User", "Updated car ID ". $fields['id']);

                        // then redirect to User Account Page
                        Redirect::to($us_url_root.'users/account.php');
                    }
                } else {
                    $errors[]='DB Abort'.print_r($errors);
                }
            }
        } // End Post with data
    } // End Post
    
    if (!empty($_GET)) {
        // Did someone pass me a car?
        $car_id = $_GET['car_id'];
    
        if (!empty($car_id)) {
        
        //  Let's check to see if this user owns this car`
            $userQ = $db->get('car_user', ['AND',  ['userid','=',$user_id], ['carid','=',$car_id]  ]);
            
            if ($userQ->count() > 0) {
                // Owner
                $carQ =  $db->get("cars", ["id",  "=",$car_id]);
                $theCar = $carQ->results();

                // Get the details from the current car
                $cardetails['id']          = $theCar[0]->id;
                $cardetails['year']        = $theCar[0]->year;
                $cardetails['model']       = $theCar[0]->model;
                $cardetails['chassis']     = $theCar[0]->chassis;
                $cardetails['color']       = $theCar[0]->color;
                $cardetails['engine']      = $theCar[0]->engine;
                $cardetails['purchasedate']= $theCar[0]->purchasedate;
                $cardetails['solddate']    = $theCar[0]->solddate;
                $cardetails['comments']    = $theCar[0]->comments;
                $cardetails['image']       = $theCar[0]->image;
            } else {
                // This should never happen unless the user is trying to do something bad.  Log it and then log them out
                logger($user->data()->id, "User", "Not owner of car! USER ". $user_id. " CAR ". $car_id);
                $user->logout();
                 Redirect::to($us_url_root.'index.php');

                exit();
            }
        } else { /* Empty Car */
            logger($user->data()->id, "User", "Empty car_id field in GET");

            Redirect::to($us_url_root.'users/account.php');
        } // empty $car_id
    }// $_GET
        
    // mod to allow edited values to be shown in form after update
    if (isset($fields['id'])) {
        $cardetails['id']  = $fields['id'];
    }
    if (isset($fields['year'])) {
        $cardetails['year']  = $fields['year'];
    }
    if (isset($fields['model'])) {
        $cardetails['model'] = $fields['model'];
    }
    if (isset($fields['chassis'])) {
        $cardetails['chassis'] = $fields['chassis'];
    }
    if (isset($fields['color'])) {
        $cardetails['color'] = $fields['color'];
    }
    if (isset($fields['engine'])) {
        $cardetails['engine'] = $fields['engine'];
    }
    if (isset($fields['purchasedate'])) {
        $cardetails['purchasedate'] = $fields['purchasedate'];
    }
    if (isset($fields['solddate'])) {
        $cardetails['solddate'] =  $fields['solddate'];
    }
    if (isset($fields['comments'])) {
        $cardetails['comments'] = $fields['comments'];
    }
    if (isset($fields['image'])) {
        $cardetails['image'] = $fields['image'];
    }
?>
	
<div id="page-wrapper">
    <div class="container">
        <div class="well">
            <div class="row">

                <div class="col-xs-12 col-md-10">
                    <h1>Update a Car</h1> </br>
					<?php if (!$errors=='') {
    ?><div class="alert alert-danger"><?=display_errors($errors); ?></div><?php
} ?>
					<?php if (!$successes=='') {
        ?><div class="alert alert-success"><?=display_successes($successes); ?></div><?php
    } ?>
					
	<!-- Here is the FORM -->

	<form name="addCar" action="edit_car.php" method="POST" enctype="multipart/form-data">
	<label>Car ID:</label>  <?php echo $cardetails['id']?></br>
    <label>User ID:</label>  <?php echo $user_id?></br>

   

   	<input type="hidden" name="car_id" value="<?=$cardetails['id']?>" />
		
		<div class="form-group">
			<label>Year</label>
		
   			<select name="year" onchange="populateSub(this, this.form.model);">
				<option selected><?=$cardetails['year']?></option>
				<option>1963</option> <option>1964</option> <option>1965</option> <option>1966</option>
				<option>1967</option> <option>1968</option> <option>1969</option> <option>1970</option>
				<option>1971</option> <option>1972</option> <option>1973</option> <option>1974</option>
			</select>
		</div>
                 
        <div class="form-group">
			<label>Model</label>
		
			<select name="model">
				<option selected><?=$cardetails['model']?></option>
				<option value="">--Please Choose--</option>
			</select>
		</div>


		<div class="form-group">
			<label>Chassis</label>
				<input  class='form-control' type='text' name='chassis' placeholder='<?=$carprompt['chassis']?>' value='<?=$cardetails['chassis']?>' />
		</div>
		
		<div class="form-group">
			<label>Color</label>
				<input  class='form-control' type='text' name='color' placeholder='<?=$carprompt['color']?>' value='<?=$cardetails['color']?>' />
		</div>

		<div class="form-group">
			<label>Engine Number</label>
				<input  class='form-control' type='text' name='engine' placeholder='<?=$carprompt['engine']?>' value='<?=$cardetails['engine']?>' />
		</div>
 
 		<div class="form-group">
			<label>Purchase Date</label>
                 <script>
                  $( function() {
                    $( "#datepicker1" ).datepicker({
                        showOn: "button",
                        showButtonPanel: true,
                        buttonText: "<i class='fa fa-calendar-check-o'></i>"
                    });
                    $( "#datepicker1" ).datepicker( "option", "dateFormat", "yy-mm-dd"  );

                        <?php
                         if( $cardetails['purchasedate'] == '0000-00-00' )
                         {
                            ?>$( "#datepicker1" ).datepicker( "setDate", NULL);<?php
                         } else {
                             ?>$( "#datepicker1" ).datepicker( "setDate", "<?php echo $cardetails['purchasedate']; ?>");<?php
                         }

                        ?>
                  } );
                </script>

                <p><input type="text" name='purchasedate' id="datepicker1"  size="30"></p>
		</div>                      
  
 		<div class="form-group">
			<label>Sold Date</label>
                <script>
                  $( function() {
                    $( "#datepicker2" ).datepicker({
                        showOn: "button",
                        showButtonPanel: true,
                        buttonText: "<i class='fa fa-calendar-check-o'></i>"
                    });
                    $( "#datepicker2" ).datepicker( "option", "dateFormat", "yy-mm-dd"  );

                     <?php
                         if( $cardetails['solddate'] == '0000-00-00' )
                         {
                            ?>$( "#datepicker2" ).datepicker( "setDate", NULL);<?php
                         } else {
                            ?>$( "#datepicker2" ).datepicker( "setDate", "<?php echo $cardetails['solddate']; ?>");<?php
                         }

                    ?>

                  } );
                </script>
		       <p><input type="text" name='solddate' id="datepicker2" size="30"></p>
        </div> 

 		<div class="form-group">
			<label>Comment</br></label> </br>
		</div> 
				<textarea name='comments' rows='10' cols='60' wrap='virtual' /> <?php
                    if (is_null($cardetails['comments'])) {
                        echo htmlspecialchars($carprompt['comments']);
                    } else {
                        echo htmlspecialchars($cardetails['comments']);
                    } ?>
				 </textarea>

        <!-- Form for the 'image' -->
        <!-- Add some space -->
         </br>
        <div class="form-group">
            <label>Upload/Replace Picture: </br></label> </br>
        </div> 
        <div>
          <input type="file" name="uploadedFile" />
        </div> 
         <!-- Add some space -->
         </br>
        <img src=<?=$us_url_root?>app/userimages/<?=$cardetails['image']?> width='390'>

        <!-- Add some space -->
         </br></br>

<!-- And last some buttons -->
    	<input type="hidden" name="csrf" value="<?=Token::generate();?>" />

		<p><input class='btn btn-primary' type='submit' value='Update' class='submit' /></p>
		<p><a class="btn btn-info" href=<?$us_url_root?>."/users/account.php">Cancel</a></p> 
	</form>
	
					<!-- Content Goes Here. Class width can be adjusted -->
					<?php
                    ?>
					<!-- End of main content section -->
			</div> <!-- /.col -->
		</div> <!-- /.row -->
	</div> <!-- /.container -->
</div> <!-- /.wrapper -->


	<!-- footers -->
<?php require_once $abs_us_root.$us_url_root.'users/includes/page_footer.php'; // the final html footer copyright row + the external js calls?>

<!-- Place any per-page javascript here -->

<?php require_once $abs_us_root.$us_url_root.'users/includes/html_footer.php'; // currently just the closing /body and /html?>
