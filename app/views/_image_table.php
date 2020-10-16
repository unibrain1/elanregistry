
<!-- Form for the 'image' -->
<div class="custom-file">
    <input type="file" class="custom-file-input" id="file" name="uploadedFile">
    <label class="custom-file-label" for="file">Choose file</label>
    <small id="fileHelp" class="form-text text-muted">Valid file types:  JPEG</small>
</div>
<!-- Add some space -->
</br>
<?php
if ($cardetails['image']) { ?>
    <img class="card-img-top" src=<?= $us_url_root ?>app/userimages/<?= $cardetails['image'] ?> width='390'> <?php
}
?>