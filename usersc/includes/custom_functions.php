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

//Put your custom functions in this file and they will be automatically included.

// Get encrypted environment variables
// Need to include now because the init calls usersc/vendor/autoload.php after custom_functions.php
if (file_exists($abs_us_root . $us_url_root . 'vendor/autoload.php')) {
    require_once $abs_us_root . $us_url_root . 'vendor/autoload.php';
}

use SecureEnvPHP\SecureEnvPHP;

(new SecureEnvPHP())->parse($abs_us_root . $us_url_root . '.env.enc', $abs_us_root . $us_url_root . '.env.key');

// Include classes in usersc

include_once $abs_us_root . $us_url_root . 'usersc/classes/Car.php';
include_once $abs_us_root . $us_url_root . 'usersc/classes/Resize.php';

// This is probably not the best place for this function - TODO::
function load_picture($image, $thumbnail = null)
{
    global $us_url_root;
    global $abs_us_root;
    global $settings;
    $thumbsize = 100;
    $resize = '-resized-';
    $image_dir = $settings->elan_image_dir;

    $html = "";

    $path = pathinfo($abs_us_root . $us_url_root . $image_dir  . $image);
    $filename = $path['filename'];
    $extension = $path['extension'];

    if ($thumbnail) {
        $html = '<img src="' . $us_url_root . $image_dir . $filename . $resize . $thumbsize . "." . $extension . '" width="100" alt="elan" loading="lazy" class="img-fluid"> ';
    } else {
        $html = '<img loading="lazy" class="card-img-top" src="' . $us_url_root . $image_dir . $filename . $resize . $thumbsize  . $extension . '"';
        $html .= ' sizes="50vw" ';
        $html .= ' width="100" ';
        $html .= 'srcset="';
        $html .= $us_url_root . $image_dir . $filename . '-resized-100.' . $extension . ' 100w,';
        $html .= $us_url_root . $image_dir . $filename . '-resized-300.' . $extension . ' 300w,';
        $html .= $us_url_root . $image_dir . $filename . '-resized-600.' . $extension . ' 600w,';
        $html .= $us_url_root . $image_dir . $filename . '-resized-1024.' . $extension . ' 1024w"';
        $html .= 'alt="Elan" > ';
    }
    return $html;
}
