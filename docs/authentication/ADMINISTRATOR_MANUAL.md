# ProjectSend OIDC Administrator Manual

## Table of Contents

1. [Overview](#overview)
2. [Installation and Configuration](#installation-and-configuration)
3. [User Management](#user-management)
4. [Security Configuration](#security-configuration)
5. [Monitoring and Logging](#monitoring-and-logging)
6. [Troubleshooting](#troubleshooting)
7. [Maintenance and Updates](#maintenance-and-updates)
8. [Compliance and Audit](#compliance-and-audit)

## Overview

This manual provides comprehensive guidance for administrators managing ProjectSend's OpenID Connect (OIDC) authentication integration. The OIDC implementation enables Single Sign-On (SSO) capabilities through enterprise identity providers like Keycloak, Azure AD, Auth0, and other compliant OIDC providers.

### Key Features

- **Enterprise SSO Integration**: Seamless integration with corporate identity providers
- **Automatic User Provisioning**: Users are automatically created and updated based on OIDC claims
- **Role-Based Access Control**: Map OIDC groups to ProjectSend roles (Admin, User, Client)
- **Security Hardening**: Industry-standard security measures including PKCE, token encryption, and audit logging
- **Multi-Provider Support**: Support for multiple OIDC providers simultaneously
- **Compliance Ready**: Built-in support for SOC 2, GDPR, and other regulatory requirements

## Installation and Configuration

### Prerequisites

Before configuring OIDC authentication, ensure your system meets these requirements:

#### System Requirements
- PHP 7.4 or higher with OpenSSL extension
- HTTPS enabled (required for production)
- MySQL/MariaDB database with OIDC schema updates
- Valid SSL certificate for production deployments

#### Required PHP Extensions
```bash
# Verify required extensions are installed
php -m | grep -E "(openssl|curl|json|hash|session)"
```

#### Database Schema Updates

Apply the OIDC database schema updates:

```sql
-- Run these SQL commands to add OIDC support
ALTER TABLE tbl_users ADD COLUMN oidc_subject VARCHAR(255) UNIQUE;
ALTER TABLE tbl_users ADD COLUMN oidc_provider VARCHAR(100);
ALTER TABLE tbl_users ADD COLUMN last_oidc_login TIMESTAMP;

-- Optional: Create audit log table
CREATE TABLE tbl_oidc_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    user_identifier VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    event_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Basic Configuration

#### 1. Enable OIDC in Configuration

Edit your main configuration file or use the web-based configuration interface:

```php
// config.php - Basic OIDC Configuration
$oidc_enabled = true;
$oidc_provider_url = 'https://your-keycloak.example.com/realms/projectsend';
$oidc_client_id = 'projectsend-client';
$oidc_client_secret = 'your-secure-client-secret';
$oidc_redirect_uri = 'https://your-projectsend.example.com/oidc-callback.php';
$oidc_scopes = 'openid profile email groups';
```

#### 2. User Attribute Mapping

Configure how OIDC claims map to ProjectSend user attributes:

```php
$oidc_user_mapping = array(
    'username' => 'preferred_username',  // Primary username field
    'email' => 'email',                  // Email address
    'name' => 'name',                    // Display name
    'first_name' => 'given_name',        // First name
    'last_name' => 'family_name',        // Last name
    'groups' => 'groups',                // Group membership
    'roles' => 'realm_roles'             // Role assignments
);
```

#### 3. Role Mapping Configuration

Map OIDC groups to ProjectSend roles:

```php
$oidc_group_role_mapping = array(
    // OIDC Group Name => ProjectSend Role
    'ProjectSend Administrators' => 'a',  // Admin role
    'ProjectSend Users' => 'u',           // User role  
    'ProjectSend Clients' => 'c',         // Client role
    'IT Department' => 'u',               // Custom group mapping
    'External Users' => 'c'               // External user mapping
);

// Default role for users not in mapped groups
$oidc_default_role = 'c'; // Client role as default
```

### Advanced Configuration

#### Security Settings

Configure advanced security options:

```php
$oidc_security = array(
    'require_https' => true,              // Enforce HTTPS
    'validate_state' => true,             // CSRF protection
    'validate_nonce' => true,             // Replay attack protection
    'token_encryption' => true,           // Encrypt stored tokens
    'audit_logging' => true,              // Enable audit trail
    'csrf_protection' => true,            // Additional CSRF measures
    'rate_limiting' => true,              // Rate limit auth attempts
    'secure_cookies' => true,             // Use secure cookie flags
    'session_timeout' => 3600             // Session timeout in seconds
);
```

#### Token Management

Configure token handling and validation:

```php
$oidc_token_settings = array(
    'verify_signature' => true,           // Validate JWT signatures
    'verify_issuer' => true,              // Validate token issuer
    'verify_audience' => true,            // Validate token audience
    'clock_tolerance' => 300,             // Clock skew tolerance (5 min)
    'cache_jwks' => true,                 // Cache JSON Web Key Sets
    'jwks_cache_ttl' => 3600,            // JWKS cache duration
    'token_refresh_threshold' => 300      // Refresh tokens 5 min before expiry
);
```

#### Multi-Provider Configuration

Support multiple OIDC providers:

```php
$oidc_providers = array(
    'keycloak' => array(
        'name' => 'Corporate Login',
        'provider_url' => 'https://keycloak.company.com/realms/main',
        'client_id' => 'projectsend-internal',
        'client_secret' => 'internal-secret',
        'scopes' => 'openid profile email groups',
        'button_text' => 'Login with Corporate Account',
        'button_color' => '#1f5582'
    ),
    'azure' => array(
        'name' => 'Microsoft Azure AD',
        'provider_url' => 'https://login.microsoftonline.com/tenant-id/v2.0',
        'client_id' => 'azure-client-id',
        'client_secret' => 'azure-client-secret',
        'scopes' => 'openid profile email',
        'button_text' => 'Login with Microsoft',
        'button_color' => '#0078d4'
    )
);
```

## User Management

### Automatic User Provisioning

OIDC integration supports automatic user creation and updates:

#### User Creation Process

1. **First Login**: New users are automatically created during their first OIDC login
2. **Attribute Mapping**: User attributes are populated from OIDC claims
3. **Role Assignment**: Users are assigned roles based on group membership
4. **Profile Updates**: User profiles are updated on subsequent logins

#### User Provisioning Settings

```php
$oidc_provisioning = array(
    'auto_create_users' => true,          // Automatically create new users
    'auto_update_users' => true,          // Update user info on login
    'update_on_login' => true,            // Refresh user data each login
    'preserve_local_changes' => false,    // Override local changes with OIDC data
    'require_email_verification' => false, // Skip email verification for OIDC users
    'send_welcome_email' => true          // Send welcome email to new users
);
```

### Manual User Management

#### Creating OIDC-Enabled Users

For pre-provisioned users:

1. Navigate to **Users** → **Add User**
2. Fill in basic information
3. Check **"Enable OIDC Authentication"**
4. Enter the OIDC Subject ID (if known)
5. Set appropriate role and permissions

#### Linking Existing Users

To link existing ProjectSend users with OIDC identities:

1. Navigate to **Users** → Select user
2. Edit user profile
3. Enable **"OIDC Authentication"**
4. The system will link the account on the user's next OIDC login

#### Bulk User Operations

Use the admin interface for bulk operations:

- **Bulk Enable OIDC**: Enable OIDC for multiple users
- **Role Updates**: Update roles based on current group membership
- **Account Sync**: Synchronize user data from OIDC provider

### User Role Management

#### Role Assignment Logic

1. **Group-Based Assignment**: Primary role assignment based on OIDC groups
2. **Claim-Based Assignment**: Direct role assignment from OIDC claims
3. **Default Role Fallback**: Apply default role if no group matches
4. **Manual Override**: Administrators can manually override automatic assignments

#### Role Hierarchy

- **Admin (a)**: Full system access, user management, configuration
- **User (u)**: Can upload files, manage own content, create clients
- **Client (c)**: Can only download files shared with them

## Security Configuration

### SSL/TLS Configuration

#### Requirements

- Valid SSL certificate from trusted CA
- TLS 1.2 or higher
- Strong cipher suites
- HSTS headers enabled

#### Apache Configuration Example

```apache
<VirtualHost *:443>
    ServerName projectsend.company.com
    DocumentRoot /var/www/projectsend
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/projectsend.crt
    SSLCertificateKeyFile /etc/ssl/private/projectsend.key
    
    # Security headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Frame-Options "DENY"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # CSP for OIDC integration
    Header always set Content-Security-Policy "default-src 'self'; connect-src 'self' https://your-oidc-provider.com; frame-src 'none';"
</VirtualHost>
```

### Firewall Configuration

#### Network Security Rules

```bash
# Allow HTTPS traffic
iptables -A INPUT -p tcp --dport 443 -j ACCEPT

# Allow outbound HTTPS for OIDC communication
iptables -A OUTPUT -p tcp --dport 443 -m state --state NEW,ESTABLISHED -j ACCEPT

# Block direct HTTP access (redirect to HTTPS)
iptables -A INPUT -p tcp --dport 80 -j REDIRECT --to-port 443
```

#### Application-Level Security

- Rate limiting on authentication endpoints
- IP-based access controls for admin functions
- Audit logging for all security events
- Token encryption at rest
- Secure session management

### Access Control Configuration

#### IP Restrictions

Restrict admin access by IP address:

```php
$admin_ip_whitelist = array(
    '192.168.1.0/24',    // Internal network
    '10.0.0.0/8',        // Corporate network
    '203.0.113.0/24'     // Remote office
);
```

#### Time-Based Access Controls

Configure access restrictions by time:

```php
$access_time_restrictions = array(
    'admin_hours' => array(
        'enabled' => true,
        'timezone' => 'America/New_York',
        'allowed_hours' => '08:00-18:00',
        'allowed_days' => 'Mon-Fri'
    ),
    'maintenance_window' => array(
        'enabled' => true,
        'start' => '02:00',
        'end' => '04:00',
        'block_login' => true
    )
);
```

## Monitoring and Logging

### Audit Logging

#### Log Configuration

Enable comprehensive audit logging:

```php
$oidc_logging = array(
    'enabled' => true,
    'log_level' => 'INFO',                // DEBUG, INFO, WARN, ERROR
    'log_file' => '/var/log/projectsend/oidc.log',
    'audit_table' => 'tbl_oidc_audit_log',
    'log_successful_auth' => true,
    'log_failed_auth' => true,
    'log_user_provisioning' => true,
    'log_role_changes' => true,
    'log_config_changes' => true,
    'retention_days' => 90
);
```

#### Monitored Events

The system automatically logs:

- Authentication attempts (success/failure)
- User provisioning events
- Role assignment changes
- Configuration modifications
- Security violations
- Token refresh events
- Logout events

#### Log Format

```json
{
  "timestamp": "2024-01-15T10:30:00Z",
  "event_type": "authentication_success",
  "user_identifier": "john.doe@company.com",
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "provider": "keycloak",
  "additional_data": {
    "groups": ["ProjectSend Users", "IT Department"],
    "role_assigned": "u"
  }
}
```

### Performance Monitoring

#### Key Metrics

Monitor these performance indicators:

- **Authentication Response Time**: Should be < 2 seconds
- **Token Validation Time**: Should be < 500ms
- **User Provisioning Time**: Should be < 1 second
- **JWKS Cache Hit Rate**: Should be > 95%
- **Error Rate**: Should be < 1%

#### Monitoring Configuration

```php
$oidc_monitoring = array(
    'enable_metrics' => true,
    'metrics_endpoint' => '/admin/oidc-metrics',
    'alert_thresholds' => array(
        'auth_response_time' => 5.0,      // seconds
        'error_rate' => 0.05,             // 5%
        'failed_auth_rate' => 0.10        // 10%
    ),
    'health_check_interval' => 300        // 5 minutes
);
```

### Integration with Monitoring Systems

#### Syslog Integration

```php
$syslog_config = array(
    'enabled' => true,
    'facility' => LOG_LOCAL0,
    'priority' => LOG_INFO,
    'format' => 'json'
);
```

#### SNMP Integration

```php
$snmp_config = array(
    'enabled' => true,
    'community' => 'projectsend-readonly',
    'oids' => array(
        'auth_success_count' => '1.3.6.1.4.1.12345.1.1',
        'auth_failure_count' => '1.3.6.1.4.1.12345.1.2',
        'active_sessions' => '1.3.6.1.4.1.12345.1.3'
    )
);
```

## Troubleshooting

### Common Issues and Solutions

#### 1. Authentication Failures

**Problem**: Users cannot authenticate via OIDC

**Troubleshooting Steps**:

1. Check OIDC provider connectivity:
   ```bash
   curl -I https://your-keycloak.com/realms/projectsend/.well-known/openid_configuration
   ```

2. Verify client configuration in provider
3. Check redirect URI configuration
4. Review log files for detailed error messages
5. Validate client secret and credentials

#### 2. User Provisioning Issues

**Problem**: Users are not created or updated correctly

**Solutions**:

1. Verify user mapping configuration
2. Check group membership in OIDC provider
3. Review role mapping settings
4. Ensure database has proper permissions
5. Check for duplicate email addresses

#### 3. Token Validation Errors

**Problem**: JWT token validation failures

**Solutions**:

1. Verify system time synchronization
2. Check JWKS endpoint accessibility
3. Validate token signature algorithm
4. Review clock tolerance settings
5. Clear JWKS cache if needed

#### 4. Session Management Problems

**Problem**: Users logged out unexpectedly

**Solutions**:

1. Check session timeout configuration
2. Verify token refresh settings
3. Review cookie security settings
4. Check for clock drift issues
5. Validate session storage configuration

### Diagnostic Tools

#### OIDC Configuration Validator

```bash
php scripts/validate-oidc-config.php
```

#### Connection Test Tool

```bash
php scripts/test-oidc-connection.php --provider=keycloak
```

#### User Sync Tool

```bash
php scripts/sync-oidc-users.php --dry-run
```

### Log Analysis

#### Common Log Patterns

**Successful Authentication**:
```
[INFO] OIDC authentication successful for user: john.doe@company.com
```

**Configuration Error**:
```
[ERROR] OIDC configuration invalid: missing client_secret
```

**Token Validation Failure**:
```
[WARN] JWT token validation failed: signature verification failed
```

## Maintenance and Updates

### Regular Maintenance Tasks

#### Daily Tasks

- Monitor authentication success/failure rates
- Review security logs for anomalies
- Check system performance metrics

#### Weekly Tasks

- Review user provisioning reports
- Analyze access patterns
- Update group/role mappings as needed

#### Monthly Tasks

- Review and rotate client secrets
- Update JWKS cache configuration
- Audit user access rights
- Review compliance reports

### Update Procedures

#### OIDC Provider Updates

When updating your OIDC provider:

1. Test changes in staging environment
2. Update discovery endpoint URLs if changed
3. Verify client configuration remains valid
4. Update JWKS cache if keys change
5. Monitor authentication after updates

#### ProjectSend Updates

Before updating ProjectSend:

1. Backup current OIDC configuration
2. Test OIDC functionality in staging
3. Update database schema if required
4. Verify configuration migration
5. Test all authentication flows

### Backup and Recovery

#### Configuration Backup

```bash
# Backup OIDC configuration
mysqldump -u root -p projectsend_db tbl_users tbl_oidc_audit_log > oidc_backup.sql

# Backup configuration files
tar -czf oidc_config_backup.tar.gz config/ includes/classes/class.oidc.php
```

#### Recovery Procedures

1. Restore database from backup
2. Restore configuration files
3. Verify OIDC provider connectivity
4. Test authentication functionality
5. Review audit logs for any issues

## Compliance and Audit

### Regulatory Compliance

#### GDPR Compliance

- **Data Minimization**: Only collect necessary OIDC claims
- **Consent Management**: Implement clear consent mechanisms
- **Right to Deletion**: Provide user account deletion procedures
- **Data Portability**: Allow users to export their data
- **Privacy by Design**: Default to most privacy-protective settings

#### SOC 2 Compliance

- **Access Controls**: Implement role-based access controls
- **Audit Logging**: Maintain comprehensive audit trails
- **Data Encryption**: Encrypt sensitive data at rest and in transit
- **Incident Response**: Implement security incident procedures
- **Monitoring**: Continuous security monitoring and alerting

#### HIPAA Compliance (if applicable)

- **Encryption**: Use AES-256 encryption for PHI data
- **Access Logs**: Maintain detailed access logs
- **User Authentication**: Implement strong authentication measures
- **Audit Controls**: Regular security audits and assessments

### Audit Preparation

#### Documentation Requirements

Maintain these documents for audits:

1. OIDC implementation documentation
2. Security configuration records
3. User access control matrices
4. Incident response procedures
5. Regular security assessment reports

#### Audit Checklist

- [ ] OIDC configuration documented and current
- [ ] User access rights reviewed and approved
- [ ] Security logs retained per policy
- [ ] Encryption keys properly managed
- [ ] Incident response plan tested
- [ ] Vulnerability assessments completed
- [ ] Staff security training completed

### Reporting and Analytics

#### Security Reports

Generate regular security reports:

```php
// Generate monthly security report
php scripts/generate-security-report.php --month=2024-01 --format=pdf
```

#### Access Reports

Monitor user access patterns:

```php
// Generate user access report
php scripts/generate-access-report.php --period=weekly --users=all
```

#### Compliance Reports

Generate compliance reports:

```php
// Generate SOC 2 compliance report
php scripts/generate-compliance-report.php --standard=soc2 --quarter=Q1
```

---

For additional support and advanced configurations, contact your system administrator or refer to the [ProjectSend OIDC Integration Guide](OIDC_INTEGRATION_GUIDE.md) and [Troubleshooting Guide](TROUBLESHOOTING.md).