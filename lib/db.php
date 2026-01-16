<?php
// lib/db.php
class DB {
    private static $pdo = null;

    public static function init($config) {
        if (self::$pdo === null) {
            if (!file_exists($config['db_path'])) {
                throw new PDOException('Database file not found: ' . $config['db_path']);
            }
            self::$pdo = new PDO('sqlite:' . $config['db_path']);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        return self::$pdo;
    }

    public static function query($sql, $params = []) {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch($sql, $params = []) {
        $stmt = self::query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function fetchAll($sql, $params = []) {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function execute($sql, $params = []) {
        return self::query($sql, $params);
    }

    public static function lastInsertId() {
        return self::$pdo->lastInsertId();
    }

    public static function createDatabase($config) {
        $pdo = new PDO('sqlite:' . $config['db_path']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Create schema
        $pdo->exec('
            CREATE TABLE urls (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                short_code TEXT UNIQUE NOT NULL COLLATE NOCASE,
                long_url TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                click_count INTEGER DEFAULT 0
            )
        ');
        $pdo->exec('CREATE INDEX idx_short_code ON urls(short_code)');

        $pdo->exec('
            CREATE TABLE clicks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url_id INTEGER NOT NULL,
                clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                referrer TEXT,
                user_agent TEXT,
                FOREIGN KEY (url_id) REFERENCES urls(id) ON DELETE CASCADE
            )
        ');
        $pdo->exec('CREATE INDEX idx_url_id ON clicks(url_id)');
        $pdo->exec('CREATE INDEX idx_clicked_at ON clicks(clicked_at)');

        $pdo->exec('
            CREATE TABLE settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )
        ');

        // Insert initial settings with empty password hash
        $stmt = $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)');
        $stmt->execute(['admin_password_hash', '']);

        self::$pdo = $pdo;
        return $pdo;
    }
}
