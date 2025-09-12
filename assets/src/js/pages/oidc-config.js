/**
 * OIDC Configuration JavaScript
 * Handles frontend interactions for OpenID Connect configuration
 */

(function($) {
    'use strict';

    // Configuration object
    const OIDCConfig = {
        ajaxUrl: BASE_URI + 'includes/ajax/oidc-config.php',
        
        init: function() {
            this.bindEvents();
            this.initializeTooltips();
        },

        bindEvents: function() {
            // Test connection button
            $('#test_connection_btn').on('click', this.testConnection.bind(this));
            
            // Test credentials button  
            $('#test_credentials_btn').on('click', this.testCredentials.bind(this));
            
            // Export configuration
            $('#export_config_btn').on('click', this.exportConfig.bind(this));
            
            // Import configuration
            $('#import_config_btn').on('click', this.triggerImport.bind(this));
            $('#import_config_file').on('change', this.importConfig.bind(this));
            
            // Real-time validation
            $('#oidc_server_url, #oidc_realm').on('blur', this.validateConnection.bind(this));
            $('#oidc_client_id, #oidc_client_secret').on('blur', this.validateCredentials.bind(this));
            
            // JSON validation for mapping fields
            $('#oidc_role_mapping, #oidc_user_attribute_mapping').on('blur', this.validateJSON.bind(this));
            
            // Auto-populate redirect URI
            $('#oidc_server_url, #oidc_realm').on('input', this.updateRedirectUri.bind(this));
        },

        initializeTooltips: function() {
            // Initialize Bootstrap tooltips if available
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function(tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        },

        testConnection: function() {
            const serverUrl = $('#oidc_server_url').val().trim();
            const realm = $('#oidc_realm').val().trim();
            
            if (!serverUrl || !realm) {
                this.showStatus('connection_status', 'error', 'Server URL and Realm are required');
                return;
            }

            this.showStatus('connection_status', 'info', 'Testing connection...');
            $('#test_connection_btn').prop('disabled', true);

            $.post(this.ajaxUrl, {
                action: 'test_connection',
                server_url: serverUrl,
                realm: realm
            })
            .done((response) => {
                if (response.status === 'success') {
                    this.showStatus('connection_status', 'success', response.message);
                    if (response.data) {
                        this.displayConnectionInfo(response.data);
                    }
                } else {
                    this.showStatus('connection_status', 'error', response.message || 'Connection test failed');
                }
            })
            .fail((xhr) => {
                this.showStatus('connection_status', 'error', 'Network error occurred');
                console.error('Connection test failed:', xhr);
            })
            .always(() => {
                $('#test_connection_btn').prop('disabled', false);
            });
        },

        testCredentials: function() {
            const serverUrl = $('#oidc_server_url').val().trim();
            const realm = $('#oidc_realm').val().trim();
            const clientId = $('#oidc_client_id').val().trim();
            const clientSecret = $('#oidc_client_secret').val().trim();
            
            if (!serverUrl || !realm || !clientId || !clientSecret) {
                this.showStatus('credential_status', 'error', 'All credential fields are required');
                return;
            }

            this.showStatus('credential_status', 'info', 'Testing credentials...');
            $('#test_credentials_btn').prop('disabled', true);

            $.post(this.ajaxUrl, {
                action: 'test_credentials',
                server_url: serverUrl,
                realm: realm,
                client_id: clientId,
                client_secret: clientSecret
            })
            .done((response) => {
                if (response.status === 'success') {
                    this.showStatus('credential_status', 'success', response.message);
                } else {
                    this.showStatus('credential_status', 'error', response.message || 'Credential test failed');
                }
            })
            .fail((xhr) => {
                this.showStatus('credential_status', 'error', 'Network error occurred');
                console.error('Credential test failed:', xhr);
            })
            .always(() => {
                $('#test_credentials_btn').prop('disabled', false);
            });
        },

        validateConnection: function() {
            const serverUrl = $('#oidc_server_url').val().trim();
            const realm = $('#oidc_realm').val().trim();
            
            if (serverUrl && realm) {
                // Debounce validation
                clearTimeout(this.connectionTimeout);
                this.connectionTimeout = setTimeout(() => {
                    this.testConnection();
                }, 1000);
            }
        },

        validateCredentials: function() {
            const serverUrl = $('#oidc_server_url').val().trim();
            const realm = $('#oidc_realm').val().trim();
            const clientId = $('#oidc_client_id').val().trim();
            const clientSecret = $('#oidc_client_secret').val().trim();
            
            if (serverUrl && realm && clientId && clientSecret) {
                // Debounce validation
                clearTimeout(this.credentialTimeout);
                this.credentialTimeout = setTimeout(() => {
                    this.testCredentials();
                }, 1500);
            }
        },

        validateJSON: function(event) {
            const $field = $(event.target);
            const value = $field.val().trim();
            
            if (!value) {
                this.clearFieldError($field);
                return;
            }

            try {
                JSON.parse(value);
                this.clearFieldError($field);
                $field.removeClass('is-invalid').addClass('is-valid');
            } catch (e) {
                this.showFieldError($field, 'Invalid JSON format');
                $field.removeClass('is-valid').addClass('is-invalid');
            }
        },

        updateRedirectUri: function() {
            const serverUrl = $('#oidc_server_url').val().trim();
            if (serverUrl && !$('#oidc_redirect_uri').val()) {
                $('#oidc_redirect_uri').val(BASE_URI + 'login-callback.php');
            }
        },

        exportConfig: function() {
            window.location.href = this.ajaxUrl + '?action=export_config';
        },

        triggerImport: function() {
            $('#import_config_file').click();
        },

        importConfig: function(event) {
            const file = event.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('action', 'import_config');
            formData.append('config_file', file);

            this.showStatus('import_status', 'info', 'Importing configuration...');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done((response) => {
                if (response.status === 'success') {
                    this.showStatus('import_status', 'success', response.message);
                    // Reload page to show imported settings
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    this.showStatus('import_status', 'error', response.message || 'Import failed');
                }
            })
            .fail((xhr) => {
                this.showStatus('import_status', 'error', 'Network error during import');
                console.error('Import failed:', xhr);
            });

            // Clear file input
            event.target.value = '';
        },

        showStatus: function(elementId, type, message) {
            const $element = $('#' + elementId);
            $element.removeClass('success error info')
                   .addClass(type)
                   .html('<i class="fa fa-' + this.getStatusIcon(type) + '"></i> ' + message)
                   .show();

            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(() => $element.fadeOut(), 5000);
            }
        },

        getStatusIcon: function(type) {
            const icons = {
                success: 'check-circle',
                error: 'exclamation-triangle',
                info: 'info-circle'
            };
            return icons[type] || 'info-circle';
        },

        displayConnectionInfo: function(data) {
            const $status = $('#connection_status');
            let info = '<strong>Connection Details:</strong><br>';
            info += 'Issuer: ' + data.issuer + '<br>';
            info += 'Auth Endpoint: ' + data.auth_endpoint + '<br>';
            info += 'Token Endpoint: ' + data.token_endpoint;
            
            $status.append('<br>' + info);
        },

        showFieldError: function($field, message) {
            this.clearFieldError($field);
            const errorDiv = $('<div class="field-error text-danger small mt-1">' + message + '</div>');
            $field.parent().append(errorDiv);
        },

        clearFieldError: function($field) {
            $field.parent().find('.field-error').remove();
        }
    };

    // Form validation enhancement
    const FormValidator = {
        init: function() {
            $('#options').on('submit', this.validateForm.bind(this));
        },

        validateForm: function(event) {
            const oidcEnabled = $('#oidc_enabled').prop('checked');
            
            if (!oidcEnabled) {
                return true; // No validation needed if OIDC is disabled
            }

            let isValid = true;
            const requiredFields = [
                '#oidc_server_url',
                '#oidc_realm', 
                '#oidc_client_id',
                '#oidc_client_secret'
            ];

            // Clear previous validation states
            requiredFields.forEach(selector => {
                $(selector).removeClass('is-invalid is-valid');
                OIDCConfig.clearFieldError($(selector));
            });

            // Validate required fields
            requiredFields.forEach(selector => {
                const $field = $(selector);
                const value = $field.val().trim();
                
                if (!value) {
                    $field.addClass('is-invalid');
                    OIDCConfig.showFieldError($field, 'This field is required');
                    isValid = false;
                } else {
                    $field.addClass('is-valid');
                }
            });

            // Validate URLs
            const urlFields = ['#oidc_server_url', '#oidc_redirect_uri'];
            urlFields.forEach(selector => {
                const $field = $(selector);
                const value = $field.val().trim();
                
                if (value && !this.isValidUrl(value)) {
                    $field.addClass('is-invalid');
                    OIDCConfig.showFieldError($field, 'Please enter a valid URL');
                    isValid = false;
                }
            });

            // Validate JSON fields
            const jsonFields = ['#oidc_role_mapping', '#oidc_user_attribute_mapping'];
            jsonFields.forEach(selector => {
                const $field = $(selector);
                const value = $field.val().trim();
                
                if (value) {
                    try {
                        JSON.parse(value);
                        $field.addClass('is-valid');
                    } catch (e) {
                        $field.addClass('is-invalid');
                        OIDCConfig.showFieldError($field, 'Invalid JSON format');
                        isValid = false;
                    }
                }
            });

            if (!isValid) {
                event.preventDefault();
                // Scroll to first error
                const firstError = $('.is-invalid').first();
                if (firstError.length) {
                    $('html, body').animate({
                        scrollTop: firstError.offset().top - 100
                    }, 500);
                }
            }

            return isValid;
        },

        isValidUrl: function(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        OIDCConfig.init();
        FormValidator.init();
    });

    // Export for external access
    window.OIDCConfig = OIDCConfig;

})(jQuery);