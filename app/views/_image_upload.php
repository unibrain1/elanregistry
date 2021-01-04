<div class='card card-default'>
    <div class='card-header'>
        <label for='file'>
            <h2><strong>Add New Photos</strong></h2>
        </label>
    </div>
    <div class='card-body'>
        <div class='dropzone'>
            <form action='action/imageUpdate.php'>
                <div class='fallback'>
                    <input id='file' name='file' type='file' multiple />
                </div>
            </form>
        </div>

    </div> <!-- card-body -->
</div> <!-- card -->

<div id='existing' class='card card-default'>
    <div class='card-header'>
        <h2><strong>Existing Photos</strong></h2>
    </div>
    <div class='card-body'>
        <div id='images' class='form-group row align-items-center'>
        </div>
    </div>
</div>