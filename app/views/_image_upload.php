<div class='card' id='imageCard'>
    <div class='card-header'>
        <div class='input-group-prepend'>
            <div class='col-sm-6'>
                <h2><strong>Photo Upload</strong></h2>
            </div>
            <div class='col-sm'>
                <div style='text-align: center' id='photoMessage' class='alert-success'> <span></span></div>
            </div>
        </div>
    </div>
</div>
<div class='card-body d-none' id='dropzoneCard'>
    <form class="dropzone files-container" action='action/imageUpdate.php'>
        <div class="fallback">
            <input id='file' name='file' type='file' multiple />
        </div>
    </form>

    <!-- Uploaded files section -->
    <h4 class="section-sub-title"><span>Uploaded</span> Photos</h4>
    <span class="no-files-uploaded">No photos uploaded yet.</span>

    <!-- Preview collection of uploaded documents -->
    <div class="preview-container dz-preview uploaded-files">
        <div id="previews">
            <div id="onyx-dropzone-template">
                <div class="onyx-dropzone-info">
                    <div class="thumb-container">
                        <img data-dz-thumbnail />
                    </div>
                    <div class="details">
                        <div class="dz-progress"><span class="dz-upload" data-dz-uploadprogress></span>
                        </div>
                        <div class="dz-error-message"><span data-dz-errormessage></span></div> <!-- TODO move error messages -->
                        <div class="actions">
                            <a href="#!" data-dz-remove><i class="fa fa-times-circle" title="Remove picture"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> <!-- card -->
</div>

<script src=<?= $us_url_root . 'usersc/vendor/enyo/dropzone/dist/min/dropzone.min.js' ?> type='text/javascript'></script>
<link type='text/css' rel='stylesheet' href="<?= $us_url_root ?>usersc/vendor/enyo/dropzone/dist/min/dropzone.min.css">