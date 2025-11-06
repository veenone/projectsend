<?php
/**
 * AJAX endpoint for chunked file import from external storage
 * Handles importing files in batches to prevent timeouts
 */
require_once 'bootstrap.php';
redirect_if_not_logged_in();

// Check for system administration permissions
if (!current_user_can('edit_settings')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Validate CSRF token
if (!validateCsrfToken()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Set JSON header
header('Content-Type: application/json');

// Disable output buffering for real-time progress
if (ob_get_level()) {
    ob_end_clean();
}

// Increase limits for batch processing
ini_set('memory_limit', '512M');

$action = $_POST['batch_action'] ?? '';

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'data' => []
];

try {
    switch ($action) {
        case 'get_files_batch':
            // Get a batch of files from S3 with pagination
            // This replaces get_all_files to avoid timeouts
            set_time_limit(60); // 1 minute per fetch batch

            $integration_id = (int)$_POST['integration_id'];
            $continuation_token = $_POST['continuation_token'] ?? null;
            $batch_size = isset($_POST['batch_size']) ? (int)$_POST['batch_size'] : 1000;

            $integrations_handler = new \ProjectSend\Classes\Integrations();
            $integration = $integrations_handler->getById($integration_id);

            if (!$integration) {
                throw new Exception(__('Integration not found.', 'cftp_admin'));
            }

            $storage = $integrations_handler->createStorageInstance($integration);
            if (!$storage) {
                throw new Exception(__('Failed to initialize storage connection.', 'cftp_admin'));
            }

            // Get a batch of files using pagination
            $storage_result = $storage->listFiles('', $batch_size, $continuation_token);

            if (!$storage_result['success']) {
                throw new Exception($storage_result['message'] ?? 'Failed to list files');
            }

            $batch_files = $storage_result['files'];

            // Get already imported files for this batch only
            global $dbh;
            $file_keys_to_check = array_column($batch_files, 'key');

            $existing_keys = [];
            if (!empty($file_keys_to_check)) {
                $placeholders = str_repeat('?,', count($file_keys_to_check) - 1) . '?';
                $query = "SELECT external_path FROM " . TABLE_FILES . "
                          WHERE integration_id = ? AND storage_type != 'local'
                          AND external_path IN ($placeholders)";
                $statement = $dbh->prepare($query);
                $params = array_merge([$integration_id], $file_keys_to_check);
                $statement->execute($params);
                while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    $existing_keys[] = $row['external_path'];
                }
            }

            // Filter out existing and folder markers
            $files_to_import = array_filter($batch_files, function($file) use ($existing_keys) {
                // Skip already imported
                if (in_array($file['key'], $existing_keys)) {
                    return false;
                }
                // Skip folder markers
                if (substr($file['key'], -1) === '/') {
                    return false;
                }
                // Skip empty files
                if ($file['size'] == 0) {
                    return false;
                }
                return true;
            });

            // Prepare file data
            $file_keys = array_map(function($file) {
                return [
                    'key' => $file['key'],
                    'size' => $file['size'],
                    'last_modified' => $file['last_modified'],
                    'etag' => $file['etag'] ?? '',
                    'storage_class' => $file['storage_class'] ?? 'STANDARD'
                ];
            }, array_values($files_to_import));

            $response['success'] = true;
            $response['data'] = [
                'files' => $file_keys,
                'has_more' => $storage_result['truncated'] ?? false,
                'next_token' => $storage_result['next_token'] ?? null,
                'fetched_count' => count($batch_files),
                'filtered_count' => count($file_keys)
            ];
            break;

        case 'import_batch':
            // Import a batch of files
            set_time_limit(300); // 5 minutes per import batch

            $integration_id = (int)$_POST['integration_id'];
            $file_batch = json_decode($_POST['file_batch'], true);
            $preserve_folders = !empty($_POST['preserve_folders']);
            $batch_index = (int)$_POST['batch_index'];

            if (empty($file_batch)) {
                throw new Exception(__('No files provided for import.', 'cftp_admin'));
            }

            $integrations_handler = new \ProjectSend\Classes\Integrations();
            $integration = $integrations_handler->getById($integration_id);

            if (!$integration) {
                throw new Exception(__('Integration not found.', 'cftp_admin'));
            }

            $storage = $integrations_handler->createStorageInstance($integration);
            if (!$storage) {
                throw new Exception(__('Failed to initialize storage connection.', 'cftp_admin'));
            }

            // Initialize folder importer if needed
            $folder_importer = null;
            $created_folders_count = 0;
            if ($preserve_folders) {
                $folder_importer = new \ProjectSend\Classes\FolderStructureImporter(CURRENT_USER_ID);
            }

            $imported_count = 0;
            $errors = [];
            $skipped_count = 0;

            foreach ($file_batch as $file_data) {
                $file_key = $file_data['key'];

                // Use cached metadata
                $metadata = [
                    'size' => $file_data['size'],
                    'last_modified' => $file_data['last_modified'],
                    'mime_type' => 'application/octet-stream'
                ];

                try {
                    // Create new file record
                    $file = new \ProjectSend\Classes\Files();
                    $file->setExternalFileProperties($file_key, $metadata, $integration_id, $integration['type']);
                    $file->bucket_name = $storage->getBucketName();

                    // Handle folder structure
                    if ($preserve_folders && $folder_importer) {
                        $import_result = $folder_importer->importPath($file_key, CURRENT_USER_ID);
                        if ($import_result['folder_id']) {
                            $file->folder_id = $import_result['folder_id'];
                        }
                    }

                    // Set file properties
                    $file->title = $file->filename_original;
                    $file->description = sprintf(__('Imported from %s', 'cftp_admin'), $integration['name']);
                    $file->setDefaults();

                    // Add to database
                    $result = $file->addToDatabase();

                    if ($result['status'] === 'success') {
                        $imported_count++;
                    } else {
                        $errors[] = basename($file_key) . ': ' . $result['message'];
                    }
                } catch (\Exception $e) {
                    $errors[] = basename($file_key) . ': ' . $e->getMessage();
                    error_log("Batch import error for $file_key: " . $e->getMessage());
                }
            }

            // Get folder count
            if ($preserve_folders && $folder_importer) {
                $created_folders = $folder_importer->getCreatedFolders();
                $created_folders_count = count($created_folders);
            }

            $response['success'] = true;
            $response['data'] = [
                'batch_index' => $batch_index,
                'imported_count' => $imported_count,
                'errors' => $errors,
                'skipped_count' => $skipped_count,
                'created_folders_count' => $created_folders_count
            ];
            break;

        default:
            throw new Exception(__('Invalid action specified.', 'cftp_admin'));
    }
} catch (\Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("Import AJAX error: " . $e->getMessage());
}

echo json_encode($response);
exit;
