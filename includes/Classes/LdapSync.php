<?php
/**
 * Class that handles bulk LDAP user synchronization
 */
namespace ProjectSend\Classes;
use \PDO;

class LdapSync
{
    private $dbh;
    private $logger;
    private $stats;
    private $errors;
    private $dry_run;

    public function __construct(PDO $dbh = null)
    {
        if (empty($dbh)) {
            global $dbh;
        }

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;
        $this->resetStats();
    }

    /**
     * Reset sync statistics
     */
    private function resetStats()
    {
        $this->stats = [
            'total_found' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'users' => []
        ];
        $this->errors = [];
    }

    /**
     * Enable or disable dry run mode
     */
    public function setDryRun($dry_run = true)
    {
        $this->dry_run = $dry_run;
    }

    /**
     * Get sync statistics
     */
    public function getStats()
    {
        return $this->stats;
    }

    /**
     * Get sync errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Connect to LDAP server
     */
    private function connectLdap()
    {
        // Check if LDAP is enabled
        if (get_option('ldap_signin_enabled') !== 'true') {
            throw new \Exception(__('LDAP authentication is not enabled.', 'cftp_admin'));
        }

        // Check if LDAP extension is loaded
        if (!extension_loaded('ldap')) {
            throw new \Exception(__('LDAP extension is not loaded in PHP.', 'cftp_admin'));
        }

        // Get LDAP settings
        $ldap_server = get_option('ldap_hosts');
        $ldap_admin_user = get_option('ldap_admin_user');
        $ldap_admin_password = get_option('ldap_admin_password');
        $ldap_protocol_version = get_option('ldap_protocol_version', null, '3');

        // Validate required settings
        if (empty($ldap_server) || empty($ldap_admin_user) || empty($ldap_admin_password)) {
            throw new \Exception(__('LDAP server settings are incomplete. Please configure LDAP settings first.', 'cftp_admin'));
        }

        // Connect to LDAP
        $ldap = ldap_connect($ldap_server);
        if (!$ldap) {
            throw new \Exception(__('Could not connect to LDAP server.', 'cftp_admin'));
        }

        // Set LDAP options
        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$ldap_protocol_version);
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

        // Use TLS if configured
        if (get_option('ldap_use_tls') === 'true') {
            if (!ldap_start_tls($ldap)) {
                ldap_close($ldap);
                throw new \Exception(__('Could not start TLS connection to LDAP server.', 'cftp_admin'));
            }
        }

        // Bind with admin credentials
        $bind = @ldap_bind($ldap, $ldap_admin_user, $ldap_admin_password);
        if (!$bind) {
            $error = ldap_error($ldap);
            ldap_close($ldap);
            throw new \Exception(sprintf(__('Could not bind to LDAP server: %s', 'cftp_admin'), $error));
        }

        return $ldap;
    }

    /**
     * Search for users in LDAP
     */
    private function searchLdapUsers($ldap)
    {
        $ldap_search_base = get_option('ldap_search_base');
        $sync_filter = get_option('ldap_sync_filter', null, '(objectClass=person)');

        if (empty($ldap_search_base)) {
            throw new \Exception(__('LDAP search base is not configured.', 'cftp_admin'));
        }

        // Get attributes we need
        $attributes = [
            get_option('ldap_email_attribute', null, 'mail'),
            get_option('ldap_name_attribute', null, 'cn'),
            get_option('ldap_username_attribute', null, 'uid'),
            'telephoneNumber',
            'mobile',
            'streetAddress',
            'postalAddress'
        ];

        // Search for users
        $search_result = @ldap_search($ldap, $ldap_search_base, $sync_filter, $attributes);
        if (!$search_result) {
            $error = ldap_error($ldap);
            throw new \Exception(sprintf(__('Could not search LDAP directory: %s', 'cftp_admin'), $error));
        }

        $entries = ldap_get_entries($ldap, $search_result);
        if (!$entries) {
            throw new \Exception(__('Could not retrieve LDAP search results.', 'cftp_admin'));
        }

        return $entries;
    }

    /**
     * Extract attribute value from LDAP entry
     */
    private function extractLdapAttribute($entry, $attributes, $default = '')
    {
        if (!is_array($attributes)) {
            $attributes = [$attributes];
        }

        foreach ($attributes as $attr) {
            $attr_lower = strtolower($attr);
            if (isset($entry[$attr_lower][0]) && !empty($entry[$attr_lower][0])) {
                return $entry[$attr_lower][0];
            }
        }

        return $default;
    }

    /**
     * Check if user exists by email
     */
    private function findUserByEmail($email)
    {
        $statement = $this->dbh->prepare("SELECT id FROM " . TABLE_USERS . " WHERE email = :email");
        $statement->execute([':email' => $email]);

        if ($statement->rowCount() > 0) {
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            return $row['id'];
        }

        return null;
    }

    /**
     * Sync a single LDAP user entry
     */
    private function syncUser($entry)
    {
        $email_attr = get_option('ldap_email_attribute', null, 'mail');
        $email = $this->extractLdapAttribute($entry, $email_attr);

        // Skip if no email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->stats['skipped']++;
            $this->errors[] = sprintf(
                __('Skipped entry %s: Invalid or missing email address', 'cftp_admin'),
                $this->extractLdapAttribute($entry, 'dn', 'unknown')
            );
            return false;
        }

        // Check if user exists
        $user_id = $this->findUserByEmail($email);

        if ($user_id) {
            // User exists - update if needed
            if ($this->dry_run) {
                $this->stats['updated']++;
                $this->stats['users'][] = [
                    'email' => $email,
                    'action' => 'update',
                    'id' => $user_id
                ];
                return true;
            }

            // Update user from LDAP
            $user = new \ProjectSend\Classes\Users($user_id);
            if ($user->isLdapUser()) {
                $user->syncFromLdap($entry);
                $this->stats['updated']++;
                $this->stats['users'][] = [
                    'email' => $email,
                    'action' => 'updated',
                    'id' => $user_id
                ];
            } else {
                $this->stats['skipped']++;
                $this->errors[] = sprintf(__('Skipped %s: User exists but is not an LDAP user', 'cftp_admin'), $email);
                return false;
            }
        } else {
            // User doesn't exist - create new
            if ($this->dry_run) {
                $this->stats['created']++;
                $this->stats['users'][] = [
                    'email' => $email,
                    'action' => 'create'
                ];
                return true;
            }

            // Create new user from LDAP
            $new_user = new \ProjectSend\Classes\Users();
            $create_result = $new_user->createFromLdap($entry, $email);

            if (!empty($create_result['id'])) {
                $this->stats['created']++;
                $this->stats['users'][] = [
                    'email' => $email,
                    'action' => 'created',
                    'id' => $create_result['id']
                ];

                // Log the action
                $this->logger->addEntry([
                    'action' => 46, // New action for LDAP sync
                    'owner_id' => $create_result['id'],
                    'owner_user' => $email,
                    'affected_account_name' => $this->extractLdapAttribute($entry, [get_option('ldap_name_attribute', null, 'cn')], $email),
                    'details' => 'User created via LDAP bulk sync'
                ]);
            } else {
                $this->stats['errors']++;
                $validation_errors = method_exists($new_user, 'getValidationErrors') ? $new_user->getValidationErrors() : [];
                $error_details = !empty($validation_errors) ? ': ' . implode(', ', $validation_errors) : '';
                $this->errors[] = sprintf(__('Error creating user %s from LDAP%s', 'cftp_admin'), $email, $error_details);
                return false;
            }
        }

        return true;
    }

    /**
     * Sync all users from LDAP
     */
    public function syncAllUsers($dry_run = false)
    {
        $this->setDryRun($dry_run);
        $this->resetStats();

        try {
            // Connect to LDAP
            $ldap = $this->connectLdap();

            // Search for users
            $entries = $this->searchLdapUsers($ldap);
            $this->stats['total_found'] = $entries['count'];

            // Sync each user and ensure LDAP connection is closed
            try {
                for ($i = 0; $i < $entries['count']; $i++) {
                    try {
                        $this->syncUser($entries[$i]);
                    } catch (\Exception $e) {
                        $this->stats['errors']++;
                        $this->errors[] = $e->getMessage();
                    }
                }
            } finally {
                ldap_close($ldap);
            }
            // Log the sync operation
            if (!$dry_run) {
                $this->logger->addEntry([
                    'action' => 49, // Unique action for LDAP bulk sync
                    'owner_id' => CURRENT_USER_ID,
                    'owner_user' => CURRENT_USER_USERNAME,
                    'details' => sprintf(
                        'LDAP bulk sync completed: %d found, %d created, %d updated, %d skipped, %d errors',
                        $this->stats['total_found'],
                        $this->stats['created'],
                        $this->stats['updated'],
                        $this->stats['skipped'],
                        $this->stats['errors']
                    )
                ]);
            }

            return [
                'status' => 'success',
                'stats' => $this->stats,
                'errors' => $this->errors,
                'dry_run' => $dry_run
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'stats' => $this->stats,
                'errors' => $this->errors
            ];
        }
    }
}
