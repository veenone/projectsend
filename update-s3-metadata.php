<?php
/**
 * Update s3_metadata for existing S3 files
 * This script fetches metadata from S3 and updates the database
 * for files that were imported before the s3_metadata column existed
 */
require_once 'bootstrap.php';
redirect_if_not_logged_in();

// Check for system administration permissions
if (!current_user_can('edit_settings')) {
    exit_with_error_code(403);
}

$active_nav = 'files';
$page_title = __('Update S3 Metadata', 'cftp_admin');
$page_id = 'update_s3_metadata';

global $flash;
$integrations_handler = new \ProjectSend\Classes\Integrations();

// Handle form submission
if (isset($_POST['action']) && $_POST['action'] === 'update_metadata') {
    // Validate CSRF token
    if (!validateCsrfToken()) {
        $flash->error(__('Invalid security token. Please try again.', 'cftp_admin'));
    } else {
        set_time_limit(600); // 10 minutes
        ini_set('memory_limit', '512M');

        $integration_id = !empty($_POST['integration_id']) ? (int)$_POST['integration_id'] : null;

        // Get S3 files without metadata or with null metadata
        global $dbh;
        $query = "SELECT id, external_path, integration_id, bucket_name
                  FROM " . TABLE_FILES . "
                  WHERE storage_type = 's3'
                  AND (s3_metadata IS NULL OR s3_metadata = '')";

        $params = [];
        if ($integration_id) {
            $query .= " AND integration_id = ?";
            $params[] = $integration_id;
        }

        $statement = $dbh->prepare($query);
        $statement->execute($params);
        $files = $statement->fetchAll(PDO::FETCH_ASSOC);

        $updated_count = 0;
        $skipped_count = 0;
        $errors = [];

        foreach ($files as $file_record) {
            $file_id = $file_record['id'];
            $file_key = $file_record['external_path'];
            $file_integration_id = $file_record['integration_id'];

            try {
                // Get integration
                $integration = $integrations_handler->getById($file_integration_id);
                if (!$integration) {
                    $errors[] = sprintf(__('Integration not found for file ID %d', 'cftp_admin'), $file_id);
                    $skipped_count++;
                    continue;
                }

                // Create storage instance
                $storage = $integrations_handler->createStorageInstance($integration);
                if (!$storage) {
                    $errors[] = sprintf(__('Failed to create storage instance for file ID %d', 'cftp_admin'), $file_id);
                    $skipped_count++;
                    continue;
                }

                // Fetch metadata from S3
                $metadata = $storage->getFileMetadata($file_key);
                if (!$metadata) {
                    $errors[] = sprintf(__('Could not fetch metadata for %s (ID: %d)', 'cftp_admin'), $file_key, $file_id);
                    $skipped_count++;
                    continue;
                }

                // Build s3_metadata JSON
                $s3_custom_metadata = [];

                // Extract custom metadata fields (X-Amz-Meta-* headers)
                if (isset($metadata['metadata']) && is_array($metadata['metadata'])) {
                    foreach ($metadata['metadata'] as $key => $value) {
                        $s3_custom_metadata[$key] = $value;
                    }
                }

                // Extract tags and merge them
                if (isset($metadata['tags']) && is_array($metadata['tags'])) {
                    foreach ($metadata['tags'] as $key => $value) {
                        $s3_custom_metadata['tag-' . $key] = $value;
                    }
                }

                // Update database if we have metadata
                if (!empty($s3_custom_metadata)) {
                    $s3_metadata_json = json_encode($s3_custom_metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    $update_query = "UPDATE " . TABLE_FILES . " SET s3_metadata = ? WHERE id = ?";
                    $update_statement = $dbh->prepare($update_query);
                    $update_statement->execute([$s3_metadata_json, $file_id]);

                    $updated_count++;
                    error_log("Updated s3_metadata for file ID $file_id ($file_key): " . strlen($s3_metadata_json) . " bytes");
                } else {
                    $skipped_count++;
                    error_log("No metadata found for file ID $file_id ($file_key)");
                }

            } catch (Exception $e) {
                $errors[] = sprintf(__('Error updating file ID %d: %s', 'cftp_admin'), $file_id, $e->getMessage());
                error_log("Error updating metadata for file ID $file_id: " . $e->getMessage());
                $skipped_count++;
            }
        }

        // Show results
        if ($updated_count > 0) {
            $flash->success(sprintf(__('Successfully updated metadata for %d files.', 'cftp_admin'), $updated_count));
        }

        if ($skipped_count > 0) {
            $flash->warning(sprintf(__('Skipped %d files (no metadata or errors).', 'cftp_admin'), $skipped_count));
        }

        if (!empty($errors)) {
            if (count($errors) > 10) {
                $flash->error(sprintf(__('Update completed with %d errors. Showing first 10:', 'cftp_admin'), count($errors)));
                $errors = array_slice($errors, 0, 10);
            }
            foreach ($errors as $error) {
                $flash->error($error);
            }
        }

        if ($updated_count == 0 && $skipped_count == 0) {
            $flash->info(__('No files found that need metadata updates.', 'cftp_admin'));
        }
    }
}

// Get available integrations
$integrations = $integrations_handler->getAll(true); // Only active integrations
$s3_integrations = array_filter($integrations, function($integration) {
    return $integration['type'] === 's3';
});

// Get count of files needing update
global $dbh;
$count_query = "SELECT integration_id, COUNT(*) as count
                FROM " . TABLE_FILES . "
                WHERE storage_type = 's3'
                AND (s3_metadata IS NULL OR s3_metadata = '')
                GROUP BY integration_id";
$count_statement = $dbh->prepare($count_query);
$count_statement->execute();
$counts_by_integration = [];
while ($row = $count_statement->fetch(PDO::FETCH_ASSOC)) {
    $counts_by_integration[$row['integration_id']] = $row['count'];
}

$total_count = array_sum($counts_by_integration);

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="ps-card">
            <div class="ps-card-body">
                <h3><?php _e('Update S3 Metadata', 'cftp_admin'); ?></h3>

                <div class="alert alert-info">
                    <h6><i class="fa fa-info-circle"></i> <?php _e('About this tool', 'cftp_admin'); ?></h6>
                    <p><?php _e('This tool fetches S3 custom metadata (X-Amz-Meta-* headers) and object tags from your S3 storage and updates the database for files that were imported before the s3_metadata column was added.', 'cftp_admin'); ?></p>
                    <p class="mb-0"><?php _e('Note: This may take some time if you have many files, as it needs to fetch metadata from S3 for each file.', 'cftp_admin'); ?></p>
                </div>

                <?php if (empty($s3_integrations)): ?>
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        <?php _e('No S3 integrations found.', 'cftp_admin'); ?>
                        <a href="integrations.php" class="alert-link"><?php _e('Configure integrations first', 'cftp_admin'); ?></a>
                    </div>
                <?php elseif ($total_count == 0): ?>
                    <div class="alert alert-success">
                        <i class="fa fa-check"></i>
                        <?php _e('All S3 files already have metadata. No updates needed.', 'cftp_admin'); ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <strong><?php _e('Files needing metadata updates:', 'cftp_admin'); ?></strong>
                        <span class="badge bg-warning"><?php echo number_format($total_count); ?></span>

                        <ul class="mt-2 mb-0">
                            <?php foreach ($counts_by_integration as $int_id => $count): ?>
                                <?php
                                $integration = array_filter($s3_integrations, function($i) use ($int_id) {
                                    return $i['id'] == $int_id;
                                });
                                $integration = reset($integration);
                                ?>
                                <li><strong><?php echo html_output($integration['name']); ?>:</strong> <?php echo number_format($count); ?> <?php _e('files', 'cftp_admin'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <form method="post">
                        <?php addCsrf(); ?>
                        <input type="hidden" name="action" value="update_metadata">

                        <div class="mb-3">
                            <label for="integration_id" class="form-label"><?php _e('Select Integration', 'cftp_admin'); ?></label>
                            <select name="integration_id" id="integration_id" class="form-select">
                                <option value=""><?php _e('All S3 integrations', 'cftp_admin'); ?> (<?php echo number_format($total_count); ?> <?php _e('files', 'cftp_admin'); ?>)</option>
                                <?php foreach ($s3_integrations as $integration): ?>
                                    <?php $file_count = $counts_by_integration[$integration['id']] ?? 0; ?>
                                    <?php if ($file_count > 0): ?>
                                        <option value="<?php echo $integration['id']; ?>">
                                            <?php echo html_output($integration['name']); ?>
                                            (<?php echo number_format($file_count); ?> <?php _e('files', 'cftp_admin'); ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-refresh"></i> <?php _e('Update Metadata', 'cftp_admin'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
