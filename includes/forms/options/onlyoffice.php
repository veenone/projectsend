<?php
/**
 * ONLYOFFICE Document Editor options form
 * Contains settings for ONLYOFFICE Document Server integration
 */

// Define the form sections and fields
$form_sections = [
    [
        'title' => __('Document Server', 'cftp_admin'),
        'description' => __('Configure connection to ONLYOFFICE Document Server for real-time document editing.', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'onlyoffice_enabled',
                'label' => __('Enable Document Editing', 'cftp_admin'),
                'note' => __('Allow users to edit Office documents (docx, xlsx, pptx) directly in the browser.', 'cftp_admin')
            ],
            [
                'type' => 'text',
                'name' => 'onlyoffice_document_server_url',
                'label' => __('Document Server URL', 'cftp_admin'),
                'placeholder' => 'https://docs.example.com',
                'note' => __('The URL where ONLYOFFICE Document Server is accessible. Must be reachable from both the server and user browsers.', 'cftp_admin')
            ],
            [
                'type' => 'custom',
                'name' => 'onlyoffice_connection_test',
                'render_callback' => function($field) {
                    $server_url = get_option('onlyoffice_document_server_url');
                    ?>
                    <div class="form-group row">
                        <label class="col-sm-4 control-label"><?php _e('Connection Status', 'cftp_admin'); ?></label>
                        <div class="col-sm-8">
                            <button type="button" class="btn btn-secondary" id="test_onlyoffice_connection" <?php echo empty($server_url) ? 'disabled' : ''; ?>>
                                <i class="fa fa-plug me-1"></i>
                                <?php _e('Test Connection', 'cftp_admin'); ?>
                            </button>
                            <span id="onlyoffice_connection_result" class="ms-3"></span>
                            <p class="field_note form-text text-muted mt-2">
                                <?php _e('Test the connection to verify the Document Server is accessible.', 'cftp_admin'); ?>
                            </p>
                        </div>
                    </div>
                    <script>
                    $(document).ready(function() {
                        $('#test_onlyoffice_connection').on('click', function() {
                            var $btn = $(this);
                            var $result = $('#onlyoffice_connection_result');

                            $btn.prop('disabled', true);
                            $result.html('<i class="fa fa-spinner fa-spin"></i> <?php _e('Testing...', 'cftp_admin'); ?>');

                            $.ajax({
                                url: 'process.php?do=test_onlyoffice_connection',
                                method: 'GET',
                                dataType: 'json'
                            }).done(function(response) {
                                if (response.status === 'success') {
                                    $result.html('<span class="text-success"><i class="fa fa-check-circle me-1"></i>' + response.message + '</span>');
                                } else {
                                    $result.html('<span class="text-danger"><i class="fa fa-times-circle me-1"></i>' + response.message + '</span>');
                                }
                            }).fail(function() {
                                $result.html('<span class="text-danger"><i class="fa fa-times-circle me-1"></i><?php _e('Connection failed', 'cftp_admin'); ?></span>');
                            }).always(function() {
                                $btn.prop('disabled', false);
                            });
                        });

                        // Enable/disable test button based on URL field
                        $('input[name="onlyoffice_document_server_url"]').on('input', function() {
                            $('#test_onlyoffice_connection').prop('disabled', $(this).val().trim() === '');
                        });
                    });
                    </script>
                    <?php
                }
            ]
        ]
    ],
    [
        'title' => __('Security', 'cftp_admin'),
        'description' => __('Configure JWT authentication for secure communication with Document Server.', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'onlyoffice_jwt_enabled',
                'label' => __('Enable JWT Authentication', 'cftp_admin'),
                'note' => __('Use JSON Web Tokens to secure communication between ProjectSend and Document Server. Recommended for production use.', 'cftp_admin')
            ],
            [
                'type' => 'text',
                'name' => 'onlyoffice_jwt_secret',
                'label' => __('JWT Secret', 'cftp_admin'),
                'placeholder' => __('Enter a strong secret key', 'cftp_admin'),
                'note' => __('Must match the JWT_SECRET configured in your ONLYOFFICE Document Server. Use a long, random string.', 'cftp_admin')
            ],
            [
                'type' => 'custom',
                'name' => 'onlyoffice_generate_secret',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <div class="col-sm-8 offset-sm-4">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="generate_jwt_secret">
                                <i class="fa fa-random me-1"></i>
                                <?php _e('Generate Secret', 'cftp_admin'); ?>
                            </button>
                            <script>
                            $('#generate_jwt_secret').on('click', function() {
                                var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
                                var secret = '';
                                for (var i = 0; i < 32; i++) {
                                    secret += chars.charAt(Math.floor(Math.random() * chars.length));
                                }
                                $('input[name="onlyoffice_jwt_secret"]').val(secret);
                            });
                            </script>
                        </div>
                    </div>
                    <?php
                }
            ],
            [
                'type' => 'text',
                'name' => 'onlyoffice_file_token_expiry',
                'label' => __('File Token Expiry', 'cftp_admin'),
                'placeholder' => '3600',
                'note' => __('How long (in seconds) file access tokens remain valid. Default: 3600 (1 hour).', 'cftp_admin')
            ]
        ]
    ],
    [
        'title' => __('Supported File Types', 'cftp_admin'),
        'description' => __('File types that can be edited or viewed with ONLYOFFICE.', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'custom',
                'name' => 'onlyoffice_supported_types',
                'render_callback' => function($field) {
                    $extensions = \ProjectSend\Classes\OnlyOffice::getSupportedExtensions();
                    ?>
                    <div class="form-group row">
                        <div class="col-sm-8 offset-sm-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fa fa-pencil me-1"></i> <?php _e('Editable', 'cftp_admin'); ?></h6>
                                    <div class="d-flex flex-wrap gap-1 mb-3">
                                        <?php foreach ($extensions['editable'] as $ext): ?>
                                            <span class="badge bg-success">.<?php echo $ext; ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fa fa-eye me-1"></i> <?php _e('View Only', 'cftp_admin'); ?></h6>
                                    <div class="d-flex flex-wrap gap-1 mb-3">
                                        <?php foreach ($extensions['viewable'] as $ext): ?>
                                            <span class="badge bg-secondary">.<?php echo $ext; ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            ]
        ]
    ],
    [
        'title' => __('Setup Instructions', 'cftp_admin'),
        'description' => '',
        'fields' => [
            [
                'type' => 'custom',
                'name' => 'onlyoffice_setup_guide',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <div class="col-sm-12">
                            <div class="alert alert-info">
                                <h5><i class="fa fa-info-circle me-1"></i> <?php _e('Quick Setup Guide', 'cftp_admin'); ?></h5>
                                <ol class="mb-2">
                                    <li><?php _e('Install ONLYOFFICE Document Server using Docker:', 'cftp_admin'); ?>
                                        <pre class="bg-light p-2 mt-1 mb-2" style="font-size: 12px;">docker-compose -f docker-compose-onlyoffice.yml up -d</pre>
                                    </li>
                                    <li><?php _e('Wait for the container to start (may take 1-2 minutes)', 'cftp_admin'); ?></li>
                                    <li><?php _e('Enter the Document Server URL above (e.g., http://your-server:8080)', 'cftp_admin'); ?></li>
                                    <li><?php _e('Configure the same JWT secret in both places:', 'cftp_admin'); ?>
                                        <ul>
                                            <li><?php _e('In docker-compose-onlyoffice.yml: JWT_SECRET environment variable', 'cftp_admin'); ?></li>
                                            <li><?php _e('In this form: JWT Secret field', 'cftp_admin'); ?></li>
                                        </ul>
                                    </li>
                                    <li><?php _e('Click "Test Connection" to verify', 'cftp_admin'); ?></li>
                                    <li><?php _e('Enable Document Editing and save', 'cftp_admin'); ?></li>
                                </ol>
                                <p class="mb-0">
                                    <a href="https://api.onlyoffice.com/docs/docs-api/get-started/installation/self-hosted/" target="_blank" class="alert-link">
                                        <i class="fa fa-external-link me-1"></i>
                                        <?php _e('View full documentation', 'cftp_admin'); ?>
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            ]
        ]
    ]
];
