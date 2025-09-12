# ProjectSend OIDC Integration Troubleshooting Guide

## Table of Contents

1. [Quick Diagnosis](#quick-diagnosis)
2. [Authentication Issues](#authentication-issues)
3. [User Provisioning Problems](#user-provisioning-problems)
4. [Token and Session Issues](#token-and-session-issues)
5. [Configuration Problems](#configuration-problems)
6. [Network and Connectivity Issues](#network-and-connectivity-issues)
7. [Performance Issues](#performance-issues)
8. [Security and Compliance Issues](#security-and-compliance-issues)
9. [Provider-Specific Issues](#provider-specific-issues)
10. [Diagnostic Tools](#diagnostic-tools)
11. [Log Analysis](#log-analysis)
12. [Emergency Recovery](#emergency-recovery)

## Quick Diagnosis

### Step 1: Check System Status

```bash
# Check OIDC configuration status
php scripts/oidc-health-check.php

# Verify basic connectivity
curl -I https://your-keycloak.com/realms/projectsend/.well-known/openid_configuration

# Check ProjectSend logs
tail -f /var/log/projectsend/oidc.log
```

### Step 2: Common Quick Fixes

1. **Clear JWKS cache**: Delete cached JWT keys
2. **Restart web server**: Reload PHP configuration
3. **Check system time**: Ensure time synchronization
4. **Verify SSL certificates**: Check certificate validity
5. **Test with different browser**: Rule out browser-specific issues

## Authentication Issues

### Issue: Users Cannot Login via OIDC

#### Symptoms
- "Authentication failed" error message
- Redirect loops between ProjectSend and OIDC provider
- Users see generic error page

#### Diagnostic Steps

1. **Check Discovery Endpoint**
   ```bash
   curl -s "https://your-keycloak.com/realms/projectsend/.well-known/openid_configuration" | jq .
   ```
   
2. **Verify Client Configuration**
   ```bash
   # Check if client exists and is enabled in provider
   # Verify redirect URIs match exactly
   ```

3. **Test Authorization URL**
   ```bash
   # Generate test authorization URL and verify parameters
   php scripts/test-auth-url.php
   ```

#### Common Causes and Solutions

| Cause | Solution |
|-------|----------|
| Invalid client credentials | Verify client ID and secret in provider |
| Mismatched redirect URI | Ensure redirect URI exactly matches in both systems |
| Client disabled in provider | Enable client in OIDC provider configuration |
| Invalid scopes requested | Check scope configuration in both systems |
| SSL certificate issues | Verify SSL setup and certificate validity |

#### Code Example: Testing Client Configuration

```php
// Test client configuration
$config = [
    'provider_url' => 'https://your-keycloak.com/realms/projectsend',
    'client_id' => 'projectsend-client',
    'client_secret' => 'your-client-secret'
];

$oidc = new OIDCAuthentication($config);
$isValid = $oidc->validateConfiguration();

if (!$isValid) {
    $errors = $oidc->getConfigurationErrors();
    foreach ($errors as $error) {
        echo "Configuration Error: " . $error . "\n";
    }
}
```

### Issue: "Invalid State Parameter" Errors

#### Symptoms
- CSRF protection errors
- "State parameter mismatch" in logs
- Users redirected to error page after provider authentication

#### Diagnostic Steps

1. **Check Session Storage**
   ```php
   // Verify session is properly maintained
   session_start();
   var_dump($_SESSION['oidc_state']);
   ```

2. **Verify State Generation and Validation**
   ```bash
   php scripts/test-state-validation.php
   ```

#### Solutions

1. **Session Configuration Issues**
   ```php
   // Ensure proper session configuration
   ini_set('session.cookie_secure', 1);
   ini_set('session.cookie_httponly', 1);
   ini_set('session.cookie_samesite', 'Lax');
   ```

2. **Load Balancer Issues**
   ```bash
   # Configure sticky sessions or use shared session storage
   # Redis/Memcached for session storage across multiple servers
   ```

### Issue: "Nonce Validation Failed"

#### Symptoms
- Replay attack protection triggering
- "Invalid nonce" errors in logs
- Intermittent authentication failures

#### Solutions

1. **Clock Synchronization**
   ```bash
   # Synchronize system clocks
   ntpdate -s time.nist.gov
   ```

2. **Increase Clock Tolerance**
   ```php
   $oidc_token_settings = [
       'clock_tolerance' => 300  // 5 minutes
   ];
   ```

## User Provisioning Problems

### Issue: Users Not Created Automatically

#### Symptoms
- Authentication succeeds but no user account created
- "User provisioning failed" in logs
- Users redirected to registration page

#### Diagnostic Steps

1. **Check User Mapping Configuration**
   ```php
   $mapping = $oidc->getUserMapping();
   var_dump($mapping);
   ```

2. **Verify OIDC Claims**
   ```bash
   # Decode and inspect ID token claims
   php scripts/decode-jwt-token.php --token="[ID_TOKEN]"
   ```

3. **Check Database Permissions**
   ```sql
   -- Verify database user has INSERT permissions
   SHOW GRANTS FOR 'projectsend_user'@'localhost';
   ```

#### Solutions

1. **Missing Required Claims**
   ```php
   // Ensure required claims are available
   $required_claims = ['preferred_username', 'email', 'sub'];
   foreach ($required_claims as $claim) {
       if (empty($user_data[$claim])) {
           throw new Exception("Missing required claim: " . $claim);
       }
   }
   ```

2. **Database Constraint Violations**
   ```sql
   -- Check for duplicate entries
   SELECT username, email, COUNT(*) FROM tbl_users 
   GROUP BY username, email 
   HAVING COUNT(*) > 1;
   ```

### Issue: Incorrect Role Assignment

#### Symptoms
- Users created with wrong roles
- Admin users getting client access
- Role mapping not working

#### Diagnostic Steps

1. **Test Group Mapping**
   ```php
   $groups = ['ProjectSend Administrators', 'IT Department'];
   $role = $oidc->mapGroupsToRole($groups);
   echo "Mapped role: " . $role . "\n";
   ```

2. **Check Group Claims in Token**
   ```bash
   php scripts/inspect-user-groups.php --username="john.doe"
   ```

#### Solutions

1. **Fix Group Mapping Configuration**
   ```php
   $oidc_group_role_mapping = [
       'ProjectSend Administrators' => 'a',  // Admin
       'ProjectSend Users' => 'u',           // User
       'ProjectSend Clients' => 'c',         // Client
       'default' => 'c'                      // Default role
   ];
   ```

2. **Update Provider Group Configuration**
   ```bash
   # In Keycloak, ensure groups are included in token claims
   # Add group mapper to client configuration
   ```

## Token and Session Issues

### Issue: "Token Expired" Errors

#### Symptoms
- Users logged out unexpectedly
- "Access token expired" messages
- Frequent re-authentication required

#### Solutions

1. **Configure Token Refresh**
   ```php
   $oidc_token_settings = [
       'refresh_threshold' => 300,  // Refresh 5 minutes before expiry
       'enable_silent_refresh' => true
   ];
   ```

2. **Adjust Token Lifetimes**
   ```bash
   # In Keycloak Admin Console:
   # Realm Settings -> Tokens -> Access Token Lifespan: 15 minutes
   # Refresh Token Lifespan: 30 minutes
   ```

### Issue: "Invalid Signature" Errors

#### Symptoms
- JWT signature validation failures
- "Token signature invalid" in logs
- Authentication randomly failing

#### Diagnostic Steps

1. **Check JWKS Endpoint**
   ```bash
   curl -s "https://your-keycloak.com/realms/projectsend/protocol/openid-connect/certs" | jq .
   ```

2. **Verify Key Rotation**
   ```bash
   # Check if provider keys have rotated
   php scripts/check-jwks-keys.php
   ```

#### Solutions

1. **Clear JWKS Cache**
   ```php
   $oidc->clearJWKSCache();
   ```

2. **Update Key Validation**
   ```php
   $oidc_token_settings = [
       'jwks_cache_ttl' => 1800,  // 30 minutes
       'verify_signature' => true,
       'algorithm' => ['RS256', 'PS256']  // Support multiple algorithms
   ];
   ```

## Configuration Problems

### Issue: "Configuration Invalid" Errors

#### Symptoms
- OIDC not starting
- Configuration validation failures
- Missing required parameters

#### Diagnostic Checklist

```bash
# Run configuration validator
php scripts/validate-oidc-config.php --detailed

# Check required parameters
echo "Provider URL: $oidc_provider_url"
echo "Client ID: $oidc_client_id"
echo "Client Secret: [REDACTED]"
echo "Redirect URI: $oidc_redirect_uri"
```

#### Common Configuration Issues

1. **Missing Environment Variables**
   ```bash
   # Check environment variables are set
   env | grep OIDC
   ```

2. **Incorrect URL Formats**
   ```php
   // Ensure URLs end with proper paths
   $provider_url = 'https://keycloak.com/realms/projectsend';  // Correct
   $provider_url = 'https://keycloak.com/realms/projectsend/'; // Also OK
   $provider_url = 'https://keycloak.com';                     // Incorrect
   ```

### Issue: SSL/TLS Certificate Problems

#### Symptoms
- "SSL certificate verification failed"
- cURL errors in logs
- Cannot connect to OIDC provider

#### Solutions

1. **Certificate Verification Issues**
   ```php
   // For testing only - disable SSL verification
   $oidc_curl_options = [
       CURLOPT_SSL_VERIFYPEER => false,
       CURLOPT_SSL_VERIFYHOST => false
   ];
   ```

2. **Certificate Authority Issues**
   ```bash
   # Add custom CA certificates
   echo "curl.cainfo = /path/to/cacert.pem" >> /etc/php/php.ini
   ```

## Network and Connectivity Issues

### Issue: Connection Timeouts

#### Symptoms
- "Connection timed out" errors
- Slow OIDC authentication
- Intermittent connectivity issues

#### Solutions

1. **Adjust Timeout Settings**
   ```php
   $oidc_network_settings = [
       'connection_timeout' => 30,  // 30 seconds
       'read_timeout' => 60,        // 60 seconds
       'retry_attempts' => 3
   ];
   ```

2. **Network Debugging**
   ```bash
   # Test connectivity
   telnet your-keycloak.com 443
   
   # Check DNS resolution
   nslookup your-keycloak.com
   
   # Trace network path
   traceroute your-keycloak.com
   ```

### Issue: Firewall Blocking Requests

#### Symptoms
- Connection refused errors
- OIDC provider unreachable
- Outbound HTTPS blocked

#### Solutions

1. **Configure Firewall Rules**
   ```bash
   # Allow outbound HTTPS
   iptables -A OUTPUT -p tcp --dport 443 -j ACCEPT
   
   # Allow specific OIDC provider
   iptables -A OUTPUT -d your-keycloak-ip -p tcp --dport 443 -j ACCEPT
   ```

2. **Proxy Configuration**
   ```php
   $oidc_proxy_settings = [
       'proxy_host' => 'proxy.company.com',
       'proxy_port' => 8080,
       'proxy_auth' => 'username:password'
   ];
   ```

## Performance Issues

### Issue: Slow Authentication Response

#### Symptoms
- Authentication takes > 5 seconds
- Users experience delays during login
- High server load during authentication

#### Diagnostic Steps

1. **Performance Profiling**
   ```bash
   php scripts/oidc-performance-test.php --iterations=100
   ```

2. **Check Cache Performance**
   ```bash
   php scripts/check-cache-stats.php
   ```

#### Solutions

1. **Enable Caching**
   ```php
   $oidc_cache_settings = [
       'enable_jwks_cache' => true,
       'enable_userinfo_cache' => true,
       'cache_ttl' => 3600
   ];
   ```

2. **Optimize Database Queries**
   ```sql
   -- Add database indexes for OIDC operations
   CREATE INDEX idx_users_oidc_subject ON tbl_users (oidc_subject);
   CREATE INDEX idx_users_email ON tbl_users (email);
   ```

## Security and Compliance Issues

### Issue: Security Audit Failures

#### Symptoms
- Security scanner alerts
- Compliance violations
- Vulnerability warnings

#### Security Checklist

```bash
# Run security audit
php scripts/oidc-security-audit.php

# Check for common vulnerabilities
php scripts/check-security-vulnerabilities.php

# Validate encryption settings
php scripts/validate-encryption.php
```

#### Solutions

1. **Enable Security Headers**
   ```php
   $security_headers = [
       'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
       'X-Frame-Options' => 'DENY',
       'X-Content-Type-Options' => 'nosniff',
       'Content-Security-Policy' => "default-src 'self'"
   ];
   ```

2. **Token Encryption**
   ```php
   $oidc_security = [
       'encrypt_tokens' => true,
       'encryption_key' => 'your-32-character-encryption-key',
       'encryption_method' => 'AES-256-GCM'
   ];
   ```

## Provider-Specific Issues

### Keycloak Issues

#### Issue: Realm Not Found
```bash
# Check realm exists and is enabled
curl -s "https://keycloak.com/realms/projectsend/.well-known/openid_configuration"
```

#### Issue: Client Not Found
```bash
# Verify client exists in correct realm
# Check client is enabled and properly configured
```

### Azure AD Issues

#### Issue: Tenant Configuration
```bash
# Verify tenant ID in provider URL
# Check application registration in Azure Portal
```

### Auth0 Issues

#### Issue: Application Settings
```bash
# Verify application type is set to "Regular Web Application"
# Check allowed callback URLs include your redirect URI
```

## Diagnostic Tools

### Built-in Diagnostic Scripts

1. **Health Check**
   ```bash
   php scripts/oidc-health-check.php
   ```

2. **Configuration Validator**
   ```bash
   php scripts/validate-oidc-config.php --verbose
   ```

3. **Connection Tester**
   ```bash
   php scripts/test-oidc-connection.php --provider=keycloak
   ```

4. **Token Decoder**
   ```bash
   php scripts/decode-jwt-token.php --token="eyJ..."
   ```

### External Tools

1. **JWT.io**: Decode and inspect JWT tokens
2. **Postman**: Test OIDC endpoints manually
3. **cURL**: Test HTTP requests and responses
4. **OpenSSL**: Verify SSL certificates

### Log Analysis Commands

```bash
# Filter authentication logs
grep "authentication" /var/log/projectsend/oidc.log | tail -50

# Check error patterns
grep "ERROR" /var/log/projectsend/oidc.log | sort | uniq -c

# Monitor real-time logs
tail -f /var/log/projectsend/oidc.log | grep -E "(ERROR|WARN)"

# Analyze performance metrics
grep "performance" /var/log/projectsend/oidc.log | awk '{print $NF}' | sort -n
```

## Emergency Recovery

### Disable OIDC Authentication

If OIDC is preventing access to the system:

1. **Emergency Configuration Override**
   ```php
   // Add to config.php temporarily
   $oidc_enabled = false;
   ```

2. **Database Override**
   ```sql
   -- Temporarily disable OIDC
   UPDATE tbl_options SET value = '0' WHERE name = 'oidc_enabled';
   ```

### Restore Local Authentication

```bash
# Enable local authentication temporarily
php scripts/enable-local-auth.php --emergency

# Reset admin password if needed
php scripts/reset-admin-password.php --username=admin
```

### Backup and Recovery

1. **Create Configuration Backup**
   ```bash
   cp config.php config.php.backup
   mysqldump projectsend tbl_users tbl_options > users_backup.sql
   ```

2. **Restore from Backup**
   ```bash
   cp config.php.backup config.php
   mysql projectsend < users_backup.sql
   ```

## Getting Help

### Log Information to Collect

When reporting issues, include:

1. **System Information**
   ```bash
   php --version
   cat /etc/os-release
   apache2 -v  # or nginx -v
   ```

2. **Configuration (Sanitized)**
   ```bash
   php scripts/export-config.php --sanitized
   ```

3. **Recent Log Entries**
   ```bash
   tail -100 /var/log/projectsend/oidc.log
   ```

4. **Error Details**
   - Exact error messages
   - Steps to reproduce
   - Browser and version
   - Network environment details

### Support Channels

- **GitHub Issues**: [ProjectSend Issues](https://github.com/projectsend/projectsend/issues)
- **Community Forum**: [ProjectSend Community](https://projectsend.org/community)
- **Security Issues**: security@projectsend.org
- **Enterprise Support**: enterprise@projectsend.org

### Escalation Process

1. **Level 1**: Check documentation and common solutions
2. **Level 2**: Run diagnostic tools and collect logs  
3. **Level 3**: Contact community support with detailed information
4. **Level 4**: For critical issues, contact enterprise support

---

Remember to always test solutions in a development environment before applying them to production systems.