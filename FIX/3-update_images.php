<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../users/init.php';

$errors = "";
// Get the DB
$db = DB::getInstance();

$sizes = [100, 300, 600, 1024, 2048];

$memory_limit = return_bytes(ini_get('memory_limit'));
$memory_limit = $memory_limit * 0.19;

$execution_time_limit = 180;
set_time_limit($execution_time_limit);

$img_root = $abs_us_root . $us_url_root . 'app/userimages/';

$tmpimages = glob($img_root . '*.{jpg,jpeg,png}', GLOB_BRACE);


$images = array_values(preg_grep('-resized-', $tmpimages, PREG_GREP_INVERT));

$total = count($images);

?>

<style>
    .error {
        color: red;
        font-weight: bold;
    }
</style>


</style>

<h2>Create resized images<br></h2>
<p>Updating <?= $total ?> images. Time limit is <?= $execution_time_limit ?> secs</p>
<?php
echo date("h:i:sa");
?>
<p>
    <u>Progress</u>
</p>

<?php

foreach ($images as $key => $image) {
    $imageinfo = getimagesize($image);
    $width = $imageinfo[0];
    $height = $imageinfo[1];

    outputProgress($key + 1, $total);


    $fileinfo = pathinfo($image);
    $filename = $fileinfo['filename'];
    $extension = $fileinfo['extension'];

    $imgsize = $width * $height;

    if ($imgsize > $memory_limit) {
        $errors .= "<br><span class='error'> $filename too large is " . number_format($imgsize) . " - Lim is " . number_format($memory_limit) . "</span><br>";
    } else {
        foreach ($sizes as $size) {
            $newname = $img_root . $filename . "-resized-" . $size . "." . $extension;

            if (!file_exists($newname)) {
                $resizeObj = new Resize($image);
                $resizeObj->resizeImage($size, $size, 'auto');
                $resizeObj->saveImage($newname, 80);
            }
        }
    }
}

?>

<h3><br><br><br><br>Complete</h3>

<?php


function return_bytes($val)
{
    $val = trim($val);
    $last = strtolower($val[strlen($val) - 1]);
    $val = substr($val, 0, -1);

    switch ($last) {
            // The 'G' modifier is available since PHP 5.1.0
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
        case default:
            break;
    }

    return $val;
}

function outputProgress($current, $total)
{
    global $errors;

    echo "<span style='position: absolute;z-index:$current;background:#FFF;'>" .
        " " . date('h:i:sa') .
        " - Current " . $current . " of " . $total .
        " - " . round($current / $total * 100, 2) . " %<br>" . $errors . "</span>";
    myFlush();
}

/**
 * Flush output buffer
 */
function myFlush()
{
    echo str_repeat(' ', 256);
    if (@ob_get_contents()) {
        @ob_end_flush();
    }
    flush();
}
