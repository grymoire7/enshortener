<?php
// server.php - Router for PHP built-in web server
// Usage: php -S localhost:8000 server.php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Admin routes
if (strpos($uri, '/admin') === 0) {
    include __DIR__ . '/admin.php';
    exit;
}

// Static files
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg)$/', $uri)) {
    return false; // Let PHP serve the file
}

// Short URL redirect (everything else goes to index.php)
$_GET['code'] = trim($uri, '/');
include __DIR__ . '/index.php';
