<?php

$count = count($carImages);
if ($count == 0) {
    echo "";
} else if ($count == 1) {
    echo '<img class="card-img-top" src="' . $us_url_root . 'app/userimages/' . $carImages[0]->image . '">';
} else {
?>
    <div id="slider">
        <!-- Carousel -->
        <div id="myCarousel-<?= $carImages[0]->carid ?>" class="carousel slide shadow">
            <!-- main slider carousel items -->
            <div class="carousel-inner">
                <div class="carousel-inner">
                    <?php
                    $count = 0;
                    $class = 'carousel-item active';
                    foreach ($carImages as $carImage) {
                        echo "<div class='" . $class . "' data-slide-number='" . $count . "'>";
                        echo '<img class="img-fluid card-img-top" src="' . $us_url_root . 'app/userimages/' . $carImage->image . '">';
                        echo '</div>';
                        $class = 'carousel-item';
                        $count++;
                    }
                    ?>
                </div>

                <a class="carousel-control-prev" href="#myCarousel-<?= $carImages[0]->carid ?>" role="button" data-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="sr-only">Previous</span>
                </a>
                <a class="carousel-control-next" href="#myCarousel-<?= $carImages[0]->carid ?>" role="button" data-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="sr-only">Next</span>
                </a>

            </div>
            <!-- main slider carousel nav controls -->
            <ul class="carousel-indicators list-inline mx-auto border px-2">
                <?php
                $count = 0;
                foreach ($carImages as $carImage) {
                    echo '<li class="list-inline-item active">';
                    echo '<a id="carousel-selector-' . $count . '" class="selected" data-slide-to="' . $count . '" data-target="#myCarousel-' . $carImages[0]->carid . '">';
                    echo '<img src="' . $us_url_root . 'app/userimages/thumbs/' . $carImage->image . '" class="img-fluid">';
                    echo '</a> </li>';
                    $count++;
                }
                ?>
            </ul>
        </div>
    </div>
    <!-- Carousel -->

<?php
}
?>