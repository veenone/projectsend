<?php
/**
 * Add performance indexes for file management
 *
 * This upgrade adds indexes to improve query performance when managing
 * large numbers of files (10,000+). Indexes are added on frequently
 * queried columns to speed up filtering, sorting, and pagination.
 *
 * @version 2025112001
 */
function upgrade_2025112001()
{
    global $dbh;

    $indexes_to_add = [
        // Index for folder filtering
        [
            'table' => TABLE_FILES,
            'name' => 'idx_folder_id',
            'column' => 'folder_id',
            'description' => 'Improves performance when filtering files by folder'
        ],
        // Index for uploader filtering
        [
            'table' => TABLE_FILES,
            'name' => 'idx_uploader',
            'column' => 'uploader',
            'description' => 'Improves performance when filtering files by uploader'
        ],
        // Index for timestamp sorting
        [
            'table' => TABLE_FILES,
            'name' => 'idx_timestamp',
            'column' => 'timestamp',
            'description' => 'Improves performance when sorting files by date'
        ],
        // Composite index for folder + timestamp (common query pattern)
        [
            'table' => TABLE_FILES,
            'name' => 'idx_folder_timestamp',
            'columns' => ['folder_id', 'timestamp'],
            'description' => 'Improves performance for paginated folder views'
        ],
        // Index for file_id in relations table (if not already indexed by FK)
        [
            'table' => TABLE_FILES_RELATIONS,
            'name' => 'idx_file_id',
            'column' => 'file_id',
            'description' => 'Improves performance for batch loading file assignments'
        ],
        // Index for file_id in categories relations table (if not already indexed by FK)
        [
            'table' => TABLE_CATEGORIES_RELATIONS,
            'name' => 'idx_file_id',
            'column' => 'file_id',
            'description' => 'Improves performance for batch loading file categories'
        ],
        // Index for hidden status in relations table
        [
            'table' => TABLE_FILES_RELATIONS,
            'name' => 'idx_hidden',
            'column' => 'hidden',
            'description' => 'Improves performance when filtering by visibility status'
        ],
    ];

    foreach ($indexes_to_add as $index) {
        try {
            // Build the index SQL
            if (isset($index['columns'])) {
                // Multiple columns (composite index)
                $columns_str = implode(', ', $index['columns']);
                $index_sql = "ALTER TABLE " . $index['table'] . "
                              ADD INDEX " . $index['name'] . " (" . $columns_str . ")";
            } else {
                // Single column
                $index_sql = "ALTER TABLE " . $index['table'] . "
                              ADD INDEX " . $index['name'] . " (" . $index['column'] . ")";
            }

            $statement = $dbh->prepare($index_sql);
            $statement->execute();

            error_log("ProjectSend: Added index " . $index['name'] . " to " . $index['table'] . " - " . $index['description']);
        } catch (PDOException $e) {
            // Index might already exist
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                error_log("ProjectSend: Index " . $index['name'] . " already exists on " . $index['table']);
            } else {
                error_log("ProjectSend: Could not add index " . $index['name'] . " to " . $index['table'] . ": " . $e->getMessage());
            }
        }
    }

    // Log the optimization
    $logger = new \ProjectSend\Classes\ActionsLog;
    $logger->addEntry([
        'action' => 50, // System configuration change
        'owner_id' => 1, // System
        'details' => [
            'upgrade' => '2025112001',
            'description' => 'Added performance indexes for file management',
            'indexes_added' => count($indexes_to_add),
        ],
    ]);

    error_log("ProjectSend: File management performance indexes upgrade completed");
}
