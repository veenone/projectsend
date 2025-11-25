(function () {
    'use strict';

    admin.parts.publicLinksPopup = function () {
        $(document).ready(function () {
            /**
             * Modal: show a public file's URL
             */
            $('body').on('click', '.public_link', function (e) {
                e.preventDefault();
                var type = $(this).data('type');
                var file_title = $(this).data('title');
                var public_url = $(this).data('public-url');

                if (type == 'group') {
                    var note_text = json_strings.translations.public_group_note;
                } else if (type == 'file') {
                    var note_text = json_strings.translations.public_file_note;
                }

                var modal_content = `
                <div class="public_link_modal">
                    <p>` + file_title + `</p>
                    <div class="">
                        <textarea class="input-large form-control" rows="4" readonly>` + public_url + `</textarea>
                        <button class="public_link_copy btn btn-primary" data-copy-text="` + public_url + `">
                            <i class="fa fa-files-o" aria-hidden="true"></i> ` + json_strings.translations.click_to_copy + `
                        </button>
                    </div>
                    <p class="note">` + note_text + `</p>
                </div>`;

                Swal.fire({
                    title: json_strings.translations.public_url,
                    html: modal_content,
                    showCloseButton: true,
                    showCancelButton: false,
                    showConfirmButton: false,
                    allowOutsideClick: true,
                    allowEscapeKey: true
                });
            });

            /** Used on the public link modal on both manage files and the upload results */
            $(document).on('click', '.public_link_copy', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var text = $(this).data('copy-text');
                copyTextToClipboard(text);
            });
        });
    };
})();