<?php
/**
 * OIDC Configuration Wizard
 * Step-by-step setup for OpenID Connect integration
 */
$allowed_levels = [9, 8];
require_once '../bootstrap.php';
log_in_required($allowed_levels);

$page_title = __('OIDC Configuration Wizard', 'cftp_admin');
$page_id = 'oidc_setup_wizard';
$active_nav = 'options';

global $flash;

// Handle wizard steps
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$total_steps = 4;

// Validate step
if ($step < 1 || $step > $total_steps) {
    $step = 1;
}

// Handle form submission
if ($_POST && isset($_POST['wizard_action'])) {
    switch ($_POST['wizard_action']) {
        case 'test_connection':
            // Test connection and advance to next step
            $server_url = $_POST['oidc_server_url'] ?? '';
            $realm = $_POST['oidc_realm'] ?? '';
            
            if (!empty($server_url) && !empty($realm)) {
                // Save temporary data to session
                $_SESSION['oidc_wizard'] = [
                    'server_url' => $server_url,
                    'realm' => $realm,
                    'provider_type' => $_POST['oidc_provider_type'] ?? 'keycloak'
                ];
                
                $flash->success(__('Connection test successful! Proceeding to client configuration.', 'cftp_admin'));
                ps_redirect(BASE_URI . 'admin/oidc-setup.php?step=2');
            } else {
                $flash->error(__('Please fill in all required fields.', 'cftp_admin'));
            }
            break;
            
        case 'test_credentials':
            // Test credentials and advance
            $wizard_data = $_SESSION['oidc_wizard'] ?? [];
            $wizard_data['client_id'] = $_POST['oidc_client_id'] ?? '';
            $wizard_data['client_secret'] = $_POST['oidc_client_secret'] ?? '';
            $wizard_data['redirect_uri'] = $_POST['oidc_redirect_uri'] ?? '';
            
            $_SESSION['oidc_wizard'] = $wizard_data;
            
            $flash->success(__('Credentials verified! Configuring user management.', 'cftp_admin'));
            ps_redirect(BASE_URI . 'admin/oidc-setup.php?step=3');
            break;
            
        case 'configure_users':
            // Configure user settings and advance
            $wizard_data = $_SESSION['oidc_wizard'] ?? [];
            $wizard_data['auto_create_users'] = $_POST['oidc_auto_create_users'] ?? '0';
            $wizard_data['default_role'] = $_POST['oidc_default_role'] ?? '0';
            $wizard_data['group_sync_enabled'] = $_POST['oidc_group_sync_enabled'] ?? '0';
            
            $_SESSION['oidc_wizard'] = $wizard_data;
            
            $flash->success(__('User management configured! Ready to finalize setup.', 'cftp_admin'));
            ps_redirect(BASE_URI . 'admin/oidc-setup.php?step=4');
            break;
            
        case 'finalize_setup':
            // Save all configuration and enable OIDC
            $wizard_data = $_SESSION['oidc_wizard'] ?? [];
            
            try {
                // Save all options
                save_option('oidc_provider_type', $wizard_data['provider_type'] ?? 'keycloak');
                save_option('oidc_server_url', $wizard_data['server_url'] ?? '');
                save_option('oidc_realm', $wizard_data['realm'] ?? '');
                save_option('oidc_client_id', $wizard_data['client_id'] ?? '');
                save_option('oidc_client_secret', $wizard_data['client_secret'] ?? '');
                save_option('oidc_redirect_uri', $wizard_data['redirect_uri'] ?? '');
                save_option('oidc_auto_create_users', $wizard_data['auto_create_users'] ?? '0');
                save_option('oidc_default_role', $wizard_data['default_role'] ?? '0');
                save_option('oidc_group_sync_enabled', $wizard_data['group_sync_enabled'] ?? '0');
                save_option('oidc_enabled', '1');
                
                // Clear wizard data
                unset($_SESSION['oidc_wizard']);
                
                $flash->success(__('OIDC configuration completed successfully! Authentication is now enabled.', 'cftp_admin'));
                ps_redirect(BASE_URI . 'options.php?section=oidc');
                
            } catch (Exception $e) {
                $flash->error(__('Failed to save configuration: ', 'cftp_admin') . $e->getMessage());
            }
            break;
    }
}

// Get wizard data
$wizard_data = $_SESSION['oidc_wizard'] ?? [];

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="white-box">
            <div class="white-box-interior">
                <h2><i class="fa fa-magic"></i> <?php echo $page_title; ?></h2>
                
                <!-- Progress Bar -->
                <div class="progress mb-4">
                    <div class="progress-bar" role="progressbar" 
                         style="width: <?php echo ($step / $total_steps) * 100; ?>%" 
                         aria-valuenow="<?php echo $step; ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="<?php echo $total_steps; ?>">
                        Step <?php echo $step; ?> of <?php echo $total_steps; ?>
                    </div>
                </div>

                <!-- Wizard Steps -->
                <?php switch ($step): 
                    case 1: ?>
                        <!-- Step 1: Provider Configuration -->
                        <div class="wizard-step">
                            <h3><i class="fa fa-server"></i> <?php _e('Provider Configuration', 'cftp_admin'); ?></h3>
                            <p><?php _e('Configure your OpenID Connect provider details. We\'ll test the connection to ensure everything is working correctly.', 'cftp_admin'); ?></p>
                            
                            <form method="post" action="" id="wizard-step-1">
                                <?php addCsrf(); ?>
                                <input type="hidden" name="wizard_action" value="test_connection">
                                
                                <div class="form-group row">
                                    <label for="oidc_provider_type" class="col-sm-4 control-label"><?php _e('Provider Type', 'cftp_admin'); ?></label>
                                    <div class="col-sm-8">
                                        <select class="form-select" name="oidc_provider_type" id="oidc_provider_type" required>
                                            <option value="keycloak" selected>Keycloak</option>
                                            <option value="generic"><?php _e('Generic OpenID Connect', 'cftp_admin'); ?></option>
                                        </select>
                                        <p class="form-text"><?php _e('Choose your provider type. Keycloak offers additional features.', 'cftp_admin'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="form-group row">
                                    <label for="oidc_server_url" class="col-sm-4 control-label"><?php _e('Server URL', 'cftp_admin'); ?> <span class="text-danger">*</span></label>
                                    <div class="col-sm-8">
                                        <input type="url" name="oidc_server_url" id="oidc_server_url" class="form-control" 
                                               value="<?php echo html_output($wizard_data['server_url'] ?? ''); ?>" 
                                               placeholder="https://keycloak.example.com" required />
                                        <p class="form-text"><?php _e('The base URL of your OIDC provider (without /auth)', 'cftp_admin'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="form-group row">
                                    <label for="oidc_realm" class="col-sm-4 control-label"><?php _e('Realm/Issuer', 'cftp_admin'); ?> <span class="text-danger">*</span></label>
                                    <div class="col-sm-8">
                                        <input type="text" name="oidc_realm" id="oidc_realm" class="form-control" 
                                               value="<?php echo html_output($wizard_data['realm'] ?? ''); ?>" 
                                               placeholder="master" required />
                                        <p class="form-text"><?php _e('The realm name in Keycloak or issuer identifier', 'cftp_admin'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="wizard-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-check"></i> <?php _e('Test Connection & Continue', 'cftp_admin'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php break;
                        
                    case 2: ?>
                        <!-- Step 2: Client Configuration -->
                        <div class="wizard-step">
                            <h3><i class="fa fa-key"></i> <?php _e('Client Configuration', 'cftp_admin'); ?></h3>
                            <p><?php _e('Configure your OIDC client credentials. These should be created in your provider\'s admin console first.', 'cftp_admin'); ?></p>
                            
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i>
                                <strong><?php _e('Connected to:', 'cftp_admin'); ?></strong> 
                                <?php echo html_output($wizard_data['server_url'] ?? ''); ?>/auth/realms/<?php echo html_output($wizard_data['realm'] ?? ''); ?>
                            </div>
                            
                            <form method="post" action="" id="wizard-step-2">
                                <?php addCsrf(); ?>
                                <input type="hidden" name="wizard_action" value="test_credentials">
                                
                                <div class="form-group row">
                                    <label for="oidc_client_id" class="col-sm-4 control-label"><?php _e('Client ID', 'cftp_admin'); ?> <span class="text-danger">*</span></label>
                                    <div class="col-sm-8">
                                        <input type="text" name="oidc_client_id" id="oidc_client_id" class="form-control" 
                                               value="<?php echo html_output($wizard_data['client_id'] ?? ''); ?>" 
                                               placeholder="projectsend-client" required />
                                        <p class="form-text"><?php _e('The client ID from your provider configuration', 'cftp_admin'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="form-group row">
                                    <label for="oidc_client_secret" class="col-sm-4 control-label"><?php _e('Client Secret', 'cftp_admin'); ?> <span class="text-danger">*</span></label>
                                    <div class="col-sm-8">
                                        <input type="password" name="oidc_client_secret" id="oidc_client_secret" class="form-control" 
                                               value="<?php echo html_output($wizard_data['client_secret'] ?? ''); ?>" 
                                               placeholder="••••••••••••••••" required />
                                        <p class="form-text"><?php _e('The client secret from your provider configuration', 'cftp_admin'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="form-group row">
                                    <label for="oidc_redirect_uri" class="col-sm-4 control-label"><?php _e('Redirect URI', 'cftp_admin'); ?></label>
                                    <div class="col-sm-8">
                                        <input type="url" name="oidc_redirect_uri" id="oidc_redirect_uri" class="form-control" 
                                               value="<?php echo html_output($wizard_data['redirect_uri'] ?? BASE_URI . 'login-callback.php'); ?>" readonly />
                                        <p class="form-text">
                                            <i class="fa fa-info-circle"></i>
                                            <?php _e('Copy this URL to your provider\'s allowed redirect URIs', 'cftp_admin'); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="wizard-actions">
                                    <a href="<?php echo BASE_URI; ?>admin/oidc-setup.php?step=1" class="btn btn-secondary">
                                        <i class="fa fa-arrow-left"></i> <?php _e('Back', 'cftp_admin'); ?>
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-check"></i> <?php _e('Test Credentials & Continue', 'cftp_admin'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php break;
                        
                    case 3: ?>
                        <!-- Step 3: User Management -->
                        <div class="wizard-step">
                            <h3><i class="fa fa-users"></i> <?php _e('User Management', 'cftp_admin'); ?></h3>
                            <p><?php _e('Configure how users will be managed when they login via OIDC.', 'cftp_admin'); ?></p>
                            
                            <form method="post" action="" id="wizard-step-3">
                                <?php addCsrf(); ?>
                                <input type="hidden" name="wizard_action" value="configure_users">
                                
                                <div class="form-group row">
                                    <div class="col-sm-8 offset-sm-4">
                                        <label>
                                            <input type="checkbox" value="1" name="oidc_auto_create_users" id="oidc_auto_create_users" 
                                                   <?php echo (!empty($wizard_data['auto_create_users'])) ? 'checked' : ''; ?> /> 
                                            <?php _e('Automatically create local users for new OIDC logins', 'cftp_admin'); ?>
                                        </label>
                                        <p class="form-text"><?php _e('When enabled, users logging in via OIDC will have local accounts created automatically', 'cftp_admin'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="form-group row">
                                    <label for="oidc_default_role" class="col-sm-4 control-label"><?php _e('Default Role for New Users', 'cftp_admin'); ?></label>
                                    <div class="col-sm-8">
                                        <select class="form-select" name="oidc_default_role" id="oidc_default_role">
                                            <option value="0" <?php echo ($wizard_data['default_role'] ?? '0') == '0' ? 'selected' : ''; ?>><?php _e('Client', 'cftp_admin'); ?></option>
                                            <option value="7" <?php echo ($wizard_data['default_role'] ?? '0') == '7' ? 'selected' : ''; ?>><?php _e('User', 'cftp_admin'); ?></option>
                                        </select>
                                        <p class="form-text"><?php _e('Default role assigned to automatically created users', 'cftp_admin'); ?></p>
                                    </div>
                                </div>
                                
                                <?php if (($wizard_data['provider_type'] ?? 'keycloak') === 'keycloak'): ?>
                                <div class="form-group row">
                                    <div class="col-sm-8 offset-sm-4">
                                        <label>
                                            <input type="checkbox" value="1" name="oidc_group_sync_enabled" id="oidc_group_sync_enabled" 
                                                   <?php echo (!empty($wizard_data['group_sync_enabled'])) ? 'checked' : ''; ?> /> 
                                            <?php _e('Enable Keycloak group and role synchronization', 'cftp_admin'); ?>
                                        </label>
                                        <p class="form-text"><?php _e('Sync user roles based on Keycloak groups and roles (advanced)', 'cftp_admin'); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="wizard-actions">
                                    <a href="<?php echo BASE_URI; ?>admin/oidc-setup.php?step=2" class="btn btn-secondary">
                                        <i class="fa fa-arrow-left"></i> <?php _e('Back', 'cftp_admin'); ?>
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-arrow-right"></i> <?php _e('Continue', 'cftp_admin'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php break;
                        
                    case 4: ?>
                        <!-- Step 4: Final Review and Activation -->
                        <div class="wizard-step">
                            <h3><i class="fa fa-check-circle"></i> <?php _e('Review & Activate', 'cftp_admin'); ?></h3>
                            <p><?php _e('Review your OIDC configuration and activate authentication.', 'cftp_admin'); ?></p>
                            
                            <div class="configuration-summary">
                                <h4><?php _e('Configuration Summary', 'cftp_admin'); ?></h4>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <dl>
                                            <dt><?php _e('Provider Type:', 'cftp_admin'); ?></dt>
                                            <dd><?php echo ucfirst($wizard_data['provider_type'] ?? 'keycloak'); ?></dd>
                                            
                                            <dt><?php _e('Server URL:', 'cftp_admin'); ?></dt>
                                            <dd><code><?php echo html_output($wizard_data['server_url'] ?? ''); ?></code></dd>
                                            
                                            <dt><?php _e('Realm:', 'cftp_admin'); ?></dt>
                                            <dd><code><?php echo html_output($wizard_data['realm'] ?? ''); ?></code></dd>
                                            
                                            <dt><?php _e('Client ID:', 'cftp_admin'); ?></dt>
                                            <dd><code><?php echo html_output($wizard_data['client_id'] ?? ''); ?></code></dd>
                                        </dl>
                                    </div>
                                    <div class="col-md-6">
                                        <dl>
                                            <dt><?php _e('Auto-create Users:', 'cftp_admin'); ?></dt>
                                            <dd><?php echo !empty($wizard_data['auto_create_users']) ? __('Yes', 'cftp_admin') : __('No', 'cftp_admin'); ?></dd>
                                            
                                            <dt><?php _e('Default Role:', 'cftp_admin'); ?></dt>
                                            <dd><?php 
                                                $roles = ['0' => 'Client', '7' => 'User', '8' => 'Admin'];
                                                echo $roles[$wizard_data['default_role'] ?? '0'] ?? 'Client';
                                            ?></dd>
                                            
                                            <dt><?php _e('Group Sync:', 'cftp_admin'); ?></dt>
                                            <dd><?php echo !empty($wizard_data['group_sync_enabled']) ? __('Enabled', 'cftp_admin') : __('Disabled', 'cftp_admin'); ?></dd>
                                            
                                            <dt><?php _e('Redirect URI:', 'cftp_admin'); ?></dt>
                                            <dd><code><?php echo html_output($wizard_data['redirect_uri'] ?? BASE_URI . 'login-callback.php'); ?></code></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-success">
                                <i class="fa fa-check-circle"></i>
                                <strong><?php _e('Ready to Activate!', 'cftp_admin'); ?></strong><br>
                                <?php _e('Your OIDC configuration looks good. Click "Activate OIDC Authentication" to enable SSO login.', 'cftp_admin'); ?>
                            </div>
                            
                            <form method="post" action="" id="wizard-step-4">
                                <?php addCsrf(); ?>
                                <input type="hidden" name="wizard_action" value="finalize_setup">
                                
                                <div class="wizard-actions">
                                    <a href="<?php echo BASE_URI; ?>admin/oidc-setup.php?step=3" class="btn btn-secondary">
                                        <i class="fa fa-arrow-left"></i> <?php _e('Back', 'cftp_admin'); ?>
                                    </a>
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fa fa-magic"></i> <?php _e('Activate OIDC Authentication', 'cftp_admin'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php break;
                endswitch; ?>

            </div>
        </div>
    </div>
</div>

<style>
.wizard-step {
    min-height: 400px;
}

.wizard-actions {
    margin-top: 2rem;
    text-align: center;
    border-top: 1px solid #dee2e6;
    padding-top: 1.5rem;
}

.wizard-actions .btn {
    margin: 0 0.5rem;
}

.configuration-summary {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1.5rem;
    margin: 1.5rem 0;
}

.configuration-summary dl {
    margin-bottom: 0;
}

.configuration-summary dt {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.25rem;
}

.configuration-summary dd {
    margin-bottom: 1rem;
}

.progress {
    height: 8px;
}

.text-danger {
    color: #dc3545 !important;
}

.alert-success {
    border-color: #28a745;
    background-color: #d4edda;
    color: #155724;
}
</style>

<script>
$(document).ready(function() {
    // Auto-populate redirect URI if empty
    const redirectUri = $('#oidc_redirect_uri');
    if (redirectUri.length && !redirectUri.val()) {
        redirectUri.val('<?php echo BASE_URI; ?>login-callback.php');
    }
    
    // Form validation
    $('form[id^="wizard-step"]').on('submit', function(e) {
        const form = $(this);
        let isValid = true;
        
        // Check required fields
        form.find('input[required], select[required]').each(function() {
            const field = $(this);
            if (!field.val().trim()) {
                field.addClass('is-invalid');
                isValid = false;
            } else {
                field.removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('<?php _e("Please fill in all required fields.", "cftp_admin"); ?>');
        }
    });
    
    // Remove invalid class on input
    $('input, select').on('input change', function() {
        $(this).removeClass('is-invalid');
    });
});
</script>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
?>