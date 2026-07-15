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
    <h1 class="text-3xl font-bold mb-6 text-gray-900 dark:text-gray-100">Settings</h1>

    <!-- Theme selector section -->
    <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Appearance</h2>

        <form id="themeForm" class="max-w-md">
            <div class="space-y-3">
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="radio" name="theme" value="light"
                           class="w-4 h-4 text-blue-500">
                    <span class="text-gray-700 dark:text-gray-300">Light</span>
                </label>

                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="radio" name="theme" value="dark"
                           class="w-4 h-4 text-blue-500">
                    <span class="text-gray-700 dark:text-gray-300">Dark</span>
                </label>

                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="radio" name="theme" value="system"
                           class="w-4 h-4 text-blue-500">
                    <span class="text-gray-700 dark:text-gray-300">System (follow OS preference)</span>
                </label>
            </div>
        </form>
    </div>

    <script>
    // Initialize theme form and handle changes
    (function() {
        const form = document.getElementById('themeForm');
        if (!form) return;

        // Set initial radio button state
        const current = localStorage.getItem('theme') || 'system';
        form.querySelector(`input[value="\${current}"]`).checked = true;

        // Handle theme changes - apply immediately without page reload
        form.addEventListener('change', (e) => {
            if (e.target.name === 'theme') {
                localStorage.setItem('theme', e.target.value);

                // Call the global theme resolution function to apply changes instantly
                if (window.applyTheme) {
                    window.applyTheme();
                }
            }
        });
    })();
    </script>

    {$error_html}
    {$success_html}

    <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Change Password</h2>
        <form method="POST" action="/admin/settings/password" class="max-w-md">
            <input type="hidden" name="csrf_token" value="{$csrf_token}">
            <div class="mb-4">
                <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Current Password</label>
                <div class="relative">
                    <input type="password" name="current_password" class="w-full px-3 py-2 pr-10 border dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-white" required>
                    <button type="button" onclick="let p=this.previousElementSibling;p.type=p.type==='password'?'text':'password'" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Toggle visibility">👁</button>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">New Password</label>
                <div class="relative">
                    <input type="password" name="new_password" class="w-full px-3 py-2 pr-10 border dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-white" required minlength="8">
                    <button type="button" onclick="let p=this.previousElementSibling;p.type=p.type==='password'?'text':'password'" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Toggle visibility">👁</button>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Confirm New Password</label>
                <div class="relative">
                    <input type="password" name="confirm_password" class="w-full px-3 py-2 pr-10 border dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-white" required minlength="8">
                    <button type="button" onclick="let p=this.previousElementSibling;p.type=p.type==='password'?'text':'password'" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Toggle visibility">👁</button>
                </div>
            </div>
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 dark:hover:bg-blue-700">Change Password</button>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold mb-4 text-red-600 dark:text-red-400">Danger Zone</h2>
        <p class="text-gray-600 dark:text-gray-400 mb-4">Delete all data and reset the application. This cannot be undone.</p>
        <form method="POST" action="/admin/settings/reset" onsubmit="return confirm('Are you sure? This will delete all URLs and data!')">
            <input type="hidden" name="csrf_token" value="{$csrf_token}">
            <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 dark:hover:bg-red-700">Reset Everything</button>
        </form>
    </div>
</div>
HTML;

    require_once __DIR__ . '/admin_layout.php';
    return render_admin_layout('Settings', $content, '/settings');
}
