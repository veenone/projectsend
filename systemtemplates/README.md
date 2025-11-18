# System Templates

This directory contains themes for the ProjectSend admin/system interface.

## Structure

Each system theme should have the following structure:

```
themename/
├── theme.php       # Theme metadata and configuration
├── main.css        # Theme-specific styles
└── screenshot.png  # Theme preview image (optional)
```

## Theme Metadata

The `theme.php` file should return an array with the following structure:

```php
<?php
return [
    'name' => 'Theme Name',
    'slug' => 'theme-slug',
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
        // Optional theme-specific settings
    ],
];
```

## Available Themes

### Default
The standard ProjectSend system theme with clean, modern design.

### Business Professional
A professional theme with refined colors and enhanced spacing for corporate environments.

## Creating a New Theme

1. Create a new directory under `systemtemplates/` with your theme slug
2. Create a `theme.php` file with theme metadata
3. Create a `main.css` file with your custom styles
4. Optionally add a `screenshot.png` (recommended size: 880x660px)

## Theme Loading

System themes are automatically loaded and applied to the admin interface based on the user's selected theme in the system settings.
