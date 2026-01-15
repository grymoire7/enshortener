<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/router.php';

$config = require __DIR__ . '/config.php';
$db = DB::init($config);

$router = new Router();

// Check if setup is needed
if (!is_setup_complete()) {
    require_once __DIR__ . '/lib/setup.php';
    generate_admin_password();
    $password = get_setup_password();
    die("Setup complete! Your admin password is: <strong>{$password}</strong><br>Save it and delete setup.txt");
}

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
    render_dashboard($db, $flash);
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
    render_urls_page($db, $page);
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
    render_analytics_page($db, $id);
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
