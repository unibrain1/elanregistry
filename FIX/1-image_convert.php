<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../users/init.php';

// Get the DB
$db = DB::getInstance();


echo "Create Image Table and convert<br>";

// Load pages first because it sets security
$table = '../SQL/create_image_table.sql';

$targetFilePath = $abs_us_root . $us_url_root . 'app/userimages/';
$targetFilePathThumb = $targetFilePath . 'thumbs/';

loadSQL($table);


echo "<br>-- Convert data ...<br>";

// For each car record  TODO
$carData = $db->query('SELECT id,image FROM cars WHERE image != ""')->results();

foreach ($carData as $car) {
    // verify image exists
    $targetFile = $targetFilePath . $car->image;

    if (file_exists($targetFilePath) && is_file($targetFile) && is_writable($targetFilePath)) {

        echo "---- Converting image for car: " . $car->id . " Image: " . $car->image . "<br>";

        // rename to new format
        $mime_type = get_mime_type($targetFile);
        $fileExtension = getExtension($mime_type);

        //  give the file a random name
        $newFileName = uniqid('img_', 'true') . '.' . $fileExtension;
        copy($targetFilePath . $car->image, $targetFilePath . $newFileName);
        copy($targetFilePathThumb . $car->image, $targetFilePathThumb . $newFileName);

        // Create new thumbs

        list($width, $height) = getimagesize($targetFilePath . $newFileName);
        $modwidth = 120;
        $diff = $width / $modwidth;
        $modheight = $height / $diff;
        $tn = imagecreatetruecolor($modwidth, $modheight);
        $image = imagecreatefromjpeg($targetFilePath . $newFileName);
        imagecopyresampled($tn, $image, 0, 0, 0, 0, $modwidth, $modheight, $width, $height);
        imagejpeg($tn, $targetFilePathThumb . $newFileName, 80);

        // Put in DB 
        $db->insert('images', ["carid" => $car->id, "featured" => TRUE, "image" => $newFileName]);
    } else {
        echo "---- Converting image for car: " . $car->id . " Image does not exist:<br>";
    }
}
function loadSQL($filename)
{
    global $db;
    echo "Loading information: " . $filename . "<br>";

    // Temporary variable, used to store current query
    $templine = '';
    // Read in entire file
    $lines = file($filename);
    // Loop through each line
    foreach ($lines as $line) {
        // Skip it if it's a comment
        if (substr($line, 0, 2) == '--' || $line == '') {
            continue;
        }

        // Add this line to the current segment
        $templine .= $line;
        // If it has a semicolon at the end, it's the end of the query
        if (substr(trim($line), -1, 1) == ';') {
            // Perform the query
            $db->query($templine) || print('Error performing query \'<strong>' . $templine . '\': ' . mysqli_error() . '<br /><br />');
            // Reset temp variable to empty
            $templine = '';
        }
    }
    echo "$filename imported successfully<br>";
}


function getExtension($mime_type)
{
    $extensions = array(
        'image/jpeg' => 'jpg'
    );
    return $extensions[$mime_type];
}
function get_mime_type($file)
{
    $mtype = false;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mtype = finfo_file($finfo, $file);
        finfo_close($finfo);
    } elseif (function_exists('mime_content_type')) {
        $mtype = mime_content_type($file);
    }
    return $mtype;
}
