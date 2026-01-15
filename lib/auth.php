<?php
// lib/auth.php
function require_admin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: /admin/login');
        exit;
    }
}

function is_admin_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function admin_login($password) {
    global $config;
    require_once __DIR__ . '/db.php';

    $db = DB::init($config);
    $result = DB::fetch('SELECT value FROM settings WHERE key = ?', ['admin_password_hash']);

    if (!$result || empty($result['value'])) {
        return false; // Not set up yet
    }

    if (password_verify($password, $result['value'])) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_logged_in'] = true;
        return true;
    }
    return false;
}

function admin_logout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    session_destroy();
}

function is_setup_complete() {
    global $config;
    require_once __DIR__ . '/db.php';

    $db = DB::init($config);
    $result = DB::fetch('SELECT value FROM settings WHERE key = ?', ['admin_password_hash']);
    return $result && !empty($result['value']);
}

function setup_file_exists() {
    return file_exists(__DIR__ . '/../setup.txt');
}
