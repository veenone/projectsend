<?php
/**
 * Folder Tree Navigation Component
 *
 * Renders a collapsible tree view of folders for elFinder-style navigation.
 * Can be used in both admin (manage-files.php) and public templates.
 *
 * Required variables:
 * - $folder_tree: Array from Folders::getFolderTree()
 * - $current_folder: Currently selected folder ID (or null for root)
 * - $tree_base_url: Base URL for folder links (e.g., 'manage-files.php' or 'public.php')
 * - $tree_context: Context identifier ('admin' or 'public')
 *
 * Optional variables:
 * - $tree_id: Custom ID for the tree container (default: 'folder-tree')
 * - $show_file_counts: Whether to show file counts (default: true)
 * - $allow_drag_drop: Whether to enable drag & drop (default: true for admin)
 */

// Set defaults
$tree_id = isset($tree_id) ? $tree_id : 'folder-tree';
$show_file_counts = isset($show_file_counts) ? $show_file_counts : true;
$allow_drag_drop = isset($allow_drag_drop) ? $allow_drag_drop : ($tree_context === 'admin');
$tree_collapsed = isset($_COOKIE['folder_tree_collapsed']) && $_COOKIE['folder_tree_collapsed'] === 'true';

/**
 * Recursive function to render tree nodes
 */
function render_tree_node($folder, $base_url, $current_folder, $show_file_counts, $allow_drag_drop, $depth = 0) {
    $is_active = ($folder['id'] == $current_folder);
    $is_expanded = $folder['is_expanded'] || $is_active;
    $has_children = $folder['has_children'];

    // Build URL for this folder
    $parsed_url = parse_url($base_url);
    $base_path = $parsed_url['path'] ?? $base_url;
    $existing_params = [];
    if (!empty($parsed_url['query'])) {
        parse_str($parsed_url['query'], $existing_params);
    }
    $existing_params['folder_id'] = $folder['id'];
    $folder_url = $base_path . '?' . http_build_query($existing_params);

    $node_classes = ['tree-node'];
    if ($is_active) $node_classes[] = 'active';
    if ($is_expanded && $has_children) $node_classes[] = 'expanded';
    if ($has_children) $node_classes[] = 'has-children';

    $drag_attrs = '';
    if ($allow_drag_drop) {
        $drag_attrs = 'draggable="true" data-draggable-type="folder"';
    }
    ?>
    <div class="<?php echo implode(' ', $node_classes); ?>"
         data-folder-id="<?php echo $folder['id']; ?>"
         data-folder-name="<?php echo htmlspecialchars($folder['name']); ?>"
         data-depth="<?php echo $depth; ?>"
         <?php echo $drag_attrs; ?>>

        <div class="tree-node-content" style="padding-left: <?php echo ($depth * 16) + 8; ?>px;">
            <?php if ($has_children): ?>
                <span class="tree-toggle" data-folder-id="<?php echo $folder['id']; ?>">
                    <i class="fa <?php echo $is_expanded ? 'fa-chevron-down' : 'fa-chevron-right'; ?>"></i>
                </span>
            <?php else: ?>
                <span class="tree-toggle-placeholder"></span>
            <?php endif; ?>

            <a href="<?php echo htmlspecialchars($folder_url); ?>" class="tree-node-link">
                <i class="fa <?php echo $is_expanded && $has_children ? 'fa-folder-open' : 'fa-folder'; ?> tree-icon"></i>
                <span class="tree-node-name"><?php echo htmlspecialchars($folder['name']); ?></span>
            </a>

            <?php if ($show_file_counts && $folder['files_count'] > 0): ?>
                <span class="tree-node-badge"><?php echo $folder['files_count']; ?></span>
            <?php endif; ?>

            <?php if ($allow_drag_drop): ?>
                <span class="tree-node-menu" data-folder-id="<?php echo $folder['id']; ?>">
                    <i class="fa fa-ellipsis-v"></i>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($has_children && !empty($folder['children'])): ?>
            <div class="tree-children" style="<?php echo $is_expanded ? '' : 'display: none;'; ?>">
                <?php foreach ($folder['children'] as $child): ?>
                    <?php render_tree_node($child, $base_url, $current_folder, $show_file_counts, $allow_drag_drop, $depth + 1); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>

<div id="<?php echo $tree_id; ?>-sidebar" class="folder-tree-sidebar <?php echo $tree_collapsed ? 'collapsed' : ''; ?>">
    <!-- Sidebar Header -->
    <div class="tree-sidebar-header">
        <span class="tree-sidebar-title">
            <i class="fa fa-folder-open"></i>
            <span class="title-text"><?php _e('Folders', 'cftp_admin'); ?></span>
        </span>
        <button type="button" class="tree-collapse-btn" id="<?php echo $tree_id; ?>-collapse-btn" title="<?php _e('Toggle sidebar', 'cftp_admin'); ?>">
            <i class="fa fa-chevron-left"></i>
        </button>
    </div>

    <!-- Tree Container -->
    <div class="tree-container" id="<?php echo $tree_id; ?>">
        <!-- Root Node -->
        <div class="tree-node tree-root <?php echo empty($current_folder) ? 'active' : ''; ?>" data-folder-id="">
            <div class="tree-node-content">
                <span class="tree-toggle-placeholder"></span>
                <a href="<?php echo htmlspecialchars(strtok($tree_base_url, '?')); ?>" class="tree-node-link">
                    <i class="fa fa-hdd-o tree-icon"></i>
                    <span class="tree-node-name"><?php _e('Files Root', 'cftp_admin'); ?></span>
                </a>
            </div>
        </div>

        <!-- Folder Tree -->
        <?php if (!empty($folder_tree)): ?>
            <?php foreach ($folder_tree as $folder): ?>
                <?php render_tree_node($folder, $tree_base_url, $current_folder, $show_file_counts, $allow_drag_drop, 0); ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="tree-empty">
                <i class="fa fa-folder-o"></i>
                <span><?php _e('No folders', 'cftp_admin'); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Resize Handle -->
    <div class="tree-resize-handle" id="<?php echo $tree_id; ?>-resize"></div>
</div>

<!-- Data attributes for JavaScript -->
<script type="application/json" id="<?php echo $tree_id; ?>-config">
{
    "treeId": "<?php echo $tree_id; ?>",
    "baseUrl": "<?php echo htmlspecialchars($tree_base_url); ?>",
    "ajaxUrl": "<?php echo AJAX_PROCESS_URL; ?>",
    "currentFolder": <?php echo $current_folder ? $current_folder : 'null'; ?>,
    "context": "<?php echo $tree_context; ?>",
    "allowDragDrop": <?php echo $allow_drag_drop ? 'true' : 'false'; ?>
}
</script>
