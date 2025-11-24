<?php
/**
 * Add system theme configuration option
 *
 * This upgrade adds the 'selected_system_theme' option to allow users
 * to customize the admin/system interface theme separately from the
 * public-facing client template.
 *
 * @version 2025111701
 */
function upgrade_2025111701()
{
    global $dbh;

    // Add the selected_system_theme option if it doesn't exist
    if (!option_exists('selected_system_theme')) {
        save_option('selected_system_theme', 'default');

        // Log the addition
        $logger = new \ProjectSend\Classes\ActionsLog;
        $logger->addEntry([
            'action' => 50, // System configuration change
            'owner_id' => 1, // System
            'details' => [
                'option_added' => 'selected_system_theme',
                'default_value' => 'default',
                'description' => 'Added system theme configuration option',
            ],
        ]);
    }

    // Verify the system templates directory exists
    $system_templates_dir = ROOT_DIR . DS . 'systemtemplates';
    if (!file_exists($system_templates_dir)) {
        mkdir($system_templates_dir, 0755, true);
    }

    // Verify default theme directory exists
    $default_theme_dir = $system_templates_dir . DS . 'default';
    if (!file_exists($default_theme_dir)) {
        mkdir($default_theme_dir, 0755, true);
    }
}
