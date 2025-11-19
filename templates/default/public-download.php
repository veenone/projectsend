<?php
$page_title = __('File information', 'cftp_admin');
$page_title = $file->title;

include_once ADMIN_VIEWS_DIR . DS . 'header-unlogged.php';
?>

<div class="row">
    <div class="col-12 col-sm-12 col-lg-8 offset-lg-2">

        <div class="white-box">
            <div class="white-box-interior">
                <?php
                if ($can_view) {
                ?>
                    <div class="text-center p-5">
                        <h2 class="file_title">
                            <?php echo $file->filename_original; ?>

                            <?php if ($file->filename_original != $file->title) { ?>
                                <h3><?php echo html_output($file->title); ?></h3>
                            <?php } ?>
                        </h2>
                        
                        <?php if (get_option('public_listing_enable_preview') == 1) { ?>
                            <div class="preview">
                                <?php
                                    // Preview
                                    if (file_is_image($file->full_path)) {
                                        $thumbnail = make_thumbnail($file->full_path, null, 250, 250);
                                        if (!empty($thumbnail['thumbnail']['url'])) {
                                ?>
                                            <a href="<?php echo $file->public_url . '&download'; ?>" class="get-preview">
                                                <img src="<?php echo $thumbnail['thumbnail']['url']; ?>" class="thumbnail" />
                                            </a>
                                <?php
                                        }
                                    } else {
                                        if ($file->embeddable) {
                                ?>
                                            <button class="btn btn-warning btn-sm btn-wide get-preview" data-url="<?php echo BASE_URI; ?>process.php?do=get_preview&file_id=<?php echo $file->id; ?>"><?php _e('Preview', 'cftp_admin'); ?></button>
                                <?php
                                        }
                                        
                                    }
                                ?>
                            </div>
                        <?php } ?>

                        <div class="description">
                            <?php echo html_output($file->description); ?>
                        </div>

                        <div class="size">
                            <?php echo $file->size_formatted; ?>
                            <?php
                                if (file_is_image($file->full_path)) {
                                    $dimensions = $file->getDimensions();
                                    if (!empty($dimensions)) {
                            ?>
                                        <p><?php echo $dimensions['width']; ?> x <?php echo $dimensions['height']; ?> px</p>
                            <?php
                                    }

                                    // if (function_exists('exif_read_data')) {
                                    //     $file->displayExif();
                                    //     if (get_option('public_download_show_exif_data') == '1') {
                                    //     }
                                    // }
                                }
                            ?>
                        </div>

                        <?php
                        // Display S3 Metadata if available
                        if (!empty($file->s3_metadata)) {
                            $s3_metadata_decoded = json_decode($file->s3_metadata, true);
                            if ($s3_metadata_decoded && is_array($s3_metadata_decoded)) {
                        ?>
                                <div class="s3-metadata mt-4">
                                    <h5><?php _e('Document Information', 'cftp_admin'); ?></h5>
                                    <div class="metadata-table">
                                        <?php if (!empty($s3_metadata_decoded['current-version'])): ?>
                                            <div class="metadata-row">
                                                <span class="metadata-label"><?php _e('Current Version', 'cftp_admin');?>:</span>
                                                <span class="metadata-value"><?php echo html_output($s3_metadata_decoded['current-version']); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($s3_metadata_decoded['version'])): ?>
                                            <div class="metadata-row">
                                                <span class="metadata-label"><?php _e('Version', 'cftp_admin');?>:</span>
                                                <span class="metadata-value"><?php echo html_output($s3_metadata_decoded['version']); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($s3_metadata_decoded['sharepoint-url'])): ?>
                                            <div class="metadata-row">
                                                <span class="metadata-label"><?php _e('SharePoint URL', 'cftp_admin');?>:</span>
                                                <span class="metadata-value">
                                                    <a href="<?php echo html_output($s3_metadata_decoded['sharepoint-url']); ?>" target="_blank" class="text-primary">
                                                        <?php _e('Open in SharePoint', 'cftp_admin'); ?>
                                                    </a>
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($s3_metadata_decoded['created-by'])): ?>
                                            <div class="metadata-row">
                                                <span class="metadata-label"><?php _e('Created By', 'cftp_admin');?>:</span>
                                                <span class="metadata-value"><?php echo html_output($s3_metadata_decoded['created-by']); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($s3_metadata_decoded['created-by-title'])): ?>
                                            <div class="metadata-row">
                                                <span class="metadata-label"><?php _e('Created By Title', 'cftp_admin');?>:</span>
                                                <span class="metadata-value"><?php echo html_output($s3_metadata_decoded['created-by-title']); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($s3_metadata_decoded['modified-date'])): ?>
                                            <div class="metadata-row">
                                                <span class="metadata-label"><?php _e('Modified Date', 'cftp_admin');?>:</span>
                                                <span class="metadata-value"><?php echo html_output($s3_metadata_decoded['modified-date']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                        <?php
                            }
                        }
                        ?>

                        <?php if ($can_download == true) { ?>
                            <div class="actions">
                                <a href="<?php echo $file->public_url . '&download'; ?>" class="btn btn-primary">
                                    <?php _e('Download file', 'cftp_admin'); ?>
                                </a>
                            </div>
                        <?php } ?>
                    </div>
                <?php
                }
                ?>
            </div>
        </div>

        <?php login_form_links(['homepage']); ?>
    </div>
</div>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
