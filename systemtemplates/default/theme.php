<?php
/*
Theme name: Default System Theme
URI: https://www.projectsend.org/
Author: ProjectSend
Author URI: https://www.projectsend.org/
Author e-mail: contact@projectsend.org
Description: The default system theme with clean, modern design based on Bootstrap 5. Supports light and dark modes with smooth transitions.
*/

// Theme configuration
return [
    'name' => 'Default System Theme',
    'slug' => 'default',
    'version' => '1.0.0',
    'features' => [
        'dark_mode' => true,
        'responsive' => true,
        'custom_colors' => false,
    ],
    'css_files' => [
        'main.css',
    ],
    'settings' => [
        // Future: Add customizable settings here
    ],
];
