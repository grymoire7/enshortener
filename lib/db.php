<?php
// lib/db.php
class DB {
    private static $pdo = null;

    public static function init($config) {
        if (self::$pdo === null) {
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
}
