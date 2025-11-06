<?php
namespace ProjectSend\Classes;

use \PDO;

/**
 * Helper class for importing folder structures from external storage paths
 * Parses S3 keys and creates corresponding folder hierarchy in ProjectSend
 *
 * Example:
 * S3 key: Central_R_D/CoE_Colombes/Documents/file.pdf
 * Creates: Central_R_D → CoE_Colombes → Documents (nested folders)
 */
class FolderStructureImporter
{
    protected $dbh;
    protected $user_id;
    protected $folder_cache = []; // Cache to avoid duplicate lookups
    protected $created_folders = []; // Track newly created folders

    public function __construct($user_id = null)
    {
        global $dbh;
        $this->dbh = $dbh;
        $this->user_id = $user_id ?? CURRENT_USER_ID;
    }

    /**
     * Parse S3 key and extract folder path components
     *
     * @param string $s3_key Full S3 key (e.g., "folder/subfolder/file.pdf")
     * @return array ['path_components' => [...], 'filename' => '...']
     */
    public function parsePath($s3_key)
    {
        // Normalize slashes and remove leading/trailing slashes
        $s3_key = trim(str_replace('\\', '/', $s3_key), '/');

        // Split by forward slash
        $parts = explode('/', $s3_key);

        // Last part is the filename
        $filename = array_pop($parts);

        return [
            'path_components' => $parts,
            'filename' => $filename,
            'full_path' => $s3_key,
        ];
    }

    /**
     * Create folder structure from path components
     * Returns the ID of the deepest folder for file assignment
     *
     * @param array $path_components Array of folder names in order
     * @param int|null $user_id Owner of created folders
     * @return int|null Folder ID or null if no folders
     */
    public function getOrCreateFolderStructure($path_components, $user_id = null)
    {
        if (empty($path_components)) {
            return null;
        }

        $user_id = $user_id ?? $this->user_id;
        $parent_id = null;

        // Create folders recursively from root to leaf
        foreach ($path_components as $folder_name) {
            // Skip empty folder names
            if (trim($folder_name) === '') {
                continue;
            }

            $folder_id = $this->findOrCreateFolder($folder_name, $parent_id, $user_id);

            if (!$folder_id) {
                // If folder creation failed, return what we have so far
                return $parent_id;
            }

            // This folder becomes the parent for the next level
            $parent_id = $folder_id;
        }

        return $parent_id;
    }

    /**
     * Find existing folder or create new one
     * Uses caching to minimize database queries
     *
     * @param string $folder_name Name of the folder
     * @param int|null $parent_id Parent folder ID (null for root)
     * @param int $user_id Owner user ID
     * @return int|null Folder ID or null on failure
     */
    protected function findOrCreateFolder($folder_name, $parent_id, $user_id)
    {
        // Create cache key
        $cache_key = $this->getCacheKey($folder_name, $parent_id);

        // Check cache first
        if (isset($this->folder_cache[$cache_key])) {
            return $this->folder_cache[$cache_key];
        }

        // Check if folder already exists in database
        $existing_id = $this->findFolder($folder_name, $parent_id);

        if ($existing_id) {
            // Cache and return existing folder
            $this->folder_cache[$cache_key] = $existing_id;
            return $existing_id;
        }

        // Create new folder
        $folder_id = $this->createFolder($folder_name, $parent_id, $user_id);

        if ($folder_id) {
            // Cache the newly created folder
            $this->folder_cache[$cache_key] = $folder_id;
            $this->created_folders[] = [
                'id' => $folder_id,
                'name' => $folder_name,
                'parent_id' => $parent_id,
            ];
        }

        return $folder_id;
    }

    /**
     * Search for existing folder by name and parent
     *
     * @param string $folder_name
     * @param int|null $parent_id
     * @return int|null Folder ID or null if not found
     */
    protected function findFolder($folder_name, $parent_id)
    {
        $query = "SELECT id FROM " . TABLE_FOLDERS . " WHERE name = :name ";

        if ($parent_id === null) {
            $query .= "AND parent IS NULL";
        } else {
            $query .= "AND parent = :parent";
        }

        $query .= " LIMIT 1";

        $statement = $this->dbh->prepare($query);
        $statement->bindParam(':name', $folder_name, PDO::PARAM_STR);

        if ($parent_id !== null) {
            $statement->bindParam(':parent', $parent_id, PDO::PARAM_INT);
        }

        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['id'] : null;
    }

    /**
     * Create a new folder in the database
     *
     * @param string $folder_name
     * @param int|null $parent_id
     * @param int $user_id
     * @return int|null Created folder ID or null on failure
     */
    protected function createFolder($folder_name, $parent_id, $user_id)
    {
        try {
            $folder = new Folder();
            $folder->set([
                'name' => $folder_name,
                'parent' => $parent_id,
                'public' => 1, // Make imported folders public by default
            ]);

            // Manually set user_id since create() uses CURRENT_USER_ID
            $uuid = uniqid();
            $slugify = new \Cocur\Slugify\Slugify();
            $slug = $slugify->slugify($folder_name);
            $public = 1;

            $statement = $this->dbh->prepare(
                "INSERT INTO " . TABLE_FOLDERS . " (uuid, name, slug, parent, public, user_id)
                 VALUES (:uuid, :name, :slug, :parent, :public, :user_id)"
            );

            $statement->bindParam(':uuid', $uuid);
            $statement->bindParam(':name', $folder_name);
            $statement->bindParam(':slug', $slug);

            if ($parent_id === null) {
                $statement->bindValue(':parent', null, PDO::PARAM_NULL);
            } else {
                $statement->bindParam(':parent', $parent_id, PDO::PARAM_INT);
            }

            $statement->bindParam(':public', $public, PDO::PARAM_INT);
            $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $statement->execute();

            $folder_id = $this->dbh->lastInsertId();

            // Log folder creation
            $logger = new ActionsLog();
            $logger->addEntry([
                'action' => 26, // Folder created
                'owner_id' => $user_id,
                'affected_account_name' => $folder_name,
            ]);

            return (int)$folder_id;
        } catch (\Exception $e) {
            error_log("Failed to create folder '$folder_name': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate cache key for folder lookup
     *
     * @param string $folder_name
     * @param int|null $parent_id
     * @return string
     */
    protected function getCacheKey($folder_name, $parent_id)
    {
        return $folder_name . '|' . ($parent_id ?? 'root');
    }

    /**
     * Get list of newly created folders during this import session
     *
     * @return array
     */
    public function getCreatedFolders()
    {
        return $this->created_folders;
    }

    /**
     * Reset the folder cache (useful between imports)
     */
    public function clearCache()
    {
        $this->folder_cache = [];
        $this->created_folders = [];
    }

    /**
     * Import file with folder structure preservation
     * Convenience method that combines parsing and folder creation
     *
     * @param string $s3_key Full S3 key
     * @param int|null $user_id Owner user ID
     * @return array ['folder_id' => int|null, 'filename' => string, 'path_components' => array]
     */
    public function importPath($s3_key, $user_id = null)
    {
        $parsed = $this->parsePath($s3_key);
        $folder_id = $this->getOrCreateFolderStructure($parsed['path_components'], $user_id);

        return [
            'folder_id' => $folder_id,
            'filename' => $parsed['filename'],
            'path_components' => $parsed['path_components'],
            'full_path' => $parsed['full_path'],
        ];
    }

    /**
     * Get folder hierarchy path as string
     *
     * @param int $folder_id
     * @return string Folder path (e.g., "Root / Folder1 / Folder2")
     */
    public function getFolderPath($folder_id)
    {
        if (!$folder_id) {
            return '';
        }

        $folder = new Folder($folder_id);
        $hierarchy = $folder->getHierarchy();

        if (empty($hierarchy)) {
            return '';
        }

        $path_parts = [];
        foreach ($hierarchy as $item) {
            $path_parts[] = $item['name'];
        }

        return implode(' / ', $path_parts);
    }

    /**
     * Preview folder structure that would be created for given S3 keys
     * Does not actually create folders, just analyzes paths
     *
     * @param array $s3_keys Array of S3 keys
     * @return array Statistics and folder structure preview
     */
    public function previewFolderStructure($s3_keys)
    {
        $folders_to_create = [];
        $file_assignments = [];

        foreach ($s3_keys as $s3_key) {
            $parsed = $this->parsePath($s3_key);

            if (!empty($parsed['path_components'])) {
                $path = '';
                foreach ($parsed['path_components'] as $component) {
                    $path .= ($path ? '/' : '') . $component;
                    $folders_to_create[$path] = $parsed['path_components'];
                }

                $file_assignments[$s3_key] = implode(' / ', $parsed['path_components']);
            } else {
                $file_assignments[$s3_key] = __('Root', 'cftp_admin');
            }
        }

        return [
            'total_files' => count($s3_keys),
            'unique_folders' => count($folders_to_create),
            'folder_paths' => array_keys($folders_to_create),
            'file_assignments' => $file_assignments,
        ];
    }
}
