<?php
/**
 * Database Upgrade: Fix role_permissions unique constraint
 *
 * The old unique constraint was on (role_level, permission) which prevents
 * multiple roles from having the same permission when they share the same role_level.
 * This upgrade changes the constraint to (role_id, permission) which is correct.
 *
 * Also sets a default value for role_level column.
 */

function upgrade_2025112702()
{
    global $dbh;

    try {
        // Drop the old unique constraint on (role_level, permission)
        try {
            $dbh->exec("ALTER TABLE " . TABLE_ROLE_PERMISSIONS . " DROP INDEX role_permission");
        } catch (Exception $e) {
            // Index might not exist, continue
        }

        // Check if the new unique index on (role_id, permission) exists
        $statement = $dbh->query("SHOW INDEX FROM " . TABLE_ROLE_PERMISSIONS . " WHERE Key_name = 'role_id_permission'");
        if ($statement->rowCount() == 0) {
            // Add the correct unique constraint on (role_id, permission)
            $dbh->exec("ALTER TABLE " . TABLE_ROLE_PERMISSIONS . " ADD UNIQUE INDEX role_id_permission (role_id, permission)");
        }

        // Set default value for role_level column
        $dbh->exec("ALTER TABLE " . TABLE_ROLE_PERMISSIONS . " MODIFY COLUMN role_level INT NOT NULL DEFAULT 0");

    } catch (Exception $e) {
        error_log("Upgrade 2025112702 error: " . $e->getMessage());
    }

    // Update database version
    save_option('database_version', '2025112702');
}
