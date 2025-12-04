<?php
/**
 * ONLYOFFICE File Proxy
 *
 * Serves files to ONLYOFFICE Document Server for editing.
 * For local files, streams the content directly.
 * For external storage (S3), redirects to a presigned URL.
 *
 * @package ProjectSend
 */

define('IS_ONLYOFFICE_FILE', true);

require_once 'bootstrap.php';

use ProjectSend\Classes\OnlyOffice;
use ProjectSend\Classes\Files;
use ProjectSend\Classes\Download;

// Get parameters
$fileId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (!$fileId) {
    http_response_code(400);
    die('Missing file ID');
}

// Validate file access token
$tokenData = OnlyOffice::validateFileAccessToken($token);
if ($tokenData === null) {
    http_response_code(403);
    die('Invalid or expired token');
}

// Verify token matches file ID
if (isset($tokenData['file_id']) && $tokenData['file_id'] !== $fileId) {
    http_response_code(403);
    die('Token does not match file');
}

// Load the file
$file = new Files($fileId);
if (!$file->recordExists()) {
    http_response_code(404);
    die('File not found');
}

// Handle external storage (S3)
if ($file->storage_type !== 'local') {
    // Debug log function
    $debugLog = function($msg) {
        file_put_contents('/tmp/onlyoffice_debug.log', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
    };

    $debugLog("=== Processing S3 file ===");
    $debugLog("File ID: " . $file->id);
    $debugLog("Storage type: " . $file->storage_type);
    $debugLog("External path: " . ($file->external_path ?? 'NULL'));
    $debugLog("Integration ID: " . ($file->integration_id ?? 'NULL'));

    try {
        $integrations = new \ProjectSend\Classes\Integrations();
        $integration = $integrations->getById($file->integration_id);

        $debugLog("Integration found: " . ($integration ? 'YES' : 'NO'));
        if ($integration) {
            $debugLog("Integration type: " . ($integration['type'] ?? 'NULL'));
            $debugLog("Integration name: " . ($integration['name'] ?? 'NULL'));
        }

        if ($integration && $integration['type'] === 's3') {
            // S3Storage constructor expects the integration ID, not the array
            $s3 = new \ProjectSend\Classes\S3Storage($file->integration_id);
            $debugLog("S3Storage initialized with integration ID: " . $file->integration_id);

            // Download from S3 and stream to ONLYOFFICE
            // (Don't use presigned URL redirect as ONLYOFFICE Docker may not reach S3 directly)
            $tempFile = tempnam(sys_get_temp_dir(), 'onlyoffice_s3_');
            $debugLog("Temp file: " . $tempFile);
            $debugLog("Downloading from S3: " . $file->external_path);

            $downloadResult = $s3->downloadFile($file->external_path, $tempFile);
            $debugLog("Download result: " . json_encode($downloadResult));

            if ($downloadResult['success'] && file_exists($tempFile)) {
                $mimeType = mime_content_type($tempFile) ?: 'application/octet-stream';
                $fileSize = filesize($tempFile);
                $debugLog("Success! MIME: $mimeType, Size: $fileSize");

                header('Content-Type: ' . $mimeType);
                header('Content-Length: ' . $fileSize);
                header('Content-Disposition: inline; filename="' . rawurlencode($file->filename_original) . '"');
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');

                readfile($tempFile);
                @unlink($tempFile);
                exit;
            } else {
                @unlink($tempFile);
                $debugLog("Download FAILED: " . ($downloadResult['message'] ?? 'Unknown error'));
                error_log("OnlyOffice S3 download failed: " . ($downloadResult['message'] ?? 'Unknown error') . " | Path: " . $file->external_path);
            }
        } else {
            $debugLog("Integration not found or not S3 type");
            error_log("OnlyOffice: Integration not found or not S3 type. ID: " . $file->integration_id);
        }
    } catch (\Exception $e) {
        $debugLog("EXCEPTION: " . $e->getMessage());
        error_log("OnlyOffice file proxy error: " . $e->getMessage());
    }

    http_response_code(500);
    die('Failed to retrieve file from external storage');
}

// Handle local files
if (!file_exists($file->full_path)) {
    http_response_code(404);
    die('File not found on disk');
}

// Check if file is encrypted
if ($file->encrypted) {
    // Use Download class to handle decryption
    $download = new Download();

    // Get decryption key
    $encryptionKey = null;
    if (method_exists($file, 'getDecryptionKey')) {
        $encryptionKey = $file->getDecryptionKey();
    }

    if ($encryptionKey) {
        // Decrypt to temp file and serve
        $tempFile = tempnam(sys_get_temp_dir(), 'onlyoffice_dec_');

        try {
            // Read encrypted content
            $encryptedContent = file_get_contents($file->full_path);

            // Decrypt (using the same method as Download class)
            $iv = hex2bin($file->encryption_file_iv);
            $decrypted = openssl_decrypt(
                $encryptedContent,
                $file->encryption_algorithm ?: 'aes-256-gcm',
                $encryptionKey,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($decrypted === false) {
                throw new \Exception('Decryption failed');
            }

            file_put_contents($tempFile, $decrypted);

            $mimeType = mime_content_type($tempFile) ?: 'application/octet-stream';

            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . filesize($tempFile));
            header('Content-Disposition: inline; filename="' . rawurlencode($file->filename_original) . '"');

            readfile($tempFile);
            @unlink($tempFile);
            exit;

        } catch (\Exception $e) {
            @unlink($tempFile);
            error_log("OnlyOffice file proxy decryption error: " . $e->getMessage());
            http_response_code(500);
            die('Failed to decrypt file');
        }
    }

    http_response_code(500);
    die('Encrypted file cannot be accessed');
}

// Serve unencrypted local file
$mimeType = mime_content_type($file->full_path) ?: 'application/octet-stream';
$fileSize = filesize($file->full_path);

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: inline; filename="' . rawurlencode($file->filename_original) . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Stream the file
$handle = fopen($file->full_path, 'rb');
if ($handle) {
    while (!feof($handle)) {
        echo fread($handle, 8192);
        flush();
    }
    fclose($handle);
}
