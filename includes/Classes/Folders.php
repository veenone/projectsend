<?php
namespace ProjectSend\Classes;

class Folders
{
    protected $folders;
    protected $arranged_folders;
    protected $dbh;
    protected $logger;

    public function __construct()
    {
        global $dbh;
        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;
    }

    function makeFolderBreadcrumbs($from_folder_id, $url = BASE_URI) {
        $base_url = strtok($url, '?');
        $parsed = parse_url($url);
        if (!empty($parsed['query'])) {
            $query = $parsed['query'];
            parse_str($query, $params);
            $params_remove = ['folder_id', 'search', 'assigned', 'uploader'];
            foreach ($params_remove as $param) {
                unset($params[$param]);
            }
        } else {
            $params = [];
        }
    
        $elements = [
            [
                'url' => $base_url,
                'name' => 'Files root',
            ],
        ];
    
        if (!empty($from_folder_id)) {
            $folder = new \ProjectSend\Classes\Folder($from_folder_id);
            $nested = $folder->getHierarchy();
            if (!empty($nested)) {
                $nested = array_reverse($nested);
    
                foreach ($nested as $folder) {
                    $params['folder_id'] = $folder['id'];
                    $url = ($folder['id'] != $from_folder_id) ? $base_url.'?'.http_build_query($params) : null;
                    $elements[] = [
                        'url' => $url,
                        'name' => $folder['name'],
                    ];
                }
            }
        }
    
        return $elements;
    }

    function getFolders($arguments = [])
    {
        // // Existing public flag fix
        // $queryx = "UPDATE `tbl_folders` set public = 1 ";
        // $statement = $this->dbh->prepare($queryx);
        // $statement->execute();

        // Initialize $folders as an empty array
        $folders = [];
        
        // Get client access level if not provided
        if (!isset($arguments['role']) && isset($arguments['user_id'])) {
            $arguments['role'] = $this->getUserRole($arguments['user_id']);
            if (in_array($arguments['role'], ['Client', 'Internal User']) && !isset($arguments['client_id'])) {
                $arguments['client_id'] = $arguments['user_id'];
            }
        }

        $query = "SELECT DISTINCT f.* FROM " . TABLE_FOLDERS . " f";
        $params = [];
        if (isset($arguments['role']) && in_array($arguments['role'], ['Client', 'Internal User']) && isset($arguments['client_id'])) {
            $query .= " WHERE (
            -- Folders created by the client
            f.user_id = :client_created
            OR
            -- Get folders that contain files created by current user (added condition)
            EXISTS (
                SELECT 1
                FROM " . TABLE_FILES . " tf
                WHERE tf.folder_id = f.id
                AND tf.user_id = :current_user_id
            )
            OR
            -- Get folders through direct file assignments or group memberships
            EXISTS (
                SELECT 1
                FROM " . TABLE_FILES_RELATIONS . " fr
                JOIN " . TABLE_FILES . " tf ON fr.file_id = tf.id
                WHERE tf.folder_id = f.id
                AND fr.hidden = 0
                AND (
                    -- Direct client assignment
                    fr.client_id = :client_id
                    OR
                    -- Group assignment (check both user_id and client_id for backward compatibility)
                    fr.group_id IN (
                        SELECT group_id
                        FROM " . TABLE_MEMBERS . "
                        WHERE COALESCE(user_id, client_id) = :client_id_groups
                    )
                )
            )
            OR
            -- Include all parent folders of accessible folders
            f.id IN (
                WITH RECURSIVE folder_hierarchy AS (
                    -- Base case: Get folders with directly accessible files
                    SELECT DISTINCT tf.folder_id as id, fld.parent
                    FROM " . TABLE_FILES_RELATIONS . " fr
                    JOIN " . TABLE_FILES . " tf ON fr.file_id = tf.id
                    JOIN " . TABLE_FOLDERS . " fld ON tf.folder_id = fld.id
                    WHERE fr.hidden = 0
                    AND (
                        fr.client_id = :client_id_hierarchy
                        OR
                        fr.group_id IN (
                            SELECT group_id
                            FROM " . TABLE_MEMBERS . "
                            WHERE COALESCE(user_id, client_id) = :client_id_groups_hierarchy
                        )
                    )
                    UNION ALL
                    -- Recursive case: Get all parent folders
                    SELECT f2.id, f2.parent
                    FROM " . TABLE_FOLDERS . " f2
                    INNER JOIN folder_hierarchy fh ON f2.id = fh.parent
                )
                SELECT id FROM folder_hierarchy
            )
        )";
        $params[':client_created'] = $arguments['client_id'];
        $params[':current_user_id'] = $arguments['client_id'];
        $params[':client_id'] = $arguments['client_id'];
        $params[':client_id_groups'] = $arguments['client_id'];
        $params[':client_id_hierarchy'] = $arguments['client_id'];
        $params[':client_id_groups_hierarchy'] = $arguments['client_id'];
            
            // Parent folder filter for clients
            if (array_key_exists('parent', $arguments)) {
                if (is_null($arguments['parent'])) {
                    $query .= " AND f.parent IS NULL";
                } else {
                    $query .= " AND f.parent = :parent";
                    $params[':parent'] = (int)$arguments['parent'];
                }
            }
        } else {
            // Admin access remains unchanged...
            $where_conditions = [];
            if (array_key_exists('parent', $arguments)) {
                if (is_null($arguments['parent'])) {
                    $where_conditions[] = "f.parent IS NULL";
                } else {
                    $where_conditions[] = "f.parent = :parent";
                    $params[':parent'] = (int)$arguments['parent'];
                }
            }
    
            if (isset($arguments['search'])) {
                $where_conditions[] = "(f.name LIKE :name OR f.slug LIKE :slug)";
                $search_terms = '%' . $arguments['search'] . '%';
                $params[':name'] = $search_terms;
                $params[':slug'] = $search_terms;
            }
    
            if (isset($arguments['include_public']) && $arguments['include_public'] == true) {
                $where_conditions[] = "f.public = :public";
                $params[':public'] = '1';    
            }
    
            if (isset($arguments['user_id'])) {
                $where_conditions[] = "f.user_id = :user_id";
                $params[':user_id'] = $arguments['user_id'];
            }
            
            if (isset($arguments['public_or_client']) && $arguments['public_or_client'] == true) {
                $where_conditions[] = "(f.public = :public_client OR f.user_id = :client_id)";
                $params[':public_client'] = '1';
                $params[':client_id'] = $arguments['client_id'];
            }
    
            if (!empty($where_conditions)) {
                $query .= " WHERE " . implode(" AND ", $where_conditions);
            }
        }
    
        $query .= " ORDER BY f.name ASC";
    
        $statement = $this->dbh->prepare($query);
        $statement->execute($params);
        
        // Initialize $folders before using it
        $folders = [];
        
        if ($statement->rowCount() > 0) {
            $statement->setFetchMode(\PDO::FETCH_ASSOC);
            while ($row = $statement->fetch()) {
                $obj = new \ProjectSend\Classes\Folder($row['id']);
                $folders[$row['id']] = $obj->getData();
            }
        }
    
        $this->folders = $folders;
        return $this->folders;
    }


    function getUserRole($user_id)
    {
        $query = "SELECT r.name FROM " . TABLE_USERS . " u
                  JOIN " . TABLE_ROLES . " r ON u.role_id = r.id
                  WHERE u.id = :user_id";
        $statement = $this->dbh->prepare($query);
        $statement->execute([':user_id' => $user_id]);
        $result = $statement->fetch(\PDO::FETCH_ASSOC);
        return ($result) ? $result['name'] : null;
    }

    function getAllArranged($parent = null, $depth = 0, $include = [])
    {
        $data = [];
        $folders = $this->getFolders(['parent' => $parent]);
        if (!empty($folders)) {
            foreach ($folders as $folder_id => $folder) {
                if (!empty($include) && !in_array($folder_id, $include)) {
                    continue;
                }

                // Set depth based on parent
                if ($folder['parent'] == null) {
                    $folder['depth'] = 0;
                    $currentDepth = 0;
                } else {
                    $folder['depth'] = $depth + 1;
                    $currentDepth = $depth + 1;
                }
                
                // Get child elements with current depth
                $folder['children'] = $this->getAllArranged($folder['id'], $currentDepth, $include);
                $data[] = $folder;
            }
        }
    
        return $data;
    }

    function renderSelectOptions(&$folders = [], $arguments = [])
    {
        $return = '';
        if (empty($folders)) {
            return $return;
        }

        foreach ($folders as $folder) {
            $depth_indicator = ($folder['depth'] > 0) ? str_repeat('&mdash;', $folder['depth']) . ' ' : false;
            $selected = (!empty($arguments['selected']) && $arguments['selected'] == $folder['id']) ? 'selected="selected"' : '';
            if (!empty($arguments['ignore']) && in_array($folder['id'], $arguments['ignore'])) {
                continue;
            }
            $return .= '<option '.$selected.' value="'.$folder['id'].'">'.$depth_indicator . $folder['name'].'</option>';

            if (!empty($folder['children'])) {
                $return .= $this->renderSelectOptions($folder['children'], $arguments);
            }
        }

        return $return;
    }

    /**
     * Get folder tree structure for tree view navigation
     * Returns all folders in a nested tree structure with metadata
     *
     * @param array $arguments Filter arguments (user_id, role, etc.)
     * @param int|null $expandedFolderId Currently selected/expanded folder ID
     * @return array Nested folder tree structure
     */
    function getFolderTree($arguments = [], $expandedFolderId = null)
    {
        // Get all folders without parent filter to build complete tree
        $all_folders_args = $arguments;
        unset($all_folders_args['parent']);

        // Get all accessible folders
        $all_folders = $this->getAllFoldersFlat($all_folders_args);

        // Build tree structure
        $tree = $this->buildTree($all_folders, null, $expandedFolderId);

        return $tree;
    }

    /**
     * Get all folders in a flat array (without parent filter)
     * Used internally by getFolderTree
     */
    private function getAllFoldersFlat($arguments = [])
    {
        $folders = [];
        $params = [];

        // Get client access level if not provided
        if (!isset($arguments['role']) && isset($arguments['user_id'])) {
            $arguments['role'] = $this->getUserRole($arguments['user_id']);
            if (in_array($arguments['role'], ['Client', 'Internal User']) && !isset($arguments['client_id'])) {
                $arguments['client_id'] = $arguments['user_id'];
            }
        }

        // Build file count subquery based on role/permissions
        $files_count_subquery = "(SELECT COUNT(*) FROM " . TABLE_FILES . " tf WHERE tf.folder_id = f.id";

        if (isset($arguments['role']) && in_array($arguments['role'], ['Client', 'Internal User']) && isset($arguments['client_id'])) {
            // For Client/Internal User: only count files they can access
            $files_count_subquery .= " AND (
                tf.user_id = :files_count_user_id
                OR EXISTS (
                    SELECT 1 FROM " . TABLE_FILES_RELATIONS . " fr
                    WHERE fr.file_id = tf.id AND fr.hidden = 0
                    AND (
                        fr.client_id = :files_count_client_id
                        OR fr.group_id IN (
                            SELECT group_id FROM " . TABLE_MEMBERS . "
                            WHERE COALESCE(user_id, client_id) = :files_count_client_groups
                        )
                    )
                )
            )";
            $params[':files_count_user_id'] = $arguments['client_id'];
            $params[':files_count_client_id'] = $arguments['client_id'];
            $params[':files_count_client_groups'] = $arguments['client_id'];
        } elseif (isset($arguments['owner_user_id'])) {
            // For users without edit_others_files permission: only count their own files
            $files_count_subquery .= " AND tf.user_id = :files_count_owner_id";
            $params[':files_count_owner_id'] = $arguments['owner_user_id'];
        } elseif (!empty($arguments['include_public']) && !isset($arguments['role'])) {
            // For anonymous/public: only count public files
            $files_count_subquery .= " AND tf.public_allow = 1";
        }
        // For admin users with full access: no additional filter (count all files)

        $files_count_subquery .= ") as files_count";

        $query = "SELECT DISTINCT f.*,
                  (SELECT COUNT(*) FROM " . TABLE_FOLDERS . " c WHERE c.parent = f.id) as children_count,
                  {$files_count_subquery}
                  FROM " . TABLE_FOLDERS . " f";

        if (isset($arguments['role']) && in_array($arguments['role'], ['Client', 'Internal User']) && isset($arguments['client_id'])) {
            $query .= " WHERE (
                f.user_id = :client_created
                OR
                EXISTS (
                    SELECT 1 FROM " . TABLE_FILES . " tf
                    WHERE tf.folder_id = f.id AND tf.user_id = :current_user_id
                )
                OR
                EXISTS (
                    SELECT 1 FROM " . TABLE_FILES_RELATIONS . " fr
                    JOIN " . TABLE_FILES . " tf ON fr.file_id = tf.id
                    WHERE tf.folder_id = f.id AND fr.hidden = 0
                    AND (
                        fr.client_id = :client_id
                        OR fr.group_id IN (
                            SELECT group_id FROM " . TABLE_MEMBERS . "
                            WHERE COALESCE(user_id, client_id) = :client_id_groups
                        )
                    )
                )
                OR
                f.id IN (
                    WITH RECURSIVE folder_hierarchy AS (
                        SELECT DISTINCT tf.folder_id as id, fld.parent
                        FROM " . TABLE_FILES_RELATIONS . " fr
                        JOIN " . TABLE_FILES . " tf ON fr.file_id = tf.id
                        JOIN " . TABLE_FOLDERS . " fld ON tf.folder_id = fld.id
                        WHERE fr.hidden = 0
                        AND (
                            fr.client_id = :client_id_hierarchy
                            OR fr.group_id IN (
                                SELECT group_id FROM " . TABLE_MEMBERS . "
                                WHERE COALESCE(user_id, client_id) = :client_id_groups_hierarchy
                            )
                        )
                        UNION ALL
                        SELECT f2.id, f2.parent
                        FROM " . TABLE_FOLDERS . " f2
                        INNER JOIN folder_hierarchy fh ON f2.id = fh.parent
                    )
                    SELECT id FROM folder_hierarchy
                )
            )";
            $params[':client_created'] = $arguments['client_id'];
            $params[':current_user_id'] = $arguments['client_id'];
            $params[':client_id'] = $arguments['client_id'];
            $params[':client_id_groups'] = $arguments['client_id'];
            $params[':client_id_hierarchy'] = $arguments['client_id'];
            $params[':client_id_groups_hierarchy'] = $arguments['client_id'];
        } elseif (!empty($arguments['include_public']) && !isset($arguments['role'])) {
            // Anonymous users - only show folders containing public files
            $query .= " WHERE (
                -- Folders that directly contain public files
                EXISTS (
                    SELECT 1 FROM " . TABLE_FILES . " tf
                    WHERE tf.folder_id = f.id AND tf.public_allow = 1
                )
                OR
                -- Parent folders in the hierarchy of folders with public files
                f.id IN (
                    WITH RECURSIVE folder_hierarchy AS (
                        SELECT DISTINCT tf.folder_id as id, fld.parent
                        FROM " . TABLE_FILES . " tf
                        JOIN " . TABLE_FOLDERS . " fld ON tf.folder_id = fld.id
                        WHERE tf.public_allow = 1
                        UNION ALL
                        SELECT f2.id, f2.parent
                        FROM " . TABLE_FOLDERS . " f2
                        INNER JOIN folder_hierarchy fh ON f2.id = fh.parent
                    )
                    SELECT id FROM folder_hierarchy
                )
            )";
        }

        $query .= " ORDER BY f.name ASC";

        $statement = $this->dbh->prepare($query);
        $statement->execute($params);

        if ($statement->rowCount() > 0) {
            $statement->setFetchMode(\PDO::FETCH_ASSOC);
            while ($row = $statement->fetch()) {
                $folders[$row['id']] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'parent' => $row['parent'],
                    'slug' => $row['slug'],
                    'public' => $row['public'],
                    'children_count' => (int)$row['children_count'],
                    'files_count' => (int)$row['files_count'],
                ];
            }
        }

        return $folders;
    }

    /**
     * Build nested tree structure from flat folder array
     */
    private function buildTree($folders, $parentId = null, $expandedFolderId = null)
    {
        $tree = [];

        // Find path to expanded folder for auto-expansion
        $expandPath = [];
        if ($expandedFolderId !== null && isset($folders[$expandedFolderId])) {
            $expandPath = $this->getAncestorIds($folders, $expandedFolderId);
            $expandPath[] = $expandedFolderId;
        }

        foreach ($folders as $folder) {
            if ($folder['parent'] == $parentId) {
                $children = $this->buildTree($folders, $folder['id'], $expandedFolderId);
                $hasChildren = !empty($children) || $folder['children_count'] > 0;

                // Determine if this folder should be expanded
                $isExpanded = in_array($folder['id'], $expandPath);
                $isActive = ($folder['id'] == $expandedFolderId);

                $tree[] = [
                    'id' => $folder['id'],
                    'name' => $folder['name'],
                    'slug' => $folder['slug'],
                    'parent' => $folder['parent'],
                    'public' => $folder['public'],
                    'has_children' => $hasChildren,
                    'children_count' => $folder['children_count'],
                    'files_count' => $folder['files_count'],
                    'is_expanded' => $isExpanded,
                    'is_active' => $isActive,
                    'children' => $children,
                ];
            }
        }

        return $tree;
    }

    /**
     * Get all ancestor IDs for a folder
     */
    private function getAncestorIds($folders, $folderId)
    {
        $ancestors = [];
        $currentId = $folderId;

        while ($currentId !== null && isset($folders[$currentId])) {
            $parentId = $folders[$currentId]['parent'];
            if ($parentId !== null) {
                $ancestors[] = $parentId;
            }
            $currentId = $parentId;
        }

        return $ancestors;
    }

    /**
     * Get folder tree as JSON for AJAX requests
     */
    function getFolderTreeJson($arguments = [], $expandedFolderId = null)
    {
        $tree = $this->getFolderTree($arguments, $expandedFolderId);
        return json_encode($tree);
    }
}
