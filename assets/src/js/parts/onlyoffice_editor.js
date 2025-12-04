(function () {
    'use strict';

    admin.parts.onlyofficeEditor = function () {
        console.log('ONLYOFFICE Editor: Initializing...');

        // Check if already initialized
        if ($('#onlyoffice_modal').length > 0) {
            console.log('ONLYOFFICE Editor: Already initialized');
            return;
        }

        var docEditor = null;
        var apiScriptLoaded = false;
        var serverUrl = '';

        // Append ONLYOFFICE editor modal
        var modal_layout = `<div id="onlyoffice_modal" class="modal fade" tabindex="-1" role="dialog" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-fullscreen">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h5 class="modal-title">
                            <i class="fa fa-file-text-o me-2"></i>
                            <span class="filename"></span>
                        </h5>
                        <div class="modal-header-buttons">
                            <span class="badge bg-secondary me-3 editor-status" style="display: none;">
                                <i class="fa fa-circle-o-notch fa-spin me-1"></i>
                                <span class="status-text">Loading...</span>
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="onlyoffice_close" title="Close Editor">
                                <i class="fa fa-times me-1"></i> Close
                            </button>
                        </div>
                    </div>
                    <div class="modal-body p-0" style="height: calc(100vh - 56px);">
                        <div id="onlyoffice_editor_container" style="width: 100%; height: 100%;"></div>
                    </div>
                </div>
            </div>
        </div>
        <style>
            #onlyoffice_modal .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: #f8f9fa;
                border-bottom: 1px solid #dee2e6;
            }
            #onlyoffice_modal .modal-header-buttons {
                display: flex;
                align-items: center;
            }
            #onlyoffice_modal .editor-status.saving {
                background-color: #ffc107 !important;
                color: #000;
            }
            #onlyoffice_modal .editor-status.saved {
                background-color: #28a745 !important;
            }
            #onlyoffice_modal .editor-status.error {
                background-color: #dc3545 !important;
            }
            #onlyoffice_modal .modal-body {
                overflow: hidden;
            }
            #onlyoffice_editor_container iframe {
                border: none;
            }
        </style>`;
        $('body').append(modal_layout);
        console.log('ONLYOFFICE Editor: Modal appended');

        // Load ONLYOFFICE API script dynamically
        function loadApiScript(url, callback) {
            if (apiScriptLoaded && window.DocsAPI) {
                callback();
                return;
            }

            var script = document.createElement('script');
            script.src = url + '/web-apps/apps/api/documents/api.js';
            script.onload = function() {
                apiScriptLoaded = true;
                callback();
            };
            script.onerror = function() {
                showError('Failed to load ONLYOFFICE API. Please check the server URL.');
            };
            document.head.appendChild(script);
        }

        // Show status badge
        function showStatus(text, type) {
            var $status = $('#onlyoffice_modal .editor-status');
            $status.removeClass('saving saved error').addClass(type);
            $status.find('.status-text').text(text);
            $status.show();

            if (type === 'saved') {
                setTimeout(function() {
                    $status.fadeOut();
                }, 3000);
            }
        }

        // Show error message
        function showError(message) {
            $('#onlyoffice_editor_container').html(
                '<div class="alert alert-danger m-4">' +
                '<i class="fa fa-exclamation-triangle me-2"></i>' +
                message +
                '</div>'
            );
        }

        // Initialize ONLYOFFICE editor
        function initEditor(config, filename) {
            $('#onlyoffice_modal .filename').text(filename);
            $('#onlyoffice_editor_container').empty();

            // Add event handlers to config
            config.events = {
                onAppReady: function() {
                    showStatus('Ready', 'saved');
                },
                onDocumentStateChange: function(event) {
                    if (event.data) {
                        showStatus('Unsaved changes', 'saving');
                    } else {
                        showStatus('Saved', 'saved');
                    }
                },
                onError: function(event) {
                    console.error('ONLYOFFICE Error:', event);
                    showStatus('Error', 'error');
                },
                onWarning: function(event) {
                    console.warn('ONLYOFFICE Warning:', event);
                },
                onRequestClose: function() {
                    closeEditor();
                }
            };

            try {
                docEditor = new DocsAPI.DocEditor('onlyoffice_editor_container', config);
            } catch (e) {
                console.error('Failed to initialize editor:', e);
                showError('Failed to initialize editor: ' + e.message);
            }
        }

        // Close editor
        function closeEditor() {
            if (docEditor) {
                try {
                    docEditor.destroyEditor();
                } catch (e) {
                    console.warn('Error destroying editor:', e);
                }
                docEditor = null;
            }
            $('#onlyoffice_modal').modal('hide');
            $('#onlyoffice_editor_container').empty();
        }

        // Open editor for a file
        function openEditor(fileId, mode) {
            console.log('ONLYOFFICE Editor: Opening file', fileId, 'in mode', mode);
            mode = mode || 'edit';

            // Show loading state
            $('#onlyoffice_modal .filename').text('Loading...');
            $('#onlyoffice_editor_container').html(
                '<div class="d-flex justify-content-center align-items-center h-100">' +
                '<div class="spinner-border text-primary" role="status">' +
                '<span class="visually-hidden">Loading...</span>' +
                '</div>' +
                '</div>'
            );
            $('#onlyoffice_modal').modal('show');

            // Get editor config from server
            $.ajax({
                method: 'GET',
                url: 'process.php',
                data: {
                    do: 'get_onlyoffice_config',
                    file_id: fileId,
                    mode: mode
                },
                dataType: 'json'
            }).done(function(response) {
                console.log('ONLYOFFICE Editor: Got response', response);
                if (response.status === 'success') {
                    serverUrl = response.server_url;
                    var config = response.config;
                    var filename = config.document.title;

                    // Load API and initialize editor
                    loadApiScript(serverUrl, function() {
                        initEditor(config, filename);
                    });
                } else {
                    showError(response.message || 'Failed to load editor configuration');
                }
            }).fail(function(xhr, status, error) {
                console.error('ONLYOFFICE Editor: AJAX failed', xhr, status, error);
                showError('Failed to connect to server: ' + error);
            });
        }

        // Button click handlers - Edit button
        $(document).on('click', '.onlyoffice-edit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('ONLYOFFICE Editor: Edit button clicked');
            var fileId = $(this).data('file-id');
            console.log('ONLYOFFICE Editor: File ID', fileId);
            if (fileId) {
                openEditor(fileId, 'edit');
            }
        });

        // Button click handlers - View button
        $(document).on('click', '.onlyoffice-view', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('ONLYOFFICE Editor: View button clicked');
            var fileId = $(this).data('file-id');
            console.log('ONLYOFFICE Editor: File ID', fileId);
            if (fileId) {
                openEditor(fileId, 'view');
            }
        });

        // Close button handler
        $(document).on('click', '#onlyoffice_close', function(e) {
            e.preventDefault();

            // Check for unsaved changes
            var $status = $('#onlyoffice_modal .editor-status');
            if ($status.hasClass('saving') && $status.is(':visible')) {
                if (!confirm('You have unsaved changes. Are you sure you want to close?')) {
                    return;
                }
            }

            closeEditor();
        });

        // Cleanup when modal is hidden
        $('#onlyoffice_modal').on('hidden.bs.modal', function(e) {
            if (docEditor) {
                try {
                    docEditor.destroyEditor();
                } catch (e) {
                    console.warn('Error destroying editor:', e);
                }
                docEditor = null;
            }
            $('#onlyoffice_editor_container').empty();
            $('.editor-status').hide();
        });

        // Prevent accidental navigation when editing
        $(window).on('beforeunload', function(e) {
            if (docEditor && $('#onlyoffice_modal').hasClass('show')) {
                var $status = $('#onlyoffice_modal .editor-status');
                if ($status.hasClass('saving') && $status.is(':visible')) {
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            }
        });

        console.log('ONLYOFFICE Editor: Initialization complete');
    };
})();
