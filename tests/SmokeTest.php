<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Smoke tests to catch missing dependencies and syntax errors.
 *
 * These tests verify that all PHP files can be loaded without fatal errors.
 * They would have caught the setup_file_exists() bug immediately.
 */
class SmokeTest extends TestCase
{
    private string $testDbPath;
    private array $config;

    protected function setUp(): void
    {
        // Create a test database for files that need it
        $this->testDbPath = sys_get_temp_dir() . '/smoke_test_' . uniqid() . '.sqlite';
        $this->config = ['db_path' => $this->testDbPath];

        // Set global config for files that use it
        global $config;
        $config = $this->config;

        // Reset DB singleton
        $reflection = new ReflectionClass(DB::class);
        $property = $reflection->getProperty('pdo');
        $property->setValue(null, null);

        // Create database with schema
        DB::createDatabase($this->config);

        // Ensure session is available
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }

        // Reset DB singleton
        $reflection = new ReflectionClass(DB::class);
        $property = $reflection->getProperty('pdo');
        $property->setValue(null, null);

        $_SESSION = [];
    }

    // ==================== Library Files ====================

    public function testLibDbLoads(): void
    {
        // Already loaded in setUp, but verify class exists
        $this->assertTrue(class_exists('DB'));
        $this->assertTrue(method_exists('DB', 'init'));
        $this->assertTrue(method_exists('DB', 'createDatabase'));
    }

    public function testLibCsrfLoads(): void
    {
        require_once __DIR__ . '/../lib/csrf.php';

        $this->assertTrue(function_exists('csrf_token'));
        $this->assertTrue(function_exists('csrf_field'));
        $this->assertTrue(function_exists('verify_csrf'));
        $this->assertTrue(function_exists('require_csrf'));
    }

    public function testLibAuthLoads(): void
    {
        require_once __DIR__ . '/../lib/auth.php';

        $this->assertTrue(function_exists('require_admin'));
        $this->assertTrue(function_exists('is_admin_logged_in'));
        $this->assertTrue(function_exists('admin_login'));
        $this->assertTrue(function_exists('admin_logout'));
        $this->assertTrue(function_exists('is_setup_complete'));
    }

    public function testRouterLoads(): void
    {
        require_once __DIR__ . '/../router.php';

        $this->assertTrue(class_exists('Router'));
        $this->assertTrue(method_exists('Router', 'get'));
        $this->assertTrue(method_exists('Router', 'post'));
        $this->assertTrue(method_exists('Router', 'dispatch'));
    }

    // ==================== View Files ====================
    // These tests verify view files load and define their render functions

    public function testViewLayoutLoads(): void
    {
        require_once __DIR__ . '/../lib/csrf.php';
        require_once __DIR__ . '/../views/layout.php';

        $this->assertTrue(function_exists('render_layout'));
    }

    public function testViewAdminLayoutLoads(): void
    {
        require_once __DIR__ . '/../lib/csrf.php';
        require_once __DIR__ . '/../views/admin_layout.php';

        $this->assertTrue(function_exists('render_admin_layout'));
    }

    public function testViewAdminLoginLoads(): void
    {
        require_once __DIR__ . '/../lib/csrf.php';
        require_once __DIR__ . '/../views/admin_login.php';

        $this->assertTrue(function_exists('render_login_page'));
    }

    public function testViewAdminSetupLoads(): void
    {
        require_once __DIR__ . '/../lib/csrf.php';
        require_once __DIR__ . '/../views/admin_setup.php';

        $this->assertTrue(function_exists('render_setup_page'));
    }

    public function testViewAdminDashboardLoads(): void
    {
        require_once __DIR__ . '/../lib/csrf.php';
        require_once __DIR__ . '/../views/admin_dashboard.php';

        $this->assertTrue(function_exists('render_dashboard'));
    }

    public function testViewAdminUrlsLoads(): void
    {
        require_once __DIR__ . '/../lib/csrf.php';
        require_once __DIR__ . '/../views/admin_urls.php';

        $this->assertTrue(function_exists('render_urls_page'));
    }

    public function testViewAdminAnalyticsLoads(): void
    {
        require_once __DIR__ . '/../lib/csrf.php';
        require_once __DIR__ . '/../views/admin_analytics.php';

        $this->assertTrue(function_exists('render_analytics_page'));
    }

    public function testViewAdminSettingsLoads(): void
    {
        require_once __DIR__ . '/../lib/csrf.php';
        require_once __DIR__ . '/../views/admin_settings.php';

        $this->assertTrue(function_exists('render_settings_page'));
    }

    // ==================== Comprehensive Load Test ====================

    /**
     * This test loads ALL view files together to catch any conflicts
     * or missing dependencies when files are combined.
     */
    public function testAllViewsLoadTogether(): void
    {
        // Load dependencies first
        require_once __DIR__ . '/../lib/csrf.php';
        require_once __DIR__ . '/../lib/auth.php';
        require_once __DIR__ . '/../lib/db.php';

        // Load all views
        $viewFiles = glob(__DIR__ . '/../views/*.php');
        $this->assertNotEmpty($viewFiles, 'Should find view files');

        foreach ($viewFiles as $viewFile) {
            require_once $viewFile;
        }

        // Verify all expected functions exist
        $expectedFunctions = [
            'render_layout',
            'render_admin_layout',
            'render_login_page',
            'render_setup_page',
            'render_dashboard',
            'render_urls_page',
            'render_analytics_page',
            'render_settings_page',
        ];

        foreach ($expectedFunctions as $func) {
            $this->assertTrue(
                function_exists($func),
                "Function {$func} should exist after loading all views"
            );
        }
    }

    // ==================== Config File ====================

    public function testConfigFileLoads(): void
    {
        $config = require __DIR__ . '/../config.php';

        $this->assertIsArray($config);
        $this->assertArrayHasKey('db_path', $config);
    }
}
