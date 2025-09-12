<?php
/**
 * OIDC Configuration AJAX Handler
 * Provides backend API endpoints for OIDC/Keycloak configuration management
 */

require_once('../../bootstrap.php');

global $dbh;
global $logger;

// Security check - only admin users
$allowed_levels = [9, 8];
if (!user_is_logged_in()) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Authentication required']));
}

if (!in_array(CURRENT_USER_LEVEL, $allowed_levels)) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Insufficient permissions']));
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'test_connection':
            handleTestConnection();
            break;
            
        case 'validate_config':
            handleValidateConfig();
            break;
            
        case 'save_config':
            handleSaveConfig();
            break;
            
        case 'load_config':
            handleLoadConfig();
            break;
            
        case 'sync_users':
            handleSyncUsers();
            break;
            
        case 'sync_status':
            handleSyncStatus();
            break;
            
        case 'export_config':
            handleExportConfig();
            break;
            
        case 'import_config':
            handleImportConfig();
            break;
            
        case 'discover_realm':
            handleDiscoverRealm();
            break;
            
        case 'test_credentials':
            handleTestCredentials();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    $logger->addError('OIDC AJAX Error: ' . $e->getMessage(), ['action' => $action]);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

function handleTestConnection() {
    $server_url = $_POST['server_url'] ?? '';
    $realm = $_POST['realm'] ?? '';
    
    if (empty($server_url) || empty($realm)) {
        echo json_encode(['status' => 'error', 'message' => 'Server URL and realm are required']);
        return;
    }
    
    try {
        $discovery_url = rtrim($server_url, '/') . "/auth/realms/{$realm}/.well-known/openid_configuration";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET',
                'header' => "Accept: application/json\r\n"
            ]
        ]);
        
        $response = file_get_contents($discovery_url, false, $context);
        
        if ($response === false) {
            throw new Exception('Unable to connect to Keycloak server');
        }
        
        $config = json_decode($response, true);
        
        if (!$config || !isset($config['issuer'])) {
            throw new Exception('Invalid OpenID Connect configuration response');
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Connection successful',
            'data' => [
                'issuer' => $config['issuer'],
                'auth_endpoint' => $config['authorization_endpoint'],
                'token_endpoint' => $config['token_endpoint'],
                'userinfo_endpoint' => $config['userinfo_endpoint']
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $e->getMessage()]);
    }
}

function handleValidateConfig() {
    $config = [
        'server_url' => $_POST['server_url'] ?? '',
        'realm' => $_POST['realm'] ?? '',
        'client_id' => $_POST['client_id'] ?? '',
        'client_secret' => $_POST['client_secret'] ?? '',
        'redirect_uri' => $_POST['redirect_uri'] ?? ''
    ];
    
    $errors = [];
    
    // Validate required fields
    if (empty($config['server_url'])) {
        $errors[] = 'Server URL is required';
    } else if (!filter_var($config['server_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Server URL must be a valid URL';
    }
    
    if (empty($config['realm'])) {
        $errors[] = 'Realm is required';
    }
    
    if (empty($config['client_id'])) {
        $errors[] = 'Client ID is required';
    }
    
    if (empty($config['client_secret'])) {
        $errors[] = 'Client Secret is required';
    }
    
    if (empty($config['redirect_uri'])) {
        $errors[] = 'Redirect URI is required';
    } else if (!filter_var($config['redirect_uri'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Redirect URI must be a valid URL';
    }
    
    if (!empty($errors)) {
        echo json_encode(['status' => 'error', 'message' => 'Validation failed', 'errors' => $errors]);
        return;
    }
    
    // Test connection with provided config
    try {
        $discovery_url = rtrim($config['server_url'], '/') . "/auth/realms/{$config['realm']}/.well-known/openid_configuration";
        $response = file_get_contents($discovery_url, false, stream_context_create([
            'http' => ['timeout' => 10]
        ]));
        
        if ($response === false) {
            throw new Exception('Cannot connect to Keycloak server');
        }
        
        $oidc_config = json_decode($response, true);
        if (!$oidc_config || !isset($oidc_config['issuer'])) {
            throw new Exception('Invalid OpenID Connect configuration');
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Configuration is valid',
            'data' => [
                'issuer' => $oidc_config['issuer'],
                'endpoints_available' => isset($oidc_config['token_endpoint'])
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Configuration test failed: ' . $e->getMessage()]);
    }
}

function handleSaveConfig() {
    global $dbh;
    
    $config = [
        'oidc_enabled' => $_POST['oidc_enabled'] ?? 'false',
        'oidc_provider_type' => $_POST['oidc_provider_type'] ?? 'keycloak',
        'oidc_server_url' => $_POST['oidc_server_url'] ?? '',
        'oidc_realm' => $_POST['oidc_realm'] ?? '',
        'oidc_client_id' => $_POST['oidc_client_id'] ?? '',
        'oidc_client_secret' => $_POST['oidc_client_secret'] ?? '',
        'oidc_redirect_uri' => $_POST['oidc_redirect_uri'] ?? '',
        'oidc_auto_create_users' => $_POST['oidc_auto_create_users'] ?? 'true',
        'oidc_default_role' => $_POST['oidc_default_role'] ?? '0',
        'oidc_group_sync_enabled' => $_POST['oidc_group_sync_enabled'] ?? 'false',
        'oidc_role_mapping' => $_POST['oidc_role_mapping'] ?? '{}',
        'oidc_user_attribute_mapping' => $_POST['oidc_user_attribute_mapping'] ?? '{}'
    ];
    
    try {
        $dbh->beginTransaction();
        
        foreach ($config as $option_name => $option_value) {
            $stmt = $dbh->prepare("
                INSERT INTO tbl_options (name, value) 
                VALUES (:name, :value) 
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ");
            $stmt->execute([
                ':name' => $option_name,
                ':value' => $option_value
            ]);
        }
        
        $dbh->commit();
        
        // Log configuration change
        global $logger;
        $logger->addInfo('OIDC configuration updated', [
            'user_id' => CURRENT_USER_ID,
            'enabled' => $config['oidc_enabled'],
            'provider' => $config['oidc_provider_type']
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Configuration saved successfully'
        ]);
        
    } catch (Exception $e) {
        $dbh->rollBack();
        throw new Exception('Failed to save configuration: ' . $e->getMessage());
    }
}

function handleLoadConfig() {
    global $dbh;
    
    $config_keys = [
        'oidc_enabled',
        'oidc_provider_type', 
        'oidc_server_url',
        'oidc_realm',
        'oidc_client_id',
        'oidc_client_secret',
        'oidc_redirect_uri',
        'oidc_auto_create_users',
        'oidc_default_role',
        'oidc_group_sync_enabled',
        'oidc_role_mapping',
        'oidc_user_attribute_mapping'
    ];
    
    $config = [];
    
    foreach ($config_keys as $key) {
        $config[$key] = get_option($key);
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $config
    ]);
}

function handleSyncUsers() {
    global $dbh, $logger;
    
    try {
        $config = [
            'server_url' => get_option('oidc_server_url'),
            'realm' => get_option('oidc_realm'),
            'client_id' => get_option('oidc_client_id'),
            'client_secret' => get_option('oidc_client_secret')
        ];
        
        if (empty($config['server_url']) || empty($config['realm'])) {
            throw new Exception('OIDC not properly configured');
        }
        
        // Start sync process
        $sync_id = uniqid('sync_');
        
        $stmt = $dbh->prepare("
            INSERT INTO tbl_sync_jobs (sync_id, status, started_at, config) 
            VALUES (:sync_id, :status, :started_at, :config)
        ");
        
        $stmt->execute([
            ':sync_id' => $sync_id,
            ':status' => 'running',
            ':started_at' => date('Y-m-d H:i:s'),
            ':config' => json_encode($config)
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'User synchronization started',
            'sync_id' => $sync_id
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to start user sync: ' . $e->getMessage());
    }
}

function handleSyncStatus() {
    global $dbh;
    
    $sync_id = $_GET['sync_id'] ?? '';
    
    if (empty($sync_id)) {
        // Get latest sync status
        $stmt = $dbh->prepare("
            SELECT sync_id, status, started_at, completed_at, users_synced, errors 
            FROM tbl_sync_jobs 
            ORDER BY started_at DESC 
            LIMIT 1
        ");
    } else {
        $stmt = $dbh->prepare("
            SELECT sync_id, status, started_at, completed_at, users_synced, errors 
            FROM tbl_sync_jobs 
            WHERE sync_id = :sync_id
        ");
        $stmt->bindParam(':sync_id', $sync_id);
    }
    
    $stmt->execute();
    $sync = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sync) {
        echo json_encode(['status' => 'error', 'message' => 'Sync job not found']);
        return;
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $sync
    ]);
}

function handleExportConfig() {
    $config = [
        'oidc_enabled' => get_option('oidc_enabled'),
        'oidc_provider_type' => get_option('oidc_provider_type'),
        'oidc_server_url' => get_option('oidc_server_url'),
        'oidc_realm' => get_option('oidc_realm'),
        'oidc_client_id' => get_option('oidc_client_id'),
        // Note: Don't export client_secret for security
        'oidc_redirect_uri' => get_option('oidc_redirect_uri'),
        'oidc_auto_create_users' => get_option('oidc_auto_create_users'),
        'oidc_default_role' => get_option('oidc_default_role'),
        'oidc_group_sync_enabled' => get_option('oidc_group_sync_enabled'),
        'oidc_role_mapping' => get_option('oidc_role_mapping'),
        'oidc_user_attribute_mapping' => get_option('oidc_user_attribute_mapping'),
        'exported_at' => date('Y-m-d H:i:s'),
        'exported_by' => CURRENT_USER_USERNAME
    ];
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="oidc-config-export-' . date('Y-m-d') . '.json"');
    
    echo json_encode($config, JSON_PRETTY_PRINT);
}

function handleImportConfig() {
    if (!isset($_FILES['config_file'])) {
        echo json_encode(['status' => 'error', 'message' => 'No configuration file uploaded']);
        return;
    }
    
    $file = $_FILES['config_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'File upload error']);
        return;
    }
    
    $config_json = file_get_contents($file['tmp_name']);
    $config = json_decode($config_json, true);
    
    if (!$config) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid configuration file format']);
        return;
    }
    
    try {
        global $dbh;
        $dbh->beginTransaction();
        
        $allowed_keys = [
            'oidc_enabled', 'oidc_provider_type', 'oidc_server_url', 'oidc_realm',
            'oidc_client_id', 'oidc_redirect_uri', 'oidc_auto_create_users',
            'oidc_default_role', 'oidc_group_sync_enabled', 'oidc_role_mapping',
            'oidc_user_attribute_mapping'
        ];
        
        foreach ($allowed_keys as $key) {
            if (isset($config[$key])) {
                $stmt = $dbh->prepare("
                    INSERT INTO tbl_options (name, value) 
                    VALUES (:name, :value) 
                    ON DUPLICATE KEY UPDATE value = VALUES(value)
                ");
                $stmt->execute([
                    ':name' => $key,
                    ':value' => $config[$key]
                ]);
            }
        }
        
        $dbh->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Configuration imported successfully'
        ]);
        
    } catch (Exception $e) {
        $dbh->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Import failed: ' . $e->getMessage()]);
    }
}

function handleDiscoverRealm() {
    $server_url = $_POST['server_url'] ?? '';
    
    if (empty($server_url)) {
        echo json_encode(['status' => 'error', 'message' => 'Server URL is required']);
        return;
    }
    
    try {
        $admin_url = rtrim($server_url, '/') . '/auth/admin/realms';
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET'
            ]
        ]);
        
        // Note: This would need proper admin credentials in real implementation
        echo json_encode([
            'status' => 'info',
            'message' => 'Realm discovery requires admin credentials. Please check your Keycloak admin console for available realms.',
            'admin_url' => rtrim($server_url, '/') . '/auth/admin/'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Discovery failed: ' . $e->getMessage()]);
    }
}

function handleTestCredentials() {
    $server_url = $_POST['server_url'] ?? '';
    $realm = $_POST['realm'] ?? '';
    $client_id = $_POST['client_id'] ?? '';
    $client_secret = $_POST['client_secret'] ?? '';
    
    if (empty($server_url) || empty($realm) || empty($client_id) || empty($client_secret)) {
        echo json_encode(['status' => 'error', 'message' => 'All credential fields are required']);
        return;
    }
    
    try {
        // Test by attempting to get an access token
        $token_url = rtrim($server_url, '/') . "/auth/realms/{$realm}/protocol/openid-connect/token";
        
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $client_id,
            'client_secret' => $client_secret
        ];
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($data)
            ]
        ]);
        
        $response = file_get_contents($token_url, false, $context);
        
        if ($response === false) {
            throw new Exception('Unable to connect to token endpoint');
        }
        
        $token_data = json_decode($response, true);
        
        if (isset($token_data['error'])) {
            throw new Exception('Authentication failed: ' . $token_data['error_description']);
        }
        
        if (!isset($token_data['access_token'])) {
            throw new Exception('No access token received');
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Credentials are valid',
            'data' => [
                'token_type' => $token_data['token_type'] ?? 'Bearer',
                'expires_in' => $token_data['expires_in'] ?? 0
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Credential test failed: ' . $e->getMessage()]);
    }
}
?>