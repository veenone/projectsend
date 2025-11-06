<?php
/**
 * Test page to verify the import external files feature is working
 * Access this at: http://your-projectsend-url/test-import-feature.php
 */

// Check if FolderStructureImporter class exists
$class_file = __DIR__ . '/includes/Classes/FolderStructureImporter.php';
$class_exists = file_exists($class_file);

// Check if import-external.php has the checkbox
$import_file = __DIR__ . '/import-external.php';
$import_exists = file_exists($import_file);
$has_checkbox = false;

if ($import_exists) {
    $import_content = file_get_contents($import_file);
    $has_checkbox = strpos($import_content, 'preserve_folders') !== false;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Import Feature Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-result {
            background: white;
            padding: 20px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #ccc;
        }
        .pass {
            border-left-color: #28a745;
        }
        .fail {
            border-left-color: #dc3545;
        }
        .pass::before {
            content: "✅ ";
        }
        .fail::before {
            content: "❌ ";
        }
        h1 {
            color: #333;
        }
        .info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>Import External Files Feature - Verification</h1>

    <div class="test-result <?php echo $class_exists ? 'pass' : 'fail'; ?>">
        <strong>FolderStructureImporter Class:</strong>
        <?php if ($class_exists): ?>
            Found at <code><?php echo $class_file; ?></code>
        <?php else: ?>
            NOT FOUND - Expected at <code><?php echo $class_file; ?></code>
        <?php endif; ?>
    </div>

    <div class="test-result <?php echo $import_exists ? 'pass' : 'fail'; ?>">
        <strong>Import External Files Page:</strong>
        <?php if ($import_exists): ?>
            Found at <code><?php echo $import_file; ?></code>
        <?php else: ?>
            NOT FOUND - Expected at <code><?php echo $import_file; ?></code>
        <?php endif; ?>
    </div>

    <div class="test-result <?php echo $has_checkbox ? 'pass' : 'fail'; ?>">
        <strong>Preserve Folders Checkbox:</strong>
        <?php if ($has_checkbox): ?>
            Feature code is present in import-external.php
        <?php else: ?>
            NOT FOUND - The preserve_folders checkbox is missing
        <?php endif; ?>
    </div>

    <?php if ($class_exists && $import_exists && $has_checkbox): ?>
        <div class="info">
            <h3>✅ All checks passed!</h3>
            <p>The folder import feature has been successfully installed.</p>
            <p><strong>Next steps:</strong></p>
            <ol>
                <li>Make sure you're logged in as an administrator</li>
                <li>Navigate to: <a href="import-external.php">Import External Files</a></li>
                <li>Select an S3 integration and list files</li>
                <li>You should see the "Preserve folder structure" checkbox below the file list</li>
            </ol>
            <p><strong>Direct link:</strong> <a href="import-external.php">Go to Import External Files</a></p>
        </div>
    <?php else: ?>
        <div class="info" style="background: #ffe7e7;">
            <h3>❌ Some checks failed</h3>
            <p>Please verify the files were created correctly or contact support.</p>
        </div>
    <?php endif; ?>

    <div style="margin-top: 30px; padding: 20px; background: white; border-radius: 5px;">
        <h3>File Locations</h3>
        <ul>
            <li><strong>Helper Class:</strong> <code>includes/Classes/FolderStructureImporter.php</code></li>
            <li><strong>Import Page:</strong> <code>import-external.php</code></li>
            <li><strong>Documentation:</strong> <code>FEATURE_FOLDER_IMPORT.md</code></li>
        </ul>
    </div>
</body>
</html>
