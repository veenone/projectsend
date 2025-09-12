# ProjectSend Keycloak Integration

## Prerequisites
- Keycloak 18+ 
- PHP 7.4+
- Composer dependencies installed

## Configuration Steps

1. **Keycloak Realm Setup**
   - Create a new realm for ProjectSend
   - Configure realm settings for security

2. **Client Configuration**
   - Create a new client in your ProjectSend realm
   - Set Client Protocol to "openid-connect"
   - Configure Valid Redirect URIs
   - Set Access Type to "confidential"

3. **Edit Configuration File**
   Update `config/keycloak/config.php` with your settings:

   ```php
   return [
       'keycloak_base_url' => 'https://your-keycloak-server.com/auth',
       'realm' => 'your-realm-name',
       'client_id' => 'your-client-id',
       'client_secret' => 'your-client-secret',
       // Other settings...
   ];
   ```

4. **Role Mapping**
   Configure role mappings in the configuration file to match Keycloak roles with ProjectSend roles.

5. **Security Recommendations**
   - Use strong, unique client secrets
   - Enable HTTPS
   - Configure token lifetimes
   - Use multi-factor authentication

## Troubleshooting
- Verify Keycloak server connectivity
- Check network firewall rules
- Validate SSL/TLS certificates
- Review Keycloak server logs

## Support
For issues, please file a GitHub issue with detailed logs and configuration.