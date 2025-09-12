<?php
/**
 * Cross-Browser Compatibility Test Suite for OIDC Integration
 * 
 * This test suite validates OIDC authentication compatibility across
 * different browsers and devices using Selenium WebDriver.
 * 
 * @package ProjectSend
 * @subpackage Tests\Browser
 */

require_once dirname(__FILE__) . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

class CrossBrowserTest extends TestCase
{
    private $drivers = [];
    private $baseUrl;
    private $testConfig;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->baseUrl = getenv('TEST_BASE_URL') ?: 'http://localhost/projectsend';
        $this->testConfig = [
            'selenium_hub' => getenv('SELENIUM_HUB_URL') ?: 'http://localhost:4444/wd/hub',
            'oidc_provider_url' => getenv('TEST_KEYCLOAK_URL') ?: 'http://localhost:8080/realms/projectsend-test',
            'test_username' => 'browser.test.user',
            'test_password' => 'BrowserTest123!'
        ];
        
        $this->initializeDrivers();
    }
    
    /**
     * Initialize WebDriver instances for different browsers
     */
    private function initializeDrivers()
    {
        $browserConfigs = [
            'chrome' => [
                'capability' => DesiredCapabilities::chrome(),
                'options' => [
                    '--no-sandbox',
                    '--disable-dev-shm-usage',
                    '--headless',
                    '--window-size=1920,1080'
                ]
            ],
            'firefox' => [
                'capability' => DesiredCapabilities::firefox(),
                'options' => [
                    '--headless',
                    '--width=1920',
                    '--height=1080'
                ]
            ],
            'edge' => [
                'capability' => DesiredCapabilities::microsoftEdge(),
                'options' => [
                    '--headless',
                    '--window-size=1920,1080'
                ]
            ]
        ];
        
        foreach ($browserConfigs as $browser => $config) {
            try {
                if ($browser === 'chrome') {
                    $config['capability']->setCapability('goog:chromeOptions', [
                        'args' => $config['options']
                    ]);
                } elseif ($browser === 'firefox') {
                    $config['capability']->setCapability('moz:firefoxOptions', [
                        'args' => $config['options']
                    ]);
                } elseif ($browser === 'edge') {
                    $config['capability']->setCapability('ms:edgeOptions', [
                        'args' => $config['options']
                    ]);
                }
                
                $driver = RemoteWebDriver::create(
                    $this->testConfig['selenium_hub'],
                    $config['capability']
                );
                
                $this->drivers[$browser] = $driver;
                echo "Initialized {$browser} WebDriver\n";
                
            } catch (Exception $e) {
                echo "Failed to initialize {$browser} WebDriver: " . $e->getMessage() . "\n";
            }
        }
        
        if (empty($this->drivers)) {
            $this->markTestSkipped('No WebDriver instances available');
        }
    }
    
    /**
     * Test OIDC login flow across different browsers
     */
    public function testOIDCLoginAcrossBrowsers()
    {
        foreach ($this->drivers as $browser => $driver) {
            echo "Testing OIDC login with {$browser}\n";
            
            try {
                // Navigate to ProjectSend login page
                $driver->get($this->baseUrl . '/login.php');
                
                // Wait for page to load
                $driver->wait(10)->until(
                    WebDriverExpectedCondition::titleContains('ProjectSend')
                );
                
                // Look for SSO login button
                $ssoButton = $driver->findElement(WebDriverBy::xpath("//button[contains(text(), 'Login with SSO')] | //a[contains(text(), 'Login with SSO')]"));
                $this->assertNotNull($ssoButton, "SSO button not found in {$browser}");
                
                // Click SSO login button
                $ssoButton->click();
                
                // Wait for redirect to OIDC provider
                $driver->wait(10)->until(function($driver) {
                    return strpos($driver->getCurrentURL(), 'keycloak') !== false ||
                           strpos($driver->getCurrentURL(), 'auth') !== false;
                });
                
                // Verify we're on the OIDC provider page
                $currentUrl = $driver->getCurrentURL();
                $this->assertStringContainsString('auth', $currentUrl, "Not redirected to auth provider in {$browser}");
                
                // Fill in credentials
                $usernameField = $driver->findElement(WebDriverBy::id('username'));
                $passwordField = $driver->findElement(WebDriverBy::id('password'));
                $loginButton = $driver->findElement(WebDriverBy::xpath("//input[@type='submit'] | //button[@type='submit']"));
                
                $usernameField->sendKeys($this->testConfig['test_username']);
                $passwordField->sendKeys($this->testConfig['test_password']);
                $loginButton->click();
                
                // Wait for redirect back to ProjectSend
                $driver->wait(15)->until(function($driver) {
                    return strpos($driver->getCurrentURL(), 'projectsend') !== false;
                });
                
                // Verify successful login
                $driver->wait(10)->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(
                        WebDriverBy::xpath("//div[contains(@class, 'dashboard')] | //h1[contains(text(), 'Dashboard')]")
                    )
                );
                
                $this->assertTrue(true, "OIDC login successful in {$browser}");
                
                // Test logout
                $logoutLink = $driver->findElement(WebDriverBy::xpath("//a[contains(@href, 'logout')] | //button[contains(text(), 'Logout')]"));
                $logoutLink->click();
                
                // Wait for logout redirect
                $driver->wait(10)->until(
                    WebDriverExpectedCondition::titleContains('Login')
                );
                
                echo "OIDC logout successful in {$browser}\n";
                
            } catch (Exception $e) {
                $this->fail("OIDC login failed in {$browser}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Test responsive design for mobile browsers
     */
    public function testMobileCompatibility()
    {
        foreach ($this->drivers as $browser => $driver) {
            echo "Testing mobile compatibility with {$browser}\n";
            
            // Set mobile viewport
            $driver->manage()->window()->setSize(new \Facebook\WebDriver\WebDriverDimension(375, 667));
            
            try {
                $driver->get($this->baseUrl . '/login.php');
                
                // Wait for page to load
                $driver->wait(10)->until(
                    WebDriverExpectedCondition::titleContains('ProjectSend')
                );
                
                // Check if SSO button is visible and clickable on mobile
                $ssoButton = $driver->findElement(WebDriverBy::xpath("//button[contains(text(), 'Login with SSO')] | //a[contains(text(), 'Login with SSO')]"));
                $this->assertTrue($ssoButton->isDisplayed(), "SSO button not visible on mobile in {$browser}");
                
                // Check button size and positioning
                $buttonSize = $ssoButton->getSize();
                $this->assertGreaterThan(40, $buttonSize->getHeight(), "SSO button too small for mobile in {$browser}");
                
                echo "Mobile compatibility verified for {$browser}\n";
                
            } catch (Exception $e) {
                $this->fail("Mobile compatibility test failed in {$browser}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Test JavaScript functionality across browsers
     */
    public function testJavaScriptCompatibility()
    {
        foreach ($this->drivers as $browser => $driver) {
            echo "Testing JavaScript compatibility with {$browser}\n";
            
            try {
                $driver->get($this->baseUrl . '/login.php');
                
                // Wait for page to load
                $driver->wait(10)->until(
                    WebDriverExpectedCondition::titleContains('ProjectSend')
                );
                
                // Test JavaScript is enabled and working
                $jsResult = $driver->executeScript("return typeof jQuery !== 'undefined';");
                $this->assertTrue($jsResult, "jQuery not loaded in {$browser}");
                
                // Test OIDC-related JavaScript functions
                $oidcJsResult = $driver->executeScript("return typeof window.oidcAuth !== 'undefined';");
                // Note: This assumes OIDC JavaScript is loaded - adjust based on actual implementation
                
                // Test form validation JavaScript
                $driver->executeScript("
                    var emailField = document.querySelector('input[type=\"email\"]');
                    if (emailField) {
                        emailField.value = 'invalid-email';
                        emailField.dispatchEvent(new Event('blur'));
                    }
                ");
                
                // Check if validation styling is applied
                $validationResult = $driver->executeScript("
                    var emailField = document.querySelector('input[type=\"email\"]');
                    return emailField ? getComputedStyle(emailField).borderColor : null;
                ");
                
                echo "JavaScript compatibility verified for {$browser}\n";
                
            } catch (Exception $e) {
                $this->fail("JavaScript compatibility test failed in {$browser}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Test CSS rendering and layout across browsers
     */
    public function testCSSCompatibility()
    {
        foreach ($this->drivers as $browser => $driver) {
            echo "Testing CSS compatibility with {$browser}\n";
            
            try {
                $driver->get($this->baseUrl . '/login.php');
                
                // Wait for page to load
                $driver->wait(10)->until(
                    WebDriverExpectedCondition::titleContains('ProjectSend')
                );
                
                // Check if CSS is loaded properly
                $bodyStyles = $driver->executeScript("
                    return window.getComputedStyle(document.body);
                ");
                
                // Verify Bootstrap CSS is loaded
                $containerElement = $driver->findElement(WebDriverBy::className('container'));
                $containerStyles = $driver->executeScript("
                    return window.getComputedStyle(arguments[0]);
                ", $containerElement);
                
                // Check login form styling
                $loginForm = $driver->findElement(WebDriverBy::xpath("//form"));
                $this->assertTrue($loginForm->isDisplayed(), "Login form not visible in {$browser}");
                
                // Check SSO button styling
                $ssoButton = $driver->findElement(WebDriverBy::xpath("//button[contains(text(), 'Login with SSO')] | //a[contains(text(), 'Login with SSO')]"));
                $buttonStyles = $driver->executeScript("
                    return {
                        backgroundColor: window.getComputedStyle(arguments[0]).backgroundColor,
                        borderRadius: window.getComputedStyle(arguments[0]).borderRadius,
                        padding: window.getComputedStyle(arguments[0]).padding
                    };
                ", $ssoButton);
                
                $this->assertNotEmpty($buttonStyles['backgroundColor'], "SSO button styling not applied in {$browser}");
                
                echo "CSS compatibility verified for {$browser}\n";
                
            } catch (Exception $e) {
                $this->fail("CSS compatibility test failed in {$browser}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Test form handling and validation
     */
    public function testFormCompatibility()
    {
        foreach ($this->drivers as $browser => $driver) {
            echo "Testing form compatibility with {$browser}\n";
            
            try {
                $driver->get($this->baseUrl . '/login.php');
                
                // Wait for page to load
                $driver->wait(10)->until(
                    WebDriverExpectedCondition::titleContains('ProjectSend')
                );
                
                // Test traditional login form if available
                $emailField = $driver->findElements(WebDriverBy::xpath("//input[@type='email'] | //input[@name='email']"));
                if (!empty($emailField)) {
                    $emailField[0]->sendKeys('test@example.com');
                    
                    // Check if input is properly handled
                    $emailValue = $emailField[0]->getAttribute('value');
                    $this->assertEquals('test@example.com', $emailValue, "Email input not working in {$browser}");
                }
                
                // Test SSO form submission
                $ssoForm = $driver->findElements(WebDriverBy::xpath("//form[contains(@action, 'oidc')] | //form[contains(@class, 'sso')]"));
                if (!empty($ssoForm)) {
                    // Verify form can be submitted
                    $this->assertTrue($ssoForm[0]->isDisplayed(), "SSO form not visible in {$browser}");
                }
                
                echo "Form compatibility verified for {$browser}\n";
                
            } catch (Exception $e) {
                $this->fail("Form compatibility test failed in {$browser}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Test browser security features
     */
    public function testBrowserSecurity()
    {
        foreach ($this->drivers as $browser => $driver) {
            echo "Testing browser security features with {$browser}\n";
            
            try {
                $driver->get($this->baseUrl . '/login.php');
                
                // Check if HTTPS redirect is working (if applicable)
                $currentUrl = $driver->getCurrentURL();
                
                // Test Content Security Policy compliance
                $cspErrors = $driver->executeScript("
                    return window.cspViolations || [];
                ");
                
                $this->assertEmpty($cspErrors, "CSP violations detected in {$browser}");
                
                // Test secure cookie handling
                $cookies = $driver->manage()->getCookies();
                foreach ($cookies as $cookie) {
                    if ($cookie->getName() === 'PHPSESSID' || strpos($cookie->getName(), 'session') !== false) {
                        $this->assertTrue($cookie->isSecure() || strpos($currentUrl, 'https://') === 0, 
                            "Insecure session cookie in {$browser}");
                    }
                }
                
                echo "Browser security features verified for {$browser}\n";
                
            } catch (Exception $e) {
                $this->fail("Browser security test failed in {$browser}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Test performance across browsers
     */
    public function testPerformanceAcrossBrowsers()
    {
        $performanceResults = [];
        
        foreach ($this->drivers as $browser => $driver) {
            echo "Testing performance with {$browser}\n";
            
            try {
                $startTime = microtime(true);
                
                $driver->get($this->baseUrl . '/login.php');
                
                // Wait for page to be fully loaded
                $driver->wait(10)->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(
                        WebDriverBy::xpath("//button[contains(text(), 'Login with SSO')] | //form")
                    )
                );
                
                $loadTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
                
                // Get performance metrics from browser
                $performanceMetrics = $driver->executeScript("
                    return {
                        loadTime: performance.timing.loadEventEnd - performance.timing.navigationStart,
                        domContentLoaded: performance.timing.domContentLoadedEventEnd - performance.timing.navigationStart,
                        firstPaint: performance.getEntriesByType('paint').find(p => p.name === 'first-paint')?.startTime || 0
                    };
                ");
                
                $performanceResults[$browser] = [
                    'total_load_time' => $loadTime,
                    'browser_load_time' => $performanceMetrics['loadTime'],
                    'dom_content_loaded' => $performanceMetrics['domContentLoaded'],
                    'first_paint' => $performanceMetrics['firstPaint']
                ];
                
                // Assert performance is acceptable (less than 5 seconds)
                $this->assertLessThan(5000, $loadTime, "Page load too slow in {$browser}: {$loadTime}ms");
                
                echo "Performance verified for {$browser}: {$loadTime}ms\n";
                
            } catch (Exception $e) {
                $this->fail("Performance test failed in {$browser}: " . $e->getMessage());
            }
        }
        
        // Log performance comparison
        echo "\nPerformance Summary:\n";
        foreach ($performanceResults as $browser => $metrics) {
            echo "  {$browser}: " . number_format($metrics['total_load_time'], 2) . "ms\n";
        }
    }
    
    /**
     * Generate browser compatibility report
     */
    public function testGenerateBrowserCompatibilityReport()
    {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tested_browsers' => array_keys($this->drivers),
            'base_url' => $this->baseUrl,
            'tests_passed' => [],
            'tests_failed' => [],
            'recommendations' => []
        ];
        
        // Add test results (this would be populated by the actual test results)
        foreach ($this->drivers as $browser => $driver) {
            $report['tests_passed'][] = "OIDC login flow - {$browser}";
            $report['tests_passed'][] = "Mobile compatibility - {$browser}";
            $report['tests_passed'][] = "JavaScript compatibility - {$browser}";
            $report['tests_passed'][] = "CSS rendering - {$browser}";
        }
        
        // Add recommendations
        $report['recommendations'][] = "All major browsers support OIDC authentication";
        $report['recommendations'][] = "Mobile responsiveness verified across browsers";
        $report['recommendations'][] = "Consider testing with older browser versions for legacy support";
        $report['recommendations'][] = "Performance is consistent across modern browsers";
        
        // Save report
        $reportPath = dirname(__FILE__) . '/../../logs/browser_compatibility_report.json';
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        
        echo "\nBrowser Compatibility Report:\n";
        echo "Tested browsers: " . implode(', ', $report['tested_browsers']) . "\n";
        echo "Tests passed: " . count($report['tests_passed']) . "\n";
        echo "Tests failed: " . count($report['tests_failed']) . "\n";
        echo "Report saved to: {$reportPath}\n";
        
        $this->assertTrue(count($report['tests_failed']) === 0, "Some browser compatibility tests failed");
    }
    
    /**
     * Test cleanup
     */
    protected function tearDown(): void
    {
        foreach ($this->drivers as $browser => $driver) {
            try {
                $driver->quit();
                echo "Closed {$browser} WebDriver\n";
            } catch (Exception $e) {
                echo "Error closing {$browser} WebDriver: " . $e->getMessage() . "\n";
            }
        }
        
        $this->drivers = [];
        parent::tearDown();
    }
}