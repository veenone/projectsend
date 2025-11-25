<?php
/**
 * Database Upgrade: Add Internal User Role
 *
 * Creates a new "Internal User" role for internal employees who need file access
 * but should not have administrative privileges. This role is designed for:
 * - Internal company employees
 * - LDAP-authenticated users
 * - Users who need to upload and share files but not manage the system
 *
 * The role includes permissions for:
 * - Uploading files
 * - Editing own files
 * - Deleting own files
 * - Setting file categories
 * - Marking files as public
 * - Managing own account
 */

function upgrade_2025112501()
{
    global $dbh;

    // Step 1: Check if Internal User role already exists
    $check_role_sql = "SELECT id FROM " . TABLE_ROLES . " WHERE name = 'Internal User'";
    $statement = $dbh->prepare($check_role_sql);
    $statement->execute();

    if ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        // Role already exists, skip creation
        $internal_user_role_id = $row['id'];
    } else {
        // Create the Internal User role
        $insert_role_sql = "INSERT INTO " . TABLE_ROLES . "
                           (name, description, is_system_role, permissions_editable, active)
                           VALUES (:name, :description, 0, 1, 1)";
        $statement = $dbh->prepare($insert_role_sql);
        $statement->execute([
            'name' => 'Internal User',
            'description' => 'Internal employees with file access and limited upload capabilities'
        ]);
        $internal_user_role_id = $dbh->lastInsertId();
    }

    // Step 2: Check if role_permissions table exists
    if (!table_exists(TABLE_ROLE_PERMISSIONS)) {
        // If the table doesn't exist, we can't set permissions
        // The permissions will need to be set manually via the UI
        return;
    }

    // Step 3: Set default permissions for Internal User role
    $default_permissions = [
        'upload',                   // Can upload files
        'edit_files',              // Can edit own files
        'delete_files',            // Can delete own files
        'edit_self_account',       // Can edit own account
        'set_file_categories',     // Can assign categories to files
        'upload_public',           // Can mark files as public
    ];

    // Remove any existing permissions for this role first (in case of re-run)
    $delete_perms_sql = "DELETE FROM " . TABLE_ROLE_PERMISSIONS . " WHERE role_id = :role_id";
    $statement = $dbh->prepare($delete_perms_sql);
    $statement->execute(['role_id' => $internal_user_role_id]);

    // Insert permissions
    $insert_perm_sql = "INSERT INTO " . TABLE_ROLE_PERMISSIONS . " (role_id, permission, allowed)
                       VALUES (:role_id, :permission, 1)";
    $statement = $dbh->prepare($insert_perm_sql);

    foreach ($default_permissions as $permission) {
        $statement->execute([
            'role_id' => $internal_user_role_id,
            'permission' => $permission
        ]);
    }

    // Step 4: Add LDAP default role option if it doesn't exist
    // This allows administrators to configure which role LDAP users get by default
    if (!option_exists('ldap_default_role')) {
        // Get the Client role ID as the default fallback
        $client_role_sql = "SELECT id FROM " . TABLE_ROLES . " WHERE name = 'Client' LIMIT 1";
        $statement = $dbh->prepare($client_role_sql);
        $statement->execute();
        $client_role = $statement->fetch(PDO::FETCH_ASSOC);
        $default_role_id = $client_role ? $client_role['id'] : $internal_user_role_id;

        // Set Internal User as the default for LDAP if it exists
        save_option('ldap_default_role', $internal_user_role_id);
    }

    // Step 5: Update database version
    save_option('database_version', '2025112501');
}
