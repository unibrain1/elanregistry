<?php
// Image is a comma seperated list of images
$carImages = explode(',', $car->image);

$j = count($carImages);
if ($j === 0 || $carImages[0] == '') {
    // No images or image name is blank
} else if ($j === 1) {
    if (is_file($abs_us_root . $us_url_root . $settings->elan_image_dir  . $carImages[0])) {
        echo '<img class="card-img-top" src="' . $us_url_root . $settings->elan_image_dir  . $carImages[0] . '">';
    }
} else if ($j > 1) {
?>
    <div id="slider">
        <!-- Carousel -->
        <div id="myCarousel-<?= $car->id ?>" class="carousel slide shadow">
            <!-- main slider carousel items -->
            <div class="carousel-inner">
                <div class="carousel-inner">
                    <?php
                    $class = 'carousel-item active';

                    $j = count($carImages);
                    for ($i = 0; $i < $j; $i++) {
                        echo "<div class='" . $class . "' data-slide-number='" . $i . "'>";
                        echo '<img class="img-fluid card-img-top" src="' . $us_url_root . $settings->elan_image_dir  . $carImages[$i] . '">';
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
                $j = count($carImages);
                for ($i = 0; $i < $j; $i++) {
                    echo '<li class="list-inline-item active">';
                    echo '<a id="carousel-selector-' . $i . '" class="selected" data-slide-to="' . $i . '" data-target="#myCarousel-' . $car->id . '">';
                    echo '<img src="' . $us_url_root . $settings->elan_image_dir . $carImages[$i] . '" class="img-fluid">';
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