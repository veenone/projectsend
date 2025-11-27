<?php
/**
 * Privacy options form configuration
 * Refactored to use array-based configuration - matches original exactly
 */

// Define the form sections and fields
$form_sections = [
    [
        'title' => __('Privacy', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'privacy_noindex_site',
                'label' => __("Prevent search engines from indexing this site", 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'enable_landing_for_all_files',
                'label' => __("Enable information page for private files", 'cftp_admin'),
                'note' => __("If enabled, the file information landing page will be available even for files that are not marked as private. Downloading them will stay restricted.", 'cftp_admin')
            ]
        ]
    ],
    [
        'title' => __('Downloads', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'select',
                'name' => 'privacy_record_downloads_ip_address',
                'label' => __('Log IP address and host for:', 'cftp_admin'),
                'options' => [
                    'all' => __('All downloads', 'cftp_admin'),
                    'anonymous' => __('Anonymous users only', 'cftp_admin'),
                    'none' => __('Never record IP address and host', 'cftp_admin')
                ],
                'required' => true
            ]
        ]
    ],
    [
        'title' => __('Public groups and files listings page', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'public_listing_page_enable',
                'label' => __('Enable page', 'cftp_admin'),
                'note' => __('The url for the listings page is', 'cftp_admin') . '<br><a href="' . PUBLIC_LANDING_URI . '" target="_blank" id="public_landing_uri">' . PUBLIC_LANDING_URI . '</a> <i class="fa fa-copy copy_text" data-target="public_landing_uri"></i>'
            ],
            [
                'type' => 'checkbox',
                'name' => 'public_listing_logged_only',
                'label' => __('Only for logged in clients', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'public_listing_show_all_files',
                'label' => __('Inside groups show all files, including those that are not marked as public.', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'public_listing_use_download_link',
                'label' => __('On public files, show the download link.', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'public_listing_enable_preview',
                'label' => __('Enable files previews', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'public_listing_home_show_link',
                'label' => __('Show a link to the public page under the log in form', 'cftp_admin')
            ]
        ]
    ],
    [
        'title' => __('File Metadata Display', 'cftp_admin'),
        'description' => __('Configure which metadata fields are shown in the file information modal for public and internal users.', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'metadata_show_created_by',
                'label' => __('Show "Created By"', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'metadata_show_created_by_email',
                'label' => __('Show "Creator Email"', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'metadata_show_created_by_title',
                'label' => __('Show "Creator Title"', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'metadata_show_modified_by',
                'label' => __('Show "Modified By"', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'metadata_show_modified_date',
                'label' => __('Show "Modified Date"', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'metadata_show_is_versioned',
                'label' => __('Show "Versioned"', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'metadata_show_is_current_version',
                'label' => __('Show "Current Version"', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'metadata_show_version_id',
                'label' => __('Show "Version ID"', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'metadata_show_content_type',
                'label' => __('Show "Content Type"', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'metadata_show_crawl_depth',
                'label' => __('Show "Crawl Depth"', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'metadata_show_discovered_from',
                'label' => __('Show "Discovered From"', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'metadata_show_enriched_files',
                'label' => __('Show "Enriched Files"', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'metadata_show_enriched_version_label',
                'label' => __('Show "Enriched Version Label"', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'metadata_show_sharepoint_file_size',
                'label' => __('Show "SharePoint File Size"', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'metadata_show_sharepoint_url',
                'label' => __('Show "SharePoint URL"', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'metadata_show_version_url',
                'label' => __('Show "Version URL"', 'cftp_admin')
            ]
        ],
        'divider' => false // No divider at the end
    ]
];

// Render the form sections
render_options_form_sections($form_sections);
