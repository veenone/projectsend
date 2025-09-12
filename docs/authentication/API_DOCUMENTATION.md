# ProjectSend OIDC API Documentation

## Overview

This document provides comprehensive API documentation for ProjectSend's OpenID Connect (OIDC) authentication system. These APIs enable integration with external systems, custom authentication workflows, and programmatic management of OIDC functionality.

## Table of Contents

1. [Authentication Endpoints](#authentication-endpoints)
2. [Configuration Management](#configuration-management)
3. [User Management](#user-management)
4. [Token Management](#token-management)
5. [Audit and Monitoring](#audit-and-monitoring)
6. [Error Handling](#error-handling)
7. [SDK and Client Libraries](#sdk-and-client-libraries)
8. [Integration Examples](#integration-examples)

## Authentication Endpoints

### Initiate OIDC Login

Redirects the user to the OIDC provider for authentication.

```http
GET /api/auth/oidc/login
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| provider | string | No | OIDC provider name (default: primary) |
| redirect_url | string | No | URL to redirect after successful auth |
| state | string | No | Custom state parameter |

**Example Request:**
```bash
curl -X GET "https://projectsend.example.com/api/auth/oidc/login?provider=keycloak&redirect_url=https://app.example.com/dashboard"
```

**Response:**
```json
{
  "success": true,
  "authorization_url": "https://keycloak.example.com/realms/projectsend/protocol/openid-connect/auth?client_id=...",
  "state": "abc123...",
  "nonce": "xyz789..."
}
```

### Handle OIDC Callback

Processes the authorization code received from the OIDC provider.

```http
POST /api/auth/oidc/callback
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| code | string | Yes | Authorization code from provider |
| state | string | Yes | State parameter for CSRF protection |

**Example Request:**
```bash
curl -X POST "https://projectsend.example.com/api/auth/oidc/callback" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "authorization-code-from-provider",
    "state": "abc123..."
  }'
```

**Success Response:**
```json
{
  "success": true,
  "user": {
    "id": 123,
    "username": "john.doe",
    "email": "john.doe@example.com",
    "name": "John Doe",
    "role": "u",
    "oidc_subject": "user-12345",
    "created": "2024-01-15T10:30:00Z",
    "last_login": "2024-01-15T10:30:00Z"
  },
  "session": {
    "token": "session-token-12345",
    "expires_at": "2024-01-15T11:30:00Z"
  }
}
```

### Logout

Terminates the user session and optionally performs OIDC provider logout.

```http
POST /api/auth/oidc/logout
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id_token | string | No | ID token for provider logout |
| post_logout_redirect_uri | string | No | Where to redirect after logout |

**Example Request:**
```bash
curl -X POST "https://projectsend.example.com/api/auth/oidc/logout" \
  -H "Authorization: Bearer session-token-12345" \
  -H "Content-Type: application/json" \
  -d '{
    "id_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "post_logout_redirect_uri": "https://app.example.com/goodbye"
  }'
```

**Response:**
```json
{
  "success": true,
  "logout_url": "https://keycloak.example.com/realms/projectsend/protocol/openid-connect/logout?post_logout_redirect_uri=...",
  "message": "Logout successful"
}
```

### Token Refresh

Refreshes access tokens using a refresh token.

```http
POST /api/auth/oidc/refresh
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| refresh_token | string | Yes | Valid refresh token |

**Example Request:**
```bash
curl -X POST "https://projectsend.example.com/api/auth/oidc/refresh" \
  -H "Content-Type: application/json" \
  -d '{
    "refresh_token": "refresh-token-12345"
  }'
```

**Response:**
```json
{
  "success": true,
  "access_token": "new-access-token",
  "refresh_token": "new-refresh-token",
  "expires_in": 3600,
  "token_type": "Bearer"
}
```

## Configuration Management

### Get OIDC Configuration

Retrieves the current OIDC configuration (public information only).

```http
GET /api/admin/oidc/config
```

**Authorization:** Admin role required

**Example Request:**
```bash
curl -X GET "https://projectsend.example.com/api/admin/oidc/config" \
  -H "Authorization: Bearer admin-token"
```

**Response:**
```json
{
  "success": true,
  "config": {
    "oidc_enabled": true,
    "provider_url": "https://keycloak.example.com/realms/projectsend",
    "client_id": "projectsend-client",
    "scopes": ["openid", "profile", "email", "groups"],
    "auto_provisioning": true,
    "default_role": "c",
    "providers": [
      {
        "name": "keycloak",
        "display_name": "Corporate Login",
        "enabled": true
      }
    ]
  }
}
```

### Update OIDC Configuration

Updates the OIDC configuration settings.

```http
PUT /api/admin/oidc/config
```

**Authorization:** Admin role required

**Example Request:**
```bash
curl -X PUT "https://projectsend.example.com/api/admin/oidc/config" \
  -H "Authorization: Bearer admin-token" \
  -H "Content-Type: application/json" \
  -d '{
    "oidc_enabled": true,
    "auto_provisioning": true,
    "default_role": "c",
    "group_role_mapping": {
      "ProjectSend Administrators": "a",
      "ProjectSend Users": "u",
      "ProjectSend Clients": "c"
    }
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Configuration updated successfully",
  "config": {
    "oidc_enabled": true,
    "auto_provisioning": true,
    "default_role": "c"
  }
}
```

### Test OIDC Connection

Tests connectivity to the configured OIDC provider.

```http
POST /api/admin/oidc/test-connection
```

**Authorization:** Admin role required

**Example Request:**
```bash
curl -X POST "https://projectsend.example.com/api/admin/oidc/test-connection" \
  -H "Authorization: Bearer admin-token"
```

**Response:**
```json
{
  "success": true,
  "tests": [
    {
      "name": "Discovery Endpoint",
      "status": "passed",
      "response_time": 245,
      "details": "Successfully retrieved OpenID Connect configuration"
    },
    {
      "name": "JWKS Endpoint",
      "status": "passed",
      "response_time": 123,
      "details": "Successfully retrieved signing keys"
    },
    {
      "name": "Client Configuration",
      "status": "passed",
      "details": "Client credentials are valid"
    }
  ],
  "overall_status": "healthy"
}
```

## User Management

### List OIDC Users

Retrieves a list of users who have authenticated via OIDC.

```http
GET /api/admin/oidc/users
```

**Authorization:** Admin role required

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| page | integer | Page number (default: 1) |
| limit | integer | Items per page (default: 50) |
| search | string | Search by username or email |
| provider | string | Filter by OIDC provider |
| role | string | Filter by user role |

**Example Request:**
```bash
curl -X GET "https://projectsend.example.com/api/admin/oidc/users?page=1&limit=25&role=u" \
  -H "Authorization: Bearer admin-token"
```

**Response:**
```json
{
  "success": true,
  "users": [
    {
      "id": 123,
      "username": "john.doe",
      "email": "john.doe@example.com",
      "name": "John Doe",
      "role": "u",
      "oidc_subject": "user-12345",
      "oidc_provider": "keycloak",
      "created": "2024-01-15T10:30:00Z",
      "last_oidc_login": "2024-01-15T15:45:00Z",
      "login_count": 15
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 25,
    "total": 150,
    "total_pages": 6
  }
}
```

### Get User Details

Retrieves detailed information about a specific OIDC user.

```http
GET /api/admin/oidc/users/{userId}
```

**Authorization:** Admin role required

**Example Request:**
```bash
curl -X GET "https://projectsend.example.com/api/admin/oidc/users/123" \
  -H "Authorization: Bearer admin-token"
```

**Response:**
```json
{
  "success": true,
  "user": {
    "id": 123,
    "username": "john.doe",
    "email": "john.doe@example.com",
    "name": "John Doe",
    "role": "u",
    "oidc_subject": "user-12345",
    "oidc_provider": "keycloak",
    "groups": ["ProjectSend Users", "IT Department"],
    "created": "2024-01-15T10:30:00Z",
    "last_oidc_login": "2024-01-15T15:45:00Z",
    "login_count": 15,
    "attributes": {
      "department": "IT",
      "employee_id": "E12345"
    }
  },
  "login_history": [
    {
      "timestamp": "2024-01-15T15:45:00Z",
      "ip_address": "192.168.1.100",
      "user_agent": "Mozilla/5.0...",
      "success": true
    }
  ]
}
```

### Update User Role

Updates a user's role assignment.

```http
PUT /api/admin/oidc/users/{userId}/role
```

**Authorization:** Admin role required

**Example Request:**
```bash
curl -X PUT "https://projectsend.example.com/api/admin/oidc/users/123/role" \
  -H "Authorization: Bearer admin-token" \
  -H "Content-Type: application/json" \
  -d '{
    "role": "a",
    "reason": "Promoted to administrator"
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "User role updated successfully",
  "user": {
    "id": 123,
    "username": "john.doe",
    "role": "a",
    "updated": "2024-01-15T16:00:00Z"
  }
}
```

### Sync User from Provider

Synchronizes a user's information from the OIDC provider.

```http
POST /api/admin/oidc/users/{userId}/sync
```

**Authorization:** Admin role required

**Example Request:**
```bash
curl -X POST "https://projectsend.example.com/api/admin/oidc/users/123/sync" \
  -H "Authorization: Bearer admin-token"
```

**Response:**
```json
{
  "success": true,
  "message": "User synchronized successfully",
  "changes": [
    {
      "field": "name",
      "old_value": "John Doe",
      "new_value": "John Smith"
    },
    {
      "field": "groups",
      "old_value": ["ProjectSend Users"],
      "new_value": ["ProjectSend Users", "ProjectSend Administrators"]
    }
  ]
}
```

## Token Management

### Validate Token

Validates an access token or ID token.

```http
POST /api/auth/oidc/validate-token
```

**Example Request:**
```bash
curl -X POST "https://projectsend.example.com/api/auth/oidc/validate-token" \
  -H "Content-Type: application/json" \
  -d '{
    "token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "access_token"
  }'
```

**Response:**
```json
{
  "success": true,
  "valid": true,
  "claims": {
    "sub": "user-12345",
    "preferred_username": "john.doe",
    "email": "john.doe@example.com",
    "groups": ["ProjectSend Users"],
    "exp": 1705308000,
    "iat": 1705304400
  },
  "expires_in": 3600
}
```

### Revoke Token

Revokes an access token or refresh token.

```http
POST /api/auth/oidc/revoke-token
```

**Example Request:**
```bash
curl -X POST "https://projectsend.example.com/api/auth/oidc/revoke-token" \
  -H "Authorization: Bearer session-token" \
  -H "Content-Type: application/json" \
  -d '{
    "token": "token-to-revoke",
    "token_type_hint": "refresh_token"
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Token revoked successfully"
}
```

## Audit and Monitoring

### Get Authentication Metrics

Retrieves authentication metrics and statistics.

```http
GET /api/admin/oidc/metrics
```

**Authorization:** Admin role required

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| period | string | Time period (day, week, month) |
| start_date | string | Start date (YYYY-MM-DD) |
| end_date | string | End date (YYYY-MM-DD) |

**Example Request:**
```bash
curl -X GET "https://projectsend.example.com/api/admin/oidc/metrics?period=week" \
  -H "Authorization: Bearer admin-token"
```

**Response:**
```json
{
  "success": true,
  "period": "week",
  "metrics": {
    "total_logins": 1250,
    "successful_logins": 1200,
    "failed_logins": 50,
    "success_rate": 96.0,
    "unique_users": 85,
    "new_users": 5,
    "avg_response_time": 1.2,
    "provider_breakdown": {
      "keycloak": 1100,
      "azure": 150
    },
    "daily_stats": [
      {
        "date": "2024-01-15",
        "logins": 180,
        "unique_users": 25
      }
    ]
  }
}
```

### Get Audit Logs

Retrieves OIDC authentication audit logs.

```http
GET /api/admin/oidc/audit-logs
```

**Authorization:** Admin role required

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| page | integer | Page number |
| limit | integer | Items per page |
| event_type | string | Filter by event type |
| user_id | integer | Filter by user ID |
| start_date | string | Start date |
| end_date | string | End date |

**Example Request:**
```bash
curl -X GET "https://projectsend.example.com/api/admin/oidc/audit-logs?event_type=authentication_failure&limit=25" \
  -H "Authorization: Bearer admin-token"
```

**Response:**
```json
{
  "success": true,
  "logs": [
    {
      "id": 1001,
      "event_type": "authentication_failure",
      "user_identifier": "john.doe@example.com",
      "ip_address": "192.168.1.100",
      "user_agent": "Mozilla/5.0...",
      "timestamp": "2024-01-15T10:30:00Z",
      "details": {
        "error": "invalid_credentials",
        "provider": "keycloak"
      }
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 25,
    "total": 150
  }
}
```

## Error Handling

### Standard Error Response Format

All API endpoints return errors in a consistent format:

```json
{
  "success": false,
  "error": "error_code",
  "message": "Human-readable error message",
  "details": {
    "field": "Additional error details"
  },
  "request_id": "req_12345"
}
```

### Common Error Codes

| Error Code | HTTP Status | Description |
|------------|-------------|-------------|
| `invalid_request` | 400 | Malformed request |
| `unauthorized` | 401 | Invalid or missing authentication |
| `forbidden` | 403 | Insufficient permissions |
| `not_found` | 404 | Resource not found |
| `conflict` | 409 | Resource conflict |
| `validation_error` | 422 | Request validation failed |
| `rate_limit_exceeded` | 429 | Too many requests |
| `internal_error` | 500 | Internal server error |
| `service_unavailable` | 503 | OIDC provider unavailable |

### OIDC-Specific Error Codes

| Error Code | Description |
|------------|-------------|
| `oidc_disabled` | OIDC authentication is disabled |
| `invalid_provider` | Unknown OIDC provider |
| `discovery_failed` | Cannot retrieve provider configuration |
| `invalid_state` | State parameter validation failed |
| `invalid_nonce` | Nonce validation failed |
| `token_expired` | Token has expired |
| `token_invalid` | Token validation failed |
| `user_provisioning_failed` | Cannot create or update user |
| `insufficient_claims` | Required claims missing from token |

## SDK and Client Libraries

### JavaScript/Node.js

```javascript
const ProjectSendOIDC = require('@projectsend/oidc-client');

const client = new ProjectSendOIDC({
  baseUrl: 'https://projectsend.example.com',
  apiKey: 'your-api-key'
});

// Initiate OIDC login
const loginResponse = await client.auth.initiateOIDCLogin({
  provider: 'keycloak',
  redirectUrl: 'https://app.example.com/dashboard'
});

// Handle callback
const callbackResponse = await client.auth.handleOIDCCallback({
  code: authorizationCode,
  state: stateParameter
});
```

### PHP

```php
use ProjectSend\OIDC\Client;

$client = new Client([
    'base_url' => 'https://projectsend.example.com',
    'api_key' => 'your-api-key'
]);

// Initiate OIDC login
$loginResponse = $client->auth()->initiateOIDCLogin([
    'provider' => 'keycloak',
    'redirect_url' => 'https://app.example.com/dashboard'
]);

// Handle callback
$callbackResponse = $client->auth()->handleOIDCCallback([
    'code' => $authorizationCode,
    'state' => $stateParameter
]);
```

### Python

```python
from projectsend_oidc import Client

client = Client(
    base_url='https://projectsend.example.com',
    api_key='your-api-key'
)

# Initiate OIDC login
login_response = client.auth.initiate_oidc_login(
    provider='keycloak',
    redirect_url='https://app.example.com/dashboard'
)

# Handle callback
callback_response = client.auth.handle_oidc_callback(
    code=authorization_code,
    state=state_parameter
)
```

## Integration Examples

### Single-Page Application (SPA)

```javascript
// Initialize OIDC client
const oidcClient = new OIDCClient({
  baseUrl: 'https://projectsend.example.com'
});

// Login function
async function login() {
  try {
    const response = await oidcClient.initiateLogin({
      provider: 'keycloak'
    });
    
    // Redirect to OIDC provider
    window.location.href = response.authorization_url;
  } catch (error) {
    console.error('Login failed:', error);
  }
}

// Handle callback (in callback page)
async function handleCallback() {
  const urlParams = new URLSearchParams(window.location.search);
  const code = urlParams.get('code');
  const state = urlParams.get('state');
  
  try {
    const response = await oidcClient.handleCallback({ code, state });
    
    // Store session token
    localStorage.setItem('session_token', response.session.token);
    
    // Redirect to application
    window.location.href = '/dashboard';
  } catch (error) {
    console.error('Callback handling failed:', error);
  }
}
```

### Mobile Application

```javascript
// React Native example
import { OIDCClient } from '@projectsend/react-native-oidc';

const oidcClient = new OIDCClient({
  baseUrl: 'https://projectsend.example.com'
});

// Login with native browser
async function loginWithOIDC() {
  try {
    const result = await oidcClient.authorize({
      provider: 'keycloak',
      additionalParameters: {},
      customHeaders: {}
    });
    
    // Handle successful authentication
    const userSession = await oidcClient.handleCallback(result);
    
    // Store session
    await AsyncStorage.setItem('user_session', JSON.stringify(userSession));
    
    return userSession;
  } catch (error) {
    console.error('OIDC login failed:', error);
    throw error;
  }
}
```

### Webhook Integration

```javascript
// Express.js webhook handler
app.post('/webhooks/oidc-events', (req, res) => {
  const { event_type, user_data, timestamp } = req.body;
  
  switch (event_type) {
    case 'user.login':
      console.log(`User ${user_data.username} logged in at ${timestamp}`);
      // Update user activity
      updateUserActivity(user_data.id, timestamp);
      break;
      
    case 'user.provisioned':
      console.log(`New user provisioned: ${user_data.username}`);
      // Send welcome notification
      sendWelcomeNotification(user_data);
      break;
      
    case 'user.role_changed':
      console.log(`User ${user_data.username} role changed to ${user_data.role}`);
      // Update external systems
      syncUserRoleToExternalSystems(user_data);
      break;
  }
  
  res.status(200).json({ success: true });
});
```

## Rate Limiting

All API endpoints are subject to rate limiting:

- **Authentication endpoints**: 10 requests per minute per IP
- **Admin endpoints**: 100 requests per minute per user
- **Public endpoints**: 30 requests per minute per IP

Rate limit headers are included in responses:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1705308000
```

## API Versioning

The API uses URL-based versioning:

- Current version: `v1`
- Base URL: `https://projectsend.example.com/api/v1/`

Version-specific changes are documented in the [API Changelog](API_CHANGELOG.md).

---

For additional support and examples, refer to the [ProjectSend Developer Portal](https://developers.projectsend.org) or contact the development team at api-support@projectsend.org.