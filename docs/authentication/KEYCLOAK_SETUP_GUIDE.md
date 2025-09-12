# Keycloak Setup Guide for ProjectSend OIDC Integration

## Overview

This guide provides step-by-step instructions for configuring Keycloak as an OpenID Connect provider for ProjectSend. Keycloak is a robust, enterprise-grade identity and access management solution that provides excellent OIDC support.

## Table of Contents

1. [Keycloak Installation](#keycloak-installation)
2. [Realm Configuration](#realm-configuration)
3. [Client Configuration](#client-configuration)
4. [User Management](#user-management)
5. [Group and Role Mapping](#group-and-role-mapping)
6. [Advanced Configuration](#advanced-configuration)
7. [Production Deployment](#production-deployment)
8. [Troubleshooting](#troubleshooting)

## Keycloak Installation

### Docker Installation (Recommended for Testing)

```bash
# Pull the official Keycloak image
docker pull quay.io/keycloak/keycloak:latest

# Run Keycloak with development settings
docker run -p 8080:8080 -e KEYCLOAK_ADMIN=admin -e KEYCLOAK_ADMIN_PASSWORD=admin quay.io/keycloak/keycloak:latest start-dev
```

### Production Installation

For production environments, follow the [official Keycloak installation guide](https://www.keycloak.org/guides#getting-started).

#### System Requirements

- Java 11+ (OpenJDK recommended)
- Database (PostgreSQL, MySQL, or MariaDB recommended)
- 2GB+ RAM
- HTTPS certificate for production

#### Database Setup

```sql
-- PostgreSQL example
CREATE DATABASE keycloak;
CREATE USER keycloak WITH PASSWORD 'strong-password';
GRANT ALL PRIVILEGES ON DATABASE keycloak TO keycloak;
```

## Realm Configuration

### 1. Create a New Realm

1. Access Keycloak Admin Console: `http://localhost:8080/admin/`
2. Log in with admin credentials
3. Click "Add realm" or hover over "Master" and click "Add realm"
4. Name: `projectsend` (or your preferred name)
5. Click "Create"

### 2. Realm Settings

Navigate to **Realm Settings** and configure:

#### General Tab
- **Realm ID**: `projectsend`
- **Display name**: `ProjectSend`
- **HTML Display name**: `<b>ProjectSend</b> Authentication`
- **Frontend URL**: `https://your-keycloak-domain` (for production)

#### Login Tab
- **User registration**: Enabled (if you want self-registration)
- **Forgot password**: Enabled
- **Remember me**: Enabled
- **Verify email**: Enabled (recommended)
- **Login with email**: Enabled

#### Keys Tab
- Ensure RS256 algorithm is active
- Generate new keys if needed for production

#### Tokens Tab
```
Access Token Lifespan: 5 minutes
Access Token Lifespan For Implicit Flow: 15 minutes
Client login timeout: 1 minute
Login timeout: 30 minutes
Login action timeout: 5 minutes
```

### 3. Email Configuration

Configure SMTP settings for email verification:

```
Host: your-smtp-server.com
Port: 587
From: noreply@your-domain.com
Enable StartTLS: On
Enable Authentication: On
Username: your-smtp-username
Password: your-smtp-password
```

## Client Configuration

### 1. Create OIDC Client

1. Navigate to **Clients** → **Create**
2. **Client ID**: `projectsend-client`
3. **Client Protocol**: `openid-connect`
4. **Root URL**: `https://your-projectsend-domain`
5. Click "Save"

### 2. Client Settings

Configure the following settings:

#### Settings Tab
```
Name: ProjectSend Application
Description: ProjectSend file sharing application
Enabled: On
Consent Required: Off
Client Protocol: openid-connect
Access Type: confidential
Standard Flow Enabled: On
Implicit Flow Enabled: Off
Direct Access Grants Enabled: On
Service Accounts Enabled: Off
Authorization Enabled: Off
```

#### Valid Redirect URIs
```
https://your-projectsend-domain/oidc-callback.php
https://your-projectsend-domain/oidc-callback.php/*
```

#### Web Origins
```
https://your-projectsend-domain
```

#### Advanced Settings
```
Proof Key for Code Exchange Code Challenge Method: S256
```

### 3. Client Credentials

1. Go to **Credentials** tab
2. **Client Authenticator**: `Client Id and Secret`
3. Copy the **Secret** - you'll need this for ProjectSend configuration

### 4. Client Scopes

#### Default Client Scopes
Ensure these scopes are assigned:
- `openid` (required)
- `profile` (recommended)
- `email` (recommended)
- `roles` (for role mapping)

#### Optional Client Scopes
Add custom scopes if needed:
- `groups` (for group membership)
- `projectsend-access` (custom scope)

## User Management

### 1. Create Users

#### Manual User Creation
1. Navigate to **Users** → **Add user**
2. Fill in user details:
   ```
   Username: john.doe
   Email: john.doe@company.com
   First Name: John
   Last Name: Doe
   Email Verified: On
   Enabled: On
   ```
3. Click "Save"

#### Set Password
1. Go to **Credentials** tab
2. Set password
3. **Temporary**: Off (for permanent passwords)

### 2. User Attributes

Add custom attributes for ProjectSend integration:

```
projectsend_role: admin|user|client
department: IT
cost_center: 12345
```

### 3. Bulk User Import

Use the Keycloak REST API or import JSON:

```json
{
  "users": [
    {
      "username": "user1",
      "email": "user1@company.com",
      "firstName": "User",
      "lastName": "One",
      "enabled": true,
      "emailVerified": true,
      "credentials": [
        {
          "type": "password",
          "value": "password123",
          "temporary": false
        }
      ],
      "attributes": {
        "projectsend_role": ["user"]
      }
    }
  ]
}
```

## Group and Role Mapping

### 1. Create Groups

1. Navigate to **Groups** → **New**
2. Create groups:
   - `ProjectSend Administrators`
   - `ProjectSend Users`
   - `ProjectSend Clients`

#### Group Attributes
For each group, add attributes:
```
projectsend_role: admin|user|client
access_level: full|limited|read-only
```

### 2. Create Roles

1. Navigate to **Roles** → **Add Role**
2. Create roles:
   - `projectsend-admin`
   - `projectsend-user`
   - `projectsend-client`

### 3. User Assignment

Assign users to groups:
1. Go to **Users** → Select user → **Groups**
2. Click "Join" for appropriate groups

### 4. Client Mappers

Configure attribute mappers for the ProjectSend client:

#### Group Membership Mapper
1. **Client** → `projectsend-client` → **Mappers** → **Add Builtin**
2. Select "groups" mapper
3. Configure:
   ```
   Name: groups
   Mapper Type: Group Membership
   Token Claim Name: groups
   Full group path: Off
   Add to ID token: On
   Add to access token: On
   Add to userinfo: On
   ```

#### User Attribute Mapper
1. **Mappers** → **Create**
2. Configure:
   ```
   Name: projectsend-role
   Mapper Type: User Attribute
   User Attribute: projectsend_role
   Token Claim Name: projectsend_role
   Claim JSON Type: String
   Add to ID token: On
   Add to access token: On
   Add to userinfo: On
   ```

## Advanced Configuration

### 1. Custom Themes

Create custom login themes:

```bash
# Create theme directory
mkdir -p /opt/keycloak/themes/projectsend/login

# Copy base theme
cp -r /opt/keycloak/themes/keycloak/login/* /opt/keycloak/themes/projectsend/login/
```

Update `theme.properties`:
```properties
parent=keycloak
import=common/keycloak
```

### 2. Custom Authentication Flows

Configure custom authentication flows:
1. **Authentication** → **Flows** → **Copy** (Browser flow)
2. Name: `ProjectSend Browser`
3. Customize as needed

### 3. Identity Provider Integration

Connect to external identity providers:
1. **Identity Providers** → **Add provider**
2. Configure SAML, Google, Microsoft, etc.

### 4. Protocol Mappers

Add custom protocol mappers for specific claims:

```
Name: department
Mapper Type: User Attribute
User Attribute: department
Token Claim Name: department
Claim JSON Type: String
```

## Production Deployment

### 1. Database Configuration

Configure production database in `keycloak.conf`:

```properties
# Database
db=postgres
db-username=keycloak
db-password=strong-password
db-url=jdbc:postgresql://localhost/keycloak

# Hostname
hostname=auth.your-domain.com
hostname-strict=false
hostname-strict-https=true

# HTTP/TLS
http-enabled=false
https-certificate-file=/path/to/cert.pem
https-certificate-key-file=/path/to/key.pem
```

### 2. Clustering

For high availability:

```properties
# Clustering
cache=ispn
cache-stack=kubernetes
```

### 3. Performance Tuning

JVM settings for production:

```bash
export JAVA_OPTS="-Xms2g -Xmx4g -XX:MetaspaceSize=96M -XX:MaxMetaspaceSize=256m"
```

### 4. Security Headers

Configure security headers:

```properties
# Security
spi-login-protocol-openid-connect-legacy-logout-redirect-uri=true
spi-x509cert-lookup-provider=default
```

### 5. Monitoring

Enable metrics:

```properties
# Metrics
metrics-enabled=true
```

## Integration with ProjectSend

### 1. ProjectSend Configuration

Add to `config.php`:

```php
// OIDC Configuration
$oidc_enabled = true;
$oidc_provider_url = 'https://your-keycloak-domain/realms/projectsend';
$oidc_client_id = 'projectsend-client';
$oidc_client_secret = 'your-client-secret';
$oidc_redirect_uri = 'https://your-projectsend-domain/oidc-callback.php';
$oidc_scopes = 'openid profile email groups';

// User mapping
$oidc_user_mapping = array(
    'username' => 'preferred_username',
    'email' => 'email',
    'name' => 'name',
    'groups' => 'groups',
    'role' => 'projectsend_role'
);

// Group to role mapping
$oidc_group_role_mapping = array(
    'ProjectSend Administrators' => 'a', // admin
    'ProjectSend Users' => 'u',          // user
    'ProjectSend Clients' => 'c'         // client
);
```

### 2. Testing the Integration

1. Navigate to ProjectSend login page
2. Click "Login with SSO"
3. Authenticate with Keycloak
4. Verify user is created/updated in ProjectSend
5. Check role assignment

## Troubleshooting

### Common Issues

#### 1. Invalid Redirect URI
**Error**: `Invalid parameter: redirect_uri`

**Solution**: Ensure redirect URI in Keycloak matches exactly:
```
https://your-projectsend-domain/oidc-callback.php
```

#### 2. Token Validation Failed
**Error**: Token signature validation failed

**Solution**: 
- Check system clocks are synchronized
- Verify JWKS endpoint is accessible
- Check token algorithm configuration

#### 3. User Not Found
**Error**: User cannot be found after authentication

**Solution**:
- Check user attribute mapping configuration
- Verify group membership mapping
- Check user provisioning settings

#### 4. SSL/TLS Issues
**Error**: SSL certificate validation failed

**Solution**:
- Use proper SSL certificates
- Configure certificate trust chain
- For testing, disable SSL verification (not recommended for production)

### Debug Settings

Enable debug logging in Keycloak:

1. **Server Info** → **Providers** → **Logger**
2. Set log level to `DEBUG` for:
   - `org.keycloak.protocol.oidc`
   - `org.keycloak.authentication`
   - `org.keycloak.events`

### Useful Endpoints

- **Discovery**: `https://your-keycloak-domain/realms/projectsend/.well-known/openid_configuration`
- **Authorization**: `https://your-keycloak-domain/realms/projectsend/protocol/openid-connect/auth`
- **Token**: `https://your-keycloak-domain/realms/projectsend/protocol/openid-connect/token`
- **UserInfo**: `https://your-keycloak-domain/realms/projectsend/protocol/openid-connect/userinfo`
- **JWKS**: `https://your-keycloak-domain/realms/projectsend/protocol/openid-connect/certs`

## Security Best Practices

### 1. Client Security
- Use confidential clients for server-side applications
- Enable PKCE for additional security
- Regularly rotate client secrets

### 2. Token Security
- Use short-lived access tokens (5-15 minutes)
- Implement proper token refresh logic
- Store tokens securely

### 3. Network Security
- Always use HTTPS in production
- Implement proper firewall rules
- Use private networks when possible

### 4. User Security
- Enforce strong password policies
- Enable email verification
- Implement account lockout policies

### 5. Monitoring
- Enable audit logging
- Monitor authentication events
- Set up alerts for suspicious activity

---

For additional support and advanced configurations, refer to the [official Keycloak documentation](https://www.keycloak.org/documentation) and the [ProjectSend OIDC Integration Guide](OIDC_INTEGRATION_GUIDE.md).