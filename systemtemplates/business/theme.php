<?php
/*
Theme name: Business Professional System Theme
URI: https://www.projectsend.org/
Author: ProjectSend
Author URI: https://www.projectsend.org/
Author e-mail: contact@projectsend.org
Description: A professional business theme for the system interface with refined colors, enhanced spacing, and corporate styling. Perfect for enterprise environments.
*/

// Theme configuration
return [
    'name' => 'Business Professional System Theme',
    'slug' => 'business',
    'version' => '1.0.0',
    'features' => [
        'dark_mode' => true,
        'responsive' => true,
        'custom_colors' => true,
    ],
    'css_files' => [
        'main.css',
    ],
    'settings' => [
        'primary_color' => [
            'type' => 'color',
            'label' => 'Primary Color',
            'default' => '#1e3a5f',
        ],
        'accent_color' => [
            'type' => 'color',
            'label' => 'Accent Color',
            'default' => '#3498db',
        ],
    ],
];
