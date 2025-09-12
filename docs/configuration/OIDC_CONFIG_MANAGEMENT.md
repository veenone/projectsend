# OIDC Configuration Management Strategy

## Overview
This document outlines the proposed configuration management approach for OpenID Connect (OIDC) integration in ProjectSend.

## Configuration Storage
- Utilize existing `tbl_options` for encrypted configuration storage
- Leverage ProjectSend's current encryption mechanisms
- Support multiple OIDC provider configurations

## Configuration Keys
```php
[
    'oidc_providers' => [
        // Array of provider configurations
        [
            'provider_name' => 'Google',
            'client_id' => '...',
            'client_secret' => '...',  // Encrypted
            'issuer' => 'https://accounts.google.com',
            'scopes' => ['openid', 'profile', 'email'],
            'enabled' => true
        ]
    ],
    'oidc_global_settings' => [
        'auto_create_users' => true,
        'default_user_role' => 'client',
        'allowed_domains' => ['example.com', 'company.org']
    ]
]
```

## Configuration Management Principles
1. Encryption at Rest
   - Use ProjectSend's existing encryption utilities
   - Never store raw secrets in configuration

2. Configuration Validation
   - Implement strict schema validation
   - Validate provider metadata
   - Sanitize and normalize configuration inputs

3. Provider Management
   - Support multiple concurrent OIDC providers
   - Enable/disable individual providers
   - Flexible scoping and mapping

## Recommended Implementation
```php
class OIDCConfigurationManager {
    private const CONFIG_KEY = 'oidc_authentication_settings';

    public function saveProviderConfiguration(array $providerConfig): bool {
        // Validate configuration
        // Encrypt sensitive data
        // Save to tbl_options
    }

    public function getProviderConfigurations(): array {
        // Retrieve and decrypt configurations
    }

    public function validateProviderConfiguration(array $config): bool {
        // Comprehensive configuration validation
    }
}
```

## Security Considerations
- Encrypt all sensitive configuration data
- Implement strict input validation
- Provide clear configuration guidelines
- Support gradual, backward-compatible rollout

## Next Steps
1. Implement configuration management class
2. Create configuration validation mechanisms
3. Develop user interface for OIDC provider setup
4. Create migration path for existing users

## Compliance
- Follow OAuth 2.0 and OpenID Connect specifications
- Maintain alignment with existing ProjectSend security model