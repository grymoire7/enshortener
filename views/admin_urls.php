<?php
// views/admin_urls.php
function render_urls_page($db, $page = 1, $flash = null) {
    $csrf_token = csrf_token();
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    $total = DB::fetch('SELECT COUNT(*) as count FROM urls')['count'];
    $urls = DB::fetchAll('SELECT * FROM urls ORDER BY created_at DESC LIMIT ? OFFSET ?', [$per_page, $offset]);

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

    $error = $_SESSION['flash_error'] ?? null;
    $success = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_error'], $_SESSION['flash_success']);

    $flash_html = '';
    if ($error) {
        $flash_html = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">' . htmlspecialchars($error) . '</div>';
    }
    if ($success) {
        $flash_html = '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">' . htmlspecialchars($success) . '</div>';
    }

    $content = <<<HTML
<div>
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">URLs</h1>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Create New URL</button>
    </div>

    {$flash_html}

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
<dialog id="createModal" class="rounded-lg shadow-xl p-0 backdrop:bg-black/50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Create Short URL</h2>
        <form method="POST" action="/admin/urls">
            <input type="hidden" name="csrf_token" value="{$csrf_token}">
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
</dialog>

<script>
function deleteUrl(id, code) {
    if (confirm('Are you sure you want to delete /' + code + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/urls/' + id + '/delete';
        form.innerHTML = '<input type="hidden" name="csrf_token" value="{$csrf_token}">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
HTML;

    require_once __DIR__ . '/admin_layout.php';
    render_admin_layout('URLs', $content, '/urls', $flash);
}
