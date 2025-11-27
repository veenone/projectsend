(function () {
    'use strict';

    admin.parts.filePreviewModal = function () {

        $(document).ready(function(e) {
            // Append modal with custom size (600x800) and fullscreen toggle
            var modal_layout = `<div id="preview_modal" class="modal fade" tabindex="-1" role="dialog">
                <div class="modal-dialog" style="max-width: 600px; width: 600px;">
                    <div class="modal-content" style="height: 800px;">
                        <div class="modal-header">
                            <h5 class="modal-title"></h5>
                            <div class="modal-header-buttons">
                                <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="preview_fullscreen_toggle" title="Toggle Fullscreen">
                                    <i class="fa fa-expand"></i>
                                </button>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                        </div>
                        <div class="modal-body" style="height: calc(100% - 56px); overflow: auto;">
                        </div>
                    </div>
                </div>
            </div>
            <style>
                #preview_modal .modal-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                #preview_modal .modal-header-buttons {
                    display: flex;
                    align-items: center;
                }
                #preview_modal.fullscreen .modal-dialog {
                    max-width: 100% !important;
                    width: 100% !important;
                    height: 100% !important;
                    margin: 0 !important;
                }
                #preview_modal.fullscreen .modal-content {
                    height: 100% !important;
                    border-radius: 0 !important;
                }
                #preview_modal.fullscreen .modal-body {
                    height: calc(100% - 56px) !important;
                }
                #preview_modal .pdf-preview-container,
                #preview_modal .pdf-preview-container object,
                #preview_modal .pdf-preview-container iframe {
                    width: 100%;
                    height: 100%;
                }
                #preview_modal .modal-body img.img-responsive {
                    max-width: 100%;
                    height: auto;
                }
            </style>`;
            $('body').append(modal_layout);

            // Fullscreen toggle
            $(document).on('click', '#preview_fullscreen_toggle', function() {
                var modal = $('#preview_modal');
                modal.toggleClass('fullscreen');
                var icon = $(this).find('i');
                if (modal.hasClass('fullscreen')) {
                    icon.removeClass('fa-expand').addClass('fa-compress');
                } else {
                    icon.removeClass('fa-compress').addClass('fa-expand');
                }
            });

            // Button trigger - use event delegation for dynamically created elements
            $(document).on('click', '.get-preview', function(e) {
                e.preventDefault();
                var url = $(this).data("url");
                var content = '';

                $.ajax({
                    method: "GET",
                    url: url,
                    cache: false,
                }).done(function(response) {
                    var obj = JSON.parse(response);
                    switch (obj.type) {
                        case 'video':
                            content = `
                                <div class="embed-responsive embed-responsive-16by9">
                                    <video controls class="w-100">
                                        <source src="`+obj.file_url+`" format="`+obj.mime_type+`">
                                    </video>
                                </div>`;
                            break;
                        case 'audio':
                            content = `
                                <audio controls>
                                    <source src="`+obj.file_url+`" format="`+obj.mime_type+`">
                                </audio>`;
                            break;
                        case 'pdf':
                            // Add PDF viewer parameters to disable toolbar features (print, download, edit)
                            // #toolbar=0 hides the entire toolbar
                            // #navpanes=0 hides side panels
                            // #scrollbar=1 keeps scrollbar
                            // #view=FitH fits width
                            var pdfUrl = obj.file_url + '#toolbar=0&navpanes=0&scrollbar=1&view=FitH';
                            content = `
                                <div class="pdf-preview-container">
                                    <object data="`+pdfUrl+`" type="application/pdf">
                                        <iframe src="`+pdfUrl+`">
                                            <p>Your browser does not support PDFs. <a href="`+obj.file_url+`">Download the PDF</a>.</p>
                                        </iframe>
                                    </object>
                                </div>
                            `;
                            break;
                        case 'image':
                            content = `<img src="`+obj.file_url+`" class="img-responsive">`
                            break;
                        }
                    $('#preview_modal .modal-header h5').html(obj.name);
                    $('#preview_modal .modal-body').html(content);
                    // show modal
                    $('#preview_modal').modal('show');
                }).fail(function(response) {
                    alert(json_strings.translations.preview_failed);
                }).always(function() {
                });
            });

            // Remove content and reset fullscreen when closing modal
            $('#preview_modal').on('hidden.bs.modal', function (e) {
                $(this).removeClass('fullscreen');
                $(this).find('#preview_fullscreen_toggle i').removeClass('fa-compress').addClass('fa-expand');
                $(this).find('.modal-body').html('');
            })
        });
    };
})();
