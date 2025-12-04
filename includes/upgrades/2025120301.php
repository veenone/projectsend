<?php
/**
 * Database Upgrade: Add ONLYOFFICE Document Server integration options
 *
 * Adds configuration options for ONLYOFFICE Document Server integration
 * which enables real-time editing of Office documents (docx, xlsx, pptx).
 */

function upgrade_2025120301()
{
    global $dbh;

    try {
        // ONLYOFFICE integration options
        $options = [
            ['onlyoffice_enabled', '0'],
            ['onlyoffice_document_server_url', ''],
            ['onlyoffice_jwt_enabled', '1'],
            ['onlyoffice_jwt_secret', ''],
            ['onlyoffice_jwt_header', 'Authorization'],
            ['onlyoffice_file_token_expiry', '3600'], // 1 hour in seconds
        ];

        $statement = $dbh->prepare("INSERT IGNORE INTO " . TABLE_OPTIONS . " (name, value) VALUES (:name, :value)");

        foreach ($options as $option) {
            $statement->execute([
                ':name' => $option[0],
                ':value' => $option[1]
            ]);
        }

    } catch (Exception $e) {
        error_log("Upgrade 2025120301 error: " . $e->getMessage());
    }

    // Update database version
    save_option('database_version', '2025120301');
}
