
<!-- Form for the 'image' -->
<div class="input-group mb-3">
    <div class="custom-file">
        <input type="file" class="custom-file-input" id="file" name="file">
        <label class="custom-file-label" for="file">Choose file</label>
        <small id="fileHelp" class="form-text text-muted">Valid file types:  JPEG</small>
    </div>
</div>
<!-- Add some space -->
</br>
<?php
if ($cardetails['image']) { ?>
    <img class="card-img-top" src=<?= $us_url_root ?>app/userimages/<?= $cardetails['image'] ?> width='390'> <?php
}
?>

<script>
// Update file select box with filename
    $('#file').on('change',function(){
        //get the file name
        var fileName = $(this).val();
        // Remove c:\fakepath\
        fileName = fileName.substring(fileName.lastIndexOf("\\") + 1, fileName.length);
        //replace the "Choose a file" label
        $(this).next('.custom-file-label').html(fileName);
    })
</script>