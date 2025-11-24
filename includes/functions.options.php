<?php
// global $options: \ProjectSend\Classes\Options already set in app init

function option_exists($name)
{
    global $dbh;
    $statement = $dbh->prepare("SELECT name FROM " . TABLE_OPTIONS . " WHERE name=:name");
    $statement->execute([
        ':name' => $name,
    ]);
    return ($statement->rowCount() > 0);
}

function get_option($name, $escape = false, $default = null)
{
    global $dbh;
    if (empty($dbh)) {
        return $default;
    }

    // List of options that are encrypted
    $encrypted_options = ['ldap_admin_password'];

    try {
        if (table_exists(TABLE_OPTIONS)) {
            $statement = $dbh->prepare("SELECT * FROM " . TABLE_OPTIONS . " WHERE name=:name");
            $statement->execute([
                ':name' => $name,
            ]);
            if ($statement->rowCount() == 0) {
                return $default;
            }

            $statement->setFetchMode(PDO::FETCH_ASSOC);
            while ( $row = $statement->fetch() ) {
                $value = $row['value'];

                // Decrypt if this is an encrypted option
                if (in_array($name, $encrypted_options)) {
                    $value = decrypt_option_value($value);
                }

                if ($escape == true) {
                    $value = html_output($value);
                }

                return $value;
            }
        }
    } catch (\PDOException $e) {
        return $default;
    }

    return $default;
}

/**
 * Decrypt an option value.
 *
 * @param string $value The encrypted value to decrypt.
 * @return string The decrypted value, or empty string if decryption fails.
 */
function decrypt_option_value($value)
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

    $master_key = get_option_master_key();
    if (!$master_key) {
        error_log('WARNING: Cannot decrypt option - no master key available');
        return '';
    }

    $algorithm = 'aes-256-gcm';
    $iv_length = openssl_cipher_iv_length($algorithm);
    $tag_length = 16; // GCM tag is 16 bytes

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

function save_option($name, $value)
{
    global $dbh;

    // List of options that should be encrypted
    $encrypted_options = ['ldap_admin_password'];

    // Encrypt value if needed
    if (in_array($name, $encrypted_options)) {
        $value = encrypt_option_value($value);
    }

    if (option_exists($name)) {
        $save = $dbh->prepare( "UPDATE " . TABLE_OPTIONS . " SET value=:value WHERE name=:name" );
        $save->bindParam(':value', $value);
        $save->bindParam(':name', $name);
        $result = $save->execute();
    }
    else {
        if (!empty($dbh)) {
            $save = $dbh->prepare("INSERT INTO " . TABLE_OPTIONS . " (name, value)"
            ." VALUES (:name, :value)");
            $save->bindParam(':name', $name);
            $save->bindParam(':value', $value);
            $result = $save->execute();
        }
    }

    return $result;
}

/**
 * Encrypt an option value for secure storage.
 *
 * @param string $value The value to encrypt.
 * @return string The encrypted value with 'ENC:' prefix, or the original value if encryption fails.
 */
function encrypt_option_value($value)
    if (empty($value)) {
        return '';
    }

    // Get master key from HASH_SALT or ENCRYPTION_MASTER_KEY
    $master_key = get_option_master_key();
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
 * Get or generate the master encryption key for options
 */
/**
 * Get or generate the master encryption key for options.
 *
 * @return string|null The master encryption key as binary data, or null if no key is available.
 */
function get_option_master_key()
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
