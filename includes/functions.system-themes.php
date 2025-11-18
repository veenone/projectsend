<?php
/**
 * Functions related to the system/admin interface themes
 */

// SYSTEM_TEMPLATES_DIR is defined in includes/app.php
// SYSTEM_TEMPLATES_URL is defined in includes/Classes/Options.php (setSystemConstants)

/**
 * Get the system theme metadata from theme.php
 * Based on extract_template_info() but for system themes
 *
 * @param string $theme_directory
 * @return array|false
 */
function extract_system_theme_info($theme_directory)
{
    if (empty($theme_directory)) {
        return false;
    }

    $folder = str_replace(SYSTEM_TEMPLATES_DIR . DS, '', $theme_directory);

    $theme_file = $theme_directory . DS . 'theme.php';
    if (!file_exists($theme_file)) {
        return false;
    }

    // Load the theme configuration
    $theme_config = include $theme_file;

    if (!is_array($theme_config)) {
        // Fallback: try to extract info from comments like templates do
        $fp = fopen($theme_file, 'r');
        if ($fp === false) {
            return false;
        }
        $file_info = fread($fp, 8192);
        fclose($fp);

        $file_info = str_replace("\r", "\n", $file_info);

        $theme_info = array(
            'name' => 'Theme name',
            'themeuri' => 'URI',
            'author' => 'Author',
            'authoruri' => 'Author URI',
            'authoremail' => 'Author e-mail',
            'description' => 'Description',
            'version' => 'Version',
        );

        foreach ($theme_info as $data => $regex) {
            if (preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi', $file_info, $match) && $match[1])
                $theme_info[$data] = html_output(trim($match[1]));
            else
                $theme_info[$data] = '';
        }

        if (empty($theme_info['name'])) {
            $theme_info['name'] = $folder;
        }

        $theme_config = $theme_info;
    } else {
        // Merge with defaults
        $theme_config = array_merge([
            'name' => ucfirst($folder),
            'slug' => $folder,
            'version' => '1.0.0',
            'description' => '',
            'author' => 'ProjectSend',
            'authoruri' => '',
            'authoremail' => '',
            'features' => [],
            'css_files' => ['main.css'],
            'settings' => [],
        ], $theme_config);
    }

    // Location is the value saved on the DB.
    $theme_config['location'] = $folder;

    // Currently active theme
    if ($folder == get_option('selected_system_theme', 'default')) {
        $theme_config['active'] = 1;
    }

    // Look for the screenshot
    $screenshot_file = $theme_directory . DS . 'screenshot.png';

    if (defined('SYSTEM_TEMPLATES_URL')) {
        $screenshot_url = SYSTEM_TEMPLATES_URL . $folder . '/screenshot.png';
        $theme_config['screenshot'] = (file_exists($screenshot_file)) ? $screenshot_url : (defined('ASSETS_IMG_URL') ? ASSETS_IMG_URL . 'template-screenshot.png' : '');
    } else {
        $theme_config['screenshot'] = '';
    }

    return $theme_config;
}

/**
 * Scan for available system themes
 *
 * @return array
 */
function look_for_system_themes()
{
    $themes = [];
    $themes_error = [];

    // Make sure constant is defined
    if (!defined('SYSTEM_TEMPLATES_DIR')) {
        return $themes;
    }

    if (!file_exists(SYSTEM_TEMPLATES_DIR)) {
        return $themes;
    }

    $ignore = array('.', '..');
    $base_directory = SYSTEM_TEMPLATES_DIR . DS;
    $directories = glob($base_directory . "*");

    foreach ($directories as $directory) {
        if (is_dir($directory) && !in_array($directory, $ignore)) {
            if (check_system_theme_integrity($directory)) {
                $theme_info = extract_system_theme_info($directory);

                if ($theme_info) {
                    // Generate the valid themes array
                    $themes[] = $theme_info;
                }
            } else {
                // Generate another array with the themes that are not complete
                $themes_error[] = [
                    'theme_error' => $directory
                ];
            }
        }
    }

    // Put active theme as first element of the array
    foreach ($themes as $index => $theme) {
        if (array_key_exists('active', $theme)) {
            unset($themes[$index]);
            array_unshift($themes, $theme);
        }
    }

    return $themes;
}

/**
 * Check if system theme has required files
 *
 * Each theme must have at least:
 * - theme.php (metadata and configuration)
 * - main.css (optional, but recommended)
 *
 * @param string $folder
 * @return bool
 */
function check_system_theme_integrity($folder)
{
    $required_files = [
        'theme.php',
    ];

    $miss = 0;
    $found = glob($folder . "/*");

    foreach ($required_files as $required) {
        $this_file = $folder . '/' . $required;
        if (!in_array($this_file, $found)) {
            $miss++;
        }
    }

    if ($miss == 0) {
        return true;
    }

    return false;
}

/**
 * Get the path to the selected system theme
 *
 * @return string
 */
function get_selected_system_theme_path()
{
    $theme_slug = get_option('selected_system_theme', 'default');
    $path = SYSTEM_TEMPLATES_DIR . DS . $theme_slug . DS;

    // Fallback to default if theme doesn't exist
    if (!file_exists($path)) {
        $path = SYSTEM_TEMPLATES_DIR . DS . 'default' . DS;
    }

    return $path;
}

/**
 * Get the URL to the selected system theme
 *
 * @return string
 */
function get_selected_system_theme_url()
{
    if (!defined('SYSTEM_TEMPLATES_URL')) {
        return '';
    }

    $theme_slug = get_option('selected_system_theme', 'default');
    $url = SYSTEM_TEMPLATES_URL . $theme_slug . '/';

    return $url;
}

/**
 * Get the default system theme path
 *
 * @return string
 */
function get_default_system_theme_path()
{
    $path = SYSTEM_TEMPLATES_DIR . DS . 'default' . DS;
    return $path;
}

/**
 * Load system theme configuration
 *
 * @param string $theme_slug
 * @return array|false
 */
function load_system_theme_config($theme_slug = null)
{
    if (empty($theme_slug)) {
        $theme_slug = get_option('selected_system_theme', 'default');
    }

    $theme_file = SYSTEM_TEMPLATES_DIR . DS . $theme_slug . DS . 'theme.php';

    if (!file_exists($theme_file)) {
        return false;
    }

    $config = include $theme_file;
    return is_array($config) ? $config : false;
}

/**
 * Load system theme CSS files
 * This should be called in the header
 *
 * @return void
 */
function load_system_theme_assets()
{
    // Only load if constants are defined (after Options class initialization)
    if (!defined('SYSTEM_TEMPLATES_URL') || !defined('SYSTEM_TEMPLATES_DIR')) {
        return;
    }

    $theme_slug = get_option('selected_system_theme', 'default');
    $theme_config = load_system_theme_config($theme_slug);

    if (!$theme_config) {
        return;
    }

    // Load CSS files
    if (!empty($theme_config['css_files'])) {
        $theme_url = get_selected_system_theme_url();

        // If URL is empty, constants aren't available yet
        if (empty($theme_url)) {
            return;
        }

        $version = !empty($theme_config['version']) ? $theme_config['version'] : '1.0.0';

        foreach ($theme_config['css_files'] as $css_file) {
            $css_path = get_selected_system_theme_path() . $css_file;
            if (file_exists($css_path)) {
                add_asset('css', 'system_theme_' . str_replace('.css', '', $css_file), $theme_url . $css_file, $version, 'head');
            }
        }
    }
}

/**
 * Get system theme setting
 *
 * @param string $setting_name
 * @param mixed $default
 * @return mixed
 */
function get_system_theme_setting($setting_name, $default = null)
{
    $theme_slug = get_option('selected_system_theme', 'default');
    return get_theme_option($theme_slug, $setting_name, $default);
}

/**
 * Save system theme setting
 *
 * @param string $setting_name
 * @param mixed $value
 * @param string $type
 * @return bool
 */
function save_system_theme_setting($setting_name, $value, $type = 'string')
{
    $theme_slug = get_option('selected_system_theme', 'default');
    return save_theme_option($theme_slug, $setting_name, $value, $type);
}

/**
 * Check if current user can change system theme
 *
 * @return bool
 */
function can_change_system_theme()
{
    return current_user_can_manage_options();
}

/**
 * Validate system theme slug
 *
 * @param string $theme_slug
 * @return bool
 */
function is_valid_system_theme($theme_slug)
{
    $themes = look_for_system_themes();
    foreach ($themes as $theme) {
        if ($theme['location'] === $theme_slug) {
            return true;
        }
    }
    return false;
}
