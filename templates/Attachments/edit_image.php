<?php
$this->Html->script('Uskur/Attachments.cropper.min.js', ['block' => 'script']);
$this->Html->css('Uskur/Attachments.cropper.min.css', ['block' => 'css']);

$pageTitle = __d('Uskur/Attachments', 'Edit Image');
$this->assign('title', $pageTitle);
$this->Breadcrumbs->add($pageTitle, [
    'controller' => 'Attachments',
    'action' => 'editImage',
    $image->id,
]);

$this->start('context-menu');
$this->end();
?>

<div class="image-editor" id="imageEditor">
    <div class="image-editor__toolbar">
        <button class="btn btn-success image-editor__save" id="save" type="button">
            <i class="fa fa-floppy-o" aria-hidden="true"></i>
            <?= __d('Uskur/Attachments', 'Save') ?>
        </button>

        <div
            class="image-editor__ratios"
            role="group"
            aria-label="<?= h(__d('Uskur/Attachments', 'Aspect Ratios')) ?>"
        >
            <span class="image-editor__label"><?= __d('Uskur/Attachments', 'Crop ratio') ?></span>
            <div class="btn-group image-editor__ratio-buttons" role="group">
                <button
                    type="button"
                    class="aspectRatio btn btn-secondary"
                    data-ratio="1.3333333333"
                    aria-pressed="false"
                >
                    4×3
                </button>
                <button type="button" class="aspectRatio btn btn-secondary" data-ratio="0.75" aria-pressed="false">
                    3×4
                </button>
                <button
                    type="button"
                    class="aspectRatio btn btn-secondary active"
                    data-ratio="1.7777777778"
                    aria-pressed="true"
                >
                    16×9
                </button>
                <button
                    type="button"
                    class="aspectRatio btn btn-secondary"
                    data-ratio="2.3333333333"
                    aria-pressed="false"
                >
                    21×9
                </button>
                <button type="button" class="aspectRatio btn btn-secondary" data-ratio="1" aria-pressed="false">
                    1×1
                </button>
                <button type="button" class="aspectRatio btn btn-secondary" data-ratio="free" aria-pressed="false">
                    <?= __dx('Uskur/Attachments', 'as in freedom', 'Free') ?>
                </button>
            </div>
        </div>

        <div class="image-editor__rotation">
            <label class="image-editor__label" for="rotate">
                <?= __d('Uskur/Attachments', 'Rotate') ?>
                <output id="rotateDegree" for="rotate">0°</output>
            </label>
            <input type="range" class="custom-range" min="-180" max="180" value="0" id="rotate">
        </div>
    </div>

    <div class="image-editor__canvas" id="imageEditorCanvas">
        <?= $this->Html->image([
            'prefix' => false,
            'plugin' => 'Uskur/Attachments',
            'controller' => 'Attachments',
            'action' => 'image',
            $image->id,
        ], [
            'id' => 'image',
            'alt' => '',
        ]) ?>
    </div>
</div>

<?php
echo $this->Form->create($image, ['type' => 'file', 'id' => 'imageSave']);
echo $this->Form->control('image', ['type' => 'hidden', 'id' => 'imageField']);
echo $this->Form->end();
?>

<?php $this->append('script'); ?>
<script type="text/javascript">
    $(function () {
        const editor = document.getElementById('imageEditor');
        const canvas = document.getElementById('imageEditorCanvas');
        const image = document.getElementById('image');
        const saveButton = document.getElementById('save');

        function fitCanvasToViewport() {
            const viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
            const availableHeight = Math.floor(viewportHeight - canvas.getBoundingClientRect().top - 16);

            editor.style.setProperty('--image-editor-canvas-height', Math.max(160, availableHeight) + 'px');
        }

        fitCanvasToViewport();
        window.addEventListener('resize', fitCanvasToViewport);
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', fitCanvasToViewport);
        }

        const cropper = new Cropper(image, {
            dragMode: 'move',
            aspectRatio: 16 / 9,
            responsive: true,
            restore: true,
            viewMode: 1
        });

        $('#rotate').on('input', function (event) {
            const degrees = Number(event.currentTarget.value);
            cropper.rotateTo(degrees);
            $('#rotateDegree').text(degrees + '°');
        });

        $('.aspectRatio').on('click', function (event) {
            const button = $(event.currentTarget);
            const isActive = button.hasClass('active');
            const isInverted = button.hasClass('btn-dark');
            const ratioValue = button.data('ratio');
            let ratio = ratioValue === 'free' ? NaN : Number(ratioValue);

            if (isActive && isNaN(ratio)) {
                return;
            }

            $('.aspectRatio')
                .removeClass('active btn-dark')
                .addClass('btn-secondary')
                .attr('aria-pressed', 'false');

            if (isActive && !isInverted) {
                button
                    .removeClass('btn-secondary')
                    .addClass('active btn-dark')
                    .attr('aria-pressed', 'true');
                ratio = Math.pow(ratio, -1);
            } else {
                button.addClass('active').attr('aria-pressed', 'true');
            }

            cropper.setAspectRatio(ratio);
        });

        $('#save').on('click', function () {
            saveButton.disabled = true;
            $('#imageField').val(cropper.getCroppedCanvas().toDataURL('image/png'));
            $('#imageSave').submit();
        });
    });
</script>
<?php $this->end(); ?>

<?php $this->append('css'); ?>
<style>
    .image-editor {
        display: flex;
        flex-direction: column;
        width: 100%;
        min-width: 0;
    }

    .image-editor__toolbar {
        display: flex;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: .75rem 1rem;
        padding-bottom: .75rem;
    }

    .image-editor__save {
        flex: 0 0 auto;
        min-height: 38px;
    }

    .image-editor__ratios,
    .image-editor__rotation {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .image-editor__ratios {
        flex: 1 1 25rem;
    }

    .image-editor__rotation {
        flex: 1 1 14rem;
        max-width: 24rem;
    }

    .image-editor__label {
        display: block;
        margin: 0 0 .25rem;
        color: #495057;
        font-size: .875rem;
        font-weight: 600;
        line-height: 1.25;
    }

    .image-editor__label output {
        font-weight: 400;
    }

    .image-editor__ratio-buttons {
        display: flex;
        max-width: 100%;
    }

    .image-editor__ratio-buttons .btn {
        flex: 1 1 auto;
        min-width: 3.25rem;
        padding-right: .65rem;
        padding-left: .65rem;
        white-space: nowrap;
    }

    .image-editor__rotation .custom-range {
        width: 100%;
        min-height: 38px;
        margin: 0;
    }

    .image-editor__canvas {
        position: relative;
        width: 100%;
        height: var(--image-editor-canvas-height, 60vh);
        min-height: 0;
        overflow: hidden;
        background-color: #212529;
        border: 1px solid #ced4da;
        border-radius: .25rem;
    }

    .image-editor__canvas > #image {
        display: block;
        width: 100%;
        height: 100%;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .image-editor__canvas > .cropper-container {
        width: 100% !important;
        height: 100% !important;
    }

    @media (max-width: 575.98px) {
        .image-editor__toolbar {
            gap: .625rem;
        }

        .image-editor__save {
            width: 100%;
        }

        .image-editor__ratios,
        .image-editor__rotation {
            flex-basis: 100%;
            max-width: none;
        }

        .image-editor__ratio-buttons {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            width: 100%;
        }

        .image-editor__ratio-buttons .btn {
            min-width: 0;
            margin: -1px 0 0 -1px;
            border-radius: 0;
        }
    }
</style>
<?php $this->end(); ?>
