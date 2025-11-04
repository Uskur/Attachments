<?php
$this->Html->script('Uskur/Attachments.cropper.min.js', ['block' => 'script']);
$this->Html->css('Uskur/Attachments.cropper.min.css', ['block' => 'css']);

$pageTitle = __d('Uskur/Attachments', 'Edit Image');
$this->assign('title', $pageTitle);
$this->Breadcrumbs->add($pageTitle, [
    'controller' => 'Attachments',
    'action' => 'editImage',
    $image->id
]);

$this->start('context-menu'); ?>

<?php $this->end(); ?>
<div class="row">
    <div class="col-12 col-md-8">
        <button class="btn btn-success" id="save"><i class="fa fa-floppy-o"
                                                     aria-hidden="true"></i> <?= __d('Uskur/Attachments', 'Save') ?>
        </button>

        <div class="btn-group" role="group" aria-label="Aspect Ratios">
            <button type="button" class="aspectRatio btn btn-secondary" data-ratio="4/3">4x3</button>
            <button type="button" class="aspectRatio btn btn-secondary" data-ratio="3/4">3x4</button>
            <button type="button" class="aspectRatio btn btn-secondary active" data-ratio="16/9">16x9</button>
            <button type="button" class="aspectRatio btn btn-secondary" data-ratio="21/9">21x9</button>
            <button type="button" class="aspectRatio btn btn-secondary" data-ratio="1/1">1x1</button>
            <button type="button" class="aspectRatio btn btn-secondary"
                    data-ratio="NaN"><?= __dx('Uskur/Attachments', 'as in freedom', 'Free') ?></button>
        </div>

    </div>
    <div class="col-12 col-md-4">
        <label for="rotate">Rotate <span id="rotateDegree">0</span>&deg;</label>
        <input type="range" class="custom-range" min="-180" max="180" value="0" id="rotate">
    </div>
</div>
<div class="row">
    <div
        class="col-12">
        <div><?= $this->Html->image(['prefix' => false, 'plugin' => 'Uskur/Attachments', 'controller' => 'Attachments', 'action' => 'image', $image->id], ['id' => 'image']) ?></div>
    </div>
</div>
<?php
echo $this->Form->create($image, ['type' => 'file', 'id' => 'imageSave']);
echo $this->Form->control('image', ['type' => 'hidden', 'id' => 'imageField']);
echo $this->Form->end();
?>
</script>

<?php $this->append('script'); ?>
<script type="text/javascript">
    $(function () {
        var firstClick = null;
        const image = document.getElementById('image');
        const cropper = new Cropper(image, {
            dragMode: 'move',
            aspectRatio: 16 / 9
        });

        $('#rotate').on('input', function (e) {
            cropper.rotateTo(e.currentTarget.value);
            $('#rotateDegree').html(e.currentTarget.value);
        });

        $('.aspectRatio').on('click', function (e) {
            alreadyActive = $(e.currentTarget).hasClass('active');
            alreadyInverted = $(e.currentTarget).hasClass('btn-dark');
            ratio = eval($(e.currentTarget).data('ratio'));
            if (alreadyActive) {
                if (isNaN(ratio)) {
                    return;
                }

                if (!alreadyInverted) {
                    $(e.currentTarget).addClass('btn-dark').removeClass('btn-secondary');
                    ratio = Math.pow(ratio, -1);
                } else {
                    $(e.currentTarget).addClass('btn-secondary').removeClass('btn-dark');
                }
            } else {
                $('.aspectRatio.active').toggleClass('active').addClass('btn-secondary').removeClass('btn-dark');
                $(e.currentTarget).toggleClass('active');
            }
            cropper.setAspectRatio(ratio)
        });

        $('#save').on('click', function () {

            $('#imageField').val(cropper.getCroppedCanvas().toDataURL('image/png'));
            $('#imageSave').submit();
        });

    });

    function saveImage() {
        $().cropper('getCroppedCanvas').toBlob(function (blob) {

            var formData = new FormData();

            formData.append('croppedImage', blob);

            $.ajax('upload.php', {
                method: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function () {
                    console.log('Upload success');
                },
                error: function () {
                    console.log('Upload error');
                }
            });
        }/* , 'image/jpeg' */);
    }


</script>
<style>
    #image {
        display: block;

        /* This rule is very important, please don't ignore this */
        max-width: 100%;
        max-height: 100%;
    }
</style>
<?php $this->end(); ?>
