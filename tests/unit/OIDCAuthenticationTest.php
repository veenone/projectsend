<?php
/**
 * Unit Tests for OIDC Authentication Implementation
 * 
 * This test suite validates the core OIDC authentication functionality
 * including token validation, user provisioning, and security measures.
 * 
 * @package ProjectSend
 * @subpackage Tests\Unit
 */

require_once dirname(__FILE__) . '/../../vendor/autoload.php';
require_once dirname(__FILE__) . '/../../includes/classes/class.oidc.php';

use PHPUnit\Framework\TestCase;
use ProjectSend\Classes\OIDC\OIDCAuthentication;
use ProjectSend\Classes\OIDC\TokenValidator;
use ProjectSend\Classes\OIDC\UserProvisioning;

class OIDCAuthenticationTest extends TestCase
{
    private $oidcAuth;
    private $mockConfig;
    private $testProviderUrl;
    private $testClientId;
    private $testClientSecret;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock configuration for testing
        $this->mockConfig = [
            'oidc_enabled' => true,
            'oidc_provider_url' => 'https://test-keycloak.example.com/realms/test',
            'oidc_client_id' => 'test-client',
            'oidc_client_secret' => 'test-secret',
            'oidc_redirect_uri' => 'https://test-projectsend.example.com/oidc-callback.php',
            'oidc_scopes' => 'openid profile email groups',
            'oidc_user_mapping' => [
                'username' => 'preferred_username',
                'email' => 'email',
                'name' => 'name',
                'groups' => 'groups'
            ]
        ];
        
        $this->oidcAuth = new OIDCAuthentication($this->mockConfig);
        $this->testProviderUrl = $this->mockConfig['oidc_provider_url'];
        $this->testClientId = $this->mockConfig['oidc_client_id'];
        $this->testClientSecret = $this->mockConfig['oidc_client_secret'];
    }
    
    /**
     * Test OIDC configuration validation
     */
    public function testOIDCConfigurationValidation()
    {
        // Test valid configuration
        $this->assertTrue($this->oidcAuth->validateConfiguration());
        
        // Test invalid configuration - missing provider URL
        $invalidConfig = $this->mockConfig;
        unset($invalidConfig['oidc_provider_url']);
        $invalidAuth = new OIDCAuthentication($invalidConfig);
        $this->assertFalse($invalidAuth->validateConfiguration());
        
        // Test invalid configuration - missing client ID
        $invalidConfig = $this->mockConfig;
        unset($invalidConfig['oidc_client_id']);
        $invalidAuth = new OIDCAuthentication($invalidConfig);
        $this->assertFalse($invalidAuth->validateConfiguration());
    }
    
    /**
     * Test authorization URL generation
     */
    public function testAuthorizationURLGeneration()
    {
        $state = 'test-state-123';
        $nonce = 'test-nonce-456';
        
        $authUrl = $this->oidcAuth->getAuthorizationUrl($state, $nonce);
        
        $this->assertStringContainsString($this->testProviderUrl, $authUrl);
        $this->assertStringContainsString('response_type=code', $authUrl);
        $this->assertStringContainsString('client_id=' . $this->testClientId, $authUrl);
        $this->assertStringContainsString('state=' . $state, $authUrl);
        $this->assertStringContainsString('nonce=' . $nonce, $authUrl);
        $this->assertStringContainsString('scope=openid+profile+email+groups', $authUrl);
        
        // Test PKCE parameters
        $this->assertStringContainsString('code_challenge', $authUrl);
        $this->assertStringContainsString('code_challenge_method=S256', $authUrl);
    }
    
    /**
     * Test PKCE code verifier generation
     */
    public function testPKCECodeVerifierGeneration()
    {
        $codeVerifier = $this->oidcAuth->generateCodeVerifier();
        
        // Code verifier should be 43-128 characters long
        $this->assertGreaterThanOrEqual(43, strlen($codeVerifier));
        $this->assertLessThanOrEqual(128, strlen($codeVerifier));
        
        // Should contain only allowed characters
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-._~]+$/', $codeVerifier);
    }
    
    /**
     * Test PKCE code challenge generation
     */
    public function testPKCECodeChallengeGeneration()
    {
        $codeVerifier = 'test-code-verifier-123456789012345678901234567890';
        $codeChallenge = $this->oidcAuth->generateCodeChallenge($codeVerifier);
        
        // Code challenge should be base64url encoded
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $codeChallenge);
        
        // Should be deterministic
        $codeChallenge2 = $this->oidcAuth->generateCodeChallenge($codeVerifier);
        $this->assertEquals($codeChallenge, $codeChallenge2);
    }
    
    /**
     * Test state parameter generation and validation
     */
    public function testStateParameterHandling()
    {
        $state = $this->oidcAuth->generateState();
        
        // State should be sufficiently long and random
        $this->assertGreaterThanOrEqual(32, strlen($state));
        
        // Should be different each time
        $state2 = $this->oidcAuth->generateState();
        $this->assertNotEquals($state, $state2);
        
        // Test state validation
        $this->assertTrue($this->oidcAuth->validateState($state, $state));
        $this->assertFalse($this->oidcAuth->validateState($state, 'different-state'));
    }
    
    /**
     * Test nonce generation and validation
     */
    public function testNonceHandling()
    {
        $nonce = $this->oidcAuth->generateNonce();
        
        // Nonce should be sufficiently long and random
        $this->assertGreaterThanOrEqual(32, strlen($nonce));
        
        // Should be different each time
        $nonce2 = $this->oidcAuth->generateNonce();
        $this->assertNotEquals($nonce, $nonce2);
    }
    
    /**
     * Test JWT token parsing
     */
    public function testJWTTokenParsing()
    {
        // Mock JWT token (header.payload.signature)
        $mockHeader = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'RS256']));
        $mockPayload = base64url_encode(json_encode([
            'iss' => $this->testProviderUrl,
            'aud' => $this->testClientId,
            'sub' => 'user-123',
            'exp' => time() + 3600,
            'iat' => time(),
            'nonce' => 'test-nonce'
        ]));
        $mockSignature = 'mock-signature';
        $mockToken = $mockHeader . '.' . $mockPayload . '.' . $mockSignature;
        
        $parsedToken = $this->oidcAuth->parseJWT($mockToken);
        
        $this->assertEquals('JWT', $parsedToken['header']['typ']);
        $this->assertEquals('RS256', $parsedToken['header']['alg']);
        $this->assertEquals($this->testProviderUrl, $parsedToken['payload']['iss']);
        $this->assertEquals($this->testClientId, $parsedToken['payload']['aud']);
        $this->assertEquals('user-123', $parsedToken['payload']['sub']);
    }
    
    /**
     * Test JWT token validation
     */
    public function testJWTTokenValidation()
    {
        $tokenValidator = new TokenValidator($this->mockConfig);
        
        // Test valid token structure
        $validPayload = [
            'iss' => $this->testProviderUrl,
            'aud' => $this->testClientId,
            'sub' => 'user-123',
            'exp' => time() + 3600,
            'iat' => time(),
            'nonce' => 'test-nonce'
        ];
        
        $this->assertTrue($tokenValidator->validateTokenPayload($validPayload, 'test-nonce'));
        
        // Test expired token
        $expiredPayload = $validPayload;
        $expiredPayload['exp'] = time() - 3600;
        $this->assertFalse($tokenValidator->validateTokenPayload($expiredPayload, 'test-nonce'));
        
        // Test wrong audience
        $wrongAudPayload = $validPayload;
        $wrongAudPayload['aud'] = 'wrong-client';
        $this->assertFalse($tokenValidator->validateTokenPayload($wrongAudPayload, 'test-nonce'));
        
        // Test wrong issuer
        $wrongIssPayload = $validPayload;
        $wrongIssPayload['iss'] = 'https://wrong-provider.com';
        $this->assertFalse($tokenValidator->validateTokenPayload($wrongIssPayload, 'test-nonce'));
        
        // Test wrong nonce
        $this->assertFalse($tokenValidator->validateTokenPayload($validPayload, 'wrong-nonce'));
    }
    
    /**
     * Test user attribute mapping
     */
    public function testUserAttributeMapping()
    {
        $userProvisioning = new UserProvisioning($this->mockConfig);
        
        $oidcUserData = [
            'preferred_username' => 'john.doe',
            'email' => 'john.doe@example.com',
            'name' => 'John Doe',
            'given_name' => 'John',
            'family_name' => 'Doe',
            'groups' => ['users', 'employees'],
            'sub' => 'user-12345'
        ];
        
        $mappedUser = $userProvisioning->mapUserAttributes($oidcUserData);
        
        $this->assertEquals('john.doe', $mappedUser['username']);
        $this->assertEquals('john.doe@example.com', $mappedUser['email']);
        $this->assertEquals('John Doe', $mappedUser['name']);
        $this->assertEquals(['users', 'employees'], $mappedUser['groups']);
        $this->assertEquals('user-12345', $mappedUser['oidc_subject']);
    }
    
    /**
     * Test role mapping from groups
     */
    public function testRoleMappingFromGroups()
    {
        $userProvisioning = new UserProvisioning($this->mockConfig);
        
        // Test admin role mapping
        $adminGroups = ['ProjectSend Administrators', 'users'];
        $adminRole = $userProvisioning->mapGroupsToRole($adminGroups);
        $this->assertEquals('a', $adminRole); // admin
        
        // Test user role mapping
        $userGroups = ['ProjectSend Users', 'employees'];
        $userRole = $userProvisioning->mapGroupsToRole($userGroups);
        $this->assertEquals('u', $userRole); // user
        
        // Test client role mapping
        $clientGroups = ['ProjectSend Clients'];
        $clientRole = $userProvisioning->mapGroupsToRole($clientGroups);
        $this->assertEquals('c', $clientRole); // client
        
        // Test default role for unknown groups
        $unknownGroups = ['some-other-group'];
        $defaultRole = $userProvisioning->mapGroupsToRole($unknownGroups);
        $this->assertEquals('c', $defaultRole); // default to client
    }
    
    /**
     * Test user provisioning
     */
    public function testUserProvisioning()
    {
        $userProvisioning = new UserProvisioning($this->mockConfig);
        
        $oidcUserData = [
            'preferred_username' => 'jane.smith',
            'email' => 'jane.smith@example.com',
            'name' => 'Jane Smith',
            'groups' => ['ProjectSend Users'],
            'sub' => 'user-67890'
        ];
        
        // Test new user creation
        $result = $userProvisioning->provisionUser($oidcUserData);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('jane.smith', $result['user']['username']);
        $this->assertEquals('jane.smith@example.com', $result['user']['email']);
        $this->assertEquals('u', $result['user']['role']); // user role
        $this->assertEquals('user-67890', $result['user']['oidc_subject']);
        
        // Test existing user update
        $updatedData = $oidcUserData;
        $updatedData['name'] = 'Jane Smith Updated';
        $updatedData['groups'] = ['ProjectSend Administrators'];
        
        $updateResult = $userProvisioning->provisionUser($updatedData);
        
        $this->assertTrue($updateResult['success']);
        $this->assertEquals('Jane Smith Updated', $updateResult['user']['name']);
        $this->assertEquals('a', $updateResult['user']['role']); // admin role
    }
    
    /**
     * Test session management
     */
    public function testSessionManagement()
    {
        $sessionData = [
            'user_id' => 123,
            'username' => 'test.user',
            'email' => 'test.user@example.com',
            'role' => 'u',
            'oidc_subject' => 'user-123',
            'access_token' => 'mock-access-token',
            'refresh_token' => 'mock-refresh-token',
            'token_expires_at' => time() + 3600
        ];
        
        // Test session creation
        $this->oidcAuth->createSession($sessionData);
        
        $this->assertEquals(123, $_SESSION['user_id']);
        $this->assertEquals('test.user', $_SESSION['username']);
        $this->assertEquals('u', $_SESSION['role']);
        
        // Test session validation
        $this->assertTrue($this->oidcAuth->isValidSession());
        
        // Test token expiration check
        $_SESSION['token_expires_at'] = time() - 3600; // expired
        $this->assertFalse($this->oidcAuth->isTokenValid());
        
        // Test session cleanup
        $this->oidcAuth->clearSession();
        $this->assertFalse(isset($_SESSION['user_id']));
    }
    
    /**
     * Test error handling
     */
    public function testErrorHandling()
    {
        // Test invalid authorization code
        $result = $this->oidcAuth->handleAuthorizationResponse([
            'error' => 'access_denied',
            'error_description' => 'The user denied the request'
        ]);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('access_denied', $result['error']);
        
        // Test missing authorization code
        $result = $this->oidcAuth->handleAuthorizationResponse([]);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('code', $result['error']);
        
        // Test state mismatch
        $result = $this->oidcAuth->handleAuthorizationResponse([
            'code' => 'test-code',
            'state' => 'wrong-state'
        ], 'correct-state');
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('state', $result['error']);
    }
    
    /**
     * Test configuration security
     */
    public function testConfigurationSecurity()
    {
        // Test that sensitive data is not exposed
        $config = $this->oidcAuth->getPublicConfiguration();
        
        $this->assertArrayNotHasKey('oidc_client_secret', $config);
        $this->assertArrayHasKey('oidc_provider_url', $config);
        $this->assertArrayHasKey('oidc_client_id', $config);
        
        // Test client secret encryption in storage
        $encryptedSecret = $this->oidcAuth->encryptClientSecret($this->testClientSecret);
        $this->assertNotEquals($this->testClientSecret, $encryptedSecret);
        
        $decryptedSecret = $this->oidcAuth->decryptClientSecret($encryptedSecret);
        $this->assertEquals($this->testClientSecret, $decryptedSecret);
    }
    
    /**
     * Test logout functionality
     */
    public function testLogoutFunctionality()
    {
        // Set up session
        $sessionData = [
            'user_id' => 123,
            'username' => 'test.user',
            'access_token' => 'mock-access-token',
            'id_token' => 'mock-id-token'
        ];
        $this->oidcAuth->createSession($sessionData);
        
        // Test logout URL generation
        $logoutUrl = $this->oidcAuth->getLogoutUrl('https://example.com/goodbye');
        
        $this->assertStringContainsString($this->testProviderUrl, $logoutUrl);
        $this->assertStringContainsString('post_logout_redirect_uri', $logoutUrl);
        $this->assertStringContainsString('id_token_hint', $logoutUrl);
        
        // Test local session cleanup
        $this->oidcAuth->performLogout();
        $this->assertFalse(isset($_SESSION['user_id']));
    }
    
    /**
     * Helper function to base64url encode
     */
    private function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Test cleanup
     */
    protected function tearDown(): void
    {
        // Clean up any test sessions
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        parent::tearDown();
    }
}

/**
 * Helper function for base64url encoding (global scope)
 */
if (!function_exists('base64url_encode')) {
    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}