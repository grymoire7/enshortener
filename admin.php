<?php
// Configure session before starting
ini_set('session.cookie_path', '/');
ini_set('session.cookie_samesite', 'Lax');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/router.php';

$config = require __DIR__ . '/config.php';

// Initialize database, create if missing
try {
    $db = DB::init($config);
} catch (PDOException $e) {
    try {
        $db = DB::createDatabase($config);
    } catch (PDOException $createError) {
        // Show error page with troubleshooting
        http_response_code(500);
        echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Setup Failed</title>
    <link rel="stylesheet" href="/css/compiled.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md max-w-lg">
        <h1 class="text-2xl font-bold text-red-600 mb-4">Setup Failed - Database Creation Error</h1>
        <p class="mb-4">Possible fixes:</p>
        <ul class="list-disc pl-6 mb-4 space-y-1">
            <li>Check directory is writable (chmod 755 or 775)</li>
            <li>Verify disk space available</li>
            <li>Ensure SQLite3 extension enabled</li>
        </ul>
        <p class="text-gray-600 text-sm">Error: {$createError->getMessage()}</p>
    </div>
</body>
</html>
HTML;
        exit;
    }
}

// Check if setup is needed
$needsSetup = !is_setup_complete() || file_exists(__DIR__ . '/reset.txt');

if ($needsSetup) {
    $isReset = file_exists(__DIR__ . '/reset.txt');

    // Handle setup form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['REQUEST_URI'], '/admin/setup') !== false) {
        require_csrf();

        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $error = '';

        if ($password !== $confirm) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        }

        if ($error) {
            require_once __DIR__ . '/views/admin_setup.php';
            render_setup_page($isReset, $error);
            exit;
        }

        // Hash and save password
        $hash = password_hash($password, PASSWORD_DEFAULT);
        DB::execute('UPDATE settings SET value = ? WHERE key = ?', [$hash, 'admin_password_hash']);

        // Delete reset.txt if exists
        $resetFile = __DIR__ . '/reset.txt';
        if (file_exists($resetFile)) {
            unlink($resetFile);
        }

        // Auto-login
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['flash_success'] = $isReset ? 'Password reset successfully' : 'Setup complete! Welcome to your URL shortener.';

        header('Location: /admin');
        exit;
    }

    // Show setup form (for GET requests or any non-setup POST)
    require_once __DIR__ . '/views/admin_setup.php';
    render_setup_page($isReset);
    exit;
}

$router = new Router();

// GET /login - Show login form
$router->get('/login', function() {
    require_once __DIR__ . '/views/admin_login.php';
    render_login_page();
});

// POST /login - Handle login
$router->post('/login', function() {
    require_csrf();
    $password = $_POST['password'] ?? '';

    if (admin_login($password)) {
        header('Location: /admin');
        exit;
    }

    require_once __DIR__ . '/views/admin_login.php';
    render_login_page('Invalid password');
});

// GET / - Dashboard
$router->get('/', function() use ($db) {
    require_admin();
    $flash = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_success']);
    require_once __DIR__ . '/views/admin_dashboard.php';
    return render_dashboard($db, $flash);
});

// POST /logout
$router->post('/logout', function() {
    require_csrf();
    admin_logout();
    header('Location: /admin/login');
    exit;
});

// GET /urls - List URLs
$router->get('/urls', function() use ($db) {
    require_admin();
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    require_once __DIR__ . '/views/admin_urls.php';
    return render_urls_page($db, $page);
});

// POST /urls - Create URL
$router->post('/urls', function() use ($db, $config) {
    require_admin();
    require_csrf();

    $long_url = $_POST['long_url'] ?? '';
    $short_code = $_POST['short_code'] ?? '';

    // Validate URL
    if (!filter_var($long_url, FILTER_VALIDATE_URL)) {
        $_SESSION['flash_error'] = 'Invalid URL';
        header('Location: /admin/urls');
        exit;
    }

    // Generate short code if not provided
    if (empty($short_code)) {
        do {
            $short_code = substr(bin2hex(random_bytes(4)), 0, 6);
        } while (DB::fetch('SELECT id FROM urls WHERE short_code = ?', [$short_code]));
    }

    // Check for duplicate
    if (DB::fetch('SELECT id FROM urls WHERE short_code = ?', [$short_code])) {
        $_SESSION['flash_error'] = 'Short code already exists';
        header('Location: /admin/urls');
        exit;
    }

    // Create URL
    DB::execute(
        'INSERT INTO urls (short_code, long_url) VALUES (?, ?)',
        [$short_code, $long_url]
    );

    $_SESSION['flash_success'] = 'URL created successfully';
    header('Location: /admin/urls');
    exit;
});

// POST /urls/:id/delete - Delete URL
$router->post('/urls/:id/delete', function($id) use ($db) {
    require_admin();
    require_csrf();

    DB::execute('DELETE FROM urls WHERE id = ?', [$id]);

    $_SESSION['flash_success'] = 'URL deleted';
    header('Location: /admin/urls');
    exit;
});

// GET /analytics/:id - Analytics for a URL
$router->get('/analytics/:id', function($id) use ($db) {
    require_admin();
    require_once __DIR__ . '/views/admin_analytics.php';
    return render_analytics_page($db, $id);
});

// GET /settings - Settings page
$router->get('/settings', function() use ($db) {
    require_admin();
    require_once __DIR__ . '/views/admin_settings.php';
    return render_settings_page($db);
});

// POST /settings/password - Change password
$router->post('/settings/password', function() use ($db) {
    require_admin();
    require_csrf();

    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Verify current password
    $hash = DB::fetch('SELECT value FROM settings WHERE key = ?', ['admin_password_hash'])['value'];
    if (!password_verify($current, $hash)) {
        $_SESSION['flash_error'] = 'Current password is incorrect';
        header('Location: /admin/settings');
        exit;
    }

    if ($new !== $confirm) {
        $_SESSION['flash_error'] = 'New passwords do not match';
        header('Location: /admin/settings');
        exit;
    }

    if (strlen($new) < 8) {
        $_SESSION['flash_error'] = 'Password must be at least 8 characters';
        header('Location: /admin/settings');
        exit;
    }

    $new_hash = password_hash($new, PASSWORD_DEFAULT);
    DB::execute('UPDATE settings SET value = ? WHERE key = ?', [$new_hash, 'admin_password_hash']);

    $_SESSION['flash_success'] = 'Password changed successfully';
    header('Location: /admin/settings');
    exit;
});

// POST /settings/reset - Reset everything
$router->post('/settings/reset', function() use ($db) {
    require_admin();
    require_csrf();

    DB::execute('DELETE FROM clicks');
    DB::execute('DELETE FROM urls');
    DB::execute('UPDATE settings SET value = ? WHERE key = ?', ['', 'admin_password_hash']);

    // Redirect to login which will trigger setup
    header('Location: /admin/login');
    exit;
});

// Dispatch
$uri = strtok($_SERVER['REQUEST_URI'], '?');
$method = $_SERVER['REQUEST_METHOD'];
$uri = preg_replace('#^/admin#', '', $uri) ?: '/';

$result = $router->dispatch($uri, $method);

if ($result === null) {
    // Try without leading slash
    $result = $router->dispatch(ltrim($uri, '/'), $method);
}

if ($result === null) {
    http_response_code(404);
    echo '404 - Not Found';
}
