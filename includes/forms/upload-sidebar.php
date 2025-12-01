<?php
// Upload sidebar - contains storage and encryption options
// Storage selection for users with permission
$can_select_storage = current_user_can('upload_storage_select');
$default_storage = get_option('default_upload_storage', 'local');

// Get allowed storage for current user (only if user is logged in)
$effective_allowed_storage = [];
if (defined('CURRENT_USER_ID') && CURRENT_USER_ID) {
    $current_user = new \ProjectSend\Classes\Users(CURRENT_USER_ID);
    $user_allowed_storage = $current_user->getAllowedStorageArray();

    // Get allowed storage from user's groups
    $group_allowed_storage = [];
    $user_groups = $current_user->groups;
    if (!empty($user_groups)) {
        foreach ($user_groups as $group_id) {
            $group = new \ProjectSend\Classes\Groups($group_id);
            $group_storage = $group->getAllowedStorageArray();
            if (!empty($group_storage)) {
                // If any group has restrictions, accumulate them
                $group_allowed_storage = array_merge($group_allowed_storage, $group_storage);
            }
        }
        $group_allowed_storage = array_unique($group_allowed_storage);
    }

    // Determine effective allowed storage
    // Logic: User can use storage allowed by user settings OR group settings (union).
    // If both have restrictions, combine them (user gets access to storage allowed by either).
    $has_user_restrictions = !empty($user_allowed_storage);
    $has_group_restrictions = !empty($group_allowed_storage);

    if ($has_user_restrictions && $has_group_restrictions) {
        // Use union - user can use storage allowed by user OR group settings
        $effective_allowed_storage = array_unique(array_merge($user_allowed_storage, $group_allowed_storage));
    } elseif ($has_user_restrictions) {
        // Only user restrictions apply
        $effective_allowed_storage = $user_allowed_storage;
    } elseif ($has_group_restrictions) {
        // Only group restrictions apply
        $effective_allowed_storage = $group_allowed_storage;
    }
}
// If neither has restrictions or user not logged in, $effective_allowed_storage remains empty (all allowed)

// Encryption settings are defined in upload.php for use in JavaScript

// Disk quota information
$user_disk_quota = defined('CURRENT_USER_DISK_QUOTA') ? CURRENT_USER_DISK_QUOTA : 0; // In MB, 0 = unlimited
$user_disk_usage = defined('CURRENT_USER_DISK_USAGE') ? CURRENT_USER_DISK_USAGE : 0; // In bytes
$quota_unlimited = ($user_disk_quota == 0);

if (!$quota_unlimited) {
    $quota_bytes = $user_disk_quota * 1048576; // Convert MB to bytes
    $quota_available_bytes = $quota_bytes - $user_disk_usage;
    // Don't allow negative available space
    if ($quota_available_bytes < 0) {
        $quota_available_bytes = 0;
    }
    $quota_percentage = ($quota_bytes > 0) ? round(($user_disk_usage / $quota_bytes) * 100, 1) : 0;
    // Cap percentage at 100%
    if ($quota_percentage > 100) {
        $quota_percentage = 100;
    }
}
?>

<?php if (!$quota_unlimited): ?>
    <div class="ps-card mb-3">
        <div class="ps-card-body">
            <h4><?php _e('Disk Quota', 'cftp_admin'); ?></h4>
            <div class="mb-2">
                <div class="d-flex justify-content-between mb-1">
                    <span><?php echo format_file_size($user_disk_usage); ?> <?php _e('of', 'cftp_admin'); ?> <?php echo format_file_size($quota_bytes); ?></span>
                    <span><?php echo $quota_percentage; ?>%</span>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar <?php echo ($quota_percentage > 90) ? 'bg-danger' : (($quota_percentage > 75) ? 'bg-warning' : 'bg-primary'); ?>"
                         role="progressbar"
                         style="width: <?php echo $quota_percentage; ?>%">
                    </div>
                </div>
            </div>
            <small class="text-muted">
                <?php echo format_file_size($quota_available_bytes); ?> <?php _e('available', 'cftp_admin'); ?>
            </small>
        </div>
    </div>
<?php endif; ?>

<?php if ($can_select_storage): ?>
    <div class="ps-card mb-3">
        <div class="ps-card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="mb-0"><?php _e('Storage Destination', 'cftp_admin'); ?></h4>
                <?php if (current_user_can('edit_settings')): ?>
                <a href="<?php echo BASE_URI; ?>integrations.php" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="fa fa-cog"></i> <?php _e('Manage', 'cftp_admin'); ?>
                </a>
                <?php endif; ?>
            </div>
            <label for="storage_selector" class="form-label"><?php _e('Choose where to store the uploaded files', 'cftp_admin'); ?></label>
            <?php
            // Get all available storage options
            $integrations_handler = new \ProjectSend\Classes\Integrations();
            $active_integrations = $integrations_handler->getAll(true); // Only active

            // Build list of available storage options based on restrictions
            $available_storage = [];
            $has_restrictions = !empty($effective_allowed_storage);

            // Check if local storage is allowed
            if (!$has_restrictions || in_array('local', $effective_allowed_storage)) {
                $available_storage['local'] = __('Local storage', 'cftp_admin');
            }

            // Check which integrations are allowed
            foreach ($active_integrations as $integration) {
                $integration_id = $integration['id'];
                if (!$has_restrictions || in_array($integration_id, $effective_allowed_storage) || in_array((string)$integration_id, $effective_allowed_storage)) {
                    $type_config = \ProjectSend\Classes\Integrations::getTypeConfig($integration['type']);
                    $type_name = $type_config ? $type_config['name'] : ucfirst($integration['type']);
                    $available_storage[$integration_id] = html_output($integration['name']) . ' (' . $type_name . ')';
                }
            }

            // Only show storage selector if there are multiple options
            if (count($available_storage) > 1):
            ?>
            <select name="storage_selector" id="storage_selector" class="form-select">
                <?php
                foreach ($available_storage as $storage_id => $storage_name) {
                    $selected = ($default_storage == $storage_id) ? 'selected' : '';
                    echo '<option value="' . html_output($storage_id) . '" ' . $selected . '>' . $storage_name . '</option>';
                }
                ?>
            </select>
            <?php elseif (count($available_storage) == 1):
                // Only one option available, show it as read-only
                $only_storage_id = array_key_first($available_storage);
                $only_storage_name = $available_storage[$only_storage_id];
            ?>
            <input type="hidden" name="storage_selector" value="<?php echo html_output($only_storage_id); ?>">
            <div class="alert alert-info mb-0">
                <i class="fa fa-info-circle"></i> <?php echo sprintf(__('Files will be stored in: %s', 'cftp_admin'), '<strong>' . $only_storage_name . '</strong>'); ?>
            </div>
            <?php else:
                // No storage options available (shouldn't happen normally)
            ?>
            <div class="alert alert-warning mb-0">
                <i class="fa fa-exclamation-triangle"></i> <?php _e('No storage options are available. Please contact your administrator.', 'cftp_admin'); ?>
            </div>
            <?php endif; ?>
            <div class="form-text"><?php _e('Files will be stored in the selected destination.', 'cftp_admin'); ?></div>
        </div>
    </div>
<?php endif; ?>

<?php if ($show_encryption_option || $encryption_required): ?>
    <div class="ps-card">
        <div class="ps-card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="mb-0"><?php _e('File Encryption', 'cftp_admin'); ?></h4>
                <?php if (current_user_can('edit_settings')): ?>
                <a href="<?php echo BASE_URI; ?>options.php?section=encryption" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="fa fa-cog"></i> <?php _e('Manage', 'cftp_admin'); ?>
                </a>
                <?php endif; ?>
            </div>
            <?php if ($encryption_required): ?>
                <div class="alert alert-info mb-0">
                    <i class="fa fa-lock"></i> <?php _e('File encryption is required for all uploads. Your files will be automatically encrypted at rest on the server.', 'cftp_admin'); ?>
                </div>
            <?php else: ?>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="encrypt_file_checkbox" id="encrypt_file_checkbox" value="1" <?php echo ($encryption_enabled) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="encrypt_file_checkbox">
                        <i class="fa fa-lock"></i> <?php _e('Encrypt files on server', 'cftp_admin'); ?>
                    </label>
                </div>
                <div class="form-text">
                    <?php _e('Files will be encrypted at rest using AES-256-GCM encryption.', 'cftp_admin'); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
