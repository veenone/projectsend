<?php
/**
 * ONLYOFFICE Callback Handler
 *
 * Receives save notifications from ONLYOFFICE Document Server
 * and saves the edited document back to storage.
 *
 * ONLYOFFICE Status Codes:
 * 0 - No document with the key identifier could be found
 * 1 - Document is being edited
 * 2 - Document is ready for saving
 * 3 - Document saving error has occurred
 * 4 - Document is closed with no changes
 * 6 - Document is being edited, but the current document state is saved
 * 7 - Error has occurred while force saving the document
 *
 * @package ProjectSend
 */

define('IS_ONLYOFFICE_CALLBACK', true);

require_once 'bootstrap.php';

use ProjectSend\Classes\OnlyOffice;
use ProjectSend\Classes\Files;
use ProjectSend\Classes\ActionsLog;

// Set JSON response header
header('Content-Type: application/json');

/**
 * Send JSON response and exit
 */
function sendResponse(int $error, string $message = ''): void
{
    $response = ['error' => $error];
    if ($message) {
        $response['message'] = $message;
    }
    echo json_encode($response);
    exit;
}

// Get file ID from request
$fileId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (!$fileId) {
    sendResponse(1, 'Missing file ID');
}

// Validate file access token
$tokenData = OnlyOffice::validateFileAccessToken($token);
if ($tokenData === null || (isset($tokenData['file_id']) && $tokenData['file_id'] !== $fileId)) {
    sendResponse(1, 'Invalid or expired token');
}

// Get POST data from ONLYOFFICE
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    sendResponse(1, 'Invalid JSON data');
}

// Validate JWT from ONLYOFFICE if enabled
$jwtEnabled = get_option('onlyoffice_jwt_enabled') === '1';
$jwtSecret = get_option('onlyoffice_jwt_secret', '');

if ($jwtEnabled && !empty($jwtSecret)) {
    $jwtHeader = get_option('onlyoffice_jwt_header', 'Authorization');

    // Get JWT from header or body
    $jwt = null;

    // Check Authorization header
    $headers = getallheaders();
    if (isset($headers[$jwtHeader])) {
        $authHeader = $headers[$jwtHeader];
        if (strpos($authHeader, 'Bearer ') === 0) {
            $jwt = substr($authHeader, 7);
        }
    }

    // Check body token
    if (!$jwt && isset($data['token'])) {
        $jwt = $data['token'];
    }

    if ($jwt) {
        $jwtData = OnlyOffice::validateJWT($jwt);
        if ($jwtData === null) {
            sendResponse(1, 'Invalid JWT token');
        }
        // Use payload from JWT if available
        if (isset($jwtData['payload'])) {
            $data = array_merge($data, (array) $jwtData['payload']);
        }
    }
}

// Get status from ONLYOFFICE
$status = isset($data['status']) ? (int) $data['status'] : 0;

// Log the callback for debugging
error_log("OnlyOffice callback - File ID: $fileId, Status: $status");

// Load the file
$file = new Files($fileId);
if (!$file->recordExists()) {
    sendResponse(1, 'File not found');
}

switch ($status) {
    case 0:
        // Document not found - acknowledge
        sendResponse(0);
        break;

    case 1:
        // Document is being edited - acknowledge
        sendResponse(0);
        break;

    case 2:
    case 6:
        // Document ready for saving (2) or force save (6)
        if (!isset($data['url']) || empty($data['url'])) {
            sendResponse(1, 'Missing document URL');
        }

        $documentUrl = $data['url'];

        // Download the edited document from ONLYOFFICE
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $documentUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $documentContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200 || empty($documentContent)) {
            error_log("OnlyOffice callback - Failed to download document: HTTP $httpCode, Error: $curlError");
            sendResponse(1, 'Failed to download edited document');
        }

        // Save to storage based on storage type
        try {
            if ($file->storage_type === 'local') {
                // Save to local filesystem
                $result = file_put_contents($file->full_path, $documentContent);
                if ($result === false) {
                    throw new \Exception('Failed to write file to disk');
                }

                // Update file size in database
                $newSize = strlen($documentContent);
                $statement = $dbh->prepare("UPDATE " . TABLE_FILES . " SET size = :size WHERE id = :id");
                $statement->execute([':size' => $newSize, ':id' => $fileId]);

            } else {
                // Save to external storage (S3, etc.)
                // Create a temporary file
                $tempFile = tempnam(sys_get_temp_dir(), 'onlyoffice_');
                file_put_contents($tempFile, $documentContent);

                // Get the integration
                $integrations = new \ProjectSend\Classes\Integrations();
                $integration = $integrations->getById($file->integration_id);

                if ($integration && $integration['type'] === 's3') {
                    // S3Storage constructor expects the integration ID, not the array
                    $s3 = new \ProjectSend\Classes\S3Storage($file->integration_id);

                    // Upload to S3 with the same path
                    // uploadFile(local_path, remote_path, metadata)
                    $result = $s3->uploadFile(
                        $tempFile,
                        $file->external_path,
                        ['original_filename' => $file->filename_original]
                    );

                    if (!$result['success']) {
                        throw new \Exception('Failed to upload to S3: ' . ($result['error'] ?? 'Unknown error'));
                    }

                    // Update file size in database
                    $newSize = strlen($documentContent);
                    $statement = $dbh->prepare("UPDATE " . TABLE_FILES . " SET size = :size WHERE id = :id");
                    $statement->execute([':size' => $newSize, ':id' => $fileId]);
                }

                // Clean up temp file
                @unlink($tempFile);
            }

            // Log the save action
            $logger = new ActionsLog();
            $logger->addEntry([
                'action' => 32, // File edited
                'owner_id' => isset($data['users'][0]) ? (int) $data['users'][0] : 0,
                'affected_file' => $fileId,
                'affected_file_name' => $file->title,
                'details' => 'Document edited via ONLYOFFICE'
            ]);

            error_log("OnlyOffice callback - File saved successfully: $fileId");
            sendResponse(0);

        } catch (\Exception $e) {
            error_log("OnlyOffice callback - Save error: " . $e->getMessage());
            sendResponse(1, 'Failed to save document: ' . $e->getMessage());
        }
        break;

    case 3:
    case 7:
        // Error occurred - log and acknowledge
        error_log("OnlyOffice callback - Error status $status for file $fileId");
        sendResponse(0);
        break;

    case 4:
        // Document closed without changes - acknowledge
        sendResponse(0);
        break;

    default:
        // Unknown status - acknowledge
        sendResponse(0);
        break;
}
