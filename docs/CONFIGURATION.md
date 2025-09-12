# ProjectSend Configuration Guide

This comprehensive guide covers all configuration options for ProjectSend, with a particular focus on the newly implemented OIDC (OpenID Connect) authentication features and integration with enterprise identity providers.

## Table of Contents

- [Configuration Overview](#configuration-overview)
- [Database Configuration](#database-configuration)
- [Email and SMTP Settings](#email-and-smtp-settings)
- [File Upload and Storage](#file-upload-and-storage)
- [Security Settings](#security-settings)
- [Authentication Configuration](#authentication-configuration)
- [OIDC Configuration (Comprehensive)](#oidc-configuration-comprehensive)
- [Performance and Caching](#performance-and-caching)
- [Advanced Configuration](#advanced-configuration)
- [Environment-Specific Configurations](#environment-specific-configurations)
- [Troubleshooting Configuration](#troubleshooting-configuration)

## Configuration Overview

ProjectSend uses a centralized configuration system with multiple layers:

1. **Core Configuration**: `includes/sys.config.php` - Main configuration file
2. **Database Settings**: Stored in database tables with web interface
3. **Environment Variables**: For Docker and cloud deployments
4. **Provider-Specific Configs**: OIDC, LDAP, and social login configurations

### Configuration File Structure

```php
<?php
/**
 * ProjectSend Configuration File
 * Location: includes/sys.config.php
 */

// Database settings
define('DB_DRIVER', 'mysql');
define('DB_NAME', 'projectsend');
// ... other database settings

// Security settings
define('ENCRYPTION_KEY', 'your-32-character-key-here');
// ... other security settings

// Feature flags
define('OIDC_ENABLED', true);
define('SOCIAL_LOGIN_ENABLED', true);
// ... other features
```

## Database Configuration

### Basic Database Settings

```php
// Database connection settings
define('DB_DRIVER', 'mysql');        // Database type (mysql, pgsql)
define('DB_NAME', 'projectsend');    // Database name
define('DB_HOST', 'localhost');      // Database host
define('DB_USER', 'projectsend');    // Database username
define('DB_PASSWORD', 'password');   // Database password
define('DB_PORT', '3306');          // Database port (optional)

// Connection options
define('DB_CHARSET', 'utf8mb4');     // Character set
define('DB_COLLATION', 'utf8mb4_unicode_ci'); // Collation
define('DB_PERSISTENT', false);     // Use persistent connections
define('DB_TIMEOUT', 30);           // Connection timeout (seconds)
```

### Advanced Database Configuration

```php
// Connection pooling and performance
define('DB_MAX_CONNECTIONS', 100);   // Maximum database connections
define('DB_CONNECTION_TIMEOUT', 5);  // Connection timeout
define('DB_QUERY_TIMEOUT', 30);     // Query timeout

// Database-specific options
define('DB_SSL_ENABLED', false);    // Enable SSL connections
define('DB_SSL_CA', '');           // SSL CA certificate path
define('DB_SSL_CERT', '');         // SSL certificate path
define('DB_SSL_KEY', '');          // SSL key path

// Backup and maintenance
define('DB_BACKUP_ENABLED', true);   // Enable automatic backups
define('DB_BACKUP_FREQUENCY', 'daily'); // Backup frequency
define('DB_BACKUP_RETENTION', 30);   // Keep backups for N days
```

### Multiple Database Support

For enterprise deployments with read/write separation:

```php
// Master database (write operations)
define('DB_MASTER_HOST', 'master.db.company.com');
define('DB_MASTER_USER', 'projectsend_write');
define('DB_MASTER_PASSWORD', 'write_password');

// Slave databases (read operations)
define('DB_SLAVE_HOSTS', 'slave1.db.company.com,slave2.db.company.com');
define('DB_SLAVE_USER', 'projectsend_read');
define('DB_SLAVE_PASSWORD', 'read_password');

// Load balancing
define('DB_LOAD_BALANCING', true);
define('DB_FAILOVER_ENABLED', true);
```

## Email and SMTP Settings

### Basic Email Configuration

```php
// Email method (smtp, sendmail, mail)
define('MAIL_METHOD', 'smtp');

// SMTP server settings
define('SMTP_HOST', 'smtp.company.com');
define('SMTP_PORT', 587);              // Common ports: 25, 465, 587
define('SMTP_SECURITY', 'tls');        // none, ssl, tls, starttls
define('SMTP_USER', 'noreply@company.com');
define('SMTP_PASS', 'smtp_password');

// Email sender information
define('MAIL_FROM_NAME', 'Company File Sharing');
define('MAIL_FROM_ADDRESS', 'noreply@company.com');
define('MAIL_REPLY_TO', 'support@company.com');
```

### Advanced Email Configuration

```php
// Email authentication and security
define('SMTP_AUTH_REQUIRED', true);    // SMTP authentication required
define('SMTP_VERIFY_SSL', true);       // Verify SSL certificates
define('SMTP_TIMEOUT', 30);            // SMTP timeout (seconds)
define('SMTP_KEEP_ALIVE', true);       // Keep SMTP connection alive

// Email templates and formatting
define('EMAIL_TEMPLATE_DIR', 'templates/email/');
define('EMAIL_USE_HTML', true);        // Send HTML emails
define('EMAIL_CHARSET', 'UTF-8');      // Email character set

// Bulk email settings
define('EMAIL_BATCH_SIZE', 50);        // Send emails in batches
define('EMAIL_BATCH_DELAY', 2);        // Delay between batches (seconds)
define('EMAIL_DAILY_LIMIT', 1000);     // Daily email limit
```

### Popular Email Provider Settings

**Gmail/Google Workspace:**
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURITY', 'tls');
// Use App Password for authentication
```

**Microsoft 365/Outlook:**
```php
define('SMTP_HOST', 'smtp-mail.outlook.com');
define('SMTP_PORT', 587);
define('SMTP_SECURITY', 'starttls');
```

**Amazon SES:**
```php
define('SMTP_HOST', 'email-smtp.us-east-1.amazonaws.com');
define('SMTP_PORT', 587);
define('SMTP_SECURITY', 'tls');
```

**SendGrid:**
```php
define('SMTP_HOST', 'smtp.sendgrid.net');
define('SMTP_PORT', 587);
define('SMTP_SECURITY', 'tls');
define('SMTP_USER', 'apikey');
define('SMTP_PASS', 'your_sendgrid_api_key');
```

## File Upload and Storage

### Basic File Upload Settings

```php
// File size limits (in bytes)
define('MAX_FILESIZE', 500 * 1024 * 1024); // 500MB
define('MIN_FILESIZE', 1024);               // 1KB minimum

// Allowed file types
define('ALLOWED_FILE_TYPES', 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,odt,ods,odp,jpg,jpeg,png,gif,bmp,tiff,svg,mp4,avi,mov,wmv,mp3,wav,zip,rar,7z,tar,gz');

// Blocked file types (security)
define('BLOCKED_FILE_TYPES', 'exe,bat,cmd,com,pif,scr,vbs,js,jar,php,asp,jsp,pl,py,rb');

// File upload behavior
define('UPLOAD_OVERWRITE', false);         // Allow file overwriting
define('UPLOAD_AUTO_RENAME', true);        // Auto-rename duplicate files
define('UPLOAD_VIRUS_SCAN', true);         // Enable virus scanning
```

### Storage Configuration

```php
// Local file storage
define('UPLOAD_FOLDER', 'upload/files/');
define('THUMBNAILS_FOLDER', 'upload/thumbnails/');
define('TEMP_FOLDER', 'upload/temp/');

// Storage paths (absolute paths recommended for production)
define('STORAGE_BASE_PATH', '/var/www/projectsend/upload/');
define('THUMBNAIL_BASE_PATH', '/var/www/projectsend/upload/thumbnails/');

// File organization
define('ORGANIZE_FILES_BY_DATE', true);    // Organize in YYYY/MM folders
define('ORGANIZE_FILES_BY_USER', false);   // Organize by user folders
define('ORGANIZE_FILES_BY_CLIENT', false); // Organize by client folders
```

### Cloud Storage Integration

**Amazon S3:**
```php
define('STORAGE_TYPE', 's3');
define('AWS_ACCESS_KEY_ID', 'your_access_key');
define('AWS_SECRET_ACCESS_KEY', 'your_secret_key');
define('AWS_REGION', 'us-east-1');
define('AWS_BUCKET_NAME', 'your-projectsend-bucket');
define('AWS_USE_SSL', true);
define('AWS_CUSTOM_DOMAIN', ''); // Optional CDN domain
```

**Azure Blob Storage:**
```php
define('STORAGE_TYPE', 'azure');
define('AZURE_ACCOUNT_NAME', 'your_account_name');
define('AZURE_ACCOUNT_KEY', 'your_account_key');
define('AZURE_CONTAINER_NAME', 'projectsend-files');
define('AZURE_USE_SSL', true);
```

**Google Cloud Storage:**
```php
define('STORAGE_TYPE', 'gcs');
define('GCS_PROJECT_ID', 'your-project-id');
define('GCS_KEY_FILE', '/path/to/service-account.json');
define('GCS_BUCKET_NAME', 'your-projectsend-bucket');
```

### File Processing Settings

```php
// Image processing
define('GENERATE_THUMBNAILS', true);
define('THUMBNAIL_MAX_WIDTH', 300);
define('THUMBNAIL_MAX_HEIGHT', 200);
define('THUMBNAIL_QUALITY', 85);        // JPEG quality (1-100)

// Image formats
define('THUMBNAIL_FORMAT', 'jpg');      // jpg, png, webp
define('CONVERT_IMAGES_TO_WEB', true);  // Convert to web-friendly formats

// Document processing
define('EXTRACT_TEXT_CONTENT', true);   // Extract text for search
define('GENERATE_PDF_PREVIEWS', true);  // Generate PDF previews
define('OFFICE_DOCUMENT_CONVERSION', false); // Convert Office docs to PDF

// Video processing (requires FFmpeg)
define('VIDEO_PROCESSING', false);
define('VIDEO_THUMBNAIL_TIME', 5);      // Generate thumbnail at X seconds
define('VIDEO_MAX_DURATION', 3600);     // Maximum video length (seconds)
```

## Security Settings

### Basic Security Configuration

```php
// Encryption and hashing
define('ENCRYPTION_KEY', 'your-32-character-secret-key-here');
define('HASH_SALT', 'your-unique-salt-string-here');
define('HASH_ALGORITHM', 'sha256');     // Hashing algorithm
define('ENCRYPTION_METHOD', 'AES-256-GCM'); // Encryption method

// Session security
define('SESSION_TIMEOUT', 3600);        // Session timeout (seconds)
define('SESSION_REGENERATE_ID', true);  // Regenerate session ID
define('SESSION_COOKIE_HTTPONLY', true); // HTTP-only cookies
define('SESSION_COOKIE_SECURE', true);  // Secure cookies (HTTPS only)
define('SESSION_COOKIE_SAMESITE', 'Strict'); // SameSite attribute

// Password security
define('PASSWORD_MIN_LENGTH', 8);       // Minimum password length
define('PASSWORD_COMPLEXITY', true);    // Require complex passwords
define('PASSWORD_HISTORY', 5);          // Remember last N passwords
define('PASSWORD_EXPIRY_DAYS', 90);     // Password expires after N days
```

### Advanced Security Settings

```php
// Access control
define('FORCE_HTTPS', true);            // Force HTTPS connections
define('SECURE_COOKIES', true);         // Use secure cookies
define('ADMIN_IP_RESTRICTION', false);  // Restrict admin access by IP
define('ALLOWED_ADMIN_IPS', '192.168.1.0/24,10.0.0.0/8'); // Allowed IP ranges

// Brute force protection
define('LOGIN_MAX_ATTEMPTS', 5);        // Max login attempts
define('LOGIN_LOCKOUT_TIME', 900);      // Lockout time (seconds)
define('LOGIN_ATTEMPT_WINDOW', 300);    // Time window for attempts

// Content Security Policy
define('CSP_ENABLED', true);
define('CSP_DEFAULT_SRC', "'self'");
define('CSP_SCRIPT_SRC', "'self' 'unsafe-inline'");
define('CSP_STYLE_SRC', "'self' 'unsafe-inline'");
define('CSP_IMG_SRC', "'self' data: https:");

// File security
define('SCAN_UPLOADED_FILES', true);    // Virus/malware scanning
define('QUARANTINE_INFECTED_FILES', true);
define('VIRUS_SCANNER', 'clamav');      // clamav, windows_defender
```

### Audit and Compliance

```php
// Audit logging
define('AUDIT_LOG_ENABLED', true);
define('AUDIT_LOG_FILE', 'logs/audit.log');
define('AUDIT_LOG_LEVEL', 'INFO');      // DEBUG, INFO, WARNING, ERROR
define('AUDIT_LOG_RETENTION', 365);     // Keep logs for N days

// Compliance settings
define('GDPR_COMPLIANCE', true);        // Enable GDPR features
define('DATA_RETENTION_DAYS', 2555);    // 7 years retention
define('ALLOW_DATA_EXPORT', true);      // User data export
define('ALLOW_DATA_DELETION', true);    // User data deletion

// Privacy settings
define('ANONYMIZE_IP_ADDRESSES', true); // Anonymize IPs in logs
define('COOKIE_CONSENT_REQUIRED', true); // Require cookie consent
define('PRIVACY_POLICY_URL', '/privacy-policy');
```

## Authentication Configuration

### Local Authentication

```php
// Local user authentication
define('LOCAL_AUTH_ENABLED', true);
define('ALLOW_USER_REGISTRATION', false);  // Open registration
define('REQUIRE_EMAIL_VERIFICATION', true);
define('USERNAME_MIN_LENGTH', 3);
define('USERNAME_MAX_LENGTH', 50);

// User account settings
define('DEFAULT_USER_ROLE', 'client');     // client, uploader
define('AUTO_ACTIVATE_USERS', false);      // Admin approval required
define('ALLOW_PROFILE_EDITING', true);     // Users can edit profiles
```

### Social Login Configuration

```php
// Social login settings
define('SOCIAL_LOGIN_ENABLED', true);
define('SOCIAL_AUTO_REGISTER', true);     // Auto-create accounts
define('SOCIAL_DEFAULT_ROLE', 'client');

// Google OAuth
define('GOOGLE_CLIENT_ID', 'your-google-client-id.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'your-google-client-secret');
define('GOOGLE_ENABLED', true);

// Facebook Login
define('FACEBOOK_APP_ID', 'your-facebook-app-id');
define('FACEBOOK_APP_SECRET', 'your-facebook-app-secret');
define('FACEBOOK_ENABLED', true);

// LinkedIn OAuth
define('LINKEDIN_CLIENT_ID', 'your-linkedin-client-id');
define('LINKEDIN_CLIENT_SECRET', 'your-linkedin-client-secret');
define('LINKEDIN_ENABLED', true);

// Twitter OAuth
define('TWITTER_CONSUMER_KEY', 'your-twitter-consumer-key');
define('TWITTER_CONSUMER_SECRET', 'your-twitter-consumer-secret');
define('TWITTER_ENABLED', false);

// GitHub OAuth
define('GITHUB_CLIENT_ID', 'your-github-client-id');
define('GITHUB_CLIENT_SECRET', 'your-github-client-secret');
define('GITHUB_ENABLED', false);
```

### LDAP Configuration

```php
// LDAP authentication
define('LDAP_ENABLED', true);
define('LDAP_HOST', 'ldap.company.com');
define('LDAP_PORT', 389);               // 389 for LDAP, 636 for LDAPS
define('LDAP_USE_TLS', true);           // Use TLS encryption
define('LDAP_VERSION', 3);              // LDAP protocol version

// LDAP binding
define('LDAP_BIND_DN', 'cn=projectsend,ou=service-accounts,dc=company,dc=com');
define('LDAP_BIND_PASSWORD', 'service_account_password');

// User search settings
define('LDAP_USER_BASE_DN', 'ou=users,dc=company,dc=com');
define('LDAP_USER_FILTER', '(uid=%s)'); // %s replaced with username
define('LDAP_USERNAME_ATTRIBUTE', 'uid');
define('LDAP_EMAIL_ATTRIBUTE', 'mail');
define('LDAP_NAME_ATTRIBUTE', 'cn');

// Group-based authorization
define('LDAP_GROUP_BASE_DN', 'ou=groups,dc=company,dc=com');
define('LDAP_GROUP_FILTER', '(member=%s)'); // %s replaced with user DN
define('LDAP_ADMIN_GROUP', 'cn=projectsend-admins,ou=groups,dc=company,dc=com');
define('LDAP_USER_GROUP', 'cn=projectsend-users,ou=groups,dc=company,dc=com');

// Advanced LDAP settings
define('LDAP_REFERRALS', false);        // Follow referrals
define('LDAP_AUTO_CREATE_USERS', true); // Auto-create LDAP users
define('LDAP_UPDATE_USER_INFO', true);  // Update user info on login
define('LDAP_CACHE_TIMEOUT', 300);      // Cache LDAP results (seconds)
```

## OIDC Configuration (Comprehensive)

The OIDC (OpenID Connect) integration is a flagship feature of ProjectSend, providing enterprise-grade authentication with support for multiple providers.

### Generic OIDC Configuration

```php
// Enable OIDC authentication
define('OIDC_ENABLED', true);
define('OIDC_PROVIDER', 'generic');     // Provider type: generic, keycloak, azure, auth0

// Basic OIDC settings
define('OIDC_CLIENT_ID', 'projectsend');
define('OIDC_CLIENT_SECRET', 'your-oidc-client-secret');
define('OIDC_ISSUER', 'https://your-oidc-provider.com');

// OIDC endpoints (auto-discovered if not specified)
define('OIDC_AUTHORIZATION_ENDPOINT', ''); // Leave empty for auto-discovery
define('OIDC_TOKEN_ENDPOINT', '');
define('OIDC_USERINFO_ENDPOINT', '');
define('OIDC_JWKS_URI', '');
define('OIDC_END_SESSION_ENDPOINT', '');

// OIDC flow settings
define('OIDC_FLOW_TYPE', 'authorization_code'); // authorization_code, hybrid
define('OIDC_RESPONSE_TYPE', 'code');
define('OIDC_SCOPES', 'openid email profile');
define('OIDC_REDIRECT_URI', 'https://files.company.com/login/oidc/callback');
```

### Keycloak Configuration

Keycloak is the primary supported OIDC provider with advanced features:

```php
// Keycloak-specific settings
define('OIDC_PROVIDER', 'keycloak');
define('OIDC_CLIENT_ID', 'projectsend');
define('OIDC_CLIENT_SECRET', 'keycloak-client-secret');
define('OIDC_ISSUER', 'https://keycloak.company.com/realms/projectsend');

// Keycloak realm and client settings
define('KEYCLOAK_REALM', 'projectsend');
define('KEYCLOAK_BASE_URL', 'https://keycloak.company.com');
define('KEYCLOAK_ADMIN_CLI_SECRET', 'admin-cli-secret'); // For user management

// Advanced Keycloak features
define('KEYCLOAK_USER_FEDERATION', true);    // Enable user federation
define('KEYCLOAK_GROUP_MAPPING', true);      // Map Keycloak groups to roles
define('KEYCLOAK_REALM_ROLES', true);        // Use realm roles
define('KEYCLOAK_CLIENT_ROLES', true);       // Use client roles

// Keycloak group to ProjectSend role mapping
define('KEYCLOAK_ROLE_MAPPING', json_encode([
    'projectsend-admin' => 'admin',
    'projectsend-manager' => 'uploader',
    'projectsend-user' => 'client',
    'default' => 'client'                    // Default role if no mapping found
]));

// Token validation
define('KEYCLOAK_VERIFY_SSL', true);         // Verify SSL certificates
define('KEYCLOAK_TOKEN_VALIDATION', 'local'); // local, remote
define('KEYCLOAK_JWK_CACHE_TIME', 3600);     // Cache JWK for 1 hour
```

### Azure AD Configuration

```php
// Azure Active Directory configuration
define('OIDC_PROVIDER', 'azure');
define('OIDC_CLIENT_ID', 'your-azure-app-id');
define('OIDC_CLIENT_SECRET', 'your-azure-app-secret');
define('OIDC_ISSUER', 'https://login.microsoftonline.com/your-tenant-id/v2.0');

// Azure AD specific settings
define('AZURE_TENANT_ID', 'your-tenant-id');
define('AZURE_TENANT_NAME', 'your-organization.onmicrosoft.com');
define('AZURE_API_VERSION', 'v2.0');

// Azure AD group mapping
define('AZURE_USE_GROUPS', true);
define('AZURE_GROUP_CLAIM', 'groups');       // Claim containing group IDs
define('AZURE_GROUP_MAPPING', json_encode([
    'group-id-for-admins' => 'admin',
    'group-id-for-uploaders' => 'uploader',
    'group-id-for-users' => 'client'
]));

// Multi-tenant support
define('AZURE_MULTITENANT', false);          // Single tenant by default
define('AZURE_ALLOWED_TENANTS', '');         // Comma-separated tenant IDs
```

### Auth0 Configuration

```php
// Auth0 configuration
define('OIDC_PROVIDER', 'auth0');
define('OIDC_CLIENT_ID', 'your-auth0-client-id');
define('OIDC_CLIENT_SECRET', 'your-auth0-client-secret');
define('OIDC_ISSUER', 'https://your-tenant.auth0.com/');

// Auth0 specific settings
define('AUTH0_DOMAIN', 'your-tenant.auth0.com');
define('AUTH0_CONNECTION', '');              // Specific connection (optional)
define('AUTH0_AUDIENCE', '');                // API audience (optional)

// Auth0 role mapping using custom claims
define('AUTH0_ROLE_CLAIM', 'https://projectsend.com/roles');
define('AUTH0_ROLE_MAPPING', json_encode([
    'ProjectSend Admin' => 'admin',
    'ProjectSend Manager' => 'uploader',
    'ProjectSend User' => 'client'
]));

// Auth0 user metadata
define('AUTH0_USE_USER_METADATA', true);
define('AUTH0_METADATA_NAMESPACE', 'https://projectsend.com/');
```

### Generic OIDC Provider Configuration

For other OIDC-compliant providers:

```php
// Generic OIDC provider
define('OIDC_PROVIDER', 'generic');
define('OIDC_CLIENT_ID', 'your-client-id');
define('OIDC_CLIENT_SECRET', 'your-client-secret');
define('OIDC_ISSUER', 'https://your-provider.com');

// Manual endpoint configuration (if auto-discovery fails)
define('OIDC_AUTHORIZATION_ENDPOINT', 'https://your-provider.com/auth');
define('OIDC_TOKEN_ENDPOINT', 'https://your-provider.com/token');
define('OIDC_USERINFO_ENDPOINT', 'https://your-provider.com/userinfo');
define('OIDC_JWKS_URI', 'https://your-provider.com/jwks');

// Custom claim mapping
define('OIDC_CLAIM_MAPPING', json_encode([
    'sub' => 'user_id',                     // Subject claim
    'email' => 'email',                     // Email claim
    'name' => 'full_name',                  // Name claim
    'given_name' => 'first_name',           // First name claim
    'family_name' => 'last_name',           // Last name claim
    'roles' => 'roles',                     // Roles claim
    'groups' => 'groups'                    // Groups claim
]));
```

### OIDC User Synchronization

```php
// User provisioning and synchronization
define('OIDC_AUTO_PROVISION', true);        // Auto-create users
define('OIDC_UPDATE_USER_INFO', true);      // Update user info on login
define('OIDC_SYNC_ON_LOGIN', true);         // Sync user data on each login

// Default settings for new users
define('OIDC_DEFAULT_ROLE', 'client');      // Default role for new users
define('OIDC_DEFAULT_STATUS', 'active');    // Default status: active, inactive
define('OIDC_AUTO_ACTIVATE', true);         // Auto-activate new users

// User matching strategy
define('OIDC_USER_MATCHING', 'email');      // email, username, sub
define('OIDC_ALLOW_EMAIL_UPDATE', true);    // Allow email updates
define('OIDC_ALLOW_NAME_UPDATE', true);     // Allow name updates

// Deprovisioning
define('OIDC_DEPROVISION_USERS', false);    // Disable users not in OIDC
define('OIDC_DEPROVISION_GRACE_PERIOD', 30); // Grace period in days
```

### OIDC Security Settings

```php
// Token validation and security
define('OIDC_VERIFY_SSL', true);            // Verify SSL certificates
define('OIDC_SSL_CA_PATH', '');             // Custom CA bundle path
define('OIDC_TIMEOUT', 30);                 // Request timeout (seconds)

// Token settings
define('OIDC_TOKEN_CACHE_TIME', 300);       // Cache tokens for 5 minutes
define('OIDC_VERIFY_AUDIENCE', true);       // Verify audience claim
define('OIDC_VERIFY_NONCE', true);          // Verify nonce parameter
define('OIDC_CLOCK_TOLERANCE', 60);         // Clock skew tolerance (seconds)

// State parameter security
define('OIDC_USE_STATE', true);             // Use state parameter
define('OIDC_STATE_TIMEOUT', 600);          // State timeout (seconds)

// PKCE (Proof Key for Code Exchange)
define('OIDC_USE_PKCE', true);              // Use PKCE for additional security
define('OIDC_PKCE_METHOD', 'S256');         // PKCE code challenge method
```

### Advanced OIDC Features

```php
// Single Sign-On (SSO)
define('OIDC_SSO_ENABLED', true);           // Enable SSO
define('OIDC_FORCE_SSO', false);            // Force all users to use SSO
define('OIDC_SSO_BUTTON_TEXT', 'Login with Company Account');

// Single Logout (SLO)
define('OIDC_SLO_ENABLED', true);           // Enable single logout
define('OIDC_SLO_REDIRECT_URL', '');        // Post-logout redirect URL

// Just-In-Time (JIT) provisioning
define('OIDC_JIT_PROVISIONING', true);      // Enable JIT provisioning
define('OIDC_JIT_GROUP_SYNC', true);        // Sync groups during JIT
define('OIDC_JIT_ROLE_SYNC', true);         // Sync roles during JIT

// Multi-provider support
define('OIDC_MULTI_PROVIDER', false);       // Support multiple OIDC providers
define('OIDC_PROVIDER_SELECTION', true);    // Allow users to choose provider

// Provider discovery
define('OIDC_AUTO_DISCOVERY', true);        // Use .well-known endpoint
define('OIDC_DISCOVERY_CACHE_TIME', 3600);  // Cache discovery for 1 hour
```

### OIDC Debugging and Monitoring

```php
// Debug settings (development only)
define('OIDC_DEBUG', false);                // Enable debug logging
define('OIDC_LOG_TOKENS', false);           // Log tokens (NEVER in production)
define('OIDC_LOG_REQUESTS', true);          // Log HTTP requests

// Monitoring
define('OIDC_METRICS_ENABLED', true);       // Enable metrics collection
define('OIDC_HEALTH_CHECK_ENABLED', true);  // Enable health checks
define('OIDC_HEALTH_CHECK_INTERVAL', 300);  // Health check interval (seconds)

// Fallback authentication
define('OIDC_FALLBACK_ENABLED', true);      // Allow local login if OIDC fails
define('OIDC_FALLBACK_ADMIN_ONLY', true);   // Only allow admin fallback
```

## Performance and Caching

### Caching Configuration

```php
// Cache settings
define('ENABLE_CACHE', true);
define('CACHE_TYPE', 'redis');              // file, redis, memcached, apcu
define('CACHE_TTL', 3600);                  // Default cache TTL (seconds)
define('CACHE_PREFIX', 'projectsend_');     // Cache key prefix

// Redis cache settings
define('REDIS_HOST', 'localhost');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', '');
define('REDIS_DATABASE', 0);
define('REDIS_TIMEOUT', 5);
define('REDIS_PERSISTENT', true);

// Memcached settings
define('MEMCACHED_HOST', 'localhost');
define('MEMCACHED_PORT', 11211);
define('MEMCACHED_TIMEOUT', 5);

// File cache settings
define('FILE_CACHE_DIR', 'cache/');
define('FILE_CACHE_PERMISSIONS', 0755);

// Cache strategies
define('CACHE_USER_DATA', true);            // Cache user information
define('CACHE_FILE_METADATA', true);        // Cache file metadata
define('CACHE_THUMBNAILS', true);           // Cache thumbnail data
define('CACHE_OIDC_TOKENS', true);          // Cache OIDC tokens
define('CACHE_DATABASE_QUERIES', false);    // Cache database queries
```

### Performance Optimization

```php
// PHP performance settings
define('PHP_MEMORY_LIMIT', '512M');
define('PHP_MAX_EXECUTION_TIME', 300);
define('PHP_MAX_INPUT_TIME', 300);

// File processing optimization
define('ASYNC_FILE_PROCESSING', true);      // Process files asynchronously
define('THUMBNAIL_GENERATION_ASYNC', true); // Generate thumbnails async
define('BATCH_FILE_OPERATIONS', true);      // Batch file operations

// Database optimization
define('DB_CONNECTION_POOL_SIZE', 10);      // Connection pool size
define('DB_QUERY_CACHE_ENABLED', true);     // Enable query cache
define('DB_SLOW_QUERY_LOG', true);          // Log slow queries
define('DB_SLOW_QUERY_TIME', 2);            // Log queries slower than 2s

// Content optimization
define('ENABLE_GZIP_COMPRESSION', true);    // Enable gzip compression
define('MINIFY_CSS', true);                 // Minify CSS files
define('MINIFY_JS', true);                  // Minify JavaScript files
define('COMBINE_CSS_FILES', true);          // Combine CSS files
define('COMBINE_JS_FILES', true);           // Combine JS files
```

## Advanced Configuration

### API Configuration

```php
// API settings
define('API_ENABLED', true);                // Enable REST API
define('API_VERSION', 'v1');                // API version
define('API_BASE_PATH', '/api/v1/');        // API base path

// API authentication
define('API_AUTH_METHOD', 'token');         // token, oauth, jwt
define('API_TOKEN_EXPIRY', 86400);          // Token expiry (seconds)
define('API_RATE_LIMIT', 1000);             // Requests per hour
define('API_RATE_LIMIT_WINDOW', 3600);      // Rate limit window (seconds)

// API documentation
define('API_DOCS_ENABLED', true);           // Enable API documentation
define('API_DOCS_PATH', '/api/docs/');      // Documentation path
```

### Webhook Configuration

```php
// Webhook settings
define('WEBHOOKS_ENABLED', true);           // Enable webhooks
define('WEBHOOK_TIMEOUT', 30);              // Webhook timeout (seconds)
define('WEBHOOK_RETRY_ATTEMPTS', 3);        // Number of retry attempts
define('WEBHOOK_RETRY_DELAY', 60);          // Delay between retries (seconds)

// Webhook security
define('WEBHOOK_SECRET_KEY', 'your-webhook-secret');
define('WEBHOOK_VERIFY_SSL', true);         // Verify SSL for webhook URLs

// Webhook events
define('WEBHOOK_EVENTS', json_encode([
    'file.uploaded',
    'file.downloaded',
    'user.created',
    'user.login',
    'user.logout'
]));
```

### Multi-tenancy Configuration

```php
// Multi-tenant settings
define('MULTITENANCY_ENABLED', false);      // Enable multi-tenancy
define('TENANT_ISOLATION', 'database');     // database, schema, shared
define('TENANT_IDENTIFICATION', 'subdomain'); // subdomain, domain, header

// Tenant-specific settings
define('TENANT_ALLOW_SIGNUP', true);        // Allow tenant self-signup
define('TENANT_DEFAULT_PLAN', 'basic');     // Default tenant plan
define('TENANT_MAX_USERS', 100);            // Max users per tenant
define('TENANT_MAX_STORAGE', 10737418240);  // Max storage per tenant (10GB)
```

### Monitoring and Analytics

```php
// Analytics settings
define('ANALYTICS_ENABLED', true);          // Enable analytics
define('ANALYTICS_PROVIDER', 'internal');   // internal, google, custom
define('ANALYTICS_TRACK_DOWNLOADS', true);  // Track file downloads
define('ANALYTICS_TRACK_UPLOADS', true);    // Track file uploads
define('ANALYTICS_TRACK_LOGINS', true);     // Track user logins

// External analytics
define('GOOGLE_ANALYTICS_ID', '');          // Google Analytics tracking ID
define('CUSTOM_ANALYTICS_ENDPOINT', '');    // Custom analytics endpoint

// Monitoring
define('HEALTH_CHECK_ENABLED', true);       // Enable health checks
define('HEALTH_CHECK_ENDPOINTS', json_encode([
    '/health',
    '/api/v1/health',
    '/admin/health'
]));
```

## Environment-Specific Configurations

### Development Environment

```php
// Development settings
define('DEVELOPMENT_ENV', true);
define('DEBUG', true);
define('ERROR_REPORTING', true);
define('LOG_LEVEL', 'DEBUG');
define('OIDC_DEBUG', true);

// Security relaxed for development
define('FORCE_HTTPS', false);
define('SECURE_COOKIES', false);
define('OIDC_VERIFY_SSL', false);

// Performance settings for development
define('ENABLE_CACHE', false);
define('MINIFY_CSS', false);
define('MINIFY_JS', false);
```

### Staging Environment

```php
// Staging settings
define('DEVELOPMENT_ENV', false);
define('DEBUG', false);
define('ERROR_REPORTING', false);
define('LOG_LEVEL', 'INFO');

// Security similar to production
define('FORCE_HTTPS', true);
define('SECURE_COOKIES', true);
define('OIDC_VERIFY_SSL', true);

// Performance optimizations
define('ENABLE_CACHE', true);
define('CACHE_TTL', 1800);                  // Shorter cache for staging
```

### Production Environment

```php
// Production settings
define('DEVELOPMENT_ENV', false);
define('DEBUG', false);
define('ERROR_REPORTING', false);
define('LOG_LEVEL', 'WARNING');

// Maximum security
define('FORCE_HTTPS', true);
define('SECURE_COOKIES', true);
define('OIDC_VERIFY_SSL', true);
define('CSP_ENABLED', true);

// Maximum performance
define('ENABLE_CACHE', true);
define('CACHE_TTL', 3600);
define('MINIFY_CSS', true);
define('MINIFY_JS', true);
define('ASYNC_FILE_PROCESSING', true);
```

### Docker Configuration

When using Docker, use environment variables:

```yaml
# docker-compose.yml environment section
environment:
  - DB_HOST=projectsend-db
  - DB_NAME=projectsend
  - DB_USER=projectsend
  - DB_PASSWORD=${DB_PASSWORD}
  - OIDC_ENABLED=true
  - OIDC_PROVIDER=keycloak
  - OIDC_CLIENT_ID=projectsend
  - OIDC_CLIENT_SECRET=${OIDC_CLIENT_SECRET}
  - OIDC_ISSUER=${OIDC_ISSUER}
  - CACHE_TYPE=redis
  - REDIS_HOST=projectsend-redis
```

### Cloud Provider Configurations

**AWS Configuration:**
```php
// AWS-specific settings
define('AWS_REGION', 'us-east-1');
define('AWS_USE_IAM_ROLES', true);          // Use IAM roles for authentication
define('AWS_CLOUDFRONT_ENABLED', true);     // Use CloudFront for file delivery
define('AWS_SES_ENABLED', true);            // Use SES for email delivery
```

**Azure Configuration:**
```php
// Azure-specific settings
define('AZURE_REGION', 'East US');
define('AZURE_USE_MANAGED_IDENTITY', true); // Use managed identity
define('AZURE_CDN_ENABLED', true);          // Use Azure CDN
define('AZURE_KEY_VAULT_ENABLED', true);    // Store secrets in Key Vault
```

**Google Cloud Configuration:**
```php
// GCP-specific settings
define('GCP_REGION', 'us-central1');
define('GCP_USE_SERVICE_ACCOUNT', true);    // Use service account
define('GCP_CDN_ENABLED', true);            // Use Cloud CDN
define('GCP_SECRET_MANAGER_ENABLED', true); // Use Secret Manager
```

## Troubleshooting Configuration

### Configuration Validation

ProjectSend includes a built-in configuration validator:

```php
// Enable configuration validation
define('VALIDATE_CONFIG_ON_STARTUP', true);
define('CONFIG_VALIDATION_STRICT', false);  // Strict validation mode

// Validation settings
define('VALIDATE_DATABASE_CONNECTION', true);
define('VALIDATE_EMAIL_SETTINGS', true);
define('VALIDATE_OIDC_CONFIGURATION', true);
define('VALIDATE_STORAGE_PERMISSIONS', true);
```

### Common Configuration Issues

**Database Connection Issues:**
```php
// Debug database connection
define('DB_DEBUG', true);                   // Enable database debugging
define('DB_LOG_QUERIES', true);             // Log all database queries
define('DB_CONNECTION_RETRY', 3);           // Retry failed connections
```

**OIDC Configuration Issues:**
```php
// Debug OIDC configuration
define('OIDC_DEBUG', true);                 // Enable OIDC debugging
define('OIDC_LOG_REQUESTS', true);          // Log HTTP requests
define('OIDC_VALIDATE_CONFIG', true);       // Validate OIDC config on startup

// Test OIDC connectivity
define('OIDC_TEST_CONNECTION', true);       // Test provider connectivity
define('OIDC_TEST_ENDPOINTS', true);        // Test all OIDC endpoints
```

**Cache Configuration Issues:**
```php
// Debug cache configuration
define('CACHE_DEBUG', true);                // Enable cache debugging
define('CACHE_VALIDATE_CONNECTION', true);  // Validate cache connection
define('CACHE_FALLBACK_TO_FILE', true);     // Fallback to file cache if Redis fails
```

### Configuration Testing Commands

```bash
# Test configuration from command line
php bin/test-config.php

# Test specific components
php bin/test-config.php --database
php bin/test-config.php --oidc
php bin/test-config.php --cache
php bin/test-config.php --email

# Generate configuration report
php bin/config-report.php > config-report.txt
```

### Configuration Templates

**Minimal Configuration (sys.config.minimal.php):**
```php
<?php
// Minimal configuration for basic setup
define('DB_DRIVER', 'mysql');
define('DB_NAME', 'projectsend');
define('DB_HOST', 'localhost');
define('DB_USER', 'projectsend');
define('DB_PASSWORD', 'password');
define('ENCRYPTION_KEY', 'your-32-character-key-here');
define('BASE_URL', 'https://files.company.com/');
?>
```

**Enterprise Configuration Template (sys.config.enterprise.php):**
```php
<?php
// Enterprise configuration with OIDC, caching, and security
define('DB_DRIVER', 'mysql');
define('DB_NAME', 'projectsend');
define('DB_HOST', 'db.company.com');
define('DB_USER', 'projectsend');
define('DB_PASSWORD', getenv('DB_PASSWORD'));

define('OIDC_ENABLED', true);
define('OIDC_PROVIDER', 'keycloak');
define('OIDC_CLIENT_ID', 'projectsend');
define('OIDC_CLIENT_SECRET', getenv('OIDC_CLIENT_SECRET'));
define('OIDC_ISSUER', 'https://sso.company.com/realms/projectsend');

define('ENABLE_CACHE', true);
define('CACHE_TYPE', 'redis');
define('REDIS_HOST', 'cache.company.com');
define('REDIS_PASSWORD', getenv('REDIS_PASSWORD'));

define('FORCE_HTTPS', true);
define('SECURE_COOKIES', true);
define('CSP_ENABLED', true);
define('AUDIT_LOG_ENABLED', true);
?>
```

This comprehensive configuration guide covers all aspects of ProjectSend configuration, with particular emphasis on the new OIDC authentication features. The OIDC integration supports multiple providers and offers enterprise-grade security and user management capabilities.

For deployment instructions, see DEPLOY.md, and for development setup, refer to DEVELOP.md.