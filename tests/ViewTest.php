<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * View rendering tests - actually call render functions to catch runtime errors.
 *
 * These tests would have caught the setup_file_exists() bug because they
 * actually execute the render functions, not just load them.
 */
class ViewTest extends TestCase
{
    private string $testDbPath;
    private array $config;

    protected function setUp(): void
    {
        $this->testDbPath = sys_get_temp_dir() . '/view_test_' . uniqid() . '.sqlite';
        $this->config = ['db_path' => $this->testDbPath];

        global $config;
        $config = $this->config;

        // Reset DB singleton
        $reflection = new ReflectionClass(DB::class);
        $property = $reflection->getProperty('pdo');
        $property->setValue(null, null);

        // Create database
        require_once __DIR__ . '/../lib/db.php';
        DB::createDatabase($this->config);

        // Load dependencies
        require_once __DIR__ . '/../lib/csrf.php';
        require_once __DIR__ . '/../lib/auth.php';

        // Session setup
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

        $reflection = new ReflectionClass(DB::class);
        $property = $reflection->getProperty('pdo');
        $property->setValue(null, null);

        $_SESSION = [];
    }

    // ==================== Base Layout ====================

    public function testRenderLayoutProducesHtml(): void
    {
        require_once __DIR__ . '/../views/layout.php';

        ob_start();
        render_layout('Test Title', '<p>Test content</p>');
        $output = ob_get_clean();

        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('Test Title', $output);
        $this->assertStringContainsString('<p>Test content</p>', $output);
    }

    public function testRenderLayoutWithFlash(): void
    {
        require_once __DIR__ . '/../views/layout.php';

        ob_start();
        render_layout('Test', '<p>Content</p>', 'Success message');
        $output = ob_get_clean();

        $this->assertStringContainsString('Success message', $output);
        $this->assertStringContainsString('bg-green-100', $output);
    }

    // ==================== Admin Layout ====================

    public function testRenderAdminLayoutProducesHtml(): void
    {
        require_once __DIR__ . '/../views/admin_layout.php';

        ob_start();
        render_admin_layout('Dashboard', '<p>Dashboard content</p>', '/');
        $output = ob_get_clean();

        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('Dashboard', $output);
        $this->assertStringContainsString('<p>Dashboard content</p>', $output);
        $this->assertStringContainsString('Enshortener', $output);
    }

    public function testRenderAdminLayoutWithFlash(): void
    {
        require_once __DIR__ . '/../views/admin_layout.php';

        ob_start();
        render_admin_layout('Test', '<p>Content</p>', '/', 'Flash message');
        $output = ob_get_clean();

        $this->assertStringContainsString('Flash message', $output);
    }

    public function testRenderAdminLayoutNavigation(): void
    {
        require_once __DIR__ . '/../views/admin_layout.php';

        ob_start();
        render_admin_layout('Test', '<p>Content</p>', '/urls');
        $output = ob_get_clean();

        $this->assertStringContainsString('/admin/', $output);
        $this->assertStringContainsString('/admin/urls', $output);
        $this->assertStringContainsString('/admin/settings', $output);
    }

    // ==================== Login Page ====================

    public function testRenderLoginPageProducesForm(): void
    {
        require_once __DIR__ . '/../views/admin_login.php';

        ob_start();
        render_login_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('type="password"', $output);
        $this->assertStringContainsString('csrf_token', $output);
        $this->assertStringContainsString('/admin/login', $output);
    }

    public function testRenderLoginPageWithError(): void
    {
        require_once __DIR__ . '/../views/admin_login.php';

        ob_start();
        render_login_page('Invalid password');
        $output = ob_get_clean();

        $this->assertStringContainsString('Invalid password', $output);
        $this->assertStringContainsString('bg-red-100', $output);
    }

    // ==================== Setup Page ====================

    public function testRenderSetupPageProducesForm(): void
    {
        require_once __DIR__ . '/../views/admin_setup.php';

        ob_start();
        render_setup_page(false);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('type="password"', $output);
        $this->assertStringContainsString('confirm_password', $output);
        $this->assertStringContainsString('Welcome', $output);
        $this->assertStringContainsString('/admin/setup', $output);
    }

    public function testRenderSetupPageForReset(): void
    {
        require_once __DIR__ . '/../views/admin_setup.php';

        ob_start();
        render_setup_page(true);
        $output = ob_get_clean();

        $this->assertStringContainsString('Reset', $output);
    }

    public function testRenderSetupPageWithError(): void
    {
        require_once __DIR__ . '/../views/admin_setup.php';

        ob_start();
        render_setup_page(false, 'Passwords do not match');
        $output = ob_get_clean();

        $this->assertStringContainsString('Passwords do not match', $output);
        $this->assertStringContainsString('bg-red-100', $output);
    }

    // ==================== Dashboard ====================

    public function testRenderDashboardProducesHtml(): void
    {
        require_once __DIR__ . '/../views/admin_dashboard.php';

        $db = DB::init($this->config);

        ob_start();
        render_dashboard($db);
        $output = ob_get_clean();

        $this->assertStringContainsString('Dashboard', $output);
        $this->assertStringContainsString('Total URLs', $output);
        $this->assertStringContainsString('Total Clicks', $output);
    }

    // ==================== URLs Page ====================

    public function testRenderUrlsPageProducesHtml(): void
    {
        require_once __DIR__ . '/../views/admin_urls.php';

        $db = DB::init($this->config);

        ob_start();
        render_urls_page($db, 1);
        $output = ob_get_clean();

        $this->assertStringContainsString('URLs', $output);
        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('long_url', $output);
    }

    // ==================== Settings Page ====================

    public function testRenderSettingsPageProducesHtml(): void
    {
        require_once __DIR__ . '/../views/admin_settings.php';

        $db = DB::init($this->config);

        ob_start();
        render_settings_page($db);
        $output = ob_get_clean();

        $this->assertStringContainsString('Settings', $output);
        $this->assertStringContainsString('Change Password', $output);
        $this->assertStringContainsString('current_password', $output);
    }

    // ==================== Analytics Page ====================

    public function testRenderAnalyticsPageWithValidUrl(): void
    {
        require_once __DIR__ . '/../views/admin_analytics.php';

        $db = DB::init($this->config);

        // Create a test URL
        DB::execute(
            'INSERT INTO urls (short_code, long_url) VALUES (?, ?)',
            ['test123', 'https://example.com']
        );
        $urlId = DB::lastInsertId();

        ob_start();
        render_analytics_page($db, $urlId);
        $output = ob_get_clean();

        $this->assertStringContainsString('Analytics', $output);
        $this->assertStringContainsString('test123', $output);
    }
}
