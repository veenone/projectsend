<?php
function upgrade_2025111901()
{
    global $dbh;

    // Add s3_metadata column for storing S3 custom metadata and tags
    if (!column_exists_2025111901(TABLE_FILES, 's3_metadata')) {
        $query = "ALTER TABLE `" . TABLE_FILES . "` ADD COLUMN `s3_metadata` TEXT NULL AFTER `integration_id`";
        try {
            $statement = $dbh->prepare($query);
            $statement->execute();
            error_log("ProjectSend: Added s3_metadata column to " . TABLE_FILES);
        } catch (PDOException $e) {
            error_log("ProjectSend: Error adding s3_metadata column: " . $e->getMessage());
        }
    }
}

/**
 * Helper function to check if a column exists
 */
function column_exists_2025111901($table, $column) {
    global $dbh;
    $sql = "SHOW COLUMNS FROM $table LIKE '$column'";
    $statement = $dbh->prepare($sql);
    $statement->execute();
    return $statement->rowCount() > 0;
}
