<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/db.php';

class DBTest extends TestCase
{
    private string $testDbPath;
    private array $config;

    protected function setUp(): void
    {
        $this->testDbPath = sys_get_temp_dir() . '/test_url_shortener_' . uniqid() . '.sqlite';

        // Create test database schema
        $pdo = new PDO('sqlite:' . $this->testDbPath);
        $pdo->exec('
            CREATE TABLE urls (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                short_code TEXT UNIQUE NOT NULL COLLATE NOCASE,
                long_url TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                click_count INTEGER DEFAULT 0
            );
            CREATE TABLE settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );
        ');

        $this->config = [
            'db_path' => $this->testDbPath,
        ];

        // Reset static PDO between tests
        $reflection = new ReflectionClass(DB::class);
        $property = $reflection->getProperty('pdo');
        $property->setValue(null, null);
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
    }

    public function testInitReturnsPDO(): void
    {
        $pdo = DB::init($this->config);
        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function testInitReturnsSameInstance(): void
    {
        $pdo1 = DB::init($this->config);
        $pdo2 = DB::init($this->config);
        $this->assertSame($pdo1, $pdo2);
    }

    public function testFetchReturnsSingleRow(): void
    {
        DB::init($this->config);

        // Insert test data
        DB::execute('INSERT INTO settings (key, value) VALUES (?, ?)', ['test_key', 'test_value']);

        $result = DB::fetch('SELECT * FROM settings WHERE key = ?', ['test_key']);

        $this->assertIsArray($result);
        $this->assertEquals('test_key', $result['key']);
        $this->assertEquals('test_value', $result['value']);
    }

    public function testFetchReturnsFalseWhenNoMatch(): void
    {
        DB::init($this->config);

        $result = DB::fetch('SELECT * FROM settings WHERE key = ?', ['nonexistent']);

        $this->assertFalse($result);
    }

    public function testFetchAllReturnsMultipleRows(): void
    {
        DB::init($this->config);

        // Insert test data
        DB::execute('INSERT INTO settings (key, value) VALUES (?, ?)', ['key1', 'value1']);
        DB::execute('INSERT INTO settings (key, value) VALUES (?, ?)', ['key2', 'value2']);
        DB::execute('INSERT INTO settings (key, value) VALUES (?, ?)', ['key3', 'value3']);

        $results = DB::fetchAll('SELECT * FROM settings ORDER BY key');

        $this->assertCount(3, $results);
        $this->assertEquals('key1', $results[0]['key']);
        $this->assertEquals('key2', $results[1]['key']);
        $this->assertEquals('key3', $results[2]['key']);
    }

    public function testExecuteRunsQuery(): void
    {
        DB::init($this->config);

        DB::execute('INSERT INTO settings (key, value) VALUES (?, ?)', ['test', 'value']);

        $result = DB::fetch('SELECT * FROM settings WHERE key = ?', ['test']);
        $this->assertIsArray($result);
        $this->assertEquals('value', $result['value']);
    }

    public function testLastInsertIdReturnsId(): void
    {
        DB::init($this->config);

        DB::execute('INSERT INTO settings (key, value) VALUES (?, ?)', ['test', 'value']);
        $id = DB::lastInsertId();

        $this->assertEquals(1, $id);
    }

    public function testLastInsertIdIncrements(): void
    {
        DB::init($this->config);

        DB::execute('INSERT INTO settings (key, value) VALUES (?, ?)', ['key1', 'value1']);
        $id1 = DB::lastInsertId();

        DB::execute('INSERT INTO settings (key, value) VALUES (?, ?)', ['key2', 'value2']);
        $id2 = DB::lastInsertId();

        $this->assertEquals(1, $id1);
        $this->assertEquals(2, $id2);
    }

    public function testQueryReturnsStatement(): void
    {
        DB::init($this->config);

        DB::execute('INSERT INTO settings (key, value) VALUES (?, ?)', ['test', 'value']);

        $stmt = DB::query('SELECT * FROM settings WHERE key = ?', ['test']);

        $this->assertInstanceOf(PDOStatement::class, $stmt);
        $this->assertEquals('test', $stmt->fetch()['key']);
    }
}
