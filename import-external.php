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
                    // Increase time limit for bulk imports
                    set_time_limit(600); // 10 minutes
                    ini_set('memory_limit', '512M');

                    $storage = $integrations_handler->createStorageInstance($integration);
                    if (!$storage) {
                        $flash->error(__('Failed to initialize storage connection.', 'cftp_admin'));
                        break;
                    }

                    $imported_count = 0;
                    $errors = [];
                    $skipped_count = 0;
                    $preserve_folders = !empty($_POST['preserve_folders']);
                    $folder_importer = null;
                    $created_folders_count = 0;

                    // Initialize folder importer if folder preservation is enabled
                    if ($preserve_folders) {
                        $folder_importer = new \ProjectSend\Classes\FolderStructureImporter(CURRENT_USER_ID);
                    }

                    // Build metadata cache from current page files to avoid extra API calls
                    $metadata_cache = [];
                    if (isset($_POST['files_metadata'])) {
                        $metadata_cache = json_decode($_POST['files_metadata'], true) ?? [];
                    }

                    foreach ($selected_files as $file_key) {
                        // Always fetch full metadata from S3 to get custom metadata and tags
                        // The basic cache doesn't include X-Amz-Meta-* headers and tags
                        $metadata = $storage->getFileMetadata($file_key);

                        if (!$metadata) {
                            $errors[] = sprintf(__('Could not get metadata for %s', 'cftp_admin'), $file_key);
                            error_log("Import: Failed to get metadata for $file_key");
                            continue;
                        }

                        // Log full metadata structure
                        $has_custom_meta = isset($metadata['metadata']) && is_array($metadata['metadata']) && !empty($metadata['metadata']);
                        $has_tags = isset($metadata['tags']) && is_array($metadata['tags']) && !empty($metadata['tags']);
                        error_log("Import: File $file_key - Full metadata: " . json_encode($metadata));
                        error_log("Import: File $file_key - Custom metadata: " . ($has_custom_meta ? count($metadata['metadata']) . ' fields = ' . json_encode($metadata['metadata']) : 'none'));
                        error_log("Import: File $file_key - Tags: " . ($has_tags ? count($metadata['tags']) . ' tags = ' . json_encode($metadata['tags']) : 'none'));

                        // Skip folders (objects ending with /)
                        if (substr($file_key, -1) === '/') {
                            $skipped_count++;
                            continue;
                        }

                        // Skip if size is 0 (likely a folder marker)
                        if (isset($metadata['size']) && $metadata['size'] == 0) {
                            $skipped_count++;
                            continue;
                        }

                        try {
                            // Create new file record using Files class
                            $file = new \ProjectSend\Classes\Files();

                            // Set up external file properties (instead of moveToUploadDirectory)
                            $file->setExternalFileProperties($file_key, $metadata, $integration_id, $integration['type']);

                            // Set additional external storage properties
                            $file->bucket_name = $storage->getBucketName();

                            // Set defaults first (before custom properties)
                            $file->setDefaults();

                            // Handle folder structure if enabled
                            if ($preserve_folders && $folder_importer) {
                                $import_result = $folder_importer->importPath($file_key, CURRENT_USER_ID);
                                if ($import_result['folder_id']) {
                                    $file->folder_id = $import_result['folder_id'];
                                }
                            }

                            // Set file properties (after defaults so they don't get overwritten)
                            $file->title = $file->filename_original;
                            $file->description = sprintf(__('Imported from %s', 'cftp_admin'), $integration['name']);

                            // Log what's about to be saved
                            error_log("Import: About to save file $file_key - s3_metadata = " . ($file->s3_metadata ? strlen($file->s3_metadata) . ' bytes' : 'NULL/EMPTY'));

                            // Add to database using the Files class method
                            $result = $file->addToDatabase();

                            if ($result['status'] === 'success') {
                                $imported_count++;
                            } else {
                                $errors[] = sprintf(__('Failed to import %s: %s', 'cftp_admin'), basename($file_key), $result['message']);
                            }
                        } catch (\Exception $e) {
                            $errors[] = sprintf(__('Error importing %s: %s', 'cftp_admin'), basename($file_key), $e->getMessage());
                            error_log("Import error for $file_key: " . $e->getMessage());
                        }

                        // Flush output to prevent timeout
                        if (function_exists('ob_flush')) {
                            @ob_flush();
                        }
                        @flush();
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
                        if ($skipped_count > 0) {
                            $success_message .= ' ' . sprintf(__('Skipped %d items (folders or empty files).', 'cftp_admin'), $skipped_count);
                        }
                        $flash->success($success_message);
                    }

                    if (!empty($errors)) {
                        if (count($errors) > 10) {
                            // Show only first 10 errors to avoid cluttering the screen
                            $flash->error(sprintf(__('Import completed with %d errors. Showing first 10:', 'cftp_admin'), count($errors)));
                            $errors = array_slice($errors, 0, 10);
                        }
                        foreach ($errors as $error) {
                            $flash->error($error);
                        }
                    }

                    if ($imported_count == 0 && empty($errors) && $skipped_count == 0) {
                        $flash->warning(__('No files were imported. Please select files and try again.', 'cftp_admin'));
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
$pagination_info = [
    'total_files_in_bucket' => 0,
    'displayed_files' => 0,
    'has_more' => false,
    'next_token' => null,
    'page' => 1,
    'per_page' => 100
];

if (!empty($_GET['integration']) || !empty($_POST['integration_id'])) {
    $integration_id = !empty($_GET['integration']) ? (int)$_GET['integration'] : (int)$_POST['integration_id'];
    $selected_integration = $integrations_handler->getById($integration_id);

    // Pagination parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = isset($_GET['per_page']) ? max(10, min(1000, (int)$_GET['per_page'])) : 100;

    // Initialize session cache for pagination tokens
    $tokens_cache_key = 'pagination_tokens_' . $integration_id . '_' . $per_page;
    if (!isset($_SESSION[$tokens_cache_key])) {
        $_SESSION[$tokens_cache_key] = [1 => null]; // Page 1 has no token
    }

    // Reset cache if per_page changed
    if (isset($_GET['per_page']) && isset($_SESSION['last_per_page_' . $integration_id]) && $_SESSION['last_per_page_' . $integration_id] != $per_page) {
        $_SESSION[$tokens_cache_key] = [1 => null];
    }
    $_SESSION['last_per_page_' . $integration_id] = $per_page;

    $pagination_info['page'] = $page;
    $pagination_info['per_page'] = $per_page;

    if ($selected_integration && $selected_integration['active']) {
        $storage = $integrations_handler->createStorageInstance($selected_integration);
        if ($storage) {
            // Get total file count (cached in session for performance)
            $cache_key = 'total_files_' . $integration_id;
            if (!isset($_SESSION[$cache_key]) || isset($_GET['refresh_count'])) {
                $count_result = $storage->countFiles();
                if ($count_result['success']) {
                    $_SESSION[$cache_key] = $count_result['count'];
                    $pagination_info['total_files_in_bucket'] = $count_result['count'];
                }
            } else {
                $pagination_info['total_files_in_bucket'] = $_SESSION[$cache_key];
            }

            // Get continuation token for requested page
            $continuation_token = null;
            if ($page > 1) {
                // Check if we have the token for this page
                if (isset($_SESSION[$tokens_cache_key][$page])) {
                    $continuation_token = $_SESSION[$tokens_cache_key][$page];
                } else {
                    // We need to build up tokens by fetching previous pages
                    // Start from the last known page
                    $last_known_page = max(array_keys($_SESSION[$tokens_cache_key]));

                    for ($p = $last_known_page; $p < $page; $p++) {
                        $token = $_SESSION[$tokens_cache_key][$p] ?? null;
                        $result = $storage->listFiles('', $per_page, $token);

                        if ($result['success'] && isset($result['next_token'])) {
                            $_SESSION[$tokens_cache_key][$p + 1] = $result['next_token'];
                        } else {
                            // Can't go further
                            break;
                        }
                    }

                    $continuation_token = $_SESSION[$tokens_cache_key][$page] ?? null;
                }
            }

            // List files for current page
            $storage_result = $storage->listFiles('', $per_page, $continuation_token);
            if ($storage_result['success']) {
                $external_files = $storage_result['files'];
                $pagination_info['has_more'] = $storage_result['truncated'] ?? false;
                $pagination_info['next_token'] = $storage_result['next_token'] ?? null;

                // Cache the token for next page
                if ($pagination_info['next_token']) {
                    $_SESSION[$tokens_cache_key][$page + 1] = $pagination_info['next_token'];
                }

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

                // Fetch full metadata for each remaining file to display in collapsible sections
                foreach ($external_files as &$file) {
                    $full_metadata = $storage->getFileMetadata($file['key']);
                    if ($full_metadata && isset($full_metadata['metadata'])) {
                        $file['custom_metadata'] = $full_metadata['metadata'];
                    }
                    if ($full_metadata && isset($full_metadata['tags'])) {
                        $file['tags'] = $full_metadata['tags'];
                    }
                }
                unset($file); // Break reference

                $pagination_info['displayed_files'] = count($external_files);
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
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <strong><?php _e('Integration:', 'cftp_admin'); ?></strong> <?php echo html_output($selected_integration['name']); ?>
                                    (<?php echo ucfirst($selected_integration['type']); ?>)
                                </div>
                                <div class="col-md-6 text-end">
                                    <strong><?php _e('Total files in bucket:', 'cftp_admin'); ?></strong>
                                    <span class="badge bg-primary">
                                        <?php echo number_format($pagination_info['total_files_in_bucket']); ?>
                                    </span>
                                    <?php if ($pagination_info['total_files_in_bucket'] > 0): ?>
                                        <a href="?integration=<?php echo $selected_integration['id']; ?>&refresh_count=1" class="btn btn-sm btn-outline-secondary ms-2" title="<?php _e('Refresh count', 'cftp_admin'); ?>">
                                            <i class="fa fa-refresh"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($pagination_info['displayed_files'] > 0): ?>
                            <!-- Pagination Controls Top -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <strong><?php _e('Showing:', 'cftp_admin'); ?></strong>
                                    <?php echo number_format($pagination_info['displayed_files']); ?>
                                    <?php _e('files on this page', 'cftp_admin'); ?>
                                </div>
                                <div>
                                    <label for="per_page_select" class="me-2"><?php _e('Files per page:', 'cftp_admin'); ?></label>
                                    <select id="per_page_select" class="form-select form-select-sm d-inline-block" style="width: auto;" onchange="changePerPage(this.value)">
                                        <option value="50" <?php echo $pagination_info['per_page'] == 50 ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?php echo $pagination_info['per_page'] == 100 ? 'selected' : ''; ?>>100</option>
                                        <option value="250" <?php echo $pagination_info['per_page'] == 250 ? 'selected' : ''; ?>>250</option>
                                        <option value="500" <?php echo $pagination_info['per_page'] == 500 ? 'selected' : ''; ?>>500</option>
                                        <option value="1000" <?php echo $pagination_info['per_page'] == 1000 ? 'selected' : ''; ?>>1000</option>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($external_files)): ?>
                            <div class="alert alert-warning">
                                <i class="fa fa-info-circle"></i>
                                <?php _e('No new external files found to import.', 'cftp_admin'); ?>
                            </div>
                        <?php else: ?>

                            <form method="post">
                                <input type="hidden" name="integration_id" value="<?php echo $selected_integration['id']; ?>">
                                <?php addCsrf(); ?>

                                <?php
                                // Cache file metadata in form to avoid repeated API calls during import
                                $metadata_json = [];
                                foreach ($external_files as $file) {
                                    $metadata_json[$file['key']] = [
                                        'size' => $file['size'],
                                        'last_modified' => $file['last_modified'],
                                        'etag' => $file['etag'] ?? '',
                                        'storage_class' => $file['storage_class'] ?? 'STANDARD'
                                    ];
                                }
                                ?>
                                <input type="hidden" name="files_metadata" value="<?php echo htmlspecialchars(json_encode($metadata_json), ENT_QUOTES, 'UTF-8'); ?>">

                                <?php if ($pagination_info['displayed_files'] > 50): ?>
                                    <div class="alert alert-warning">
                                        <i class="fa fa-info-circle"></i>
                                        <strong><?php _e('Bulk Import Notice:', 'cftp_admin'); ?></strong>
                                        <?php _e('You are importing a large number of files. This process may take several minutes. Please do not close this page until the import is complete.', 'cftp_admin'); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5><?php _e('External Files Available for Import', 'cftp_admin'); ?></h5>
                                    <div>
                                        <button type="button" id="select_all_page" class="btn btn-sm btn-outline-secondary">
                                            <i class="fa fa-check-square-o"></i> <?php _e('Select All on Page', 'cftp_admin'); ?>
                                        </button>
                                        <button type="button" id="select_all_files" class="btn btn-sm btn-primary">
                                            <i class="fa fa-check-square"></i> <?php _e('Select ALL Files', 'cftp_admin'); ?> (<?php echo number_format($pagination_info['total_files_in_bucket']); ?>)
                                        </button>
                                        <button type="button" id="select_none" class="btn btn-sm btn-outline-secondary">
                                            <i class="fa fa-square-o"></i> <?php _e('Select None', 'cftp_admin'); ?>
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
                                                <th width="30"></th>
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
                                            $row_index = 0;
                                            foreach ($external_files as $file):
                                                $row_index++;
                                                $parsed_path = $folder_preview_helper->parsePath($file['key']);
                                                $folder_path = !empty($parsed_path['path_components'])
                                                    ? implode(' / ', $parsed_path['path_components'])
                                                    : __('Root', 'cftp_admin');
                                                $has_metadata = (!empty($file['custom_metadata']) || !empty($file['tags']));
                                                $metadata_row_id = 'metadata_row_' . $row_index;
                                            ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="files[]" value="<?php echo html_output($file['key']); ?>" class="file_checkbox">
                                                    </td>
                                                    <td>
                                                        <?php if ($has_metadata): ?>
                                                            <button type="button" class="btn btn-sm btn-link p-0 toggle-metadata" data-target="<?php echo $metadata_row_id; ?>" title="<?php _e('Show/Hide Metadata', 'cftp_admin'); ?>">
                                                                <i class="fa fa-chevron-down"></i>
                                                            </button>
                                                        <?php endif; ?>
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
                                                <?php if ($has_metadata): ?>
                                                <tr id="<?php echo $metadata_row_id; ?>" class="metadata-row" style="display: none;">
                                                    <td colspan="7">
                                                        <div class="card bg-light">
                                                            <div class="card-body p-3">
                                                                <h6 class="mb-3"><i class="fa fa-info-circle"></i> <?php _e('S3 Metadata', 'cftp_admin'); ?></h6>

                                                                <?php if (!empty($file['custom_metadata'])): ?>
                                                                    <div class="mb-3">
                                                                        <strong><?php _e('Custom Metadata (X-Amz-Meta-*):', 'cftp_admin'); ?></strong>
                                                                        <table class="table table-sm table-bordered mt-2 mb-0">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th width="30%"><?php _e('Key', 'cftp_admin'); ?></th>
                                                                                    <th><?php _e('Value', 'cftp_admin'); ?></th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php foreach ($file['custom_metadata'] as $key => $value): ?>
                                                                                <tr>
                                                                                    <td><code><?php echo html_output($key); ?></code></td>
                                                                                    <td><?php echo html_output($value); ?></td>
                                                                                </tr>
                                                                                <?php endforeach; ?>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <?php if (!empty($file['tags'])): ?>
                                                                    <div>
                                                                        <strong><?php _e('Object Tags:', 'cftp_admin'); ?></strong>
                                                                        <table class="table table-sm table-bordered mt-2 mb-0">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th width="30%"><?php _e('Tag', 'cftp_admin'); ?></th>
                                                                                    <th><?php _e('Value', 'cftp_admin'); ?></th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php foreach ($file['tags'] as $tag_key => $tag_value): ?>
                                                                                <tr>
                                                                                    <td><code><?php echo html_output($tag_key); ?></code></td>
                                                                                    <td><?php echo html_output($tag_value); ?></td>
                                                                                </tr>
                                                                                <?php endforeach; ?>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination Controls Bottom -->
                                <?php if ($pagination_info['has_more'] || $pagination_info['page'] > 1): ?>
                                    <div class="d-flex justify-content-center mt-4 mb-3">
                                        <nav>
                                            <ul class="pagination">
                                                <?php
                                                // Calculate total pages (estimate)
                                                $total_pages = ceil($pagination_info['total_files_in_bucket'] / $per_page);
                                                $current_page = $page;

                                                // Show up to 10 page numbers at a time
                                                $page_range = 5; // Show 5 pages before and after current
                                                $start_page = max(1, $current_page - $page_range);
                                                $end_page = min($total_pages, $current_page + $page_range);
                                                ?>

                                                <!-- First Page -->
                                                <?php if ($current_page > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?integration=<?php echo $selected_integration['id']; ?>&per_page=<?php echo $per_page; ?>&page=1">
                                                            &laquo; <?php _e('First', 'cftp_admin'); ?>
                                                        </a>
                                                    </li>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?integration=<?php echo $selected_integration['id']; ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $current_page - 1; ?>">
                                                            &lsaquo; <?php _e('Prev', 'cftp_admin'); ?>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>

                                                <!-- Show ellipsis if not starting from page 1 -->
                                                <?php if ($start_page > 1): ?>
                                                    <li class="page-item disabled">
                                                        <span class="page-link">...</span>
                                                    </li>
                                                <?php endif; ?>

                                                <!-- Page numbers -->
                                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                    <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                                        <?php if ($i == $current_page): ?>
                                                            <span class="page-link"><?php echo $i; ?></span>
                                                        <?php else: ?>
                                                            <a class="page-link" href="?integration=<?php echo $selected_integration['id']; ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $i; ?>">
                                                                <?php echo $i; ?>
                                                            </a>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endfor; ?>

                                                <!-- Show ellipsis if not ending at last page -->
                                                <?php if ($end_page < $total_pages): ?>
                                                    <li class="page-item disabled">
                                                        <span class="page-link">...</span>
                                                    </li>
                                                <?php endif; ?>

                                                <!-- Next Page -->
                                                <?php if ($pagination_info['has_more']): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?integration=<?php echo $selected_integration['id']; ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $current_page + 1; ?>">
                                                            <?php _e('Next', 'cftp_admin'); ?> &rsaquo;
                                                        </a>
                                                    </li>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?integration=<?php echo $selected_integration['id']; ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $total_pages; ?>">
                                                            <?php _e('Last', 'cftp_admin'); ?> &raquo;
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                    </div>

                                    <div class="text-center text-muted mb-3">
                                        <?php _e('Page', 'cftp_admin'); ?> <?php echo number_format($current_page); ?>
                                        <?php _e('of approximately', 'cftp_admin'); ?> <?php echo number_format($total_pages); ?>
                                        (<?php echo number_format($pagination_info['total_files_in_bucket']); ?> <?php _e('total files', 'cftp_admin'); ?>)
                                    </div>
                                <?php endif; ?>

                                <!-- Import Options -->
                                <div class="ps-card mt-4 mb-3">
                                    <div class="ps-card-body">
                                        <h6><?php _e('Import Options', 'cftp_admin'); ?></h6>

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
                                    <button type="submit" name="action" value="import" class="btn btn-success" id="import_button">
                                        <i class="fa fa-download"></i> <?php _e('Import Selected Files', 'cftp_admin'); ?>
                                    </button>
                                </div>

                                <!-- Loading Indicator -->
                                <div id="import_loading" style="display: none;" class="text-center mt-3">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2"><strong><?php _e('Importing files, please wait...', 'cftp_admin'); ?></strong></p>
                                    <p class="text-muted"><?php _e('This may take several minutes for large imports.', 'cftp_admin'); ?></p>
                                </div>
                            </form>

                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Import Progress Modal -->
<div class="modal fade" id="bulk_import_modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa fa-download"></i> <?php _e('Bulk Import Progress', 'cftp_admin'); ?>
                </h5>
            </div>
            <div class="modal-body">
                <!-- Overall Progress -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <strong><?php _e('Overall Progress:', 'cftp_admin'); ?></strong>
                        <span id="progress_text">0 / 0 (0%)</span>
                    </div>
                    <div class="progress" style="height: 30px;">
                        <div id="progress_bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">
                            <span id="progress_percentage">0%</span>
                        </div>
                    </div>
                </div>

                <!-- Current Batch Info -->
                <div class="alert alert-info">
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong><?php _e('Current Batch:', 'cftp_admin'); ?></strong>
                            <span id="current_batch">1</span> / <span id="total_batches">0</span>
                        </div>
                        <div>
                            <strong><?php _e('Speed:', 'cftp_admin'); ?></strong>
                            <span id="import_speed">0</span> <?php _e('files/sec', 'cftp_admin'); ?>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row text-center mb-3">
                    <div class="col-4">
                        <div class="ps-card">
                            <div class="ps-card-body">
                                <h3 class="text-success" id="success_count">0</h3>
                                <small class="text-muted"><?php _e('Imported', 'cftp_admin'); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="ps-card">
                            <div class="ps-card-body">
                                <h3 class="text-warning" id="skipped_count">0</h3>
                                <small class="text-muted"><?php _e('Skipped', 'cftp_admin'); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="ps-card">
                            <div class="ps-card-body">
                                <h3 class="text-danger" id="error_count">0</h3>
                                <small class="text-muted"><?php _e('Errors', 'cftp_admin'); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Folders Created -->
                <div class="alert alert-secondary" id="folders_created_alert" style="display: none;">
                    <strong><?php _e('Folders Created:', 'cftp_admin'); ?></strong>
                    <span id="folders_count">0</span>
                </div>

                <!-- Status Messages -->
                <div id="status_messages" class="mt-3" style="max-height: 200px; overflow-y: auto;">
                    <!-- Messages will be added here -->
                </div>

                <!-- Error Log -->
                <div id="error_log" class="mt-3" style="display: none; max-height: 150px; overflow-y: auto;">
                    <strong class="text-danger"><?php _e('Errors:', 'cftp_admin'); ?></strong>
                    <ul id="error_list" class="list-unstyled small text-danger">
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="pause_button" class="btn btn-warning" disabled>
                    <i class="fa fa-pause"></i> <?php _e('Pause', 'cftp_admin'); ?>
                </button>
                <button type="button" id="cancel_button" class="btn btn-danger">
                    <i class="fa fa-times"></i> <?php _e('Cancel', 'cftp_admin'); ?>
                </button>
                <button type="button" id="close_button" class="btn btn-primary" style="display: none;">
                    <i class="fa fa-check"></i> <?php _e('Close', 'cftp_admin'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables for import
let bulkImportState = {
    isImporting: false,
    isPaused: false,
    isCancelled: false,
    allFiles: false,
    totalFiles: 0,
    importedFiles: 0,
    erroredFiles: 0,
    skippedFiles: 0,
    totalBatches: 0,
    currentBatch: 0,
    batchSize: 50,
    filesToImport: [],
    startTime: null,
    totalFoldersCreated: 0,
    fetchContinuationToken: null,
    totalFilesFetched: 0
};

// Change items per page
function changePerPage(perPage) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('per_page', perPage);
    urlParams.delete('page'); // Reset to page 1
    urlParams.delete('token'); // Reset token
    window.location.search = urlParams.toString();
}

$(document).ready(function() {
    // Select all on page
    $('#select_all_page, #select_all, #select_all_checkbox').on('click', function() {
        bulkImportState.allFiles = false;
        $('.file_checkbox').prop('checked', true);
        updateFolderPreview();
    });

    // Select ALL files in bucket
    $('#select_all_files').on('click', function() {
        const totalFilesInBucket = <?php echo $pagination_info['total_files_in_bucket']; ?>;

        // Check if there are any files in the bucket
        if (totalFilesInBucket === 0) {
            alert('<?php _e('No files available in the S3 bucket. Please check your integration settings.', 'cftp_admin'); ?>');
            return;
        }

        const confirmed = confirm('<?php _e('This will import ALL', 'cftp_admin'); ?> ' + totalFilesInBucket.toLocaleString() + ' <?php _e('files from the bucket. This may take a very long time. Continue?', 'cftp_admin'); ?>');
        if (confirmed) {
            bulkImportState.allFiles = true;
            $(this).addClass('btn-success').removeClass('btn-primary');
            $(this).html('<i class="fa fa-check"></i> <?php _e('ALL Files Selected', 'cftp_admin'); ?> (' + totalFilesInBucket.toLocaleString() + ')');
            updateFolderPreview();
        }
    });

    // Select none
    $('#select_none').on('click', function() {
        bulkImportState.allFiles = false;
        $('.file_checkbox').prop('checked', false);
        $('#select_all_files').removeClass('btn-success').addClass('btn-primary');
        $('#select_all_files').html('<i class="fa fa-check-square"></i> <?php _e('Select ALL Files', 'cftp_admin'); ?> (<?php echo number_format($pagination_info['total_files_in_bucket']); ?>)');
        updateFolderPreview();
    });

    // Toggle metadata row
    $('.toggle-metadata').on('click', function() {
        var targetId = $(this).data('target');
        var $metadataRow = $('#' + targetId);
        var $icon = $(this).find('i');

        if ($metadataRow.is(':visible')) {
            $metadataRow.hide();
            $icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        } else {
            $metadataRow.show();
            $icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        }
    });

    // Update preview when checkboxes change
    $('.file_checkbox').on('change', function() {
        bulkImportState.allFiles = false;
        $('#select_all_files').removeClass('btn-success').addClass('btn-primary');
        $('#select_all_files').html('<i class="fa fa-check-square"></i> <?php _e('Select ALL Files', 'cftp_admin'); ?> (<?php echo number_format($pagination_info['total_files_in_bucket']); ?>)');
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

    // Handle form submission
    $('form').on('submit', function(e) {
        e.preventDefault(); // Always prevent default, we'll handle with AJAX

        const totalCheckboxes = $('.file_checkbox').length;
        const checkedCount = $('.file_checkbox:checked').length;

        // Check if there are any files available at all
        if (totalCheckboxes === 0 && !bulkImportState.allFiles) {
            alert('<?php _e('No files available to import. Please refresh the file list or check your S3 bucket.', 'cftp_admin'); ?>');
            return false;
        }

        // Check if user selected at least one file
        if (checkedCount === 0 && !bulkImportState.allFiles) {
            alert('<?php _e('Please select at least one file to import from the list.', 'cftp_admin'); ?>');
            return false;
        }

        // Start bulk import
        startBulkImport();
        return false;
    });

    // Cancel button
    $('#cancel_button').on('click', function() {
        if (confirm('<?php _e('Are you sure you want to cancel the import? Files already imported will remain.', 'cftp_admin'); ?>')) {
            bulkImportState.isCancelled = true;
            $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?php _e('Cancelling...', 'cftp_admin'); ?>');
        }
    });

    // Close button
    $('#close_button').on('click', function() {
        $('#bulk_import_modal').modal('hide');
        // Refresh page to show updated file list
        location.reload();
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

    /**
     * Start bulk import process
     */
    function startBulkImport() {
        // Reset state
        bulkImportState.isImporting = true;
        bulkImportState.isPaused = false;
        bulkImportState.isCancelled = false;
        bulkImportState.importedFiles = 0;
        bulkImportState.erroredFiles = 0;
        bulkImportState.skippedFiles = 0;
        bulkImportState.currentBatch = 0;
        bulkImportState.startTime = Date.now();
        bulkImportState.totalFoldersCreated = 0;

        // Show modal
        $('#bulk_import_modal').modal('show');
        $('#cancel_button').prop('disabled', false).html('<i class="fa fa-times"></i> <?php _e('Cancel', 'cftp_admin'); ?>');
        $('#close_button').hide();

        // Add status message
        addStatusMessage('<?php _e('Initializing import...', 'cftp_admin'); ?>', 'info');

        // Check if importing all files or just selected
        if (bulkImportState.allFiles) {
            // Fetch all files from S3
            getAllFilesFromS3();
        } else {
            // Use selected files from current page
            bulkImportState.filesToImport = [];
            $('.file_checkbox:checked').each(function() {
                const row = $(this).closest('tr');
                const fileKey = $(this).val();
                const folderPath = row.find('.folder-path').data('folder-path');

                // Get metadata from hidden field
                const metadataField = $('input[name="files_metadata"]').val();
                const metadata = metadataField ? JSON.parse(metadataField) : {};

                if (metadata[fileKey]) {
                    bulkImportState.filesToImport.push({
                        key: fileKey,
                        size: metadata[fileKey].size,
                        last_modified: metadata[fileKey].last_modified,
                        etag: metadata[fileKey].etag || '',
                        storage_class: metadata[fileKey].storage_class || 'STANDARD'
                    });
                }
            });

            bulkImportState.totalFiles = bulkImportState.filesToImport.length;
            bulkImportState.totalBatches = Math.ceil(bulkImportState.totalFiles / bulkImportState.batchSize);

            addStatusMessage('<?php _e('Found', 'cftp_admin'); ?> ' + bulkImportState.totalFiles + ' <?php _e('files to import', 'cftp_admin'); ?>', 'success');
            $('#total_batches').text(bulkImportState.totalBatches);

            // Start importing
            processBatch();
        }
    }

    /**
     * Get all files from S3 via progressive AJAX fetching
     */
    function getAllFilesFromS3() {
        bulkImportState.filesToImport = [];
        bulkImportState.fetchContinuationToken = null;
        bulkImportState.totalFilesFetched = 0;

        addStatusMessage('<?php _e('Starting to fetch files from S3...', 'cftp_admin'); ?>', 'info');

        // Start progressive fetching
        fetchFilesBatch();
    }

    /**
     * Fetch a batch of files progressively
     */
    function fetchFilesBatch() {
        if (bulkImportState.isCancelled) {
            addStatusMessage('<?php _e('File fetching cancelled', 'cftp_admin'); ?>', 'warning');
            finishImport();
            return;
        }

        addStatusMessage('<?php _e('Fetching batch from S3...', 'cftp_admin'); ?> (' + bulkImportState.totalFilesFetched + ' <?php _e('fetched so far', 'cftp_admin'); ?>)', 'info');

        $.ajax({
            url: 'import-external-ajax.php',
            method: 'POST',
            dataType: 'json',
            data: {
                batch_action: 'get_files_batch',
                integration_id: <?php echo isset($selected_integration['id']) ? $selected_integration['id'] : 0; ?>,
                continuation_token: bulkImportState.fetchContinuationToken,
                batch_size: 1000, // Fetch 1000 at a time
                csrf_token: '<?php echo getCsrfToken(); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Add files to import list
                    bulkImportState.filesToImport = bulkImportState.filesToImport.concat(response.data.files);
                    bulkImportState.totalFilesFetched += response.data.fetched_count;

                    addStatusMessage(
                        '<?php _e('Fetched', 'cftp_admin'); ?> ' + response.data.fetched_count + ' <?php _e('files', 'cftp_admin'); ?>, ' +
                        response.data.filtered_count + ' <?php _e('new files to import', 'cftp_admin'); ?>',
                        'success'
                    );

                    // Check if there are more files to fetch
                    if (response.data.has_more && response.data.next_token) {
                        bulkImportState.fetchContinuationToken = response.data.next_token;
                        // Continue fetching
                        setTimeout(fetchFilesBatch, 100);
                    } else {
                        // Done fetching, start importing
                        bulkImportState.totalFiles = bulkImportState.filesToImport.length;
                        bulkImportState.totalBatches = Math.ceil(bulkImportState.totalFiles / bulkImportState.batchSize);

                        addStatusMessage(
                            '<?php _e('Finished fetching! Total files to import:', 'cftp_admin'); ?> ' + bulkImportState.totalFiles,
                            'success'
                        );
                        $('#total_batches').text(bulkImportState.totalBatches);

                        // Start importing
                        if (bulkImportState.totalFiles > 0) {
                            setTimeout(processBatch, 500); // Small delay before starting import
                        } else {
                            addStatusMessage('<?php _e('No new files to import', 'cftp_admin'); ?>', 'warning');
                            finishImport();
                        }
                    }
                } else {
                    addStatusMessage('<?php _e('Error:', 'cftp_admin'); ?> ' + (response.message || 'Unknown error'), 'danger');
                    finishImport();
                }
            },
            error: function(xhr, status, error) {
                let errorMsg = '<?php _e('Error fetching files:', 'cftp_admin'); ?> ';
                if (xhr.responseText) {
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        errorMsg += errorData.message || error;
                    } catch (e) {
                        errorMsg += error + ' (Status: ' + xhr.status + ')';
                    }
                } else {
                    errorMsg += error;
                }
                addStatusMessage(errorMsg, 'danger');
                finishImport();
            }
        });
    }

    /**
     * Process a batch of files
     */
    function processBatch() {
        if (bulkImportState.isCancelled) {
            addStatusMessage('<?php _e('Import cancelled by user', 'cftp_admin'); ?>', 'warning');
            finishImport();
            return;
        }

        if (bulkImportState.currentBatch >= bulkImportState.totalBatches) {
            finishImport();
            return;
        }

        const startIndex = bulkImportState.currentBatch * bulkImportState.batchSize;
        const endIndex = Math.min(startIndex + bulkImportState.batchSize, bulkImportState.totalFiles);
        const batch = bulkImportState.filesToImport.slice(startIndex, endIndex);

        bulkImportState.currentBatch++;
        $('#current_batch').text(bulkImportState.currentBatch);

        addStatusMessage('<?php _e('Processing batch', 'cftp_admin'); ?> ' + bulkImportState.currentBatch + ' <?php _e('of', 'cftp_admin'); ?> ' + bulkImportState.totalBatches + '...', 'info');

        $.ajax({
            url: 'import-external-ajax.php',
            method: 'POST',
            dataType: 'json',
            data: {
                batch_action: 'import_batch',
                integration_id: <?php echo isset($selected_integration['id']) ? $selected_integration['id'] : 0; ?>,
                file_batch: JSON.stringify(batch),
                preserve_folders: $('#preserve_folders').is(':checked') ? '1' : '0',
                batch_index: bulkImportState.currentBatch,
                csrf_token: '<?php echo getCsrfToken(); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Update counters
                    bulkImportState.importedFiles += response.data.imported_count;
                    bulkImportState.erroredFiles += response.data.errors.length;
                    bulkImportState.skippedFiles += response.data.skipped_count;
                    bulkImportState.totalFoldersCreated += response.data.created_folders_count;

                    // Update UI
                    updateProgress();

                    // Log errors
                    if (response.data.errors.length > 0) {
                        response.data.errors.forEach(function(error) {
                            addError(error);
                        });
                    }

                    addStatusMessage('<?php _e('Batch', 'cftp_admin'); ?> ' + bulkImportState.currentBatch + ' <?php _e('completed:', 'cftp_admin'); ?> ' + response.data.imported_count + ' <?php _e('imported', 'cftp_admin'); ?>', 'success');

                    // Process next batch
                    setTimeout(processBatch, 100); // Small delay to prevent overwhelming server
                } else {
                    addStatusMessage('<?php _e('Error in batch', 'cftp_admin'); ?> ' + bulkImportState.currentBatch + ': ' + response.message, 'danger');
                    bulkImportState.erroredFiles += batch.length;
                    updateProgress();

                    // Continue with next batch even if one fails
                    setTimeout(processBatch, 100);
                }
            },
            error: function(xhr, status, error) {
                addStatusMessage('<?php _e('Ajax error in batch', 'cftp_admin'); ?> ' + bulkImportState.currentBatch + ': ' + error, 'danger');
                bulkImportState.erroredFiles += batch.length;
                updateProgress();

                // Continue with next batch
                setTimeout(processBatch, 100);
            }
        });
    }

    /**
     * Update progress indicators
     */
    function updateProgress() {
        const percentage = Math.round((bulkImportState.importedFiles + bulkImportState.erroredFiles) / bulkImportState.totalFiles * 100);

        $('#progress_bar').css('width', percentage + '%');
        $('#progress_percentage').text(percentage + '%');
        $('#progress_text').text(
            (bulkImportState.importedFiles + bulkImportState.erroredFiles) + ' / ' +
            bulkImportState.totalFiles + ' (' + percentage + '%)'
        );

        $('#success_count').text(bulkImportState.importedFiles);
        $('#error_count').text(bulkImportState.erroredFiles);
        $('#skipped_count').text(bulkImportState.skippedFiles);

        // Update folder count
        if (bulkImportState.totalFoldersCreated > 0) {
            $('#folders_created_alert').show();
            $('#folders_count').text(bulkImportState.totalFoldersCreated);
        }

        // Calculate speed
        const elapsedSeconds = (Date.now() - bulkImportState.startTime) / 1000;
        const speed = (bulkImportState.importedFiles / elapsedSeconds).toFixed(2);
        $('#import_speed').text(speed);
    }

    /**
     * Add status message
     */
    function addStatusMessage(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const alertClass = 'alert-' + type;
        const html = '<div class="alert ' + alertClass + ' alert-dismissible fade show py-2 mb-2" role="alert">' +
            '<small><strong>[' + timestamp + ']</strong> ' + message + '</small>' +
            '</div>';
        $('#status_messages').prepend(html);

        // Auto-remove old messages (keep last 10)
        const messages = $('#status_messages .alert');
        if (messages.length > 10) {
            messages.slice(10).remove();
        }
    }

    /**
     * Add error to error log
     */
    function addError(error) {
        $('#error_log').show();
        $('#error_list').append('<li>' + error + '</li>');

        // Limit to 50 errors shown
        const errors = $('#error_list li');
        if (errors.length > 50) {
            errors.slice(50).remove();
        }
    }

    /**
     * Finish import process
     */
    function finishImport() {
        bulkImportState.isImporting = false;

        // Update progress to 100%
        $('#progress_bar').css('width', '100%').removeClass('progress-bar-animated');
        $('#progress_percentage').text('100%');

        // Hide cancel, show close
        $('#cancel_button').hide();
        $('#close_button').show();

        // Show summary
        let summaryMessage = '<?php _e('Import completed!', 'cftp_admin'); ?>\n\n';
        summaryMessage += '<?php _e('Imported:', 'cftp_admin'); ?> ' + bulkImportState.importedFiles + '\n';
        if (bulkImportState.erroredFiles > 0) {
            summaryMessage += '<?php _e('Errors:', 'cftp_admin'); ?> ' + bulkImportState.erroredFiles + '\n';
        }
        if (bulkImportState.skippedFiles > 0) {
            summaryMessage += '<?php _e('Skipped:', 'cftp_admin'); ?> ' + bulkImportState.skippedFiles + '\n';
        }
        if (bulkImportState.totalFoldersCreated > 0) {
            summaryMessage += '<?php _e('Folders created:', 'cftp_admin'); ?> ' + bulkImportState.totalFoldersCreated + '\n';
        }

        addStatusMessage(summaryMessage.replace(/\n/g, '<br>'), bulkImportState.erroredFiles > 0 ? 'warning' : 'success');
    }
});
</script>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
?>