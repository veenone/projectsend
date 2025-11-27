<?php
/**
 * Database Upgrade: Add metadata display options
 *
 * This upgrade adds default options for controlling which S3 metadata fields
 * are displayed in the file information modal for public and internal users.
 *
 * Changes:
 * - Add metadata_show_created_by option (default: 1)
 * - Add metadata_show_created_by_email option (default: 1)
 * - Add metadata_show_created_by_title option (default: 1)
 * - Add metadata_show_modified_by option (default: 1)
 * - Add metadata_show_modified_date option (default: 1)
 * - Add metadata_show_is_versioned option (default: 1)
 * - Add metadata_show_is_current_version option (default: 1)
 * - Add metadata_show_version_id option (default: 1)
 */

function upgrade_2025112601()
{
    // Add default metadata display options (all enabled by default)
    $metadata_options = [
        'metadata_show_created_by' => '1',
        'metadata_show_created_by_email' => '1',
        'metadata_show_created_by_title' => '1',
        'metadata_show_modified_by' => '1',
        'metadata_show_modified_date' => '1',
        'metadata_show_is_versioned' => '1',
        'metadata_show_is_current_version' => '1',
        'metadata_show_version_id' => '1',
    ];

    foreach ($metadata_options as $option_name => $default_value) {
        // Only add if option doesn't exist
        $current_value = get_option($option_name);
        if ($current_value === null || $current_value === false) {
            save_option($option_name, $default_value);
        }
    }

    // Update database version
    save_option('database_version', '2025112601');
}
