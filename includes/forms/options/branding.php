<?php
/**
 * Branding options form
 */

// Load system theme functions if not already loaded
if (!function_exists('look_for_system_themes') && file_exists(ROOT_DIR . '/includes/functions.system-themes.php')) {
    require_once ROOT_DIR . '/includes/functions.system-themes.php';
}

// Logo
$logo_file_info = generate_logo_url();
$favicon_filename = get_option('favicon_filename');

// Define sections for navigation (titles only, actual rendering is below)
$form_sections = [
    ['title' => __('Company Logo', 'cftp_admin')],
    ['title' => __('Website Favicon', 'cftp_admin')],
    ['title' => __('System Theme', 'cftp_admin')],
];
?>

<input type="hidden" name="MAX_FILE_SIZE" value="1000000000">

<!-- Logo Section -->
<div class="form-group row">
    <div class="col-sm-12">
        <h3 id="section-company-logo"><?php _e('Company Logo', 'cftp_admin'); ?></h3>
        <p class="text-muted"><?php _e('Upload your company logo to customize the appearance of your ProjectSend installation.', 'cftp_admin'); ?></p>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-12">
        <div class="branding-preview">
            <?php
                // Check if there's actually a custom logo file (not just the default)
                $custom_logo_filename = get_option('logo_filename');
                $has_custom_logo = !empty($custom_logo_filename) && isset($logo_file_info) && $logo_file_info['exists'] === true;

                if ($has_custom_logo) {
                    $logo = make_thumbnail($logo_file_info['dir'], LOGO_MAX_WIDTH, LOGO_MAX_HEIGHT);
                    $img_src = ( !empty( $logo ) ) ? $logo['thumbnail']['url'] : $logo_file_info['url'];
                    $is_custom_logo = true;
                } else {
                    // Use default logo
                    if (defined('ASSETS_IMG_URL') && defined('DEFAULT_LOGO_FILENAME')) {
                        $img_src = ASSETS_IMG_URL . '/' . DEFAULT_LOGO_FILENAME;
                    } elseif (defined('ASSETS_URL')) {
                        $img_src = ASSETS_URL . '/img/projectsend-logo.svg';
                    } else {
                        $img_src = 'assets/img/projectsend-logo.svg';
                    }
                    $is_custom_logo = false;
                }
            ?>
            <div class="preview-container logo-preview">
                <img src="<?php echo $img_src; ?>" alt="<?php _e('Current logo', 'cftp_admin'); ?>" class="preview-image" id="logo-preview-img">
            </div>
            <p class="preview-note text-muted small mt-2">
                <?php if ($is_custom_logo) { ?>
                    <?php _e('Current custom logo', 'cftp_admin'); ?>
                <?php } else { ?>
                    <?php _e('Default ProjectSend logo', 'cftp_admin'); ?>
                <?php } ?>
            </p>
            <div id="logo-upload-warning" class="alert alert-warning mt-2" style="display: none;">
                <i class="fa fa-exclamation-triangle me-1"></i>
                <?php _e('Remember to save your changes to upload the new logo.', 'cftp_admin'); ?>
            </div>
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="select_logo" class="col-sm-4 control-label"><?php _e('Upload new logo', 'cftp_admin'); ?></label>
    <div class="col-sm-8">
        <label for="select_logo" class="file-upload-label">
            <div class="file-upload-area">
                <i class="fa fa-cloud-upload text-primary mb-2"></i>
                <p class="mb-1"><?php _e('Click to upload new logo', 'cftp_admin'); ?></p>
                <small class="text-muted"><?php _e('JPG, PNG, GIF, SVG (max 10MB)', 'cftp_admin'); ?></small>
            </div>
            <input type="file" name="select_logo" id="select_logo" class="file-upload-input" accept=".jpg,.jpeg,.jpe,.gif,.png,.svg" />
        </label>

        <?php if (!empty(get_option('logo_filename'))) { ?>
            <div class="mt-3">
                <a class="btn btn-outline-danger btn-sm confirm_generic" href="<?php echo BASE_URI . 'options.php?section=branding&clear=logo'; ?>">
                    <i class="fa fa-trash me-1"></i>
                    <?php _e('Remove Logo', 'cftp_admin'); ?>
                </a>
            </div>
        <?php } ?>
    </div>
</div>

<div class="options_divide"></div>

<!-- Favicon Section -->
<div class="form-group row">
    <div class="col-sm-12">
        <h3 id="section-website-favicon"><?php _e('Website Favicon', 'cftp_admin'); ?></h3>
        <p class="text-muted"><?php _e('Upload a favicon to display in browser tabs and bookmarks. Recommended format is 32x32 pixels.', 'cftp_admin'); ?></p>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-12">
        <div class="branding-preview">
            <?php if (!empty($favicon_filename)) {
                $favicon_path = ADMIN_UPLOADS_DIR . DS . $favicon_filename;
                $favicon_url = ADMIN_UPLOADS_URI . $favicon_filename;
                if (file_exists($favicon_path)) {
            ?>
                <div class="preview-container favicon-preview">
                    <img src="<?php echo $favicon_url; ?>" alt="<?php _e('Current favicon', 'cftp_admin'); ?>" class="preview-image favicon-size" id="favicon-preview-img">
                </div>
                <p class="preview-note text-muted small mt-2">
                    <?php _e('Current custom favicon', 'cftp_admin'); ?>
                </p>
            <?php } } else { ?>
                <div class="preview-container favicon-preview">
                    <img src="<?php echo ASSETS_URL . '/img/favicon/favicon-32x32.png'; ?>" alt="<?php _e('Default favicon', 'cftp_admin'); ?>" class="preview-image favicon-size" id="favicon-preview-img">
                </div>
                <p class="preview-note text-muted small mt-2">
                    <?php _e('Default ProjectSend favicon', 'cftp_admin'); ?>
                </p>
            <?php } ?>
            <div id="favicon-upload-warning" class="alert alert-warning mt-2" style="display: none;">
                <i class="fa fa-exclamation-triangle me-1"></i>
                <?php _e('Remember to save your changes to upload the new favicon.', 'cftp_admin'); ?>
            </div>
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="select_favicon" class="col-sm-4 control-label"><?php _e('Upload favicon', 'cftp_admin'); ?></label>
    <div class="col-sm-8">
        <label for="select_favicon" class="file-upload-label">
            <div class="file-upload-area">
                <i class="fa fa-cloud-upload text-primary mb-2"></i>
                <p class="mb-1"><?php _e('Click to upload favicon', 'cftp_admin'); ?></p>
                <small class="text-muted"><?php _e('ICO, PNG, GIF, JPG, SVG (1:1 format recommended)', 'cftp_admin'); ?></small>
            </div>
            <input type="file" name="select_favicon" id="select_favicon" class="file-upload-input" accept=".ico,.png,.gif,.jpg,.jpeg,.svg" />
        </label>

        <?php if (!empty($favicon_filename)) { ?>
            <div class="mt-3">
                <a class="btn btn-outline-danger btn-sm confirm_generic" href="<?php echo BASE_URI . 'options.php?section=branding&clear=favicon'; ?>">
                    <i class="fa fa-trash me-1"></i>
                    <?php _e('Remove Favicon', 'cftp_admin'); ?>
                </a>
            </div>
        <?php } ?>
    </div>
</div>

<div class="options_divide"></div>

<!-- System Theme Section -->
<div class="form-group row">
    <div class="col-sm-12">
        <h3 id="section-system-theme"><?php _e('System Theme', 'cftp_admin'); ?></h3>
        <p class="text-muted"><?php _e('Select a theme for the admin/system interface. This is separate from the public client template.', 'cftp_admin'); ?></p>
    </div>
</div>

<div class="form-group row">
    <label for="selected_system_theme" class="col-sm-4 control-label"><?php _e('Admin Interface Theme', 'cftp_admin'); ?></label>
    <div class="col-sm-8">
        <?php
            $available_system_themes = look_for_system_themes();
            $current_system_theme = get_option('selected_system_theme', 'default');
        ?>
        <select class="form-select" name="selected_system_theme" id="selected_system_theme">
            <?php foreach ($available_system_themes as $system_theme) { ?>
                <option value="<?php echo $system_theme['location']; ?>"
                    <?php if ($current_system_theme == $system_theme['location']) { echo 'selected="selected"'; } ?>>
                    <?php echo $system_theme['name']; ?>
                    <?php if (!empty($system_theme['description'])) { echo ' - ' . $system_theme['description']; } ?>
                </option>
            <?php } ?>
        </select>
        <p class="field_note text-muted mt-2">
            <i class="fa fa-info-circle"></i>
            <?php _e('Changes will take effect on the next page load.', 'cftp_admin'); ?>
        </p>

        <?php if (!empty($available_system_themes)) { ?>
            <div class="mt-4">
                <h5><?php _e('Available Themes', 'cftp_admin'); ?></h5>
                <div class="row">
                    <?php foreach ($available_system_themes as $theme) { ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card theme-card <?php echo ($current_system_theme == $theme['location']) ? 'border-primary' : ''; ?>">
                                <?php if (!empty($theme['screenshot'])) { ?>
                                    <img src="<?php echo $theme['screenshot']; ?>" class="card-img-top" alt="<?php echo $theme['name']; ?>" style="max-height: 200px; object-fit: cover;">
                                <?php } ?>
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <?php echo $theme['name']; ?>
                                        <?php if ($current_system_theme == $theme['location']) { ?>
                                            <span class="badge bg-primary ms-2"><?php _e('Active', 'cftp_admin'); ?></span>
                                        <?php } ?>
                                    </h6>
                                    <?php if (!empty($theme['description'])) { ?>
                                        <p class="card-text small text-muted"><?php echo $theme['description']; ?></p>
                                    <?php } ?>
                                    <?php if (!empty($theme['version'])) { ?>
                                        <p class="card-text">
                                            <small class="text-muted"><?php _e('Version', 'cftp_admin'); ?>: <?php echo $theme['version']; ?></small>
                                        </p>
                                    <?php } ?>
                                    <?php if (!empty($theme['features'])) { ?>
                                        <div class="mt-2">
                                            <?php foreach ($theme['features'] as $feature => $enabled) {
                                                if ($enabled) {
                                                    $feature_name = str_replace('_', ' ', ucfirst($feature));
                                                    ?>
                                                    <span class="badge bg-secondary me-1"><?php echo $feature_name; ?></span>
                                            <?php }
                                            } ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
    </div>
</div>
