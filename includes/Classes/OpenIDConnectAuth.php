<?php
namespace ProjectSend\Classes;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use GuzzleHttp\Client;
use ProjectSend\Classes\Database;

class OpenIDConnectAuth {
    private $provider;
    private $clientId;
    private $clientSecret;
    private $discoveryUrl;
    private $providerConfig;
    private $jwks;
    private $keycloakConfig;

    public function __construct($provider, $clientId, $clientSecret, $discoveryUrl, $keycloakConfig = null) {
        $this->provider = $provider;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->discoveryUrl = $discoveryUrl;
        $this->keycloakConfig = $keycloakConfig ?? require(__DIR__ . '/../../config/keycloak/config.php');
        $this->loadProviderConfiguration();
        $this->fetchJWKS();
    }

    private function handleKeycloakSpecificFeatures() {
        // Keycloak-specific additional security and configuration handling
        if ($this->provider === 'keycloak') {
            $this->enablePKCE();
            $this->configureTokenRevocation();
            $this->setupGroupAndRoleSync();
        }
    }

    private function enablePKCE() {
        // Ensure PKCE is required for public clients
        if ($this->keycloakConfig['pkce_required']) {
            // Implement PKCE code challenge generation
        }
    }

    private function configureTokenRevocation() {
        // Configure token introspection and revocation
        if ($this->keycloakConfig['token_introspection']) {
            // Implement token introspection logic
        }
    }

    private function setupGroupAndRoleSync() {
        // Configure group and role synchronization
        if ($this->keycloakConfig['group_sync_enabled']) {
            // Implement group and role mapping logic
        }
    }

    public function getOfflineToken($refreshToken) {
        // Support for long-running offline tokens in Keycloak
        if ($this->keycloakConfig['offline_token_support']) {
            // Request offline token from Keycloak
            $tokenEndpoint = $this->providerConfig['token_endpoint'];
            // Implement offline token request logic
        }
        throw new \Exception('Offline token support not configured');
    }

    public function backchannelLogout($sessionId) {
        // Implement backend channel logout for Keycloak
        if ($this->provider === 'keycloak') {
            // Call Keycloak admin API for session termination
        }
    }

    private function loadProviderConfiguration() {
        $client = new Client();
        $response = $client->get($this->discoveryUrl);
        $this->providerConfig = json_decode($response->getBody(), true);
    }

    private function fetchJWKS() {
        $client = new Client();
        $response = $client->get($this->providerConfig['jwks_uri']);
        $this->jwks = json_decode($response->getBody(), true)['keys'];
    }

    public function validateToken($token) {
        $decoded = JWT::decode($token, JWK::parseKeySet($this->jwks));
        
        // Validate claims
        $this->validateClaims($decoded);
        
        return $decoded;
    }

    private function validateClaims($claims) {
        // OIDC standard claim validation
        $requiredClaims = ['iss', 'sub', 'aud', 'exp', 'iat'];
        foreach ($requiredClaims as $claim) {
            if (!isset($claims->{$claim})) {
                throw new \Exception("Missing required claim: {$claim}");
            }
        }

        // Audience validation
        if ($claims->aud !== $this->clientId) {
            throw new \Exception('Invalid token audience');
        }

        // Expiration validation
        if ($claims->exp < time()) {
            throw new \Exception('Token has expired');
        }
    }

    public function exchangeCodeForTokens($code, $redirectUri) {
        $client = new Client();
        $response = $client->post($this->providerConfig['token_endpoint'], [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $redirectUri
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    public function synchronizeUser($tokenData) {
        $db = Database::getInstance();
        $userMapper = new UserMapper($db);

        // Extract user claims
        $claims = $this->extractUserClaims($tokenData);

        // Check if user exists or create new
        $user = $userMapper->findByExternalId($this->provider, $claims['sub']);
        if (!$user) {
            $user = $userMapper->createFromExternalProvider($claims);
        }

        // Log synchronization
        $this->logSynchronization($user->getId(), $tokenData);

        return $user;
    }

    private function extractUserClaims($tokenData) {
        // Map OIDC standard claims to ProjectSend user model
        return [
            'sub' => $tokenData['sub'],
            'email' => $tokenData['email'] ?? null,
            'name' => $tokenData['name'] ?? null,
            'preferred_username' => $tokenData['preferred_username'] ?? null
        ];
    }

    private function logSynchronization($userId, $tokenData) {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO tbl_sync_log 
            (user_id, provider, sync_status, sync_details) 
            VALUES (:user_id, :provider, :status, :details)");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':provider' => $this->provider,
            ':status' => 'success',
            ':details' => json_encode($tokenData)
        ]);
    }
}