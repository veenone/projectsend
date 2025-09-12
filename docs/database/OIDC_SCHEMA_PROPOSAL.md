# OIDC Database Schema Modifications

## Proposed Schema Changes

### 1. Extended User Table Modifications
```sql
ALTER TABLE `tbl_users` 
ADD COLUMN `oidc_provider_id` VARCHAR(255) NULL COMMENT 'Unique identifier from OIDC provider',
ADD COLUMN `oidc_provider_name` VARCHAR(100) NULL COMMENT 'Name of OIDC identity provider',
ADD COLUMN `oidc_last_sync` DATETIME NULL COMMENT 'Timestamp of last identity synchronization',
ADD INDEX `idx_oidc_provider` (`oidc_provider_id`, `oidc_provider_name`);
```

### 2. OIDC Provider Configuration Table
```sql
CREATE TABLE `tbl_oidc_providers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `provider_name` VARCHAR(100) NOT NULL,
  `issuer_url` VARCHAR(255) NOT NULL,
  `client_id` VARCHAR(255) NOT NULL,
  `client_secret_encrypted` TEXT NOT NULL,
  `authorization_endpoint` VARCHAR(255) NOT NULL,
  `token_endpoint` VARCHAR(255) NOT NULL,
  `userinfo_endpoint` VARCHAR(255) NOT NULL,
  `jwks_uri` VARCHAR(255) NOT NULL,
  `scopes` TEXT NOT NULL,
  `enabled` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unq_provider_name` (`provider_name`)
) COMMENT 'Stores OIDC Provider Configuration';
```

### 3. OIDC Authentication Logs
```sql
CREATE TABLE `tbl_oidc_auth_logs` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `provider_name` VARCHAR(100) NOT NULL,
  `auth_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('success', 'failed', 'denied') NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` TEXT NULL,
  `error_message` TEXT NULL,
  INDEX `idx_auth_logs` (`user_id`, `provider_name`, `auth_timestamp`)
) COMMENT 'Logs OIDC Authentication Attempts';
```

## Migration Considerations
- Existing users should be unaffected
- Optional OIDC integration preserves current authentication methods
- Encryption of sensitive data using existing ProjectSend mechanisms

## Backwards Compatibility
- All new columns are NULLABLE
- No existing authentication flows are disrupted
- Gradual migration path for existing users

## Security Notes
- Client secrets will be encrypted at rest
- Minimal additional surface area for potential attacks
- Maintains ProjectSend's existing security model

## Recommended Implementation Phases
1. Schema Migration
2. Configuration Management
3. Provider Setup
4. User Mapping Strategy
5. Testing and Validation