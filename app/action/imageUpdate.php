<?php // Allowed file types. This should also be reflected in getExtension

// Check to see if the chassis number is taken
require_once '../../users/init.php';
// Get the DB
$db = DB::getInstance();

$targetFilePath = $abs_us_root . $us_url_root . 'app/userimages/';
$targetURL = $us_url_root . 'app/userimages/';
$targetFilePathThumb = $targetFilePath . 'thumbs/';


/* Dropzone PHP file upload/delete */

if (isset($_POST['command'])) {
    $command = $_POST['command'];
} else {
    $response = array(
        'status' => 'error',
        'info'   => 'Invalid command'
    );
    logger($user->data()->id, "ElanRegistry", "imageUpdate Status: " . $response['status'] . " Info: " . $response['info']);

    echo json_encode($response);
    exit;
}

switch ($command) {
    case 'upload':
        $carID = Input::get('carID');
        if (!empty($_FILES)) {
            upload($_FILES, Input::get('carID'));
        }
        break;
    case 'delete':
        remove(Input::get('target_file'), Input::get('carID'));
        break;
    case 'fetch':
        fetch(Input::get('carID'));
        break;

    default:
        $response = array(
            'status' => 'error',
            'info'   => 'Invalid command'
        );
        logger($user->data()->id, "ElanRegistry", "imageUpdate Status: " . $response['status'] . " Info: " . $response['info']);

        echo json_encode($response);
        exit;
}

function upload($files, $carID)
{
    global $targetFilePath;
    global $targetFilePathThumb;
    global $db;
    global $user;

    // Allowed file types.  This should also be reflected in getExtension
    $allowed_file_types = ['image/jpeg'];

    // Check if the upload folder is exists
    if (file_exists($targetFilePath) && is_dir($targetFilePath) && is_writable($targetFilePath)) {
        $tempFile = $files['file']['tmp_name'];

        // Create a filename for the new file
        $mime_type = get_mime_type($tempFile);
        $fileExtension = getExtension($mime_type);
        //  give the file a random name
        $newFileName = uniqid('img_', 'true') . '.' . $fileExtension;
        $targetFile = $targetFilePath . $newFileName;
        $targetFileThumb = $targetFilePathThumb . $newFileName;

        if (in_array($mime_type, $allowed_file_types)) {
            if (move_uploaded_file($tempFile, $targetFile)) {
                // Create a thumbnail
                list($width, $height) = getimagesize($targetFile);
                $modwidth = 120;
                $diff = $width / $modwidth;
                $modheight = $height / $diff;
                $tn = imagecreatetruecolor($modwidth, $modheight);
                $image = imagecreatefromjpeg($targetFile);
                imagecopyresampled($tn, $image, 0, 0, 0, 0, $modwidth, $modheight, $width, $height);
                imagejpeg($tn, $targetFileThumb, 80);

                // Put in DB TODO

                $db->insert('images', ["carid" => $carID, "featured" => false, "image" => $newFileName]);

                if ($db->error()) {
                    $response = array(
                        'status' => 'error',
                        'info'   => 'Couldn\'t add the DB record a mysterious error happend.'
                    );
                    logger($user->data()->id, "ElanRegistry", "imageUpdate carID: " . $response['carID'] . " Status: " . $response['status'] . " Info: " . $response['info']);
                    echo json_encode($response);
                    exit;
                }

                $response = array(
                    'status'    => 'success',
                    'info'      => 'Your photo has been uploaded.',
                    'userID'    => $user->data()->id,
                    'carID'     => $carID,
                    'file_name' => $newFileName,
                    'file_link' => $targetFile,
                );
            } else {
                $response = array(
                    'status' => 'error',
                    'info'   => 'Couldn\'t upload the requested file :(, a mysterious error happend.'
                );
            }
        }
    } else {
        $response = array(
            'status' => 'error',
            'info'   => 'Configuration error - Admin has been notified'
        );
    }

    // Return the response
    logger($user->data()->id, "ElanRegistry", "imageUpdate carID: " . $response['carID'] . " Status: " . $response['status'] . " Info: " . $response['info']);
    echo json_encode($response);
    exit;
}
// Remove the file, the thumb, and the DB entry
function remove($filename = null, $carID = null)
{
    global $targetFilePath;
    global $targetFilePathThumb;
    global $db;
    global $user;
    // Remove file
    // TODO file_path is not set gives error
    $file = $targetFilePath . $filename;
    $thumb = $targetFilePathThumb . $filename;

    // Check if file is exists
    if (!empty($filename) && !empty($carID) && file_exists($file)) {
        // Remove  DB entry
        $db->delete("images", ["carid" => $carID, "image" => $filename]);

        unlink($file);
        unlink($thumb);

        // Be sure we deleted the file
        if (!file_exists($file)) {
            $response = array(
                'status' => 'success',
                'info'   => 'Photo Deleted.'
            );
        } else {
            // Check the directory's permissions
            $response = array(
                'status' => 'error',
                'info'   => 'We screwed up, the file can\'t be deleted.'
            );
        }
    } else {
        // Something weird happend and we lost the file
        $response = array(
            'status' => 'error',
            'info'   => 'Couldn\'t find the requested file :('
        );
    }

    // Return the response
    logger($user->data()->id, "ElanRegistry", "imageUpdate carID: " . $carID . " Status: " . $response['status'] . " Info: " . $response['info']);
    echo json_encode($response);
    exit;
}

function fetch($carID)
{
    global $targetFilePath;
    global $targetFilePathThumb;
    global $db;
    global $user;
    global $targetURL;

    $imageQ = $db->get("images", ["carid" => $carID]);

    // Did it return anything?
    if ($imageQ->count() !== 0) {
        // Yes it did
        $images = $imageQ->results();

        foreach ($images as $v1) {
            if ($v1->image != '' && $v1->image != '.' && $v1->image != '..') {

                // File path
                $file_path = $targetFilePath . $v1->image;

                // Check its not folder
                if (!is_dir($file_path)) {
                    $size = filesize($file_path);
                    $response[] = array('name' => $v1->image, 'size' => $size, 'path' => $targetURL  . $v1->image);
                }
            }
        }
        $response[] = array(
            'status' => 'success',

        );
    } else {
        $response = array(
            'status' => 'error',
            'info'   => 'No images'
        );
    }
    // Return the response
    echo json_encode($response);
    exit;
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
