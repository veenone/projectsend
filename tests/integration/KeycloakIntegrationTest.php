<?php
/**
 * Keycloak Integration Test Suite
 * 
 * This test suite validates the complete integration between ProjectSend
 * and Keycloak, testing real authentication flows and user provisioning.
 * 
 * @package ProjectSend
 * @subpackage Tests\Integration
 */

require_once dirname(__FILE__) . '/../../vendor/autoload.php';
require_once dirname(__FILE__) . '/../../includes/classes/class.oidc.php';

use PHPUnit\Framework\TestCase;
use ProjectSend\Classes\OIDC\OIDCAuthentication;
use ProjectSend\Classes\OIDC\KeycloakClient;

class KeycloakIntegrationTest extends TestCase
{
    private $oidcAuth;
    private $keycloakClient;
    private $testConfig;
    private $testRealm;
    private $testUsers;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Load test configuration
        $this->testConfig = [
            'oidc_enabled' => true,
            'oidc_provider_url' => getenv('TEST_KEYCLOAK_URL') ?: 'http://localhost:8080/realms/projectsend-test',
            'oidc_client_id' => getenv('TEST_CLIENT_ID') ?: 'projectsend-integration-test',
            'oidc_client_secret' => getenv('TEST_CLIENT_SECRET') ?: 'test-client-secret',
            'oidc_redirect_uri' => 'http://localhost/projectsend/oidc-callback.php',
            'oidc_scopes' => 'openid profile email groups'
        ];
        
        $this->testRealm = 'projectsend-test';
        
        // Initialize OIDC authentication
        $this->oidcAuth = new OIDCAuthentication($this->testConfig);
        $this->keycloakClient = new KeycloakClient($this->testConfig);
        
        // Define test users
        $this->testUsers = [
            'admin_user' => [
                'username' => 'test.admin',
                'email' => 'test.admin@example.com',
                'firstName' => 'Test',
                'lastName' => 'Admin',
                'enabled' => true,
                'groups' => ['ProjectSend Administrators'],
                'credentials' => [['type' => 'password', 'value' => 'TestAdmin123!', 'temporary' => false]]
            ],
            'regular_user' => [
                'username' => 'test.user',
                'email' => 'test.user@example.com',
                'firstName' => 'Test',
                'lastName' => 'User',
                'enabled' => true,
                'groups' => ['ProjectSend Users'],
                'credentials' => [['type' => 'password', 'value' => 'TestUser123!', 'temporary' => false]]
            ],
            'client_user' => [
                'username' => 'test.client',
                'email' => 'test.client@example.com',
                'firstName' => 'Test',
                'lastName' => 'Client',
                'enabled' => true,
                'groups' => ['ProjectSend Clients'],
                'credentials' => [['type' => 'password', 'value' => 'TestClient123!', 'temporary' => false]]
            ]
        ];
    }
    
    /**
     * Test Keycloak connectivity and discovery
     */
    public function testKeycloakConnectivity()
    {
        $this->markTestSkippedIfKeycloakNotAvailable();
        
        // Test discovery endpoint
        $discoveryUrl = $this->testConfig['oidc_provider_url'] . '/.well-known/openid_configuration';
        $discovery = $this->keycloakClient->getDiscoveryDocument();
        
        $this->assertIsArray($discovery);
        $this->assertArrayHasKey('issuer', $discovery);
        $this->assertArrayHasKey('authorization_endpoint', $discovery);
        $this->assertArrayHasKey('token_endpoint', $discovery);
        $this->assertArrayHasKey('userinfo_endpoint', $discovery);
        $this->assertArrayHasKey('jwks_uri', $discovery);
        
        // Verify endpoints are accessible
        $this->assertStringContainsString('keycloak', $discovery['issuer']);
        $this->assertStringContainsString('/auth', $discovery['authorization_endpoint']);
        $this->assertStringContainsString('/token', $discovery['token_endpoint']);
    }
    
    /**
     * Test Keycloak realm configuration
     */
    public function testRealmConfiguration()
    {
        $this->markTestSkippedIfKeycloakNotAvailable();
        
        // Test realm accessibility
        $realmInfo = $this->keycloakClient->getRealmInfo();
        
        $this->assertIsArray($realmInfo);
        $this->assertEquals($this->testRealm, $realmInfo['realm']);
        $this->assertTrue($realmInfo['enabled']);
        
        // Test client configuration
        $clientInfo = $this->keycloakClient->getClientInfo();
        
        $this->assertIsArray($clientInfo);
        $this->assertEquals($this->testConfig['oidc_client_id'], $clientInfo['clientId']);
        $this->assertTrue($clientInfo['enabled']);
        $this->assertTrue($clientInfo['standardFlowEnabled']);
    }
    
    /**
     * Test complete authentication flow with admin user
     */
    public function testAdminUserAuthenticationFlow()
    {
        $this->markTestSkippedIfKeycloakNotAvailable();
        
        // Create test admin user
        $this->createTestUser('admin_user');
        
        try {
            // Step 1: Generate authorization URL
            $state = $this->oidcAuth->generateState();
            $nonce = $this->oidcAuth->generateNonce();
            $authUrl = $this->oidcAuth->getAuthorizationUrl($state, $nonce);
            
            $this->assertStringContainsString('response_type=code', $authUrl);
            $this->assertStringContainsString('state=' . $state, $authUrl);
            $this->assertStringContainsString('nonce=' . $nonce, $authUrl);
            
            // Step 2: Simulate user authentication and get authorization code
            $authCode = $this->simulateUserAuthentication('admin_user', $authUrl);
            $this->assertNotEmpty($authCode);
            
            // Step 3: Exchange authorization code for tokens
            $tokenResponse = $this->oidcAuth->exchangeCodeForTokens($authCode, $state);
            
            $this->assertTrue($tokenResponse['success']);
            $this->assertArrayHasKey('access_token', $tokenResponse);
            $this->assertArrayHasKey('id_token', $tokenResponse);
            $this->assertArrayHasKey('refresh_token', $tokenResponse);
            
            // Step 4: Validate ID token
            $idTokenPayload = $this->oidcAuth->validateIdToken($tokenResponse['id_token'], $nonce);
            
            $this->assertEquals('test.admin', $idTokenPayload['preferred_username']);
            $this->assertEquals('test.admin@example.com', $idTokenPayload['email']);
            $this->assertContains('ProjectSend Administrators', $idTokenPayload['groups']);
            
            // Step 5: Test user provisioning
            $userResult = $this->oidcAuth->provisionUser($idTokenPayload);
            
            $this->assertTrue($userResult['success']);
            $this->assertEquals('a', $userResult['user']['role']); // Admin role
            $this->assertEquals('test.admin', $userResult['user']['username']);
            
        } finally {
            $this->cleanupTestUser('admin_user');
        }
    }
    
    /**
     * Test complete authentication flow with regular user
     */
    public function testRegularUserAuthenticationFlow()
    {
        $this->markTestSkippedIfKeycloakNotAvailable();
        
        $this->createTestUser('regular_user');
        
        try {
            // Complete authentication flow
            $result = $this->performCompleteAuthFlow('regular_user');
            
            $this->assertTrue($result['success']);
            $this->assertEquals('u', $result['user']['role']); // User role
            $this->assertEquals('test.user', $result['user']['username']);
            $this->assertEquals('test.user@example.com', $result['user']['email']);
            
        } finally {
            $this->cleanupTestUser('regular_user');
        }
    }
    
    /**
     * Test complete authentication flow with client user
     */
    public function testClientUserAuthenticationFlow()
    {
        $this->markTestSkippedIfKeycloakNotAvailable();
        
        $this->createTestUser('client_user');
        
        try {
            $result = $this->performCompleteAuthFlow('client_user');
            
            $this->assertTrue($result['success']);
            $this->assertEquals('c', $result['user']['role']); // Client role
            $this->assertEquals('test.client', $result['user']['username']);
            
        } finally {
            $this->cleanupTestUser('client_user');
        }
    }
    
    /**
     * Test user update on subsequent logins
     */
    public function testUserUpdateOnLogin()
    {
        $this->markTestSkippedIfKeycloakNotAvailable();
        
        $this->createTestUser('regular_user');
        
        try {
            // First login - create user
            $result1 = $this->performCompleteAuthFlow('regular_user');
            $this->assertTrue($result1['success']);
            $originalUserId = $result1['user']['id'];
            
            // Update user in Keycloak
            $this->updateTestUser('regular_user', [
                'firstName' => 'Updated',
                'lastName' => 'Name',
                'email' => 'updated.email@example.com'
            ]);
            
            // Second login - update user
            $result2 = $this->performCompleteAuthFlow('regular_user');
            $this->assertTrue($result2['success']);
            $this->assertEquals($originalUserId, $result2['user']['id']); // Same user
            $this->assertEquals('Updated Name', $result2['user']['name']); // Updated name
            $this->assertEquals('updated.email@example.com', $result2['user']['email']); // Updated email
            
        } finally {
            $this->cleanupTestUser('regular_user');
        }
    }
    
    /**
     * Test role changes based on group membership
     */
    public function testRoleChangeOnGroupUpdate()
    {
        $this->markTestSkippedIfKeycloakNotAvailable();
        
        $this->createTestUser('regular_user');
        
        try {
            // Initial login as regular user
            $result1 = $this->performCompleteAuthFlow('regular_user');
            $this->assertEquals('u', $result1['user']['role']); // User role
            
            // Update user groups to admin
            $this->updateTestUserGroups('regular_user', ['ProjectSend Administrators']);
            
            // Login again - should have admin role
            $result2 = $this->performCompleteAuthFlow('regular_user');
            $this->assertEquals('a', $result2['user']['role']); // Admin role
            
            // Update user groups to client
            $this->updateTestUserGroups('regular_user', ['ProjectSend Clients']);
            
            // Login again - should have client role
            $result3 = $this->performCompleteAuthFlow('regular_user');
            $this->assertEquals('c', $result3['user']['role']); // Client role
            
        } finally {
            $this->cleanupTestUser('regular_user');
        }
    }
    
    /**
     * Test token refresh functionality
     */
    public function testTokenRefresh()
    {
        $this->markTestSkippedIfKeycloakNotAvailable();
        
        $this->createTestUser('regular_user');
        
        try {
            // Complete authentication to get tokens
            $result = $this->performCompleteAuthFlow('regular_user');
            $this->assertTrue($result['success']);
            
            $originalRefreshToken = $result['tokens']['refresh_token'];
            $originalAccessToken = $result['tokens']['access_token'];
            
            // Test token refresh
            $refreshResult = $this->oidcAuth->refreshTokens($originalRefreshToken);
            
            $this->assertTrue($refreshResult['success']);
            $this->assertArrayHasKey('access_token', $refreshResult);
            $this->assertArrayHasKey('refresh_token', $refreshResult);
            
            // Tokens should be different
            $this->assertNotEquals($originalAccessToken, $refreshResult['access_token']);
            
            // New tokens should be valid
            $this->assertTrue($this->oidcAuth->validateAccessToken($refreshResult['access_token']));
            
        } finally {
            $this->cleanupTestUser('regular_user');
        }
    }
    
    /**
     * Test logout functionality
     */
    public function testLogoutFunctionality()
    {
        $this->markTestSkippedIfKeycloakNotAvailable();
        
        $this->createTestUser('regular_user');
        
        try {
            // Complete authentication
            $result = $this->performCompleteAuthFlow('regular_user');
            $this->assertTrue($result['success']);
            
            $idToken = $result['tokens']['id_token'];
            
            // Test logout URL generation
            $logoutUrl = $this->oidcAuth->getLogoutUrl('http://localhost/projectsend/goodbye.php');
            
            $this->assertStringContainsString('post_logout_redirect_uri', $logoutUrl);
            $this->assertStringContainsString('id_token_hint', $logoutUrl);
            
            // Test logout execution
            $logoutResult = $this->oidcAuth->performLogout($idToken);
            $this->assertTrue($logoutResult['success']);
            
        } finally {
            $this->cleanupTestUser('regular_user');
        }
    }
    
    /**
     * Test error handling for invalid credentials
     */
    public function testInvalidCredentialsHandling()
    {
        $this->markTestSkippedIfKeycloakNotAvailable();
        
        // Test with invalid client credentials
        $invalidConfig = $this->testConfig;
        $invalidConfig['oidc_client_secret'] = 'invalid-secret';
        
        $invalidOidc = new OIDCAuthentication($invalidConfig);
        
        $state = $invalidOidc->generateState();
        $authUrl = $invalidOidc->getAuthorizationUrl($state, 'test-nonce');
        
        // This should work (URL generation doesn't validate credentials)
        $this->assertStringContainsString('response_type=code', $authUrl);
        
        // But token exchange should fail
        $tokenResult = $invalidOidc->exchangeCodeForTokens('test-code', $state);
        $this->assertFalse($tokenResult['success']);
        $this->assertStringContainsString('invalid_client', $tokenResult['error']);
    }
    
    /**
     * Test error handling for disabled users
     */
    public function testDisabledUserHandling()
    {
        $this->markTestSkippedIfKeycloakNotAvailable();
        
        $this->createTestUser('regular_user');
        
        try {
            // Disable the user
            $this->disableTestUser('regular_user');
            
            // Attempt authentication should fail
            $result = $this->performCompleteAuthFlow('regular_user');
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('user disabled', strtolower($result['error']));
            
        } finally {
            $this->cleanupTestUser('regular_user');
        }
    }
    
    /**
     * Test group membership validation
     */
    public function testGroupMembershipValidation()
    {
        $this->markTestSkippedIfKeycloakNotAvailable();
        
        // Create user without any ProjectSend groups
        $noGroupUser = [
            'username' => 'no.groups',
            'email' => 'no.groups@example.com',
            'firstName' => 'No',
            'lastName' => 'Groups',
            'enabled' => true,
            'groups' => [], // No groups
            'credentials' => [['type' => 'password', 'value' => 'NoGroups123!', 'temporary' => false]]
        ];
        
        $this->keycloakClient->createUser($noGroupUser);
        
        try {
            $result = $this->performCompleteAuthFlow('no_groups');
            
            // User should be created with default role
            $this->assertTrue($result['success']);
            $this->assertEquals('c', $result['user']['role']); // Default client role
            
        } finally {
            $this->keycloakClient->deleteUser('no.groups');
        }
    }
    
    /**
     * Test concurrent authentication
     */
    public function testConcurrentAuthentication()
    {
        $this->markTestSkippedIfKeycloakNotAvailable();
        
        // Create multiple test users
        $this->createTestUser('regular_user');
        $this->createTestUser('client_user');
        
        try {
            // Simulate concurrent authentication
            $results = [];
            $users = ['regular_user', 'client_user'];
            
            foreach ($users as $userType) {
                $results[$userType] = $this->performCompleteAuthFlow($userType);
            }
            
            // Both authentications should succeed
            $this->assertTrue($results['regular_user']['success']);
            $this->assertTrue($results['client_user']['success']);
            
            // Users should have correct roles
            $this->assertEquals('u', $results['regular_user']['user']['role']);
            $this->assertEquals('c', $results['client_user']['user']['role']);
            
        } finally {
            $this->cleanupTestUser('regular_user');
            $this->cleanupTestUser('client_user');
        }
    }
    
    /**
     * Helper method to check if Keycloak is available
     */
    private function isKeycloakAvailable()
    {
        try {
            $discoveryUrl = $this->testConfig['oidc_provider_url'] . '/.well-known/openid_configuration';
            $response = file_get_contents($discoveryUrl);
            return $response !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Skip test if Keycloak is not available
     */
    private function markTestSkippedIfKeycloakNotAvailable()
    {
        if (!$this->isKeycloakAvailable()) {
            $this->markTestSkipped('Keycloak test instance is not available');
        }
    }
    
    /**
     * Create a test user in Keycloak
     */
    private function createTestUser($userType)
    {
        if (!isset($this->testUsers[$userType])) {
            throw new InvalidArgumentException("Unknown user type: $userType");
        }
        
        $userData = $this->testUsers[$userType];
        return $this->keycloakClient->createUser($userData);
    }
    
    /**
     * Update a test user in Keycloak
     */
    private function updateTestUser($userType, $updates)
    {
        $username = $this->testUsers[$userType]['username'];
        return $this->keycloakClient->updateUser($username, $updates);
    }
    
    /**
     * Update test user groups
     */
    private function updateTestUserGroups($userType, $groups)
    {
        $username = $this->testUsers[$userType]['username'];
        return $this->keycloakClient->updateUserGroups($username, $groups);
    }
    
    /**
     * Disable a test user
     */
    private function disableTestUser($userType)
    {
        $username = $this->testUsers[$userType]['username'];
        return $this->keycloakClient->updateUser($username, ['enabled' => false]);
    }
    
    /**
     * Clean up test user
     */
    private function cleanupTestUser($userType)
    {
        $username = $this->testUsers[$userType]['username'];
        
        try {
            $this->keycloakClient->deleteUser($username);
        } catch (Exception $e) {
            // User may not exist, ignore error
        }
        
        // Also clean up from ProjectSend database
        try {
            $this->cleanupProjectSendUser($username);
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }
    
    /**
     * Clean up user from ProjectSend database
     */
    private function cleanupProjectSendUser($username)
    {
        global $dbh;
        
        $statement = $dbh->prepare("DELETE FROM tbl_users WHERE username = ?");
        $statement->execute([$username]);
    }
    
    /**
     * Simulate user authentication and return authorization code
     */
    private function simulateUserAuthentication($userType, $authUrl)
    {
        // In a real integration test, this would use a browser automation tool
        // like Selenium to actually perform the authentication flow
        
        $userData = $this->testUsers[$userType];
        
        // For this test, we'll simulate the process
        return $this->keycloakClient->simulateAuthenticationFlow(
            $userData['username'],
            $userData['credentials'][0]['value'],
            $authUrl
        );
    }
    
    /**
     * Perform complete authentication flow
     */
    private function performCompleteAuthFlow($userType)
    {
        $state = $this->oidcAuth->generateState();
        $nonce = $this->oidcAuth->generateNonce();
        $authUrl = $this->oidcAuth->getAuthorizationUrl($state, $nonce);
        
        $authCode = $this->simulateUserAuthentication($userType, $authUrl);
        
        if (!$authCode) {
            return ['success' => false, 'error' => 'Failed to get authorization code'];
        }
        
        $tokenResponse = $this->oidcAuth->exchangeCodeForTokens($authCode, $state);
        
        if (!$tokenResponse['success']) {
            return $tokenResponse;
        }
        
        $idTokenPayload = $this->oidcAuth->validateIdToken($tokenResponse['id_token'], $nonce);
        
        if (!$idTokenPayload) {
            return ['success' => false, 'error' => 'Invalid ID token'];
        }
        
        $userResult = $this->oidcAuth->provisionUser($idTokenPayload);
        
        return [
            'success' => true,
            'user' => $userResult['user'],
            'tokens' => $tokenResponse
        ];
    }
    
    /**
     * Test cleanup
     */
    protected function tearDown(): void
    {
        // Clean up any remaining test users
        foreach (array_keys($this->testUsers) as $userType) {
            try {
                $this->cleanupTestUser($userType);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        parent::tearDown();
    }
}