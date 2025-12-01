<?php
// Start output buffering FIRST to catch any stray output
ob_start();

define('FILE_UPLOADING', true);

// Suppress ALL HTML error output - we need clean JSON responses
ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(0);

// Set up error handler to log errors instead of displaying them
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Upload error [$errno]: $errstr in $errfile on line $errline");
    return true; // Don't execute PHP's internal error handler
});

// Set up exception handler
set_exception_handler(function($exception) {
    ob_end_clean(); // Clear any buffered output
    error_log("Upload exception: " . $exception->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['OK' => 0, 'error' => ['code' => 500, 'message' => 'Server error']]);
    exit;
});

/**
 *  Call the required system files
 */
require_once '../bootstrap.php';

// Clear any output that may have been generated during bootstrap
ob_end_clean();

/**
 * If there is no valid session/user block the upload of files
 */
if ( !user_is_logged_in() ) {
	exit;
}

function dieWithError($message = null, $code = 400)
{
    header('Content-Type: application/json');
    $response = [
        'OK' => 0,
        'error' => [
            'code' => $code,
            'message' => $message,
            'filename' => isset($_REQUEST["name"]) ? $_REQUEST["name"] : ''
        ]
    ];

    echo json_encode($response);
    http_response_code($code);
    exit;
}

/**
 * upload.php
 *
 * Copyright 2009, Moxiecode Systems AB
 * Released under GPL License.
 *
 * License: http://www.plupload.com/license
 * Contributing: http://www.plupload.com/contributing
 */
// HTTP headers for no cache etc
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Settings
$targetDir = UPLOADED_FILES_DIR;

$cleanupTargetDir = true; // Remove old files
$maxFileAge = 5 * 3600; // Temp file age in seconds

@set_time_limit(UPLOAD_TIME_LIMIT);

// Uncomment this one to fake upload time
// usleep(5000);

// Get parameters
$chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
$chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;
$fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : '';

// Validate file has an acceptable extension
if (!file_is_allowed($fileName)) {
    dieWithError('Invalid Extension');
}

// Create target dir
if (!file_exists($targetDir))
	@mkdir($targetDir);

// Check for directory traversal
$basePath = $targetDir . DS;
$realBase = realpath($basePath);

$filePath = dirname($basePath . $fileName);
$realFilePath = realpath($filePath);

if ($realFilePath === false || strpos($realFilePath, $realBase) !== 0) {
    dieWithError("Directory Traversal Detected!");
}

$filePath = $targetDir . DS . $fileName;

// Remove old temp files	
if ($cleanupTargetDir && is_dir($targetDir) && ($dir = @opendir($targetDir))) {
	while (($file = readdir($dir)) !== false) {
		$tmpfilePath = $targetDir . DS . $file;

		// Remove temp file if it is older than the max age and is not the current file
		if (preg_match('/\.part$/', $file) && (filemtime($tmpfilePath) < time() - $maxFileAge) && ($tmpfilePath != "{$filePath}.part")) {
			@unlink($tmpfilePath);
		}
	}

	closedir($dir);
} else
    dieWithError('Failed to open temp directory');
	

// Look for the content type header
if (isset($_SERVER["HTTP_CONTENT_TYPE"]))
	$contentType = $_SERVER["HTTP_CONTENT_TYPE"];

if (isset($_SERVER["CONTENT_TYPE"]))
	$contentType = $_SERVER["CONTENT_TYPE"];

// Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
if (strpos($contentType, "multipart") !== false) {
	if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
		// Open temp file
		$out = fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");
		if ($out) {
			// Read binary input stream and append it to temp file
			$in = fopen($_FILES['file']['tmp_name'], "rb");

			if ($in) {
				while ($buff = fread($in, 4096))
					fwrite($out, $buff);
            } else
                dieWithError('Failed to open input stream');
			fclose($in);
			fclose($out);
			@unlink($_FILES['file']['tmp_name']);
        } else {
            dieWithError('Failed to open output stream');
        }
    } else {
        dieWithError('Failed to move uploaded file');
    }
} else {
	// Open temp file
	$out = fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");
	if ($out) {
		// Read binary input stream and append it to temp file
		$in = fopen("php://input", "rb");

		if ($in) {
			while ($buff = fread($in, 4096))
				fwrite($out, $buff);
        } else {
            dieWithError('Failed to open input stream');
        }

		fclose($in);
		fclose($out);
    } else {
        dieWithError('Failed to open output stream');
    }
}

// Check if file has been uploaded
if (!$chunks || $chunk == $chunks - 1) {
	// Strip the temp .part suffix off
	rename("{$filePath}.part", $filePath);

    // Get storage selection from request or use default
    $storage_selection = isset($_REQUEST['storage_selection']) ? $_REQUEST['storage_selection'] : get_option('default_upload_storage', 'local');

    // Validate storage selection against user/group restrictions (only if user is logged in)
    if (defined('CURRENT_USER_ID') && CURRENT_USER_ID) {
        $current_user = new \ProjectSend\Classes\Users(CURRENT_USER_ID);
        $user_allowed_storage = $current_user->getAllowedStorageArray();

        // Get allowed storage from user's groups
        $group_allowed_storage = [];
        $user_groups = $current_user->groups;
        if (!empty($user_groups)) {
            foreach ($user_groups as $group_id) {
                $group = new \ProjectSend\Classes\Groups($group_id);
                $group_storage = $group->getAllowedStorageArray();
                if (!empty($group_storage)) {
                    $group_allowed_storage = array_merge($group_allowed_storage, $group_storage);
                }
            }
            $group_allowed_storage = array_unique($group_allowed_storage);
        }

        // Determine effective allowed storage
        // Logic: User can use storage allowed by user settings OR group settings (union)
        $effective_allowed_storage = [];
        $has_user_restrictions = !empty($user_allowed_storage);
        $has_group_restrictions = !empty($group_allowed_storage);

        if ($has_user_restrictions && $has_group_restrictions) {
            // Use union - user can use storage allowed by user OR group settings
            $effective_allowed_storage = array_unique(array_merge($user_allowed_storage, $group_allowed_storage));
        } elseif ($has_user_restrictions) {
            $effective_allowed_storage = $user_allowed_storage;
        } elseif ($has_group_restrictions) {
            $effective_allowed_storage = $group_allowed_storage;
        }

        // Validate selected storage is allowed
        if (!empty($effective_allowed_storage)) {
            $storage_allowed = in_array($storage_selection, $effective_allowed_storage) ||
                              in_array((string)$storage_selection, $effective_allowed_storage);
            if (!$storage_allowed) {
                dieWithError(__('You are not allowed to upload to the selected storage location.', 'cftp_admin'), 403);
            }
        }
    }

    // Check if encryption is requested
    $encrypt_file = false;
    if (isset($_REQUEST['encrypt_file']) && $_REQUEST['encrypt_file'] === '1') {
        $encrypt_file = true;
    } elseif (\ProjectSend\Classes\Encryption::isRequired()) {
        // Encryption is required globally
        $encrypt_file = true;
    } elseif (\ProjectSend\Classes\Encryption::isEnabled()) {
        // Encryption is enabled by default but not required
        $encrypt_file = true;
    }

    // Encrypt file if requested/required
    if ($encrypt_file) {
        try {
            $encryption = new \ProjectSend\Classes\Encryption();

            // Generate unique file key
            $file_key = $encryption->generateFileKey();

            // Encrypt the file
            $encrypted_path = $filePath . '.encrypted';
            $encrypt_result = $encryption->encryptFile($filePath, $encrypted_path, $file_key);

            if (!$encrypt_result['success']) {
                dieWithError('Encryption failed: ' . $encrypt_result['error']);
            }

            // Encrypt the file key with master key
            $encrypted_key_data = $encryption->encryptFileKey($file_key);

            // Replace original file with encrypted version
            unlink($filePath);
            rename($encrypted_path, $filePath);

            // Store encryption metadata for later use
            $encryption_metadata = [
                'encrypted' => 1,
                'encryption_key_encrypted' => $encrypted_key_data['encrypted_key'],
                'encryption_iv' => $encrypted_key_data['iv'],
                'encryption_algorithm' => $encryption->getAlgorithm(),
                'encryption_file_iv' => $encrypt_result['iv']
            ];

        } catch (\Exception $e) {
            error_log('File encryption error: ' . $e->getMessage());
            dieWithError('File encryption failed');
        }
    } else {
        $encryption_metadata = [
            'encrypted' => 0,
            'encryption_key_encrypted' => null,
            'encryption_iv' => null,
            'encryption_algorithm' => null,
            'encryption_file_iv' => null
        ];
    }

    // Add to database
    $file = new \ProjectSend\Classes\Files;
    $file->setDefaults();

    // Set encryption metadata
    $file->encrypted = $encryption_metadata['encrypted'];
    $file->encryption_key_encrypted = $encryption_metadata['encryption_key_encrypted'];
    $file->encryption_iv = $encryption_metadata['encryption_iv'];
    $file->encryption_algorithm = $encryption_metadata['encryption_algorithm'];
    $file->encryption_file_iv = $encryption_metadata['encryption_file_iv'];

    // Route to appropriate storage based on selection
    $route_result = $file->routeToStorage($filePath, $storage_selection, $fileName);

    if ($route_result && isset($route_result['filename_original'])) {
        $result = $file->addToDatabase();
    } else {
        $result = [
            'status' => 'error',
            'message' => __('Failed to process file upload to selected storage.', 'cftp_admin')
        ];
    }

    if ($result['status'] === 'success') {
        // Return JSON-RPC response
        $response = [
            'OK' => 1,
            'info' => [
                'id' => $file->getId(),
                'NewFileName' => $fileName,
                'encrypted' => $encryption_metadata['encrypted']
            ]
        ];

        echo json_encode($response);
        http_response_code(200);
    } else {
        // Return error response
        $response = [
            'OK' => 0,
            'error' => $result['message']
        ];

        echo json_encode($response);
        http_response_code(400);
    }
    exit;
}
