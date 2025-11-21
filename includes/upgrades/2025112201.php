<?php
function upgrade_2025112201()
{
    global $dbh;

    // Add upload_directory_path option for configurable upload directory
    // Empty value means use default location (ROOT_DIR/upload)
    if (!option_exists('upload_directory_path')) {
        save_option('upload_directory_path', '');
        error_log("ProjectSend: Added upload_directory_path option (configurable upload directory)");
    }
}
