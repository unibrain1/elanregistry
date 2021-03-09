


function carousel(data) {
    var images = data.split(',');
    var i;

    const id = Math.floor(Math.random() * 100); // Generate and ID number for the carousel in case there are more than 1 per page

    if (images.length === 1) {
        // 1 Image
        return load_picture(images[0], true);
    }

    var response = '<div id="slider"> <div id="myCarousel-' + id + '" class="carousel slide shadow"> <div class="carousel-inner"> <div class="carousel-inner"> ';
    var active = 'carousel-item active';
    for (i = 0; i < images.length; i++) {
        response += "<div class='" + active + "' data-slide-number='" + i + "'>";
        response += load_picture(images[i]);
        response += '</div>';
        active = 'carousel-item';
    }
    response += '</div><a class="carousel-control-prev" href="#myCarousel-' + id + '" role="button" data-slide="prev">';
    response += '<span class="carousel-control-prev-icon" aria-hidden="true" > </span>';
    response += '<span class="sr-only">Previous</span></a> <a class="carousel-control-next" href="#myCarousel-' + id + '" role="button" data-slide="next">';
    response += '<span class="carousel-control-next-icon" aria-hidden="true" ></span> <span class="sr-only">Next</span> </a>';
    response += '</div>';

    return response;
}

function load_picture(image, thumbnail = null) {
    var html;

    const index = image.lastIndexOf('.');
    const filename = image.substr(0, index);
    const extension = image.substr((index + 1));

    if (thumbnail) {
        html = '<img src="' + img_root + filename + '-resized-100.' + extension + '" width="100" alt="elan" loading="lazy" class="img-fluid"> ';
    } else {
        html = '<img loading="lazy" class="card-img-top" src="' + img_root + filename + '-resized-100.' + extension + '"';
        html += ' sizes="5vw" ';
        html += ' width="100" ';
        html += 'srcset="';
        html += img_root + filename + '-resized-100.' + extension + ' 100w,';
        html += img_root + filename + '-resized-300.' + extension + ' 300w"';
        html += 'alt="Elan" > ';
    }
    return html;
}