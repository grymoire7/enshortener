<?php
// views/admin_setup.php
function render_setup_page($isReset = false, $error = '') {
    $csrf_token = csrf_token();
    $title = $isReset ? 'Reset Admin Password' : 'Welcome! Set Admin Password';
    $error_html = $error ? '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">' . htmlspecialchars($error) . '</div>' : '';

    $content = <<<HTML
<div class="min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold mb-6 text-center">{$title}</h1>
        {$error_html}
        <form method="POST" action="/admin/setup">
            <input type="hidden" name="csrf_token" value="{$csrf_token}">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" name="password" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required autofocus minlength="8">
                <p class="text-gray-500 text-sm mt-1">Minimum 8 characters</p>
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Confirm Password</label>
                <input type="password" name="confirm_password" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required minlength="8">
            </div>
            <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition">Set Password</button>
        </form>
    </div>
</div>
HTML;

    require_once __DIR__ . '/layout.php';
    render_layout($isReset ? 'Reset Password' : 'Setup', $content);
}
