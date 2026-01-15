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
