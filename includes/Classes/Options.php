<?php

/**
 * Get custom system options from the database
 */

namespace ProjectSend\Classes;

class Options
{
    private $dbh;

    function __construct()
    {
        global $dbh;
        $this->dbh = $dbh;
    }

    /**
     * List of options that should be encrypted in the database
     */
    private static $encrypted_options = [
        'ldap_admin_password',
    ];

    /**
     * Check if an option should be encrypted
     */
    private function isEncryptedOption($option)
    {
        return in_array($option, self::$encrypted_options);
    }

    /**
     * Encrypt an option value
     */
    private function encryptValue($value)
    {
        if (empty($value)) {
            return '';
        }

        // Get master key from HASH_SALT or ENCRYPTION_MASTER_KEY
        $master_key = $this->getMasterKey();
        if (!$master_key) {
            error_log('WARNING: Cannot encrypt option - no master key available');
            return $value; // Fallback to plaintext (not ideal but prevents data loss)
        }

        $algorithm = 'aes-256-gcm';
        $iv = random_bytes(openssl_cipher_iv_length($algorithm));
        $tag = '';

        $encrypted = openssl_encrypt(
            $value,
            $algorithm,
            $master_key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            error_log('WARNING: Encryption failed for option');
            return $value; // Fallback to plaintext
        }

        // Combine IV + encrypted data + tag, then base64 encode
        $encrypted_with_tag = $encrypted . $tag;
        return 'ENC:' . base64_encode($iv . $encrypted_with_tag);
    }

    /**
     * Decrypt an option value
     */
    private function decryptValue($value)
    {
        if (empty($value)) {
            return '';
        }

        // Check if value is encrypted (has ENC: prefix)
        if (substr($value, 0, 4) !== 'ENC:') {
            // Not encrypted, return as-is (for backward compatibility)
            return $value;
        }

        // Remove ENC: prefix and decode
        $encrypted_data = base64_decode(substr($value, 4));
        if ($encrypted_data === false) {
            error_log('WARNING: Failed to decode encrypted option value');
            return '';
        }

        $master_key = $this->getMasterKey();
        if (!$master_key) {
            error_log('WARNING: Cannot decrypt option - no master key available');
            return '';
        }

        $algorithm = 'aes-256-gcm';
        $iv_length = openssl_cipher_iv_length($algorithm);
        $tag_length = 16; // GCM tag is 16 bytes

        // Validate encrypted data length
        if (strlen($encrypted_data) < ($iv_length + $tag_length)) {
            error_log('WARNING: Encrypted option value is too short');
            return '';
        }

        // Extract IV, encrypted data, and tag
        $iv = substr($encrypted_data, 0, $iv_length);
        $encrypted_with_tag = substr($encrypted_data, $iv_length);
        $encrypted = substr($encrypted_with_tag, 0, -$tag_length);
        $tag = substr($encrypted_with_tag, -$tag_length);

        $decrypted = openssl_decrypt(
            $encrypted,
            $algorithm,
            $master_key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            error_log('WARNING: Decryption failed for option - authentication failed');
            return '';
        }

        return $decrypted;
    }

    /**
     * Get or generate the master encryption key
     */
    private function getMasterKey()
    {
        // Check if master key exists in config
        if (defined('ENCRYPTION_MASTER_KEY') && !empty(ENCRYPTION_MASTER_KEY)) {
            return base64_decode(ENCRYPTION_MASTER_KEY);
        }

        // For backward compatibility, generate from existing secret if available
        if (defined('HASH_SALT') && !empty(HASH_SALT)) {
            // Derive a 256-bit key from the existing hash salt
            return hash_pbkdf2('sha256', HASH_SALT, 'projectsend-options-encryption', 10000, 32, true);
        }

        // No key available
        return null;
    }

    /**
     * Gets the values from the options table, which has 2 columns.
     * The first one is the option name, and the second is the assigned value.
     */
    public function getOption($option = null)
    {
        if (empty($option)) {
            return null;
        }

        if (empty($this->dbh)) {
            return null;
        }

        try {
            $statement = $this->dbh->prepare("SELECT value FROM " . TABLE_OPTIONS . " WHERE name = :option");
            $statement->bindParam(':option', $option);
            $statement->execute();
            $results = $statement->fetch();

            $value = $results['value'];

            if ((!empty($value))) {
                // Decrypt if this is an encrypted option
                if ($this->isEncryptedOption($option)) {
                    return $this->decryptValue($value);
                }
                return $value;
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getCurrentUrl()
    {
        $pageURL = 'http';
        if (!empty($_SERVER['HTTPS'])) {
            if ($_SERVER['HTTPS'] == 'on') {
                $pageURL .= "s";
            }
        }
        $pageURL .= "://";
        $pageURL .= $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];

        $extension = substr($pageURL, -4);
        if ($extension == '.php') {
            $pageURL = substr($pageURL, 0, -17);
            return $pageURL;
        } else {
            $pageURL = substr($pageURL, 0, -8);
            return $pageURL;
        }
    }

    /**
     * Makes the options available to the app
     */
    public function setSystemConstants()
    {
        $base_uri = (empty($this->getOption('base_uri'))) ? $this->getCurrentUrl() : $this->getOption('base_uri');
        define('BASE_URI', $base_uri);

        // Set the default timezone based on the value of the Timezone select box of the options page
        $timezone = $this->getOption('timezone');
        if (!empty($timezone)) {
            date_default_timezone_set($this->getOption('timezone'));
        }

        // Landing page for public groups and files
        define('PUBLIC_DOWNLOAD_URL', BASE_URI . 'download.php');
        define('PUBLIC_LANDING_URI', BASE_URI . 'public.php');
        define('PUBLIC_GROUP_URL', BASE_URI . 'public.php');

        // Cron
        define('CRON_COMMAND_EXAMPLE', '*/5 * * * * /usr/bin/php ' . ROOT_DIR . '/cron.php key=' . $this->getOption('cron_key') . '  >/dev/null');
        define('CRON_URL', BASE_URI . 'cron.php?key=' . $this->getOption('cron_key'));

        // URLs
        define('THUMBNAILS_FILES_URL', BASE_URI . 'upload/thumbnails');
        define('EMAIL_TEMPLATES_URL', BASE_URI . 'emails/');
        define('TEMPLATES_URL', BASE_URI . 'templates/');
        define('SYSTEM_TEMPLATES_URL', BASE_URI . 'systemtemplates/');

        // Widgets
        define('WIDGETS_URL', BASE_URI . 'includes/widgets/');

        // Logo Uploads
        define('ADMIN_UPLOADS_URI', BASE_URI . 'upload/admin/');

        // Assets
        define('ASSETS_URL', BASE_URI . 'assets');
        define('ASSETS_CSS_URL', ASSETS_URL . '/css');
        define('ASSETS_IMG_URL', ASSETS_URL . '/img');
        define('ASSETS_JS_URL', ASSETS_URL . '/js');
        define('ASSETS_LIB_URL', ASSETS_URL . '/lib');

        // Ajax
        define('AJAX_PROCESS_URL', BASE_URI. 'includes/ajax.process.php');

        // Client's landing URI
        define('CLIENT_VIEW_FILE_LIST_URL_PATH', 'my_files/index.php');
        define('CLIENT_VIEW_FILE_LIST_URL', BASE_URI . CLIENT_VIEW_FILE_LIST_URL_PATH);

        // Set a page for each status code
        define('STATUS_PAGES_DIR', ADMIN_VIEWS_DIR . DS . 'http_status_pages');
        define('PAGE_STATUS_CODE_URL', BASE_URI . 'error.php');
        define('PAGE_STATUS_CODE_403', PAGE_STATUS_CODE_URL . '?e=403');
        define('PAGE_STATUS_CODE_404', PAGE_STATUS_CODE_URL . '?e=404');
        define('PAGE_STATUS_CODE_REQUIREMENTS', PAGE_STATUS_CODE_URL . '?e=requirements');
    }
}
