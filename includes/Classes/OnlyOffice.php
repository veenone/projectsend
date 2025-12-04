<?php
/**
 * ONLYOFFICE Document Server Integration
 *
 * Handles integration with ONLYOFFICE Document Server for real-time
 * editing of Office documents (docx, xlsx, pptx, etc.)
 *
 * @package ProjectSend
 */

namespace ProjectSend\Classes;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class OnlyOffice
{
    private $dbh;

    /**
     * Editable file extensions mapped to document types
     */
    private static $editableExtensions = [
        // Word documents
        'docx' => 'word',
        'doc' => 'word',
        'odt' => 'word',
        'rtf' => 'word',
        'txt' => 'word',
        // Spreadsheets
        'xlsx' => 'cell',
        'xls' => 'cell',
        'ods' => 'cell',
        'csv' => 'cell',
        // Presentations
        'pptx' => 'slide',
        'ppt' => 'slide',
        'odp' => 'slide',
    ];

    /**
     * Viewable-only extensions (can view but not edit)
     */
    private static $viewableExtensions = [
        'pdf' => 'word',
        'djvu' => 'word',
        'xps' => 'word',
    ];

    public function __construct()
    {
        global $dbh;
        $this->dbh = $dbh;
    }

    /**
     * Check if ONLYOFFICE integration is enabled
     */
    public static function isEnabled(): bool
    {
        return get_option('onlyoffice_enabled') === '1';
    }

    /**
     * Get the Document Server URL
     */
    public static function getServerUrl(): string
    {
        return rtrim(get_option('onlyoffice_document_server_url', ''), '/');
    }

    /**
     * Check if a file extension is editable
     */
    public static function isEditable(string $extension): bool
    {
        $extension = strtolower(trim($extension, '.'));
        return isset(self::$editableExtensions[$extension]);
    }

    /**
     * Check if a file extension is viewable (editable or view-only)
     */
    public static function isViewable(string $extension): bool
    {
        $extension = strtolower(trim($extension, '.'));
        return isset(self::$editableExtensions[$extension]) || isset(self::$viewableExtensions[$extension]);
    }

    /**
     * Get the document type for an extension
     */
    public static function getDocumentType(string $extension): ?string
    {
        $extension = strtolower(trim($extension, '.'));

        if (isset(self::$editableExtensions[$extension])) {
            return self::$editableExtensions[$extension];
        }

        if (isset(self::$viewableExtensions[$extension])) {
            return self::$viewableExtensions[$extension];
        }

        return null;
    }

    /**
     * Generate a unique document key for ONLYOFFICE
     * The key must change when the document changes
     */
    public static function generateDocumentKey(Files $file): string
    {
        // Use file ID + modification time to create unique key
        $mtime = time(); // Default to current time

        // For local files, try to get actual modification time
        if ($file->storage_type === 'local' || empty($file->storage_type)) {
            if (!empty($file->full_path) && file_exists($file->full_path)) {
                $mtime = filemtime($file->full_path);
                if ($mtime === false) {
                    $mtime = time();
                }
            }
        }

        return md5($file->id . '_' . $file->filename_on_disk . '_' . $mtime);
    }

    /**
     * Generate a file access token for ONLYOFFICE to fetch the file
     */
    public static function generateFileAccessToken(int $fileId): string
    {
        $secret = get_option('onlyoffice_jwt_secret', '');
        $expiry = (int) get_option('onlyoffice_file_token_expiry', 3600);

        $payload = [
            'file_id' => $fileId,
            'exp' => time() + $expiry,
            'iat' => time(),
            'type' => 'file_access'
        ];

        if (empty($secret)) {
            // Fallback to simple token if no JWT secret configured
            return base64_encode(json_encode($payload));
        }

        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * Validate a file access token
     */
    public static function validateFileAccessToken(string $token): ?array
    {
        $secret = get_option('onlyoffice_jwt_secret', '');

        try {
            if (empty($secret)) {
                // Fallback validation for simple token
                $payload = json_decode(base64_decode($token), true);
                if (!$payload || !isset($payload['file_id']) || !isset($payload['exp'])) {
                    return null;
                }
                if ($payload['exp'] < time()) {
                    return null;
                }
                return $payload;
            }

            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            error_log("OnlyOffice token validation error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate JWT token for ONLYOFFICE API communication
     */
    public static function generateJWT(array $payload): string
    {
        $secret = get_option('onlyoffice_jwt_secret', '');

        if (empty($secret) || get_option('onlyoffice_jwt_enabled') !== '1') {
            return '';
        }

        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * Validate JWT token from ONLYOFFICE callback
     */
    public static function validateJWT(string $token): ?array
    {
        $secret = get_option('onlyoffice_jwt_secret', '');

        if (empty($secret) || get_option('onlyoffice_jwt_enabled') !== '1') {
            return []; // JWT disabled, allow all
        }

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            error_log("OnlyOffice JWT validation error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the file URL for ONLYOFFICE to fetch
     */
    public static function getFileUrl(Files $file): string
    {
        // Always use the file proxy endpoint for all storage types
        // This ensures ONLYOFFICE (which may run in Docker) can access files
        // through the web server, regardless of whether files are on S3 or local storage
        $token = self::generateFileAccessToken($file->id);
        return BASE_URI . 'onlyoffice-file.php?id=' . $file->id . '&token=' . urlencode($token);
    }

    /**
     * Get the callback URL for ONLYOFFICE to send save notifications
     */
    public static function getCallbackUrl(int $fileId): string
    {
        $token = self::generateFileAccessToken($fileId);
        return BASE_URI . 'onlyoffice-callback.php?id=' . $fileId . '&token=' . urlencode($token);
    }

    /**
     * Generate the full editor configuration for ONLYOFFICE
     */
    public function getEditorConfig(Files $file, Users $user, string $mode = 'edit'): array
    {
        $extension = strtolower(pathinfo($file->filename_original, PATHINFO_EXTENSION));
        $documentType = self::getDocumentType($extension);

        if (!$documentType) {
            throw new \Exception('Unsupported file type: ' . $extension);
        }

        // Determine if editing is allowed
        $canEdit = ($mode === 'edit') && self::isEditable($extension);

        $config = [
            'document' => [
                'fileType' => $extension,
                'key' => self::generateDocumentKey($file),
                'title' => $file->filename_original,
                'url' => self::getFileUrl($file),
                'permissions' => [
                    'comment' => $canEdit,
                    'download' => true,
                    'edit' => $canEdit,
                    'print' => true,
                    'review' => $canEdit,
                ],
            ],
            'documentType' => $documentType,
            'editorConfig' => [
                'callbackUrl' => self::getCallbackUrl($file->id),
                'lang' => defined('SITE_LANG') ? SITE_LANG : 'en',
                'mode' => $canEdit ? 'edit' : 'view',
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                ],
                'customization' => [
                    'autosave' => true,
                    'chat' => false,
                    'comments' => $canEdit,
                    'compactHeader' => false,
                    'compactToolbar' => false,
                    'feedback' => false,
                    'forcesave' => true,
                    'goback' => false,
                    'help' => false,
                    'hideRightMenu' => false,
                    'hideRulers' => false,
                    'logo' => [
                        'image' => '',
                        'imageDark' => '',
                        'url' => BASE_URI,
                    ],
                    'macros' => false,
                    'plugins' => false,
                    'toolbarNoTabs' => false,
                    'uiTheme' => 'theme-light',
                ],
            ],
            'height' => '100%',
            'width' => '100%',
            'type' => 'desktop',
        ];

        // Add JWT token if enabled
        $jwtSecret = get_option('onlyoffice_jwt_secret', '');
        if (!empty($jwtSecret) && get_option('onlyoffice_jwt_enabled') === '1') {
            $config['token'] = self::generateJWT($config);
        }

        return $config;
    }

    /**
     * Test connection to ONLYOFFICE Document Server
     */
    public static function testConnection(): array
    {
        $serverUrl = self::getServerUrl();

        if (empty($serverUrl)) {
            return [
                'success' => false,
                'message' => __('Document Server URL is not configured', 'cftp_admin')
            ];
        }

        // Try to fetch the healthcheck endpoint
        $healthUrl = $serverUrl . '/healthcheck';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $healthUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false, // May need to adjust for production
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => __('Connection failed', 'cftp_admin') . ': ' . $error
            ];
        }

        if ($httpCode === 200 && $response === 'true') {
            return [
                'success' => true,
                'message' => __('Connection successful', 'cftp_admin')
            ];
        }

        // Try the welcome page as fallback
        $welcomeUrl = $serverUrl . '/welcome/';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $welcomeUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return [
                'success' => true,
                'message' => __('Connection successful', 'cftp_admin')
            ];
        }

        return [
            'success' => false,
            'message' => __('Server responded with status', 'cftp_admin') . ': ' . $httpCode
        ];
    }

    /**
     * Get list of supported file extensions for display
     */
    public static function getSupportedExtensions(): array
    {
        return [
            'editable' => array_keys(self::$editableExtensions),
            'viewable' => array_keys(self::$viewableExtensions),
        ];
    }
}
