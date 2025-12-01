<?php
/**
 * File information editor
 */
define('IS_FILE_EDITOR', true);

require_once 'bootstrap.php';
check_access_enhanced(['edit_files']);

$active_nav = 'files';

$page_title = __('Edit files', 'cftp_admin');

$page_id = 'file_editor';

define('CAN_INCLUDE_FILES', true);

// Get upload folder from form (passed from upload.php)
$upload_to_folder = isset($_POST['upload_to_folder']) && !empty($_POST['upload_to_folder']) ? (int)$_POST['upload_to_folder'] : null;

// Editable
$editable = [];
$files = explode(',', $_GET['ids']);
foreach ($files as $file_id) {
    if (is_numeric($file_id)) {
        if (user_can_edit_file(CURRENT_USER_ID, $file_id)) {
            $editable[] = (int)$file_id;
        }
    }
}

$saved_files = [];

// Fill the categories array that will be used on the form
$categories = [];
$get_categories = get_categories();

function custom_download_exists($link)
{
    global $dbh;
    $statement = $dbh->prepare('SELECT link, file_id FROM ' . TABLE_CUSTOM_DOWNLOADS . ' WHERE link=:link');
    $statement->bindParam(':link', $link);
    $statement->execute();
    return $statement->fetchColumn();
}

/**
 * Validate and sanitize custom download link
 * @param string $link The custom download link to validate
 * @return array Status and sanitized link or error message
 */
function validate_custom_download_link($link)
{
    // Remove any HTML tags and decode entities
    $link = strip_tags($link);
    $link = html_entity_decode($link, ENT_QUOTES, 'UTF-8');

    // Trim whitespace
    $link = trim($link);

    // Check length (1-255 characters)
    if (empty($link)) {
        return ['valid' => false, 'message' => __('Custom download link cannot be empty', 'cftp_admin')];
    }

    if (strlen($link) > 255) {
        return ['valid' => false, 'message' => __('Custom download link too long (max 255 characters)', 'cftp_admin')];
    }

    // Only allow alphanumeric characters, hyphens, underscores, and dots
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $link)) {
        return ['valid' => false, 'message' => __('Custom download link can only contain letters, numbers, dots, hyphens and underscores', 'cftp_admin')];
    }

    // Additional security: reject common XSS patterns
    $dangerous_patterns = [
        'javascript:', 'data:', 'vbscript:', 'onload', 'onerror',
        '<script', '</script', '&lt;script', '&lt;/script'
    ];

    $link_lower = strtolower($link);
    foreach ($dangerous_patterns as $pattern) {
        if (strpos($link_lower, strtolower($pattern)) !== false) {
            return ['valid' => false, 'message' => __('Custom download link contains invalid characters', 'cftp_admin')];
        }
    }

    return ['valid' => true, 'link' => $link];
}

function create_custom_download($link, $file_id, $client_id)
{
    global $dbh;

    // Validate and sanitize the link
    $validation = validate_custom_download_link($link);
    if (!$validation['valid']) {
        global $flash;
        $flash->error($validation['message']);
        return false;
    }

    $sanitized_link = $validation['link'];

    if (custom_download_exists($sanitized_link)) {
        $statement = $dbh->prepare('UPDATE ' . TABLE_CUSTOM_DOWNLOADS . ' SET file_id=:file_id, client_id=:client_id WHERE link=:link');
        $statement->bindParam(':link', $sanitized_link);
        $statement->bindParam(':file_id', $file_id, PDO::PARAM_INT);
        $statement->bindParam(':client_id', $client_id, PDO::PARAM_INT);
        $statement->execute();
        return true;
    }
    else {
        $statement = $dbh->prepare('INSERT INTO ' . TABLE_CUSTOM_DOWNLOADS . ' (link, file_id, client_id) VALUES (:link, :file_id, :client_id)');
        $statement->bindParam(':link', $sanitized_link);
        $statement->bindParam(':file_id', $file_id);
        $statement->bindParam(':client_id', $client_id, PDO::PARAM_INT);
        $statement->execute();
    }
    return true;
}

if (isset($_POST['save'])) {
    // Edit each file and its assignations
    $confirm = false;
    foreach ($_POST['file'] as $file) {
        $object = new \ProjectSend\Classes\Files($file['id']);
        if ($object->recordExists()) {
            if ($object->save($file) != false) {
                $saved_files[] = $file['id'];
            }
        }

        $custom_downloads = $file['custom_downloads'] ?? [];
        foreach ($custom_downloads as $custom_download) {
            global $dbh;

            if (custom_download_exists($custom_download["link"]) && (!isset($_GET['confirmed']) || !$_GET['confirmed'])) {
                $confirm = true;
                continue;
            }

            if ($custom_download['id']) {
                if ($custom_download['link']) {
                    if ($custom_download['link'] != $custom_download['id']) {
                        $statement = $dbh->prepare('UPDATE ' . TABLE_CUSTOM_DOWNLOADS . ' SET file_id=NULL WHERE link=:link');
                        $statement->bindParam(':link', $custom_download['id']);
                        $statement->execute();
                        if (create_custom_download($custom_download['link'], $file['id'], CURRENT_USER_ID)) {
                            global $flash;
                            $flash->warning(__('Updated existing custom link to point to this file.', 'cftp_admin'));
                        }
                    }
                }
                else { // remove file_id from custom download
                    $statement = $dbh->prepare('UPDATE ' . TABLE_CUSTOM_DOWNLOADS . ' SET file_id=NULL WHERE link=:link');
                    $statement->bindParam(':link', $custom_download['id']);
                    $statement->execute();
                }
            }
            else {
                if ($custom_download['link']) {
                    if (create_custom_download($custom_download['link'], $file['id'], CURRENT_USER_ID)) {
                        $flash->warning(__('Updated existing custom link to point to this file.', 'cftp_admin'));
                    }
                }
            }
        }
    }

    // Send the notifications
    if (get_option('notifications_send_when_saving_files') == '1') {
        $notifications = new \ProjectSend\Classes\EmailNotifications();
        $notifications->sendNotifications();
        if (!empty($notifications->getNotificationsSent())) {
            $flash->success(__('E-mail notifications have been sent.', 'cftp_admin'));
        }
        if (!empty($notifications->getNotificationsFailed())) {
            $flash->error(__("One or more notifications couldn't be sent.", 'cftp_admin'));
        }
        if (!empty($notifications->getNotificationsInactiveAccounts())) {
            if (current_role_in(['Client'])) {
                /**
                 * Clients do not need to know about the status of the
                 * creator's account. Show the ok message instead.
                 */
                $flash->success(__('E-mail notifications have been sent.', 'cftp_admin'));
            } else {
                $flash->warning(__('E-mail notifications for inactive clients were not sent.', 'cftp_admin'));
            }
        }
    } else {
        $flash->warning(__('E-mail notifications were not sent according to your settings. Make sure you have a cron job enabled if you need to send them.', 'cftp_admin'));
    }

    // Redirect
    $saved = implode(',', $saved_files);


    if ($confirm) {
        global $flash;
        $flash->success(__('Files saved successfully', 'cftp_admin'));
        $flash->warning(__('A custom link like this already exists, enter it again to override.', 'cftp_admin'));
        ps_redirect('files-edit.php?&ids=' . $saved . '&confirm=true');
    }
    else {
        $flash->success(__('Files saved successfully', 'cftp_admin'));
        ps_redirect('files-edit.php?&ids=' . $saved . '&saved=true');
    }
}

// Message
if (!empty($editable) && !isset($_GET['saved'])) {
    if (!current_role_in(['Client'])) {
        //$flash->info(__('You can skip assigning if you want. The files are retained and you may add them to clients or groups later.', 'cftp_admin'));
    }
}

// if (count($editable) > 1) {
//     // Header buttons
//     $header_action_buttons = [
//         [
//             'url' => '#',
//             'id' => 'files_collapse_all',
//             'icon' => 'fa fa-chevron-right',
//             'label' => __('Collapse all', 'cftp_admin'),
//         ],
//         [
//             'url' => '#',
//             'id' => 'files_expand_all',
//             'icon' => 'fa fa-chevron-down',
//             'label' => __('Expand all', 'cftp_admin'),
//         ],
//     ];
// }

// Include layout files
include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>
<div class="row">
    <div class="col-12">
        <?php
        // Saved files - Success view
        $saved_files = [];
        if (!empty($_GET['saved'])) {
            foreach ($editable as $file_id) {
                if (is_numeric($file_id)) {
                    $saved_files[] = $file_id;
                }
            }

            $file_count = count($saved_files);
        ?>
            <!-- Success Header -->
            <div class="upload-success-header mb-4">
                <div class="d-flex align-items-center justify-content-center flex-column text-center py-4">
                    <div class="success-icon mb-3">
                        <i class="fa fa-check-circle text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="mb-2"><?php echo sprintf(_n('%d File Uploaded Successfully', '%d Files Uploaded Successfully', $file_count, 'cftp_admin'), $file_count); ?></h3>
                    <p class="text-muted mb-0"><?php _e('Your files have been saved and are ready to use.', 'cftp_admin'); ?></p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="d-flex flex-wrap gap-2 justify-content-center mb-4">
                <a href="upload.php" class="btn btn-primary">
                    <i class="fa fa-cloud-upload"></i> <?php _e('Upload More Files', 'cftp_admin'); ?>
                </a>
                <a href="manage-files.php" class="btn btn-outline-secondary">
                    <i class="fa fa-folder"></i> <?php _e('Manage Files', 'cftp_admin'); ?>
                </a>
                <?php if (current_role_in(['Client'])): ?>
                <a href="<?php echo CLIENT_VIEW_FILE_LIST_URL; ?>" class="btn btn-outline-secondary">
                    <i class="fa fa-list"></i> <?php _e('View My Files', 'cftp_admin'); ?>
                </a>
                <?php endif; ?>
            </div>

            <!-- Uploaded Files Grid -->
            <div class="uploaded-files-grid">
                <h5 class="mb-3"><i class="fa fa-files-o"></i> <?php _e('Uploaded Files', 'cftp_admin'); ?></h5>
                <div class="row g-3">
                <?php
                foreach ($saved_files as $file_id) {
                    $file = new \ProjectSend\Classes\Files($file_id);
                    if ($file->recordExists()) {
                        // Determine file icon based on extension
                        $ext = strtolower($file->extension);
                        $icon_class = 'fa-file-o';
                        $icon_color = 'text-secondary';
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                            $icon_class = 'fa-file-image-o';
                            $icon_color = 'text-info';
                        } elseif (in_array($ext, ['pdf'])) {
                            $icon_class = 'fa-file-pdf-o';
                            $icon_color = 'text-danger';
                        } elseif (in_array($ext, ['doc', 'docx'])) {
                            $icon_class = 'fa-file-word-o';
                            $icon_color = 'text-primary';
                        } elseif (in_array($ext, ['xls', 'xlsx'])) {
                            $icon_class = 'fa-file-excel-o';
                            $icon_color = 'text-success';
                        } elseif (in_array($ext, ['ppt', 'pptx'])) {
                            $icon_class = 'fa-file-powerpoint-o';
                            $icon_color = 'text-warning';
                        } elseif (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
                            $icon_class = 'fa-file-archive-o';
                            $icon_color = 'text-warning';
                        } elseif (in_array($ext, ['mp4', 'avi', 'mov', 'wmv', 'webm'])) {
                            $icon_class = 'fa-file-video-o';
                            $icon_color = 'text-purple';
                        } elseif (in_array($ext, ['mp3', 'wav', 'ogg', 'flac'])) {
                            $icon_class = 'fa-file-audio-o';
                            $icon_color = 'text-pink';
                        } elseif (in_array($ext, ['txt', 'rtf'])) {
                            $icon_class = 'fa-file-text-o';
                            $icon_color = 'text-secondary';
                        } elseif (in_array($ext, ['html', 'css', 'js', 'php', 'py', 'java'])) {
                            $icon_class = 'fa-file-code-o';
                            $icon_color = 'text-dark';
                        }
                ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card uploaded-file-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="file-icon <?php echo $icon_color; ?>">
                                        <i class="fa <?php echo $icon_class; ?>" style="font-size: 2.5rem;"></i>
                                    </div>
                                    <div class="file-details flex-grow-1 min-width-0">
                                        <h6 class="file-title mb-1 text-truncate" title="<?php echo html_output($file->title); ?>">
                                            <?php echo html_output($file->title); ?>
                                        </h6>
                                        <p class="file-name text-muted small mb-2 text-truncate" title="<?php echo html_output($file->filename_original); ?>">
                                            <?php echo html_output($file->filename_original); ?>
                                        </p>
                                        <div class="file-meta d-flex flex-wrap gap-2 mb-2">
                                            <span class="badge bg-secondary"><?php echo strtoupper($file->extension); ?></span>
                                            <span class="badge bg-light text-dark"><?php echo $file->size_formatted; ?></span>
                                            <?php if ($file->public == '1'): ?>
                                            <span class="badge bg-success"><?php _e('Public', 'cftp_admin'); ?></span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary"><?php _e('Private', 'cftp_admin'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($file->description)): ?>
                                        <p class="file-description text-muted small mb-0 text-truncate-2" title="<?php echo html_output($file->description); ?>">
                                            <?php echo html_output($file->description); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-top">
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="files-edit.php?ids=<?php echo $file->id; ?>" class="btn btn-sm btn-outline-primary flex-grow-1" title="<?php _e('Edit file', 'cftp_admin'); ?>">
                                        <i class="fa fa-pencil"></i> <?php _e('Edit', 'cftp_admin'); ?>
                                    </a>
                                    <a href="<?php echo $file->download_link; ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="<?php _e('Download file', 'cftp_admin'); ?>">
                                        <i class="fa fa-download"></i>
                                    </a>
                                    <?php if ($file->public == '1'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-success public_link" data-type="file" data-public-url="<?php echo $file->public_url; ?>" data-title="<?php echo html_output($file->title); ?>" title="<?php _e('Copy public link', 'cftp_admin'); ?>">
                                        <i class="fa fa-link"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php
                    }
                }
                ?>
                </div>
            </div>

            <style>
            .upload-success-header {
                background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(255, 255, 255, 0) 100%);
                border-radius: 12px;
                border: 1px solid rgba(40, 167, 69, 0.2);
            }
            .uploaded-file-card {
                border-radius: 8px;
                border: 1px solid var(--border-color, #dee2e6);
                transition: all 0.2s ease;
            }
            .uploaded-file-card:hover {
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                transform: translateY(-2px);
            }
            .file-icon {
                flex-shrink: 0;
                width: 50px;
                text-align: center;
            }
            .file-details {
                overflow: hidden;
            }
            .file-title {
                font-weight: 600;
            }
            .text-truncate-2 {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            .min-width-0 {
                min-width: 0;
            }
            [data-theme="dark"] .upload-success-header {
                background: linear-gradient(135deg, rgba(40, 167, 69, 0.15) 0%, rgba(0, 0, 0, 0) 100%);
                border-color: rgba(40, 167, 69, 0.3);
            }
            [data-theme="dark"] .uploaded-file-card {
                background: var(--bg-card);
                border-color: var(--border-color);
            }
            [data-theme="dark"] .uploaded-file-card:hover {
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            }
            </style>
        <?php
        } else {
            // Generate the table of files ready to be edited
            if (!empty($editable)) {
                include_once FORMS_DIR . DS . 'file_editor.php';
            } else {
                // No files can be edited - show error message
                echo '<div class="alert alert-warning">';
                echo '<h4>' . __('No files available for editing', 'cftp_admin') . '</h4>';
                echo '<p>' . __('You do not have permission to edit the requested files, or the files do not exist.', 'cftp_admin') . '</p>';
                echo '<a href="manage-files.php" class="btn btn-primary">' . __('Back to Files', 'cftp_admin') . '</a>';
                echo '</div>';
            }
        }
        ?>
    </div>
</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
