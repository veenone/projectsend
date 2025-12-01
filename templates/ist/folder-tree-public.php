<?php
/**
 * Folder Tree Navigation Component for IST Public Theme
 * With explicit IST color styling for better readability
 *
 * Required variables:
 * - $folder_tree: Array from Folders::getFolderTree()
 * - $current_folder: Currently selected folder ID (or null for root)
 * - $tree_base_url: Base URL for folder links
 */

$tree_id = 'public-folder-tree';
$tree_collapsed = isset($_COOKIE['public_folder_tree_collapsed']) && $_COOKIE['public_folder_tree_collapsed'] === 'true';

/**
 * Recursive function to render tree nodes with Tailwind
 */
function render_public_tree_node($folder, $base_url, $current_folder, $depth = 0) {
    $is_active = ($folder['id'] == $current_folder);
    $is_expanded = $folder['is_expanded'] || $is_active;
    $has_children = $folder['has_children'];

    // Build URL
    $parsed_url = parse_url($base_url);
    $base_path = $parsed_url['path'] ?? $base_url;
    $existing_params = [];
    if (!empty($parsed_url['query'])) {
        parse_str($parsed_url['query'], $existing_params);
    }
    $existing_params['folder_id'] = $folder['id'];
    $folder_url = $base_path . '?' . http_build_query($existing_params);

    $padding_left = ($depth * 16) + 8;
    ?>
    <div class="ist-tree-node" data-folder-id="<?php echo $folder['id']; ?>" data-expanded="<?php echo $is_expanded ? 'true' : 'false'; ?>">
        <div class="ist-tree-node-content <?php echo $is_active ? 'active' : ''; ?>"
             style="padding-left: <?php echo $padding_left; ?>px;">

            <?php if ($has_children): ?>
                <button type="button" class="ist-tree-toggle"
                        onclick="toggleTreeNode(this)" data-folder-id="<?php echo $folder['id']; ?>">
                    <i class="fas <?php echo $is_expanded ? 'fa-chevron-down' : 'fa-chevron-right'; ?>"></i>
                </button>
            <?php else: ?>
                <span class="ist-tree-toggle-placeholder"></span>
            <?php endif; ?>

            <a href="<?php echo htmlspecialchars($folder_url); ?>" class="ist-tree-link <?php echo $is_active ? 'active' : ''; ?>">
                <i class="fas <?php echo ($is_expanded && $has_children) ? 'fa-folder-open' : 'fa-folder'; ?> ist-tree-icon"></i>
                <span class="ist-tree-name"><?php echo htmlspecialchars($folder['name']); ?></span>
            </a>

            <?php if ($folder['files_count'] > 0): ?>
                <span class="ist-tree-badge"><?php echo $folder['files_count']; ?></span>
            <?php endif; ?>
        </div>

        <?php if ($has_children && !empty($folder['children'])): ?>
            <div class="ist-tree-children" style="<?php echo $is_expanded ? '' : 'display: none;'; ?>">
                <?php foreach ($folder['children'] as $child): ?>
                    <?php render_public_tree_node($child, $base_url, $current_folder, $depth + 1); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>

<!-- Folder Tree Sidebar -->
<div id="<?php echo $tree_id; ?>-sidebar" class="ist-folder-tree-sidebar <?php echo $tree_collapsed ? 'collapsed' : ''; ?>">
    <!-- Header -->
    <div class="ist-tree-header">
        <span class="ist-tree-header-title">
            <i class="fas fa-folder-open"></i>
            <span class="title-text"><?php echo __('Folders', 'ist_template'); ?></span>
        </span>
        <button type="button" id="<?php echo $tree_id; ?>-collapse-btn"
                class="ist-tree-collapse-btn"
                onclick="toggleTreeSidebar('<?php echo $tree_id; ?>')"
                title="<?php echo __('Toggle sidebar', 'ist_template'); ?>">
            <i class="fas fa-chevron-left collapse-icon"></i>
        </button>
    </div>

    <!-- Tree Container -->
    <div class="ist-tree-container" id="<?php echo $tree_id; ?>">
        <!-- Root Node -->
        <div class="ist-tree-node" data-folder-id="">
            <div class="ist-tree-node-content ist-tree-root <?php echo empty($current_folder) ? 'active' : ''; ?>">
                <span class="ist-tree-toggle-placeholder"></span>
                <a href="<?php echo htmlspecialchars(strtok($tree_base_url, '?')); ?>" class="ist-tree-link <?php echo empty($current_folder) ? 'active' : ''; ?>">
                    <i class="fas fa-hdd ist-tree-icon-root"></i>
                    <span class="ist-tree-name"><?php echo __('All Files', 'ist_template'); ?></span>
                </a>
            </div>
        </div>

        <!-- Folder Tree -->
        <?php if (!empty($folder_tree)): ?>
            <?php foreach ($folder_tree as $folder): ?>
                <?php render_public_tree_node($folder, $tree_base_url, $current_folder, 0); ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="ist-tree-empty">
                <i class="fas fa-folder-open"></i>
                <span><?php echo __('No folders', 'ist_template'); ?></span>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* IST Public Theme - Folder Tree Sidebar Styles */
    /* Using explicit colors for better readability */

    .ist-folder-tree-sidebar {
        width: 280px;
        min-width: 200px;
        max-width: 400px;
        flex-shrink: 0;
        background-color: #ffffff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(50, 60, 100, 0.08);
        display: flex;
        flex-direction: column;
        transition: width 0.3s ease, min-width 0.3s ease;
    }

    /* Dark mode sidebar */
    .dark .ist-folder-tree-sidebar {
        background-color: #6b7280;
        border-color: #9ca3af;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .ist-folder-tree-sidebar.collapsed {
        width: 48px !important;
        min-width: 48px !important;
        overflow: hidden;
    }

    .ist-folder-tree-sidebar.collapsed .title-text,
    .ist-folder-tree-sidebar.collapsed .ist-tree-container {
        display: none;
    }

    .ist-folder-tree-sidebar.collapsed .ist-tree-header-title {
        justify-content: center;
    }

    .ist-folder-tree-sidebar.collapsed .collapse-icon {
        transform: rotate(180deg);
    }

    /* Header */
    .ist-tree-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        background: linear-gradient(135deg, #323C64 0%, #5564A5 100%);
        border-radius: 8px 8px 0 0;
        border-bottom: 2px solid #7382E6;
    }

    .dark .ist-tree-header {
        background: linear-gradient(135deg, #4b5563 0%, #6b7280 100%);
        border-bottom-color: #7382E6;
    }

    .ist-tree-header-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #ffffff !important;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .ist-tree-header-title span,
    .ist-tree-header-title .title-text {
        color: #ffffff !important;
    }

    .ist-tree-header-title i {
        color: #CDDCFF !important;
    }

    .ist-tree-collapse-btn {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.1);
        border: none;
        color: #ffffff;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .ist-tree-collapse-btn:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .ist-tree-collapse-btn .collapse-icon {
        transition: transform 0.3s ease;
    }

    /* Tree Container */
    .ist-tree-container {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0.5rem 0;
        background-color: #ffffff;
        border-radius: 0 0 8px 8px;
    }

    .dark .ist-tree-container {
        background-color: #6b7280;
    }

    /* Tree Nodes */
    .ist-tree-node-content {
        display: flex;
        align-items: center;
        padding: 0.5rem;
        cursor: pointer;
        transition: background-color 0.15s ease;
        gap: 0.25rem;
    }

    .ist-tree-node-content:hover {
        background-color: #CDDCFF;
    }

    .dark .ist-tree-node-content:hover {
        background-color: #4b5563;
    }

    .ist-tree-node-content.active {
        background-color: #CDDCFF;
        border-right: 3px solid #7382E6;
    }

    .dark .ist-tree-node-content.active {
        background-color: #4b5563;
        border-right-color: #96AFFF;
    }

    /* Root node */
    .ist-tree-root {
        border-bottom: 1px solid #dee2e6;
        margin-bottom: 0.25rem;
    }

    .dark .ist-tree-root {
        border-bottom-color: #9ca3af;
    }

    /* Toggle button */
    .ist-tree-toggle {
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: none;
        border: none;
        color: #5564A5;
        cursor: pointer;
        border-radius: 3px;
        flex-shrink: 0;
        transition: all 0.15s ease;
        padding: 0;
    }

    .ist-tree-toggle:hover {
        background-color: #CDDCFF;
        color: #323C64;
    }

    .dark .ist-tree-toggle {
        color: #ffffff;
    }

    .dark .ist-tree-toggle:hover {
        background-color: #4b5563;
        color: #ffffff;
    }

    .ist-tree-toggle i {
        font-size: 0.65rem;
    }

    .ist-tree-toggle-placeholder {
        width: 20px;
        flex-shrink: 0;
    }

    /* Tree link */
    .ist-tree-link {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex: 1;
        min-width: 0;
        text-decoration: none !important;
        color: #323C64 !important;
        font-size: 0.875rem;
    }

    .ist-tree-link:hover {
        color: #7382E6 !important;
    }

    .ist-tree-link.active {
        color: #323C64 !important;
        font-weight: 600;
    }

    .dark .ist-tree-link {
        color: #ffffff !important;
    }

    .dark .ist-tree-link:hover {
        color: #CDDCFF !important;
    }

    .dark .ist-tree-link.active {
        color: #ffffff !important;
    }

    .ist-tree-icon {
        color: #7382E6 !important;
        flex-shrink: 0;
    }

    .dark .ist-tree-icon {
        color: #CDDCFF !important;
    }

    .ist-tree-icon-root {
        color: #5564A5 !important;
    }

    .dark .ist-tree-icon-root {
        color: #CDDCFF !important;
    }

    .ist-tree-name {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Badge */
    .ist-tree-badge {
        margin-left: auto;
        padding: 0.1rem 0.4rem;
        font-size: 0.7rem;
        font-weight: 500;
        background-color: #7382E6;
        color: #ffffff !important;
        border-radius: 10px;
        flex-shrink: 0;
    }

    .dark .ist-tree-badge {
        background-color: #7382E6;
        color: #ffffff !important;
    }

    /* Empty state */
    .ist-tree-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
        color: #5564A5;
        text-align: center;
    }

    .ist-tree-empty i {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        opacity: 0.5;
    }

    .ist-tree-empty span {
        font-size: 0.875rem;
    }

    .dark .ist-tree-empty {
        color: #ffffff;
    }

    /* Responsive */
    @media (max-width: 1023px) {
        .ist-folder-tree-sidebar {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 100% !important;
            max-height: 250px;
            margin-bottom: 1rem;
        }

        .ist-folder-tree-sidebar.collapsed {
            width: 100% !important;
            min-width: 100% !important;
            max-height: 48px;
        }

        .ist-folder-tree-sidebar .collapse-icon {
            transform: rotate(-90deg);
        }

        .ist-folder-tree-sidebar.collapsed .collapse-icon {
            transform: rotate(90deg);
        }
    }
</style>

<script>
function toggleTreeNode(button) {
    const node = button.closest('.ist-tree-node');
    const children = node.querySelector('.ist-tree-children');
    const icon = button.querySelector('i');
    const folderIcon = node.querySelector('.ist-tree-link i.fa-folder, .ist-tree-link i.fa-folder-open');

    if (!children) return;

    const isExpanded = children.style.display !== 'none';

    if (isExpanded) {
        children.style.display = 'none';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-right');
        if (folderIcon) {
            folderIcon.classList.remove('fa-folder-open');
            folderIcon.classList.add('fa-folder');
        }
    } else {
        children.style.display = '';
        icon.classList.remove('fa-chevron-right');
        icon.classList.add('fa-chevron-down');
        if (folderIcon) {
            folderIcon.classList.remove('fa-folder');
            folderIcon.classList.add('fa-folder-open');
        }
    }
}

function toggleTreeSidebar(treeId) {
    const sidebar = document.getElementById(treeId + '-sidebar');
    const icon = sidebar.querySelector('.collapse-icon');

    sidebar.classList.toggle('collapsed');
    const isCollapsed = sidebar.classList.contains('collapsed');

    // Save state
    document.cookie = 'public_folder_tree_collapsed=' + isCollapsed + ';path=/;max-age=31536000';
}
</script>
