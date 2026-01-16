<?php
// views/admin_layout.php
function render_admin_layout($title, $content, $active = '', $flash = null) {
    $csrf_token = csrf_token();
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
    <link rel="stylesheet" href="/css/compiled.css">
</head>
<body class="bg-gray-50 m-0 p-0">
    <div class="admin-layout">
        <!-- Masthead -->
        <header class="admin-masthead bg-white shadow-sm border-b">
            <div class="flex items-center justify-between px-4 md:px-8 py-4">
                <div class="flex items-center gap-4">
                    <button id="sidebarToggle" class="md:hidden text-gray-600 p-2">☰</button>
                    <h1 class="text-xl font-bold"><a href="/" class="hover:text-blue-600 transition">Enshortener</a></h1>
                </div>
                <form method="POST" action="/admin/logout" class="inline">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <button type="submit" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded hover:bg-gray-100">Logout</button>
                </form>
            </div>
        </header>

        <!-- Sidebar -->
        <aside id="sidebar" class="admin-sidebar bg-white border-r">
            <div class="flex justify-end md:hidden p-2 border-b">
                <button id="sidebarClose" class="text-gray-500">✕</button>
            </div>
            <nav class="py-4">
                {$nav_html}
            </nav>
        </aside>

        <!-- Main content -->
        <main class="admin-main p-4 md:p-8">
            {$flash_html}
            {$content}
        </main>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebarToggle');
        const close = document.getElementById('sidebarClose');

        if (toggle) {
            toggle.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-open');
            });
        }
        if (close) {
            close.addEventListener('click', function() {
                sidebar.classList.remove('mobile-open');
            });
        }
    });
    </script>
</body>
</html>
HTML;
    return true;
}
