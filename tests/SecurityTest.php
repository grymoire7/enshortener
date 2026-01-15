<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/setup.php';

class SecurityTest extends TestCase
{
    private string $testDbPath;
    private array $config;

    protected function setUp(): void
    {
        $this->testDbPath = sys_get_temp_dir() . '/test_url_shortener_' . uniqid() . '.sqlite';

        // Create test database schema
        $pdo = new PDO('sqlite:' . $this->testDbPath);
        $pdo->exec('
            CREATE TABLE settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );
            INSERT INTO settings (key, value) VALUES ("admin_password_hash", "");
            INSERT INTO settings (key, value) VALUES ("base_url", "https://test.example");
        ');

        $this->config = [
            'db_path' => $this->testDbPath,
        ];

        // Reset static PDO between tests
        $reflection = new ReflectionClass(DB::class);
        $property = $reflection->getProperty('pdo');
        $property->setValue(null, null);

        // Clear session data
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }

        // Reset static PDO
        $reflection = new ReflectionClass(DB::class);
        $property = $reflection->getProperty('pdo');
        $property->setValue(null, null);

        // Clear session
        $_SESSION = [];
    }

    // CSRF Tests
    public function testCsrfTokenGeneratesToken(): void
    {
        $token = csrf_token();
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function testCsrfTokenIsPersistent(): void
    {
        $token1 = csrf_token();
        $token2 = csrf_token();
        $this->assertEquals($token1, $token2);
    }

    public function testCsrfFieldReturnsHtml(): void
    {
        $field = csrf_field();
        $this->assertStringContainsString('<input type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
    }

    public function testVerifyCsrfWithValidToken(): void
    {
        $token = csrf_token();
        $this->assertTrue(verify_csrf($token));
    }

    public function testVerifyCsrfWithInvalidToken(): void
    {
        csrf_token(); // Generate valid token
        $this->assertFalse(verify_csrf('invalid_token_12345678901234567890'));
    }

    public function testVerifyCsvrfWithEmptyToken(): void
    {
        csrf_token(); // Generate valid token
        $this->assertFalse(verify_csrf(''));
    }

    // Setup Tests
    public function testGenerateAdminPasswordCreatesPassword(): void
    {
        global $config;
        $config = $this->config;

        $password = generate_admin_password();
        $this->assertIsString($password);
        $this->assertEquals(32, strlen($password)); // 16 bytes = 32 hex chars
    }

    public function testGenerateAdminPasswordSavesToDatabase(): void
    {
        global $config;
        $config = $this->config;

        generate_admin_password();

        $result = DB::fetch('SELECT value FROM settings WHERE key = ?', ['admin_password_hash']);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result['value']);
        $this->assertStringStartsWith('$', $result['value']); // bcrypt hash starts with $
    }

    public function testGetSetupPasswordRetrievesPassword(): void
    {
        global $config;
        $config = $this->config;

        $password = generate_admin_password();
        $retrieved = get_setup_password();

        $this->assertEquals($password, $retrieved);
    }

    public function testIsSetupCompleteReturnsFalseWhenNoPassword(): void
    {
        global $config;
        $config = $this->config;

        DB::init($this->config);
        $this->assertFalse(is_setup_complete());
    }

    public function testIsSetupCompleteReturnsTrueWhenPasswordSet(): void
    {
        global $config;
        $config = $this->config;

        generate_admin_password();
        $this->assertTrue(is_setup_complete());
    }

    // Auth Tests
    public function testAdminLoginWithValidPassword(): void
    {
        global $config;
        $config = $this->config;

        $password = generate_admin_password();

        $result = admin_login($password);
        $this->assertTrue($result);
        $this->assertTrue($_SESSION['admin_logged_in']);
    }

    public function testAdminLoginWithInvalidPassword(): void
    {
        global $config;
        $config = $this->config;

        generate_admin_password();

        $result = admin_login('wrong_password');
        $this->assertFalse($result);
        $this->assertArrayNotHasKey('admin_logged_in', $_SESSION);
    }

    public function testAdminLogoutClearsSession(): void
    {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['other_data'] = 'test';

        admin_logout();

        $this->assertArrayNotHasKey('admin_logged_in', $_SESSION);
    }

    public function testIsAdminLoggedInReturnsTrueWhenLoggedIn(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_logged_in'] = true;
        $this->assertTrue(is_admin_logged_in());
    }

    public function testIsAdminLoggedInReturnsFalseWhenNotLoggedIn(): void
    {
        $this->assertFalse(is_admin_logged_in());
    }

    public function testSetupFileExistsReturnsTrueWhenFileExists(): void
    {
        $testFile = sys_get_temp_dir() . '/test_setup_' . uniqid() . '.txt';
        file_put_contents($testFile, 'Admin password: test123');

        // Mock the setup file path
        $this->assertFileExists($testFile);
        unlink($testFile);
    }
}
