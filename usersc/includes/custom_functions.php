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

// This is probably not the best place for this function
function loadPicture($image, $thumbnail = null)
{
    global $us_url_root;
    global $abs_us_root;
    $thumbsize = 100;
    $resize = '-resized-';

    $html = "<!--Start loadPicture -->";

    $path = pathinfo($image['path']);

    if ($thumbnail) {
        $html .= '<img src="' . $path['dirname'] . "/" .
            $path['filename'] . $resize . $thumbsize . "." .
            $path['extension'] . '" width="100" alt="elan" loading="lazy" class="img-fluid"> ';
    } else {
        $html = '<img loading="lazy" class="card-img-top" src="' .
            $path['dirname'] . "/" . $path['filename'] . $resize . $thumbsize  . $path['extension'] . '"';
        $html .= ' sizes="50vw" ';
        $html .= ' width="100" ';
        $html .= 'srcset="';
        $html .= $path['dirname'] . "/" . $path['filename'] . '-resized-100.' . $path['extension'] . ' 100w,';
        $html .= $path['dirname'] . "/" . $path['filename'] . '-resized-300.' . $path['extension'] . ' 300w,';
        $html .= $path['dirname'] . "/" . $path['filename'] . '-resized-600.' . $path['extension'] . ' 600w,';
        $html .= $path['dirname'] . "/" . $path['filename'] . '-resized-1024.' . $path['extension'] . ' 1024w"';
        $html .= 'alt="Elan" > ';
    }
    $html .= '<!--End loadPicture -->';

    return $html;
}

// Given a  CAR display the images
function displayCarousel($car)
{

    $carImages = $car->images();
    $html = "<!--Start displayCarousel -->";
    $carouselId = rand(0, 100);

    $sizes = [100, 300, 600, 1024, 2048];
    // Remove the smallest since that will be the default
    unset($sizes[0]);

    $count = count($carImages);
    if ($count === 0 || $carImages[0] == '') {
        // No images or image name is blank
        $html .= '<!--End displayCarousel -->';
        return $html;
    }

    if ($count === 1) {
        echo loadPicture($carImages[0]);
    } else {

        $html .= '<div id="slider"><div id="myCarousel-' . $carouselId .
            '" class="carousel slide shadow"> <div class="carousel-inner"><div class="carousel-inner">';

        $class = 'carousel-item active';

        foreach ($carImages as $key => $image) {
            $html .=  "<div class='" . $class . "' data-slide-number='" . $key . "'>";
            $html .=  loadPicture($image);
            $html .=  '</div>';
            $class = 'carousel-item';
        }
        $html .= '</div>
                <a class="carousel-control-prev" href="#myCarousel-' . $carouselId . '" role="button" data-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="sr-only">Previous</span>
                </a>
                <a class="carousel-control-next" href="#myCarousel-' . $carouselId . '" role="button" data-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="sr-only">Next</span>
                </a>
            </div>
            <ul class="carousel-indicators list-inline mx-auto border px-0">';
        foreach ($carImages as $key => $image) {
            $html .= '<li class="list-inline-item active">';
            $html .= '<a id="carousel-selector-' . $key . '" class="selected" data-slide-to="'
                . $key . '" data-target="#myCarousel-' . $carouselId . '">';
            $html .= loadPicture($image, true);
            $html .= '</a> </li>';
        }
        $html .= '</ul></div></div>';
    }
    $html .= '<!--End displayCarousel -->';

    return $html;
}

function findByOwner($ownerID)
{
    global $db;

    $carQ = $db->query("SELECT id FROM cars WHERE user_id = ?", array($ownerID))->results();
    $cars = [];

    foreach ($carQ as $key => $car) {
        $cars[$key] = new Car($car->id);
    }
    return $cars;
}
