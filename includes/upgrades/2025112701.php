<?php
/**
 * Database Upgrade: Add storage restrictions for users and groups
 *
 * This upgrade adds columns to store allowed storage integrations for users and groups.
 * When a user uploads files, they will only be able to select from their allowed storage options.
 * Restrictions can be set at both user level and group level.
 *
 * Changes:
 * - Add allowed_storage column to users table (JSON array of integration IDs)
 * - Add allowed_storage column to groups table (JSON array of integration IDs)
 */

function upgrade_2025112701()
{
    global $dbh;

    // Add allowed_storage column to users table
    $statement = $dbh->query("SHOW COLUMNS FROM " . TABLE_USERS . " LIKE 'allowed_storage'");
    if ($statement->rowCount() == 0) {
        $dbh->query("ALTER TABLE " . TABLE_USERS . " ADD COLUMN allowed_storage TEXT NULL DEFAULT NULL COMMENT 'JSON array of allowed storage integration IDs. NULL means all allowed. Use \"local\" for local storage.'");
    }

    // Add allowed_storage column to groups table
    $statement = $dbh->query("SHOW COLUMNS FROM " . TABLE_GROUPS . " LIKE 'allowed_storage'");
    if ($statement->rowCount() == 0) {
        $dbh->query("ALTER TABLE " . TABLE_GROUPS . " ADD COLUMN allowed_storage TEXT NULL DEFAULT NULL COMMENT 'JSON array of allowed storage integration IDs. NULL means all allowed. Use \"local\" for local storage.'");
    }

    // Update database version
    save_option('database_version', '2025112701');
}
