<?php
return [
    'keycloak_base_url' => 'https://your-keycloak-server.com/auth',
    'realm' => 'projectsend-realm',
    'client_id' => 'projectsend-client',
    'client_secret' => 'your-client-secret',
    'admin_client_id' => 'admin-cli',
    'admin_client_secret' => 'your-admin-client-secret',
    
    // Security settings
    'pkce_required' => true,
    'token_introspection' => true,
    'token_revocation' => true,
    
    // User sync settings
    'sync_interval' => 3600, // Sync every hour
    'sync_enabled' => true,
    
    // Logging and monitoring
    'log_authentication_events' => true,
    'event_listener_enabled' => true,
    
    // Advanced configuration
    'offline_token_support' => true,
    'session_timeout' => 3600, // 1 hour
    
    // Group and role mapping
    'group_sync_enabled' => true,
    'role_mapping' => [
        'admin' => 'keycloak-admin-role',
        'user' => 'keycloak-user-role'
    ]
];