# OpenID Connect (OIDC) Integration Guide for ProjectSend

## Overview

This guide provides comprehensive instructions for integrating OpenID Connect authentication with ProjectSend, enabling Single Sign-On (SSO) capabilities through compliant OIDC providers like Keycloak, Auth0, Azure AD, and others.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Prerequisites](#prerequisites)
3. [Configuration](#configuration)
4. [Provider Setup](#provider-setup)
5. [Testing Integration](#testing-integration)
6. [Troubleshooting](#troubleshooting)
7. [Advanced Configuration](#advanced-configuration)

## Architecture Overview

The OIDC integration in ProjectSend follows the Authorization Code flow with PKCE (Proof Key for Code Exchange) for enhanced security:

```
[User] → [ProjectSend] → [OIDC Provider] → [Authentication] → [ProjectSend] → [User Access]
```

### Key Components

- **OIDC Client**: ProjectSend acts as an OIDC Relying Party (RP)
- **Authentication Flow**: Authorization Code flow with PKCE
- **Token Management**: Secure handling of ID tokens, access tokens, and refresh tokens
- **User Mapping**: Automatic user provisioning and attribute mapping
- **Session Management**: Secure session handling with token validation

## Prerequisites

### System Requirements

- PHP 7.4+ with OpenSSL extension
- cURL extension enabled
- JSON Web Token (JWT) support
- HTTPS enabled (required for production)
- Database with user authentication tables

### Required PHP Extensions

```bash
php -m | grep -E "(openssl|curl|json|hash)"
```

### OIDC Provider Requirements

Your OIDC provider must support:
- OpenID Connect Core 1.0
- Authorization Code flow
- PKCE (RFC 7636)
- JSON Web Tokens (JWT)
- Discovery endpoint (/.well-known/openid_configuration)

## Configuration

### Basic Configuration

1. **Enable OIDC Authentication**

Edit your `config.php` or use the admin interface:

```php
// Enable OIDC authentication
$oidc_enabled = true;

// OIDC Provider Configuration
$oidc_provider_url = 'https://your-keycloak-domain/auth/realms/your-realm';
$oidc_client_id = 'projectsend-client';
$oidc_client_secret = 'your-client-secret';
$oidc_redirect_uri = 'https://your-projectsend-domain/oidc-callback.php';

// Optional: Custom scopes
$oidc_scopes = 'openid profile email groups';

// User attribute mapping
$oidc_user_mapping = array(
    'username' => 'preferred_username',
    'email' => 'email',
    'name' => 'name',
    'groups' => 'groups'
);
```

2. **Database Configuration**

Ensure your database includes the OIDC-related columns:

```sql
ALTER TABLE tbl_users ADD COLUMN oidc_subject VARCHAR(255) UNIQUE;
ALTER TABLE tbl_users ADD COLUMN oidc_provider VARCHAR(100);
ALTER TABLE tbl_users ADD COLUMN last_oidc_login TIMESTAMP;
```

### Advanced Configuration Options

#### Token Validation Settings

```php
// Token validation configuration
$oidc_token_validation = array(
    'verify_signature' => true,
    'verify_issuer' => true,
    'verify_audience' => true,
    'clock_tolerance' => 300, // 5 minutes
    'cache_jwks' => true,
    'jwks_cache_ttl' => 3600 // 1 hour
);
```

#### User Provisioning Settings

```php
// Automatic user provisioning
$oidc_auto_provision = true;
$oidc_default_role = 'c'; // client role by default
$oidc_admin_groups = array('projectsend-admins', 'administrators');
$oidc_user_groups = array('projectsend-users', 'users');
```

#### Session Management

```php
// Session configuration
$oidc_session_timeout = 3600; // 1 hour
$oidc_refresh_before_expiry = 300; // 5 minutes
$oidc_logout_redirect = 'https://your-domain/goodbye.php';
```

## Provider Setup

### Generic OIDC Provider

For any compliant OIDC provider:

1. Create a new client application
2. Set the redirect URI: `https://your-domain/oidc-callback.php`
3. Enable Authorization Code flow
4. Enable PKCE (if available)
5. Configure appropriate scopes: `openid profile email`
6. Note the client ID and secret

### Provider-Specific Guides

- [Keycloak Setup Guide](KEYCLOAK_SETUP_GUIDE.md)
- [Azure AD Configuration](AZURE_AD_SETUP.md)
- [Auth0 Configuration](AUTH0_SETUP.md)
- [Google Workspace Setup](GOOGLE_WORKSPACE_SETUP.md)

## Testing Integration

### Manual Testing Steps

1. **Test Discovery Endpoint**

```bash
curl https://your-provider/.well-known/openid_configuration
```

2. **Test Authentication Flow**

Navigate to: `https://your-projectsend-domain/login.php`
Click "Login with SSO" and verify the flow completes successfully.

3. **Verify User Creation**

Check that users are created with proper OIDC subject mapping.

4. **Test Token Refresh**

Ensure tokens are refreshed before expiration.

### Automated Testing

Run the included test suite:

```bash
php vendor/bin/phpunit tests/OIDCIntegrationTest.php
```

## Security Considerations

### Required Security Measures

1. **HTTPS Only**: Never use OIDC over HTTP in production
2. **Secure Token Storage**: Store tokens securely with encryption
3. **PKCE Implementation**: Always use PKCE for public clients
4. **State Parameter**: Implement CSRF protection using state parameter
5. **Token Validation**: Verify all JWT tokens properly
6. **Session Security**: Implement secure session management

### Security Configuration

```php
// Security settings
$oidc_security = array(
    'require_https' => true,
    'secure_cookies' => true,
    'validate_state' => true,
    'token_encryption' => true,
    'audit_logging' => true
);
```

## Migration from Existing Authentication

### Gradual Migration Strategy

1. **Phase 1**: Enable OIDC alongside existing authentication
2. **Phase 2**: Migrate users gradually with email matching
3. **Phase 3**: Disable legacy authentication (optional)

### User Migration Script

```php
// Migration script example
$migration_script = 'scripts/migrate-users-to-oidc.php';
```

See [Migration Guide](MIGRATION_GUIDE.md) for detailed instructions.

## Monitoring and Logging

### Authentication Events

Monitor these key events:
- Successful OIDC logins
- Failed authentication attempts
- Token refresh events
- User provisioning events

### Log Configuration

```php
// Logging configuration
$oidc_logging = array(
    'log_level' => 'INFO',
    'log_file' => 'logs/oidc.log',
    'audit_trail' => true,
    'security_events' => true
);
```

## Performance Optimization

### Caching Strategy

- Cache JWKS (JSON Web Key Sets)
- Cache user information
- Implement token refresh optimization

### Recommended Settings

```php
// Performance optimization
$oidc_performance = array(
    'jwks_cache_enabled' => true,
    'jwks_cache_ttl' => 3600,
    'user_info_cache_ttl' => 300,
    'connection_timeout' => 10,
    'read_timeout' => 30
);
```

## Compliance and Standards

### Supported Standards

- OpenID Connect Core 1.0
- OAuth 2.0 (RFC 6749)
- PKCE (RFC 7636)
- JWT (RFC 7519)
- JWS (RFC 7515)
- JWE (RFC 7516) - optional

### Compliance Features

- GDPR compliance with user data handling
- SOC 2 audit trail support
- HIPAA-compliant token handling (when configured)

## Support and Resources

### Documentation

- [Administrator Manual](ADMINISTRATOR_MANUAL.md)
- [End User Guide](END_USER_GUIDE.md)
- [API Documentation](API_DOCUMENTATION.md)
- [Troubleshooting Guide](TROUBLESHOOTING.md)

### Community Support

- GitHub Issues: [ProjectSend Issues](https://github.com/projectsend/projectsend/issues)
- Community Forum: [ProjectSend Community](https://projectsend.org/community)
- Security Issues: security@projectsend.org

---

For detailed provider-specific setup instructions, refer to the individual setup guides in this documentation directory.