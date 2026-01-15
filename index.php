<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';

$config = require __DIR__ . '/config.php';
$db = DB::init($config);

$code = $_GET['code'] ?? '';

if (empty($code)) {
    // No code provided, show home page or 404
    http_response_code(404);
    die('404 - Not Found');
}

// Look up the URL
$url = DB::fetch(
    'SELECT * FROM urls WHERE short_code = ? COLLATE NOCASE',
    [$code]
);

if (!$url) {
    http_response_code(404);
    die('404 - Short URL not found');
}

// Record the click
DB::execute(
    'INSERT INTO clicks (url_id, referrer, user_agent) VALUES (?, ?, ?)',
    [
        $url['id'],
        $_SERVER['HTTP_REFERER'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]
);

// Increment click count
DB::execute('UPDATE urls SET click_count = click_count + 1 WHERE id = ?', [$url['id']]);

// Redirect
header("Location: {$url['long_url']}", true, 301);
exit;
