<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';

$config = require __DIR__ . '/config.php';

try {
    $db = DB::init($config);
} catch (PDOException $e) {
    // Database missing - show friendly setup message
    http_response_code(503);
    echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Setup Required</title>
    <link rel="stylesheet" href="/css/compiled.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md max-w-md text-center">
        <h1 class="text-2xl font-bold mb-4">URL Shortener</h1>
        <p class="text-gray-600 mb-6">This shortener hasn't been configured yet.</p>
        <a href="/admin" class="inline-block bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition">Visit /admin to complete setup</a>
    </div>
</body>
</html>
HTML;
    exit;
}

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
