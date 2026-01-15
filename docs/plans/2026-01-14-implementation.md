# URL Shortener Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a self-hosted URL shortener with analytics for personal use on shared hosting.

**Architecture:** PHP application with custom mini-router, SQLite database, and admin interface using Tailwind CSS. All routing goes through two entry points (index.php for redirects, admin.php for dashboard).

**Tech Stack:** PHP 7.4+, SQLite3, Tailwind CSS (CDN), Chart.js (CDN)

---

## Task 1: Project Foundation

**Files:**
- Create: `config.php`
- Create: `lib/db.php`
- Create: `.htaccess`
- Create: `database.sqlite`

**Step 1: Create configuration file**

```php
<?php
// config.php
return [
    'base_url' => 'https://trcy.cc',
    'db_path' => __DIR__ . '/database.sqlite',
    'site_name' => 'trcy.cc',
];
```

**Step 2: Create database connection helper**

```php
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
        return $stmt->fetch();
    }

    public static function fetchAll($sql, $params = []) {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    public static function execute($sql, $params = []) {
        return self::query($sql, $params);
    }

    public static function lastInsertId() {
        return self::$pdo->lastInsertId();
    }
}
```

**Step 3: Create .htaccess for URL rewriting**

```apache
RewriteEngine On
RewriteRule ^([^/]+)/?$ index.php?code=$1 [L,QSA]
RewriteRule ^admin(?:/.*)?$ admin.php [L,QSA]

# Protect sensitive files
<Files "setup.txt">
    Order allow,deny
    Deny from all
</Files>
<FilesMatch "\.(sqlite|db)$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

**Step 4: Create empty SQLite database with schema**

```bash
sqlite3 database.sqlite << 'EOF'
CREATE TABLE urls (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    short_code TEXT UNIQUE NOT NULL COLLATE NOCASE,
    long_url TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    click_count INTEGER DEFAULT 0
);
CREATE INDEX idx_short_code ON urls(short_code);

CREATE TABLE clicks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url_id INTEGER NOT NULL,
    clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    referrer TEXT,
    user_agent TEXT,
    FOREIGN KEY (url_id) REFERENCES urls(id) ON DELETE CASCADE
);
CREATE INDEX idx_url_id ON clicks(url_id);
CREATE INDEX idx_clicked_at ON clicks(clicked_at);

CREATE TABLE settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

INSERT INTO settings (key, value) VALUES ('admin_password_hash', '');
INSERT INTO settings (key, value) VALUES ('base_url', 'https://trcy.cc');
EOF

chmod 666 database.sqlite
```

**Step 5: Commit**

```bash
git add config.php lib/db.php .htaccess database.sqlite
git commit -m "feat: add project foundation - config, database helper, and schema"
```

---

## Task 2: Custom Router

**Files:**
- Create: `router.php`
- Test: Manually test routing

**Step 1: Create the router class**

```php
<?php
// router.php
class Router {
    private $routes = [];

    public function get($pattern, $handler) {
        $this->addRoute('GET', $pattern, $handler);
    }

    public function post($pattern, $handler) {
        $this->addRoute('POST', $pattern, $handler);
    }

    public function delete($pattern, $handler) {
        $this->addRoute('DELETE', $pattern, $handler);
    }

    private function addRoute($method, $pattern, $handler) {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler
        ];
    }

    public function dispatch($uri, $method) {
        // Remove query string
        $uri = strtok($uri, '?');
        // Remove trailing slash
        $uri = rtrim($uri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            // Convert pattern to regex
            $pattern = preg_replace('/:([^\/]+)/', '([^/]+)', $route['pattern']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // Remove full match
                return call_user_func_array($route['handler'], $matches);
            }
        }

        return null; // No match found
    }
}
```

**Step 2: Commit**

```bash
git add router.php
git commit -m "feat: add custom mini-router with RESTful routing"
```

---

## Task 3: Security Layer (CSRF & Auth)

**Files:**
- Create: `lib/csrf.php`
- Create: `lib/auth.php`
- Create: `lib/setup.php`

**Step 1: Create CSRF protection**

```php
<?php
// lib/csrf.php
function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        die('CSRF validation failed');
    }
}
```

**Step 2: Create authentication helper**

```php
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
```

**Step 3: Create initial setup helper**

```php
<?php
// lib/setup.php
function generate_admin_password() {
    global $config;
    require_once __DIR__ . '/db.php';

    $password = bin2hex(random_bytes(16));
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $db = DB::init($config);
    DB::execute('UPDATE settings SET value = ? WHERE key = ?', [$hash, 'admin_password_hash']);

    // Write to setup.txt
    file_put_contents(__DIR__ . '/../setup.txt', "Admin password: {$password}\n");

    return $password;
}

function get_setup_password() {
    $file = __DIR__ . '/../setup.txt';
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (preg_match('/Admin password: (.+)/', $content, $matches)) {
            return $matches[1];
        }
    }
    return null;
}
```

**Step 4: Commit**

```bash
git add lib/csrf.php lib/auth.php lib/setup.php
git commit -m "feat: add security layer - CSRF protection and authentication"
```

---

## Task 4: Short URL Redirect (index.php)

**Files:**
- Create: `index.php`

**Step 1: Create the redirect handler**

```php
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
```

**Step 2: Commit**

```bash
git add index.php
git commit -m "feat: add short URL redirect handler"
```

---

## Task 5: Admin Layout & Views

**Files:**
- Create: `views/layout.php`
- Create: `views/admin_login.php`

**Step 1: Create base admin layout**

```php
<?php
// views/layout.php
function render_layout($title, $content, $flash = null) {
    $setup_warning = setup_file_exists() ? '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4">Warning: setup.txt still exists. Delete it after saving your password!</div>' : '';
    $flash_html = $flash ? '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">' . htmlspecialchars($flash) . '</div>' : '';

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    {$setup_warning}
    {$flash_html}
    {$content}
</body>
</html>
HTML;
}
```

**Step 2: Create login page**

```php
<?php
// views/admin_login.php
function render_login_page($error = '') {
    $error_html = $error ? '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">' . htmlspecialchars($error) . '</div>' : '';

    $content = <<<HTML
<div class="min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold mb-6 text-center">Admin Login</h1>
        {$error_html}
        <form method="POST" action="/admin/login">
            <input type="hidden" name="csrf_token" value="{csrf_token()}">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" name="password" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required autofocus>
            </div>
            <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition">Login</button>
        </form>
    </div>
</div>
HTML;

    require_once __DIR__ . '/layout.php';
    render_layout('Login', $content);
}
```

**Step 3: Commit**

```bash
git add views/layout.php views/admin_login.php
git commit -m "feat: add admin layout and login view"
```

---

## Task 6: Admin Login Handler

**Files:**
- Create: `admin.php` (login routes only)

**Step 1: Create admin.php with login routes**

```php
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
```

**Step 2: Commit**

```bash
git add admin.php
git commit -m "feat: add admin login handler"
```

---

## Task 7: Admin Dashboard Layout

**Files:**
- Create: `views/admin_dashboard.php`
- Create: `views/admin_layout.php`

**Step 1: Create admin layout with sidebar**

```php
<?php
// views/admin_layout.php
function render_admin_layout($title, $content, $active = '', $flash = null) {
    $setup_warning = setup_file_exists() ? '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4">Warning: setup.txt still exists. Delete it after saving your password!</div>' : '';
    $flash_html = $flash ? '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">' . htmlspecialchars($flash) . '</div>' : '';

    $nav_items = [
        '/' => ['label' => 'Dashboard', 'icon' => '📊'],
        '/urls' => ['label' => 'URLs', 'icon' => '🔗'],
        '/settings' => ['label' => 'Settings', 'icon' => '⚙️'],
    ];

    $nav_html = '';
    foreach ($nav_items as $path => $item) {
        $is_active = $active === $path ? 'bg-blue-50 border-r-4 border-blue-500' : 'hover:bg-gray-100';
        $nav_html .= '<a href="/admin' . $path . '" class="flex items-center px-4 py-3 ' . $is_active . '">';
        $nav_html .= '<span class="mr-3">' . $item['icon'] . '</span>';
        $nav_html .= '<span>' . $item['label'] . '</span>';
        $nav_html .= '</a>';
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    {$setup_warning}
    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-white min-h-screen shadow-md">
            <div class="p-4 border-b">
                <h1 class="text-xl font-bold">trcy.cc</h1>
            </div>
            <nav class="mt-4">
                {$nav_html}
            </nav>
            <div class="absolute bottom-0 w-64 p-4 border-t bg-white">
                <form method="POST" action="/admin/logout">
                    <input type="hidden" name="csrf_token" value="{csrf_token()}">
                    <button type="submit" class="w-full text-left px-4 py-2 hover:bg-gray-100 rounded">Logout</button>
                </form>
            </div>
        </aside>

        <!-- Main content -->
        <main class="flex-1 p-8">
            {$flash_html}
            {$content}
        </main>
    </div>
</body>
</html>
HTML;
}
```

**Step 2: Create dashboard view**

```php
<?php
// views/admin_dashboard.php
function render_dashboard($db) {
    // Get stats
    $total_urls = $db->fetch('SELECT COUNT(*) as count FROM urls')['count'];
    $total_clicks = $db->fetch('SELECT SELECT SUM(click_count) as total FROM urls')['total'] ?? 0;
    $clicks_today = $db->fetch('SELECT COUNT(*) as count FROM clicks WHERE DATE(clicked_at) = DATE("now")')['count'];
    $clicks_week = $db->fetch('SELECT COUNT(*) as count FROM clicks WHERE DATE(clicked_at) >= DATE("now", "-7 days")')['count'];

    // Get recent URLs
    $recent_urls = $db->fetchAll('SELECT * FROM urls ORDER BY created_at DESC LIMIT 5');

    $urls_table = '';
    foreach ($recent_urls as $url) {
        $urls_table .= '<tr class="border-b">';
        $urls_table .= '<td class="px-4 py-3"><a href="/admin/analytics/' . $url['id'] . '" class="text-blue-500 hover:underline">' . htmlspecialchars($url['short_code']) . '</a></td>';
        $urls_table .= '<td class="px-4 py-3 truncate max-w-xs">' . htmlspecialchars($url['long_url']) . '</td>';
        $urls_table .= '<td class="px-4 py-3">' . $url['click_count'] . '</td>';
        $urls_table .= '<td class="px-4 py-3">' . date('M j, Y', strtotime($url['created_at'])) . '</td>';
        $urls_table .= '</tr>';
    }

    $content = <<<HTML
<div class="mb-8">
    <h1 class="text-3xl font-bold mb-6">Dashboard</h1>

    <!-- Stats cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="text-gray-500 text-sm">Total URLs</div>
            <div class="text-3xl font-bold">{$total_urls}</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="text-gray-500 text-sm">Total Clicks</div>
            <div class="text-3xl font-bold">{$total_clicks}</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="text-gray-500 text-sm">Clicks Today</div>
            <div class="text-3xl font-bold">{$clicks_today}</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="text-gray-500 text-sm">Clicks This Week</div>
            <div class="text-3xl font-bold">{$clicks_week}</div>
        </div>
    </div>

    <!-- Recent URLs -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b flex justify-between items-center">
            <h2 class="text-xl font-semibold">Recent URLs</h2>
            <a href="/admin/urls" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Create New</a>
        </div>
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="text-left px-4 py-3">Short Code</th>
                    <th class="text-left px-4 py-3">Long URL</th>
                    <th class="text-left px-4 py-3">Clicks</th>
                    <th class="text-left px-4 py-3">Created</th>
                </tr>
            </thead>
            <tbody>
                {$urls_table}
            </tbody>
        </table>
    </div>
</div>
HTML;

    require_once __DIR__ . '/admin_layout.php';
    render_admin_layout('Dashboard', $content, '/');
}
```

**Step 3: Commit**

```bash
git add views/admin_layout.php views/admin_dashboard.php
git commit -m "feat: add admin dashboard with stats and recent URLs"
```

---

## Task 8: Admin Dashboard Route

**Files:**
- Modify: `admin.php`

**Step 1: Add dashboard and logout routes**

Add before dispatch section in admin.php:

```php
// GET / - Dashboard
$router->get('/', function() use ($db) {
    require_admin();
    require_once __DIR__ . '/views/admin_dashboard.php';
    render_dashboard($db);
});

// POST /logout
$router->post('/logout', function() {
    require_csrf();
    admin_logout();
    header('Location: /admin/login');
    exit;
});
```

**Step 2: Commit**

```bash
git add admin.php
git commit -m "feat: add dashboard and logout routes"
```

---

## Task 9: URL Management Interface

**Files:**
- Create: `views/admin_urls.php`

**Step 1: Create URL management view**

```php
<?php
// views/admin_urls.php
function render_urls_page($db, $page = 1, $flash = null) {
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    $total = $db->fetch('SELECT COUNT(*) as count FROM urls')['count'];
    $urls = $db->fetchAll('SELECT * FROM urls ORDER BY created_at DESC LIMIT ? OFFSET ?', [$per_page, $offset]);

    $rows = '';
    foreach ($urls as $url) {
        $rows .= '<tr class="border-b hover:bg-gray-50">';
        $rows .= '<td class="px-4 py-3"><a href="/' . htmlspecialchars($url['short_code']) . '" target="_blank" class="text-blue-500 hover:underline">/' . htmlspecialchars($url['short_code']) . '</a></td>';
        $rows .= '<td class="px-4 py-3 truncate max-w-md"><a href="' . htmlspecialchars($url['long_url']) . '" target="_blank" class="text-gray-600 hover:underline">' . htmlspecialchars($url['long_url']) . '</a></td>';
        $rows .= '<td class="px-4 py-3">' . $url['click_count'] . '</td>';
        $rows .= '<td class="px-4 py-3">' . date('M j, Y', strtotime($url['created_at'])) . '</td>';
        $rows .= '<td class="px-4 py-3">';
        $rows .= '<a href="/admin/analytics/' . $url['id'] . '" class="text-blue-500 hover:underline mr-3">Analytics</a>';
        $rows .= '<button onclick="deleteUrl(' . $url['id'] . ', \'' . htmlspecialchars($url['short_code'], ENT_QUOTES) . '\')" class="text-red-500 hover:underline">Delete</button>';
        $rows .= '</td></tr>';
    }

    $pagination = '';
    if ($total > $per_page) {
        $pages = ceil($total / $per_page);
        $pagination .= '<div class="flex justify-center mt-6 space-x-2">';
        for ($i = 1; $i <= $pages; $i++) {
            $active = $i == $page ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-100';
            $pagination .= '<a href="/admin/urls?page=' . $i . '" class="px-4 py-2 rounded ' . $active . '">' . $i . '</a>';
        }
        $pagination .= '</div>';
    }

    $content = <<<HTML
<div>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">URLs</h1>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Create New URL</button>
    </div>

    <div class="bg-white rounded-lg shadow">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="text-left px-4 py-3">Short Code</th>
                    <th class="text-left px-4 py-3">Long URL</th>
                    <th class="text-left px-4 py-3">Clicks</th>
                    <th class="text-left px-4 py-3">Created</th>
                    <th class="text-left px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                {$rows}
            </tbody>
        </table>
        {$pagination}
    </div>
</div>

<!-- Create Modal -->
<div id="createModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Create Short URL</h2>
        <form method="POST" action="/admin/urls">
            <input type="hidden" name="csrf_token" value="{csrf_token()}">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Long URL</label>
                <input type="url" name="long_url" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required placeholder="https://example.com/very/long/url">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Custom Short Code (optional)</label>
                <input type="text" name="short_code" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="my-custom-code">
                <p class="text-gray-500 text-sm mt-1">Leave empty to auto-generate</p>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg hover:bg-gray-100">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">Create</button>
            </div>
        </form>
    </div>
</div>

<script>
function deleteUrl(id, code) {
    if (confirm('Are you sure you want to delete /' + code + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/urls/' + id + '/delete';
        form.innerHTML = '<input type="hidden" name="csrf_token" value="{csrf_token()}">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
HTML;

    require_once __DIR__ . '/admin_layout.php';
    render_admin_layout('URLs', $content, '/urls', $flash);
}
```

**Step 2: Commit**

```bash
git add views/admin_urls.php
git commit -m "feat: add URL management interface with create and delete"
```

---

## Task 10: URL CRUD Routes

**Files:**
- Modify: `admin.php`

**Step 1: Add URL management routes**

Add before dispatch section:

```php
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
        } while ($db->fetch('SELECT id FROM urls WHERE short_code = ?', [$short_code]));
    }

    // Check for duplicate
    if ($db->fetch('SELECT id FROM urls WHERE short_code = ?', [$short_code])) {
        $_SESSION['flash_error'] = 'Short code already exists';
        header('Location: /admin/urls');
        exit;
    }

    // Create URL
    $db->execute(
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

    $db->execute('DELETE FROM urls WHERE id = ?', [$id]);

    $_SESSION['flash_success'] = 'URL deleted';
    header('Location: /admin/urls');
    exit;
});
```

**Step 2: Commit**

```bash
git add admin.php
git commit -m "feat: add URL CRUD routes (create, delete)"
```

---

## Task 11: Analytics Page

**Files:**
- Create: `views/admin_analytics.php`

**Step 1: Create analytics view**

```php
<?php
// views/admin_analytics.php
function render_analytics_page($db, $url_id) {
    $url = $db->fetch('SELECT * FROM urls WHERE id = ?', [$url_id]);

    if (!$url) {
        echo 'URL not found';
        return;
    }

    // Clicks over time (30 days)
    $clicks_over_time = $db->fetchAll("
        SELECT DATE(clicked_at) as date, COUNT(*) as clicks
        FROM clicks
        WHERE url_id = ? AND clicked_at >= DATE('now', '-30 days')
        GROUP BY DATE(clicked_at)
        ORDER BY date
    ", [$url_id]);

    // Top referrers
    $referrers = $db->fetchAll("
        SELECT referrer, COUNT(*) as clicks
        FROM clicks
        WHERE url_id = ? AND referrer IS NOT NULL AND referrer != ''
        GROUP BY referrer
        ORDER BY clicks DESC
        LIMIT 10
    ", [$url_id]);

    // Recent clicks
    $recent_clicks = $db->fetchAll("
        SELECT clicked_at, referrer, user_agent
        FROM clicks
        WHERE url_id = ?
        ORDER BY clicked_at DESC
        LIMIT 20
    ", [$url_id]);

    // Format clicks data for Chart.js
    $chart_labels = [];
    $chart_data = [];
    foreach ($clicks_over_time as $row) {
        $chart_labels[] = $row['date'];
        $chart_data[] = $row['clicks'];
    }

    // Build referrers table
    $referrers_html = '';
    foreach ($referrers as $ref) {
        $referrers_html .= '<tr class="border-b">';
        $referrers_html .= '<td class="px-4 py-2 truncate max-w-md">' . htmlspecialchars($ref['referrer']) . '</td>';
        $referrers_html .= '<td class="px-4 py-2">' . $ref['clicks'] . '</td>';
        $referrers_html .= '</tr>';
    }

    // Build recent clicks table
    $clicks_html = '';
    foreach ($recent_clicks as $click) {
        $clicks_html .= '<tr class="border-b">';
        $clicks_html .= '<td class="px-4 py-2">' . date('M j, Y g:i A', strtotime($click['clicked_at'])) . '</td>';
        $clicks_html .= '<td class="px-4 py-2 truncate max-w-md">' . htmlspecialchars($click['referrer'] ?? 'Direct') . '</td>';
        $clicks_html .= '<td class="px-4 py-2 truncate max-w-xs text-gray-500">' . htmlspecialchars($click['user_agent'] ?? '') . '</td>';
        $clicks_html .= '</tr>';
    }

    $content = <<<HTML
<div>
    <div class="mb-6">
        <a href="/admin/urls" class="text-blue-500 hover:underline">← Back to URLs</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h1 class="text-2xl font-bold mb-2">/</h1>
        <h1 class="text-2xl font-bold mb-4">{$url['short_code']}</h1>
        <p class="text-gray-600 truncate">{$url['long_url']}</p>
        <div class="mt-4 grid grid-cols-3 gap-4">
            <div>
                <div class="text-gray-500 text-sm">Total Clicks</div>
                <div class="text-2xl font-bold">{$url['click_count']}</div>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Created</div>
                <div class="text-lg">{date('M j, Y', strtotime($url['created_at']))}</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Clicks Over Time (30 days)</h2>
            <canvas id="clicksChart"></canvas>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Top Referrers</h2>
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="text-left px-4 py-2">Referrer</th>
                        <th class="text-left px-4 py-2">Clicks</th>
                    </tr>
                </thead>
                <tbody>
                    {$referrers_html}
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mt-6">
        <h2 class="text-xl font-semibold mb-4">Recent Clicks</h2>
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="text-left px-4 py-2">Time</th>
                    <th class="text-left px-4 py-2">Referrer</th>
                    <th class="text-left px-4 py-2">User Agent</th>
                </tr>
            </thead>
            <tbody>
                {$clicks_html}
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('clicksChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: [{implode(', ', array_map(function($s) { return "'{$s}'"; }, $chart_labels))}],
        datasets: [{
            label: 'Clicks',
            data: [{implode(', ', $chart_data)}],
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>
HTML;

    require_once __DIR__ . '/admin_layout.php';
    render_admin_layout('Analytics: ' . $url['short_code'], $content);
}
```

**Step 2: Commit**

```bash
git add views/admin_analytics.php
git commit -m "feat: add analytics page with charts"
```

---

## Task 12: Analytics Route

**Files:**
- Modify: `admin.php`

**Step 1: Add analytics route**

```php
// GET /analytics/:id - Analytics for a URL
$router->get('/analytics/:id', function($id) use ($db) {
    require_admin();
    require_once __DIR__ . '/views/admin_analytics.php';
    render_analytics_page($db, $id);
});
```

**Step 2: Commit**

```bash
git add admin.php
git commit -m "feat: add analytics route"
```

---

## Task 13: Settings Page

**Files:**
- Create: `views/admin_settings.php`

**Step 1: Create settings view**

```php
<?php
// views/admin_settings.php
function render_settings_page($db, $error = '', $success = '') {
    $base_url = $db->fetch('SELECT value FROM settings WHERE key = ?', ['base_url'])['value'] ?? '';

    $error_html = $error ? '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">' . htmlspecialchars($error) . '</div>' : '';
    $success_html = $success ? '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">' . htmlspecialchars($success) . '</div>' : '';

    $content = <<<HTML
<div>
    <h1 class="text-3xl font-bold mb-6">Settings</h1>

    {$error_html}
    {$success_html}

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Change Password</h2>
        <form method="POST" action="/admin/settings/password" class="max-w-md">
            <input type="hidden" name="csrf_token" value="{csrf_token()}">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Current Password</label>
                <input type="password" name="current_password" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">New Password</label>
                <input type="password" name="new_password" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required minlength="8">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Confirm New Password</label>
                <input type="password" name="confirm_password" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required minlength="8">
            </div>
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">Change Password</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Site Settings</h2>
        <form method="POST" action="/admin/settings/site" class="max-w-md">
            <input type="hidden" name="csrf_token" value="{csrf_token()}">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Base URL</label>
                <input type="url" name="base_url" value="{$base_url}" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">Save Settings</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold mb-4 text-red-600">Danger Zone</h2>
        <p class="text-gray-600 mb-4">Delete all data and reset the application. This cannot be undone.</p>
        <form method="POST" action="/admin/settings/reset" onsubmit="return confirm('Are you sure? This will delete all URLs and data!')">
            <input type="hidden" name="csrf_token" value="{csrf_token()}">
            <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">Reset Everything</button>
        </form>
    </div>
</div>
HTML;

    require_once __DIR__ . '/admin_layout.php';
    render_admin_layout('Settings', $content, '/settings');
}
```

**Step 2: Commit**

```bash
git add views/admin_settings.php
git commit -m "feat: add settings page UI"
```

---

## Task 14: Settings Routes

**Files:**
- Modify: `admin.php`

**Step 1: Add settings routes**

```php
// GET /settings - Settings page
$router->get('/settings', function() use ($db) {
    require_admin();
    $error = $_SESSION['flash_error'] ?? null;
    $success = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_error'], $_SESSION['flash_success']);
    require_once __DIR__ . '/views/admin_settings.php';
    render_settings_page($db, $error, $success);
});

// POST /settings/password - Change password
$router->post('/settings/password', function() use ($db) {
    require_admin();
    require_csrf();

    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Verify current password
    $hash = $db->fetch('SELECT value FROM settings WHERE key = ?', ['admin_password_hash'])['value'];
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
    $db->execute('UPDATE settings SET value = ? WHERE key = ?', [$new_hash, 'admin_password_hash']);

    $_SESSION['flash_success'] = 'Password changed successfully';
    header('Location: /admin/settings');
    exit;
});

// POST /settings/site - Update site settings
$router->post('/settings/site', function() use ($db) {
    require_admin();
    require_csrf();

    $base_url = $_POST['base_url'] ?? '';

    if (!filter_var($base_url, FILTER_VALIDATE_URL)) {
        $_SESSION['flash_error'] = 'Invalid base URL';
        header('Location: /admin/settings');
        exit;
    }

    $db->execute('UPDATE settings SET value = ? WHERE key = ?', [$base_url, 'base_url']);

    $_SESSION['flash_success'] = 'Settings saved';
    header('Location: /admin/settings');
    exit;
});

// POST /settings/reset - Reset everything
$router->post('/settings/reset', function() use ($db) {
    require_admin();
    require_csrf();

    $db->execute('DELETE FROM clicks');
    $db->execute('DELETE FROM urls');
    $db->execute('UPDATE settings SET value = ? WHERE key = ?', ['', 'admin_password_hash']);

    // Redirect to login which will trigger setup
    header('Location: /admin/login');
    exit;
});
```

**Step 2: Commit**

```bash
git add admin.php
git commit -m "feat: add settings routes (password, site config, reset)"
```

---

## Task 15: Flash Message Support

**Files:**
- Modify: `admin.php`

**Step 1: Update routes to show flash messages**

Modify the dashboard route to handle flash messages:

```php
// GET / - Dashboard
$router->get('/', function() use ($db) {
    require_admin();
    $flash = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_success']);
    require_once __DIR__ . '/views/admin_dashboard.php';
    render_dashboard($db, $flash);
});
```

**Step 2: Commit**

```bash
git add admin.php
git commit -m "feat: add flash message support for better UX"
```

---

## Task 16: README Documentation

**Files:**
- Create: `README.md`

**Step 1: Create README**

```markdown
# trcy.cc - URL Shortener

A self-hosted URL shortener with analytics, designed for personal use on shared hosting.

## Features

- Create short URLs with custom slugs
- Track clicks with referrer and user agent analytics
- Clean admin interface
- SQLite database (no separate server needed)
- Dead simple deployment

## Requirements

- PHP 7.4 or higher
- SQLite3 extension
- Apache with .htaccess support (or equivalent)

## Installation

1. Upload all files to your web server
2. Ensure `database.sqlite` is writable (chmod 666)
3. Visit `/admin` in your browser
4. Your admin password will be auto-generated and saved to `setup.txt`
5. Login with the generated password
6. Delete `setup.txt` after saving your password

## Usage

### Creating a URL

1. Login to the admin panel at `/admin`
2. Click "Create New URL"
3. Enter your long URL and optionally a custom short code
4. Click "Create"

### Viewing Analytics

1. Go to the URLs page
2. Click "Analytics" next to any URL
3. View clicks over time, top referrers, and recent clicks

## File Structure

```
/
├── admin.php           # Admin interface entry point
├── index.php           # Short URL redirect handler
├── config.php          # Configuration
├── database.sqlite     # SQLite database
├── lib/                # Helper functions
├── views/              # PHP templates
└── .htaccess           # URL rewriting
```

## Security

- Admin password hashed with bcrypt
- CSRF protection on all forms
- SQL injection prevention with prepared statements
- XSS prevention with output escaping

## License

MIT
```

**Step 2: Commit**

```bash
git add README.md
git commit -m "docs: add README with installation and usage instructions"
```

---

## Task 17: Final Polish & Testing

**Files:**
- Modify: `views/admin_layout.php`
- Test: Manual testing checklist

**Step 1: Add mobile responsive sidebar**

Update the sidebar in admin_layout.php to be mobile-friendly with a toggle:

```php
// Add this script at the end of admin_layout.php before closing body tag:
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('aside');
    const toggle = document.createElement('button');
    toggle.className = 'md:hidden fixed top-4 left-4 z-50 bg-white p-2 rounded shadow';
    toggle.innerHTML = '☰';
    toggle.onclick = function() {
        sidebar.classList.toggle('hidden');
    };
    document.body.appendChild(toggle);
});
</script>
```

**Step 2: Test the application**

Run through this checklist:

- [ ] Visit `/` - should work (no code = not found or empty)
- [ ] Visit `/admin` - should show login or generate password
- [ ] Login with generated password
- [ ] Create a short URL
- [ ] Visit the short URL - should redirect
- [ ] View analytics for the URL
- [ ] Delete the URL
- [ ] Change admin password
- [ ] Update base URL in settings
- [ ] Try accessing without login - should redirect to login
- [ ] Test on mobile browser

**Step 3: Commit**

```bash
git add views/admin_layout.php
git commit -m "feat: add mobile responsive sidebar toggle"
```

---

## Summary

This implementation plan builds the URL shortener in 17 focused tasks:

1. **Foundation** - Config, database helper, SQLite schema
2. **Router** - Custom mini-router for clean routing
3. **Security** - CSRF and authentication layer
4. **Redirect** - Short URL lookup and redirect
5. **Login UI** - Login form and base layout
6. **Login Handler** - Authentication routes
7. **Dashboard** - Admin layout and stats dashboard
8. **Dashboard Route** - Connect dashboard to router
9. **URL Interface** - URL management UI
10. **URL Routes** - Create and delete URL endpoints
11. **Analytics** - Analytics page with Chart.js
12. **Analytics Route** - Connect analytics to router
13. **Settings UI** - Settings page forms
14. **Settings Routes** - Password and config endpoints
15. **Flash Messages** - Better UX feedback
16. **Documentation** - README for deployment
17. **Polish** - Mobile responsive and testing

Each task commits incrementally, following TDD principles where applicable and keeping changes focused.
