<?php
/**
 * Shows a list of files found in external storage (S3, etc.) that
 * are not yet in the database, allowing them to be imported
 * into ProjectSend for management.
 */
require_once 'bootstrap.php';
redirect_if_not_logged_in();

// Check for system administration permissions
if (!current_user_can('edit_settings')) {
    exit_with_error_code(403);
}

$active_nav = 'files';
$page_title = __('Import external files', 'cftp_admin');
$page_id = 'import_external';

global $flash;
$integrations_handler = new \ProjectSend\Classes\Integrations();

// Handle form submissions
if (isset($_POST['action'])) {
    // Validate CSRF token
    if (!validateCsrfToken()) {
        $flash->error(__('Invalid security token. Please try again.', 'cftp_admin'));
    } else if (!empty($_POST['files']) && !empty($_POST['integration_id'])) {
        $integration_id = (int)$_POST['integration_id'];
        $selected_files = $_POST['files'];
        $integration = $integrations_handler->getById($integration_id);

        if (!$integration) {
            $flash->error(__('Integration not found.', 'cftp_admin'));
        } else {
            switch ($_POST['action']) {
                case 'import':
                    $storage = $integrations_handler->createStorageInstance($integration);
                    if (!$storage) {
                        $flash->error(__('Failed to initialize storage connection.', 'cftp_admin'));
                        break;
                    }

                    $imported_count = 0;
                    $errors = [];
                    $preserve_folders = !empty($_POST['preserve_folders']);
                    $folder_importer = null;
                    $created_folders_count = 0;

                    // Initialize folder importer if folder preservation is enabled
                    if ($preserve_folders) {
                        $folder_importer = new \ProjectSend\Classes\FolderStructureImporter(CURRENT_USER_ID);
                    }

                    foreach ($selected_files as $file_key) {
                        // Get file metadata from external storage
                        $metadata = $storage->getFileMetadata($file_key);
                        if (!$metadata) {
                            $errors[] = sprintf(__('Could not get metadata for %s', 'cftp_admin'), $file_key);
                            continue;
                        }

                        // Create new file record using Files class
                        $file = new \ProjectSend\Classes\Files();

                        // Set up external file properties (instead of moveToUploadDirectory)
                        $file->setExternalFileProperties($file_key, $metadata, $integration_id, $integration['type']);

                        // Set additional external storage properties
                        $file->bucket_name = $storage->getBucketName();

                        // Handle folder structure if enabled
                        if ($preserve_folders && $folder_importer) {
                            $import_result = $folder_importer->importPath($file_key, CURRENT_USER_ID);
                            if ($import_result['folder_id']) {
                                $file->folder_id = $import_result['folder_id'];
                            }
                        }

                        // Set file properties
                        $file->title = $file->filename_original;
                        $file->description = sprintf(__('Imported from %s', 'cftp_admin'), $integration['name']);

                        // Set defaults like import-orphans does
                        $file->setDefaults();

                        // Add to database using the Files class method
                        $result = $file->addToDatabase();

                        if ($result['status'] === 'success') {
                            $imported_count++;
                        } else {
                            $errors[] = sprintf(__('Failed to import %s: %s', 'cftp_admin'), basename($file_key), $result['message']);
                        }
                    }

                    // Get count of created folders
                    if ($preserve_folders && $folder_importer) {
                        $created_folders = $folder_importer->getCreatedFolders();
                        $created_folders_count = count($created_folders);
                    }

                    // Show results
                    if ($imported_count > 0) {
                        $success_message = sprintf(__('Successfully imported %d files.', 'cftp_admin'), $imported_count);
                        if ($preserve_folders && $created_folders_count > 0) {
                            $success_message .= ' ' . sprintf(__('Created %d new folders.', 'cftp_admin'), $created_folders_count);
                        }
                        $flash->success($success_message);
                    }

                    if (!empty($errors)) {
                        foreach ($errors as $error) {
                            $flash->error($error);
                        }
                    }

                    break;
            }
        }
    } else {
        $flash->error(__('Please select files and an integration.', 'cftp_admin'));
    }
}

// Get available integrations
$integrations = $integrations_handler->getAll(true); // Only active integrations

// Get files from selected integration
$external_files = [];
$selected_integration = null;

if (!empty($_GET['integration']) || !empty($_POST['integration_id'])) {
    $integration_id = !empty($_GET['integration']) ? (int)$_GET['integration'] : (int)$_POST['integration_id'];
    $selected_integration = $integrations_handler->getById($integration_id);

    if ($selected_integration && $selected_integration['active']) {
        $storage = $integrations_handler->createStorageInstance($selected_integration);
        if ($storage) {
            $storage_result = $storage->listFiles();
            if ($storage_result['success']) {
                $external_files = $storage_result['files'];

                // Filter out files that are already in the database
                $existing_keys = [];
                $query = "SELECT external_path FROM " . TABLE_FILES . " WHERE integration_id = :integration_id AND storage_type != 'local'";
                global $dbh;
                $statement = $dbh->prepare($query);
                $statement->bindParam(':integration_id', $integration_id, \PDO::PARAM_INT);
                $statement->execute();
                while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    $existing_keys[] = $row['external_path'];
                }

                // Remove already imported files
                $external_files = array_filter($external_files, function($file) use ($existing_keys) {
                    return !in_array($file['key'], $existing_keys);
                });
            } else {
                $flash->error(__('Failed to list external files: ', 'cftp_admin') . $storage_result['message']);
            }
        } else {
            $flash->error(__('Failed to connect to external storage.', 'cftp_admin'));
        }
    }
}

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="ps-card">
            <div class="ps-card-body">
                <h3><?php _e('How It Works', 'cftp_admin'); ?></h3>
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center p-3">
                            <h6><?php _e('1. Connect', 'cftp_admin'); ?></h6>
                            <p class="text-muted small">
                                <?php _e('Select an external storage integration to scan for files.', 'cftp_admin'); ?>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3">
                            <h6><?php _e('2. Discover', 'cftp_admin'); ?></h6>
                            <p class="text-muted small">
                                <?php _e('We scan your external storage for files not yet in ProjectSend.', 'cftp_admin'); ?>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3">
                            <h6><?php _e('3. Import', 'cftp_admin'); ?></h6>
                            <p class="text-muted small">
                                <?php _e('Select and import files to make them available in ProjectSend.', 'cftp_admin'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="ps-card">
            <div class="ps-card-body">
                <?php if (empty($integrations)): ?>
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        <?php _e('No external storage integrations found.', 'cftp_admin'); ?>
                        <a href="integrations.php" class="alert-link"><?php _e('Configure integrations first', 'cftp_admin'); ?></a>
                    </div>
                <?php else: ?>

                    <!-- Integration Selection -->
                    <div class="mb-4">
                        <form method="get" class="row align-items-end">
                            <div class="col-md-6">
                                <label for="integration" class="form-label"><?php _e('Integration', 'cftp_admin'); ?></label>
                                <select name="integration" id="integration" class="form-select" required>
                                    <option value=""><?php _e('Choose integration...', 'cftp_admin'); ?></option>
                                    <?php foreach ($integrations as $integration): ?>
                                        <option value="<?php echo $integration['id']; ?>"
                                                <?php echo ($selected_integration && $selected_integration['id'] == $integration['id']) ? 'selected' : ''; ?>>
                                            <?php echo html_output($integration['name']); ?>
                                            (<?php echo ucfirst($integration['type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-search"></i> <?php _e('List Files', 'cftp_admin'); ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if ($selected_integration): ?>
                        <div class="alert alert-info">
                            <strong><?php _e('Integration:', 'cftp_admin'); ?></strong> <?php echo html_output($selected_integration['name']); ?>
                            (<?php echo ucfirst($selected_integration['type']); ?>)
                        </div>

                        <?php if (empty($external_files)): ?>
                            <div class="alert alert-warning">
                                <i class="fa fa-info-circle"></i>
                                <?php _e('No new external files found to import.', 'cftp_admin'); ?>
                            </div>
                        <?php else: ?>

                            <form method="post">
                                <input type="hidden" name="integration_id" value="<?php echo $selected_integration['id']; ?>">
                                <?php addCsrf(); ?>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5><?php _e('External Files Available for Import', 'cftp_admin'); ?></h5>
                                    <div>
                                        <button type="button" id="select_all" class="btn btn-sm btn-outline-secondary">
                                            <?php _e('Select All', 'cftp_admin'); ?>
                                        </button>
                                        <button type="button" id="select_none" class="btn btn-sm btn-outline-secondary">
                                            <?php _e('Select None', 'cftp_admin'); ?>
                                        </button>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-striped" id="external_files_list">
                                        <thead>
                                            <tr>
                                                <th width="30">
                                                    <input type="checkbox" id="select_all_checkbox">
                                                </th>
                                                <th><?php _e('File Name', 'cftp_admin'); ?></th>
                                                <th><?php _e('Folder Path', 'cftp_admin'); ?></th>
                                                <th><?php _e('Size', 'cftp_admin'); ?></th>
                                                <th><?php _e('Modified', 'cftp_admin'); ?></th>
                                                <th><?php _e('Storage Class', 'cftp_admin'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $folder_preview_helper = new \ProjectSend\Classes\FolderStructureImporter();
                                            foreach ($external_files as $file):
                                                $parsed_path = $folder_preview_helper->parsePath($file['key']);
                                                $folder_path = !empty($parsed_path['path_components'])
                                                    ? implode(' / ', $parsed_path['path_components'])
                                                    : __('Root', 'cftp_admin');
                                            ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="files[]" value="<?php echo html_output($file['key']); ?>" class="file_checkbox">
                                                    </td>
                                                    <td>
                                                        <strong><?php echo html_output(basename($file['key'])); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo html_output($file['key']); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info folder-path" data-folder-path="<?php echo html_output($folder_path); ?>">
                                                            <?php echo html_output($folder_path); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php echo format_file_size($file['size']); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo date(get_option('timeformat'), strtotime($file['last_modified'])); ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <?php echo html_output($file['storage_class'] ?? 'STANDARD'); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Import Options -->
                                <div class="card mt-4 mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php _e('Import Options', 'cftp_admin'); ?></h6>

                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="preserve_folders" id="preserve_folders" value="1" checked>
                                            <label class="form-check-label" for="preserve_folders">
                                                <strong><?php _e('Preserve folder structure from S3 paths', 'cftp_admin'); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php _e('Automatically create folders based on the S3 file paths. For example, "Central_R_D/CoE_Colombes/Documents/file.pdf" will create nested folders and place the file in "Documents".', 'cftp_admin'); ?>
                                                </small>
                                            </label>
                                        </div>

                                        <!-- Folder Preview Area -->
                                        <div id="folder_preview" class="mt-3" style="display: none;">
                                            <div class="alert alert-info">
                                                <h6><i class="fa fa-folder-open"></i> <?php _e('Folder Structure Preview', 'cftp_admin'); ?></h6>
                                                <p class="mb-2"><strong id="preview_file_count">0</strong> <?php _e('files selected', 'cftp_admin'); ?></p>
                                                <p class="mb-0"><strong id="preview_folder_count">0</strong> <?php _e('unique folders will be created', 'cftp_admin'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-end mt-3">
                                    <button type="submit" name="action" value="import" class="btn btn-success">
                                        <i class="fa fa-download"></i> <?php _e('Import Selected Files', 'cftp_admin'); ?>
                                    </button>
                                </div>
                            </form>

                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<script>
$(document).ready(function() {
    // Select all/none functionality
    $('#select_all, #select_all_checkbox').on('click', function() {
        $('.file_checkbox').prop('checked', true);
        updateFolderPreview();
    });

    $('#select_none').on('click', function() {
        $('.file_checkbox').prop('checked', false);
        updateFolderPreview();
    });

    // Update preview when checkboxes change
    $('.file_checkbox').on('change', function() {
        updateFolderPreview();
    });

    // Toggle folder path column visibility
    $('#preserve_folders').on('change', function() {
        if ($(this).is(':checked')) {
            $('.folder-path').parent().show();
            $('th:contains("<?php _e('Folder Path', 'cftp_admin'); ?>")').show();
            updateFolderPreview();
        } else {
            $('.folder-path').parent().hide();
            $('th:contains("<?php _e('Folder Path', 'cftp_admin'); ?>")').hide();
            $('#folder_preview').hide();
        }
    });

    // Initial preview update
    updateFolderPreview();

    /**
     * Update folder preview based on selected files
     */
    function updateFolderPreview() {
        const preserveFolders = $('#preserve_folders').is(':checked');

        if (!preserveFolders) {
            $('#folder_preview').hide();
            return;
        }

        // Count selected files
        const selectedCheckboxes = $('.file_checkbox:checked');
        const fileCount = selectedCheckboxes.length;

        if (fileCount === 0) {
            $('#folder_preview').hide();
            return;
        }

        // Extract unique folder paths from selected files
        const uniqueFolders = new Set();

        selectedCheckboxes.each(function() {
            const row = $(this).closest('tr');
            const folderPath = row.find('.folder-path').data('folder-path');

            if (folderPath && folderPath !== '<?php _e('Root', 'cftp_admin'); ?>') {
                // Add each level of the path
                const pathParts = folderPath.split(' / ');
                let currentPath = '';

                pathParts.forEach(function(part) {
                    currentPath += (currentPath ? ' / ' : '') + part;
                    uniqueFolders.add(currentPath);
                });
            }
        });

        const folderCount = uniqueFolders.size;

        // Update preview
        $('#preview_file_count').text(fileCount);
        $('#preview_folder_count').text(folderCount);

        if (folderCount > 0) {
            $('#folder_preview').show();
        } else {
            $('#folder_preview').hide();
        }
    }
});
</script>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
?>