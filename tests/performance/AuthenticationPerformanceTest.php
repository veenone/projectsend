<?php
/**
 * Performance Test Suite for OIDC Authentication
 * 
 * This test suite evaluates the performance characteristics of the OIDC
 * authentication implementation under various load conditions.
 * 
 * @package ProjectSend
 * @subpackage Tests\Performance
 */

require_once dirname(__FILE__) . '/../../vendor/autoload.php';
require_once dirname(__FILE__) . '/../../includes/classes/class.oidc.php';

use PHPUnit\Framework\TestCase;
use ProjectSend\Classes\OIDC\OIDCAuthentication;
use ProjectSend\Classes\OIDC\PerformanceProfiler;

class AuthenticationPerformanceTest extends TestCase
{
    private $oidcAuth;
    private $profiler;
    private $mockConfig;
    private $performanceBaseline;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockConfig = [
            'oidc_enabled' => true,
            'oidc_provider_url' => 'https://test-keycloak.example.com/realms/test',
            'oidc_client_id' => 'performance-test-client',
            'oidc_client_secret' => 'test-secret-for-performance',
            'oidc_redirect_uri' => 'https://test-projectsend.example.com/oidc-callback.php',
            'oidc_performance' => [
                'jwks_cache_enabled' => true,
                'jwks_cache_ttl' => 3600,
                'user_info_cache_ttl' => 300,
                'connection_timeout' => 10,
                'read_timeout' => 30
            ]
        ];
        
        $this->oidcAuth = new OIDCAuthentication($this->mockConfig);
        $this->profiler = new PerformanceProfiler();
        
        // Performance baselines (in milliseconds)
        $this->performanceBaseline = [
            'token_validation' => 500,      // 500ms
            'user_provisioning' => 1000,   // 1 second
            'authorization_url_gen' => 100, // 100ms
            'jwks_fetch' => 2000,          // 2 seconds
            'session_creation' => 200,      // 200ms
            'config_validation' => 50       // 50ms
        ];
    }
    
    /**
     * Test authorization URL generation performance
     */
    public function testAuthorizationURLGenerationPerformance()
    {
        $iterations = 1000;
        $times = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            
            $state = $this->oidcAuth->generateState();
            $nonce = $this->oidcAuth->generateNonce();
            $authUrl = $this->oidcAuth->getAuthorizationUrl($state, $nonce);
            
            $times[] = (microtime(true) - $start) * 1000; // Convert to milliseconds
        }
        
        $avgTime = array_sum($times) / count($times);
        $maxTime = max($times);
        $minTime = min($times);
        $p95Time = $this->calculatePercentile($times, 95);
        
        // Performance assertions
        $this->assertLessThan(
            $this->performanceBaseline['authorization_url_gen'], 
            $avgTime,
            "Authorization URL generation average time ({$avgTime}ms) exceeds baseline"
        );
        
        $this->assertLessThan(
            $this->performanceBaseline['authorization_url_gen'] * 2,
            $p95Time,
            "Authorization URL generation P95 time ({$p95Time}ms) exceeds acceptable limits"
        );
        
        // Log performance metrics
        $this->profiler->logMetric('authorization_url_generation', [
            'avg_time_ms' => $avgTime,
            'min_time_ms' => $minTime,
            'max_time_ms' => $maxTime,
            'p95_time_ms' => $p95Time,
            'iterations' => $iterations
        ]);
        
        echo "\nAuthorization URL Generation Performance:\n";
        echo "  Average: " . number_format($avgTime, 2) . "ms\n";
        echo "  P95: " . number_format($p95Time, 2) . "ms\n";
        echo "  Max: " . number_format($maxTime, 2) . "ms\n";
    }
    
    /**
     * Test JWT token validation performance
     */
    public function testJWTValidationPerformance()
    {
        // Create mock JWT tokens for testing
        $mockTokens = $this->generateMockJWTTokens(100);
        $times = [];
        
        foreach ($mockTokens as $token) {
            $start = microtime(true);
            
            try {
                $this->oidcAuth->validateJWT($token, 'test-nonce');
            } catch (Exception $e) {
                // Expected for mock tokens, we're measuring parsing performance
            }
            
            $times[] = (microtime(true) - $start) * 1000;
        }
        
        $avgTime = array_sum($times) / count($times);
        $p95Time = $this->calculatePercentile($times, 95);
        $p99Time = $this->calculatePercentile($times, 99);
        
        // Performance assertions
        $this->assertLessThan(
            $this->performanceBaseline['token_validation'],
            $avgTime,
            "JWT validation average time ({$avgTime}ms) exceeds baseline"
        );
        
        $this->assertLessThan(
            $this->performanceBaseline['token_validation'] * 1.5,
            $p95Time,
            "JWT validation P95 time ({$p95Time}ms) exceeds acceptable limits"
        );
        
        $this->profiler->logMetric('jwt_validation', [
            'avg_time_ms' => $avgTime,
            'p95_time_ms' => $p95Time,
            'p99_time_ms' => $p99Time,
            'token_count' => count($mockTokens)
        ]);
        
        echo "\nJWT Validation Performance:\n";
        echo "  Average: " . number_format($avgTime, 2) . "ms\n";
        echo "  P95: " . number_format($p95Time, 2) . "ms\n";
        echo "  P99: " . number_format($p99Time, 2) . "ms\n";
    }
    
    /**
     * Test user provisioning performance
     */
    public function testUserProvisioningPerformance()
    {
        $mockUsers = $this->generateMockUserData(50);
        $times = [];
        
        foreach ($mockUsers as $userData) {
            $start = microtime(true);
            
            try {
                $result = $this->oidcAuth->provisionUser($userData);
            } catch (Exception $e) {
                // Handle database connection issues in testing
            }
            
            $times[] = (microtime(true) - $start) * 1000;
        }
        
        $avgTime = array_sum($times) / count($times);
        $p95Time = $this->calculatePercentile($times, 95);
        $maxTime = max($times);
        
        // Performance assertions
        $this->assertLessThan(
            $this->performanceBaseline['user_provisioning'],
            $avgTime,
            "User provisioning average time ({$avgTime}ms) exceeds baseline"
        );
        
        $this->profiler->logMetric('user_provisioning', [
            'avg_time_ms' => $avgTime,
            'max_time_ms' => $maxTime,
            'p95_time_ms' => $p95Time,
            'user_count' => count($mockUsers)
        ]);
        
        echo "\nUser Provisioning Performance:\n";
        echo "  Average: " . number_format($avgTime, 2) . "ms\n";
        echo "  P95: " . number_format($p95Time, 2) . "ms\n";
        echo "  Max: " . number_format($maxTime, 2) . "ms\n";
    }
    
    /**
     * Test session creation and management performance
     */
    public function testSessionManagementPerformance()
    {
        $iterations = 500;
        $sessionData = [
            'user_id' => 123,
            'username' => 'performance.test',
            'email' => 'test@example.com',
            'role' => 'u',
            'access_token' => str_repeat('a', 1024), // 1KB token
            'refresh_token' => str_repeat('r', 512),  // 512B token
            'id_token' => str_repeat('i', 2048)       // 2KB token
        ];
        
        $createTimes = [];
        $validateTimes = [];
        $cleanupTimes = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Test session creation
            $start = microtime(true);
            $this->oidcAuth->createSession($sessionData);
            $createTimes[] = (microtime(true) - $start) * 1000;
            
            // Test session validation
            $start = microtime(true);
            $isValid = $this->oidcAuth->isValidSession();
            $validateTimes[] = (microtime(true) - $start) * 1000;
            
            // Test session cleanup
            $start = microtime(true);
            $this->oidcAuth->clearSession();
            $cleanupTimes[] = (microtime(true) - $start) * 1000;
        }
        
        $avgCreateTime = array_sum($createTimes) / count($createTimes);
        $avgValidateTime = array_sum($validateTimes) / count($validateTimes);
        $avgCleanupTime = array_sum($cleanupTimes) / count($cleanupTimes);
        
        // Performance assertions
        $this->assertLessThan(
            $this->performanceBaseline['session_creation'],
            $avgCreateTime,
            "Session creation average time ({$avgCreateTime}ms) exceeds baseline"
        );
        
        $this->profiler->logMetric('session_management', [
            'create_avg_ms' => $avgCreateTime,
            'validate_avg_ms' => $avgValidateTime,
            'cleanup_avg_ms' => $avgCleanupTime,
            'iterations' => $iterations
        ]);
        
        echo "\nSession Management Performance:\n";
        echo "  Creation: " . number_format($avgCreateTime, 2) . "ms\n";
        echo "  Validation: " . number_format($avgValidateTime, 2) . "ms\n";
        echo "  Cleanup: " . number_format($avgCleanupTime, 2) . "ms\n";
    }
    
    /**
     * Test configuration validation performance
     */
    public function testConfigurationValidationPerformance()
    {
        $iterations = 2000;
        $times = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $isValid = $this->oidcAuth->validateConfiguration();
            $times[] = (microtime(true) - $start) * 1000;
        }
        
        $avgTime = array_sum($times) / count($times);
        $maxTime = max($times);
        $p99Time = $this->calculatePercentile($times, 99);
        
        // Performance assertions
        $this->assertLessThan(
            $this->performanceBaseline['config_validation'],
            $avgTime,
            "Configuration validation average time ({$avgTime}ms) exceeds baseline"
        );
        
        $this->profiler->logMetric('config_validation', [
            'avg_time_ms' => $avgTime,
            'max_time_ms' => $maxTime,
            'p99_time_ms' => $p99Time,
            'iterations' => $iterations
        ]);
        
        echo "\nConfiguration Validation Performance:\n";
        echo "  Average: " . number_format($avgTime, 3) . "ms\n";
        echo "  Max: " . number_format($maxTime, 3) . "ms\n";
        echo "  P99: " . number_format($p99Time, 3) . "ms\n";
    }
    
    /**
     * Test concurrent authentication performance
     */
    public function testConcurrentAuthenticationPerformance()
    {
        $concurrentUsers = 20;
        $requestsPerUser = 10;
        $results = [];
        
        // Simulate concurrent authentication requests
        for ($user = 0; $user < $concurrentUsers; $user++) {
            $userTimes = [];
            
            for ($request = 0; $request < $requestsPerUser; $request++) {
                $start = microtime(true);
                
                // Simulate authentication flow steps
                $state = $this->oidcAuth->generateState();
                $nonce = $this->oidcAuth->generateNonce();
                $authUrl = $this->oidcAuth->getAuthorizationUrl($state, $nonce);
                
                // Simulate token validation (mocked)
                try {
                    $mockToken = $this->generateMockJWTToken();
                    $this->oidcAuth->parseJWT($mockToken);
                } catch (Exception $e) {
                    // Expected for mock tokens
                }
                
                $userTimes[] = (microtime(true) - $start) * 1000;
            }
            
            $results[$user] = $userTimes;
        }
        
        // Calculate overall statistics
        $allTimes = array_merge(...array_values($results));
        $avgTime = array_sum($allTimes) / count($allTimes);
        $p95Time = $this->calculatePercentile($allTimes, 95);
        $throughput = count($allTimes) / (max($allTimes) - min($allTimes)) * 1000;
        
        $this->profiler->logMetric('concurrent_authentication', [
            'concurrent_users' => $concurrentUsers,
            'requests_per_user' => $requestsPerUser,
            'avg_time_ms' => $avgTime,
            'p95_time_ms' => $p95Time,
            'throughput_rps' => $throughput
        ]);
        
        echo "\nConcurrent Authentication Performance:\n";
        echo "  Concurrent Users: {$concurrentUsers}\n";
        echo "  Requests per User: {$requestsPerUser}\n";
        echo "  Average Time: " . number_format($avgTime, 2) . "ms\n";
        echo "  P95 Time: " . number_format($p95Time, 2) . "ms\n";
        echo "  Estimated Throughput: " . number_format($throughput, 1) . " RPS\n";
    }
    
    /**
     * Test memory usage during authentication
     */
    public function testMemoryUsagePerformance()
    {
        $initialMemory = memory_get_usage(true);
        $peakMemory = $initialMemory;
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Perform authentication operations
            $state = $this->oidcAuth->generateState();
            $nonce = $this->oidcAuth->generateNonce();
            $authUrl = $this->oidcAuth->getAuthorizationUrl($state, $nonce);
            
            // Create and destroy session
            $sessionData = [
                'user_id' => $i,
                'username' => "user{$i}",
                'access_token' => str_repeat('x', 1024)
            ];
            $this->oidcAuth->createSession($sessionData);
            $this->oidcAuth->clearSession();
            
            $currentMemory = memory_get_usage(true);
            $peakMemory = max($peakMemory, $currentMemory);
            
            // Force garbage collection every 10 iterations
            if ($i % 10 === 0) {
                gc_collect_cycles();
            }
        }
        
        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;
        $peakIncrease = $peakMemory - $initialMemory;
        
        // Memory usage assertions (should not increase significantly)
        $this->assertLessThan(
            1024 * 1024 * 10, // 10MB
            $memoryIncrease,
            "Memory usage increased by more than 10MB during authentication tests"
        );
        
        $this->profiler->logMetric('memory_usage', [
            'initial_memory_mb' => $initialMemory / 1024 / 1024,
            'final_memory_mb' => $finalMemory / 1024 / 1024,
            'memory_increase_mb' => $memoryIncrease / 1024 / 1024,
            'peak_increase_mb' => $peakIncrease / 1024 / 1024,
            'iterations' => $iterations
        ]);
        
        echo "\nMemory Usage Performance:\n";
        echo "  Initial Memory: " . number_format($initialMemory / 1024 / 1024, 2) . " MB\n";
        echo "  Final Memory: " . number_format($finalMemory / 1024 / 1024, 2) . " MB\n";
        echo "  Memory Increase: " . number_format($memoryIncrease / 1024 / 1024, 2) . " MB\n";
        echo "  Peak Increase: " . number_format($peakIncrease / 1024 / 1024, 2) . " MB\n";
    }
    
    /**
     * Test cache performance for JWKS and user data
     */
    public function testCachePerformance()
    {
        $iterations = 200;
        $cacheHits = 0;
        $cacheMisses = 0;
        $hitTimes = [];
        $missTimes = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $cacheKey = "test-key-" . ($i % 20); // 20 unique keys, forcing cache hits
            
            $start = microtime(true);
            $cached = $this->oidcAuth->getCachedData($cacheKey);
            
            if ($cached !== null) {
                $cacheHits++;
                $hitTimes[] = (microtime(true) - $start) * 1000;
            } else {
                $cacheMisses++;
                $missTimes[] = (microtime(true) - $start) * 1000;
                
                // Simulate data retrieval and caching
                $data = ['mock' => 'data', 'timestamp' => time()];
                $this->oidcAuth->setCachedData($cacheKey, $data, 300);
            }
        }
        
        $avgHitTime = count($hitTimes) > 0 ? array_sum($hitTimes) / count($hitTimes) : 0;
        $avgMissTime = count($missTimes) > 0 ? array_sum($missTimes) / count($missTimes) : 0;
        $hitRate = $cacheHits / ($cacheHits + $cacheMisses) * 100;
        
        // Cache performance assertions
        $this->assertGreaterThan(50, $hitRate, "Cache hit rate should be above 50%");
        $this->assertLessThan(10, $avgHitTime, "Cache hits should be very fast (<10ms)");
        
        $this->profiler->logMetric('cache_performance', [
            'cache_hits' => $cacheHits,
            'cache_misses' => $cacheMisses,
            'hit_rate_percent' => $hitRate,
            'avg_hit_time_ms' => $avgHitTime,
            'avg_miss_time_ms' => $avgMissTime
        ]);
        
        echo "\nCache Performance:\n";
        echo "  Cache Hits: {$cacheHits}\n";
        echo "  Cache Misses: {$cacheMisses}\n";
        echo "  Hit Rate: " . number_format($hitRate, 1) . "%\n";
        echo "  Avg Hit Time: " . number_format($avgHitTime, 3) . "ms\n";
        echo "  Avg Miss Time: " . number_format($avgMissTime, 3) . "ms\n";
    }
    
    /**
     * Generate performance report
     */
    public function testGeneratePerformanceReport()
    {
        $report = $this->profiler->generateReport();
        
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('metrics', $report);
        $this->assertArrayHasKey('recommendations', $report);
        
        // Save report to file
        $reportPath = dirname(__FILE__) . '/../../logs/oidc_performance_report.json';
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        
        echo "\n=== OIDC Authentication Performance Report ===\n";
        echo "Report generated at: " . date('Y-m-d H:i:s') . "\n";
        echo "Total metrics collected: " . count($report['metrics']) . "\n";
        echo "Report saved to: {$reportPath}\n";
        
        // Display recommendations
        if (!empty($report['recommendations'])) {
            echo "\nPerformance Recommendations:\n";
            foreach ($report['recommendations'] as $recommendation) {
                echo "  • " . $recommendation . "\n";
            }
        }
    }
    
    /**
     * Helper method to generate mock JWT tokens
     */
    private function generateMockJWTTokens($count)
    {
        $tokens = [];
        
        for ($i = 0; $i < $count; $i++) {
            $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'RS256']));
            $payload = base64url_encode(json_encode([
                'iss' => $this->mockConfig['oidc_provider_url'],
                'aud' => $this->mockConfig['oidc_client_id'],
                'sub' => "user-{$i}",
                'exp' => time() + 3600,
                'iat' => time(),
                'nonce' => "nonce-{$i}"
            ]));
            $signature = 'mock-signature-' . $i;
            
            $tokens[] = $header . '.' . $payload . '.' . $signature;
        }
        
        return $tokens;
    }
    
    /**
     * Helper method to generate a single mock JWT token
     */
    private function generateMockJWTToken()
    {
        return $this->generateMockJWTTokens(1)[0];
    }
    
    /**
     * Helper method to generate mock user data
     */
    private function generateMockUserData($count)
    {
        $users = [];
        
        for ($i = 0; $i < $count; $i++) {
            $users[] = [
                'preferred_username' => "user{$i}",
                'email' => "user{$i}@example.com",
                'name' => "Test User {$i}",
                'given_name' => 'Test',
                'family_name' => "User {$i}",
                'groups' => ['ProjectSend Users'],
                'sub' => "user-subject-{$i}"
            ];
        }
        
        return $users;
    }
    
    /**
     * Helper method to calculate percentiles
     */
    private function calculatePercentile($array, $percentile)
    {
        sort($array);
        $index = ceil(($percentile / 100) * count($array)) - 1;
        return $array[$index];
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
        
        // Clear any cached data
        $this->oidcAuth->clearAllCachedData();
        
        parent::tearDown();
    }
}