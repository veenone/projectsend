<?php
/**
 * Database Upgrade: Add additional metadata display options
 *
 * This upgrade adds default options for controlling additional S3 metadata fields
 * related to SharePoint integration.
 *
 * Changes:
 * - Add metadata_show_content_type option (default: 1)
 * - Add metadata_show_crawl_depth option (default: 1)
 * - Add metadata_show_discovered_from option (default: 1)
 * - Add metadata_show_enriched_files option (default: 1)
 * - Add metadata_show_enriched_version_label option (default: 1)
 * - Add metadata_show_sharepoint_file_size option (default: 1)
 * - Add metadata_show_sharepoint_url option (default: 1)
 * - Add metadata_show_version_url option (default: 1)
 */

function upgrade_2025112602()
{
    // Add default metadata display options (all enabled by default)
    $metadata_options = [
        'metadata_show_content_type' => '1',
        'metadata_show_crawl_depth' => '1',
        'metadata_show_discovered_from' => '1',
        'metadata_show_enriched_files' => '1',
        'metadata_show_enriched_version_label' => '1',
        'metadata_show_sharepoint_file_size' => '1',
        'metadata_show_sharepoint_url' => '1',
        'metadata_show_version_url' => '1',
    ];

    foreach ($metadata_options as $option_name => $default_value) {
        // Only add if option doesn't exist
        $current_value = get_option($option_name);
        if ($current_value === null || $current_value === false) {
            save_option($option_name, $default_value);
        }
    }

    // Update database version
    save_option('database_version', '2025112602');
}
