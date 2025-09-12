<?php
/**
 * OIDC/Keycloak Configuration Form
 * Frontend interface for OpenID Connect authentication settings
 */

// Add OIDC-specific assets
add_asset('css', BASE_URI . 'assets/src/css/oidc-styles.css');
add_asset('js', BASE_URI . 'assets/src/js/pages/oidc-config.js', 'foot');
?>

<h3><i class="fa fa-key"></i> <?php _e('OpenID Connect Authentication', 'cftp_admin'); ?></h3>
<p><?php _e('Configure OpenID Connect authentication with Keycloak or other compatible providers. This allows users to login using their organization credentials.', 'cftp_admin'); ?></p>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="oidc_enabled">
            <input type="checkbox" value="1" name="oidc_enabled" id="oidc_enabled" class="checkbox_options" <?php echo (get_option('oidc_enabled') == 1) ? 'checked="checked"' : ''; ?> /> 
            <?php _e('Enable OpenID Connect authentication', 'cftp_admin'); ?>
        </label>
        <p class="field_note form-text"><?php _e('Allow users to login using OpenID Connect providers like Keycloak', 'cftp_admin'); ?></p>
    </div>
</div>

<div id="oidc_settings_container" style="<?php echo (get_option('oidc_enabled') != 1) ? 'display: none;' : ''; ?>">

    <div class="options_divide"></div>

    <h4><?php _e('Provider Settings', 'cftp_admin'); ?></h4>
    
    <div class="form-group row">
        <label for="oidc_provider_type" class="col-sm-4 control-label"><?php _e('Provider Type', 'cftp_admin'); ?></label>
        <div class="col-sm-8">
            <select class="form-select" name="oidc_provider_type" id="oidc_provider_type">
                <option value="keycloak" <?php echo (get_option('oidc_provider_type') == 'keycloak') ? 'selected="selected"' : ''; ?>>Keycloak</option>
                <option value="generic" <?php echo (get_option('oidc_provider_type') == 'generic') ? 'selected="selected"' : ''; ?>><?php _e('Generic OpenID Connect', 'cftp_admin'); ?></option>
            </select>
            <p class="field_note form-text"><?php _e('Select your OpenID Connect provider type. Keycloak provides additional features.', 'cftp_admin'); ?></p>
        </div>
    </div>

    <div class="form-group row">
        <label for="oidc_server_url" class="col-sm-4 control-label"><?php _e('Server URL', 'cftp_admin'); ?> <span class="required">*</span></label>
        <div class="col-sm-8">
            <input type="url" name="oidc_server_url" id="oidc_server_url" class="form-control" 
                   value="<?php echo html_output(get_option('oidc_server_url')); ?>" 
                   placeholder="https://keycloak.example.com" />
            <p class="field_note form-text"><?php _e('Base URL of your Keycloak/OIDC server (without /auth)', 'cftp_admin'); ?></p>
            <div class="connection-status mt-2" id="connection_status" style="display: none;"></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="oidc_realm" class="col-sm-4 control-label"><?php _e('Realm/Issuer', 'cftp_admin'); ?> <span class="required">*</span></label>
        <div class="col-sm-8">
            <div class="input-group">
                <input type="text" name="oidc_realm" id="oidc_realm" class="form-control" 
                       value="<?php echo html_output(get_option('oidc_realm')); ?>" 
                       placeholder="master" />
                <button type="button" class="btn btn-outline-secondary" id="test_connection_btn">
                    <i class="fa fa-link"></i> <?php _e('Test Connection', 'cftp_admin'); ?>
                </button>
            </div>
            <p class="field_note form-text"><?php _e('Keycloak realm name or issuer identifier', 'cftp_admin'); ?></p>
        </div>
    </div>

    <div class="options_divide"></div>

    <h4><?php _e('Client Configuration', 'cftp_admin'); ?></h4>

    <div class="form-group row">
        <label for="oidc_client_id" class="col-sm-4 control-label"><?php _e('Client ID', 'cftp_admin'); ?> <span class="required">*</span></label>
        <div class="col-sm-8">
            <input type="text" name="oidc_client_id" id="oidc_client_id" class="form-control" 
                   value="<?php echo html_output(get_option('oidc_client_id')); ?>" 
                   placeholder="projectsend-client" />
            <p class="field_note form-text"><?php _e('Client ID configured in your OIDC provider', 'cftp_admin'); ?></p>
        </div>
    </div>

    <div class="form-group row">
        <label for="oidc_client_secret" class="col-sm-4 control-label"><?php _e('Client Secret', 'cftp_admin'); ?> <span class="required">*</span></label>
        <div class="col-sm-8">
            <div class="input-group">
                <input type="password" name="oidc_client_secret" id="oidc_client_secret" class="form-control" 
                       value="<?php echo html_output(get_option('oidc_client_secret')); ?>" 
                       placeholder="••••••••••••••••" />
                <button type="button" class="btn btn-outline-secondary" id="toggle_secret_visibility">
                    <i class="fa fa-eye"></i>
                </button>
            </div>
            <p class="field_note form-text"><?php _e('Client secret from your OIDC provider configuration', 'cftp_admin'); ?></p>
        </div>
    </div>

    <div class="form-group row">
        <label for="oidc_redirect_uri" class="col-sm-4 control-label"><?php _e('Redirect URI', 'cftp_admin'); ?></label>
        <div class="col-sm-8">
            <input type="url" name="oidc_redirect_uri" id="oidc_redirect_uri" class="form-control" 
                   value="<?php echo html_output(get_option('oidc_redirect_uri') ?: BASE_URI . 'login-callback.php'); ?>" />
            <p class="field_note form-text">
                <?php _e('Redirect URI to configure in your OIDC client. Copy this value to your provider configuration.', 'cftp_admin'); ?>
                <i class="fa fa-copy copy_text ms-2" data-target="oidc_redirect_uri" title="<?php _e('Copy to clipboard', 'cftp_admin'); ?>"></i>
            </p>
        </div>
    </div>

    <div class="form-group row">
        <div class="col-sm-8 offset-sm-4">
            <button type="button" class="btn btn-outline-primary" id="test_credentials_btn">
                <i class="fa fa-check-circle"></i> <?php _e('Test Credentials', 'cftp_admin'); ?>
            </button>
            <div class="credential-status mt-2" id="credential_status" style="display: none;"></div>
        </div>
    </div>

    <div class="options_divide"></div>

    <h4><?php _e('User Management', 'cftp_admin'); ?></h4>

    <div class="form-group row">
        <div class="col-sm-8 offset-sm-4">
            <label for="oidc_auto_create_users">
                <input type="checkbox" value="1" name="oidc_auto_create_users" id="oidc_auto_create_users" class="checkbox_options" 
                       <?php echo (get_option('oidc_auto_create_users') == 1) ? 'checked="checked"' : ''; ?> /> 
                <?php _e('Automatically create local users for new OIDC logins', 'cftp_admin'); ?>
            </label>
            <p class="field_note form-text"><?php _e('When enabled, users logging in via OIDC who don\'t have local accounts will have accounts created automatically', 'cftp_admin'); ?></p>
        </div>
    </div>

    <div class="form-group row">
        <label for="oidc_default_role" class="col-sm-4 control-label"><?php _e('Default Role for New Users', 'cftp_admin'); ?></label>
        <div class="col-sm-8">
            <select class="form-select" name="oidc_default_role" id="oidc_default_role">
                <option value="0" <?php echo (get_option('oidc_default_role') == '0') ? 'selected="selected"' : ''; ?>><?php _e('Client', 'cftp_admin'); ?></option>
                <option value="7" <?php echo (get_option('oidc_default_role') == '7') ? 'selected="selected"' : ''; ?>><?php _e('User', 'cftp_admin'); ?></option>
                <option value="8" <?php echo (get_option('oidc_default_role') == '8') ? 'selected="selected"' : ''; ?>><?php _e('Admin', 'cftp_admin'); ?></option>
            </select>
            <p class="field_note form-text"><?php _e('Default role assigned to automatically created users', 'cftp_admin'); ?></p>
        </div>
    </div>

    <div class="keycloak_features" style="<?php echo (get_option('oidc_provider_type') != 'keycloak') ? 'display: none;' : ''; ?>">
        <div class="options_divide"></div>
        
        <h4><i class="fa fa-shield-alt"></i> <?php _e('Keycloak Advanced Features', 'cftp_admin'); ?></h4>

        <div class="form-group row">
            <div class="col-sm-8 offset-sm-4">
                <label for="oidc_group_sync_enabled">
                    <input type="checkbox" value="1" name="oidc_group_sync_enabled" id="oidc_group_sync_enabled" class="checkbox_options" 
                           <?php echo (get_option('oidc_group_sync_enabled') == 1) ? 'checked="checked"' : ''; ?> /> 
                    <?php _e('Enable Keycloak group and role synchronization', 'cftp_admin'); ?>
                </label>
                <p class="field_note form-text"><?php _e('Sync user roles based on Keycloak groups and roles', 'cftp_admin'); ?></p>
            </div>
        </div>

        <div id="group_sync_settings" style="<?php echo (get_option('oidc_group_sync_enabled') != 1) ? 'display: none;' : ''; ?>">
            <div class="form-group row">
                <label for="oidc_role_mapping" class="col-sm-4 control-label"><?php _e('Role Mapping', 'cftp_admin'); ?></label>
                <div class="col-sm-8">
                    <textarea name="oidc_role_mapping" id="oidc_role_mapping" class="form-control" rows="4" 
                              placeholder='{"admin": "8", "user": "7", "client": "0"}'><?php echo html_output(get_option('oidc_role_mapping') ?: '{}'); ?></textarea>
                    <p class="field_note form-text"><?php _e('JSON mapping of Keycloak roles to ProjectSend user levels', 'cftp_admin'); ?></p>
                </div>
            </div>

            <div class="form-group row">
                <label for="oidc_user_attribute_mapping" class="col-sm-4 control-label"><?php _e('Attribute Mapping', 'cftp_admin'); ?></label>
                <div class="col-sm-8">
                    <textarea name="oidc_user_attribute_mapping" id="oidc_user_attribute_mapping" class="form-control" rows="4" 
                              placeholder='{"name": "name", "email": "email", "username": "preferred_username"}'><?php echo html_output(get_option('oidc_user_attribute_mapping') ?: '{}'); ?></textarea>
                    <p class="field_note form-text"><?php _e('JSON mapping of OIDC claims to user attributes', 'cftp_admin'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="options_divide"></div>

    <h4><?php _e('Configuration Management', 'cftp_admin'); ?></h4>

    <div class="form-group row">
        <div class="col-sm-8 offset-sm-4">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-info" id="export_config_btn">
                    <i class="fa fa-download"></i> <?php _e('Export Configuration', 'cftp_admin'); ?>
                </button>
                <button type="button" class="btn btn-outline-warning" id="import_config_btn">
                    <i class="fa fa-upload"></i> <?php _e('Import Configuration', 'cftp_admin'); ?>
                </button>
                <a href="<?php echo BASE_URI; ?>admin/user-sync.php" class="btn btn-outline-success">
                    <i class="fa fa-sync"></i> <?php _e('User Sync Dashboard', 'cftp_admin'); ?>
                </a>
            </div>
            <input type="file" id="import_config_file" accept=".json" style="display: none;" />
            <p class="field_note form-text mt-2"><?php _e('Export settings for backup or import configuration from file', 'cftp_admin'); ?></p>
        </div>
    </div>

    <div id="import_status" style="display: none;" class="mt-3"></div>

</div>

<style>
.connection-status, .credential-status {
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 14px;
}

.connection-status.success, .credential-status.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.connection-status.error, .credential-status.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.connection-status.info, .credential-status.info {
    background-color: #cce7ff;
    color: #004085;
    border: 1px solid #b8daff;
}

.required {
    color: #dc3545;
}

.copy_text {
    cursor: pointer;
    color: #007bff;
}

.copy_text:hover {
    color: #0056b3;
}

#oidc_settings_container {
    transition: opacity 0.3s ease-in-out;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle OIDC settings visibility
    const oidcEnabled = document.getElementById('oidc_enabled');
    const settingsContainer = document.getElementById('oidc_settings_container');
    
    oidcEnabled.addEventListener('change', function() {
        settingsContainer.style.display = this.checked ? 'block' : 'none';
    });

    // Toggle provider-specific features
    const providerType = document.getElementById('oidc_provider_type');
    const keycloakFeatures = document.querySelector('.keycloak_features');
    
    providerType.addEventListener('change', function() {
        keycloakFeatures.style.display = this.value === 'keycloak' ? 'block' : 'none';
    });

    // Toggle group sync settings
    const groupSyncEnabled = document.getElementById('oidc_group_sync_enabled');
    const groupSyncSettings = document.getElementById('group_sync_settings');
    
    if (groupSyncEnabled && groupSyncSettings) {
        groupSyncEnabled.addEventListener('change', function() {
            groupSyncSettings.style.display = this.checked ? 'block' : 'none';
        });
    }

    // Toggle password visibility
    const toggleSecretBtn = document.getElementById('toggle_secret_visibility');
    const secretField = document.getElementById('oidc_client_secret');
    
    toggleSecretBtn.addEventListener('click', function() {
        const isPassword = secretField.type === 'password';
        secretField.type = isPassword ? 'text' : 'password';
        this.innerHTML = isPassword ? '<i class="fa fa-eye-slash"></i>' : '<i class="fa fa-eye"></i>';
    });

    // Copy to clipboard functionality
    document.querySelectorAll('.copy_text').forEach(function(element) {
        element.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const targetElement = document.getElementById(targetId);
            if (targetElement) {
                targetElement.select();
                document.execCommand('copy');
                
                // Show temporary feedback
                const original = this.innerHTML;
                this.innerHTML = '<i class="fa fa-check"></i>';
                setTimeout(() => {
                    this.innerHTML = original;
                }, 2000);
            }
        });
    });
});
</script>