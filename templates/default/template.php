<?php
/*
Template name: Default
URI: https://www.projectsend.org/templates/default
Author: ProjectSend
Author URI: https://www.projectsend.org/
Author e-mail: contact@projectsend.org
Description: The default template uses the same style as the system backend, allowing for a seamless user experience
*/
$ld = 'cftp_template'; // specify the language domain for this template

define('TEMPLATE_RESULTS_PER_PAGE', get_option('pagination_results_per_page'));
define('TEMPLATE_THUMBNAILS_WIDTH', '50');
define('TEMPLATE_THUMBNAILS_HEIGHT', '50');

// Handle POST actions (delete, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    // Verify CSRF token
    if (!validateCsrfToken()) {
        $flash->error(__('Invalid security token. Please try again.', 'cftp_admin'));
    } else {
        switch ($_POST['action']) {
            case 'delete':
                if (!empty($_POST['files']) && (current_user_can('delete_files') || current_user_can('delete_others_files'))) {
                    $delete_results = ['success' => 0, 'errors' => 0];
                    foreach ($_POST['files'] as $file_id) {
                        $file = new \ProjectSend\Classes\Files($file_id);
                        if ($file->currentUserCanDelete()) {
                            $result = $file->deleteFiles();
                            if ($result['status'] === 'success') {
                                $delete_results['success']++;
                            } else {
                                $delete_results['errors']++;
                            }
                        } else {
                            $delete_results['errors']++;
                        }
                    }
                    if ($delete_results['success'] > 0) {
                        $flash->success(sprintf(__('%d file(s) deleted successfully.', 'cftp_admin'), $delete_results['success']));
                    }
                    if ($delete_results['errors'] > 0) {
                        $flash->error(sprintf(__('%d file(s) could not be deleted.', 'cftp_admin'), $delete_results['errors']));
                    }
                    // Redirect to avoid form resubmission
                    ps_redirect(CLIENT_VIEW_FILE_LIST_URL);
                }
                break;
        }
    }
}

$filter_by_category = isset($_GET['category']) ? $_GET['category'] : null;

$current_url = get_form_action_with_existing_parameters('index.php');

include_once ROOT_DIR . '/templates/common.php'; // include the required functions for every template

$window_title = __('File downloads', 'cftp_template');

$page_id = 'default_template';

$body_class = array('template', 'default-template', 'hide_title');

// Flash errors
if (!$count) {
    if (isset($no_results_error)) {
        switch ($no_results_error) {
            case 'search':
                $flash->error(__('Your search keywords returned no results.', 'cftp_admin'));
                break;
            case 'filter':
                $flash->error(__('The filters you selected returned no results.', 'cftp_admin'));
                break;
        }
    } else {
        $flash->warning(__('There are no files available.', 'cftp_admin'));
    }
}

// Header buttons
if (current_user_can_upload()) {
    $upload_url = BASE_URI.'upload.php';
    if (!empty($current_folder)) {
        $upload_url .= '?folder_id=' . $current_folder;
    }
    $header_action_buttons = [
        [
            'url' => $upload_url,
            'label' => __('Upload file', 'cftp_admin'),
        ],
    ];
}

// Search + filters bar data
$search_form_action = 'index.php';
$filters_form = [
    'action' => '',
    'items' => [],
];

if (!empty($cat_ids)) {
    $selected_parent = (isset($_GET['category'])) ? [$_GET['category']] : [];
    $category_filter = [];
    $generate_categories_options = generate_categories_options($get_categories['arranged'], 0, $selected_parent, 'include', $cat_ids);
    $format_categories_options = format_categories_options($generate_categories_options);
    foreach ($format_categories_options as $key => $category) {
        $category_filter[$category['id']] = $category['label'];
    }
    $filters_form['items']['category'] = [
        'current' => (isset($_GET['category'])) ? $_GET['category'] : null,
        'placeholder' => [
            'value' => '0',
            'label' => __('All categories', 'cftp_admin')
        ],
        'options' => $category_filter,
    ];
}

// Results count and form actions
$elements_found_count = (isset($count_for_pagination)) ? $count_for_pagination : 0;
$bulk_actions_items = [
    'none' => __('Select action', 'cftp_admin'),
    'zip' => __('Download zipped', 'cftp_admin'),
];

// Add delete option if user has permission
if (current_user_can('delete_files') || current_user_can('delete_others_files')) {
    $bulk_actions_items['delete'] = __('Delete', 'cftp_admin');
}

// Include layout files
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

include_once LAYOUT_DIR . DS . 'search-filters-bar.php';

include_once LAYOUT_DIR . DS . 'breadcrumbs.php';

include_once LAYOUT_DIR . DS . 'folders-nav.php';

?>
<form action="" name="files_list" method="post" class="form-inline batch_actions">
    <?php addCsrf(); ?>
    <div class="row">
        <div class="col-12">
            <?php include_once LAYOUT_DIR . DS . 'form-counts-actions.php'; ?>

            <?php
            if (isset($count) && $count > 0) {
                // Generate the table using the class.
                $table = new \ProjectSend\Classes\Layout\Table([
                    'id' => 'files_list',
                    'class' => 'footable table',
                    'origin' => CLIENT_VIEW_FILE_LIST_URL_PATH,
                ]);

                $thead_columns = array(
                    array(
                        'select_all' => true,
                        'attributes' => array(
                            'class' => array('td_checkbox'),
                        ),
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'filename',
                        'content' => __('Title', 'cftp_admin'),
                    ),
                    array(
                        'content' => __('Type', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'description',
                        'content' => __('Description', 'cftp_admin'),
                        'hide' => 'phone',
                        'attributes' => array(
                            'class' => array('description'),
                        ),
                    ),
                    array(
                        'content' => __('Size', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'timestamp',
                        'sort_default' => true,
                        'content' => __('Date', 'cftp_admin'),
                    ),
                    array(
                        'content' => __('Expiry', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'content' => __('Preview', 'cftp_admin'),
                        'hide' => 'phone,tablet',
                    ),
                    array(
                        'content' => __('Download', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'content' => __('Actions', 'cftp_admin'),
                        'hide' => 'phone',
                        'condition' => (current_user_can('delete_files') || current_user_can('delete_others_files')),
                    ),
                );

                $table->thead($thead_columns);

                foreach ($available_files as $file_id) {
                    $file = new \ProjectSend\Classes\Files($file_id);

                    $table->addRow();

                    /**
                     * Prepare the information to be used later on the cells array
                     */

                    /** Checkbox */
                    $checkbox = ($file->expired == false) ? '<input type="checkbox" name="files[]" value="' . $file->id . '" class="batch_checkbox" />' : null;

                    /** File title - click to show metadata */
                    $file_title_content = '<strong>' . html_output($file->title) . '</strong>';
                    $title_content = '<a href="#" class="get-file-info" data-file-id="' . $file->id . '">' . $file_title_content . '</a>';
                    if ($file->title != $file->filename_original) {
                        $title_content .= '<br><small>'.$file->filename_original.'</small>';
                    }
                    if (file_is_image($file->full_path)) {
                        $dimensions = $file->getDimensions();
                        if (!empty($dimensions)) {
                            $title_content .= '<br><div class="file_meta"><small>'.$dimensions['width'].' x '.$dimensions['height'].' px</small></div>';
                        }
                    }



                    /** Extension */
                    $extension_cell = '<span class="badge bg-success label_big">' . $file->extension . '</span>';

                    /** Date */
                    $date = format_date($file->uploaded_date);

                    /** Expiration */
                    if ($file->expires == '1') {
                        if ($file->expired == false) {
                            $badge_class = 'bg-primary';
                        } else {
                            $badge_class = 'bg-danger';
                        }

                        $badge_label = date(get_option('timeformat'), strtotime($file->expiry_date));
                    } else {
                        $badge_class = 'bg-success';
                        $badge_label = __('Never', 'cftp_template');
                    }

                    $expiration_cell = '<span class="badge ' . $badge_class . ' label_big">' . $badge_label . '</span>';

                    /** Thumbnail */
                    $preview_cell = '';
                    if ($file->expired == false) {
                        if ($file->isImage()) {
                            $thumbnail = make_thumbnail($file->full_path, null, TEMPLATE_THUMBNAILS_WIDTH, TEMPLATE_THUMBNAILS_HEIGHT);
                            if (!empty($thumbnail['thumbnail']['url'])) {
                                $preview_cell = '
                                        <a href="#" class="get-preview" data-url="' . BASE_URI . 'process.php?do=get_preview&file_id=' . $file->id . '">
                                            <img src="' . $thumbnail['thumbnail']['url'] . '" class="thumbnail" alt="' . $file->title . '" />
                                        </a>';
                            }
                        } else {
                            if ($file->embeddable) {
                                $preview_cell = '<button class="btn btn-warning btn-sm btn-wide get-preview" data-url="' . BASE_URI . 'process.php?do=get_preview&file_id=' . $file->id . '">' . __('Preview', 'cftp_admin') . '</button>';
                            }
                        }
                    }

                    /** Download */
                    if ($file->expired == true) {
                        $download_link = 'javascript:void(0);';
                        $download_btn_class = 'btn btn-danger btn-sm disabled';
                        $download_text = __('File expired', 'cftp_template');
                    } else {
                        $download_btn_class = 'btn btn-primary btn-sm btn-wide';
                        $download_text = __('Download', 'cftp_template');
                    }
                    $download_cell = '<a href="' . $file->download_link . '" class="' . $download_btn_class . '" target="_blank">' . $download_text . '</a>';



                    $tbody_cells = array(
                        array(
                            'content' => $checkbox,
                        ),
                        array(
                            'content' => $title_content,
                            'attributes' => array(
                                'class' => array('file_name'),
                            ),
                        ),
                        array(
                            'content' => $extension_cell,
                            'attributes' => array(
                                'class' => array('extra'),
                            ),
                        ),
                        array(
                            'content' => $file->description,
                            'attributes' => array(
                                'class' => array('description'),
                            ),
                        ),
                        array(
                            'content' => $file->size_formatted,
                        ),
                        array(
                            'content' => $date,
                        ),
                        array(
                            'content' => $expiration_cell,
                        ),
                        array(
                            'content' => $preview_cell,
                            'attributes' => array(
                                'class' => array('extra'),
                            ),
                        ),
                        array(
                            'content' => $download_cell,
                            'attributes' => array(
                                'class' => array('text-center'),
                            ),
                        ),
                        array(
                            'content' => '<button type="button" class="btn btn-danger btn-sm delete-file-btn" data-file-id="' . $file->id . '" data-file-name="' . html_output($file->title) . '" title="' . __('Delete file', 'cftp_admin') . '"><i class="fa fa-trash"></i></button>',
                            'condition' => $file->currentUserCanDelete(),
                            'attributes' => array(
                                'class' => array('text-center'),
                            ),
                        ),
                    );

                    foreach ($tbody_cells as $cell) {
                        $table->addCell($cell);
                    }

                    $table->end_row();
                }

                echo $table->render();
            }
            ?>
        </div>
    </div>
</form>
<?php
    if (!empty($table)) {
        // PAGINATION
        $pagination = new \ProjectSend\Classes\Layout\Pagination;
        echo $pagination->make([
            'link' => 'my_files/index.php',
            'current' => $pagination_page,
            'item_count' => $count_for_pagination,
            'items_per_page' => TEMPLATE_RESULTS_PER_PAGE,
        ]);
    }

render_footer_text();

render_json_variables();

render_assets('js', 'footer');
render_assets('css', 'footer');

render_custom_assets('body_bottom');

// Add delete button handler if user has delete permission
if (current_user_can('delete_files') || current_user_can('delete_others_files')) {
?>
<script>
document.addEventListener('click', function(e) {
    if (e.target.closest('.delete-file-btn')) {
        const btn = e.target.closest('.delete-file-btn');
        const fileId = btn.getAttribute('data-file-id');
        const fileName = btn.getAttribute('data-file-name');

        if (confirm('<?php echo __('Are you sure you want to delete this file?', 'cftp_admin'); ?>\n\n' + fileName)) {
            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;

            // Add CSRF token
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = document.querySelector('input[name="csrf_token"]')?.value || '';
            form.appendChild(csrfInput);

            // Add action
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            form.appendChild(actionInput);

            // Add file ID
            const fileInput = document.createElement('input');
            fileInput.type = 'hidden';
            fileInput.name = 'files[]';
            fileInput.value = fileId;
            form.appendChild(fileInput);

            document.body.appendChild(form);
            form.submit();
        }
    }
});
</script>
<?php
}
?>

<!-- File Info Modal -->
<div id="file_info_modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="fileInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fileInfoModalLabel"><?php _e('File Information', 'cftp_admin'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php _e('Close', 'cftp_admin'); ?>"></button>
            </div>
            <div class="modal-body">
                <div id="file_info_loading" class="text-center py-4">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2"><?php _e('Loading...', 'cftp_admin'); ?></p>
                </div>
                <div id="file_info_content" style="display: none;">
                    <table class="table table-striped">
                        <tbody>
                            <tr>
                                <th style="width: 30%;"><?php _e('Title', 'cftp_admin'); ?></th>
                                <td id="info_title"></td>
                            </tr>
                            <tr>
                                <th><?php _e('Filename', 'cftp_admin'); ?></th>
                                <td id="info_filename"></td>
                            </tr>
                            <tr id="info_description_row">
                                <th><?php _e('Description', 'cftp_admin'); ?></th>
                                <td id="info_description"></td>
                            </tr>
                            <tr>
                                <th><?php _e('File Type', 'cftp_admin'); ?></th>
                                <td id="info_type"></td>
                            </tr>
                            <tr>
                                <th><?php _e('File Size', 'cftp_admin'); ?></th>
                                <td id="info_size"></td>
                            </tr>
                            <tr>
                                <th><?php _e('Upload Date', 'cftp_admin'); ?></th>
                                <td id="info_date"></td>
                            </tr>
                            <tr>
                                <th><?php _e('Uploaded By', 'cftp_admin'); ?></th>
                                <td id="info_uploader"></td>
                            </tr>
                            <tr>
                                <th><?php _e('Expires', 'cftp_admin'); ?></th>
                                <td id="info_expiry"></td>
                            </tr>
                        </tbody>
                    </table>
                    <div id="info_s3_section" style="display: none;">
                        <h6 class="mt-4 mb-3"><?php _e('Document Metadata', 'cftp_admin'); ?></h6>
                        <table class="table table-striped table-sm">
                            <tbody id="info_s3_metadata"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php _e('Close', 'cftp_admin'); ?></button>
                <a href="#" id="info_download_btn" class="btn btn-primary" target="_blank">
                    <i class="fa fa-download"></i> <?php _e('Download', 'cftp_admin'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInfoModal = new bootstrap.Modal(document.getElementById('file_info_modal'));
    const fileInfoLoading = document.getElementById('file_info_loading');
    const fileInfoContent = document.getElementById('file_info_content');
    const downloadBtn = document.getElementById('info_download_btn');

    // Metadata key labels
    const metadataLabels = {
        'created-by': '<?php _e('Created By', 'cftp_admin'); ?>',
        'created-by-email': '<?php _e('Creator Email', 'cftp_admin'); ?>',
        'created-by-title': '<?php _e('Creator Title', 'cftp_admin'); ?>',
        'modified-by': '<?php _e('Modified By', 'cftp_admin'); ?>',
        'modified-date': '<?php _e('Modified Date', 'cftp_admin'); ?>',
        'is-versioned': '<?php _e('Versioned', 'cftp_admin'); ?>',
        'is-current-version': '<?php _e('Current Version', 'cftp_admin'); ?>',
        'version-id': '<?php _e('Version ID', 'cftp_admin'); ?>',
        'content-type': '<?php _e('Content Type', 'cftp_admin'); ?>',
        'sharepoint-url': '<?php _e('SharePoint URL', 'cftp_admin'); ?>'
    };

    // Handle file info click
    document.addEventListener('click', function(e) {
        if (e.target.closest('.get-file-info')) {
            e.preventDefault();
            const link = e.target.closest('.get-file-info');
            const fileId = link.getAttribute('data-file-id');
            openFileInfoModal(fileId);
        }
    });

    function openFileInfoModal(fileId) {
        // Show modal with loading state
        fileInfoLoading.style.display = 'block';
        fileInfoContent.style.display = 'none';
        fileInfoModal.show();

        // Fetch file metadata
        fetch('<?php echo BASE_URI; ?>process.php?do=get_file_metadata&file_id=' + fileId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Populate basic info
                    document.getElementById('info_title').textContent = data.title || '-';
                    document.getElementById('info_filename').textContent = data.filename || '-';
                    document.getElementById('info_description').textContent = data.description || '-';
                    document.getElementById('info_type').textContent = data.type || '-';
                    document.getElementById('info_size').textContent = data.size || '-';
                    document.getElementById('info_date').textContent = data.upload_date || '-';
                    document.getElementById('info_uploader').textContent = data.uploader || '-';
                    document.getElementById('info_expiry').textContent = data.expiry || '-';

                    // Hide description row if empty
                    const descRow = document.getElementById('info_description_row');
                    descRow.style.display = (!data.description || data.description === '') ? 'none' : '';

                    // Set download link
                    downloadBtn.href = data.download_link || '#';

                    // Populate S3 metadata if available
                    const s3Section = document.getElementById('info_s3_section');
                    const s3Container = document.getElementById('info_s3_metadata');
                    s3Container.innerHTML = '';

                    if (data.s3_metadata && Object.keys(data.s3_metadata).length > 0) {
                        s3Section.style.display = 'block';

                        Object.keys(data.s3_metadata).forEach(key => {
                            const label = metadataLabels[key] || formatMetadataKey(key);
                            const value = formatMetadataValue(key, data.s3_metadata[key]);
                            const row = document.createElement('tr');
                            row.innerHTML = '<th style="width: 30%;">' + label + '</th><td>' + value + '</td>';
                            s3Container.appendChild(row);
                        });
                    } else {
                        s3Section.style.display = 'none';
                    }

                    // Show content, hide loading
                    fileInfoLoading.style.display = 'none';
                    fileInfoContent.style.display = 'block';
                } else {
                    fileInfoModal.hide();
                    alert('<?php _e('Failed to load file information', 'cftp_admin'); ?>');
                }
            })
            .catch(error => {
                console.error('Error fetching file metadata:', error);
                fileInfoModal.hide();
                alert('<?php _e('Failed to load file information', 'cftp_admin'); ?>');
            });
    }

    function formatMetadataKey(key) {
        return key.split('-').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
    }

    function formatMetadataValue(key, value) {
        if (value === null || value === undefined) return '-';
        if (typeof value === 'boolean') return value ? '<?php _e('Yes', 'cftp_admin'); ?>' : '<?php _e('No', 'cftp_admin'); ?>';
        if (key.includes('url') && typeof value === 'string' && value.startsWith('http')) {
            return '<a href="' + value + '" target="_blank" class="text-truncate d-inline-block" style="max-width: 300px;">' + value + '</a>';
        }
        return String(value);
    }
});
</script>
</body>

</html>