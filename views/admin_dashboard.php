<?php
// views/admin_dashboard.php
function render_dashboard($db, $flash = null) {
    // Get stats
    $total_urls = DB::fetch('SELECT COUNT(*) as count FROM urls')['count'];
    $total_clicks = DB::fetch('SELECT SUM(click_count) as total FROM urls')['total'] ?? 0;
    $clicks_today = DB::fetch('SELECT COUNT(*) as count FROM clicks WHERE DATE(clicked_at) = DATE("now")')['count'];
    $clicks_week = DB::fetch('SELECT COUNT(*) as count FROM clicks WHERE DATE(clicked_at) >= DATE("now", "-7 days")')['count'];

    // Get recent URLs
    $recent_urls = DB::fetchAll('SELECT * FROM urls ORDER BY created_at DESC LIMIT 5');

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
    return render_admin_layout('Dashboard', $content, '/', $flash);
}
