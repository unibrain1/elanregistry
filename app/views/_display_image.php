<?php
// Image is a comma seperated list of images
$carImages = explode(',', $car->image);

$sizes = [100, 300, 600, 1024, 2048];  // This should be from config TODO
//Remove the smallest since that will be the default
$small = $sizes[0];
unset($sizes[0]);

$count = count($carImages);
if ($count === 0 || $carImages[0] == '') {
    // No images or image name is blank
    return;
}

if ($count === 1) {
    echo load_picture($carImages[0]);
} else {
?>
    <div id="slider">
        <!-- Carousel -->
        <div id="myCarousel-<?= $car->id ?>" class="carousel slide shadow">
            <!-- main slider carousel items -->
            <div class="carousel-inner">
                <div class="carousel-inner">
                    <?php
                    $class = 'carousel-item active';

                    foreach ($carImages as $key => $image) {
                        echo "<div class='" . $class . "' data-slide-number='" . $key . "'>";
                        echo load_picture($carImages[$key]);
                        echo '</div>';
                        $class = 'carousel-item';
                    }
                    ?>
                </div>

                <a class="carousel-control-prev" href="#myCarousel-<?= $car->id ?>" role="button" data-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="sr-only">Previous</span>
                </a>
                <a class="carousel-control-next" href="#myCarousel-<?= $car->id ?>" role="button" data-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="sr-only">Next</span>
                </a>

            </div>
            <!-- main slider carousel nav controls -->
            <ul class="carousel-indicators list-inline mx-auto border px-0">
                <?php
                foreach ($carImages as $key => $image) {

                    echo '<li class="list-inline-item active">';
                    echo '<a id="carousel-selector-' . $key . '" class="selected" data-slide-to="' . $key . '" data-target="#myCarousel-' . $car->id . '">';
                    echo load_picture($image, true);
                    echo '</a> </li>';
                }
                ?>
            </ul>
        </div>
    </div>
    <!-- Carousel -->

<?php
}


?>