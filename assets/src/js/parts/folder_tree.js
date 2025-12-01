/**
 * Folder Tree Navigation Component
 *
 * Provides elFinder-style tree view functionality for folder navigation.
 * Features:
 * - Expand/collapse folders
 * - Drag & drop support
 * - Resizable sidebar
 * - Collapsible sidebar
 * - Context menu integration
 */

(function() {
    'use strict';

    class FolderTree {
        constructor(configElementId) {
            this.configElement = document.getElementById(configElementId);
            if (!this.configElement) {
                console.warn('FolderTree: Config element not found:', configElementId);
                return;
            }

            try {
                this.config = JSON.parse(this.configElement.textContent);
            } catch (e) {
                console.error('FolderTree: Failed to parse config:', e);
                return;
            }

            this.treeId = this.config.treeId;
            this.sidebar = document.getElementById(this.treeId + '-sidebar');
            this.container = document.getElementById(this.treeId);
            this.collapseBtn = document.getElementById(this.treeId + '-collapse-btn');
            this.resizeHandle = document.getElementById(this.treeId + '-resize');

            if (!this.sidebar || !this.container) {
                console.warn('FolderTree: Required elements not found');
                return;
            }

            this.init();
        }

        init() {
            this.bindToggleEvents();
            this.bindCollapseEvents();
            this.bindResizeEvents();

            if (this.config.allowDragDrop) {
                this.bindDragDropEvents();
            }

            // Restore sidebar width from localStorage
            this.restoreSidebarWidth();
        }

        /**
         * Toggle folder expand/collapse
         */
        bindToggleEvents() {
            this.container.addEventListener('click', (e) => {
                const toggle = e.target.closest('.tree-toggle');
                if (!toggle) return;

                e.preventDefault();
                e.stopPropagation();

                const node = toggle.closest('.tree-node');
                if (!node) return;

                this.toggleNode(node);
            });
        }

        toggleNode(node) {
            const isExpanded = node.classList.contains('expanded');
            const children = node.querySelector('.tree-children');
            const toggle = node.querySelector('.tree-toggle i');
            const icon = node.querySelector('.tree-icon');

            if (isExpanded) {
                // Collapse
                node.classList.remove('expanded');
                if (children) {
                    children.style.display = 'none';
                }
                if (toggle) {
                    toggle.classList.remove('fa-chevron-down');
                    toggle.classList.add('fa-chevron-right');
                }
                if (icon) {
                    icon.classList.remove('fa-folder-open');
                    icon.classList.add('fa-folder');
                }
            } else {
                // Expand
                node.classList.add('expanded');
                if (children) {
                    children.style.display = '';
                }
                if (toggle) {
                    toggle.classList.remove('fa-chevron-right');
                    toggle.classList.add('fa-chevron-down');
                }
                if (icon) {
                    icon.classList.remove('fa-folder');
                    icon.classList.add('fa-folder-open');
                }
            }

            // Save expanded state
            this.saveExpandedState();
        }

        /**
         * Sidebar collapse/expand
         */
        bindCollapseEvents() {
            if (!this.collapseBtn) return;

            this.collapseBtn.addEventListener('click', () => {
                this.toggleSidebar();
            });
        }

        toggleSidebar() {
            const isCollapsed = this.sidebar.classList.toggle('collapsed');

            // Update icon direction
            const icon = this.collapseBtn.querySelector('i');
            if (icon) {
                if (isCollapsed) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                }
            }

            // Save state to cookie
            document.cookie = 'folder_tree_collapsed=' + isCollapsed + ';path=/;max-age=31536000';

            // Dispatch event for layout adjustments
            window.dispatchEvent(new CustomEvent('folderTreeToggle', {
                detail: { collapsed: isCollapsed }
            }));
        }

        /**
         * Resizable sidebar
         */
        bindResizeEvents() {
            if (!this.resizeHandle) return;

            let isResizing = false;
            let startX = 0;
            let startWidth = 0;

            this.resizeHandle.addEventListener('mousedown', (e) => {
                if (this.sidebar.classList.contains('collapsed')) return;

                isResizing = true;
                startX = e.clientX;
                startWidth = this.sidebar.offsetWidth;

                document.body.classList.add('resizing-sidebar');
                e.preventDefault();
            });

            document.addEventListener('mousemove', (e) => {
                if (!isResizing) return;

                const diff = e.clientX - startX;
                const newWidth = Math.max(200, Math.min(500, startWidth + diff));
                this.sidebar.style.width = newWidth + 'px';
            });

            document.addEventListener('mouseup', () => {
                if (!isResizing) return;

                isResizing = false;
                document.body.classList.remove('resizing-sidebar');

                // Save width to localStorage
                localStorage.setItem('folder_tree_width', this.sidebar.offsetWidth);
            });
        }

        restoreSidebarWidth() {
            const savedWidth = localStorage.getItem('folder_tree_width');
            if (savedWidth && !this.sidebar.classList.contains('collapsed')) {
                this.sidebar.style.width = savedWidth + 'px';
            }
        }

        /**
         * Drag & Drop support
         */
        bindDragDropEvents() {
            const nodes = this.container.querySelectorAll('.tree-node[draggable="true"]');

            nodes.forEach(node => {
                // Drag start
                node.addEventListener('dragstart', (e) => {
                    e.stopPropagation();
                    const folderId = node.dataset.folderId;
                    e.dataTransfer.setData('text/plain', JSON.stringify({
                        type: 'folder',
                        id: folderId
                    }));
                    e.dataTransfer.effectAllowed = 'move';
                    node.classList.add('dragging');

                    // Mark valid drop targets
                    this.markDropTargets(folderId);
                });

                // Drag end
                node.addEventListener('dragend', (e) => {
                    node.classList.remove('dragging');
                    this.clearDropTargets();
                });
            });

            // Drop targets (all nodes including root)
            const allNodes = this.container.querySelectorAll('.tree-node');
            allNodes.forEach(targetNode => {
                targetNode.addEventListener('dragover', (e) => {
                    if (!targetNode.classList.contains('drop-forbidden')) {
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';
                        targetNode.classList.add('drop-hover');
                    }
                });

                targetNode.addEventListener('dragleave', (e) => {
                    targetNode.classList.remove('drop-hover');
                });

                targetNode.addEventListener('drop', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    targetNode.classList.remove('drop-hover');

                    try {
                        const data = JSON.parse(e.dataTransfer.getData('text/plain'));
                        const targetFolderId = targetNode.dataset.folderId || null;

                        if (data.type === 'folder') {
                            this.moveFolder(data.id, targetFolderId);
                        } else if (data.type === 'file') {
                            this.moveFile(data.id, targetFolderId);
                        }
                    } catch (err) {
                        console.error('Drop error:', err);
                    }
                });
            });
        }

        markDropTargets(draggedFolderId) {
            const allNodes = this.container.querySelectorAll('.tree-node');
            allNodes.forEach(node => {
                const nodeId = node.dataset.folderId;

                // Cannot drop on self or children
                if (nodeId === draggedFolderId || this.isDescendant(draggedFolderId, nodeId)) {
                    node.classList.add('drop-forbidden');
                } else {
                    node.classList.add('drop-ready');
                }
            });
        }

        clearDropTargets() {
            const allNodes = this.container.querySelectorAll('.tree-node');
            allNodes.forEach(node => {
                node.classList.remove('drop-ready', 'drop-forbidden', 'drop-hover');
            });
        }

        isDescendant(parentId, childId) {
            const parent = this.container.querySelector(`.tree-node[data-folder-id="${parentId}"]`);
            if (!parent) return false;

            const child = parent.querySelector(`.tree-node[data-folder-id="${childId}"]`);
            return !!child;
        }

        moveFolder(folderId, newParentId) {
            const url = this.config.ajaxUrl + '?do=folder_move';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    folder_id: folderId,
                    new_parent_id: newParentId || ''
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Reload page to reflect changes
                    window.location.reload();
                } else {
                    console.error('Move folder failed:', data);
                    alert('Failed to move folder');
                }
            })
            .catch(err => {
                console.error('Move folder error:', err);
                alert('Failed to move folder');
            });
        }

        moveFile(fileId, newFolderId) {
            const url = this.config.ajaxUrl + '?do=file_move';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    file_id: fileId,
                    new_parent_id: newFolderId || ''
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    console.error('Move file failed:', data);
                    alert('Failed to move file');
                }
            })
            .catch(err => {
                console.error('Move file error:', err);
                alert('Failed to move file');
            });
        }

        /**
         * Save expanded folder state to localStorage
         */
        saveExpandedState() {
            const expanded = [];
            this.container.querySelectorAll('.tree-node.expanded').forEach(node => {
                if (node.dataset.folderId) {
                    expanded.push(node.dataset.folderId);
                }
            });
            localStorage.setItem('folder_tree_expanded_' + this.config.context, JSON.stringify(expanded));
        }

        /**
         * Refresh tree data via AJAX
         */
        refresh() {
            const url = this.config.ajaxUrl + '?do=folder_tree&current_folder=' + (this.config.currentFolder || '');

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Would need to re-render the tree - for now just reload
                        window.location.reload();
                    }
                })
                .catch(err => {
                    console.error('Refresh tree error:', err);
                });
        }
    }

    // Auto-initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        // Find all tree configs and initialize
        const configs = document.querySelectorAll('script[id$="-config"][type="application/json"]');
        configs.forEach(config => {
            if (config.id.includes('folder-tree')) {
                new FolderTree(config.id);
            }
        });
    });

    // Expose globally for external use
    window.FolderTree = FolderTree;

})();
