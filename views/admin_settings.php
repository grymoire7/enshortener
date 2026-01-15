<?php
// views/admin_settings.php
function render_settings_page($db) {
    $csrf_token = csrf_token();

    $error = $_SESSION['flash_error'] ?? null;
    $success = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_error'], $_SESSION['flash_success']);

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
            <input type="hidden" name="csrf_token" value="{$csrf_token}">
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

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold mb-4 text-red-600">Danger Zone</h2>
        <p class="text-gray-600 mb-4">Delete all data and reset the application. This cannot be undone.</p>
        <form method="POST" action="/admin/settings/reset" onsubmit="return confirm('Are you sure? This will delete all URLs and data!')">
            <input type="hidden" name="csrf_token" value="{$csrf_token}">
            <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">Reset Everything</button>
        </form>
    </div>
</div>
HTML;

    require_once __DIR__ . '/admin_layout.php';
    return render_admin_layout('Settings', $content, '/settings');
}
