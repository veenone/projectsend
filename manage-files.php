<?php

/**
 * Allows to hide, show or delete the files assigned to the
 * selected client.
 */
require_once 'bootstrap.php';
// This page should be accessible to anyone who can view files
redirect_if_not_logged_in();
// Both clients and users can access their own files

$active_nav = 'files';

$page_title = __('Manage files', 'cftp_admin');

$page_id = 'manage_files';

$current_url = get_form_action_with_existing_parameters(basename(__FILE__), array('modify_id', 'modify_type'));

// Handle view preference with cookie
$view_mode = 'cards'; // default
$cookie_name = 'projectsend_manage_files_view_preference';

// Check if view parameter is in URL
if (isset($_GET['view']) && in_array($_GET['view'], ['table', 'cards'])) {
    $view_mode = $_GET['view'];
    // Save preference in cookie (expires in 30 days)
    setcookie($cookie_name, $view_mode, time() + (30 * 24 * 60 * 60), '/');
} elseif (isset($_COOKIE[$cookie_name]) && in_array($_COOKIE[$cookie_name], ['table', 'cards'])) {
    // Use cookie preference if no URL parameter
    $view_mode = $_COOKIE[$cookie_name];
}

if (current_role_in(['Client'])) {
    if (count_user_uploads(CURRENT_USER_ID) == 0 && !current_user_can('upload')) {
        exit_with_error_code(403);
    }
}

/**
 * Used to distinguish the current page results.
 * Global means all files.
 * Client or group is only when looking into files
 * assigned to any of them.
 */
$results_type = 'global';

/**
 * The client's id is passed on the URI.
 * Then get_client_by_id() gets all the other account values.
 */
if (isset($_GET['client'])) {
    if (!is_numeric($_GET['client'])) {
       exit_with_error_code(403);
    }

    $this_id = (int)$_GET['client'];
    $this_client = get_client_by_id($this_id);

    /** Add the name of the client to the page's title. */
    if (!empty($this_client)) {
        $page_title .= ' ' . __('for client', 'cftp_admin') . ' ' . html_entity_decode($this_client['name']);
        $search_on = 'client_id';
        $results_type = 'client';
    }
}

// The group's id is passed on the URI also
if (isset($_GET['group'])) {
    $this_id = $_GET['group'];
    $group = get_group_by_id($this_id);

    // Add the name of the client to the page's title.
    if (!empty($group['name'])) {
        $page_title .= ' ' . __('for group', 'cftp_admin') . ' ' . html_entity_decode($group['name']);
        $search_on = 'group_id';
        $results_type = 'group';
    }
}

// Filtering by category
if (isset($_GET['category'])) {
    $this_id = $_GET['category'];
    $this_category = get_category($this_id);

    // Add the name of the client to the page's title.
    if (!empty($this_category)) {
        $page_title .= ' ' . __('on category', 'cftp_admin') . ' ' . html_entity_decode($this_category['name']);
        $results_type = 'category';
    }
}

// Setting the filter options to avoid duplicates
$filter_options_uploader = array(
    '0' => __('Uploader', 'cftp_admin'),
);
$sql_uploaders = $dbh->prepare("SELECT uploader FROM " . TABLE_FILES . " GROUP BY uploader");
$sql_uploaders->execute();
$sql_uploaders->setFetchMode(PDO::FETCH_ASSOC);
while ($data_uploaders = $sql_uploaders->fetch()) {
    $filter_options_uploader[$data_uploaders['uploader']] = $data_uploaders['uploader'];
}

$filter_options_assigned = array(
    '0' => __('All files', 'cftp_admin'),
    'assigned' => __('Assigned', 'cftp_admin'),
    'not_assigned' => __('Not assigned', 'cftp_admin'),
);

// Apply the corresponding action to the selected files.
if (isset($_POST['action'])) {
    if (!empty($_POST['batch'])) {
        $selected_files = array_map('intval', array_unique($_POST['batch']));

        switch ($_POST['action']) {
            case 'hide':
                /**
                 * Changes the value on the "hidden" column value on the database.
                 * This files are not shown on the client's file list. They are
                 * also not counted on the dashboard.php files count when the logged in
                 * account is the client.
                 */
                foreach ($selected_files as $file_id) {
                    $file = new \ProjectSend\Classes\Files($file_id);
                    $file->hide($results_type, $_POST['modify_id']);
                }

                $flash->success(__('The selected files were marked as hidden.', 'cftp_admin'));
                break;
            case 'show':
                foreach ($selected_files as $file_id) {
                    $file = new \ProjectSend\Classes\Files($file_id);
                    $file->show($results_type, $_POST['modify_id']);
                }

                $flash->success(__('The selected files were marked as visible.', 'cftp_admin'));
                break;
            case 'hide_everyone':
                foreach ($selected_files as $file_id) {
                    $file = new \ProjectSend\Classes\Files($file_id);
                    $file->hideFromEveryone();
                }

                $flash->success(__('The selected files were marked as hidden.', 'cftp_admin'));
                break;
            case 'show_everyone':
                foreach ($selected_files as $file_id) {
                    $file = new \ProjectSend\Classes\Files($file_id);
                    $file->showToEveryone();
                }

                $flash->success(__('The selected files were marked as visible.', 'cftp_admin'));
                break;
            case 'unassign':
                // Remove the file from this client or group only.
                foreach ($selected_files as $file_id) {
                    $file = new \ProjectSend\Classes\Files($file_id);
                    $file->removeAssignment($results_type, $_POST['modify_id']);
                }

                $flash->success(__('The selected files were successfully unassigned.', 'cftp_admin'));
                break;
            case 'delete':
                $delete_results    = array(
                    'success' => 0,
                    'errors' => 0,
                );
                foreach ($selected_files as $index => $file_id) {
                    if (!empty($file_id)) {
                        $file = new \ProjectSend\Classes\Files($file_id);
                        $result = $file->deleteFiles();
                        if ($result['status'] === 'success') {
                            $delete_results['success']++;
                        } else {
                            $delete_results['errors']++;
                        }
                    }
                }

                // Invalidate file count cache after deletions
                if ($delete_results['success'] > 0) {
                    invalidate_file_count_cache();
                    $flash->success(__('The selected files were deleted.', 'cftp_admin'));
                }
                if ($delete_results['errors'] > 0) {
                    $flash->error(__('Some files could not be deleted.', 'cftp_admin'));
                }
                break;
            case 'edit':
                ps_redirect(BASE_URI . 'files-edit.php?ids=' . implode(',', $selected_files));
                break;
        }
    } else {
        $flash->error(__('Please select at least one file.', 'cftp_admin'));
    }

    ps_redirect($current_url);
}

// Global form action
$query_table_files = true;

// Performance monitoring for large datasets
$perf_start_time = microtime(true);
$perf_metrics = [];

function perf_log($label) {
    global $perf_start_time, $perf_metrics;
    $current_time = microtime(true);
    $elapsed = $current_time - $perf_start_time;
    $perf_metrics[$label] = round($elapsed * 1000, 2); // Convert to milliseconds
    return $elapsed;
}

// Folders
$current_folder = (isset($_GET['folder_id'])) ? (int)$_GET['folder_id'] : null;
$folders_arguments = [
    'parent' => $current_folder
];
if (!empty($_GET['search'])) {
    $folders_arguments['search'] = $_GET['search'];
}
if (current_role_in(['Client', 'Internal User'])) {
    if (current_user_can('upload_public')) {
        $folders_arguments['public_or_client'] = true;
        $folders_arguments['client_id'] = CURRENT_USER_ID;
    } else {
        $folders_arguments['user_id'] = CURRENT_USER_ID;
    }
}
// @todo DECIDE WHICH FOLDERS TO GET IF VIEWING FILES BY CLIENT, GROUP OR CATEGORY
// if ($filter_by_client) {
//     $folders_arguments['client'] = $_GET['client_id'];
// }
// if ($filter_by_group) {
//     $folders_arguments['group'] = $_GET['group_id'];
// }

$folders_obj = new \ProjectSend\Classes\Folders;
$folders = $folders_obj->getFolders($folders_arguments);

// Get folder tree for sidebar navigation - use same filtering as folders_arguments
$folder_tree_arguments = [];
if (current_role_in(['Client', 'Internal User'])) {
    // Always pass user_id and client_id for proper role detection and permission filtering
    $folder_tree_arguments['user_id'] = CURRENT_USER_ID;
    $folder_tree_arguments['client_id'] = CURRENT_USER_ID;
    if (current_user_can('upload_public')) {
        $folder_tree_arguments['public_or_client'] = true;
    }
} elseif (!current_user_can('edit_others_files')) {
    // For users without edit_others_files permission, only count their own files
    $folder_tree_arguments['owner_user_id'] = CURRENT_USER_ID;
}
$folder_tree = $folders_obj->getFolderTree($folder_tree_arguments, $current_folder);

// Get files
if (isset($search_on)) {
    $params = [];
    $rq = "SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE $search_on = :id";
    $params[':id'] = $this_id;

    // Add the status filter
    if (isset($_GET['hidden']) && $_GET['hidden'] != 'all') {
        $set_and = true;
        $rq .= " AND hidden = :hidden";
        $no_results_error = 'filter';

        $params[':hidden'] = $_GET['hidden'];
    }

    // Count the files assigned to this client. If there is none, show an error message.
    $sql = $dbh->prepare($rq);
    $sql->execute($params);

    if ($sql->rowCount() > 0) {
        // Get the IDs of files that match the previous query.
        $sql->setFetchMode(PDO::FETCH_ASSOC);
        while ($row_files = $sql->fetch()) {
            $files_ids[] = $row_files['file_id'];
            $gotten_files = implode(',', $files_ids);
        }
    } else {
        $count = 0;
        $no_results_error = 'filter';
        $query_table_files = false;
    }
}

if ($query_table_files === true) {
    // Get the files
    $params = [];

    /**
     * Add the download count to the main query.
     * If the page is filtering files by client, then
     * add the client ID to the subquery.
     */
    $add_user_to_query = '';
    if (isset($search_on) && $results_type == 'client') {
        $add_user_to_query = "AND user_id = :user_id";
        $params[':user_id'] = $this_id;
    }
    // For large datasets, we'll use separate COUNT query instead of SQL_CALC_FOUND_ROWS
    // This is more efficient as SQL_CALC_FOUND_ROWS scans all rows even with LIMIT
    $cq = "SELECT files.*, ( SELECT COUNT(file_id) FROM " . TABLE_DOWNLOADS . " WHERE " . TABLE_DOWNLOADS . ".file_id=files.id " . $add_user_to_query . ") as download_count FROM " . TABLE_FILES . " files";

    if (isset($search_on) && !empty($gotten_files)) {
        $conditions[] = "FIND_IN_SET(id, :files)";
        $params[':files'] = $gotten_files;
    }

    // Add the search terms
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $conditions[] = "(filename LIKE :name OR description LIKE :description)";
        $no_results_error = 'search';

        $search_terms = '%' . $_GET['search'] . '%';
        $params[':name'] = $search_terms;
        $params[':description'] = $search_terms;
    }

    // Filter by uploader
    if (isset($_GET['uploader']) && !empty($_GET['uploader'])) {
        $conditions[] = "uploader = :uploader";
        $no_results_error = 'filter';

        $params[':uploader'] = $_GET['uploader'];
    }

    // Filter by folders
    if (!empty($current_folder)) {
        $conditions[] = "folder_id = :folder_id";
        $params[':folder_id'] = $current_folder;
    } else {
        $conditions[] = "folder_id IS NULL";
    }

    // Filter by ownership if user doesn't have edit_others_files permission
    if (!current_user_can('edit_others_files')) {
        // Only show files uploaded by the current user
        $conditions[] = "user_id = :owner_user_id";
        $params[':owner_user_id'] = CURRENT_USER_ID;
    }

    // Filter by assignations
    if (isset($_GET['assigned']) && !empty($_GET['assigned'])) {
        if (array_key_exists($_GET['assigned'], $filter_options_assigned)) {
            $assigned_files_id = [];
            $statement = $dbh->prepare("SELECT DISTINCT file_id FROM " . TABLE_FILES_RELATIONS);
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            while ($file_data = $statement->fetch()) {
                $assigned_files_id[] = $file_data['file_id'];
            }
            $assigned_files_id = implode(',', $assigned_files_id);

            // Overwrite the parameter set previously
            $pre = ($_GET['assigned'] == 'not_assigned') ? 'NOT ' : '';
            $conditions[] = $pre . "FIND_IN_SET(id, :files)";
            $params[':files'] = $assigned_files_id;
        }
    }

    /**
     * If the user is an uploader, or a client is editing their files
     * only show files uploaded by that account.
     */
    if (current_role_in(['Client', 'Uploader'])) {
        $conditions[] = "uploader = :uploader";
        $no_results_error = 'account_level';

        $params[':uploader'] = CURRENT_USER_USERNAME;
    }

    // Add the category filter
    if (isset($results_type) && $results_type == 'category') {
        $files_id_by_cat = [];
        $statement = $dbh->prepare("SELECT file_id FROM " . TABLE_CATEGORIES_RELATIONS . " WHERE cat_id = :cat_id");
        $statement->bindParam(':cat_id', $this_category['id'], PDO::PARAM_INT);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        while ($file_data = $statement->fetch()) {
            $files_id_by_cat[] = $file_data['file_id'];
        }
        $files_id_by_cat = implode(',', $files_id_by_cat);

        // Overwrite the parameter set previously
        $conditions[] = "FIND_IN_SET(id, :files)";
        $params[':files'] = $files_id_by_cat;

        $no_results_error = 'category';
    }

    // Build the final query
    if (!empty($conditions)) {
        foreach ($conditions as $index => $condition) {
            $cq .= ($index == 0) ? ' WHERE ' : ' AND ';
            $cq .= $condition;
        }
    }

    /**
     * Add the order.
     * Defaults to order by: date, order: ASC
     */
    $cq .= sql_add_order(TABLE_FILES, 'timestamp', 'desc');

    // Performance optimization: Use keyset pagination for large offsets (experimental)
    // Keyset pagination is faster than OFFSET for large datasets
    // Format: ?keyset_id=123&keyset_dir=next
    $use_keyset = isset($_GET['keyset_id']) && is_numeric($_GET['keyset_id']);

    if ($use_keyset) {
        $keyset_id = (int)$_GET['keyset_id'];
        $keyset_dir = isset($_GET['keyset_dir']) && $_GET['keyset_dir'] === 'prev' ? 'prev' : 'next';

        // Add keyset condition to WHERE clause
        if ($keyset_dir === 'next') {
            // Get files with ID < keyset_id (for descending order by timestamp)
            $conditions[] = "id < :keyset_id";
        } else {
            // Get files with ID > keyset_id (for previous page)
            $conditions[] = "id > :keyset_id";
        }
        $params[':keyset_id'] = $keyset_id;

        // No OFFSET needed with keyset pagination
        $cq .= " LIMIT :limit_number";
    } else {
        // Traditional offset pagination
        $cq .= " LIMIT :limit_start, :limit_number";
    }
    $sql = $dbh->prepare($cq);

    // Handle per page override via URL parameter
    $results_per_page = get_option('pagination_results_per_page');
    if (isset($_GET['per_page']) && is_numeric($_GET['per_page']) && $_GET['per_page'] > 0 && $_GET['per_page'] <= 100) {
        $results_per_page = (int)$_GET['per_page'];
    }

    $pagination_page = (isset($_GET["page"])) ? $_GET["page"] : 1;
    $pagination_start = ($pagination_page - 1) * $results_per_page;

    // Performance optimization: Warn about large offsets
    // LIMIT with large offset is slow (MySQL has to scan and skip all rows)
    $large_offset_threshold = 10000;
    if ($pagination_start > $large_offset_threshold) {
        // For very large offsets, show performance warning
        $flash->warning(__('You are viewing a page far from the beginning. Consider using filters or search to narrow down results for better performance.', 'cftp_admin'));
    }

    // Bind non-LIMIT parameters first
    foreach ($params as $key => $value) {
        $sql->bindValue($key, $value);
    }

    // Bind LIMIT parameters as integers
    if ($use_keyset) {
        // Keyset pagination only needs limit_number
        $sql->bindValue(':limit_number', (int)$results_per_page, PDO::PARAM_INT);
    } else {
        // Traditional pagination needs both start and number
        $sql->bindValue(':limit_start', (int)$pagination_start, PDO::PARAM_INT);
        $sql->bindValue(':limit_number', (int)$results_per_page, PDO::PARAM_INT);
    }

    $sql->execute();
    $count = $sql->rowCount();
    perf_log('query_execute');

    // Get total count using optimized method with caching for large datasets
    // Build cache key based on query parameters
    $cache_key_parts = [
        isset($search_on) ? $search_on : 'all',
        isset($this_id) ? $this_id : 'none',
        isset($_GET['search']) ? $_GET['search'] : '',
        isset($_GET['uploader']) ? $_GET['uploader'] : '',
        isset($_GET['hidden']) ? $_GET['hidden'] : '',
        $current_folder ?? 'null',
    ];
    $cache_key = implode('_', $cache_key_parts);

    // Use cached count for better performance (5 minute TTL)
    $count_for_pagination = get_cached_file_count($cache_key, function() use ($cq, $params, $dbh) {
        return get_optimized_file_count($cq, $params);
    }, 300);

    // Debug output (remove after testing) - commented out
    // error_log("LIMIT DEBUG: start=" . $pagination_start . ", per_page=" . $results_per_page . ", actual_count=" . $count . ", total=" . $count_for_pagination);
} else {
    $count_for_pagination = 0;
}

if (!$count) {
    if (isset($no_results_error)) {
        switch ($no_results_error) {
            case 'search':
                $flash->error(__('Your search keywords returned no results.', 'cftp_admin'));
                break;
            case 'category':
                $flash->error(__('There are no files assigned to this category.', 'cftp_admin'));
                break;
            case 'filter':
                $flash->error(__('The filters you selected returned no results.', 'cftp_admin'));
                break;
            case 'account_level':
                $flash->error(__('You have not uploaded any files yet.', 'cftp_admin'));
                break;
        }
    } else {
        $flash->warning(__('There are no files.', 'cftp_admin'));
    }
}

// Header buttons
if (current_user_can_upload()) {
    $header_action_buttons = [
        [
            'url' => '#',
            'label' => __('New folder', 'cftp_admin'),
            'id' => 'btn_header_folder_create',
            'data-attributes' => [
                'modal-title' => __('New folder', 'cftp_admin'),
                'modal-label' => __('Name', 'cftp_admin'),
                'modal-title-invalid' => __('Name is not valid', 'cftp_admin'),
                'parent' => $current_folder,
                'process-url' => AJAX_PROCESS_URL.'?do=folder_create',
                'folder-url' => BASE_URI.'manage-files.php?folder_id={folder_id}',
            ],
        ],
        [
            'url' => 'upload.php' . (!empty($current_folder) ? '?folder_id=' . $current_folder : ''),
            'label' => __('Upload files', 'cftp_admin'),
        ],
    ];
}

// Search + filters bar data
$search_form_action = 'manage-files.php';
if (!current_role_in(['Client'])) {
    $filters_form = [
        'action' => $current_url,
        'ignore_form_parameters' => ['hidden', 'action', 'uploader', 'assigned'],
    ];
    // Filters are not available for clients
    if ($results_type == 'global') {
        $filters_form['items'] = [
            'uploader' => [
                'current' => (isset($_GET['uploader'])) ? $_GET['uploader'] : null,
                'options' => $filter_options_uploader,
            ],
            'assigned' => [
                'current' => (isset($_GET['assigned'])) ? $_GET['assigned'] : null,
                'options' => $filter_options_assigned,
            ],
        ];
    } else {
        // Filters available when results are only those of a group or client
        $filters_form['items'] = [
            'hidden' => [
                'current' => (isset($_GET['hidden'])) ? $_GET['hidden'] : null,
                'options' => [
                    '2' => __('All statuses', 'cftp_admin'),
                    '0' => __('Visible', 'cftp_admin'),
                    '1' => __('Hidden', 'cftp_admin'),
                ],
            ],
        ];
    }
}

// Results count and form actions 
$elements_found_count = $count_for_pagination;// + count($folders);
$bulk_actions_items = [
    'none' => __('Select action', 'cftp_admin'),
    'edit' => __('Edit', 'cftp_admin'),
];
if (!current_role_in(['Client'])) {
    $bulk_actions_items['zip'] = __('Download zipped', 'cftp_admin');
    if (!isset($search_on)) {
        $bulk_actions_items['hide_everyone'] = __('Set to hidden from everyone already assigned', 'cftp_admin');
        $bulk_actions_items['show_everyone'] = __('Set to visible to everyone already assigned', 'cftp_admin');
    }
}
if (!current_role_in(['Client']) && isset($search_on)) {
    $bulk_actions_items['hide'] = __('Set to hidden', 'cftp_admin');
    $bulk_actions_items['show'] = __('Set to visible', 'cftp_admin');
    $bulk_actions_items['unassign'] = __('Unassign', 'cftp_admin');
}
// Show delete option for users with delete_files or delete_others_files permission
if (current_user_can('delete_files') || current_user_can('delete_others_files')) {
    $bulk_actions_items['delete'] = __('Delete', 'cftp_admin');
}

// Include layout files
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

include_once LAYOUT_DIR . DS . 'search-filters-bar.php';

// Folder tree sidebar variables
$tree_base_url = $current_url;
$tree_context = 'admin';
$tree_id = 'folder-tree';
$show_file_counts = true;
$allow_drag_drop = current_user_can_upload();
?>

<!-- Folder Tree Layout Container -->
<div class="folder-tree-layout">
    <?php
    // Include the folder tree sidebar
    include_once LAYOUT_DIR . DS . 'folder-tree.php';
    ?>

    <!-- Main Content Area -->
    <div class="main-content-area">
        <?php
        // Check if folder buttons should be shown (default: hidden when tree is visible)
        $show_folder_buttons = isset($_COOKIE['show_folder_buttons']) && $_COOKIE['show_folder_buttons'] === 'true';
        include_once LAYOUT_DIR . DS . 'breadcrumbs.php';
        ?>

        <!-- Folder Buttons Toggle & Container -->
        <div class="folder-buttons-wrapper">
            <button type="button" class="btn btn-sm btn-outline-secondary folder-buttons-toggle" id="toggle-folder-buttons" title="<?php _e('Toggle folder buttons view', 'cftp_admin'); ?>">
                <i class="fa <?php echo $show_folder_buttons ? 'fa-th-large' : 'fa-th-large'; ?>"></i>
                <span><?php echo $show_folder_buttons ? __('Hide Folder Buttons', 'cftp_admin') : __('Show Folder Buttons', 'cftp_admin'); ?></span>
            </button>
            <div id="folders-nav-container" class="<?php echo $show_folder_buttons ? '' : 'hidden'; ?>">
                <?php include_once LAYOUT_DIR . DS . 'folders-nav.php'; ?>
            </div>
        </div>

        <script>
        document.getElementById('toggle-folder-buttons').addEventListener('click', function() {
            const container = document.getElementById('folders-nav-container');
            const btn = this;
            const isHidden = container.classList.contains('hidden');

            if (isHidden) {
                container.classList.remove('hidden');
                btn.querySelector('span').textContent = '<?php _e('Hide Folder Buttons', 'cftp_admin'); ?>';
                document.cookie = 'show_folder_buttons=true;path=/;max-age=31536000';
            } else {
                container.classList.add('hidden');
                btn.querySelector('span').textContent = '<?php _e('Show Folder Buttons', 'cftp_admin'); ?>';
                document.cookie = 'show_folder_buttons=false;path=/;max-age=31536000';
            }
        });
        </script>

<form action="<?php echo $current_url; ?>" name="files_list" method="post" class="batch_actions">
    <?php addCsrf(); ?>
    <?php include_once LAYOUT_DIR . DS . 'form-counts-actions.php'; ?>

    <?php if (isset($search_on)) { ?>
        <input type="hidden" name="modify_type" id="modify_type" value="<?php echo $search_on; ?>" />
        <input type="hidden" name="modify_id" id="modify_id" value="<?php echo $this_id; ?>" />
    <?php } ?>


    <!-- Shared Control Bar for Both Views -->
    <?php if ($count_for_pagination > 0): ?>
    <div class="shared-control-bar mb-3">
        <div class="control-bar-left">
            <!-- Select All Button -->
            <button class="select-all-btn" id="shared-select-all" type="button">
                <i class="fa fa-square-o"></i>
                <span><?php _e('Select All', 'cftp_admin'); ?></span>
            </button>

            <?php
            // Shared dropdown component function
            function render_control_dropdown($config) {
                echo '<div class="' . $config['class'] . '">';
                echo '<button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown">';
                echo '<i class="fa ' . $config['icon'] . '"></i> ' . $config['button_text'];
                echo '</button>';
                echo '<ul class="dropdown-menu">';

                foreach ($config['options'] as $option) {
                    $active_class = $option['active'] ? ' active' : '';
                    $icon = isset($option['icon']) ? $option['icon'] : '';
                    echo '<li><a class="dropdown-item' . $active_class . '" href="' . $option['url'] . '">' . $option['label'] . $icon . '</a></li>';
                }

                echo '</ul>';
                echo '</div>';
            }

            // Prepare shared parameters
            $current_params = $_GET;
            $current_orderby = $_GET['orderby'] ?? '';
            $current_order = $_GET['order'] ?? '';

            // Helper function to build sort URL
            $build_sort_url = function($orderby, $order) use ($current_params) {
                $params = $current_params;
                $params['orderby'] = $orderby;
                $params['order'] = $order;
                return 'manage-files.php?' . http_build_query($params);
            };

            // Helper function to build per page URL
            $build_per_page_url = function($per_page) use ($current_params) {
                $params = $current_params;
                $params['per_page'] = $per_page;
                unset($params['page']); // Reset to page 1 when changing per page
                return 'manage-files.php?' . http_build_query($params);
            };

            // Sort dropdown configuration
            $sort_options = [];
            $sort_options[] = ['url' => $build_sort_url('filename', 'asc'), 'label' => __('Title A-Z', 'cftp_admin'), 'active' => $current_orderby == 'filename' && $current_order == 'asc'];
            $sort_options[] = ['url' => $build_sort_url('filename', 'desc'), 'label' => __('Title Z-A', 'cftp_admin'), 'active' => $current_orderby == 'filename' && $current_order == 'desc'];
            $sort_options[] = ['url' => $build_sort_url('timestamp', 'desc'), 'label' => __('Newest first', 'cftp_admin'), 'active' => $current_orderby == 'timestamp' && $current_order == 'desc'];
            $sort_options[] = ['url' => $build_sort_url('timestamp', 'asc'), 'label' => __('Oldest first', 'cftp_admin'), 'active' => $current_orderby == 'timestamp' && $current_order == 'asc'];
            $sort_options[] = ['url' => $build_sort_url('description', 'asc'), 'label' => __('Description A-Z', 'cftp_admin'), 'active' => $current_orderby == 'description' && $current_order == 'asc'];
            $sort_options[] = ['url' => $build_sort_url('description', 'desc'), 'label' => __('Description Z-A', 'cftp_admin'), 'active' => $current_orderby == 'description' && $current_order == 'desc'];
            $sort_options[] = ['url' => $build_sort_url('public_allow', 'desc'), 'label' => __('Public first', 'cftp_admin'), 'active' => $current_orderby == 'public_allow' && $current_order == 'desc'];
            $sort_options[] = ['url' => $build_sort_url('public_allow', 'asc'), 'label' => __('Private first', 'cftp_admin'), 'active' => $current_orderby == 'public_allow' && $current_order == 'asc'];
            $sort_options[] = ['url' => $build_sort_url('expires', 'asc'), 'label' => __('Expiring soon', 'cftp_admin'), 'active' => $current_orderby == 'expires' && $current_order == 'asc'];
            $sort_options[] = ['url' => $build_sort_url('expires', 'desc'), 'label' => __('No expiry first', 'cftp_admin'), 'active' => $current_orderby == 'expires' && $current_order == 'desc'];
            $sort_options[] = ['url' => $build_sort_url('download_count', 'desc'), 'label' => __('Most downloaded', 'cftp_admin'), 'active' => $current_orderby == 'download_count' && $current_order == 'desc'];
            $sort_options[] = ['url' => $build_sort_url('download_count', 'asc'), 'label' => __('Least downloaded', 'cftp_admin'), 'active' => $current_orderby == 'download_count' && $current_order == 'asc'];

            // Add sort direction icons for active items
            foreach ($sort_options as &$option) {
                if ($option['active']) {
                    $option['icon'] = ($current_order == 'asc') ? ' <i class="fa fa-sort-up"></i>' : ' <i class="fa fa-sort-down"></i>';
                }
            }

            // Render Sort Dropdown
            render_control_dropdown([
                'class' => 'sort-dropdown',
                'icon' => 'fa-sort',
                'button_text' => __('Sort', 'cftp_admin'),
                'options' => $sort_options
            ]);

            // Per page dropdown configuration
            $per_page_options = [10, 25, 50, 100];
            $current_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : get_option('pagination_results_per_page');
            $per_page_dropdown_options = [];

            foreach ($per_page_options as $option) {
                $per_page_dropdown_options[] = [
                    'url' => $build_per_page_url($option),
                    'label' => $option . ' ' . __('per page', 'cftp_admin'),
                    'active' => ($current_per_page == $option)
                ];
            }

            // Render Per Page Dropdown
            render_control_dropdown([
                'class' => 'per-page-dropdown',
                'icon' => 'fa-list-ol',
                'button_text' => $results_per_page . ' ' . __('per page', 'cftp_admin'),
                'options' => $per_page_dropdown_options
            ]);
            ?>
        </div>

        <div class="control-bar-right">
            <!-- View Toggle -->
            <div class="view-toggle">
                <div class="btn-group view-toggle-buttons" role="group" aria-label="<?php _e('View toggle', 'cftp_admin'); ?>">
                    <?php
                    // Build URLs preserving other parameters
                    $current_params = $_GET;

                    // Table view URL (set view=table)
                    $table_params = $current_params;
                    $table_params['view'] = 'table';
                    $table_url = basename(__FILE__) . '?' . http_build_query($table_params);

                    // Cards view URL (set view=cards)
                    $cards_params = $current_params;
                    $cards_params['view'] = 'cards';
                    $cards_url = basename(__FILE__) . '?' . http_build_query($cards_params);

                    // Determine active states
                    $table_class = ($view_mode === 'table') ? 'btn-primary' : 'btn-outline-secondary';
                    $cards_class = ($view_mode === 'cards') ? 'btn-primary' : 'btn-outline-secondary';
                    ?>
                    <a href="<?php echo $cards_url; ?>" class="btn btn-sm <?php echo $cards_class; ?>" title="<?php _e('Card view', 'cftp_admin'); ?>">
                        <i class="fa fa-th-large"></i> <?php _e('Cards', 'cftp_admin'); ?>
                    </a>
                    <a href="<?php echo $table_url; ?>" class="btn btn-sm <?php echo $table_class; ?>" title="<?php _e('Table view', 'cftp_admin'); ?>">
                        <i class="fa fa-list"></i> <?php _e('Table', 'cftp_admin'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <?php
                if ($count_for_pagination > 0) {
                    // Use the view mode determined from URL parameter or cookie
                    $use_card_view = ($view_mode === 'cards');

                    if ($use_card_view) {
                        // Generate the modern card list
                        $table = new \ProjectSend\Classes\Layout\CardList([
                            'id' => 'files_list',
                            'class' => 'files-card-list',
                            'origin' => basename(__FILE__),
                        ]);
                    } else {
                        // Generate the table using the class.
                        $table = new \ProjectSend\Classes\Layout\Table([
                            'id' => 'files_tbl',
                            'class' => 'footable table',
                            'origin' => basename(__FILE__),
                        ]);
                    }

                    /**
                     * Set the conditions to true or false once here to
                     * avoid repetition
                     * They will be used to generate or no certain columns
                     */
                    $conditions = array(
                        'select_all' => true,
                        'is_not_client' => !current_role_in(['Client']),
                        'can_set_public' => (!current_role_in(['Client']) || current_user_can_upload_public()),
                        'can_set_expiration' => (!current_role_in(['Client']) || current_user_can('set_file_expiration_date')),
                        'can_set_categories' => (!current_role_in(['Client']) || current_user_can('set_file_categories')),
                        'total_downloads' => (!current_role_in(['Client']) && !isset($search_on)),
                        'is_search_on' => (isset($search_on)) ? true : false,
                    );

                    $thead_columns = array(
                        array(
                            'content' => '',  // Empty column for checkboxes
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'filename',
                            'content' => __('Title', 'cftp_admin'),
                        ),
                        array(
                            'content' => __('Preview', 'cftp_admin'),
                            'hide' => 'phone,tablet',
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'timestamp',
                            'sort_default' => true,
                            'content' => __('Added on', 'cftp_admin'),
                            'hide' => 'phone',
                        ),
                        array(
                            'content' => __('Ext.', 'cftp_admin'),
                            'hide' => 'phone,tablet',
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'description',
                            'content' => __('Description', 'cftp_admin'),
                        ),
                        array(
                            'content' => __('Size', 'cftp_admin'),
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'uploader',
                            'content' => __('Uploader', 'cftp_admin'),
                            'hide' => 'phone,tablet',
                            'condition' => $conditions['is_not_client'],
                        ),
                        array(
                            'content' => __('Assigned', 'cftp_admin'),
                            'hide' => 'phone',
                            'condition' => ($conditions['is_not_client'] && !$conditions['is_search_on']),
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'public_allow',
                            'content' => __('Public permissions', 'cftp_admin'),
                            'hide' => 'phone',
                            'condition' => $conditions['can_set_public'],
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'expires',
                            'content' => __('Expiry', 'cftp_admin'),
                            'hide' => 'phone',
                            'condition' => $conditions['can_set_expiration'],
                        ),
                        array(
                            'sortable' => false,
                            'content' => __('Categories', 'cftp_admin'),
                            'hide' => 'phone',
                            'condition' => $conditions['can_set_categories'],
                        ),
                        array(
                            'content' => __('Status', 'cftp_admin'),
                            'hide' => 'phone',
                            'condition' => $conditions['is_search_on'],
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'download_count',
                            'content' => __('Download count', 'cftp_admin'),
                            'hide' => 'phone',
                            'condition' => $conditions['is_search_on'],
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'download_count',
                            'content' => __('Total downloads', 'cftp_admin'),
                            'hide' => 'phone',
                            'condition' => $conditions['total_downloads'],
                        ),
                        array(
                            'content' => __('Download Limit', 'cftp_admin'),
                            'hide' => 'phone',
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'encrypted',
                            'content' => __('Encryption', 'cftp_admin'),
                            'hide' => 'phone',
                        ),
                        array(
                            'content' => __('Actions', 'cftp_admin'),
                            'hide' => 'phone',
                            'actions' => true, // Mark this as actions column for CardList
                        ),
                    );

                    $table->thead($thead_columns);

                    // Files - Fetch all results for batch processing
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    $files_data = $sql->fetchAll();

                    // Batch load assignments and categories for all files to avoid N+1 query problem
                    $file_ids = array_column($files_data, 'id');
                    perf_log('data_fetch');

                    $all_assignations = get_files_assignations_batch($file_ids);
                    perf_log('batch_assignments');

                    $all_categories = get_files_categories_batch($file_ids);
                    perf_log('batch_categories');

                    // Process each file
                    foreach ($files_data as $row) {
                        $table->addRow([
                            'class' => 'file_draggable',
                            'attributes' => [
                                'draggable' => 'true',
                            ],
                            'data-attributes' => [
                                'draggable-type' => 'file',
                                'file-id' => $row['id'],
                            ],
                        ]);
                        $file = new \ProjectSend\Classes\Files($row['id']);

                        // Get pre-loaded assignments instead of querying for each file
                        $assignations = isset($all_assignations[$file->id]) ? $all_assignations[$file->id] : ['clients' => [], 'groups' => []];

                        $count_assignations = 0;
                        if (!empty($assignations['clients'])) {
                            $count_assignations += count($assignations['clients']);
                        }
                        if (!empty($assignations['groups'])) {
                            $count_assignations += count($assignations['groups']);
                        }

                        switch ($results_type) {
                            case 'client':
                                $hidden = $assignations['clients'][$this_id]['hidden'];
                                break;
                            case 'group':
                                $hidden = $assignations['groups'][$this_id]['hidden'];
                                break;
                        }

                        // Preview - check if file can be previewed
                        $preview_cell = '';
                        // For local images, try to create thumbnail
                        if (file_is_image($file->full_path)) {
                            $thumbnail = make_thumbnail($file->full_path, 'proportional', 300, 300, 90);
                            if (!empty($thumbnail['thumbnail']['url'])) {
                                // Use lazy loading with data attribute and placeholder
                                $placeholder = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'300\' height=\'300\'%3E%3Crect width=\'300\' height=\'300\' fill=\'%23f0f0f0\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' dominant-baseline=\'middle\' text-anchor=\'middle\' font-family=\'Arial\' font-size=\'18\' fill=\'%23999\'%3ELoading...%3C/text%3E%3C/svg%3E';
                                $preview_cell = '<a href="#" class="get-preview" data-url="' . BASE_URI . 'process.php?do=get_preview&file_id=' . $file->id . '">
                                            <img alt="" src="' . $placeholder . '" data-thumbnail="' . $thumbnail['thumbnail']['url'] . '" class="thumbnail lazy-thumbnail" />
                                        </a>';
                            }
                        }
                        // For non-image files or S3 files, show preview button if embeddable
                        // Check both the isEmbeddable method and direct extension check for S3 files
                        $embeddable_extensions = ['pdf', 'mp4', 'ogg', 'webm', 'mp3', 'wav', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
                        $file_ext = strtolower($file->getExtension());
                        $is_embeddable = $file->isEmbeddable() || in_array($file_ext, $embeddable_extensions);
                        if (empty($preview_cell) && $is_embeddable) {
                            $preview_cell = '<button class="btn btn-warning btn-sm btn-wide get-preview" data-url="' . BASE_URI . 'process.php?do=get_preview&file_id=' . $file->id . '">' . __('Preview', 'cftp_admin') . '</button>';
                        }

                        // Is file assigned?
                        $assigned_class = ($count_assignations == 0) ? 'danger' : 'success';
                        $assigned_status = ($count_assignations == 0) ? __('No', 'cftp_admin') : __('Yes', 'cftp_admin');

                        // Visibility
                        if ($file->isPublic()) {
                            $visibility_link = '<a href="javascript:void(0);" class="btn btn-primary btn-sm public_link" data-type="file" data-public-url="' . $file->public_url . '" data-title="' . $file->title . '">' . __('Download', 'cftp_admin') . '</a>';
                        } else {
                            if (get_option('enable_landing_for_all_files') == '1') {
                                $visibility_link = '<a href="javascript:void(0);" class="btn btn-pslight btn-sm public_link" data-type="file" data-public-url="' . $file->public_url . '" data-title="' . $file->title . '">' . __('View information', 'cftp_admin') . '</a>';
                            } else {
                                $visibility_link = '<a href="javascript:void(0);" class="btn btn-pslight btn-sm disabled" title="">' . __('None', 'cftp_admin') . '</a>';
                            }
                        }

                        // Expiration
                        if ($file->expires == '0' || !$file->expires) {
                            $expires_button = 'success';
                            $expires_label = __('Does not expire', 'cftp_admin');
                        } else {
                            $expires_date = date(get_option('timeformat'), strtotime($file->expiry_date));

                            if ($file->expired == true) {
                                $expires_button = 'danger';
                                $expires_label = __('Expired', 'cftp_admin');
                            } else {
                                $expires_button = 'info';
                                $expires_label = __('Expires', 'cftp_admin') . ' ' . $expires_date;
                            }
                        }

                        // Visibility
                        $status_class = '';
                        $status_label = '';
                        if (isset($search_on)) {
                            $status_class = ($hidden == 1) ? 'danger' : 'success';
                            $status_label = ($hidden == 1) ? __('Hidden', 'cftp_admin') : __('Visible', 'cftp_admin');
                        }

                        // Download count when filtering by group or client
                        if (isset($search_on)) {
                            $download_count_content = $row['download_count'] . ' ' . __('times', 'cftp_admin');

                            switch ($results_type) {
                                case 'client':
                                    break;
                                case 'group':
                                case 'category':
                                    if (current_user_can('view_downloads_details')) {
                                        $download_count_class = ($row['download_count'] > 0) ? 'downloaders btn-primary' : 'btn-pslight disabled';
                                        $download_count_content = '<a href="' . BASE_URI . 'download-information.php?id=' . $file->id . '" class="' . $download_count_class . ' btn btn-sm" title="' . html_output($row['filename']) . '">' . $download_count_content . '</a>';
                                    }
                                    break;
                            }
                        }

                        // Categories - Use pre-loaded batch data
                        $categories = isset($all_categories[$file->id]) ? $all_categories[$file->id] : [];
                        $categories_list = '';
                        if (!empty($categories)) {
                            $categories_list = '<ul class="ms-3 p-0">';
                            foreach ($categories as $category) {
                                $categories_list .= '<li>'.$category.'</li>';
                            }
                            $categories_list .= '</ul>';
                        }

                        // Download count and link on the unfiltered files table no specific client or group selected)
                        if (!isset($search_on)) {
                            if (!current_role_in(['Client']) && current_user_can('view_downloads_details')) {
                                if ($row["download_count"] > 0) {
                                    $btn_class = 'downloaders btn-primary';
                                } else {
                                    $btn_class = 'btn-pslight disabled';
                                }

                                $downloads_table_link = '<a href="' . BASE_URI . 'download-information.php?id=' . $file->id . '" class="' . $btn_class . ' btn btn-sm" title="' . html_output($row['filename']) . '">' . $row["download_count"] . ' ' . __('downloads', 'cftp_admin') . '</a>';
                            } else if (!isset($downloads_table_link)) {
                                // Show count without link if no permission
                                $downloads_table_link = '<span class="btn btn-sm btn-pslight disabled">' . $row["download_count"] . ' ' . __('downloads', 'cftp_admin') . '</span>';
                            }
                        }

                        // Download limit badge (calculate before title content)
                        $download_limit_badge = '';
                        $download_limit_icon = '';
                        if ($file->download_limit_enabled) {
                            $current_count = (int)$row['download_count'];
                            $max_count = (int)$file->download_limit_count;
                            $limit_type_text = ($file->download_limit_type == 'per_user') ? __('per user', 'cftp_admin') : __('total', 'cftp_admin');

                            // Determine badge color based on usage
                            $usage_percent = ($max_count > 0) ? ($current_count / $max_count) * 100 : 0;
                            if ($current_count >= $max_count) {
                                $badge_color = 'danger';
                            } elseif ($usage_percent >= 80) {
                                $badge_color = 'warning';
                            } else {
                                $badge_color = 'info';
                            }

                            $download_limit_badge = ' <span class="badge bg-' . $badge_color . '" title="' . __('Download limit', 'cftp_admin') . ': ' . $current_count . '/' . $max_count . ' (' . $limit_type_text . ')">';
                            $download_limit_badge .= '<i class="fa fa-download"></i> ' . $current_count . '/' . $max_count;
                            $download_limit_badge .= '</span>';

                            $download_limit_icon = '<i class="fa fa-download text-' . $badge_color . '" title="' . __('Download limit', 'cftp_admin') . ': ' . $current_count . '/' . $max_count . ' (' . $limit_type_text . ')"></i>';
                        } else {
                            $download_limit_icon = '<i class="fa fa-download text-muted" style="opacity: 0.3;" title="' . __('No download limit', 'cftp_admin') . '"></i>';
                        }

                        // Title content for table view (includes extra info)
                        $encryption_badge = '';
                        if ($file->encrypted) {
                            $encryption_badge = ' <span class="badge bg-success" title="' . __('This file is encrypted at rest', 'cftp_admin') . '"><i class="fa fa-lock"></i> ' . __('Encrypted', 'cftp_admin') . '</span>';
                        }
                        $title_content_table = '<a href="' . $file->download_link . '" target="_blank">' . $file->title . '</a>' . $encryption_badge;
                        if ($file->title != $file->filename_original) {
                            $title_content_table .= '<br><small>'.$file->filename_original.'</small>';
                        }
                        if (file_is_image($file->full_path)) {
                            $dimensions = $file->getDimensions();
                            if (!empty($dimensions)) {
                                $title_content_table .= '<br><div class="file_meta"><small>'.$dimensions['width'].' x '.$dimensions['height'].' px</small></div>';
                            }
                        }

                        // Simple title content for card view (title + link only)
                        $title_content = '<a href="' . $file->download_link . '" target="_blank">' . $file->title . '</a>';
                        if ($file->encrypted) {
                            $title_content .= '<br><span class="badge bg-success"><i class="fa fa-lock"></i> ' . __('Encrypted', 'cftp_admin') . '</span>';
                        }
                        if ($file->download_limit_enabled) {
                            $current_count = (int)$row['download_count'];
                            $max_count = (int)$file->download_limit_count;
                            $limit_type_text = ($file->download_limit_type == 'per_user') ? __('per user', 'cftp_admin') : __('total', 'cftp_admin');
                            $usage_percent = ($max_count > 0) ? ($current_count / $max_count) * 100 : 0;
                            if ($current_count >= $max_count) {
                                $badge_color = 'danger';
                            } elseif ($usage_percent >= 80) {
                                $badge_color = 'warning';
                            } else {
                                $badge_color = 'info';
                            }
                            $title_content .= '<br><span class="badge bg-' . $badge_color . '"><i class="fa fa-download"></i> ' . $current_count . '/' . $max_count . ' (' . $limit_type_text . ')</span>';
                        }

                        //* Add the cells to the row
                        $tbody_cells = array(
                            array(
                                'checkbox' => true,
                                'value' => $file->id,
                                'condition' => $conditions['select_all'],
                            ),
                            array(
                                'attributes' => array(
                                    'class' => array('file_name'),
                                ),
                                'content' => $title_content_table, // Full content for table view
                                'card_content' => $title_content, // Simple content for card view
                            ),
                            array(
                                'content' => $preview_cell,
                            ),
                            array(
                                'content' => format_date($file->uploaded_date),
                            ),
                            array(
                                'content' => $file->extension,
                            ),
                            array(
                                'content' => $file->description,
                            ),
                            array(
                                'content' => $file->size_formatted,
                            ),
                            array(
                                'content' => $file->uploaded_by,
                                'condition' => $conditions['is_not_client'],
                            ),
                            array(
                                'content' => '<span class="badge bg-' . $assigned_class . '">' . $assigned_status . '</span>',
                                'condition' => ($conditions['is_not_client'] && !$conditions['is_search_on']),
                            ),
                            array(
                                'attributes' => array(
                                    'class' => array('col_visibility'),
                                ),
                                'content' => $visibility_link,
                                'condition' => $conditions['can_set_public'],
                            ),
                            array(
                                'content' => '<span class="badge bg-' . $expires_button . '">' . $expires_label . '</span>',
                                'condition' => $conditions['can_set_expiration'],
                            ),
                            array(
                                'content' => $categories_list,
                                'condition' => $conditions['can_set_categories'],
                            ),
                            array(
                                'content' => '<span class="badge bg-' . $status_class . '">' . $status_label . '</span>',
                                'condition' => $conditions['is_search_on'],
                            ),
                            array(
                                'content' => (!empty($download_count_content)) ? $download_count_content : false,
                                'condition' => $conditions['is_search_on'],
                            ),
                            array(
                                'content' => (!empty($downloads_table_link)) ? $downloads_table_link : false,
                                'condition' => $conditions['total_downloads'],
                            ),
                            array(
                                'content' => current_user_can('view_downloads_details')
                                    ? '<a href="' . BASE_URI . 'download-information.php?id=' . $file->id . '" class="btn btn-sm ' . (($row['download_count'] > 0) ? 'downloaders btn-primary' : 'btn-pslight disabled') . '">' . $row['download_count'] . ' ' . __('downloads', 'cftp_admin') . '</a>'
                                    : '<span class="btn btn-sm btn-pslight disabled">' . $row['download_count'] . ' ' . __('downloads', 'cftp_admin') . '</span>',
                                'condition' => true, // Always include for card view
                                'hide_from_table' => true, // Hide from table view
                                'attributes' => array(
                                    'column_name' => 'card_download_count', // Custom identifier for card processing
                                ),
                            ),
                            array(
                                'content' => !empty($download_limit_badge) ? $download_limit_badge : '-',
                            ),
                            array(
                                'content' => $file->encrypted
                                    ? '<i class="fa fa-lock text-success" title="' . __('Encrypted', 'cftp_admin') . '"></i>'
                                    : '<i class="fa fa-unlock text-muted" style="opacity: 0.3;" title="' . __('Not encrypted', 'cftp_admin') . '"></i>',
                                'attributes' => array(
                                    'column_name' => 'encryption', // Identify this as encryption column
                                ),
                            ),
                            array(
                                'content' => '<a href="files-edit.php?ids=' . $file->id . '" class="btn btn-primary btn-sm" title="' . __('Edit file', 'cftp_admin') . '"><i class="fa fa-pencil"></i><span class="button_label">' . __('Edit', 'cftp_admin') . '</span></a>',
                                'condition' => $file->currentUserCanEdit(), // Check if current user can edit this file
                            ),
                            array(
                                'content' => '<button type="button" class="btn btn-danger btn-sm delete-file-btn" data-file-id="' . $file->id . '" data-file-name="' . html_output($file->title) . '" title="' . __('Delete file', 'cftp_admin') . '"><i class="fa fa-trash"></i><span class="button_label">' . __('Delete', 'cftp_admin') . '</span></button>',
                                'condition' => $file->currentUserCanDelete(), // Check if current user can delete this file
                            ),
                        );

                        foreach ($tbody_cells as $cell) {
                            $table->addCell($cell);
                        }

                        $table->end_row();
                    }

                    echo $table->render();

                    // Performance monitoring output (only for admins with debug enabled)
                    perf_log('render_complete');
                    if (current_role_in(['System Administrator']) && get_option('debug_mode') == '1') {
                        echo '<div class="alert alert-info" style="margin-top: 20px;">';
                        echo '<strong>Performance Metrics:</strong><br>';
                        echo 'Query execution: ' . ($perf_metrics['query_execute'] ?? 0) . 'ms<br>';
                        echo 'Data fetch: ' . ($perf_metrics['data_fetch'] ?? 0) . 'ms<br>';
                        echo 'Batch assignments: ' . (isset($perf_metrics['batch_assignments']) ? ($perf_metrics['batch_assignments'] - $perf_metrics['data_fetch']) : 0) . 'ms<br>';
                        echo 'Batch categories: ' . (isset($perf_metrics['batch_categories']) ? ($perf_metrics['batch_categories'] - $perf_metrics['batch_assignments']) : 0) . 'ms<br>';
                        echo 'Total render time: ' . ($perf_metrics['render_complete'] ?? 0) . 'ms<br>';
                        echo 'Files processed: ' . count($files_data ?? []) . '<br>';
                        echo 'Total files: ' . number_format($count_for_pagination ?? 0) . '<br>';
                        echo 'Memory peak: ' . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB';
                        echo '</div>';
                    }
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
            'link' => 'manage-files.php',
            'current' => $pagination_page,
            'item_count' => $count_for_pagination,
            'items_per_page' => $results_per_page,
        ]);
    }
?>

<!-- File Info Panel Overlay -->
<div id="info-panel-overlay" class="file-info-overlay" style="display: none;"></div>

<!-- File Info Panel -->
<div id="file-info-panel" class="file-info-panel">
    <div class="file-info-header">
        <h3><?php _e('File Information', 'cftp_admin'); ?></h3>
        <button id="close-info-panel" class="close-panel-btn" type="button">
            <i class="fa fa-times"></i>
        </button>
    </div>

    <div class="file-info-content">
        <div id="file-info-loading" class="info-loading">
            <div class="loading-spinner"></div>
            <p><?php _e('Loading file information...', 'cftp_admin'); ?></p>
        </div>
        <div id="file-info-data" style="display: none;">
            <!-- File info will be loaded here -->
        </div>
    </div>
</div>

<script>
// Lazy loading for thumbnails using IntersectionObserver
document.addEventListener('DOMContentLoaded', function() {
    // Check if IntersectionObserver is supported
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    const thumbnailUrl = img.getAttribute('data-thumbnail');

                    if (thumbnailUrl) {
                        // Create a new image to preload
                        const tempImg = new Image();
                        tempImg.onload = function() {
                            img.src = thumbnailUrl;
                            img.classList.remove('lazy-thumbnail');
                            img.classList.add('lazy-loaded');
                        };
                        tempImg.onerror = function() {
                            // If thumbnail fails to load, show error placeholder
                            img.alt = 'Failed to load thumbnail';
                            img.classList.remove('lazy-thumbnail');
                        };
                        tempImg.src = thumbnailUrl;

                        // Stop observing this image
                        observer.unobserve(img);
                    }
                }
            });
        }, {
            // Load images when they are within 200px of the viewport
            rootMargin: '200px',
            threshold: 0.01
        });

        // Observe all lazy thumbnail images
        const lazyImages = document.querySelectorAll('img.lazy-thumbnail');
        lazyImages.forEach(function(img) {
            imageObserver.observe(img);
        });
    } else {
        // Fallback for browsers that don't support IntersectionObserver
        const lazyImages = document.querySelectorAll('img.lazy-thumbnail');
        lazyImages.forEach(function(img) {
            const thumbnailUrl = img.getAttribute('data-thumbnail');
            if (thumbnailUrl) {
                img.src = thumbnailUrl;
                img.classList.remove('lazy-thumbnail');
            }
        });
    }
});

// Handle individual file delete buttons
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
            const batchInput = document.createElement('input');
            batchInput.type = 'hidden';
            batchInput.name = 'batch[]';
            batchInput.value = fileId;
            form.appendChild(batchInput);

            document.body.appendChild(form);
            form.submit();
        }
    }
});
</script>

    </div><!-- .main-content-area -->
</div><!-- .folder-tree-layout -->

<!-- Folder Tree JavaScript -->
<script src="<?php echo BASE_URI; ?>assets/src/js/parts/folder_tree.js"></script>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
