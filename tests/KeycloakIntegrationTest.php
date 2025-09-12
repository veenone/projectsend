<?php
namespace ProjectSend\Tests;

use PHPUnit\Framework\TestCase;
use ProjectSend\Classes\OpenIDConnectAuth;
use ProjectSend\Authentication\Providers\KeycloakProvider;

class KeycloakIntegrationTest extends TestCase {
    private $keycloakAuth;
    private $keycloakConfig;

    protected function setUp(): void {
        $this->keycloakConfig = require(__DIR__ . '/../config/keycloak/config.php');
        $this->keycloakAuth = new KeycloakProvider($this->keycloakConfig);
    }

    public function testTokenValidation() {
        // Mock token for testing
        $mockToken = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...';
        
        try {
            $validatedToken = $this->keycloakAuth->validateToken($mockToken);
            $this->assertNotNull($validatedToken, 'Token validation failed');
        } catch (\Exception $e) {
            $this->fail('Token validation threw an exception: ' . $e->getMessage());
        }
    }

    public function testUserSynchronization() {
        // Mock token data for synchronization test
        $mockTokenData = [
            'sub' => 'test-user-123',
            'email' => 'test@example.com',
            'preferred_username' => 'testuser',
            'realm_access' => [
                'roles' => ['user', 'projectsend-user']
            ]
        ];

        try {
            $user = $this->keycloakAuth->synchronizeUser($mockTokenData);
            $this->assertNotNull($user, 'User synchronization failed');
            $this->assertEquals('test@example.com', $user->getEmail());
        } catch (\Exception $e) {
            $this->fail('User synchronization threw an exception: ' . $e->getMessage());
        }
    }

    public function testPKCESupport() {
        $this->assertTrue(
            $this->keycloakConfig['pkce_required'], 
            'PKCE must be enabled for enhanced security'
        );
    }

    public function testAdminApiSync() {
        try {
            $this->keycloakAuth->adminApiSync();
            $this->assertTrue(true, 'Admin API sync completed');
        } catch (\Exception $e) {
            $this->fail('Admin API sync failed: ' . $e->getMessage());
        }
    }
}