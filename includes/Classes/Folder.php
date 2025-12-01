<?php
namespace ProjectSend\Classes;

use \ProjectSend\Classes\Validation;
use \Cocur\Slugify\Slugify;
use \PDO;

class Folder
{
    protected $dbh;
    protected $logger;
    protected $id;
    protected $uuid;
    protected $name;
    protected $slug;
    protected $parent;
    protected $user_id;
    protected $public;
    protected $validation_passed;
    protected $validation_errors;

    public function __construct($id = null)
    {
        global $dbh;

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;

        if (!empty($id)) {
            $this->get((int)$id);
        }
    }

    public function __get($name)
    {
        return html_output($this->$name);
    }

    /**
     * Set the ID
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Return the ID
     * @return int
     */
    public function getId()
    {
        if (!empty($this->id)) {
            return $this->id;
        }

        return false;
    }

    /**
     * Set the properties when editing
     */
    public function set($arguments = [])
    {
		$this->name = (!empty($arguments['name'])) ? encode_html($arguments['name']) : null;
        $this->parent = (!empty($arguments['parent'])) ? encode_html($arguments['parent']) : null;
        $this->public = (!empty($arguments['public'])) ? (int)$arguments['public'] : 0;
    }

    /**
     * Get existing user data from the database
     * @return bool
     */
    public function get($id)
    {
        $this->id = $id;

        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_FOLDERS . " WHERE id=:id");
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        if ($statement->rowCount() == 0) {
            return false;
        }
    
        while ($row = $statement->fetch() ) {
            $this->uuid = html_output($row['uuid']);
            $this->name = html_output($row['name']);
            $this->slug = html_output($row['slug']);
            $this->parent = html_output($row['parent']);
            $this->user_id = html_output($row['user_id']);
            $this->public = html_output($row['public']);
        }
    }

    public function create()
    {
        if (empty($this->name)) {
            return false;
        }

        if (!$this->validate()) {
            return false;
        }

        try {
            $slugify = new Slugify();
    
            $this->uuid = uniqid();
            $this->parent = (!empty($this->parent)) ? $this->parent : null;
            $this->slug = $slugify->slugify($this->name);
            $this->user_id = CURRENT_USER_ID;
            $public = 1;
    
            $statement = $this->dbh->prepare("INSERT INTO " . TABLE_FOLDERS . " (uuid, name, slug, parent, public, user_id) VALUES (:uuid, :name, :slug, :parent, :public, :user_id)");
            $statement->bindParam(':uuid', $this->uuid);
            $statement->bindParam(':name', $this->name);
            $statement->bindParam(':slug', $this->slug);
            $statement->bindParam(':parent', $this->parent);
            $statement->bindParam(':public', $public);
            $statement->bindParam(':user_id', $this->user_id);
            $statement->execute();

            $this->id = $this->dbh->lastInsertId();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getData()
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent' => $this->parent,
            'user_id' => $this->user_id,
            'public' => $this->public,
        ];
    }

    public function userCanEdit($user_id)
    {
        // Use current_role_in() for current user (faster, no DB query)
        if ($user_id == CURRENT_USER_ID) {
            if (current_role_in(['System Administrator', 'Account Manager', 'Uploader'])) {
                return true;
            }
        } else {
            // For other users, we need to query
            $user = new \ProjectSend\Classes\Users($user_id);
            if (in_array($user->role, ['System Administrator', 'Account Manager', 'Uploader'])) {
                return true;
            }
        }

        if ($this->user_id == $user_id) {
            return true;
        }

        return false;
    }

    public function userCanNavigate($user_id)
    {
        if ($this->public == 1) {
            return true;
        }

        // If top level is public, this is too
        $hierarchy = $this->getHierarchy();
        $top_level = $hierarchy[array_key_last($hierarchy)];
        if ($top_level['public'] == 1) {
            return true;
        }

        // Check if user can edit (created the folder or is admin)
        if ($this->userCanEdit($user_id)) {
            return true;
        }

        // Check if user has files in this folder via direct assignment or group membership
        $query = "SELECT COUNT(*) as count FROM " . TABLE_FILES . " f
                  JOIN " . TABLE_FILES_RELATIONS . " fr ON f.id = fr.file_id
                  WHERE f.folder_id = :folder_id
                  AND fr.hidden = 0
                  AND (
                      fr.client_id = :user_id
                      OR fr.group_id IN (
                          SELECT group_id
                          FROM " . TABLE_MEMBERS . "
                          WHERE COALESCE(user_id, client_id) = :user_id_groups
                      )
                  )";

        $stmt = $this->dbh->prepare($query);
        $stmt->execute([
            ':folder_id' => $this->id,
            ':user_id' => $user_id,
            ':user_id_groups' => $user_id
        ]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    }

    public function userCanDelete($user_id)
    {
        // Use current_role_in() for current user (faster, no DB query)
        if ($user_id == CURRENT_USER_ID) {
            if (current_role_in(['System Administrator', 'Account Manager', 'Uploader'])) {
                return true;
            }
        } else {
            // For other users, we need to query
            $user = new \ProjectSend\Classes\Users($user_id);
            if (in_array($user->role, ['System Administrator', 'Account Manager', 'Uploader'])) {
                return true;
            }
        }

        if ($this->user_id == $user_id) {
            return true;
        }

        return false;
    }

    public function setNewParent($user_id, $new_parent_id)
    {
        if (empty($this->id)) {
            return false;
        }

        if ($this->userCanEdit($user_id) && $this->validate()) {
            if (empty($new_parent_id)) {
                $new_parent_id = null;
            } else {
                $new_parent_id = (int)$new_parent_id;
            }

            if ($new_parent_id == $this->id) {
                return false;
            }

            if ($this->validate()) {
                $statement = $this->dbh->prepare("UPDATE " . TABLE_FOLDERS . " SET parent=:parent_id WHERE id=:id");
                $statement->bindParam(':id', $this->id);
                $statement->bindParam(':parent_id', $new_parent_id);
                if ($statement->execute()) {
                    $this->parent = $new_parent_id;
                    return true;
                }
            }

            $this->get($this->id);
        }

        return false;
    }

    public function rename($name)
    {
        if (empty($this->id)) {
            return false;
        }

        if ($this->userCanEdit(CURRENT_USER_ID)) {
            $this->name = $name;

            if ($this->validate()) {
                $statement = $this->dbh->prepare("UPDATE " . TABLE_FOLDERS . " SET name=:name WHERE id=:id");
                $statement->bindParam(':id', $this->id);
                $statement->bindParam(':name', $name);
                if ($statement->execute()) {
                    return true;
                }
            }
        }

        // Refresh data
        $this->get($this->id);

        return false;
    }

    public function delete()
    {
        if (!$this->userCanDelete(CURRENT_USER_ID)) {
            return false;
        }

        $deleted = [
            'files' => [],
            'folders' => [],
        ];

        // Get all descendant folder IDs in one query (optimized)
        $descendant_ids = $this->getAllDescendantIds($this->id);

        if (empty($descendant_ids)) {
            return $deleted;
        }

        // Reverse to delete children before parents
        $descendant_ids = array_reverse($descendant_ids);

        // Get all files in all folders in one batch query
        $placeholders = implode(',', array_fill(0, count($descendant_ids), '?'));
        $stmt = $this->dbh->prepare("SELECT id, folder_id FROM " . TABLE_FILES . " WHERE folder_id IN ($placeholders)");
        $stmt->execute($descendant_ids);
        $all_files = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Group files by folder
        $files_by_folder = [];
        foreach ($all_files as $file) {
            $files_by_folder[$file['folder_id']][] = $file['id'];
        }

        // Delete folders and their files
        foreach ($descendant_ids as $folder_id) {
            // Delete folder from database directly (we already checked permissions for root)
            $stmt = $this->dbh->prepare("DELETE FROM " . TABLE_FOLDERS . " WHERE id = ?");
            if (!$stmt->execute([$folder_id])) {
                continue;
            }

            $deleted['folders'][] = $folder_id;

            // Delete files in this folder
            if (isset($files_by_folder[$folder_id])) {
                foreach ($files_by_folder[$folder_id] as $file_id) {
                    $file = new \ProjectSend\Classes\Files($file_id);
                    $result = $file->deleteFiles();
                    if ($result['status'] === 'success') {
                        $deleted['files'][] = $file_id;
                    }
                }
            }
        }

        return $deleted;
    }

    /**
     * Get all descendant folder IDs using optimized recursive query
     * @param int $folder_id
     * @return array
     */
    private function getAllDescendantIds($folder_id)
    {
        $all_ids = [(int)$folder_id];
        $to_process = [(int)$folder_id];

        while (!empty($to_process)) {
            $placeholders = implode(',', array_fill(0, count($to_process), '?'));
            $stmt = $this->dbh->prepare("SELECT id FROM " . TABLE_FOLDERS . " WHERE parent IN ($placeholders)");
            $stmt->execute($to_process);
            $children = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            if (empty($children)) {
                break;
            }

            $all_ids = array_merge($all_ids, $children);
            $to_process = $children;
        }

        return $all_ids;
    }

    public function deleteFromDatabase()
    {
        $sql = $this->dbh->prepare("DELETE FROM " . TABLE_FOLDERS . " WHERE id = :id");
        $sql->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $sql->execute();
    }

    public function currentUserCanAssignToFolder()
    {
        if (current_user_can('edit_files')) {
            return true;
        }

        if ($this->user_id == CURRENT_USER_ID) {
            return true;
        }

        if ($this->public == '1') {
            return client_can_assign_to_public_folder(CURRENT_USER_ID);
        }
        
        return false;
    }

    private function validate()
    {
        global $json_strings;

        $validation = new \ProjectSend\Classes\Validation;

        $validation_items = [
            $this->name => [
                'required' => ['error' => $json_strings['validation']['no_name']],
            ],
            $this->parent => [
                'number' => ['error' => sprintf($json_strings['validation']['numeric'], 'parent')],
            ],
            $this->user_id => [
                'number' => ['error' => sprintf($json_strings['validation']['numeric'], 'user_id')],
            ],
        ];

        $validation->validate_items($validation_items);

        if ($validation->passed()) {
            $this->validation_passed = true;
            return true;
        } else {
            $this->validation_passed = false;
            $this->validation_errors = $validation->list_errors();
        }

        return false;
    }

    function getHierarchy()
    {
        return $this->getHierarchyFrom($this->id);
    }

    function getHierarchyFrom($folder_id = null, array $hierarchy = [])
    {
        $folder_id = (int)$folder_id;

        // Add current folder
        $folder = new \ProjectSend\Classes\Folder($folder_id);
        $hierarchy[] = $folder->getData();

        // Parents
        if ($folder_id != null) {
            $query = "SELECT * FROM " . TABLE_FOLDERS . " WHERE id=:id";
            $params[':id'] = (int)$folder_id;
            $statement = $this->dbh->prepare($query);
            $statement->execute($params);
            if ($statement->rowCount() > 0) {
                $statement->setFetchMode(\PDO::FETCH_ASSOC);
                while ($row = $statement->fetch()) {
                    if ($row['parent'] != null) {   
                        $hierarchy = $this->getHierarchyFrom($row['parent'], $hierarchy);
                    }
                }
            }
        }
    
        return $hierarchy;
    }

    function getAllDescendants($folder_id = null, array $descendants = [])
    {
        $folder_id = (int)$folder_id;

        // Add current folder
        $folder = new \ProjectSend\Classes\Folder($folder_id);
        $descendants[] = $folder->getData();

        // Children
        if ($folder_id != null) {
            $query = "SELECT * FROM " . TABLE_FOLDERS . " WHERE parent=:id";
            $params[':id'] = (int)$folder_id;
            $statement = $this->dbh->prepare($query);
            $statement->execute($params);
            if ($statement->rowCount() > 0) {
                $statement->setFetchMode(\PDO::FETCH_ASSOC);
                while ($row = $statement->fetch()) {
                    $descendants = $this->getAllDescendants($row['id'], $descendants);
                }
            }
        }
    
        return $descendants;
    }
}
