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
    <div class="flex md:hidden">
        <button id="sidebarToggle" class="fixed top-4 left-4 z-50 bg-white p-2 rounded shadow">☰</button>
    </div>
    <div class="flex">
        <!-- Sidebar -->
        <aside id="sidebar" class="hidden md:block w-64 bg-white min-h-screen shadow-md fixed md:relative z-40">
            <div class="p-4 border-b flex justify-between items-center">
                <h1 class="text-xl font-bold">trcy.cc</h1>
                <button id="sidebarClose" class="md:hidden text-gray-500">✕</button>
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
        <main class="flex-1 p-4 md:p-8 w-full">
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
                sidebar.classList.toggle('hidden');
                sidebar.classList.toggle('fixed');
                sidebar.classList.toggle('inset-0');
            });
        }
        if (close) {
            close.addEventListener('click', function() {
                sidebar.classList.add('hidden');
            });
        }
    });
    </script>
</body>
</html>
HTML;
}
