<?php defined('ABSPATH') or die; ?>

<?php if(count($downloadableFiles)): ?>
<div  class="font_downloader_wrapper text-center">
    <img class="mb-3" src="<?= FLUENTFORM_PDF_URL . 'assets/images/pdf-img.png'; ?>" alt="">
    <h3 class="mb-2"><?php echo __('Fonts are required for PDF Generation', 'fluentform-pdf') ?></h3>
    <p class="mb-4"><?php echo __('This module requires to download fonts for PDF generation. Please click on the below button and it will download the required font files. This is one time job', 'fluentform-pdf') ?></p>
    <button id="ff_download_fonts" class="el-button el-button--primary">
        <span class="ff_download_fonts_bar"></span>
        <span class="ff_download_fonts_text"><?php echo __('Install Fonts', 'fluentform-pdf') ?></span>
    </button>
    <div class="ff_download_loading mt-3"></div>
    <div class="ff_download_logs mt-3 hidden"></div>
</div>
<?php else: ?>

<div class="ff_pdf_system_status">
    <h3 class="mb-3"><?php echo __('Fluent Forms PDF Module is now active', 'fluentform-pdf') ?> <?php if(!$statuses['status']): ?><span style="color: red;"><?php echo __('But Few Server Extensions are missing', 'fluentform-pdf') ?></span><?php endif; ?></h3>
    <ul>
        <?php foreach ($statuses['extensions'] as $status): ?>
        <li>
            <?php if($status['status']): ?><span class="dashicons dashicons-yes"></span>
            <?php else: ?><span class="dashicons dashicons-no-alt"></span><?php endif; ?>
            <?php echo $status['label']; ?>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php if($statuses['status']): ?>
    <p><?php echo __('All Looks good! You can now use Fluent Forms PDF Addon.', 'fluentform-pdf') ?> <a href="<?php echo $globalSettingsUrl; ?>"><?php echo __('Click Here', 'fluentform-pdf') ?></a> <?php echo __(' to check your global PDF feed settings', 'fluentform-pdf') ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>