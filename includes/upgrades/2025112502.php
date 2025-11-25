<?php
/**
 * Database Upgrade: Extend Groups to Support All User Types
 *
 * Currently, groups only work with clients because tbl_members uses client_id.
 * This upgrade extends groups to support all user types (clients, internal users, etc.)
 * by adding a user_id column and migrating data.
 *
 * Changes:
 * - Add user_id column to tbl_members
 * - Copy existing client_id data to user_id
 * - Keep client_id for backward compatibility (marked as deprecated)
 * - Update indexes for better performance
 */

function upgrade_2025112502()
{
    global $dbh;

    // Step 1: Check if tbl_members table exists
    if (!table_exists(TABLE_MEMBERS)) {
        error_log("tbl_members table does not exist, skipping upgrade 2025112502");
        return;
    }

    // Step 2: Check if user_id column already exists
    $check_column_sql = "SHOW COLUMNS FROM " . TABLE_MEMBERS . " LIKE 'user_id'";
    $statement = $dbh->prepare($check_column_sql);
    $statement->execute();

    if ($statement->rowCount() > 0) {
        // Column already exists, skip migration
        error_log("user_id column already exists in tbl_members, skipping data migration");
    } else {
        // Add user_id column after client_id
        $add_column_sql = "ALTER TABLE " . TABLE_MEMBERS . "
                          ADD COLUMN user_id INT(11) NULL AFTER client_id,
                          ADD INDEX idx_user_id (user_id)";
        $statement = $dbh->prepare($add_column_sql);
        $statement->execute();

        // Copy data from client_id to user_id
        $copy_data_sql = "UPDATE " . TABLE_MEMBERS . " SET user_id = client_id WHERE user_id IS NULL";
        $statement = $dbh->prepare($copy_data_sql);
        $statement->execute();

        // Make user_id NOT NULL now that data is copied
        $make_not_null_sql = "ALTER TABLE " . TABLE_MEMBERS . " MODIFY COLUMN user_id INT(11) NOT NULL";
        $statement = $dbh->prepare($make_not_null_sql);
        $statement->execute();

        // Add foreign key constraint to user_id
        try {
            $add_fk_sql = "ALTER TABLE " . TABLE_MEMBERS . "
                          ADD CONSTRAINT fk_members_user_id
                          FOREIGN KEY (user_id) REFERENCES " . TABLE_USERS . "(id)
                          ON DELETE CASCADE ON UPDATE CASCADE";
            $statement = $dbh->prepare($add_fk_sql);
            $statement->execute();
        } catch (PDOException $e) {
            // Foreign key might already exist or other constraint issue
            error_log("Could not add foreign key to user_id: " . $e->getMessage());
        }
    }

    // Step 3: Add composite index for better query performance
    try {
        $check_index_sql = "SHOW INDEX FROM " . TABLE_MEMBERS . " WHERE Key_name = 'idx_user_group'";
        $statement = $dbh->prepare($check_index_sql);
        $statement->execute();

        if ($statement->rowCount() == 0) {
            $add_index_sql = "ALTER TABLE " . TABLE_MEMBERS . "
                             ADD UNIQUE INDEX idx_user_group (user_id, group_id)";
            $statement = $dbh->prepare($add_index_sql);
            $statement->execute();
        }
    } catch (PDOException $e) {
        // Index might conflict with existing data or constraints
        error_log("Could not add composite index: " . $e->getMessage());
    }

    // Step 4: Update database version
    save_option('database_version', '2025112502');

    // Note: We keep client_id column for backward compatibility
    // New code should use user_id, but old code referencing client_id will still work
    // since both columns contain the same data
}
