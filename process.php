<?php
use ProjectSend\Classes\Session as Session;
use ProjectSend\Classes\Download;
use ProjectSend\Classes\ActionsLog;

/** Process an action */
require_once 'bootstrap.php';

// Allow certain actions without login requirement
$public_actions = ['get_public_file_info', 'get_file_metadata'];

// For get_file_metadata, suppress any output before JSON
if (isset($_GET['do']) && $_GET['do'] === 'get_file_metadata') {
    @ini_set('display_errors', 0);
    error_reporting(0);
    ob_start();
}

if (!isset($_GET['do']) || !in_array($_GET['do'], $public_actions)) {
    redirect_if_not_logged_in();
}

global $auth;
global $logger;

extend_session();

if (!isset($_GET['do'])) {
    exit_with_error_code(403);
}

switch ($_GET['do']) {
    case 'social_login':
        // Store provider in session for callback
        if (Session::has('SOCIAL_LOGIN_NETWORK')) {
            Session::remove('SOCIAL_LOGIN_NETWORK');
        }
        Session::add('SOCIAL_LOGIN_NETWORK', $_GET['provider']);

        // HybridAuth handles CSRF state parameter internally
        $login = $auth->socialLogin($_GET['provider']);
        break;
    case 'login_ldap':
        // Validate required fields
        if (empty($_POST['ldap_username']) || empty($_POST['ldap_password'])) {
            echo json_encode([
                'status' => 'error',
                'message' => __("Username and password are required.", 'cftp_admin')
            ]);
            exit;
        }

        // Check if LDAP is enabled
        if (get_option('ldap_signin_enabled') != 'true') {
            echo json_encode([
                'status' => 'error',
                'message' => __("LDAP authentication is not enabled.", 'cftp_admin')
            ]);
            exit;
        }

        // Set language if provided
        if (!empty($_POST['language'])) {
            $auth->setLanguage($_POST['language']);
        }

        // Perform LDAP authentication
        $login = $auth->loginLdap($_POST['ldap_username'], $_POST['ldap_password'], $_POST['language'] ?? null, $_POST['remember_me'] ?? false);
        echo $login;
        break;
    case 'test_ldap_connection':
        // Require admin level for testing LDAP connection
        redirect_if_role_not_allowed([9]);

        $test_result = $auth->testLdapConnection();
        echo json_encode($test_result);
        break;
    case 'ldap_sync_users':
        // Require manage_users permission for LDAP sync
        check_access_enhanced(['manage_users']);

        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';

        $ldap_sync = new \ProjectSend\Classes\LdapSync();
        $result = $ldap_sync->syncAllUsers($dry_run);

        echo json_encode($result);
        break;
    case 'logout':
        force_logout();
        break;
    case 'change_language':
        $auth->setLanguage(html_output($_GET['language']));
        $location = 'index.php';
        if (!empty($_GET['return_to']) && strpos($_GET['return_to'], BASE_URI) === 0) {
            $location = str_replace(BASE_URI, '', $_GET['return_to']);
        }
        ps_redirect(BASE_URI . $location);
        break;
    case 'get_preview':
        $return = [];
        if (!empty($_GET['file_id'])) {
            if (!user_can_download_file(CURRENT_USER_ID, $_GET['file_id'])) {
                exit_with_error_code(403);
            }
            $file = new \ProjectSend\Classes\Files($_GET['file_id']);
            if ($file->embeddable) {
                $return = json_decode($file->getEmbedData());
            }
        }

        echo json_encode($return);
        exit;
        break;
    case 'get_file_metadata':
        // Clean any buffered output and set JSON header
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');

        $return = ['success' => false];

        try {
            if (!empty($_GET['file_id'])) {
                // Check if user can access this file (either logged in with access, or file is public)
                $file = new \ProjectSend\Classes\Files($_GET['file_id']);
                $can_access = false;

                // Check if file is public (property is 'public' not 'public_allow')
                if ($file->public == '1') {
                    $can_access = true;
                }
                // Check if user is logged in and can download
                elseif (defined('CURRENT_USER_ID') && CURRENT_USER_ID && user_can_download_file(CURRENT_USER_ID, $_GET['file_id'])) {
                    $can_access = true;
                }

                if (!$can_access) {
                    echo json_encode(['success' => false, 'error' => 'Access denied']);
                    exit;
                }

                // Get basic file info
                $return = [
                    'success' => true,
                    'title' => $file->title,
                    'filename' => $file->filename_original,
                    'description' => $file->description,
                    'size' => $file->size_formatted,
                    'type' => strtoupper($file->extension),
                    'upload_date' => format_date($file->uploaded_date),
                    'uploader' => $file->uploaded_by ?? '',
                    'expiry' => ($file->expires == '1') ? format_date($file->expiry_date) : __('Never', 'cftp_admin'),
                    'download_link' => $file->download_link,
                    's3_metadata' => []
                ];

                // Get S3 metadata if file is stored externally
                if ($file->storage_type !== 'local' && !empty($file->integration_id) && !empty($file->external_path)) {
                    $integrations_handler = new \ProjectSend\Classes\Integrations();
                    $integration = $integrations_handler->getById($file->integration_id);

                    if ($integration) {
                        $storage = $integrations_handler->createStorageInstance($integration);
                        if ($storage && method_exists($storage, 'getFileMetadata')) {
                            $s3_meta = $storage->getFileMetadata($file->external_path);
                            if ($s3_meta && isset($s3_meta['metadata'])) {
                                // Filter metadata based on system settings
                                $filtered_metadata = [];
                                $metadata_settings = [
                                    'created-by' => get_option('metadata_show_created_by'),
                                    'created-by-email' => get_option('metadata_show_created_by_email'),
                                    'created-by-title' => get_option('metadata_show_created_by_title'),
                                    'modified-by' => get_option('metadata_show_modified_by'),
                                    'modified-date' => get_option('metadata_show_modified_date'),
                                    'is-versioned' => get_option('metadata_show_is_versioned'),
                                    'is-current-version' => get_option('metadata_show_is_current_version'),
                                    'version-id' => get_option('metadata_show_version_id'),
                                    'content-type' => get_option('metadata_show_content_type'),
                                    'crawl-depth' => get_option('metadata_show_crawl_depth'),
                                    'discovered-from' => get_option('metadata_show_discovered_from'),
                                    'enriched-files' => get_option('metadata_show_enriched_files'),
                                    'enriched-version-label' => get_option('metadata_show_enriched_version_label'),
                                    'sharepoint-file-size' => get_option('metadata_show_sharepoint_file_size'),
                                    'sharepoint-url' => get_option('metadata_show_sharepoint_url'),
                                    'version-url' => get_option('metadata_show_version_url'),
                                ];

                                foreach ($s3_meta['metadata'] as $key => $value) {
                                    // Check if this metadata field has a setting
                                    if (isset($metadata_settings[$key])) {
                                        // Only include if setting is enabled (1) or not set (default to show)
                                        if ($metadata_settings[$key] === '1' || $metadata_settings[$key] === null) {
                                            $filtered_metadata[$key] = $value;
                                        }
                                    } else {
                                        // For unknown metadata keys, always include them
                                        $filtered_metadata[$key] = $value;
                                    }
                                }

                                $return['s3_metadata'] = $filtered_metadata;
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $return = ['success' => false, 'error' => $e->getMessage()];
        }

        echo json_encode($return);
        exit;
        break;
    case 'download':
        $download = new Download;
        $inline = isset($_GET['inline']) && $_GET['inline'] == '1';
        $download->download($_GET['id'], $inline);
        break;
    case 'dismiss_upgraded_notice':
        redirect_if_not_logged_in();
        if (!current_user_can('edit_settings')) {
            exit_with_error_code(403);
        }
        save_option('show_upgrade_success_message', 'false');

        // Redirect back to the original page if provided, otherwise dashboard
        $return_to = isset($_GET['return_to']) ? $_GET['return_to'] : BASE_URI.'dashboard.php';

        // Validate the return URL to prevent open redirects
        $parsed_url = parse_url($return_to);
        if ($parsed_url && (empty($parsed_url['host']) || $parsed_url['host'] === $_SERVER['HTTP_HOST'])) {
            ps_redirect($return_to);
        } else {
            ps_redirect(BASE_URI.'dashboard.php');
        }
        break;
    case 'return_files_ids':
        redirect_if_not_logged_in();
        redirect_if_role_not_allowed($allowed_levels);
        $download = new Download;
        $download->returnFilesIds($_GET['files']);
        break;
    case 'download_zip':
        redirect_if_not_logged_in();
        redirect_if_role_not_allowed($allowed_levels);
        $download = new Download;
        $download->downloadZip($_GET['files']);
    break;

    case 'get_file_info':
        redirect_if_not_logged_in();
        // Skip role-based check, rely on permission-based check below
        // This allows custom roles with appropriate permissions to access this endpoint

        header('Content-Type: application/json');

        if (!isset($_GET['file_id']) || empty($_GET['file_id'])) {
            echo json_encode(['success' => false, 'error' => 'File ID is required']);
            break;
        }

        $file_id = (int)$_GET['file_id'];

        // Check if user can access this file's information
        // For file info, we use edit permissions only (not download permissions)
        // This ensures users can only get detailed info about files they can edit
        $can_access = user_can_edit_file(CURRENT_USER_ID, $file_id);

        if (!$can_access) {
            echo json_encode(['success' => false, 'error' => 'Access denied - you do not have permission to view this file information']);
            break;
        }

        // Get file information
        $file = new \ProjectSend\Classes\Files($file_id);

        if (!$file->id) {
            echo json_encode(['success' => false, 'error' => 'File not found']);
            break;
        }

        // Get file data using the getPublicData method
        $file_data = $file->getPublicData();

        // Add categories with names
        $categories_names = [];
        if (!empty($file->categories)) {
            $categories_query = "SELECT id, name FROM " . TABLE_CATEGORIES . " WHERE id IN (" . implode(',', $file->categories) . ")";
            $categories_stmt = $dbh->query($categories_query);
            while ($cat = $categories_stmt->fetch(PDO::FETCH_ASSOC)) {
                $categories_names[] = [
                    'id' => $cat['id'],
                    'name' => $cat['name']
                ];
            }
        }
        $file_data['categories'] = $categories_names;

        // Add privacy information
        $file_data['public'] = $file->public;
        $file_data['public_token'] = $file->public_token;
        $file_data['public_url'] = $file->public_url;

        // Add expiry information
        $file_data['expires'] = $file->expires;
        if ($file->expires && $file->expiry_date) {
            $file_data['expiry_date_formatted'] = date(get_option('timeformat'), strtotime($file->expiry_date));
            $file_data['days_until_expiry'] = round((strtotime($file->expiry_date) - time()) / 86400);
        }

        // Add image metadata for images
        if ($file->isImage()) {
            // Get dimensions
            $dimensions = $file->getDimensions();
            if ($dimensions) {
                $file_data['image_metadata'] = [
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                ];
            }

            // Get EXIF data (if available)
            if (function_exists('exif_read_data')) {
                try {
                    $exif = @exif_read_data($file->full_path, 'IFD0', true);
                    if ($exif && isset($exif['IFD0'])) {
                        $exif_data = [];
                        $exif_fields = ['Make', 'Model', 'DateTime', 'ExposureTime', 'FNumber', 'ISOSpeedRatings'];
                        foreach ($exif_fields as $field) {
                            if (!empty($exif['IFD0'][$field])) {
                                $exif_data[$field] = $exif['IFD0'][$field];
                            }
                        }
                        if (!empty($exif_data)) {
                            $file_data['image_metadata']['exif'] = $exif_data;
                        }
                    }
                } catch (Exception $e) {
                    // Silently fail if EXIF reading fails
                }
            }
        }

        // Add uploader information
        $file_data['uploaded_by'] = $file->uploaded_by;

        // Add assignment information
        $file_data['assignments'] = [
            'clients' => $file->assignments_clients,
            'groups' => $file->assignments_groups
        ];

        // Add S3 metadata if available
        if (!empty($file->s3_metadata)) {
            $s3_metadata_decoded = json_decode($file->s3_metadata, true);
            if ($s3_metadata_decoded && is_array($s3_metadata_decoded)) {
                $file_data['s3_metadata'] = $s3_metadata_decoded;
            }
        }

        echo json_encode(['success' => true, 'file' => $file_data]);
    break;

    case 'get_public_file_info':
        header('Content-Type: application/json');
        
        if (!isset($_GET['file_id']) || empty($_GET['file_id'])) {
            echo json_encode(['success' => false, 'error' => 'File ID is required']);
            break;
        }
        
        $file_id = (int)$_GET['file_id'];
        
        try {
            // Get file information
            $file = new \ProjectSend\Classes\Files($file_id);
            
            if (!$file->id) {
                echo json_encode(['success' => false, 'error' => 'File not found', 'debug' => 'File object has no ID']);
                break;
            }
            
            // Check if file is public
            if (!$file->isPublic()) {
                echo json_encode(['success' => false, 'error' => 'File is not public', 'debug' => 'File exists but isPublic() returned false']);
                break;
            }
            
            // Get file data using the getPublicData method
            $file_data = $file->getPublicData();

            if (empty($file_data)) {
                echo json_encode(['success' => false, 'error' => 'Failed to get file data', 'debug' => 'getPublicData() returned empty']);
                break;
            }

            // Add S3 metadata if available
            if (!empty($file->s3_metadata)) {
                $s3_metadata_decoded = json_decode($file->s3_metadata, true);
                if ($s3_metadata_decoded && is_array($s3_metadata_decoded)) {
                    $file_data['s3_metadata'] = $s3_metadata_decoded;
                }
            }

            echo json_encode(['success' => true, 'file' => $file_data]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Exception occurred: ' . $e->getMessage(), 'debug' => 'Exception in get_public_file_info']);
        }
    break;

    case 'check_update_requirements':
        // Check permissions
        if (!current_user_can('manage_updates')) {
            echo json_encode(['status' => 'error', 'message' => __('Permission denied', 'cftp_admin')]);
            exit;
        }

        $updater = new \ProjectSend\Classes\AutoUpdate();
        echo json_encode($updater->checkSystemRequirements());
        break;

    case 'perform_system_update':
        // Check permissions
        if (!current_user_can('manage_updates')) {
            echo json_encode(['status' => 'error', 'message' => __('Permission denied', 'cftp_admin')]);
            exit;
        }

        $step = $_POST['step'] ?? 'download';
        $updater = new \ProjectSend\Classes\AutoUpdate();

        switch($step) {
            case 'download':
                $url = $_POST['url'] ?? '';
                if (empty($url)) {
                    echo json_encode(['status' => 'error', 'message' => __('Download URL is required', 'cftp_admin')]);
                    exit;
                }
                $hash = $_POST['hash'] ?? null;
                $result = $updater->downloadUpdate($url, $hash);
                break;

            case 'backup':
                $result = $updater->createBackup();
                break;

            case 'extract':
                $result = $updater->extractUpdate();
                break;

            case 'finalize':
                $result = $updater->finalize();
                break;

            case 'rollback':
                $result = $updater->rollback();
                break;

            default:
                $result = ['status' => 'error', 'message' => __('Invalid update step', 'cftp_admin')];
        }

        echo json_encode($result);
        break;

    case 'get_roles_for_reassignment':
        // Get all active roles except the one being deleted
        if (!current_user_can('edit_settings')) {
            echo json_encode(['error' => 'Access denied']);
            exit;
        }

        $exclude_role = (int)$_GET['exclude_role'];
        $roles = get_all_roles();
        $available_roles = [];

        foreach ($roles as $role) {
            if ($role['id'] != $exclude_role && $role['active']) {
                $available_roles[] = [
                    'id' => $role['id'],
                    'name' => $role['name']
                ];
            }
        }

        echo json_encode(['roles' => $available_roles]);
        break;

    case 'get_role_users':
        // Get users assigned to a specific role
        if (!current_user_can('edit_settings')) {
            echo json_encode(['error' => 'Access denied']);
            exit;
        }

        $role_id = (int)$_GET['role_id'];
        $role = new \ProjectSend\Classes\Roles($role_id);
        $users = $role->getUsers();

        echo json_encode(['users' => $users]);
        break;

    case 'delete_role':
        // Only users with edit_settings permission can delete roles
        if (!current_user_can('edit_settings')) {
            $flash->error(__('You do not have permission to delete roles.', 'cftp_admin'));
            ps_redirect('roles.php');
        }

        if (empty($_POST['role_id'])) {
            $flash->error(__('No role specified.', 'cftp_admin'));
            ps_redirect('roles.php');
        }

        $role_id = (int)$_POST['role_id'];
        $role = new \ProjectSend\Classes\Roles($role_id);

        if (!$role->exists()) {
            $flash->error(__('Role not found.', 'cftp_admin'));
            ps_redirect('roles.php');
        }

        if ($role->is_system_role) {
            $flash->error(__('System roles cannot be deleted.', 'cftp_admin'));
            ps_redirect('roles.php');
        }

        // Handle user reassignment if needed
        if ($role->getUserCount() > 0) {
            if (empty($_POST['reassign_to_role'])) {
                $flash->error(__('Cannot delete role with assigned users. Please reassign users first.', 'cftp_admin'));
                ps_redirect('roles.php');
            }

            $new_role_id = (int)$_POST['reassign_to_role'];
            $new_role = new \ProjectSend\Classes\Roles($new_role_id);

            if (!$new_role->exists()) {
                $flash->error(__('Target role for reassignment not found.', 'cftp_admin'));
                ps_redirect('roles.php');
            }

            // Reassign all users to the new role
            $reassignment_result = $role->reassignUsersToRole($new_role_id);
            if ($reassignment_result['status'] !== 'success') {
                $flash->error(__('Failed to reassign users: ', 'cftp_admin') . $reassignment_result['message']);
                ps_redirect('roles.php');
            }
        }

        $result = $role->delete();
        if ($result['status'] === 'success') {
            $flash->success($result['message']);
        } else {
            $flash->error($result['message']);
        }

        ps_redirect('roles.php');
    break;

    case 'update_custom_fields_order':
        // Check permissions
        if (!current_role_in(['System Administrator']) && !current_user_can('manage_custom_fields')) {
            echo json_encode(['status' => 'error', 'message' => __('Permission denied', 'cftp_admin')]);
            exit;
        }

        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        // Validate CSRF token from JSON input
        if (!isset($input['csrf_token']) || !isset($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
            echo json_encode(['status' => 'error', 'message' => __('Invalid CSRF token', 'cftp_admin')]);
            exit;
        }

        if (!isset($input['sort_data']) || !is_array($input['sort_data'])) {
            echo json_encode(['status' => 'error', 'message' => __('Invalid sort data', 'cftp_admin')]);
            exit;
        }

        $success_count = 0;
        $total_count = count($input['sort_data']);
        $debug_info = [];

        foreach ($input['sort_data'] as $item) {
            if (!isset($item['id']) || !isset($item['sort_order'])) {
                $debug_info[] = "Missing id or sort_order in item";
                continue;
            }

            $field_id = (int)$item['id'];
            $sort_order = (int)$item['sort_order'];

            $field = new \ProjectSend\Classes\CustomFields($field_id);

            $debug_info[] = "Field ID: $field_id, exists: " . ($field->exists ? 'true' : 'false');

            if ($field->exists) {
                // Check permissions explicitly
                $has_permission = current_role_in(['System Administrator']) || current_user_can('manage_custom_fields');
                $debug_info[] = "Field $field_id permissions check: " . ($has_permission ? 'passed' : 'failed');

                $result = $field->updateSortOrder($sort_order);
                $debug_info[] = "Update result for field $field_id: " . ($result ? 'success' : 'failed');
                if ($result) {
                    $success_count++;
                }
            } else {
                $debug_info[] = "Field $field_id does not exist";
            }
        }

        if ($success_count === $total_count) {
            echo json_encode([
                'status' => 'success',
                'message' => __('Field order updated successfully', 'cftp_admin')
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => sprintf(__('Only %d of %d fields were updated', 'cftp_admin'), $success_count, $total_count),
                'debug' => $debug_info
            ]);
        }
    break;

    case 'encrypt_single_file':
        // Check permissions
        if (!current_user_can('edit_settings')) {
            echo json_encode(['status' => 'error', 'message' => __('Permission denied', 'cftp_admin')]);
            exit;
        }

        if (!isset($_POST['file_id']) || empty($_POST['file_id'])) {
            echo json_encode(['status' => 'error', 'message' => __('File ID is required', 'cftp_admin')]);
            exit;
        }

        $file_id = (int)$_POST['file_id'];
        $file = new \ProjectSend\Classes\Files($file_id);

        if (!$file->id) {
            echo json_encode(['status' => 'error', 'message' => __('File not found', 'cftp_admin')]);
            exit;
        }

        // Check if file is already encrypted
        if ($file->encrypted) {
            echo json_encode(['status' => 'error', 'message' => __('File is already encrypted', 'cftp_admin')]);
            exit;
        }

        // Check if encryption is enabled
        if (!\ProjectSend\Classes\Encryption::isEnabled()) {
            echo json_encode(['status' => 'error', 'message' => __('File encryption is not enabled', 'cftp_admin')]);
            exit;
        }

        // Get file path
        $file_path = $file->full_path;

        if (!file_exists($file_path)) {
            echo json_encode(['status' => 'error', 'message' => __('Physical file not found', 'cftp_admin')]);
            exit;
        }

        // Encrypt the file
        try {
            $encryption = new \ProjectSend\Classes\Encryption();

            // Generate unique file key
            $file_key = $encryption->generateFileKey();

            // Encrypt the file
            $encrypted_path = $file_path . '.encrypted';
            $encrypt_result = $encryption->encryptFile($file_path, $encrypted_path, $file_key);

            if (!$encrypt_result['success']) {
                throw new \Exception($encrypt_result['error']);
            }

            // Encrypt the file key with master key
            $encrypted_key_data = $encryption->encryptFileKey($file_key);

            // Replace original file with encrypted version
            unlink($file_path);
            rename($encrypted_path, $file_path);

            // Update database with encryption metadata
            global $dbh;
            $update_query = "UPDATE " . TABLE_FILES . " SET
                encrypted = 1,
                encryption_key_encrypted = :key,
                encryption_iv = :iv,
                encryption_algorithm = :algo,
                encryption_file_iv = :file_iv
                WHERE id = :id";

            $stmt = $dbh->prepare($update_query);
            $stmt->execute([
                ':key' => $encrypted_key_data['encrypted_key'],
                ':iv' => $encrypted_key_data['iv'],
                ':algo' => $encryption->getAlgorithm(),
                ':file_iv' => $encrypt_result['iv'],
                ':id' => $file_id
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => __('File encrypted successfully', 'cftp_admin')
            ]);
        } catch (\Exception $e) {
            error_log('File encryption error: ' . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => __('Error encrypting file: ', 'cftp_admin') . $e->getMessage()
            ]);
        }
        exit;
    break;

    case 'get_onlyoffice_config':
        // Get ONLYOFFICE editor configuration for a file
        if (!isset($_GET['file_id']) || empty($_GET['file_id'])) {
            echo json_encode(['status' => 'error', 'message' => __('File ID is required', 'cftp_admin')]);
            exit;
        }

        $file_id = (int)$_GET['file_id'];
        $mode = isset($_GET['mode']) ? $_GET['mode'] : 'edit';

        // Check if ONLYOFFICE is enabled
        if (!\ProjectSend\Classes\OnlyOffice::isEnabled()) {
            echo json_encode(['status' => 'error', 'message' => __('Document editing is not enabled', 'cftp_admin')]);
            exit;
        }

        // Load the file
        $file = new \ProjectSend\Classes\Files($file_id);
        if (!$file->recordExists()) {
            echo json_encode(['status' => 'error', 'message' => __('File not found', 'cftp_admin')]);
            exit;
        }

        // Check if user can access this file
        if (!user_can_download_file(CURRENT_USER_ID, $file_id)) {
            echo json_encode(['status' => 'error', 'message' => __('You do not have permission to access this file', 'cftp_admin')]);
            exit;
        }

        // Check if file type is supported
        $extension = strtolower(pathinfo($file->filename_original, PATHINFO_EXTENSION));
        if (!\ProjectSend\Classes\OnlyOffice::isViewable($extension)) {
            echo json_encode(['status' => 'error', 'message' => __('This file type is not supported for editing', 'cftp_admin')]);
            exit;
        }

        // For edit mode, check if user has edit permission
        if ($mode === 'edit' && !current_user_can('edit_files')) {
            $mode = 'view'; // Downgrade to view mode
        }

        try {
            $onlyoffice = new \ProjectSend\Classes\OnlyOffice();
            $user = new \ProjectSend\Classes\Users(CURRENT_USER_ID);
            $config = $onlyoffice->getEditorConfig($file, $user, $mode);

            echo json_encode([
                'status' => 'success',
                'config' => $config,
                'server_url' => \ProjectSend\Classes\OnlyOffice::getServerUrl()
            ]);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    break;

    case 'test_onlyoffice_connection':
        // Test ONLYOFFICE Document Server connection
        if (!current_user_can('edit_settings')) {
            echo json_encode(['status' => 'error', 'message' => __('Permission denied', 'cftp_admin')]);
            exit;
        }

        $result = \ProjectSend\Classes\OnlyOffice::testConnection();
        echo json_encode([
            'status' => $result['success'] ? 'success' : 'error',
            'message' => $result['message']
        ]);
        exit;
    break;
}

exit;
