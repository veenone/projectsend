<?php
/**
 * Encrypt LDAP admin password in database
 *
 * This upgrade encrypts the ldap_admin_password option value using AES-256-GCM
 * to secure sensitive LDAP credentials. Any existing plaintext passwords will be
 * encrypted automatically during this upgrade.
 *
 * @version 2025111901
 */
function upgrade_2025111901()
{
    global $dbh;

    // Check if ldap_admin_password option exists
    if (option_exists('ldap_admin_password')) {
        try {
            $dbh->beginTransaction();
            // Get the current value
            $statement = $dbh->prepare("SELECT value FROM " . TABLE_OPTIONS . " WHERE name = 'ldap_admin_password'");
            $statement->execute();
            $result = $statement->fetch(PDO::FETCH_ASSOC);

            if ($result && !empty($result['value'])) {
                $current_value = $result['value'];

                // Check if already encrypted (has ENC: prefix)
                if (substr($current_value, 0, 4) !== 'ENC:') {
                    // Not encrypted yet, encrypt it
                    $encrypted_value = encrypt_option_value($current_value);

                    // Update the database with encrypted value
                    $update = $dbh->prepare("UPDATE " . TABLE_OPTIONS . " SET value = :value WHERE name = 'ldap_admin_password'");
                    $update->bindParam(':value', $encrypted_value);
                    $update->execute();

                    // Log the encryption
                    $logger = new \ProjectSend\Classes\ActionsLog;
                    $logger->addEntry([
                        'action' => 50, // System configuration change
                        'owner_id' => 1, // System
                        'details' => [
                            'option_encrypted' => 'ldap_admin_password',
                            'description' => 'Encrypted LDAP admin password for secure storage',
                        ],
                    ]);
                }
            }
            $dbh->commit();
        } catch (Exception $e) {
            $dbh->rollBack();
            error_log("Failed to encrypt LDAP admin password: " . $e->getMessage());
            throw $e;
        }
    }
}
