<?php
// views/layout.php
function render_layout($title, $content, $flash = null) {
    $flash_html = $flash ? '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">' . htmlspecialchars($flash) . '</div>' : '';

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - Admin</title>
    <link rel="stylesheet" href="/css/compiled.css">
    <script>
    (function() {
        // Read saved preference, default to 'system'
        const saved = localStorage.getItem('theme') || 'system';

        // Check OS preference
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        // Resolve actual theme
        const isDark = saved === 'dark' || (saved === 'system' && prefersDark);

        // Apply before paint
        document.documentElement.classList.toggle('dark', isDark);

        // Listen for OS preference changes (only matters if saved === 'system')
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (localStorage.getItem('theme') === 'system') {
                document.documentElement.classList.toggle('dark', e.matches);
                // Optional: Update Chart.js instances if they exist
                if (window.updateChartColors) {
                    window.updateChartColors();
                }
            }
        });
    })();
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    {$flash_html}
    {$content}
</body>
</html>
HTML;
}
