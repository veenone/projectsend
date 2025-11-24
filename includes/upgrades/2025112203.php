<?php
/**
 * Add LDAP password change configuration option
 */
function upgrade_2025112203()
{
    global $dbh;

    // Add option to disable local password changes for LDAP users
    // Default is 'true' (disabled) - LDAP users should change password via LDAP
    if (!option_exists_2025112203('ldap_disable_password_change')) {
        $stmt = $dbh->prepare("INSERT INTO " . TABLE_OPTIONS . " (name, value) VALUES (:name, :value)");
        $stmt->execute([
            ':name' => 'ldap_disable_password_change',
            ':value' => 'true'
        ]);
        error_log("ProjectSend: Added ldap_disable_password_change option");
    }
}

function option_exists_2025112203($name)
{
    global $dbh;
    $stmt = $dbh->prepare("SELECT COUNT(*) as count FROM " . TABLE_OPTIONS . " WHERE name = :name");
    $stmt->execute([':name' => $name]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}
