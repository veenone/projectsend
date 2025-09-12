<?php
namespace ProjectSend\Authentication\Providers;

use ProjectSend\Classes\OpenIDConnectAuth;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use GuzzleHttp\Client;

class KeycloakProvider extends OpenIDConnectAuth {
    private $keycloakBaseUrl;
    private $realm;
    private $client;

    public function __construct($config) {
        parent::__construct($config);
        $this->keycloakBaseUrl = $config['keycloak_base_url'];
        $this->realm = $config['realm'];
        $this->client = new Client();
    }

    public function fetchJWKS() {
        try {
            $jwksUrl = "{$this->keycloakBaseUrl}/realms/{$this->realm}/protocol/openid-connect/certs";
            $response = $this->client->get($jwksUrl);
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            // Log and handle JWKS fetch error
            throw new \RuntimeException("Failed to fetch JWKS: " . $e->getMessage());
        }
    }

    public function validateToken($token) {
        $jwks = $this->fetchJWKS();
        $keys = JWK::parseKeySet($jwks);

        try {
            $decoded = JWT::decode($token, $keys, ['RS256']);
            $this->validateTokenClaims($decoded);
            return $decoded;
        } catch (\Exception $e) {
            // Comprehensive token validation
            throw new \RuntimeException("Token validation failed: " . $e->getMessage());
        }
    }

    private function validateTokenClaims($claims) {
        // Keycloak-specific claim validations
        if (!isset($claims->iss) || strpos($claims->iss, $this->keycloakBaseUrl) !== 0) {
            throw new \RuntimeException("Invalid token issuer");
        }

        // Validate audience
        if (!in_array($this->clientId, $claims->aud)) {
            throw new \RuntimeException("Invalid token audience");
        }

        // Time-based validations
        $now = time();
        if (isset($claims->exp) && $claims->exp < $now) {
            throw new \RuntimeException("Token has expired");
        }

        if (isset($claims->nbf) && $claims->nbf > $now) {
            throw new \RuntimeException("Token not yet valid");
        }
    }

    public function synchronizeUser($token) {
        $userInfo = $this->extractUserInfo($token);
        // Implement user sync logic with ProjectSend user management
        return $this->createOrUpdateUser($userInfo);
    }

    private function extractUserInfo($token) {
        $decoded = $this->validateToken($token);
        return [
            'username' => $decoded->preferred_username ?? $decoded->sub,
            'email' => $decoded->email,
            'first_name' => $decoded->given_name,
            'last_name' => $decoded->family_name,
            'groups' => $decoded->groups ?? [],
            'roles' => $decoded->realm_access->roles ?? []
        ];
    }

    public function adminApiSync() {
        // Keycloak Admin API synchronization
        try {
            $accessToken = $this->getAdminAccessToken();
            $users = $this->fetchUsersFromKeycloak($accessToken);
            $this->bulkUserSync($users);
        } catch (\Exception $e) {
            // Log and handle sync errors
            throw new \RuntimeException("Admin API sync failed: " . $e->getMessage());
        }
    }

    private function getAdminAccessToken() {
        // Implement admin client credentials flow
        $tokenUrl = "{$this->keycloakBaseUrl}/realms/master/protocol/openid-connect/token";
        // Use admin client credentials for token
    }

    private function fetchUsersFromKeycloak($accessToken) {
        // Implement Keycloak Admin API user fetch
    }

    private function bulkUserSync($users) {
        // Implement bulk user synchronization
    }
}